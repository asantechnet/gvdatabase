<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { AcademyClassesApi, type AcademyClassPayload, type AcademyClassRecord, type AcademyDurationRecord, type AcademyOption } from '@/api/academyClasses'
import { apiFieldErrors } from '@/utils/apiErrors'

const props = defineProps<{
  modelValue: boolean
  record: AcademyClassRecord | null
  programs: AcademyOption[]
  times: AcademyOption[]
  durations: AcademyDurationRecord[]
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
const weekdays = [
  { value: 1, label: 'Mon' },
  { value: 2, label: 'Tue' },
  { value: 3, label: 'Wed' },
  { value: 4, label: 'Thu' },
  { value: 5, label: 'Fri' },
  { value: 6, label: 'Sat' },
  { value: 7, label: 'Sun' },
]
const form = reactive<AcademyClassPayload>({
  name: '',
  description: '',
  program_id: null,
  time_id: null,
  duration_id: null,
  teacher_ids: [],
  weekdays: [],
  starts_on: null,
  ends_on: null,
  starts_at: null,
  ends_at: null,
  capacity: null,
  fee: null,
  is_active: true,
  sort_order: 0,
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
  form.time_id = record?.time?.id ?? null
  form.duration_id = record?.duration?.id ?? null
  form.teacher_ids = record?.teachers.map(teacher => teacher.id) ?? []
  form.weekdays = [...(record?.weekdays ?? [])]
  form.starts_on = record?.starts_on ?? null
  form.ends_on = record?.ends_on ?? null
  form.starts_at = record?.starts_at ?? null
  form.ends_at = record?.ends_at ?? null
  form.capacity = record?.capacity ?? null
  form.fee = record?.fee ?? null
  form.is_active = record?.is_active ?? true
  form.sort_order = record?.sort_order ?? 0
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
</script>

<template>
  <VDialog v-model="open" max-width="820" scrollable>
    <VCard>
      <VCardTitle>{{ t(record ? 'academy.classes.editTitle' : 'academy.classes.createTitle') }}</VCardTitle>
      <VCardText>
        <VRow>
          <VCol cols="12" md="8">
            <VTextField
              v-model="form.name"
              :label="t('academy.fields.name')"
              :error-messages="errors.name"
              required
            />
          </VCol>
          <VCol cols="12" md="4">
            <VTextField v-model.number="form.sort_order" type="number" min="0" :label="t('academy.fields.sortOrder')" />
          </VCol>
          <VCol cols="12" md="4">
            <VSelect
              v-model="form.program_id"
              :items="programs"
              item-title="name"
              item-value="id"
              :label="t('academy.fields.program')"
              clearable
              :error-messages="errors.program_id"
            />
          </VCol>
          <VCol cols="12" md="4">
            <VSelect
              v-model="form.time_id"
              :items="times"
              item-title="name"
              item-value="id"
              :label="t('academy.fields.time')"
              clearable
              :error-messages="errors.time_id"
            />
          </VCol>
          <VCol cols="12" md="4">
            <VSelect
              v-model="form.duration_id"
              :items="durations"
              item-title="name"
              item-value="id"
              :label="t('academy.fields.duration')"
              clearable
              :error-messages="errors.duration_id"
            />
          </VCol>
          <VCol cols="12">
            <VSelect
              v-model="form.teacher_ids"
              :items="teachers"
              item-title="name"
              item-value="id"
              :label="t('academy.fields.teachers')"
              multiple
              chips
              :error-messages="errors.teacher_ids"
            />
          </VCol>
          <VCol cols="12">
            <div class="text-subtitle-2 mb-1">{{ t('academy.fields.weekdays') }}</div>
            <div class="weekday-options">
              <VCheckbox
                v-for="day in weekdays"
                :key="day.value"
                v-model="form.weekdays"
                :label="day.label"
                :value="day.value"
                hide-details
              />
            </div>
            <div v-if="errors.weekdays" class="text-error text-caption mt-1">{{ errors.weekdays }}</div>
          </VCol>
          <VCol cols="6" md="3">
            <VTextField v-model="form.starts_at" type="time" :label="t('academy.fields.startsAt')" :error-messages="errors.starts_at" />
          </VCol>
          <VCol cols="6" md="3">
            <VTextField v-model="form.ends_at" type="time" :label="t('academy.fields.endsAt')" :error-messages="errors.ends_at" />
          </VCol>
          <VCol cols="6" md="3">
            <VTextField v-model="form.starts_on" type="date" :label="t('academy.fields.startsOn')" :error-messages="errors.starts_on" />
          </VCol>
          <VCol cols="6" md="3">
            <VTextField v-model="form.ends_on" type="date" :label="t('academy.fields.endsOn')" :error-messages="errors.ends_on" />
          </VCol>
          <VCol cols="6" md="3">
            <VTextField v-model.number="form.capacity" type="number" min="1" max="10000" :label="t('academy.fields.capacity')" :error-messages="errors.capacity" />
          </VCol>
          <VCol cols="6" md="3">
            <VTextField v-model.number="form.fee" type="number" min="0" :label="t('academy.fields.fee')" :error-messages="errors.fee" />
          </VCol>
          <VCol cols="12" md="6">
            <VSwitch v-model="form.is_active" :label="t('academy.fields.active')" />
          </VCol>
          <VCol cols="12">
            <VTextarea v-model="form.description" :label="t('academy.fields.description')" rows="3" :error-messages="errors.description" />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn :disabled="saving" @click="open = false">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :loading="saving" :disabled="!form.name.trim()" @click="save">{{ t('common.actions.save') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.weekday-options { display: flex; flex-wrap: wrap; gap: 4px 20px; }
.weekday-options :deep(.v-checkbox) { flex: 0 0 auto; }
</style>
