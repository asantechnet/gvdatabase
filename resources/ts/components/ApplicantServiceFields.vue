<script setup lang="ts">
import ErpField from '@/components/erp/ErpField.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import type { ApplicantPayload } from '@/api/applicants'
import MasterDataApi, { type EducationalCatalog, type MasterDataRecord } from '@/api/educationalMasterData'
import ContractTypeApi, { type ContractType } from '@/api/contractType'

const model = defineModel<ApplicantPayload>({ required: true })
const languageId = defineModel<number | null>('languageId')
const props = defineProps<{
  fixedService?: boolean
  showLanguage?: boolean
  languages?: Array<{ id: number; name: string }>
}>()
const { t } = useI18n()
const toast = useToast()
const loading = ref(false)
const catalogs = ref<Partial<Record<EducationalCatalog, MasterDataRecord[]>>>({})
const contractTypes = ref<ContractType[]>([])
const serviceOptions = computed(() => [
  { title: t('educational.builtInServices.scholarship'), value: 'scholarship' },
  { title: t('educational.builtInServices.testOffer'), value: 'test_offer' },
  { title: t('educational.builtInServices.academy'), value: 'academy' },
  { title: t('educational.builtInServices.private'), value: 'private' },
])
// One row per service; nothing from another service is ever rendered.
const selectedFields: Partial<Record<EducationalCatalog, keyof ApplicantPayload>> = {
  countries: 'country_id',
  'education-levels': 'education_level_id',
  semesters: 'semester_id',
  subjects: 'favorite_subject_ids',
  tests: 'test_id',
  skills: 'skill_ids',
  classes: 'class_id',
  programs: 'program_id',
  times: 'time_id',
  durations: 'duration_id',
  teachers: 'teacher_ids',
}
const options = (catalog: EducationalCatalog) => {
  const field = selectedFields[catalog]
  const selected = field ? model.value[field] : undefined
  return (catalogs.value[catalog] ?? [])
    .filter(row => row.is_active || selected === row.id || (Array.isArray(selected) && selected.some(id => id === row.id)))
    .map(row => ({ title: row.name, value: row.id }))
}
const contractTypeOptions = computed(() => contractTypes.value
  .filter(row => row.is_active && !['employment', 'other'].includes(row.slug))
  .map(row => ({ title: row.name, value: row.id })))

onMounted(async () => {
  loading.value = true
  const names: EducationalCatalog[] = ['countries', 'education-levels', 'semesters', 'subjects', 'tests', 'skills', 'programs', 'times', 'durations', 'teachers', 'classes']
  const results = await Promise.allSettled([
    ...names.map(async name => { catalogs.value[name] = await MasterDataApi.list(name) }),
    ContractTypeApi.getAll().then(rows => { contractTypes.value = rows }),
  ])
  loading.value = false
  if (results.some(result => result.status === 'rejected'))
    toast.error(t('educational.messages.loadFiltersFailed'))
})
</script>

<template>
  <VRow dense>
    <VCol v-if="!props.fixedService" cols="12"><ErpField v-model="model.service" kind="select" :items="serviceOptions" :label="t('educational.service')" required /></VCol>
    <template v-if="model.service === 'scholarship' || model.service === 'private'">
      <VCol cols="12" sm="6" md="3"><ErpField v-model="model.grade_score" type="number" min="0" max="100" step="0.01" suffix="%" :label="t('erp.percentage')" required clearable /></VCol>
      <VCol cols="12" sm="6" md="3"><ErpField v-model="model.country_id" kind="select" :items="options('countries')" :label="t('educational.destinationCountry')" required clearable /></VCol>
      <VCol v-if="props.showLanguage" cols="12" sm="6" md="3"><ErpField v-model="languageId" kind="select" :items="props.languages ?? []" item-title="name" item-value="id" :label="t('serviceContracts.language')" required /></VCol>
      <VCol cols="12" sm="6" md="3"><ErpField v-model="model.education_level_id" kind="select" :items="options('education-levels')" :label="t('educational.educationLevel')" required clearable /></VCol>
      <VCol v-if="model.service === 'private'" cols="12" sm="6" md="3"><ErpField v-model="model.semester_id" kind="select" :items="options('semesters')" :label="t('educational.semester')" required clearable /></VCol>
      <VCol v-if="model.service === 'private'" cols="12" sm="6" md="3"><ErpField v-model="model.contract_type_id" kind="select" :items="contractTypeOptions" :label="t('educational.contractType')" required clearable /></VCol>
      <VCol v-if="model.service === 'private'" cols="12" sm="6" md="3"><ErpField v-model="model.university_fee" type="number" min="0" step="0.01" :label="t('educational.serviceFields.universityFee')" required clearable /></VCol>
      <VCol cols="12" sm="6" md="3"><ErpField v-model="model.favorite_subject_ids" kind="select" :items="options('subjects')" :label="t('erp.requestedFields')" required multiple chips closable-chips clearable /></VCol>
    </template>
    <template v-else-if="model.service === 'test_offer'">
      <VCol cols="12" sm="6" md="4"><ErpField v-model="model.test_id" kind="select" :items="options('tests')" :label="t('educational.masterData.tests')" required clearable /></VCol>
      <VCol v-if="props.showLanguage" cols="12" sm="6" md="4"><ErpField v-model="languageId" kind="select" :items="props.languages ?? []" item-title="name" item-value="id" :label="t('serviceContracts.language')" required /></VCol>
      <VCol cols="12" sm="6" md="4"><ErpField v-model="model.skill_ids" kind="select" :items="options('skills')" :label="t('educational.masterData.skills')" required multiple chips closable-chips clearable /></VCol>
    </template>
    <template v-else-if="model.service === 'academy'">
      <VCol cols="12" sm="6" md="4"><ErpField v-model="model.student_id" :label="t('serviceContracts.studentId')" maxlength="64" required /></VCol>
      <VCol v-if="props.showLanguage" cols="12" sm="6" md="4"><ErpField v-model="languageId" kind="select" :items="props.languages ?? []" item-title="name" item-value="id" :label="t('serviceContracts.language')" required /></VCol>
      <VCol cols="12" sm="6" md="4"><ErpField v-model="model.time_id" kind="select" :items="options('times')" :label="t('educational.masterData.times')" required clearable /></VCol>
      <VCol cols="12" sm="6" md="4"><ErpField v-model="model.teacher_ids" kind="select" :items="options('teachers')" :label="t('educational.masterData.teachers')" required multiple chips closable-chips clearable /></VCol>
      <VCol cols="12" sm="6" md="4"><ErpField v-model="model.duration_id" kind="select" :items="options('durations')" :label="t('educational.masterData.durations')" required clearable /></VCol>
      <VCol cols="12" sm="6" md="4"><ErpField v-model="model.program_id" kind="select" :items="options('programs')" :label="t('educational.masterData.programs')" required clearable /></VCol>
      <VCol cols="12" sm="6" md="4"><ErpField v-model="model.class_id" kind="select" :items="options('classes')" :label="t('erp.class')" required clearable /></VCol>
    </template>
  </VRow>
</template>
