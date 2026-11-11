<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useToast } from "vue-toastification";
import ConfirmDialog from "@/components/dialogs/ConfirmDialog.vue";
import ErpField from "@/components/erp/ErpField.vue";
import ErpPageHeader from "@/components/erp/ErpPageHeader.vue";
import ErpSearchField from "@/components/erp/ErpSearchField.vue";
import DataTableFilterMenu from "@/components/responsive-data-table/DataTableFilterMenu.vue";
import ResponsiveDataTable from "@/components/responsive-data-table/ResponsiveDataTable.vue";
import TableEmptyState from "@/components/TableEmptyState.vue";
import {
  AcademyAttendanceApi,
  type AcademyAttendanceMonth,
  type AcademyAttendanceStatus,
  type AcademyAttendanceWriteRow,
} from "@/api/academyAttendance";
import {
  AcademyClassesApi,
  type AcademyClassRecord,
} from "@/api/academyClasses";
import { canAccess } from "@/utils/authorization";
import { formatActiveDate } from "@/utils/localization";
import { printDocument } from "@/utils/printDocument";
import AcademyAttendanceTemplate from "@/pages/templates/academy_attendance_template.vue";

type LedgerRow = {
  key: string;
  attendance_id: number;
  enrollment_id: number;
  student_number: string | null;
  name: string | null;
  contact: string | null;
  date: string;
  status: AcademyAttendanceStatus;
  check_in_time: string | null;
  check_out_time: string | null;
  late_minutes: number | null;
};
const { t } = useI18n();
const toast = useToast();
const currentDate = new Date().toISOString().slice(0, 10);
const classes = ref<AcademyClassRecord[]>([]);
const activeClassId = ref<number | null>(null);
const data = ref<AcademyAttendanceMonth | null>(null);
const loading = ref(true);
const failed = ref(false);
const search = ref("");
const filterOpen = ref(false);
const attendanceDate = ref(currentDate);
const filterDate = ref(currentDate);
const markOpen = ref(false);
const markData = ref<AcademyAttendanceMonth | null>(null);
const markLoading = ref(false);
const saving = ref(false);
const editOpen = ref(false);
const savingEdit = ref(false);
const editing = ref<LedgerRow | null>(null);
const editStatus = ref<AcademyAttendanceStatus>("present");
const editCheckInTime = ref<string | null>(null);
const editCheckOutTime = ref<string | null>(null);
const deleting = ref<LedgerRow | null>(null);
const deleteOpen = ref(false);
const drafts = ref<AcademyAttendanceWriteRow[]>([]);
const completeOpen = ref(false);
const canManage = computed(() => canAccess("manage_academy_attendance"));
const activeClass = computed(
  () => classes.value.find((row) => row.id === activeClassId.value) ?? null,
);
const headers = computed(() => [
  { title: t("academy.fields.student"), key: "student" },
  { title: t("academy.fields.date"), key: "date" },
  { title: t("academy.fields.status"), key: "status" },
  { title: t("academy.attendance.checkIn"), key: "check_in_time" },
  { title: t("academy.attendance.checkOut"), key: "check_out_time" },
  { title: t("academy.attendance.lateMinutes"), key: "late_minutes" },
  {
    title: t("common.messages.actions"),
    key: "actions",
    sortable: false,
    align: "end" as const,
  },
]);
const statusOptions = computed(() =>
  (["present", "absent", "leave"] as AcademyAttendanceStatus[]).map(
    (value) => ({ title: t(`academy.attendance.status.${value}`), value }),
  ),
);
const timeOptions = Array.from({ length: 96 }, (_, index) => {
  const hour = Math.floor(index / 4);
  const minute = (index % 4) * 15;
  const value = `${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}`;
  return {
    title: new Intl.DateTimeFormat(undefined, {
      hour: "numeric",
      minute: "2-digit",
    }).format(new Date(2000, 0, 1, hour, minute)),
    value,
  };
});
const ledgerRows = computed<LedgerRow[]>(() => {
  const needle = search.value.trim().toLocaleLowerCase();
  const result: LedgerRow[] = [];
  for (const student of data.value?.rows ?? []) {
    const cell = student.attendance[attendanceDate.value];
    if (
      cell &&
      (!needle ||
        [student.name, student.student_number, student.contact].some((value) =>
          String(value ?? "")
            .toLocaleLowerCase()
            .includes(needle),
        ))
    )
      result.push({
        key: `${student.enrollment_id}|${attendanceDate.value}`,
        attendance_id: cell.id,
        enrollment_id: student.enrollment_id,
        student_number: student.student_number,
        name: student.name,
        contact: student.contact,
        date: attendanceDate.value,
        ...cell,
        check_in_time: formatTime(cell.check_in_time),
        check_out_time: formatTime(cell.check_out_time),
        late_minutes: lateMinutes(
          cell.status,
          cell.check_in_time,
          data.value?.class.starts_at,
        ),
      });
  }
  return result.sort((a, b) => String(a.name).localeCompare(String(b.name)));
});
async function loadClasses() {
  classes.value = await AcademyClassesApi.list();
  activeClassId.value ||= classes.value[0]?.id ?? null;
}
async function load() {
  if (!activeClassId.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  failed.value = false;
  try {
    data.value = await AcademyAttendanceApi.getMonth(
      activeClassId.value,
      attendanceDate.value.slice(0, 7),
    );
  } catch {
    failed.value = true;
  } finally {
    loading.value = false;
  }
}
function applyFilter() {
  attendanceDate.value = filterDate.value || currentDate;
  filterOpen.value = false;
  void load();
}
function resetFilter() {
  filterDate.value = currentDate;
  applyFilter();
}
function isEligibleForDate(
  row: AcademyAttendanceMonth["rows"][number],
  date: string,
) {
  return (
    (!row.started_on || date >= row.started_on) &&
    (!row.ends_on || date <= row.ends_on)
  );
}
function classTime(time: string | null | undefined) {
  return time?.slice(0, 5) || null;
}
function timeToMinutes(time: string | null | undefined) {
  if (!time) return null;
  const [hours, minutes] = time.split(":").map(Number);
  return Number.isFinite(hours) && Number.isFinite(minutes)
    ? hours * 60 + minutes
    : null;
}
function lateMinutes(
  status: AcademyAttendanceStatus,
  checkIn: string | null,
  classStartsAt: string | null | undefined,
) {
  if (status !== "present") return null;
  const checkedInAt = timeToMinutes(checkIn);
  const startsAt = timeToMinutes(classStartsAt);
  return checkedInAt === null || startsAt === null
    ? null
    : Math.max(0, checkedInAt - startsAt);
}
function formatTime(time: string | null) {
  const minutes = timeToMinutes(time);
  if (minutes === null) return "—";
  return new Intl.DateTimeFormat(undefined, {
    hour: "numeric",
    minute: "2-digit",
  }).format(new Date(2000, 0, 1, Math.floor(minutes / 60), minutes % 60));
}
function formatLateMinutes(minutes: number | null) {
  if (minutes === null) return "—";
  if (minutes < 60) return `${minutes} min`;
  return `${Math.floor(minutes / 60)}h ${minutes % 60}m`;
}
const hasRecordedAttendance = computed(() =>
  (markData.value?.rows ?? []).some((student) =>
    Boolean(student.attendance[attendanceDate.value]),
  ),
);
async function loadMark() {
  if (!activeClassId.value) {
    markData.value = null;
    drafts.value = [];
    return;
  }
  markLoading.value = true;
  try {
    const response = await AcademyAttendanceApi.getMonth(
      activeClassId.value,
      attendanceDate.value.slice(0, 7),
    );
    markData.value = response;
    const defaultCheckIn = classTime(response.class.starts_at);
    const defaultCheckOut = classTime(response.class.ends_at);
    drafts.value = response.rows
      .filter((student) => isEligibleForDate(student, attendanceDate.value))
      .map((student) => {
        const cell = student.attendance[attendanceDate.value];
        return {
          enrollment_id: student.enrollment_id,
          date: attendanceDate.value,
          status: cell?.status ?? "present",
          check_in_time: cell?.check_in_time ?? defaultCheckIn,
          check_out_time: cell?.check_out_time ?? defaultCheckOut,
        };
      });
  } finally {
    markLoading.value = false;
  }
}
function studentFor(enrollmentId: number) {
  return markData.value?.rows.find((row) => row.enrollment_id === enrollmentId);
}
function setStatus(
  row: AcademyAttendanceWriteRow,
  status: AcademyAttendanceStatus,
) {
  row.status = status;
  if (status !== "present") {
    row.check_in_time = null;
    row.check_out_time = null;
  } else {
    row.check_in_time ||= markData.value?.class.starts_at?.slice(0, 5) ?? null;
    row.check_out_time ||= markData.value?.class.ends_at?.slice(0, 5) ?? null;
  }
}
function openEdit(row: LedgerRow) {
  editing.value = row;
  editStatus.value = row.status;
  const cell = data.value?.rows.find(
    (student) => student.enrollment_id === row.enrollment_id,
  )?.attendance[row.date];
  editCheckInTime.value = cell?.check_in_time ?? null;
  editCheckOutTime.value = cell?.check_out_time ?? null;
  editOpen.value = true;
}
async function saveEdit() {
  if (!editing.value) return;
  savingEdit.value = true;
  try {
    await AcademyAttendanceApi.save([
      {
        enrollment_id: editing.value.enrollment_id,
        date: editing.value.date,
        status: editStatus.value,
        check_in_time:
          editStatus.value === "present" ? editCheckInTime.value : null,
        check_out_time:
          editStatus.value === "present" ? editCheckOutTime.value : null,
      },
    ]);
    toast.success(t("academy.attendance.saved"));
    editOpen.value = false;
    await load();
  } finally {
    savingEdit.value = false;
  }
}
async function deleteAttendance(confirmed: boolean) {
  if (!confirmed || !deleting.value) return;
  try {
    await AcademyAttendanceApi.remove(deleting.value.attendance_id);
    toast.success(t("common.messages.deleted"));
    await load();
  } finally {
    deleting.value = null;
    deleteOpen.value = false;
  }
}
async function saveAttendance() {
  if (!drafts.value.length) return;
  saving.value = true;
  try {
    await AcademyAttendanceApi.save(drafts.value);
    toast.success(t("academy.attendance.saved"));
    markOpen.value = false;
    await load();
  } finally {
    saving.value = false;
  }
}
async function completeClass(confirmed: boolean) {
  if (!confirmed || !activeClassId.value) return;
  try {
    await AcademyClassesApi.complete(activeClassId.value);
    toast.success(t("academy.attendance.classCompleted"));
    await loadClasses();
  } catch {
    /* API notifier owns errors. */
  }
}
async function printSheet() {
  await printDocument({
    bodyClass: "printing-academy-attendance",
    pageStyle: "size: A4 landscape; margin: 8mm;",
    element: document.querySelector<HTMLElement>(
      ".academy-attendance-print-host",
    ),
  });
}
watch(activeClassId, () => {
  void load();
  if (markOpen.value) void loadMark();
});
watch(markOpen, (open) => {
  if (open) void loadMark();
});
onMounted(async () => {
  try {
    await loadClasses();
    await load();
  } catch {
    failed.value = true;
    loading.value = false;
  }
});
</script>
<template>
  <div class="academy-page">
    <div class="academy-attendance-print-host" aria-hidden="true">
      <AcademyAttendanceTemplate
        v-if="data"
        :title="activeClass?.name ?? ''"
        :month="data.month"
        :dates="data.dates.filter((date) => date === attendanceDate)"
        :rows="data.rows"
      />
    </div>
    <ErpPageHeader
      :title="t('academy.attendance.title')"
      section="academy"
    /><VCard
      ><VCardText
        ><VRow align="center"
          ><VCol cols="12" md="5"
            ><ErpSearchField
              v-model="search"
              :placeholder="t('academy.attendance.title')" /></VCol
          ><VCol
            cols="12"
            md="7"
            class="attendance-toolbar d-flex flex-wrap justify-end ga-2"
            ><DataTableFilterMenu
              v-model="filterOpen"
              :active="attendanceDate !== currentDate"
              :show-reset="false"
              ><ErpField
                v-model="filterDate"
                kind="date"
                :label="t('academy.fields.date')"
                :placeholder="t('academy.fields.date')"
                :config="{ dateFormat: 'Y-m-d', static: false }"
              /><template #actions
                ><VBtn variant="tonal" color="primary" @click="resetFilter">{{
                  t("common.messages.reset")
                }}</VBtn
                ><VBtn color="primary" @click="applyFilter">{{
                  t("educational.apply")
                }}</VBtn></template
              ></DataTableFilterMenu
            ><VBtn
              variant="tonal"
              class="attendance-toolbar__print"
              :disabled="!ledgerRows.length"
              @click="printSheet"
              ><VIcon icon="tabler-printer" /><VTooltip activator="parent">{{
                t("common.actions.print")
              }}</VTooltip></VBtn
            ><VBtn
              v-if="canManage"
              variant="tonal"
              color="success"
              :disabled="!activeClassId"
              @click="completeOpen = true"
              >{{ t("academy.attendance.completeClass") }}</VBtn
            ><VBtn
              v-if="canManage"
              color="primary"
              :disabled="!activeClassId"
              @click="markOpen = true"
              ><VIcon icon="tabler-calendar-check" class="me-1" />{{
                t("academy.attendance.markAttendance")
              }}</VBtn
            ></VCol
          ></VRow
        ></VCardText
      ><VTabs
        v-if="classes.length"
        v-model="activeClassId"
        class="px-4"
        show-arrows
        ><VTab v-for="klass in classes" :key="klass.id" :value="klass.id">{{
          klass.name
        }}</VTab></VTabs
      ><VAlert v-if="failed" type="error" variant="tonal" class="ma-4">{{
        t("academy.attendance.loadFailed")
      }}</VAlert
      ><ResponsiveDataTable
        v-else
        :headers="headers"
        :items="ledgerRows"
        :loading="loading"
        :skeleton-rows="6"
        item-value="key"
        class="attendance-table"
        mobile-cards
        mobile-expand-all
        mobile-title-key="student"
        :mobile-fields="[
          'date',
          'status',
          'check_in_time',
          'check_out_time',
          'late_minutes',
        ]"
        :sticky-key-column="false"
        :sticky-action-column="false"
        :show-scroll-affordance="false"
        ><template #item.student="{ item }"
          ><div>
            <strong>{{ item.name || "—" }}</strong>
            <div class="text-caption text-medium-emphasis">
              {{ item.student_number || item.contact || "—" }}
            </div>
          </div></template
        ><template #item.date="{ item }">{{
          formatActiveDate(item.date)
        }}</template
        ><template #item.status="{ item }"
          ><VChip
            size="small"
            variant="tonal"
            :color="
              item.status === 'present'
                ? 'success'
                : item.status === 'absent'
                  ? 'error'
                  : 'info'
            "
            >{{ t(`academy.attendance.status.${item.status}`) }}</VChip
          ></template
        ><template #item.check_in_time="{ item }">{{
          item.check_in_time || "—"
        }}</template
        ><template #item.check_out_time="{ item }">{{
          item.check_out_time || "—"
        }}</template
        ><template #item.late_minutes="{ item }">{{
          formatLateMinutes(item.late_minutes)
        }}</template
        ><template #item.actions="{ item }"
          ><VMenu location="bottom end">
            <template #activator="{ props }">
              <VBtn
                v-bind="props"
                icon="tabler-dots-vertical"
                size="small"
                variant="tonal"
                :aria-label="t('common.messages.actions')"
              />
            </template>
            <VList density="compact">
              <VListItem
                prepend-icon="tabler-pencil"
                :title="t('common.actions.edit')"
                @click="openEdit(item)"
              />
              <VListItem
                prepend-icon="tabler-trash"
                class="attendance-delete-action"
                :title="t('common.actions.delete')"
                @click="
                  deleting = item;
                  deleteOpen = true;
                "
              />
            </VList> </VMenu></template
        ><template #no-data
          ><div class="attendance-empty-primary">
            <TableEmptyState
              icon="tabler-calendar-check"
              :title="t('academy.attendance.empty')"
              :description="t('academy.attendance.emptyDescription')"
            /></div></template></ResponsiveDataTable></VCard
    ><VDialog
      v-model="markOpen"
      max-width="960"
      content-class="attendance-mark-dialog"
      persistent
      scrollable
      ><VCard class="attendance-mark-card"
        ><VForm class="app-form" @submit.prevent="saveAttendance"
          ><VCardTitle class="attendance-dialog-header px-5 py-4"
            ><div class="text-h5 font-weight-bold">
              {{ t("academy.attendance.markAttendance") }}
            </div>
            <VSpacer /><VBtn
              icon="tabler-x"
              variant="tonal"
              color="error"
              class="attendance-dialog-close"
              @click="markOpen = false" /></VCardTitle
          ><VCardText class="attendance-dialog-body pa-5"
            ><div class="text-caption text-medium-emphasis mb-4">
              {{ formatActiveDate(attendanceDate) }}
            </div>
            <VAlert
              v-if="hasRecordedAttendance && !markLoading"
              color="success"
              variant="tonal"
              class="mb-4"
            >
              {{ t("academy.attendance.alreadyRecorded") }}
            </VAlert>
            <div
              v-if="markLoading"
              class="attendance-draft-list"
              aria-busy="true"
            >
              <article
                v-for="index in 4"
                :key="index"
                class="attendance-draft attendance-draft--skeleton"
              >
                <div class="attendance-draft__top">
                  <div class="attendance-draft__identity">
                    <VSkeletonLoader type="avatar" />
                    <div class="attendance-draft__skeleton-copy">
                      <VSkeletonLoader type="heading" width="10rem" />
                      <VSkeletonLoader type="text" width="6rem" />
                    </div>
                  </div>
                  <div class="attendance-draft__skeleton-statuses">
                    <VSkeletonLoader
                      v-for="status in 3"
                      :key="status"
                      type="button"
                    />
                  </div>
                </div>
                <VRow class="mt-1">
                  <VCol cols="12" sm="6"
                    ><VSkeletonLoader type="list-item-two-line"
                  /></VCol>
                  <VCol cols="12" sm="6"
                    ><VSkeletonLoader type="list-item-two-line"
                  /></VCol>
                </VRow>
              </article>
            </div>
            <TableEmptyState
              v-else-if="!drafts.length"
              icon="tabler-users-minus"
              :title="t('academy.attendance.empty')"
              :description="t('academy.attendance.emptyDescription')" />
            <div v-else class="attendance-draft-list">
              <article
                v-for="draft in drafts"
                :key="draft.enrollment_id"
                class="attendance-draft"
              >
                <div class="attendance-draft__top">
                  <div class="attendance-draft__identity">
                    <VAvatar color="primary" variant="tonal" size="44"
                      ><span>{{
                        (studentFor(draft.enrollment_id)?.name || "—").slice(
                          0,
                          1,
                        )
                      }}</span></VAvatar
                    >
                    <div>
                      <strong>{{
                        studentFor(draft.enrollment_id)?.name || "—"
                      }}</strong
                      ><span>{{
                        studentFor(draft.enrollment_id)?.student_number || "—"
                      }}</span>
                    </div>
                  </div>
                  <div
                    class="attendance-status-picker"
                    role="radiogroup"
                    :aria-label="t('academy.fields.status')"
                  >
                    <VBtn
                      v-for="option in statusOptions"
                      :key="option.value"
                      :class="[
                        'attendance-status-picker__button',
                        `attendance-status-picker__button--${option.value}`,
                        { 'is-selected': draft.status === option.value },
                      ]"
                      :aria-pressed="draft.status === option.value"
                      size="small"
                      variant="text"
                      @click="setStatus(draft, option.value)"
                      >{{ option.title }}</VBtn
                    >
                  </div>
                </div>
                <VRow class="mt-1"
                  ><VCol cols="12" sm="6"
                    ><ErpField
                      v-model="draft.check_in_time"
                      kind="select"
                      :items="timeOptions"
                      :label="t('academy.attendance.checkIn')"
                      :disabled="draft.status !== 'present'"
                      clearable /></VCol
                  ><VCol cols="12" sm="6"
                    ><ErpField
                      v-model="draft.check_out_time"
                      kind="select"
                      :items="timeOptions"
                      :label="t('academy.attendance.checkOut')"
                      :disabled="draft.status !== 'present'"
                      clearable /></VCol
                ></VRow>
              </article></div></VCardText
          ><VCardActions class="attendance-dialog-footer px-5 py-4"
            ><VSpacer /><VBtn
              class="attendance-cancel"
              @click="markOpen = false"
              >{{ t("common.actions.cancel") }}</VBtn
            ><VBtn
              color="primary"
              variant="flat"
              class="attendance-save"
              type="submit"
              :loading="saving"
              :disabled="!drafts.length"
              >{{ t("common.actions.save") }}</VBtn
            ></VCardActions
          ></VForm
        ></VCard
      ></VDialog
    ><VDialog
      v-model="editOpen"
      :persistent="savingEdit"
      max-width="620"
      content-class="academy-attendance-edit-dialog"
    >
      <VCard class="academy-attendance-edit-dialog__card">
        <VCardTitle
          class="academy-attendance-edit-dialog__header d-flex align-center justify-space-between px-5 py-4"
        >
          <span>{{ t("common.actions.edit") }}</span>
          <VBtn
            class="academy-attendance-edit-dialog__close"
            icon="tabler-x"
            variant="tonal"
            size="small"
            :disabled="savingEdit"
            @click="editOpen = false"
          />
        </VCardTitle>
        <VCardText class="pt-5">
          <p class="text-body-2 text-medium-emphasis mb-5">
            {{ editing?.name }} ·
            {{ editing ? formatActiveDate(editing.date) : "" }}
          </p>
          <div
            class="attendance-status-picker justify-start mb-5"
            role="radiogroup"
            :aria-label="t('academy.fields.status')"
          >
            <VBtn
              v-for="option in statusOptions"
              :key="option.value"
              :class="[
                'attendance-status-picker__button',
                `attendance-status-picker__button--${option.value}`,
                { 'is-selected': editStatus === option.value },
              ]"
              :aria-pressed="editStatus === option.value"
              size="small"
              variant="text"
              @click="editStatus = option.value"
              >{{ option.title }}</VBtn
            >
          </div>
          <VRow>
            <VCol cols="12" sm="6"
              ><ErpField
                v-model="editCheckInTime"
                kind="select"
                :items="timeOptions"
                :label="t('academy.attendance.checkIn')"
                :disabled="editStatus !== 'present'"
                clearable
            /></VCol>
            <VCol cols="12" sm="6"
              ><ErpField
                v-model="editCheckOutTime"
                kind="select"
                :items="timeOptions"
                :label="t('academy.attendance.checkOut')"
                :disabled="editStatus !== 'present'"
                clearable
            /></VCol>
          </VRow>
        </VCardText>
        <VCardActions class="academy-attendance-edit-dialog__actions px-5 py-4">
          <VBtn
            class="attendance-cancel"
            :disabled="savingEdit"
            @click="editOpen = false"
            >{{ t("common.actions.cancel") }}</VBtn
          >
          <VBtn
            color="primary"
            variant="flat"
            class="attendance-save"
            :loading="savingEdit"
            @click="saveEdit"
            >{{ t("common.actions.save") }}</VBtn
          >
        </VCardActions>
      </VCard> </VDialog
    ><ConfirmDialog
      v-model:is-dialog-visible="completeOpen"
      tone="success"
      icon="tabler-circle-check"
      :confirmation-question="t('academy.attendance.completeClass')"
      :confirmation-message="activeClass?.name ?? ''"
      :confirm-text="t('academy.attendance.completeClass')"
      :cancel-text="t('common.actions.cancel')"
      :confirm-title="t('academy.attendance.classCompleted')"
      confirm-msg=""
      :cancel-title="t('common.actions.cancel')"
      cancel-msg=""
      @confirm="completeClass"
    />
    <ConfirmDialog
      v-model:is-dialog-visible="deleteOpen"
      tone="error"
      icon="tabler-trash"
      :confirmation-question="t('common.actions.delete')"
      :confirmation-message="deleting?.name ?? ''"
      :confirm-text="t('common.actions.delete')"
      :cancel-text="t('common.actions.cancel')"
      :confirm-title="t('common.actions.delete')"
      confirm-msg=""
      :cancel-title="t('common.actions.cancel')"
      cancel-msg=""
      @confirm="deleteAttendance"
    />
  </div>
