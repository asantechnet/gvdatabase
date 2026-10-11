<script setup lang="ts">
import ErpField from '@/components/erp/ErpField.vue'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useAbility } from '@casl/vue'
import type { ApplicantPayload } from '@/api/applicants'
import MasterDataApi, { type EducationalCatalog, type MasterDataRecord } from '@/api/educationalMasterData'

const model = defineModel<ApplicantPayload>({ required: true })
defineProps<{ fixedService?: boolean }>()
const { t } = useI18n()
const toast = useToast()
const ability = useAbility()
const loading = ref(false)
const catalogs = ref<Partial<Record<EducationalCatalog, MasterDataRecord[]>>>({})
const serviceOptions = computed(() => [
  { title: t('educational.builtInServices.scholarship'), value: 'scholarship' },
  { title: t('educational.builtInServices.testOffer'), value: 'test_offer' },
  { title: t('educational.builtInServices.academy'), value: 'academy' },
  { title: t('educational.builtInServices.private'), value: 'private' },
])
const selectedFields: Partial<Record<EducationalCatalog, keyof ApplicantPayload>> = {
  countries: 'country_id', 'education-levels': 'education_level_id', semesters: 'semester_id',
  subjects: 'favorite_subject_ids', tests: 'test_id', skills: 'skill_ids',
  scholarships: 'scholarship_id', classes: 'class_id', programs: 'program_id', times: 'time_id', durations: 'duration_id', teachers: 'teacher_ids',
}
const options = (catalog: EducationalCatalog) => {
  const field = selectedFields[catalog]
  const selected = field ? model.value[field] : undefined
  return (catalogs.value[catalog] ?? [])
    .filter(row => row.is_active || selected === row.id || (Array.isArray(selected) && selected.some(id => id === row.id)))
    .map(row => ({ title: row.name, value: row.id }))
}
const universities = computed(() => (catalogs.value.universities ?? [])
  .filter(row => row.country_id === model.value.country_id && (row.is_active || row.id === model.value.university_id))
  .map(row => ({ title: row.name, value: row.id })))

watch(() => model.value.country_id, (value, old) => {
  if (value !== old)
    model.value.university_id = null
})

onMounted(async () => {
  loading.value = true
  const names: EducationalCatalog[] = ['countries', 'universities', 'education-levels', 'semesters', 'subjects', 'tests', 'skills', 'programs', 'times', 'durations', 'teachers', 'scholarships', 'classes']
  const results = await Promise.allSettled([
    ...names.map(async name => { catalogs.value[name] = await MasterDataApi.list(name) }),
  ])
  loading.value = false
  if (results.some(result => result.status === 'rejected'))
    toast.error(t('educational.messages.loadFiltersFailed'))
})
</script>

<template>
  <VProgressLinear v-if="loading" indeterminate />
  <VRow dense>
    <VCol v-if="!fixedService" cols="12"><ErpField v-model="model.service" kind="select" :items="serviceOptions" :label="t('educational.service')" required /></VCol>
    <template v-if="model.service === 'scholarship' || model.service === 'private'">
      <VCol cols="12" sm="6" md="3"><ErpField v-model="model.grade_score" type="number" min="0" max="100" step="0.01" suffix="%" :label="t('erp.percentage')" clearable /></VCol>
      <VCol v-if="model.service === 'scholarship'" cols="12" sm="6" md="3"><ErpField v-model="model.scholarship_id" kind="select" :items="options('scholarships')" :label="t('erp.scholarship')" clearable /></VCol>
      <VCol cols="12" sm="6" md="3"><ErpField v-model="model.country_id" kind="select" :items="options('countries')" :label="t('educational.destinationCountry')" clearable /></VCol>
      <VCol cols="12" sm="6" md="3"><ErpField v-model="model.education_level_id" kind="select" :items="options('education-levels')" :label="t('educational.educationLevel')" clearable /></VCol>
      <VCol cols="12" sm="6" md="3"><ErpField v-model="model.semester_id" kind="select" :items="options('semesters')" :label="t('educational.semester')" clearable /></VCol>
      <VCol cols="12" sm="6" md="3"><ErpField v-model="model.university_id" kind="select" :items="universities" :disabled="!model.country_id" :label="t('educational.university')" clearable /></VCol>
      <VCol v-if="model.service === 'private'" cols="12" sm="6" md="3"><ErpField v-model="model.university_fee" type="number" min="0" step="0.01" :label="t('educational.serviceFields.universityFee')" clearable /></VCol>
      <VCol cols="12" sm="6" md="3"><ErpField v-model="model.favorite_subject_ids" kind="select" :items="options('subjects')" :label="t('erp.requestedFields')" multiple chips closable-chips clearable /></VCol>
    </template>
    <template v-else-if="model.service === 'test_offer'">
      <VCol cols="12" sm="6" md="4"><ErpField v-model="model.test_id" kind="select" :items="options('tests')" :label="t('educational.masterData.tests')" clearable /></VCol>
      <VCol cols="12" sm="6" md="4"><ErpField v-model="model.skill_ids" kind="select" :items="options('skills')" :label="t('educational.masterData.skills')" multiple chips closable-chips clearable /></VCol>
    </template>
    <template v-else-if="model.service === 'academy'">
      <VCol cols="12" sm="6" md="4"><ErpField v-model="model.time_id" kind="select" :items="options('times')" :label="t('educational.masterData.times')" clearable /></VCol>
      <VCol cols="12" sm="6" md="4"><ErpField v-model="model.teacher_ids" kind="select" :items="options('teachers')" :label="t('educational.masterData.teachers')" multiple chips closable-chips clearable /></VCol>
      <VCol cols="12" sm="6" md="4"><ErpField v-model="model.duration_id" kind="select" :items="options('durations')" :label="t('educational.masterData.durations')" clearable /></VCol>
      <VCol cols="12" sm="6" md="4"><ErpField v-model="model.program_id" kind="select" :items="options('programs')" :label="t('educational.masterData.programs')" clearable /></VCol>
      <VCol cols="12" sm="6" md="4"><ErpField v-model="model.class_id" kind="select" :items="options('classes')" :label="t('erp.class')" clearable /></VCol>
    </template>
    <VCol cols="12" class="pt-2">
      <VExpansionPanels variant="accordion" elevation="0">
        <VExpansionPanel :title="t('erp.optionalDetails')">
          <VExpansionPanelText><VRow dense>
            <VCol v-if="model.service !== 'academy'" cols="12" sm="6" md="4"><ErpField v-model="model.passport_id" :label="t('educational.serviceFields.passportId')" maxlength="64" clearable /></VCol>
            <VCol v-if="model.service === 'scholarship'" cols="12" sm="6" md="4"><ErpField v-model="model.university_fee" type="number" min="0" step="0.01" :label="t('educational.serviceFields.universityFee')" clearable /></VCol>
          </VRow></VExpansionPanelText>
        </VExpansionPanel>
      </VExpansionPanels>
    </VCol>
  </VRow>
</template>
