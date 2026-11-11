<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useToast } from "vue-toastification";
import ErpField from "@/components/erp/ErpField.vue";
import {
  AcademyClassesApi,
  type AcademyClassRecord,
} from "@/api/academyClasses";
import {
  AcademyEnrollmentsApi,
  type AcademyEnrollmentRecord,
} from "@/api/academyEnrollments";
import {
  AcademyCertificatesApi,
  type AcademyCertificateRecord,
  type AcademyCertificateTemplate,
} from "@/api/academyCertificates";
import { apiFieldErrors } from "@/utils/apiErrors";

const props = defineProps<{
  modelValue: boolean;
  mode: "issue" | "bulk" | "revoke" | "template";
  certificate?: AcademyCertificateRecord | null;
  template?: AcademyCertificateTemplate | null;
}>();
const emit = defineEmits<{
  "update:modelValue": [value: boolean];
  saved: [];
}>();
const { t } = useI18n();
const toast = useToast();
const saving = ref(false);
const errors = ref<Record<string, string>>({});
const classes = ref<AcademyClassRecord[]>([]);
const enrollments = ref<AcademyEnrollmentRecord[]>([]);
const selectedClass = ref<number | null>(null);
const selectedEnrollments = ref<number[]>([]);
const selectedEnrollment = ref<number | null>(null);
const revokeReason = ref("");
const form = reactive({
  name: "",
  title: "",
  body: "",
  signatory_title: "",
  is_active: true,
});
const placeholderOpen = ref(false);
const placeholderSearch = ref("");
const bodyField = ref<{ $el?: Element } | null>(null);
const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});
const title = computed(
  () =>
    ({
      issue: t("academy.certificates.issue"),
      bulk: t("academy.certificates.bulkIssue"),
      revoke: t("academy.certificates.revoke"),
      template: t("academy.certificates.editTemplate"),
    })[props.mode],
);
const placeholders = computed(() =>
  (
    props.template?.tokens ?? [
      "student_name",
      "father_name",
      "student_id",
      "program",
      "class",
      "teachers",
      "start_date",
      "end_date",
      "completion_date",
      "certificate_number",
      "issue_date",
    ]
  ).map((token) => ({
    token,
    label: token
      .replaceAll("_", " ")
      .replace(/\b\w/g, (letter) => letter.toUpperCase()),
  })),
);
const filteredPlaceholders = computed(() => {
  const query = placeholderSearch.value.trim().toLowerCase();
  return query
    ? placeholders.value.filter(
        (field) =>
          field.token.includes(query) ||
          field.label.toLowerCase().includes(query),
      )
    : placeholders.value;
});
const enrollmentTitle = (row: AcademyEnrollmentRecord) =>
  `${row.student?.name ?? row.applicant?.name ?? ""} · ${row.class?.name ?? ""}`;
const enrollmentName = (row: AcademyEnrollmentRecord) =>
  row.student?.name ?? row.applicant?.name ?? "";