</template>
<style scoped lang="scss">
.academy-attendance-print-host {
  display: none;
}
.attendance-table :deep(thead th) {
  background: color-mix(
    in srgb,
    rgb(var(--v-theme-primary)) 8%,
    rgb(var(--v-theme-surface)) 92%
  );
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.035em;
  text-transform: uppercase;
}
.attendance-toolbar__print {
  min-inline-size: 2.25rem !important;
  inline-size: 2.25rem !important;
  padding-inline: 0 !important;
  border-radius: 999px !important;
  font-size: 0.75rem !important;
}
.attendance-empty-primary :deep(.table-empty-state__icon) {
  background: transparent;
  color: rgb(var(--v-theme-primary));
}
.attendance-mark-card {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  max-block-size: min(90dvh, 920px);
  overflow: hidden;
}
.attendance-dialog-header,
.attendance-dialog-footer {
  z-index: 1;
  display: flex;
  align-items: center;
  background: color-mix(
    in srgb,
    rgb(var(--v-theme-surface)) 93%,
    rgb(var(--v-theme-on-surface)) 7%
  );
}
.attendance-dialog-header {
  position: sticky;
  inset-block-start: 0;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.5);
}
.attendance-dialog-footer {
  position: sticky;
  inset-block-end: 0;
  border-block-start: 1px solid rgba(var(--v-border-color), 0.5);
}
.attendance-dialog-body {
  min-block-size: 0;
  background: rgba(var(--v-theme-on-surface), 0.018);
  overflow-y: auto;
}
.attendance-dialog-close {
  border: 1px solid rgba(var(--v-theme-error), 0.38) !important;
  border-radius: 999px !important;
  color: rgb(var(--v-theme-error)) !important;
}
.attendance-cancel {
  border: 1px solid rgba(var(--v-theme-secondary), 0.45) !important;
  background: rgba(var(--v-theme-secondary), 0.12) !important;
  color: rgb(var(--v-theme-secondary)) !important;
}
.attendance-cancel:hover {
  background: rgb(var(--v-theme-secondary)) !important;
  color: rgb(var(--v-theme-on-secondary)) !important;
}
.attendance-save {
  color: rgb(var(--v-theme-on-primary)) !important;
}
.attendance-draft-list {
  display: grid;
  gap: 1.4rem;
  margin-block-start: 1.5rem;
}
.attendance-draft {
  padding: 1rem;
  border: 1px solid rgba(var(--v-border-color), 0.48);
  border-radius: 0.85rem;
  background: rgb(var(--v-theme-surface));
}
.attendance-draft--skeleton {
  pointer-events: none;
}
.attendance-draft--skeleton :deep(.v-skeleton-loader__bone) {
  animation: attendance-skeleton-wave 1.45s ease-in-out infinite;
  background: linear-gradient(
    100deg,
    rgba(var(--v-theme-on-surface), 0.055) 25%,
    rgba(var(--v-theme-on-surface), 0.11) 45%,
    rgba(var(--v-theme-on-surface), 0.055) 65%
  );
  background-size: 240% 100%;
}
.attendance-draft__skeleton-copy {
  display: grid;
  gap: 0.25rem;
}
.attendance-draft__skeleton-statuses {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}
@keyframes attendance-skeleton-wave {
  to {
    background-position: -160% 0;
  }
}
.attendance-draft:focus-within {
  border-color: rgba(var(--v-theme-primary), 0.65);
  box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), 0.1);
}
.attendance-draft__top,
.attendance-draft__identity,
.attendance-status-picker {
  display: flex;
  align-items: center;
}
.attendance-draft__top {
  justify-content: space-between;
  gap: 1rem;
}
.attendance-draft__identity {
  min-inline-size: 0;
  gap: 0.75rem;
}
.attendance-draft__identity > div {
  display: grid;
  min-inline-size: 0;
  gap: 0.1rem;
}
.attendance-draft__identity strong {
  overflow: hidden;
  font-size: 0.94rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.attendance-draft__identity span {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.78rem;
}
.attendance-status-picker {
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.4rem;
}
.attendance-status-picker__button {
  border: 1px solid rgba(var(--v-border-color), 0.54) !important;
  background: transparent !important;
  color: rgba(
    var(--v-theme-on-surface),
    var(--v-medium-emphasis-opacity)
  ) !important;
  font-weight: 700;
  text-transform: none;
}
.attendance-status-picker__button--present.is-selected {
  background: rgba(var(--v-theme-success), 0.1) !important;
  color: rgb(var(--v-theme-success)) !important;
}
.attendance-status-picker__button--absent.is-selected {
  background: rgba(var(--v-theme-error), 0.1) !important;
  color: rgb(var(--v-theme-error)) !important;
}
.attendance-status-picker__button--leave.is-selected {
  background: rgba(var(--v-theme-info), 0.1) !important;
  color: rgb(var(--v-theme-info)) !important;
}
.attendance-status-picker__button.is-selected {
  border-color: currentColor !important;
}
.attendance-delete-action {
  color: rgb(var(--v-theme-error)) !important;
}
.academy-attendance-edit-dialog__card {
  overflow: hidden;
}
.academy-attendance-edit-dialog__header,
.academy-attendance-edit-dialog__actions {
  background: color-mix(
    in srgb,
    rgb(var(--v-theme-surface)) 93%,
    rgb(var(--v-theme-on-surface)) 7%
  );
}
.academy-attendance-edit-dialog__header {
  border-block-end: 1px solid rgba(var(--v-border-color), 0.5);
}
.academy-attendance-edit-dialog__actions {
  display: flex;
  justify-content: flex-start;
  gap: 0.75rem;
  border-block-start: 1px solid rgba(var(--v-border-color), 0.5);
}
.academy-attendance-edit-dialog__actions :deep(.v-btn) {
  min-block-size: 2.75rem;
}
.academy-attendance-edit-dialog__close {
  border: 1px solid rgba(var(--v-theme-error), 0.38) !important;
  color: rgb(var(--v-theme-error)) !important;
}
:global(.v-overlay:has(.attendance-mark-card) .v-overlay__scrim) {
  background: rgba(var(--v-theme-on-surface), 0.28) !important;
  backdrop-filter: blur(7px);
  opacity: 1 !important;
}
:global(.v-overlay:has(.academy-attendance-edit-dialog) .v-overlay__scrim) {
  background: rgba(var(--v-theme-on-surface), 0.28) !important;
  backdrop-filter: blur(7px);
  opacity: 1 !important;
}
@media (max-width: 700px) {
  .attendance-dialog-header,
  .attendance-dialog-footer {
    padding-inline: 1rem !important;
  }
  .attendance-draft {
    padding: 0.85rem;
  }
  .attendance-draft__top {
    align-items: flex-start;
    flex-direction: column;
  }
  .attendance-status-picker {
    justify-content: flex-start;
  }
}
@media (max-width: 599px) {
  .attendance-toolbar {
    justify-content: flex-start !important;
  }
  .attendance-toolbar .v-btn {
    flex: 0 0 auto;
  }
  .attendance-toolbar .attendance-toolbar__print {
    flex: 0 0 2.25rem;
  }
}
@media print {
  :global(body.printing-academy-attendance .academy-attendance-print-host) {
    display: block !important;
  }
  :global(
    body.printing-academy-attendance
      .academy-page
      > :not(.academy-attendance-print-host)
  ) {
    display: none !important;
  }
}
</style>
