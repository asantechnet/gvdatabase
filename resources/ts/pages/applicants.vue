<script setup lang="ts">
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import ErpField from '@/components/erp/ErpField.vue'
import DataTableFilterMenu from '@/components/responsive-data-table/DataTableFilterMenu.vue'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import ApplicantApi, { type Applicant, type ApplicantStage, type BuiltInService, type ApplicantPayload, applicantServicePayload } from '@/api/applicants'
import ApplicantServiceFields from '@/components/ApplicantServiceFields.vue'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import EmployeeApi, { type Employee } from '@/api/employee'
import { canAccess } from '@/utils/authorization'
import { translateApplicantStage, translateBackendValue } from '@/utils/localization'
import ApplicantSummary from '@/components/erp/ApplicantSummary.vue'
import CrmRelationshipDetails from '@/components/crm/CrmRelationshipDetails.vue'
import { apiErrorMessage } from '@/utils/apiErrors'

const toast = useToast()
const router = useRouter()
const route = useRoute()
const { t } = useI18n()

const rows = ref<Applicant[]>([])
const loading = ref(false)
const page = ref(1)
const lastPage = ref(1)
const perPage = ref(25)
const total = ref(0)
const filtersOpen = ref(false)

const search = ref((route.query.search as string) ?? '')
const service = ref<BuiltInService | ''>('')
const serviceOptions = computed(() => [
  { title: t('educational.builtInServices.scholarship'), value: 'scholarship' },
  { title: t('educational.builtInServices.testOffer'), value: 'test_offer' },
  { title: t('educational.builtInServices.academy'), value: 'academy' },
  { title: t('educational.builtInServices.private'), value: 'private' },
])
// The stage tab strip drives this same filter, so deep links such as
// `?stage=visa_processing` from the dashboard and the pipeline keep working.
const stage = ref((route.query.stage as string) ?? '')
const status = ref<'active' | 'closed' | ''>((route.query.status as 'active' | 'closed') ?? '')
const paymentStatus = ref<'paid' | 'unpaid' | 'partially_paid' | ''>('')
const responsibleEmployeeId = ref<number | null>(null)
const countryId = ref<number | null>(null)
const universityId = ref<number | null>(null)
const educationLevelId = ref<number | null>(null)
const semesterId = ref<number | null>(null)
const dateFrom = ref('')
const dateTo = ref('')
const invalidDateRange = computed(() => !!dateFrom.value && !!dateTo.value && dateFrom.value > dateTo.value)

const countries = ref<MasterDataRecord[]>([])
const universities = ref<MasterDataRecord[]>([])
const levels = ref<MasterDataRecord[]>([])
const semesters = ref<MasterDataRecord[]>([])
const employees = ref<Employee[]>([])
const admissionEmployees = ref<Employee[]>([])
const stages = ref<ApplicantStage[]>([])

const canViewPipeline = computed(() => canAccess('view_applicants'))
const canCreate = computed(() => canAccess('create_applicants'))
const canAssignEmployee = computed(() => canAccess('assign_applicant_employee'))
const activeFilterCount = computed(() => [service.value, search.value, stage.value, status.value, paymentStatus.value, responsibleEmployeeId.value, countryId.value, universityId.value, educationLevelId.value, semesterId.value, dateFrom.value, dateTo.value].filter(Boolean).length)
const visibleRows = computed(() => paymentStatus.value ? rows.value.filter(row => row.payment_status === paymentStatus.value) : rows.value)

const createDialog = ref(false)
const creating = ref(false)
const createForm = ref({ name: '', father_name: '', father_phone: '', backup_phone: '', contact: '', email: '', admission_employee_id: null as number | null, responsible_employee_id: null as number | null, notes: '' })
const serviceForm = ref<ApplicantPayload>({ service: 'scholarship' })
// The list is driven by the workflow stages themselves: every stage is a tab,
// and the pipeline keeps its order while the terminal (closing) stages are
// grouped at the end. This replaces the coarse Operation / Final / Cancelled
// boards, which were just pre-grouped sets of these same stages.
const pipelineStages = computed(() => stages.value.filter(item => !item.is_terminal))
const terminalStages = computed(() => stages.value.filter(item => item.is_terminal))

const terminalStageIcons: Record<string, string> = {
  completed: 'tabler-circle-check',
  cancelled: 'tabler-ban',
  rejected: 'tabler-circle-x',
  withdrawn: 'tabler-arrow-back-up',
}

const terminalStageIcon = (key: string) => terminalStageIcons[key] ?? 'tabler-flag'

const terminalStageColor = (key: string) => {
  if (key === 'completed')
    return 'success'

  return key === 'rejected' ? 'error' : 'secondary'
}