watch(
  () => props.modelValue,
  async (open) => {
    if (!open) return;
    errors.value = {};
    selectedEnrollments.value = [];
    selectedEnrollment.value = null;
    revokeReason.value = "";
    if (props.template)
      Object.assign(form, {
        name: props.template.name,
        title: props.template.title,
        body: props.template.body,
        signatory_title: props.template.signatory_title ?? "",
        is_active: props.template.is_active,
      });
    if (props.mode === "issue" || props.mode === "bulk") {
      try {
        classes.value = await AcademyClassesApi.list({ is_active: true });
        selectedClass.value =
          props.mode === "bulk" ? (classes.value[0]?.id ?? null) : null;
        await loadEnrollments();
      } catch {
        /* API notifier owns errors. */
      }
    }
  },
);
async function loadEnrollments() {
  try {
    const response = await AcademyEnrollmentsApi.list({
      status: "completed",
      class_id:
        props.mode === "bulk" ? (selectedClass.value ?? undefined) : undefined,
      per_page: 100,
    });
    enrollments.value = response.data;
  } catch {
    /* API notifier owns errors. */
  }
}
function insertPlaceholder(token: string) {
  const snippet = `{{${token}}}`;
  const textarea = bodyField.value?.$el?.querySelector(
    "textarea",
  ) as HTMLTextAreaElement | null;
  if (!textarea) {
    form.body += snippet;
  } else {
    const start = textarea.selectionStart ?? form.body.length;
    const end = textarea.selectionEnd ?? form.body.length;
    form.body = `${form.body.slice(0, start)}${snippet}${form.body.slice(end)}`;
    void nextTick(() => {
      textarea.focus();
      textarea.setSelectionRange(
        start + snippet.length,
        start + snippet.length,
      );
    });
  }
  placeholderOpen.value = false;
  placeholderSearch.value = "";
}
async function save() {
  saving.value = true;
  errors.value = {};
  try {
    if (props.mode === "revoke") {
      if (!props.certificate) return;
      await AcademyCertificatesApi.revoke(
        props.certificate.id,
        revokeReason.value,
      );
      toast.success(t("academy.certificates.revokedDone"));
    } else if (props.mode === "template") {
      if (!props.template) return;
      await AcademyCertificatesApi.updateTemplate(props.template.id, {
        name: form.name,
        title: form.title,
        body: form.body,
        signatory_title: form.signatory_title || null,
        is_active: form.is_active,
      });
      toast.success(t("academy.certificates.templateSaved"));
    } else if (props.mode === "bulk") {
      await AcademyCertificatesApi.issue({
        class_id: selectedClass.value ?? undefined,
        enrollment_ids: selectedEnrollments.value,
      });
      toast.success(t("academy.certificates.bulkSaved"));
    } else {
      if (!selectedEnrollment.value) return;
      await AcademyCertificatesApi.issue({
        academy_enrollment_id: selectedEnrollment.value,
      });
      toast.success(t("academy.certificates.issued"));
    }
    visible.value = false;
    emit("saved");
  } catch (error) {
    errors.value = apiFieldErrors(error);
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <VDialog
    v-model="visible"
    max-width="980"
    :persistent="saving"
    scrollable
    scroll-strategy="block"
    content-class="academy-certificate-dialog"
    ><VCard class="academy-certificate-card app-form-dialog"
      ><VForm class="app-form" @submit.prevent="save">
        <VCardTitle class="academy-certificate__header px-5 py-4"
          ><span class="text-h5 font-weight-bold">{{ title }}</span
          ><VSpacer /><VBtn
            icon="tabler-x"
            variant="tonal"
            color="error"
            class="academy-certificate__close"
            :disabled="saving"
            :aria-label="t('common.actions.close')"
            @click="visible = false"
        /></VCardTitle>
        <VCardText class="academy-certificate__body pa-5 pa-sm-6">
          <template v-if="mode === 'issue'"
            ><ErpField
              v-model="selectedEnrollment"
              kind="select"
              :items="enrollments"
              :item-title="enrollmentTitle"
              item-value="id"
              :label="t('academy.certificates.completedEnrollment')"
              :placeholder="t('academy.certificates.completedEnrollment')"
              :error-messages="errors.academy_enrollment_id"
            /><VAlert type="info" variant="tonal" class="mt-4">{{
              t("academy.certificates.issueHint")
            }}</VAlert></template
          >
          <template v-else-if="mode === 'bulk'"
            ><VRow
              ><VCol cols="12" md="6"
                ><ErpField
                  v-model="selectedClass"
                  kind="select"
                  :items="classes"
                  item-title="name"
                  item-value="id"
                  :label="t('academy.fields.class')"
                  :placeholder="t('academy.fields.class')"
                  clearable
                  @update:model-value="loadEnrollments" /></VCol
              ><VCol cols="12" md="6"
                ><ErpField
                  v-model="selectedEnrollments"
                  kind="autocomplete"
                  :items="enrollments"
                  :item-title="enrollmentName"
                  item-value="id"
                  :label="t('academy.certificates.completedEnrollments')"
                  :placeholder="t('academy.certificates.completedEnrollments')"
                  multiple
                  chips
                  closable-chips /></VCol></VRow
            ><VAlert type="info" variant="tonal" class="mt-4">{{
              t("academy.certificates.bulkHint")
            }}</VAlert></template
          >
          <template v-else-if="mode === 'revoke'"
            ><VAlert type="warning" variant="tonal" class="mb-4"
              >{{ certificate?.certificate_number }} ·
              {{ certificate?.student?.name }}</VAlert
            ><ErpField
              v-model="revokeReason"
              kind="textarea"
              :label="t('academy.certificates.revokeReason')"
              :placeholder="t('academy.certificates.revokeReason')"
              :error-messages="errors.reason"
              rows="3"
          /></template>
          <template v-else
            ><VRow
              ><VCol cols="12" md="4"
                ><ErpField
                  v-model="form.name"
                  :label="t('academy.fields.name')"
                  :placeholder="t('academy.fields.name')"
                  :error-messages="errors.name" /></VCol
              ><VCol cols="12" md="4"
                ><ErpField
                  v-model="form.title"
                  :label="t('academy.certificates.templateTitle')"
                  :placeholder="t('academy.certificates.templateTitle')"
                  :error-messages="errors.title" /></VCol
              ><VCol cols="12" md="4"
                ><ErpField
                  v-model="form.signatory_title"
                  :label="t('academy.certificates.signatoryTitle')"
                  :placeholder="
                    t('academy.certificates.signatoryTitle')
                  " /></VCol
              ><VCol cols="12"
                ><div class="academy-certificate__body-toolbar">
                  <VMenu
                    v-model="placeholderOpen"
                    :close-on-content-click="false"
                    location="bottom start"
                    max-width="520"
                    ><template #activator="{ props: menuProps }"
                      ><VBtn
                        v-bind="menuProps"
                        variant="tonal"
                        color="secondary"
                        size="small"
                        prepend-icon="tabler-braces"
                        >{{
                          t("pages.whatsapp.editor.insertPlaceholder")
                        }}</VBtn
                      ></template
                    ><VCard
                      ><VCardText class="pb-0"
                        ><VTextField
                          v-model="placeholderSearch"
                          density="compact"
                          hide-details
                          clearable
                          autofocus
                          :placeholder="
                            t('pages.whatsapp.editor.searchPlaceholders')
                          "
                          prepend-inner-icon="tabler-search" /></VCardText
                      ><VList
                        density="compact"
                        max-height="280"
                        class="overflow-y-auto"
                        ><VListSubheader>{{
                          t("pages.whatsapp.editor.placeholders")
                        }}</VListSubheader
                        ><VListItem
                          v-for="field in filteredPlaceholders"
                          :key="field.token"
                          :title="field.label"
                          :subtitle="`{{${field.token}}}`"
                          @click="insertPlaceholder(field.token)" /><VListItem
                          v-if="!filteredPlaceholders.length"
                          :title="
                            t('common.messages.noMatchingRecords')
                          " /></VList></VCard
                  ></VMenu>
                </div>
                <ErpField
                  ref="bodyField"
                  v-model="form.body"
                  kind="textarea"
                  :label="t('academy.certificates.templateBody')"
                  :placeholder="t('academy.certificates.templateBody')"
                  :error-messages="errors.body"
                  rows="8" /></VCol
              ><VCol cols="12"
                ><VSwitch
                  v-model="form.is_active"
                  color="primary"
                  :label="t('academy.fields.active')"
                  hide-details /></VCol></VRow
          ></template>
        </VCardText>
        <VCardActions class="academy-certificate__footer px-5 py-4"
          ><VSpacer /><VBtn
            color="secondary"
            variant="flat"
            class="academy-certificate__cancel"
            :disabled="saving"
            @click="visible = false"
            >{{ t("common.actions.cancel") }}</VBtn
          ><VBtn
            color="primary"
            variant="flat"
            type="submit"
            :loading="saving"
            :disabled="mode === 'issue' && !selectedEnrollment"
            >{{ t("common.actions.save") }}</VBtn
          ></VCardActions
        >
      </VForm></VCard
    ></VDialog
  >
</template>

<style scoped>
.academy-certificate-card {
  overflow: hidden;
}
.academy-certificate__header,
.academy-certificate__footer {
  display: flex;
  align-items: center;
  background: color-mix(
    in srgb,
    rgb(var(--v-theme-surface)) 94%,
    rgb(var(--v-theme-on-surface)) 6%
  );
}
.academy-certificate__header {
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.07);
}
.academy-certificate__footer {
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.07);
}
.academy-certificate__close {
  border: 1px solid rgba(var(--v-theme-error), 0.38) !important;
  border-radius: 999px !important;
  color: rgb(var(--v-theme-error)) !important;
}
.academy-certificate__close:hover {
  background: rgba(var(--v-theme-error), 0.18) !important;
}
.academy-certificate__cancel {
  border: 1px solid rgba(var(--v-theme-secondary), 0.45) !important;
  background: rgba(var(--v-theme-secondary), 0.12) !important;
  color: rgb(var(--v-theme-secondary)) !important;
}
.academy-certificate__cancel:hover {
  background: rgb(var(--v-theme-secondary)) !important;
  color: rgb(var(--v-theme-on-secondary)) !important;
}
.academy-certificate__body-toolbar {
  display: flex;
  justify-content: flex-end;
  margin-block-end: 0.6rem;
}
:global(.v-overlay:has(.academy-certificate-dialog) .v-overlay__scrim) {
  background: rgba(var(--v-theme-on-surface), 0.28) !important;
  backdrop-filter: blur(7px);
  opacity: 1 !important;
}
@media (max-width: 599px) {
  .academy-certificate__header,
  .academy-certificate__footer,
  .academy-certificate__body {
    padding-inline: 1rem !important;
  }
}
</style>
