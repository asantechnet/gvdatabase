<script setup lang="ts">
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import ApplicantApi, { type Applicant, type BuiltInService } from '@/api/applicants'
import ContactActionMenu from '@/components/ContactActionMenu.vue'
import CrmRelationshipDetails from '@/components/crm/CrmRelationshipDetails.vue'
import PrintableListReport from '@/components/PrintableListReport.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'
import { printDocument } from '@/utils/printDocument'
import { canAccess } from '@/utils/authorization'
import { confirmActionWarning } from '@/composables/useActionWarning'
import { useAcademyEnabled } from '@/composables/useAcademyEnabled'

const { t } = useI18n()
const { enabled: academyEnabled } = useAcademyEnabled()
const route = useRoute()
const toast = useToast()
// Deep link support: `/all-customers?contact=<applicantId>` (from the visa case
// or applications page) auto-opens that applicant's phone menu.
const deepLinkContactId = computed(() => (route.query.contact ? String(route.query.contact) : ''))
const applicants = ref<Applicant[]>([])
const loading = ref(true)
const filterMenu = ref(false)
const page = ref(1)
const perPage = ref(25)
const total = ref(0)
const dateFrom = ref('')
const dateTo = ref('')
const search = ref('')
const service = ref<BuiltInService | ''>('')
const dialog = ref(false)
const editing = ref<Applicant | null>(null)
const form = ref({ name: '', contact: '', email: '', address: '', father_name: '', father_phone: '' })
const canEdit = computed(() => canAccess('edit_applicants'))
const canDelete = computed(() => canAccess('delete_applicants'))
const serviceOptions = computed(() => [
  { title: t('educational.builtInServices.scholarship'), value: 'scholarship' },
  { title: t('educational.builtInServices.testOffer'), value: 'test_offer' },
  ...(academyEnabled.value ? [{ title: t('educational.builtInServices.academy'), value: 'academy' }] : []),
  { title: t('educational.builtInServices.private'), value: 'private' },
])
const hasFilters = computed(() => Boolean(search.value || service.value || dateFrom.value || dateTo.value))
const headers = computed(() => [
  { title: t('common.fields.name'), key: 'name' },
  { title: t('common.messages.contact'), key: 'contact' },
  { title: t('common.fields.email'), key: 'email' },
  { title: t('common.navigation.services'), key: 'services' },
  { title: t('common.messages.actions'), key: 'actions', sortable: false, align: 'end' },
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
  filterMenu.value = false
  applyFilters()
}
const dockFilterCalendar = (_selectedDates: Date[], _dateString: string, instance: { calendarContainer: HTMLElement }) => {
  requestAnimationFrame(() => {
    instance.calendarContainer.style.setProperty('transform', 'translateY(0) scale(0.86)', 'important')
    instance.calendarContainer.style.setProperty('transform-origin', 'top left', 'important')
    instance.calendarContainer.style.setProperty('z-index', '2400', 'important')
  })
}
function updateOptions(options: { page: number; itemsPerPage: number }) {
  page.value = options.page
  perPage.value = options.itemsPerPage
  void load()
}
function openEdit(row: Applicant) {
  editing.value = row
  form.value = { name: row.name, contact: row.contact ?? '', email: row.email ?? '', address: row.address ?? '', father_name: row.father_name ?? '', father_phone: row.father_phone ?? '' }
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
  if (!await confirmActionWarning({ key: `delete-customer-${row.id}`, title: t('educational.caseUi.deleteApplicantTitle'), message: t('educational.caseUi.deleteApplicantConfirm', { name: row.name }), confirmText: t('common.actions.delete'), cancelText: t('common.actions.cancel'), icon: 'tabler-trash', tone: 'error' })) return
  try { await ApplicantApi.delete(row.id); await load() } catch { toast.error(t('common.messages.failedToDelete')) }
}
onMounted(load)
</script>

<template>
  <div class="erp-workspace">
  <ErpPageHeader :title="$t('common.navigation.applicants')" section="applications" />
  <VCard>
    <VCardText class="crm-applicants-toolbar">
      <ErpSearchField v-model="search" :placeholder="$t('educational.searchApplicant')" @keyup.enter="applyFilters" />
      <div class="crm-applicants-toolbar__actions">
<VMenu v-model="filterMenu" location="bottom start" origin="top start" :close-on-content-click="false"><template #activator="{ props }"><VBtn v-bind="props" icon="tabler-filter" size="small" :variant="hasFilters ? 'flat' : 'tonal'" :color="hasFilters ? 'primary' : undefined" :aria-label="$t('common.messages.filters')"><VIcon icon="tabler-filter" /><VTooltip activator="parent">{{ t('common.messages.filters') }}</VTooltip></VBtn></template><VCard class="crm-customers-filter-card"><VCardText class="pa-4"><VRow><VCol cols="12"><ErpField v-model="service" kind="select" :items="serviceOptions" :label="$t('educational.service')" :placeholder="$t('educational.service')" clearable /></VCol><VCol cols="12" sm="6"><div class="erp-field"><label class="erp-field-label">{{ $t('common.fields.startDate') }}</label><AppDateTimePicker v-model="dateFrom" class="erp-field-control" :placeholder="$t('common.fields.startDate')" persistent-placeholder :config="{ dateFormat: 'Y-m-d', static: false, onOpen: dockFilterCalendar }" /></div></VCol><VCol cols="12" sm="6"><div class="erp-field"><label class="erp-field-label">{{ $t('common.fields.endDate') }}</label><AppDateTimePicker v-model="dateTo" class="erp-field-control" :placeholder="$t('common.fields.endDate')" persistent-placeholder :config="{ dateFormat: 'Y-m-d', static: false, onOpen: dockFilterCalendar }" /></div></VCol></VRow></VCardText><VCardActions class="px-4 py-3 d-flex justify-end ga-2"><VBtn color="primary" variant="tonal" @click="resetFilters">{{ $t('common.messages.reset') }}</VBtn><VBtn color="primary" variant="flat" @click="filterMenu = false; applyFilters()">{{ $t('educational.apply') }}</VBtn></VCardActions></VCard></VMenu>
        <VBtn icon="tabler-printer" variant="tonal" size="small" class="flex-shrink-0" :loading="loading" @click="printDocument({ bodyClass: 'printing-table-crm-applicants', pageStyle: 'size: A4 landscape; margin: 12mm;' })"><VIcon icon="tabler-printer" /><VTooltip activator="parent">{{ t('common.actions.print') }}</VTooltip></VBtn>
      </div>
    </VCardText>
    <ResponsiveDataTableServer :headers="headers" :items="applicants" :items-length="total" :loading="loading" :page="page" :items-per-page="perPage" :items-per-page-options="[10, 25, 50, 100]" item-value="id" class="crm-responsive-table" mobile-cards mobile-expand-all mobile-actions-as-menu mobile-title-key="name" mobile-action-key="actions" :sticky-key-column="false" :sticky-action-column="false" :skeleton-rows="5" :show-scroll-affordance="false" pagination @update:options="updateOptions">
      <template #loading>
        <TableLoadingSkeleton :columns="headers.length" :label="$t('common.messages.loading')" />
      </template>
      <template #no-data><TableEmptyState icon="tabler-users" :title="$t('pages.contracts.visitor.visitor.noApplicants')" :description="$t('educational.noApplicants')" /></template>
      <template #item.name="{ item }"><span class="crm-applicant-name" :class="{ 'crm-applicant-name--focused': String(item.id) === deepLinkContactId }">{{ item.name }}</span></template>
      <template #item.contact="{ item }"><ContactActionMenu kind="phone" :value="item.contact" contactable-type="applicant" :contactable-id="item.id" audience="applicant" :context="item" :auto-open="String(item.id) === deepLinkContactId" /></template>
      <template #item.email="{ item }"><ContactActionMenu kind="email" :value="item.email" contactable-type="applicant" :contactable-id="item.id" /></template>
      <template #item.services="{ item }"><div class="d-flex flex-wrap ga-1"><VChip v-for="line in item.attached_services ?? []" :key="line.id" size="x-small" variant="tonal">{{ line.service_label }}<span v-if="line.contract_number"> · {{ line.contract_number }}</span></VChip><span v-if="!item.attached_services?.length" class="text-medium-emphasis">—</span></div></template>
      <template #item.actions="{ item }"><div class="d-flex justify-end" @click.stop><VMenu location="bottom end"><template #activator="{ props }"><VBtn v-bind="props" icon="tabler-dots-vertical" variant="tonal" size="small" :aria-label="$t('common.messages.actions')" /></template><VList density="compact" min-width="210"><CrmRelationshipDetails type="applicant" :id="item.id" :title="item.name" menu-action /><VListItem v-if="canEdit" prepend-icon="tabler-edit" :title="$t('common.actions.edit')" @click="openEdit(item)" /><VListItem v-if="canDelete" prepend-icon="tabler-trash" base-color="error" :title="$t('common.actions.delete')" @click="remove(item)" /></VList></VMenu></div></template>
    </ResponsiveDataTableServer>
  </VCard>
  <PrintableListReport :title="t('common.navigation.applicants')" :subtitle="t('agencyStaff.currentPageReport')" :columns="headers.slice(0, -1).map(column => column.title)" :rows="applicants.map(row => [row.name, row.contact, row.email, (row.attached_services ?? []).map(line => line.service_label).join(', ') || '—'])" />
  <VDialog v-model="dialog" max-width="900" content-class="crm-customer-form-dialog" scrollable>
    <VCard class="crm-customer-form-card">
      <VForm class="app-form" @submit.prevent="save">
      <VCardTitle class="crm-customer-form-dialog__title px-5 py-4"><span>{{ editing ? $t('common.actions.edit') : $t('common.actions.add') }}</span><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="crm-customer-form-dialog__close" :aria-label="$t('common.actions.close')" @click="dialog = false" /></VCardTitle>
      <VCardText class="crm-customer-form-dialog__body pa-5">
        <VRow>
          <VCol cols="12" md="4"><ErpField v-model="form.name" required :label="$t('common.fields.name')" :placeholder="$t('common.fields.name')" /></VCol>
          <VCol cols="12" md="4"><ErpField v-model="form.contact" :label="$t('common.messages.contact')" :placeholder="$t('common.messages.contact')" /></VCol>
          <VCol cols="12" md="4"><ErpField v-model="form.email" type="email" :label="$t('common.fields.email')" :placeholder="$t('common.fields.email')" /></VCol>
          <VCol cols="6"><ErpField v-model="form.father_name" :label="$t('serviceContracts.fatherName')" :placeholder="$t('serviceContracts.fatherName')" /></VCol>
          <VCol cols="6"><ErpField v-model="form.father_phone" :label="$t('serviceContracts.fatherPhone')" :placeholder="$t('serviceContracts.fatherPhone')" /></VCol>
          <VCol cols="12"><ErpField kind="textarea" rows="2" v-model="form.address" :label="$t('common.fields.address')" :placeholder="$t('common.fields.address')" /></VCol>
        </VRow>
      </VCardText>
      <VCardActions class="crm-customer-form-dialog__actions px-5 py-4"><VSpacer /><VBtn class="crm-customer-form-dialog__cancel" color="error" variant="flat" @click="dialog = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn class="crm-customer-form-dialog__save" color="primary" variant="flat" type="submit" :disabled="!form.name.trim()">{{ $t('common.actions.save') }}</VBtn></VCardActions>
      </VForm>
    </VCard>
  </VDialog>
  </div>
</template>

<style scoped>
.crm-applicants-toolbar,
.crm-applicants-toolbar__actions {
  align-items: center;
  display: flex;
  gap: 0.5rem;
}

.crm-applicants-toolbar {
  flex-wrap: wrap;
  justify-content: space-between;
}

.crm-applicants-toolbar > :deep(.erp-search-field) {
  flex: 0 1 420px;
  inline-size: 420px;
  max-inline-size: 100%;
}


.crm-applicants-toolbar__actions {
  flex: 0 0 auto;
  justify-content: flex-end;
}

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

@media (max-width: 599px) {
  .crm-applicants-toolbar__actions {
    inline-size: 100%;
    justify-content: space-between;
  }

  .crm-applicants-toolbar > :deep(.erp-search-field) {
    flex-basis: 100%;
    inline-size: 100%;
  }
}
</style>

<style lang="scss">
.crm-customers-filter-card { border: 1px solid rgb(var(--v-border-color) / .42); border-radius: 12px; inline-size: min(28rem, calc(100vw - 24px)); overflow: hidden; }.crm-customers-filter-card .v-card-actions { background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%); border-block-start: 1px solid rgb(var(--v-border-color) / .3); }
.crm-customer-form-card { overflow: hidden; }.crm-customer-form-dialog__title, .crm-customer-form-dialog__actions { display: flex; align-items: center; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%); border-color: rgba(var(--v-theme-on-surface), .06); border-style: solid; border-width: 0; }.crm-customer-form-dialog__title { border-block-end-width: 1px; font-size: 1.35rem; font-weight: 700; }.crm-customer-form-dialog__actions { border-block-start-width: 1px; }.crm-customer-form-dialog__body { background: rgb(var(--v-theme-surface)); }.crm-customer-form-dialog__close { border: 1px solid rgba(var(--v-theme-error), .34) !important; border-radius: 50% !important; }.crm-customer-form-dialog__cancel { border: 1px solid rgba(var(--v-theme-error), .62) !important; background: rgba(var(--v-theme-error), .12) !important; color: rgb(var(--v-theme-error)) !important; }.crm-customer-form-dialog__cancel:hover { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }.crm-customer-form-dialog__save { color: rgb(var(--v-theme-on-primary)) !important; }
</style>
