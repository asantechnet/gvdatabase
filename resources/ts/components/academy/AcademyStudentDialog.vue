<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import type { AcademyClassRecord } from '@/api/academyClasses'
import { AcademyStudentsApi, type AcademyStudentPayload } from '@/api/academyStudents'
import { apiFieldErrors } from '@/utils/apiErrors'

const props = defineProps<{ modelValue: boolean; classes: AcademyClassRecord[] }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; saved: [] }>()
const { t } = useI18n()
const toast = useToast()
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const currentDate = () => new Date().toISOString().slice(0, 10)
const form = reactive<AcademyStudentPayload>({
  name: '',
  father_name: '',
  father_phone: '',
  contact: '',
  email: '',
  address: '',
  notes: '',
  class_id: 0,
  started_on: currentDate(),
})

const open = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })

watch(() => props.modelValue, value => {
  if (!value)
    return
  errors.value = {}
  Object.assign(form, {
    name: '', father_name: '', father_phone: '', contact: '', email: '', address: '', notes: '',
    class_id: props.classes[0]?.id ?? 0,
    started_on: currentDate(),
  })
})

async function save() {
  if (!form.name.trim() || !form.class_id || saving.value)
    return
  saving.value = true
  errors.value = {}
  try {
    await AcademyStudentsApi.create(form)
    toast.success(t('academy.students.saved'))
    open.value = false
    emit('saved')
  }
  catch (error) {
    errors.value = apiFieldErrors(error)
  }
  finally {
    saving.value = false
  }
}

const dockDialogPicker = (_selectedDates: Date[], _dateString: string, instance: { calendarContainer: HTMLElement }) => {
  requestAnimationFrame(() => {
    instance.calendarContainer.style.setProperty('transform', 'translateY(0) scale(0.86)', 'important')
    instance.calendarContainer.style.setProperty('transform-origin', 'top left', 'important')
    instance.calendarContainer.style.setProperty('z-index', '2400', 'important')
  })
}
</script>

<template>
  <VDialog v-model="open" max-width="980" :persistent="saving" scrollable scroll-strategy="block" content-class="academy-student-form-dialog">
    <VCard class="academy-student-form-card app-form-dialog">
      <VForm class="app-form" @submit.prevent="save">
        <VCardTitle class="academy-student-form__header px-5 py-4">
          <span class="text-h5 font-weight-bold">{{ t('academy.students.addTitle') }}</span>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="error" class="academy-student-form__close" :disabled="saving" :aria-label="t('common.actions.close')" @click="open = false" />
        </VCardTitle>
        <VCardText class="academy-student-form__body pa-5 pa-sm-6">
          <VRow class="academy-student-form__grid">
            <VCol cols="12" md="4"><ErpField v-model="form.name" required :show-required-rule="false" :label="t('academy.fields.name')" :placeholder="t('academy.fields.name')" :error-messages="errors.name" /></VCol>
            <VCol cols="12" md="4"><ErpField v-model="form.contact" :label="t('academy.fields.contact')" :placeholder="t('academy.fields.contact')" :error-messages="errors.contact" /></VCol>
            <VCol cols="12" md="4"><ErpField v-model="form.email" type="email" :label="t('common.fields.email')" :placeholder="t('common.fields.email')" :error-messages="errors.email" /></VCol>

            <VCol cols="12" md="4"><ErpField v-model="form.father_name" :label="t('educational.fatherName')" :placeholder="t('educational.fatherName')" :error-messages="errors.father_name" /></VCol>
            <VCol cols="12" md="4"><ErpField v-model="form.father_phone" :label="t('educational.fatherPhone')" :placeholder="t('educational.fatherPhone')" :error-messages="errors.father_phone" /></VCol>
            <VCol cols="12" md="4"><ErpField v-model="form.address" :label="t('common.fields.address')" :placeholder="t('common.fields.address')" :error-messages="errors.address" /></VCol>

            <VCol cols="12" md="4"><ErpField v-model="form.class_id" kind="select" :items="classes" item-title="name" item-value="id" :label="t('academy.fields.class')" :placeholder="t('academy.fields.class')" :error-messages="errors.class_id" /></VCol>
            <VCol cols="12" md="4"><div class="erp-field"><label class="erp-field-label">{{ t('academy.fields.joiningDate') }}</label><AppDateTimePicker v-model="form.started_on" class="erp-field-control" :placeholder="t('academy.placeholders.date')" :error-messages="errors.started_on" :config="{ dateFormat: 'Y-m-d', static: false, onOpen: dockDialogPicker }" /></div></VCol>
            <VCol cols="12" md="4"><ErpField v-model="form.notes" :label="t('academy.fields.notes')" :placeholder="t('academy.fields.notes')" :error-messages="errors.notes" /></VCol>
          </VRow>
        </VCardText>
        <VCardActions class="academy-student-form__footer px-5 py-4">
          <VSpacer />
          <VBtn color="secondary" variant="flat" class="academy-student-form__cancel" :disabled="saving" @click="open = false">{{ t('common.actions.cancel') }}</VBtn>
          <VBtn color="primary" variant="flat" type="submit" :loading="saving" :disabled="!form.name.trim() || !form.class_id">{{ t('common.actions.save') }}</VBtn>
        </VCardActions>
      </VForm>
    </VCard>
  </VDialog>
</template>

<style scoped>
.academy-student-form-card { overflow: hidden; }
.academy-student-form__header, .academy-student-form__footer { align-items: center; display: flex; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%); }
.academy-student-form__header { border-block-end: 1px solid rgba(var(--v-theme-on-surface), .07); }
.academy-student-form__footer { border-block-start: 1px solid rgba(var(--v-theme-on-surface), .07); gap: .5rem; }
.academy-student-form__body { background: rgb(var(--v-theme-surface)); }
.academy-student-form__grid { row-gap: 6px; }
.academy-student-form__close { border: 1px solid rgba(var(--v-theme-error), .38) !important; border-radius: 999px !important; color: rgb(var(--v-theme-error)) !important; }
.academy-student-form__close:hover { background: rgba(var(--v-theme-error), .18) !important; }
.academy-student-form__cancel { border: 1px solid rgba(var(--v-theme-secondary), .45) !important; background: rgba(var(--v-theme-secondary), .12) !important; color: rgb(var(--v-theme-secondary)) !important; }
.academy-student-form__cancel:hover { background: rgb(var(--v-theme-secondary)) !important; color: rgb(var(--v-theme-on-secondary)) !important; }
:global(.v-overlay:has(.academy-student-form-dialog) .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .28) !important; backdrop-filter: blur(7px); opacity: 1 !important; }
:global(.academy-student-form-dialog) { max-block-size: calc(100dvh - 2rem); }
@media (max-width: 599px) { .academy-student-form__header, .academy-student-form__footer { padding-inline: 1rem !important; } .academy-student-form__body { padding: 1rem !important; } }
</style>