async function load() {
  if (invalidDateRange.value) return
  loading.value = true
  try {
    const result = await ApplicantApi.list({
      service: service.value || undefined,
      stage: stage.value || undefined,
      status: (status.value || undefined) as 'active' | 'closed' | undefined,
      search: search.value || undefined,
      responsible_employee_id: responsibleEmployeeId.value,
      country_id: countryId.value,
      university_id: universityId.value,
      education_level_id: educationLevelId.value,
      semester_id: semesterId.value,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
      page: page.value,
      per_page: perPage.value,
    })

    rows.value = result.data
    page.value = result.current_page
    lastPage.value = result.last_page
    perPage.value = result.per_page
    total.value = result.total
  }
  catch {
    toast.error(t('educational.messages.loadApplicantsFailed'))
  }
  finally {
    loading.value = false
  }
}

async function loadCatalogs() {
  try {
    const [countryRows, universityRows, levelRows, semesterRows, employeeRows, stageRows, admissionRows] = await Promise.all([
      MasterDataApi.list('countries'),
      MasterDataApi.list('universities'),
      MasterDataApi.list('education-levels'),
      MasterDataApi.list('semesters'),
      canAccess('view_employees') ? EmployeeApi.getOptions() : Promise.resolve([]),
      ApplicantApi.stages(),
      EmployeeApi.getAssignmentOptions('admission'),
    ])

    countries.value = countryRows
    universities.value = universityRows
    levels.value = levelRows
    semesters.value = semesterRows
    employees.value = employeeRows
    admissionEmployees.value = admissionRows
    stages.value = stageRows
  }
  catch {
    toast.error(t('educational.messages.loadFiltersFailed'))
  }
}

function onTabChange() {
  page.value = 1
  load()
}

function openCreate() {
  createForm.value = { name: '', father_name: '', father_phone: '', backup_phone: '', contact: '', email: '', admission_employee_id: null, responsible_employee_id: null, notes: '' }
  serviceForm.value = { service: 'scholarship' }
  createDialog.value = true
}

async function create() {
  if (!createForm.value.name.trim())
    return

  creating.value = true
  try {
    const payload: Record<string, unknown> = {
      name: createForm.value.name.trim(),
      father_name: createForm.value.father_name.trim() || null,
      father_phone: createForm.value.father_phone.trim() || null,
      backup_phone: createForm.value.backup_phone || null,
      contact: createForm.value.contact || null,
      email: createForm.value.email || null,
      admission_employee_id: createForm.value.admission_employee_id,
    }

    Object.assign(payload, applicantServicePayload(serviceForm.value))
    // Academy enrolment has no admission officer, so no employee is attached.
    if (serviceForm.value.service !== 'academy')
      payload.admission_employee_id = createForm.value.admission_employee_id
    else
      delete payload.admission_employee_id
    if (createForm.value.responsible_employee_id)
      payload.responsible_employee_id = createForm.value.responsible_employee_id

    if (createForm.value.notes.trim())
      payload.notes = createForm.value.notes.trim()

    const created = await ApplicantApi.create(payload)

    createDialog.value = false
    await load()
    toast.success(t('educational.messages.caseCreated', { id: created.id }))
    router.push({ name: 'applicant-case', params: { id: created.id } })
  }
  catch (error) {
    toast.error(apiErrorMessage(error, t('educational.messages.createCaseFailed')))
  }
  finally {
    creating.value = false
  }
}

function clearFilters() {
  service.value = ''
  search.value = ''
  stage.value = ''
  status.value = ''
  paymentStatus.value = ''
  responsibleEmployeeId.value = null
  countryId.value = null
  universityId.value = null
  educationLevelId.value = null
  semesterId.value = null
  dateFrom.value = ''
  dateTo.value = ''
  page.value = 1
  load()
}

// Pagination controls call load() explicitly; no page watcher, so changing
// pages never fires duplicate requests.
watch(countryId, () => {
  const stillVisible = universities.value
    .filter(u => u.is_active && (!countryId.value || u.country_id === countryId.value))
    .some(u => u.id === universityId.value)

  if (!stillVisible)
    universityId.value = null
})


onMounted(async () => {
  await loadCatalogs()
  await load()
})
</script>

