<script setup lang="ts">
import ErpField from '@/components/erp/ErpField.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import type { ApplicantPayload } from '@/api/applicants'
import MasterDataApi, { type EducationalCatalog, type MasterDataRecord } from '@/api/educationalMasterData'
import { AcademyTeachersApi, type AcademyTeacher } from '@/api/academyTeachers'
import ContractTypeApi, { type ContractType } from '@/api/contractType'
import { useAcademyEnabled } from '@/composables/useAcademyEnabled'

const model = defineModel<ApplicantPayload>({ required: true })
const languageId = defineModel<number | null>('languageId')
const props = defineProps<{
  fixedService?: boolean
  showLanguage?: boolean
  /** The academy contract creates its student record and number automatically. */
  showStudentId?: boolean
  /** Lets the contract form place its fee and staff fields in the same grid. */
  contractLayout?: boolean
  /** Renders only the fields that belong to a contract-wizard step. */
  contractStep?: 1 | 2 | 3
  languages?: Array<{ id: number; name: string }>
}>()
const { t } = useI18n()
const toast = useToast()
const { enabled: academyEnabled, load: loadAcademyEnabled } = useAcademyEnabled()
const loading = ref(false)
const catalogs = ref<Partial<Record<EducationalCatalog, MasterDataRecord[]>>>({})
const contractTypes = ref<ContractType[]>([])
const teachers = ref<AcademyTeacher[]>([])
const serviceOptions = computed(() => [
  { title: t('educational.builtInServices.scholarship'), value: 'scholarship' },
  { title: t('educational.builtInServices.testOffer'), value: 'test_offer' },
  ...(academyEnabled.value ? [{ title: t('educational.builtInServices.academy'), value: 'academy' }] : []),
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
const visibleOnStep = (...steps: Array<1 | 2 | 3>) => !props.contractStep || steps.includes(props.contractStep)

onMounted(async () => {
  loading.value = true
  // Academy's whole API tree is unavailable when the module is off, so its
  // teacher list must not be requested for the other service forms.
  const academyOn = academyEnabled.value ?? await loadAcademyEnabled()
  const names: EducationalCatalog[] = ['countries', 'education-levels', 'semesters', 'subjects', 'tests', 'skills', 'programs', 'classes']
  const results = await Promise.allSettled([
    ...names.map(async name => { catalogs.value[name] = await MasterDataApi.list(name) }),
    ...(academyOn ? [AcademyTeachersApi.list().then(rows => { teachers.value = rows })] : []),
    ContractTypeApi.getAll().then(rows => { contractTypes.value = rows }),
  ])
  loading.value = false
  if (results.some(result => result.status === 'rejected'))
    toast.error(t('educational.messages.loadFiltersFailed'))
})
</script>

<template>
  <VRow class="applicant-service-fields">
    <VCol v-if="!props.fixedService" cols="12"><ErpField v-model="model.service" kind="select" :items="serviceOptions" :label="t('educational.service')" :placeholder="t('serviceContracts.placeholders.service')" required /></VCol>
    <template v-if="model.service === 'scholarship' || model.service === 'private'">
      <template v-if="visibleOnStep(2)">
        <slot v-if="props.contractLayout && model.service === 'scholarship'" name="scholarship-staff" />
        <slot v-if="props.contractLayout && model.service === 'private'" name="private-prefix" />
        <VCol cols="12" sm="6" :md="['private', 'scholarship'].includes(model.service) ? 6 : 3"><ErpField v-model="model.grade_score" type="number" min="0" max="100" step="0.01" suffix="%" :label="t('erp.percentage')" :placeholder="t('serviceContracts.placeholders.gradeScore')" required clearable /></VCol>
        <VCol cols="12" sm="6" :md="['private', 'scholarship'].includes(model.service) ? 6 : 3"><ErpField v-model="model.country_id" kind="select" :items="options('countries')" :label="t('educational.destinationCountry')" :placeholder="t('serviceContracts.placeholders.country')" required clearable /></VCol>
        <slot v-if="props.contractLayout && model.service === 'scholarship'" name="scholarship-version" />
        <slot v-if="props.contractLayout && model.service === 'private'" name="private-version" />
        <VCol v-if="props.showLanguage" cols="12" sm="6" :md="['private', 'scholarship'].includes(model.service) ? 6 : 3"><ErpField v-model="languageId" kind="select" :items="props.languages ?? []" item-title="name" item-value="id" :label="t('serviceContracts.language')" :placeholder="t('serviceContracts.placeholders.language')" clearable /></VCol>
        <VCol cols="12" sm="6" :md="['private', 'scholarship'].includes(model.service) ? 6 : 3"><ErpField v-model="model.education_level_id" kind="select" :items="options('education-levels')" :label="t('educational.educationLevel')" :placeholder="t('serviceContracts.placeholders.educationLevel')" required clearable /></VCol>
        <VCol v-if="model.service === 'private'" cols="12" sm="6" md="6"><ErpField v-model="model.semester_id" kind="select" :items="options('semesters')" :label="t('educational.semester')" :placeholder="t('serviceContracts.placeholders.semester')" required clearable /></VCol>
        <VCol v-if="model.service === 'private'" cols="12" sm="6" md="6"><ErpField v-model="model.contract_type_id" kind="select" :items="contractTypeOptions" :label="t('educational.contractType')" :placeholder="t('serviceContracts.placeholders.contractType')" clearable /></VCol>
        <VCol cols="12" sm="6" :md="['private', 'scholarship'].includes(model.service) ? 6 : 3"><ErpField v-model="model.favorite_subject_ids" class="requested-subjects" kind="select" :items="options('subjects')" :label="t('erp.requestedFields')" :placeholder="t('serviceContracts.placeholders.subjects')" multiple chips closable-chips clearable /></VCol>
      </template>
      <template v-if="model.service === 'private' && visibleOnStep(3)">
        <VCol cols="12" sm="6" md="6"><ErpField v-model="model.university_fee" type="number" min="0" step="0.01" :label="t('educational.serviceFields.universityFee')" :placeholder="t('serviceContracts.placeholders.universityFee')" clearable /></VCol>
        <slot v-if="props.contractLayout" name="private-fees" />
      </template>
    </template>
    <template v-else-if="model.service === 'test_offer'">
      <template v-if="visibleOnStep(props.contractLayout ? 1 : 2)">
        <VCol cols="12" sm="6" md="6"><ErpField v-model="model.test_id" kind="select" :items="options('tests')" :label="t('educational.masterData.tests')" :placeholder="t('serviceContracts.placeholders.test')" required clearable /></VCol>
        <slot v-if="props.contractLayout" name="test-offer-version" />
        <VCol v-if="props.showLanguage" cols="12" sm="6" md="6"><ErpField v-model="languageId" kind="select" :items="props.languages ?? []" item-title="name" item-value="id" :label="t('serviceContracts.language')" :placeholder="t('serviceContracts.placeholders.language')" clearable /></VCol>
        <VCol cols="12" sm="6" md="6"><ErpField v-model="model.skill_ids" class="requested-subjects" kind="select" :items="options('skills')" :label="t('educational.masterData.skills')" :placeholder="t('serviceContracts.placeholders.skills')" required multiple chips closable-chips clearable /></VCol>
      </template>
    </template>
    <template v-else-if="model.service === 'academy'">
      <template v-if="visibleOnStep(1)">
        <slot v-if="props.contractLayout" name="academy-student-basics" />
        <VCol v-if="props.showStudentId !== false" cols="12" sm="6" md="6"><ErpField v-model="model.student_id" :label="t('serviceContracts.studentId')" :placeholder="t('serviceContracts.placeholders.studentId')" maxlength="64" required /></VCol>
        <VCol v-if="props.showLanguage" cols="12" sm="6" md="6"><ErpField v-model="languageId" kind="select" :items="props.languages ?? []" item-title="name" item-value="id" :label="t('serviceContracts.language')" :placeholder="t('serviceContracts.placeholders.language')" clearable /></VCol>
      </template>
      <template v-if="visibleOnStep(2)">
        <VCol cols="12" sm="6" md="6"><ErpField v-model="model.teacher_ids" class="requested-subjects" kind="select" :items="teachers" item-title="name" item-value="id" :label="t('educational.masterData.teachers')" :placeholder="t('serviceContracts.placeholders.teachers')" required multiple chips closable-chips clearable /></VCol>
        <VCol cols="12" sm="6" md="6"><ErpField v-model="model.program_id" kind="select" :items="options('programs')" :label="t('educational.masterData.programs')" :placeholder="t('serviceContracts.placeholders.program')" required clearable /></VCol>
        <VCol cols="12" class="contract-wizard-full-row"><ErpField v-model="model.class_id" kind="select" :items="options('classes')" :label="t('erp.class')" :placeholder="t('serviceContracts.placeholders.class')" required clearable /></VCol>
      </template>
      <slot v-if="props.contractLayout && visibleOnStep(3)" name="academy-payment" />
    </template>
  </VRow>
</template>

<style scoped>
.applicant-service-fields {
  --v-gutter-x: 12px;
  --v-gutter-y: 12px;
}

/* Keep chosen subjects on one quiet, horizontally scrollable line. This keeps
   the compact form height stable without exposing an always-visible scrollbar. */
:global(.requested-subjects .v-field__input) {
  align-items: center;
  flex-wrap: nowrap !important;
  max-inline-size: 100%;
  overflow-x: auto !important;
  white-space: nowrap;
  scrollbar-width: none;
}

:global(.requested-subjects .v-select__selection) {
  display: flex;
  flex-wrap: nowrap !important;
  min-inline-size: 0;
  max-inline-size: 100%;
  overflow-x: auto;
  overscroll-behavior-inline: contain;
  scrollbar-width: none;
}

:global(.requested-subjects .v-field__input::-webkit-scrollbar) { display: none; }
:global(.requested-subjects .v-select__selection::-webkit-scrollbar) { display: none; }
:global(.requested-subjects .v-chip) { flex: 0 0 auto; }
:global(.requested-subjects .v-select__selection-text) { flex: 0 0 auto; white-space: nowrap; }
</style>
