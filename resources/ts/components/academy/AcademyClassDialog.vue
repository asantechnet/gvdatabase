<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { AcademyClassesApi, type AcademyClassPayload, type AcademyClassRecord, type AcademyOption } from '@/api/academyClasses'
import { apiFieldErrors } from '@/utils/apiErrors'

const props = defineProps<{
  modelValue: boolean
  record: AcademyClassRecord | null
  programs: AcademyOption[]
  teachers: AcademyOption[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  saved: []
}>()

const { t } = useI18n()
const toast = useToast()
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const currentDate = () => new Date().toISOString().slice(0, 10)
const weekdays = computed(() => [
  { value: 1, label: t('academy.fields.weekdaysShort.mon') },
  { value: 2, label: t('academy.fields.weekdaysShort.tue') },
  { value: 3, label: t('academy.fields.weekdaysShort.wed') },
  { value: 4, label: t('academy.fields.weekdaysShort.thu') },
  { value: 5, label: t('academy.fields.weekdaysShort.fri') },
  { value: 6, label: t('academy.fields.weekdaysShort.sat') },
  { value: 7, label: t('academy.fields.weekdaysShort.sun') },
])
const form = reactive<AcademyClassPayload>({
  name: '',
  description: '',
  program_id: null,
  teacher_ids: [],
  weekdays: [],
  starts_on: currentDate(),
  ends_on: null,
  starts_at: null,
  ends_at: null,
  capacity: null,
  fee: null,
  is_active: true,
})

const open = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

function reset() {
  const record = props.record
  errors.value = {}
  form.name = record?.name ?? ''
  form.description = record?.description ?? ''
  form.program_id = record?.program?.id ?? null
  form.teacher_ids = record?.teachers.map(teacher => teacher.id) ?? []
  form.weekdays = [...(record?.weekdays ?? [])]
  form.starts_on = record?.starts_on ?? currentDate()
  form.ends_on = record?.ends_on ?? null
  form.starts_at = record?.starts_at ?? null
  form.ends_at = record?.ends_at ?? null
  form.capacity = record?.capacity ?? null
  form.fee = record?.fee ?? null
  form.is_active = record?.is_active ?? true
}

watch(() => props.modelValue, value => {
  if (value)
    reset()
})

async function save() {
  if (!form.name.trim() || saving.value)
    return

  saving.value = true
  errors.value = {}
  try {
    if (props.record)
      await AcademyClassesApi.update(props.record.id, form)
    else
      await AcademyClassesApi.create(form)
    toast.success(t('academy.classes.saved'))
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

// Flatpickr's popup lives at document level. Keep it docked to the dialog
// field and above the overlay, rather than letting Vuetify's dialog transform
// offset it.
const dockDialogPicker = (_selectedDates: Date[], _dateString: string, instance: { calendarContainer: HTMLElement }) => {
  requestAnimationFrame(() => {
    instance.calendarContainer.style.setProperty('transform', 'translateY(0) scale(0.86)', 'important')
    instance.calendarContainer.style.setProperty('transform-origin', 'top left', 'important')
    instance.calendarContainer.style.setProperty('z-index', '2400', 'important')
  })
}
</script>

<template>
  <VDialog v-model="open" max-width="1000" :persistent="saving" scrollable scroll-strategy="block" content-class="academy-class-form-dialog">
    <VCard class="academy-class-form-card app-form-dialog">
      <VForm class="app-form" @submit.prevent="save">
        <VCardTitle class="academy-class-form__header px-5 py-4">
          <span class="text-h5 font-weight-bold">{{ t(record ? 'academy.classes.editTitle' : 'academy.classes.createTitle') }}</span>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="error" class="academy-class-form__close" :disabled="saving" :aria-label="t('common.actions.close')" @click="open = false" />
        </VCardTitle>

        <VCardText class="academy-class-form__body pa-5 pa-sm-6">
          <VRow class="academy-class-form__grid">
            <VCol cols="12" md="4"><ErpField v-model="form.name" required :show-required-rule="false" :label="t('academy.fields.name')" :placeholder="t('academy.fields.name')" :error-messages="errors.name" /></VCol>
            <VCol cols="12" md="4"><ErpField v-model="form.program_id" kind="select" :items="programs" item-title="name" item-value="id" :label="t('academy.fields.program')" :placeholder="t('academy.fields.program')" clearable :error-messages="errors.program_id" /></VCol>
            <VCol cols="12" md="4"><ErpField v-model="form.teacher_ids" kind="autocomplete" :items="teachers" item-title="name" item-value="id" :label="t('academy.fields.teachers')" :placeholder="t('academy.fields.teachers')" multiple chips closable-chips clearable :error-messages="errors.teacher_ids" /></VCol>

            <VCol cols="12" md="4"><ErpField v-model.number="form.capacity" type="number" min="1" max="10000" :label="t('academy.fields.capacity')" :placeholder="t('academy.fields.capacity')" :error-messages="errors.capacity" /></VCol>
            <VCol cols="12" md="4"><ErpField v-model.number="form.fee" type="number" min="0" :label="t('academy.fields.fee')" :placeholder="t('academy.fields.fee')" :error-messages="errors.fee" /></VCol>
            <VCol cols="12" md="4"><ErpField v-model="form.description" :label="t('academy.fields.description')" :placeholder="t('academy.fields.description')" :error-messages="errors.description" /></VCol>

            <VCol cols="12" sm="6" md="3">
              <div class="erp-field"><label class="erp-field-label">{{ t('academy.fields.startsAt') }}</label><AppDateTimePicker v-model="form.starts_at" class="erp-field-control" :placeholder="t('academy.placeholders.startTime')" :error-messages="errors.starts_at" :config="{ enableTime: true, noCalendar: true, dateFormat: 'H:i', altInput: true, altFormat: 'h:i K', time_24hr: false, static: false, onOpen: dockDialogPicker }" /></div>
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <div class="erp-field"><label class="erp-field-label">{{ t('academy.fields.endsAt') }}</label><AppDateTimePicker v-model="form.ends_at" class="erp-field-control" :placeholder="t('academy.placeholders.endTime')" :error-messages="errors.ends_at" :config="{ enableTime: true, noCalendar: true, dateFormat: 'H:i', altInput: true, altFormat: 'h:i K', time_24hr: false, static: false, onOpen: dockDialogPicker }" /></div>
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <div class="erp-field"><label class="erp-field-label">{{ t('academy.fields.startsOn') }}</label><AppDateTimePicker v-model="form.starts_on" class="erp-field-control" :placeholder="t('academy.placeholders.date')" :error-messages="errors.starts_on" :config="{ dateFormat: 'Y-m-d', static: false, maxDate: form.ends_on || undefined, onOpen: dockDialogPicker }" /></div>
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <div class="erp-field"><label class="erp-field-label">{{ t('academy.fields.endsOn') }}</label><AppDateTimePicker v-model="form.ends_on" class="erp-field-control" :placeholder="t('academy.placeholders.date')" :error-messages="errors.ends_on" :config="{ dateFormat: 'Y-m-d', static: false, minDate: form.starts_on || undefined, onOpen: dockDialogPicker }" /></div>
            </VCol>

            <VCol cols="12">
              <div class="erp-field academy-class-form__weekdays">
                <span class="erp-field-label">{{ t('academy.fields.weekdays') }}</span>
                <div class="academy-class-form__weekday-list" :aria-label="t('academy.fields.weekdays')">
                  <label v-for="day in weekdays" :key="day.value" class="academy-class-form__weekday-option" :class="{ 'academy-class-form__weekday-option--active': form.weekdays.includes(day.value) }">
                    <VCheckboxBtn v-model="form.weekdays" :value="day.value" color="primary" density="compact" hide-details />
                    <span>{{ day.label }}</span>
                  </label>
                </div>
                <div v-if="errors.weekdays" class="text-error text-caption mt-1">{{ errors.weekdays }}</div>
              </div>
            </VCol>
            <VCol cols="12"><VSwitch v-model="form.is_active" :label="t('academy.fields.active')" color="primary" hide-details class="academy-class-form__active" /></VCol>
          </VRow>
        </VCardText>

        <VCardActions class="academy-class-form__footer px-5 py-4">
          <VSpacer />
          <VBtn color="secondary" variant="flat" class="academy-class-form__cancel" :disabled="saving" @click="open = false">{{ t('common.actions.cancel') }}</VBtn>
          <VBtn color="primary" variant="flat" type="submit" :loading="saving" :disabled="!form.name.trim()">{{ t('common.actions.save') }}</VBtn>
        </VCardActions>
      </VForm>
    </VCard>
  </VDialog>
</template>

<style scoped>
.academy-class-form-card { overflow: hidden; }
.academy-class-form__header,
.academy-class-form__footer {
  align-items: center;
  background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%);
  display: flex;
}
.academy-class-form__header { border-block-end: 1px solid rgba(var(--v-theme-on-surface), .07); }
.academy-class-form__footer { border-block-start: 1px solid rgba(var(--v-theme-on-surface), .07); }
.academy-class-form__body { background: rgb(var(--v-theme-surface)); }
.academy-class-form__grid { row-gap: 6px; }
.academy-class-form__close { border: 1px solid rgba(var(--v-theme-error), .38) !important; border-radius: 999px !important; color: rgb(var(--v-theme-error)) !important; }
.academy-class-form__close:hover { background: rgba(var(--v-theme-error), .18) !important; }
.academy-class-form__cancel { border: 1px solid rgba(var(--v-theme-secondary), .45) !important; background: rgba(var(--v-theme-secondary), .12) !important; color: rgb(var(--v-theme-secondary)) !important; }
.academy-class-form__cancel:hover { background: rgb(var(--v-theme-secondary)) !important; color: rgb(var(--v-theme-on-secondary)) !important; }
.academy-class-form__weekdays { display: grid; gap: 6px; }
.academy-class-form__weekday-list { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: .5rem; }
.academy-class-form__weekday-option { align-items: center; display: flex; gap: .25rem; min-block-size: 42px; padding-inline: .55rem; border: 1px solid rgba(var(--v-border-color), .7); border-radius: 9px; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); cursor: pointer; font-size: .8125rem; font-weight: 600; transition: background-color .16s ease, border-color .16s ease, color .16s ease; }
.academy-class-form__weekday-option:hover { border-color: rgba(var(--v-theme-primary), .38); background: rgba(var(--v-theme-primary), .06); color: rgb(var(--v-theme-primary)); }
.academy-class-form__weekday-option--active { border-color: rgba(var(--v-theme-primary), .42); background: rgba(var(--v-theme-primary), .12); color: rgb(var(--v-theme-primary)); }
.academy-class-form__weekday-option :deep(.v-selection-control) { flex: none; }
.academy-class-form__weekday-option :deep(.v-selection-control__wrapper) { inline-size: 26px; block-size: 26px; }
.academy-class-form__active { margin: 0; }

:global(.v-overlay:has(.academy-class-form-dialog) .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .28) !important; backdrop-filter: blur(7px); opacity: 1 !important; }
:global(.academy-class-form-dialog) { max-block-size: calc(100dvh - 2rem); }

@media (max-width: 599px) {
  .academy-class-form__header,
  .academy-class-form__footer { padding-inline: 1rem !important; }
  .academy-class-form__body { padding: 1rem !important; }
  .academy-class-form__weekday-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