<template>
  <div class="education-workspace erp-workspace">
    <ErpPageHeader :title="$t('educational.applications')" section="applicants" :breadcrumbs="false">
      <template #actions><VBtn v-if="canCreate" color="primary" prepend-icon="tabler-plus" @click="openCreate">{{ $t('educational.newCase') }}</VBtn></template>
    </ErpPageHeader>
  <VCard class="education-panel">
    <VCardText>
      <VRow dense align="center">
        <VCol cols="12" sm="8" md="6"><VTextField v-model="search" :label="$t('educational.searchApplicant')" prepend-inner-icon="tabler-search" clearable hide-details @keyup.enter="page = 1; load()" /></VCol>
        <VCol cols="12" sm="4" md="6" class="d-flex justify-end">
          <DataTableFilterMenu v-model="filtersOpen" :active="activeFilterCount > 0" @reset="clearFilters">
            <VSelect v-model="service" :items="serviceOptions" :label="$t('educational.service')" clearable />
            <ErpDateRange v-model:from="dateFrom" v-model:to="dateTo" :show-apply="false" />
            <VSelect v-model="status" :items="[{ title: $t('educational.active'), value: 'active' }, { title: $t('educational.closed'), value: 'closed' }]" :label="$t('educational.status')" clearable />
            <VSelect v-model="paymentStatus" :items="[{ title: $t('finance.paymentStatus.paid'), value: 'paid' }, { title: $t('finance.paymentStatus.partially_paid'), value: 'partially_paid' }, { title: $t('finance.paymentStatus.unpaid'), value: 'unpaid' }]" :label="$t('finance.paymentStatus')" clearable />
            <VSelect v-model="responsibleEmployeeId" :items="employees.map(e => ({ title: e.name, value: e.id }))" :label="$t('educational.employee')" clearable />
            <VSelect v-model="countryId" :items="countries.filter(c => c.is_active).map(c => ({ title: c.name, value: c.id }))" :label="$t('educational.country')" clearable />
            <VSelect v-model="universityId" :items="universities.filter(u => u.is_active && (!countryId || u.country_id === countryId)).map(u => ({ title: u.name, value: u.id }))" :label="$t('educational.university')" clearable />
            <VSelect v-model="educationLevelId" :items="levels.filter(l => l.is_active).map(l => ({ title: l.name, value: l.id }))" :label="$t('educational.educationLevel')" clearable />
            <VSelect v-model="semesterId" :items="semesters.filter(s => s.is_active).map(s => ({ title: s.name, value: s.id }))" :label="$t('educational.semester')" clearable />
            <VBtn color="primary" :loading="loading" :disabled="invalidDateRange" @click="page = 1; load()">{{ $t('educational.apply') }}</VBtn>
          </DataTableFilterMenu>
        </VCol>
      </VRow>
    </VCardText>
    <VTabs v-model="stage" show-arrows class="px-3" @update:model-value="onTabChange">
      <VTab value="">{{ $t('educational.allApplications') }}</VTab>
      <VTab v-for="item in pipelineStages" :key="item.id" :value="item.key">{{ translateApplicantStage(item) }}</VTab>
      <VDivider v-if="terminalStages.length && pipelineStages.length" vertical class="stage-tabs-divider align-self-center" />
      <VTab v-for="item in terminalStages" :key="item.id" :value="item.key">
        <VIcon start size="small" :icon="terminalStageIcon(item.key)" :color="terminalStageColor(item.key)" />
        {{ translateApplicantStage(item) }}
        <VTooltip activator="parent" location="bottom">{{ $t('educational.terminalStageTab') }}</VTooltip>
      </VTab>
    </VTabs>
    <VDivider />
    <div class="d-flex align-center gap-2 px-4 py-3"><VIcon icon="tabler-users" color="primary" /><strong>{{ $t('educational.resultCount', { count: total }) }}</strong><VSpacer /><VBtn v-if="activeFilterCount" size="small" variant="text" prepend-icon="tabler-filter" @click="filtersOpen = true">{{ $t('educational.activeFilters', { count: activeFilterCount }) }}</VBtn></div>
    <VProgressLinear v-if="loading" indeterminate />
    <div class="education-table-scroll"><VTable density="compact" class="education-table">
      <thead><tr><th>{{ $t('educational.id') }}</th><th>{{ $t('educational.applicant') }}</th><th>{{ $t('educational.caseUi.file') }}</th><th>{{ $t('educational.employee') }}</th><th>{{ $t('educational.destination') }}</th><th>{{ $t('erp.details') }}</th><th>{{ $t('educational.stage') }}</th><th>{{ $t('educational.status') }}</th><th>{{ $t('finance.paymentStatus') }}</th><th /></tr></thead>
      <tbody>
        <tr v-for="row in visibleRows" :key="row.id" class="cursor-pointer" @click="router.push({ name: 'applicant-case', params: { id: row.id } })">
          <td>#{{ row.id }}</td>
          <td @click.stop><RouterLink class="applicant-name-link" :to="{ name: 'all-customers', query: { contact: row.id } }" :title="$t('educational.openContactInCrm')">{{ row.name }}</RouterLink></td>
          <td><VChip size="x-small" :color="row.attachments_count ? 'success' : 'warning'" variant="tonal">{{ row.attachments_count ? $t('educational.caseUi.documentCount', { count: row.attachments_count }) : $t('educational.caseUi.documentsNeeded') }}</VChip></td>
          <td>{{ row.service === 'academy' ? '—' : (row.responsible_employee?.name ?? $t('educational.unassigned')) }}</td>
          <td><VChip size="x-small" variant="tonal" class="me-2">{{ serviceOptions.find(option => option.value === row.service)?.title }}</VChip><span v-if="['scholarship', 'private'].includes(row.service)">{{ row.details?.country?.name ?? '—' }}{{ row.details?.university?.name ? ` · ${row.details.university.name}` : '' }}</span></td>
          <td><ApplicantSummary :applicant="row" /></td><td>{{ row.current_stage ? translateApplicantStage(row.current_stage) : '—' }}</td>
          <td><VChip size="small" :color="row.status === 'active' ? 'success' : 'secondary'">{{ translateBackendValue(row.status) }}</VChip></td>
          <td><VChip v-if="row.payment_status" size="small" variant="tonal" :color="row.payment_status === 'paid' ? 'success' : row.payment_status === 'partially_paid' ? 'warning' : 'error'">{{ $t(`finance.paymentStatus.${row.payment_status}`) }}</VChip><span v-else class="text-medium-emphasis">—</span></td>
          <td class="text-end"><div class="d-inline-flex align-center ga-1" @click.stop><CrmRelationshipDetails type="applicant" :id="row.id" :title="row.name" /><VBtn size="small" variant="text" @click="router.push({ name: 'applicant-case', params: { id: row.id } })">{{ $t('educational.openCase') }}</VBtn></div></td>
        </tr>
        <tr v-if="!loading && !visibleRows.length"><td colspan="10" class="text-center text-medium-emphasis py-8">{{ $t('educational.noApplicants') }}</td></tr>
      </tbody>
    </VTable></div>
    <VCardText class="d-flex align-center gap-4">
      <VPagination v-model="page" :length="Math.max(lastPage, 1)" size="small" @update:model-value="load" />
      <VSelect v-model="perPage" :items="[10, 25, 50, 100]" :label="$t('educational.perPage')" style="max-inline-size: 140px;" @update:model-value="page = 1; load()" />
    </VCardText>
  </VCard>
  </div>
  <VDialog v-model="createDialog" max-width="1100" scrollable class="erp-form">
    <VCard :title="$t('educational.newApplicantCase')">
      <VCardText>
        <VRow dense>
          <VCol cols="12" sm="6" md="4"><ErpField v-model="createForm.name" :label="$t('common.fields.name')" required /></VCol>
          <VCol cols="12" sm="6" md="4"><ErpField v-model="createForm.contact" :label="$t('common.messages.contact')" /></VCol>
          <VCol cols="12" sm="6" md="4"><ErpField v-model="createForm.email" :label="$t('common.fields.email')" type="email" /></VCol>
          <VCol v-if="['scholarship', 'private'].includes(serviceForm.service || '')" cols="12" sm="6" md="4"><ErpField v-model="createForm.father_name" :label="$t('educational.fatherName')" /></VCol>
          <VCol v-if="['scholarship', 'private'].includes(serviceForm.service || '')" cols="12" sm="6" md="4"><ErpField v-model="createForm.father_phone" :label="$t('educational.fatherPhone')" /></VCol>
          <VCol v-if="serviceForm.service !== 'academy'" cols="12" sm="6" md="4"><ErpField kind="select" v-model="createForm.admission_employee_id" :items="admissionEmployees.map(e => ({ title: e.name, value: e.id }))" :label="$t('agencyStaff.admission')" clearable /></VCol>
          <VCol v-if="serviceForm.service === 'test_offer'" cols="12" sm="6" md="4"><ErpField v-model="createForm.backup_phone" :label="$t('erp.backupPhone')" /></VCol>
          <VCol cols="12"><ApplicantServiceFields v-model="serviceForm" /></VCol>
          <VCol cols="12"><ErpField kind="textarea" rows="2" v-model="createForm.notes" :label="$t('educational.notes')" /></VCol>
        </VRow>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn color="secondary" variant="tonal" @click="createDialog = false">{{ $t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" variant="flat" :loading="creating" :disabled="!createForm.name.trim()" @click="create">{{ $t('educational.createCase') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
/* Separates the open pipeline stages from the terminal (closing) stages. */
.stage-tabs-divider {
  block-size: 22px;
  margin-inline: 12px;
}

.applicant-name-link {
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
  text-decoration: none;
}

.applicant-name-link:hover {
  text-decoration: underline;
}
</style>
