<script setup lang="ts">
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import ErpField from '@/components/erp/ErpField.vue'
import DataTableFilterMenu from '@/components/responsive-data-table/DataTableFilterMenu.vue'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import ApplicantApi, { type Applicant, type BuiltInService } from '@/api/applicants'
import EmployeeApi, { type Employee } from '@/api/employee'
import ContactActionMenu from '@/components/ContactActionMenu.vue'
import CrmRelationshipDetails from '@/components/crm/CrmRelationshipDetails.vue'
import PrintableListReport from '@/components/PrintableListReport.vue'
import { printDocument } from '@/utils/printDocument'
import { canAccess } from '@/utils/authorization'

const { t } = useI18n()
const route = useRoute()
const toast = useToast()
// Deep link support: `/all-customers?contact=<applicantId>` (from the visa case
// or applications page) auto-opens that applicant's phone menu.
const deepLinkContactId = computed(() => (route.query.contact ? String(route.query.contact) : ''))
const applicants = ref<Applicant[]>([])
const admissionEmployees = ref<Employee[]>([])
const loading = ref(false)
const page = ref(1)
const perPage = ref(25)
const total = ref(0)
const dateFrom = ref('')
const dateTo = ref('')
const search = ref('')
const service = ref<BuiltInService | ''>('')
const dialog = ref(false)
const editing = ref<Applicant | null>(null)
const form = ref({ name: '', contact: '', email: '', address: '', tax_number: '', father_name: '', father_phone: '', admission_employee_id: null as number | null })
const canEdit = computed(() => canAccess('edit_applicants'))
const canDelete = computed(() => canAccess('delete_applicants'))
const serviceOptions = computed(() => [
  { title: t('educational.builtInServices.scholarship'), value: 'scholarship' },
  { title: t('educational.builtInServices.testOffer'), value: 'test_offer' },
  { title: t('educational.builtInServices.academy'), value: 'academy' },
  { title: t('educational.builtInServices.private'), value: 'private' },
])
const hasFilters = computed(() => Boolean(search.value || service.value || dateFrom.value || dateTo.value))
const headers = computed(() => [
  { title: t('common.fields.name'), key: 'name' },
  { title: t('common.messages.contact'), key: 'contact' },
  { title: t('common.fields.email'), key: 'email' },
  { title: t('common.messages.actions'), key: 'actions', sortable: false },
])
async function load() {
  loading.value = true
  try {
    // Applicants are the unified party/case record. There is no customer API.
    const result = await ApplicantApi.list({ page: page.value, per_page: perPage.value, service: service.value || undefined, date_from: dateFrom.value || undefined, date_to: dateTo.value || undefined, search: search.value || undefined })
    applicants.value = result.data
    total.value = result.total
  } catch { toast.error(t('educational.messages.loadApplicantsFailed')) } finally { loading.value = false }
}
function applyFilters() {
  page.value = 1
  void load()
}
function resetFilters() {
  search.value = ''
  service.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  applyFilters()
}
function updateOptions(options: { page: number; itemsPerPage: number }) {
  page.value = options.page
  perPage.value = options.itemsPerPage
  void load()
}
function openEdit(row: Applicant) {
  editing.value = row
  form.value = { name: row.name, contact: row.contact ?? '', email: row.email ?? '', address: row.address ?? '', tax_number: row.tax_number ?? '', father_name: row.father_name ?? '', father_phone: row.father_phone ?? '', admission_employee_id: row.admission_employee_id ?? null }
  dialog.value = true
}
async function save() {
  if (!form.value.name.trim()) return
  const payload = { ...form.value, name: form.value.name.trim() }
  try {
    if (editing.value) await ApplicantApi.update(editing.value.id, payload)
    dialog.value = false
    await load()
  } catch { toast.error(t('educational.messages.createCaseFailed')) }
}
async function remove(row: Applicant) {
  if (!confirm(t('common.messages.areYouSure'))) return
  try { await ApplicantApi.delete(row.id); await load() } catch { toast.error(t('common.messages.failedToDelete')) }
}
onMounted(async () => {
  await Promise.all([EmployeeApi.getAssignmentOptions('admission').then(rows => admissionEmployees.value = rows)])
  await load()
})
</script>

<template>
  <div class="erp-workspace">
  <ErpPageHeader :title="$t('common.navigation.applicants')" section="crm" />
  <VCard>
    <VCardTitle class="d-flex align-center ga-3">
      <VIcon icon="tabler-users" /> {{ $t('common.navigation.applicants') }}
    </VCardTitle>
    <VCardText class="d-flex align-center flex-wrap ga-2">
      <VTextField v-model="search" class="data-table-search" :label="$t('educational.searchApplicant')" prepend-inner-icon="tabler-search" clearable hide-details @keyup.enter="applyFilters" />
      <DataTableFilterMenu :active="hasFilters" @reset="resetFilters">
        <VSelect v-model="service" :items="serviceOptions" :label="$t('educational.service')" clearable />
        <ErpDateRange v-model:from="dateFrom" v-model:to="dateTo" @apply="applyFilters" />
      </DataTableFilterMenu>
      <VBtn icon="tabler-printer" variant="text" class="flex-shrink-0" @click="printDocument({ bodyClass: 'printing-table-crm-applicants', pageStyle: 'size: A4 landscape; margin: 12mm;' })"><VIcon icon="tabler-printer" /><VTooltip activator="parent">{{ t('common.actions.print') }}</VTooltip></VBtn>
    </VCardText>
    <ResponsiveDataTableServer :headers="headers" :items="applicants" :items-length="total" :loading="loading" :page="page" :items-per-page="perPage" :items-per-page-options="[10, 25, 50, 100]" item-value="id" @update:options="updateOptions">
      <template #item.name="{ item }"><span class="crm-applicant-name" :class="{ 'crm-applicant-name--focused': String(item.id) === deepLinkContactId }">{{ item.name }}</span></template>
      <template #item.contact="{ item }"><ContactActionMenu kind="phone" :value="item.contact" contactable-type="applicant" :contactable-id="item.id" audience="applicant" :context="item" :auto-open="String(item.id) === deepLinkContactId" /></template>
      <template #item.email="{ item }"><ContactActionMenu kind="email" :value="item.email" contactable-type="applicant" :contactable-id="item.id" /></template>
      <template #item.actions="{ item }"><CrmRelationshipDetails type="applicant" :id="item.id" :title="item.name" /><VBtn v-if="canEdit" icon="tabler-edit" variant="text" @click="openEdit(item)" /><VBtn v-if="canDelete" icon="tabler-trash" color="error" variant="text" @click="remove(item)" /></template>
    </ResponsiveDataTableServer>
  </VCard>
  <PrintableListReport :title="t('common.navigation.applicants')" :subtitle="t('agencyStaff.currentPageReport')" :columns="headers.slice(0, -1).map(column => column.title)" :rows="applicants.map(row => [row.name, row.contact, row.email])" />
  <VDialog v-model="dialog" max-width="900" scrollable class="erp-form">
    <VCard :title="editing ? $t('common.actions.edit') : $t('common.actions.add')">
      <VCardText>
        <VRow>
          <VCol cols="12" md="4"><ErpField v-model="form.name" required :label="$t('common.fields.name')" /></VCol>
          <VCol cols="12" md="4"><ErpField v-model="form.contact" :label="$t('common.messages.contact')" /></VCol>
          <VCol cols="12" md="4"><ErpField v-model="form.email" :label="$t('common.fields.email')" /></VCol>
          <VCol cols="12" md="6"><ErpField v-model="form.father_name" :label="$t('serviceContracts.fatherName')" /></VCol>
          <VCol cols="12" md="6"><ErpField v-model="form.father_phone" :label="$t('serviceContracts.fatherPhone')" /></VCol>
          <VCol cols="12" md="6"><ErpField v-model="form.tax_number" :label="$t('common.fields.taxNumber')" /></VCol>
          <VCol cols="12" md="6"><ErpField kind="select" v-model="form.admission_employee_id" :items="admissionEmployees.map(row => ({ title: row.name, value: row.id }))" :label="$t('agencyStaff.admission')" clearable /></VCol>
          <VCol cols="12"><ErpField kind="textarea" rows="2" v-model="form.address" :label="$t('common.fields.address')" /></VCol>
        </VRow>
      </VCardText>
      <VCardActions><VSpacer /><VBtn color="secondary" variant="tonal" @click="dialog = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="primary" :disabled="!form.name.trim()" @click="save">{{ $t('common.actions.save') }}</VBtn></VCardActions>
    </VCard>
  </VDialog>
  </div>
</template>

<style scoped>
.crm-applicant-name {
  border-radius: 6px;
  font-weight: 600;
  padding-block: 2px;
  padding-inline: 4px;
}

.crm-applicant-name--focused {
  background-color: rgba(var(--v-theme-primary), 0.12);
  outline: 2px solid rgba(var(--v-theme-primary), 0.4);
}
</style>
