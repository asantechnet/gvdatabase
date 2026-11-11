<script setup lang="ts">
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import ContractApi from '@/api/contracts'
import type { Contract, ContractParams } from '@/api/contracts'
import ContractForm, { type ContractFormPayload } from '@/components/ContractForm.vue'
import ContractDocumentPrinter from '@/components/contract/ContractDocumentPrinter.vue'
import FullDetailsButton from '@/components/FullDetailsButton.vue'
import PrintableListReport from '@/components/PrintableListReport.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'
import EmployeeAvatar from '@/components/hr/EmployeeAvatar.vue'
import { useContracts } from '@/composables/useContracts'
import { apiErrorMessage } from '@/utils/apiErrors'
import { printDocument } from '@/utils/printDocument'

const props = withDefaults(defineProps<{
  contractAudience?: 'employees' | 'other'
}>(), {
  contractAudience: 'other',
})

const route = useRoute()
const router = useRouter()
const { t, n } = useI18n()
const ability = useAbility()

const currentListRoute = () => ({
  employees: 'employee-contracts',
  other: 'other-contracts',
}[props.contractAudience])

const {
  contracts,
  templates,
  isLoading,
  error,
  listContracts,
  fetchTemplates,
  addContract,
  editContract,
  removeContract,
} = useContracts()

// The list asks the server for one page at a time.
const page = ref(1)
const perPage = ref(25)
const total = ref(0)
const sortByKey = ref<'contract_number' | 'counterparty' | 'start_date' | 'status' | 'created_at'>('start_date')
// Date-based registers lead with the most recently issued agreement.
const sortDirection = ref<'asc' | 'desc'>('desc')
// Filled from every matching contract right before printing.
const reportRows = ref<string[][]>([])
const counterparties = ref<string[]>([])

const search = ref('')
const filterCounterparty = ref<string | null>(null)
const dateFrom = ref('')
const dateTo = ref('')
const filterStatus = ref<string | null>(null)
// The dashboard's contracts-requiring-attention tile deep-links here with
// ?expiring=1, so the filter is a visible, clearable state.
const expiringOnly = ref(route.query.expiring === '1')
const isDialogVisible = ref(false)
const dialogMode = ref<'add' | 'edit'>('add')
const selectedContract = ref<Contract | null>(null)
const detailContract = ref<Contract | null>(null)
const detailsOpen = ref(false)
// Set only while the form creates an extension of an existing agreement.
const extensionParent = ref<Contract | null>(null)

const isPrintDialogVisible = ref(false)
const contractToPrint = ref<Contract | null>(null)
const isCancelling = ref(false)
const toast = useToast()
const isContractActionDialogVisible = ref(false)
const pendingContractAction = ref<{ type: 'delete' | 'cancel'; contract: Contract } | null>(null)
const showDetails = (contract: Contract) => {
  detailContract.value = contract
  // Avoid the actions menu's click also dismissing the dialog as an outside click.
  window.setTimeout(() => { detailsOpen.value = true })
}
// Other contracts are free-form templates the office completes by hand, so they
// carry no counterparty at all: no input, no column, no filter and no print
// party block. Only employment contracts are drawn for a named counterparty.
const isOtherAudience = computed(() => props.contractAudience === 'other')
const isEmployeeAudience = computed(() => props.contractAudience === 'employees')
// Payment state is a case-finance field: without the permission the column is
// omitted instead of rendered as a dash on every row.
const canViewFinancials = computed(() => ability.can('read', 'view_applicant_financials'))
const contractRequest = computed(() => ({ audience: props.contractAudience }))
const pageTitle = computed(() => t(audienceCopy.value.title))
const pageDescription = computed(() => t(audienceCopy.value.description))

const statuses = ['draft', 'active', 'completed', 'terminated', 'cancelled']
const audienceCopy = computed(() => ({
  employees: {
    title: 'pages.contracts.allContracts.allContracts.employeeContracts',
    description: 'pages.contracts.allContracts.allContracts.employeeContractsDescription',
    report: 'pages.contracts.allContracts.allContracts.employeeContractsReport',
  },
  other: {
    title: 'pages.contracts.allContracts.allContracts.otherContracts',
    description: 'pages.contracts.allContracts.allContracts.otherContractsDescription',
    report: 'pages.contracts.allContracts.allContracts.otherContractsReport',
  },
}[props.contractAudience]))

const getStatusColor = (status: string) => {
  const statusMap: Record<string, string> = {
    active: 'success',
    draft: 'info',
    pending: 'warning',
    completed: 'primary',
    terminated: 'secondary',
    cancelled: 'error',
  }

  return statusMap[status?.toLowerCase()] || 'default'
}

const listParams = (): ContractParams => ({
  ...contractRequest.value,
  search: search.value || undefined,
  counterparty: isOtherAudience.value ? undefined : filterCounterparty.value || undefined,
  status: (filterStatus.value || undefined) as ContractParams['status'],
  expiring: expiringOnly.value || undefined,
  date_from: dateFrom.value || undefined,
  date_to: dateTo.value || undefined,
  sort_by: sortByKey.value,
  sort_direction: sortDirection.value,
})

// Loads the visible page; `reload` additionally returns to the first page,
// which is what a filter change needs.
async function load() {
  try {
    const result = await listContracts({ ...listParams(), page: page.value, perPage: perPage.value })

    total.value = result.total
  }
  catch {
    // The composable already exposes the error for the inline alert.
  }
}

function reload() {
  page.value = 1
  void load()
}

function updateTableOptions(options: { page: number; itemsPerPage: number; sortBy?: Array<{ key: string; order?: 'asc' | 'desc' }> }) {
  const sort = options.sortBy?.[0]
  const sortableKeys = ['contract_number', 'counterparty', 'start_date', 'status', 'created_at'] as const
  const nextSortBy = sortableKeys.includes(sort?.key as typeof sortableKeys[number])
    ? sort?.key as typeof sortableKeys[number]
    : 'start_date'
  const nextSortDirection = sort?.order === 'desc' ? 'desc' : 'asc'
  if (page.value === options.page && perPage.value === options.itemsPerPage
    && sortByKey.value === nextSortBy && sortDirection.value === nextSortDirection)
    return

  page.value = options.page
  perPage.value = options.itemsPerPage
  sortByKey.value = nextSortBy
  sortDirection.value = nextSortDirection
  void load()
}

// Typing in the search box is a filter change, so it starts from the first page.
const debouncedSearch = useDebounceFn(reload, 300)

async function loadCounterparties() {
  try {
    counterparties.value = await ContractApi.counterparties()
  }
  catch (error) {
    console.error('Failed to fetch counterparties:', error)
  }
}

const headers = computed(() => {
  const rows: Array<{ title: string; key: string; sortable: boolean }> = [
    { title: t('common.messages.contractNumber'), key: 'contract_number', sortable: true },
    ...(isEmployeeAudience.value
      ? [{ title: t('common.fields.position'), key: 'position', sortable: false }]
      : [{ title: t('common.messages.contract'), key: 'contract', sortable: true }]),
  ]

  if (!isEmployeeAudience.value)
    rows.push({ title: t('agencyStaff.contractVersion'), key: 'version', sortable: true })

  if (!isOtherAudience.value)
    rows.push({ title: t('pages.contracts.allContracts.allContracts.counterparty'), key: 'counterparty', sortable: true })

  rows.push(
    { title: t('common.fields.startDate'), key: 'start_date', sortable: true },
    { title: t('serviceContracts.endDate'), key: 'end_date', sortable: true },
  )

  if (canViewFinancials.value && !isEmployeeAudience.value)
    rows.push({ title: t('pages.contracts.allContracts.allContracts.paymentStatus'), key: 'payment', sortable: false })

  rows.push(
    { title: t('common.fields.status'), key: 'status', sortable: true },
    { title: t('common.messages.actions'), key: 'actions', sortable: false },
  )

  return rows
})

const contractReportRow = (contract: Contract): string[] => [
  contract.contract_number ?? '—',
  isEmployeeAudience.value
    ? String(contract.employee?.position ?? '—')
    : contract.contract || contract.contract_template?.name || '—',
  ...(isEmployeeAudience.value ? [] : [contract.snapshot_data?.template_version?.name || '—']),
  ...(isOtherAudience.value ? [] : [contract.counterparty || '—']),
  contract.start_date ?? '—',
  contract.end_date ?? '—',
  ...(canViewFinancials.value && !isEmployeeAudience.value ? [paymentLabel(contract)] : []),
  contract.display_status ?? contract.status,
]

// Expiry comes from the server so the page never re-implements date arithmetic.
const expiryLabel = (contract: Contract) => {
  if (contract.expiry_state === 'expired')
    return t('pages.contracts.allContracts.allContracts.daysExpired', { count: Math.abs(contract.days_remaining ?? 0) })

  if (contract.expiry_state === 'expiring')
    return t('pages.contracts.allContracts.allContracts.daysRemaining', { count: contract.days_remaining ?? 0 })

  return ''
}

// A row-level tint makes an expiring/expired agreement distinguishable without
// relying on the badge alone.
const expiryRowProps = ({ item }: { item: Contract }) => {
  if (item.expiry_state === 'expired')
    return { style: 'background-color: rgba(var(--v-theme-error), 0.06)' }

  if (item.expiry_state === 'expiring')
    return { style: 'background-color: rgba(var(--v-theme-warning), 0.08)' }

  return {}
}

const outstandingLabel = (contract: Contract) => {
  const payment = contract.payment
  if (!payment?.applicable || payment.outstanding === null)
    return ''

  const currency = payment.currency?.symbol || payment.currency?.code || ''

  return `${n(Number(payment.outstanding))} ${currency}`.trim()
}

const paymentLabel = (contract: Contract) => {
  if (!contract.payment?.applicable)
    return t('pages.contracts.allContracts.allContracts.notApplicable')

  const status = contract.payment.status ? t(`finance.paymentStatus.${contract.payment.status}`) : ''

  return [status, outstandingLabel(contract)].filter(Boolean).join(' · ')
}

const paymentColor = (status?: string | null) => status === 'paid' ? 'success' : status === 'partially_paid' ? 'warning' : 'error'

function clearExpiring() {
  expiringOnly.value = false
  const { expiring: _expiring, ...query } = route.query
  void router.replace({ query })
  reload()
}

const contractDetailFields = computed(() => [
  { key: 'contract_number', label: t('common.messages.contractNumber') },
  { key: 'contract', label: t('common.messages.contract') },
  { key: 'snapshot_data.template_version.name', label: t('agencyStaff.contractVersion') },
  ...(canViewFinancials.value
    ? [
        { key: 'payment.status', label: t('pages.contracts.allContracts.allContracts.paymentStatus') },
        { key: 'payment.outstanding', label: t('pages.contracts.allContracts.allContracts.outstandingAmount') },
        { key: 'payment.currency.code', label: t('serviceContracts.currency') },
      ]
    : []),
  { key: 'type', label: t('educational.contractType') },
  { key: 'status', label: t('common.fields.status') },
  { key: 'start_date', label: t('serviceContracts.startDate') },
  { key: 'end_date', label: t('serviceContracts.endDate') },
  { key: 'counterparty', label: t('common.fields.employee') },
  { key: 'employee.father_name', label: t('serviceContracts.fatherName') },
  { key: 'employee.position', label: t('common.fields.position') },
  { key: 'employee.department_name', label: t('common.fields.department') },
  { key: 'employee.office.name', label: t('common.fields.office') },
  { key: 'counterparty_contact', label: t('serviceContracts.contact') },
  { key: 'counterparty_email', label: t('serviceContracts.email') },
  { key: 'counterparty_address', label: t('serviceContracts.address') },
  { key: 'company_signatory_name', label: t('serviceContracts.companySignatory') },
  { key: 'content', label: t('common.messages.contract') },
])

// Print is global: the report walks the filtered pages so it covers the whole
// result set instead of the 25 rows on screen.
const printTable = async () => {
  try {
    const collected: Contract[] = []
    let nextPage = 1
    let lastPage = 1

    do {
      const result = await listContracts({ ...listParams(), page: nextPage, perPage: 100 })

      collected.push(...result.rows)
      lastPage = result.lastPage
      nextPage += 1
    } while (nextPage <= lastPage)

    reportRows.value = collected.map(contractReportRow)
    await printDocument({ bodyClass: 'printing-table-contracts', pageStyle: 'size: A4 landscape; margin: 12mm;' })

    // Leave the table showing the page the operator was on.
    await load()
  }
  catch (error) {
    console.error('Failed to prepare the contract report:', error)
    toast.error(translate('pages.contracts.allContracts.allContracts.failedToFetchContractsPleaseTryAgain'))
  }
}

const printContract = (contract: Contract) => {
  contractToPrint.value = contract
  isPrintDialogVisible.value = true
}

const addNew = () => {
  dialogMode.value = 'add'
  extensionParent.value = null

  selectedContract.value = {
    id: 0,
    contract_number: '',
    contract: '',
    status: 'active',
    start_date: new Date().toISOString().slice(0, 10),
    counterparty: '',
    is_cancelled: false,
    template_id: templates.value[0]?.id || 0,
  }
  isDialogVisible.value = true
}

const editContractUI = (contract: Contract) => {
  dialogMode.value = 'edit'
  // An earlier extend action must not leak into this edit: `extensionParent`
  // keeps the dialog and form in extend mode.
  extensionParent.value = null
  selectedContract.value = { ...contract }
  isDialogVisible.value = true
}

const contractName = (contract: Contract) => contract.contract || contract.contract_template?.name || translate('pages.contracts.allContracts.allContracts.unnamedContract')

// An agreement is extended from a live primary contract: cancelled, finished
// and already-extended records never spawn another extension.
const canExtend = (contract: Contract) => ability.can('read', 'create_contracts')
  && !contract.is_cancelled
  && contract.contract_role !== 'extension'
  && !contract.has_live_extension
  && ['active', 'completed'].includes(contract.status)

const extendContract = (contract: Contract) => {
  dialogMode.value = 'add'
  selectedContract.value = null
  extensionParent.value = contract
  isDialogVisible.value = true
}

const requestContractAction = (type: 'delete' | 'cancel', contract: Contract) => {
  pendingContractAction.value = { type, contract }
  isContractActionDialogVisible.value = true
}

const confirmContractAction = async () => {
  const action = pendingContractAction.value
  if (!action)
    return

  isContractActionDialogVisible.value = false
  try {
    if (action.type === 'delete') {
      await removeContract(action.contract.id)
      // Step back when the last row on a later page was removed, then reload
      // so the footer total matches the stored records.
      if (contracts.value.length === 0 && page.value > 1)
        page.value -= 1
      await load()
      toast.success(translate('pages.contracts.allContracts.allContracts.contractDeleted'))
    }
    else {
      isCancelling.value = true
      await ContractApi.cancel(action.contract.id)
      await load()
      toast.success(translate('pages.contracts.allContracts.allContracts.contractCancelled'))
    }
  }
  catch (err) {
    console.error(`Failed to ${action.type} contract:`, err)
    toast.error(translate(action.type === 'delete'
      ? 'pages.contracts.allContracts.allContracts.failedToDeleteContractPleaseTryAgain'
      : 'pages.contracts.allContracts.allContracts.failedToCancelContractPleaseTryAgain'))
  }
  finally {
    isCancelling.value = false
    pendingContractAction.value = null
  }
}

const cancelContract = (contract: Contract) => {
  requestContractAction('cancel', contract)
}

const deleteContractUI = (contract: Contract) => {
  requestContractAction('delete', contract)
}

const handleFormSubmit = async (contractData: ContractFormPayload) => {
  try {
    if (dialogMode.value === 'add') {
      const parent = extensionParent.value
      await addContract({
        ...contractData,
        // An extension is linked to its parent and always issued as a draft.
        parent_contract_id: parent?.id ?? null,
        ...(parent ? { status: 'draft' as const } : {}),
      })
      toast.success(translate(parent
        ? 'pages.contracts.allContracts.allContracts.extendedDraftCreated'
        : 'pages.contracts.allContracts.allContracts.contractSaved'))
    }
    else if (selectedContract.value) {
      await editContract(selectedContract.value.id, contractData)
      toast.success(translate('pages.contracts.allContracts.allContracts.contractSaved'))
    }
    isDialogVisible.value = false
    // Positions, numbering and totals come from the server: refresh the page
    // instead of leaving a locally inserted row under a stale footer count.
    if (dialogMode.value === 'add')
      page.value = 1
    await load()
  }
  catch (err) {
    // Preserve the server's specific business code (duplicate renewal,
    // template/party validation) instead of replacing it with a generic toast.
    toast.error(apiErrorMessage(err, translate('pages.contracts.allContracts.allContracts.failedToSaveContractPleaseTryAgain')))
  }
}

onMounted(async () => {
  await Promise.all([
    load(),
    fetchTemplates(),
    ...(isOtherAudience.value ? [] : [loadCounterparties()]),
  ])
  templates.value = templates.value.filter(template =>
    props.contractAudience === 'employees'
      ? template.type_slug === 'employment'
      : template.type_slug !== 'employment' && template.type_slug !== 'customer',
  )

  const employeeId = Number(route.query.employeeId)
  if (props.contractAudience === 'employees' && route.query.print === '1' && employeeId) {
    // The employee's active contract may live on another page, so the lookup
    // falls back to the unpaged audience list.
    const listed = contracts.value.find(item => item.employee_id === employeeId && !item.is_cancelled)
      ?? (await ContractApi.getAll({ audience: 'employees' })).find(item => item.employee_id === employeeId && !item.is_cancelled)
    const contract = listed
    if (contract)
      await printContract(contract)
    else toast.warning(translate('pages.contracts.allContracts.allContracts.noActiveEmploymentContractFound'))
  }
  if (route.query.action === 'create') {
    addNew()
    await router.replace({ name: currentListRoute() })
  }
  const contractId = Number(route.query.contractId)
  if (route.query.action === 'print' && contractId) {
    const contract = contracts.value.find(item => item.id === contractId) ?? await ContractApi.getById(contractId)

    // Cancelled contracts keep a view-only history, so printing them — even
    // through a deep link — is refused.
    if (contract.is_cancelled)
      toast.warning(translate('pages.contracts.allContracts.allContracts.cancelledContractPrintBlocked'))
    else
      await printContract(contract)
    await router.replace({ name: currentListRoute() })
  }
  else if (contractId) {
    // Deep links (for example an expiry notification) name a contract that may
    // not be on the first page, so resolve it before applying the search.
    const contract = contracts.value.find(item => item.id === contractId)
      ?? await ContractApi.getById(contractId).catch(() => null)
    if (contract) {
      search.value = contract.contract_number || contract.counterparty || contract.contract
      await load()
    }
  }
})

</script>

<template>
  <div class="erp-workspace">
  <ErpPageHeader :title="pageTitle" :section="isEmployeeAudience ? 'hr' : 'contracts'" />
  <VCard>
    <VCardText>
      <VRow
        align="center"
        class="table-toolbar"
      >
        <VCol cols="12" md="6" lg="5">
          <ErpSearchField
            v-model="search"
            :placeholder="$t('pages.contracts.allContracts.allContracts.searchContracts')"
            @input="debouncedSearch"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
          lg="7"
          class="table-toolbar-actions d-flex flex-nowrap justify-end gap-2"
        >
          <DataTableFilterMenu
            :active="Boolean(search || filterCounterparty || filterStatus || expiringOnly || dateFrom || dateTo)"
            @reset="search = ''; filterCounterparty = null; filterStatus = null; expiringOnly = false; dateFrom = ''; dateTo = ''; reload()"
          >
            <ErpDateRange v-model:from="dateFrom" v-model:to="dateTo" :show-apply="false" />
            <ErpField
              v-if="!isOtherAudience"
              v-model="filterCounterparty"
              kind="select"
              :items="counterparties"
              :label="$t('pages.contracts.allContracts.allContracts.filterByCounterparty')"
              clearable
            />
            <ErpField
              v-model="filterStatus"
              kind="select"
              :items="statuses"
              :label="$t('common.messages.filterByStatus')"
              clearable
            />
            <template #actions>
              <VBtn variant="tonal" color="primary" @click="search = ''; filterCounterparty = null; filterStatus = null; expiringOnly = false; dateFrom = ''; dateTo = ''; reload()">{{ $t('common.messages.reset') }}</VBtn>
              <VBtn color="primary" variant="flat" data-filter-apply @click="reload">{{ $t('educational.apply') }}</VBtn>
            </template>
          </DataTableFilterMenu>
          <VBtn
            icon="tabler-printer"
            variant="tonal"
            class="flex-shrink-0"
            :aria-label="$t('common.actions.print')"
            @click="printTable"
          >
            <VIcon icon="tabler-printer" /><VTooltip
              activator="parent"
              location="top"
            >
              {{ $t('pages.contracts.allContracts.allContracts.printTable') }}
            </VTooltip>
          </VBtn>
          <VBtn
            color="primary"
            class="flex-shrink-0"
            @click="addNew"
          >
            <VIcon
              icon="tabler-plus"
              class="me-1"
            />
            {{ $t('pages.contracts.allContracts.allContracts.addContract') }}
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>

    <VAlert
      v-if="error"
      type="error"
      class="ma-4"
    >
      {{ error }}
    </VAlert>

    <div v-if="expiringOnly" class="d-flex flex-wrap align-center gap-2 px-4 pb-3">
      <VChip closable color="warning" variant="tonal" @click:close="clearExpiring">
        {{ $t('pages.contracts.allContracts.allContracts.expiringFilter') }}
      </VChip>
      <span class="text-caption text-medium-emphasis">{{ $t('pages.contracts.allContracts.allContracts.expiringFilterHint') }}</span>
    </div>

    <ResponsiveDataTableServer
      :headers="headers"
      :items="contracts"
      :row-props="expiryRowProps"
      :items-length="total"
      :page="page"
      :items-per-page="perPage"
      :sort-by="[{ key: sortByKey, order: sortDirection }]"
      :items-per-page-options="[10, 25, 50, 100]"
      item-value="id"
      class="contract-table text-no-wrap"
      mobile-breakpoint="sm"
      mobile-cards
      mobile-expand-all
      mobile-actions-as-menu
      mobile-title-key="contract_number"
      mobile-action-key="actions"
      :sticky-key-column="false"
      :sticky-action-column="false"
      :skeleton-rows="5"
      :show-scroll-affordance="false"
      :loading="isLoading"
      @update:options="updateTableOptions"
    >
      <template #loading><TableLoadingSkeleton :columns="headers.length" :label="$t('common.messages.loading')" /></template>
      <template #no-data>
        <div class="contract-empty-state">
          <TableEmptyState
            icon="tabler-file-off"
            :min-height="0"
            :title="isOtherAudience ? $t('pages.contracts.allContracts.allContracts.noOtherContractRecords') : $t('pages.contracts.allContracts.allContracts.employeeContracts')"
            :description="isOtherAudience ? $t('pages.contracts.allContracts.allContracts.noOtherContractRecordsDescription') : $t('pages.contracts.allContracts.allContracts.noContracts')"
          />
          <VBtn v-if="isOtherAudience" color="primary" variant="tonal" class="contract-empty-state__action" @click="addNew">
            <VIcon icon="tabler-plus" class="me-1" />{{ $t('pages.contracts.allContracts.allContracts.addContract') }}
          </VBtn>
        </div>
      </template>
      <template #item.version="{ item }">{{ item.snapshot_data?.template_version?.name ?? '—' }}<span v-if="item.snapshot_data?.template_version?.version_number"> · v{{ item.snapshot_data.template_version.version_number }}</span></template>
      <template #item.contract_number="{ item }">
        <VBtn variant="text" size="small" class="contract-number-link px-0 text-none" @click="showDetails(item)">{{ item.contract_number || '—' }}</VBtn>
        <span>{{ item.contract_number ?? '—' }}</span>
        <VChip
          v-if="item.contract_role === 'extension'"
          size="x-small"
          color="warning"
          variant="tonal"
          class="ms-2"
        >
          {{ $t('pages.contracts.allContracts.allContracts.extensionRole') }}
          <span v-if="item.parent_contract_number"> · {{ $t('pages.contracts.allContracts.allContracts.extensionOf', { number: item.parent_contract_number }) }}</span>
        </VChip>
      </template>
      <template #item.contract="{ item }">
        {{ item.contract || item.contract_template?.name || $t('pages.contracts.allContracts.allContracts.untitledContract') }}
        <VChip
          v-if="item.is_cancelled"
          color="error"
          size="x-small"
          class="ms-2"
        >
          {{ $t('common.statuses.cancelled') }}
        </VChip>
      </template>
      <template #item.position="{ item }">
        {{ item.employee?.position || '—' }}
      </template>
      <template
        v-if="!isOtherAudience"
        #item.counterparty="{ item }"
      >
        <div class="d-flex align-center ga-2">
          <EmployeeAvatar
            :name="String(item.employee?.name ?? item.counterparty ?? $t('pages.contracts.allContracts.allContracts.unknownCounterparty'))"
            :src="typeof item.employee?.avatar === 'string' ? item.employee.avatar : null"
            size="28"
          />
          <span>{{ (item.employee?.name ?? item.counterparty) || $t('pages.contracts.allContracts.allContracts.unknownCounterparty') }}</span>
        </div>
      </template>
      <template #item.start_date="{ item }">
        {{ formatLocalizedDate(item.start_date) }}
      </template>
      <template #item.end_date="{ item }">
        <span v-if="!item.end_date">—</span>
        <template v-else>
          <div>{{ formatLocalizedDate(item.end_date) }}</div>
          <div
            v-if="expiryLabel(item)"
            class="text-caption"
            :class="item.expiry_state === 'expired' ? 'text-error' : 'text-warning'"
          >
            {{ expiryLabel(item) }}
          </div>
        </template>
      </template>
      <template #item.payment="{ item }">
        <span v-if="!item.payment?.applicable" class="text-medium-emphasis">{{ $t('pages.contracts.allContracts.allContracts.notApplicable') }}</span>
        <template v-else>
          <VChip
            size="small"
            variant="tonal"
            :color="paymentColor(item.payment.status)"
          >
            {{ item.payment.status ? $t(`finance.paymentStatus.${item.payment.status}`) : '—' }}
          </VChip>
          <div v-if="outstandingLabel(item)" class="text-caption text-medium-emphasis">
            {{ $t('pages.contracts.allContracts.allContracts.outstandingAmount') }}: {{ outstandingLabel(item) }}
          </div>
        </template>
      </template>
      <template #item.status="{ item }">
        <VChip
          v-if="item.display_status === 'scheduled'"
          color="warning"
          size="small"
          class="font-weight-medium"
        >
          {{ $t('pages.contracts.allContracts.allContracts.scheduled') }}
        </VChip>
        <VChip
          v-else
          :color="getStatusColor(item.status)"
          size="small"
          class="font-weight-medium"
        >
          {{ translateBackendValue(item.status || 'unknown') }}
        </VChip>
        <VChip
          v-if="item.expiry_state === 'expired'"
          color="error"
          size="x-small"
          class="ms-2"
        >
          {{ $t('pages.contracts.allContracts.allContracts.expired') }}
        </VChip>
        <VChip
          v-else-if="item.expiry_state === 'expiring'"
          color="warning"
          size="x-small"
          class="ms-2"
        >
          {{ $t('pages.contracts.allContracts.allContracts.expiringSoon') }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <div class="d-flex align-center ga-1 justify-end">
          <VBtn v-if="!item.is_cancelled && !isEmployeeAudience" icon="tabler-printer" variant="text" size="small" :aria-label="$t('pages.contracts.allContracts.allContracts.printContract')" @click="printContract(item)" />
          <VMenu location="bottom end">
            <template #activator="{ props: menuProps }"><VBtn v-bind="menuProps" icon="tabler-dots-vertical" variant="tonal" size="small" class="contract-actions-trigger" :aria-label="$t('common.messages.actions')" /></template>
            <VList density="compact" min-width="220">
              <VListItem prepend-icon="tabler-eye" :title="t('common.actions.viewFullDetails')" @click="showDetails(item)" />
              <VListItem v-if="isEmployeeAudience && !item.is_cancelled" prepend-icon="tabler-printer" :title="$t('pages.contracts.allContracts.allContracts.printContract')" @click="printContract(item)" />
              <VListItem v-if="canExtend(item)" prepend-icon="tabler-file-plus" :title="$t('pages.contracts.allContracts.allContracts.extendContract')" @click="extendContract(item)" />
              <VListItem v-if="item.status === 'draft' && !item.is_cancelled" prepend-icon="tabler-edit" :title="$t('pages.contracts.allContracts.allContracts.editContract')" @click="editContractUI(item)" />
              <VListItem v-if="ability.can('read', 'cancel_contracts') && !item.is_cancelled && ['draft', 'active'].includes(item.status)" prepend-icon="tabler-ban" :title="$t('pages.contracts.allContracts.allContracts.cancelContract')" @click="cancelContract(item)" />
              <VListItem v-if="item.status === 'draft' && !item.is_cancelled" prepend-icon="tabler-trash" class="contract-actions-menu__delete" :title="$t('pages.contracts.allContracts.allContracts.deleteContract')" @click="deleteContractUI(item)" />
            </VList>
          </VMenu>
        </div>
      </template>
    </ResponsiveDataTableServer>
  </VCard>
  <FullDetailsButton v-if="detailContract" v-model="detailsOpen" :item="detailContract" :title="detailContract.contract_number || detailContract.contract" :fields="contractDetailFields" hide-activator />
  <PrintableListReport
    :title="t(audienceCopy.report)"
    :subtitle="$t('pages.contracts.allContracts.allContracts.filteredContractRecords')"
    :columns="headers.slice(0, -1).map(header => header.title)"
    :rows="reportRows"
  />

  <!-- Contract Form Dialog -->
  <VDialog
    v-model="isDialogVisible" class="erp-form"
    :max-width="isEmployeeAudience ? '900px' : '900px'"
    :content-class="(isOtherAudience || isEmployeeAudience) ? 'other-contract-form-dialog' : undefined"
    scrollable
  >
    <VCard :class="(isOtherAudience || isEmployeeAudience) && 'other-contract-form-card'">
      <VCardTitle :class="(isOtherAudience || isEmployeeAudience) && 'other-contract-dialog-title px-5 py-5'">
        <span class="headline">{{ extensionParent ? $t('pages.contracts.allContracts.allContracts.extendContract') : isEmployeeAudience ? $t('pages.contracts.allContracts.allContracts.employeeContracts') : dialogMode === 'add' ? $t('pages.contracts.allContracts.allContracts.addNewContract') : $t('pages.contracts.allContracts.allContracts.editContract') }}</span>
        <template v-if="isOtherAudience || isEmployeeAudience">
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="error" class="other-contract-dialog-close" :aria-label="$t('common.actions.close')" @click="isDialogVisible = false" />
        </template>
      </VCardTitle>

      <VCardText :class="['contract-form-dialog__body', (isOtherAudience || isEmployeeAudience) && 'other-contract-dialog-body pa-5 pa-sm-6']">
        <ContractForm
          :key="extensionParent ? `extend-${extensionParent.id}` : dialogMode === 'edit' ? `edit-${selectedContract?.id}` : 'add'"
          :contract="selectedContract || undefined"
          :parent-contract="extensionParent"
          :extension-mode="Boolean(extensionParent)"
          :templates="templates"
          :audience="contractAudience"
          @submit="handleFormSubmit"
          @cancel="isDialogVisible = false"
        />
      </VCardText>
    </VCard>
  </VDialog>

  <VDialog
    v-model="isContractActionDialogVisible"
    max-width="460px"
    class="contract-action-overlay"
  >
    <VCard class="contract-action-dialog">
      <VCardText class="contract-action-dialog__content">
        <div class="contract-action-dialog__icon"><VIcon :icon="pendingContractAction?.type === 'delete' ? 'tabler-trash' : 'tabler-ban'" size="30" /></div>
        <h2 class="contract-action-dialog__title">{{ $t('pages.contracts.allContracts.allContracts.confirmContractAction') }}</h2>
        {{ pendingContractAction?.type === 'delete'
          ? $t('pages.contracts.allContracts.allContracts.deleteContractConfirmation', { contract: pendingContractAction ? contractName(pendingContractAction.contract) : '' })
          : $t('pages.contracts.allContracts.allContracts.cancelContractConfirmation', { contract: pendingContractAction ? contractName(pendingContractAction.contract) : '' }) }}
      </VCardText>
      <VCardActions class="contract-action-dialog__actions">
        <VBtn
          class="contract-action-dialog__button contract-action-dialog__button--cancel"
          @click="isContractActionDialogVisible = false"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          class="contract-action-dialog__button contract-action-dialog__button--confirm"
          :class="{ 'contract-action-dialog__button--delete': pendingContractAction?.type === 'delete' }"
          @click="confirmContractAction"
        >
          {{ pendingContractAction?.type === 'delete' ? $t('common.actions.delete') : $t('pages.contracts.allContracts.allContracts.cancelContract') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <ContractDocumentPrinter v-model="isPrintDialogVisible" :contract="contractToPrint" />
  </div>
</template>

<style scoped>
:deep(.contract-number-link + span) { display: none; }
:deep(.contract-number-link) { cursor: pointer; }
:deep(.contract-number-link .v-btn__overlay) { opacity: 0 !important; }
.contract-form-dialog__body {
  /* ContractForm owns the action bar's padding. Keeping VCardText's bottom
     padding leaves an extra gutter below that footer inside the dialog. */
  padding-block-end: 0 !important;
}

:global(.other-contract-form-dialog .other-contract-dialog-title),
:global(.other-contract-form-dialog .app-form__actions) {
  border-style: solid;
  border-color: rgba(var(--v-theme-on-surface), 0.035);
  background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%);
}

:global(.other-contract-form-dialog .other-contract-dialog-title) {
  display: flex;
  align-items: center;
  border-width: 0 0 1px;
}

:global(.other-contract-form-dialog .app-form__actions) {
  justify-content: flex-start;
  border-width: 1px 0 0;
  margin-inline: -1.5rem;
  padding-block: 1rem;
  padding-inline: 1.5rem;
}

:global(.other-contract-form-dialog .other-contract-form-actions__save) {
  background-color: rgb(var(--v-theme-primary)) !important;
  color: rgb(var(--v-theme-on-primary)) !important;
}

:global(.other-contract-form-dialog .other-contract-form-actions__cancel) {
  border: 1px solid rgba(var(--v-theme-error), 0.62) !important;
  background-color: rgba(var(--v-theme-error), 0.12) !important;
  color: rgb(var(--v-theme-error)) !important;
}

:global(.other-contract-form-dialog .other-contract-form-actions__cancel:hover),
:global(.other-contract-form-dialog .other-contract-form-actions__cancel:focus-visible),
:global(.other-contract-form-dialog .other-contract-form-actions__cancel:active) {
  border-color: rgb(var(--v-theme-error)) !important;
  background-color: rgb(var(--v-theme-error)) !important;
  color: rgb(var(--v-theme-on-error)) !important;
}
:global(.other-contract-form-dialog .other-contract-form-actions__save:hover) { filter: brightness(0.9); }
:global(.other-contract-form-dialog .app-form__actions .v-btn) { min-inline-size: 104px; }
:global(.other-contract-form-dialog .other-contract-dialog-close:hover) { background-color: rgba(var(--v-theme-error), 0.18) !important; }
:global(.v-overlay:has(.other-contract-form-dialog) .v-overlay__scrim) { backdrop-filter: blur(4px); background: rgba(var(--v-theme-on-surface), 0.24); opacity: 1 !important; }
:global(.contract-action-overlay .v-overlay__scrim) { backdrop-filter: blur(5px); background: rgba(var(--v-theme-on-surface), 0.28); opacity: 1 !important; }
:global(.other-contract-form-dialog .v-card-text) { scrollbar-width: none; }
:global(.other-contract-form-dialog .v-card-text::-webkit-scrollbar) { display: none; }

.contract-action-dialog { overflow: hidden; border: 1px solid rgba(var(--v-border-color), 0.2) !important; border-radius: 16px !important; text-align: center; }
.contract-action-dialog__content { display: flex; flex-direction: column; align-items: center; padding-block: 2rem 1.25rem !important; padding-inline: 2rem !important; }
.contract-action-dialog__icon { display: grid; border-radius: 50%; background: rgba(var(--v-theme-error), 0.14); block-size: 4.25rem; color: rgb(var(--v-theme-error)); inline-size: 4.25rem; margin-block-end: 1.25rem; place-items: center; }
.contract-action-dialog__title { font-size: 1.2rem; font-weight: 700; margin-block: 0 0.7rem; margin-inline: 0; }
.contract-action-dialog__actions { display: grid !important; gap: 0.75rem; grid-template-columns: repeat(2, minmax(0, 1fr)); padding-block: 0 2rem !important; padding-inline: 2rem !important; }
.contract-action-dialog__button { border-radius: 10px !important; background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; font-weight: 700; min-block-size: 2.85rem; }
.contract-action-dialog__button--cancel { border: 1px solid rgba(var(--v-theme-secondary), 0.52) !important; background: rgba(var(--v-theme-secondary), 0.13) !important; color: rgb(var(--v-theme-secondary)) !important; }
.contract-action-dialog__button--delete { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }

.contract-table :deep(.v-data-table__wrapper) {
  overflow-x: auto;
  scrollbar-width: none;
}

.contract-empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; min-block-size: 17rem; padding-block: 1.25rem 2rem; padding-inline: 1.25rem; }
.contract-empty-state :deep(.table-empty-state) { min-block-size: 0 !important; padding-block: 1rem; }
.contract-empty-state__action { margin-block-start: -0.25rem; }

.contract-table :deep(.v-data-table__wrapper::-webkit-scrollbar) {
  display: none;
}

.contract-table :deep(.v-data-table__wrapper > table) {
  min-inline-size: 1100px;
}

.contract-table :deep(.v-table__wrapper > table) {
  min-inline-size: 1100px;
}

.contract-table :deep(.v-table__wrapper > table > tbody > tr:hover > td) {
  background-color: rgba(var(--v-theme-on-surface), 0.035) !important;
}

.contract-table :deep(.v-table__wrapper:has(.table-empty-state)) {
  overflow-x: hidden;
}

.contract-table :deep(.v-table__wrapper > table:has(.table-empty-state)) {
  min-inline-size: 100%;
  table-layout: fixed;
}

.contract-table :deep(.v-table__wrapper > table:has(.table-empty-state) > tbody > tr:hover > td),
.contract-table :deep(.v-table__wrapper > table > tbody > tr.responsive-data-table__skeleton-row:hover > td) {
  background-color: transparent !important;
}

@media (max-width: 959px) {
  .contract-table.responsive-data-table--mobile-cards :deep(.v-table__wrapper > table > tbody > tr:hover > td),
  .contract-table.responsive-data-table--mobile-cards :deep(.responsive-data-table__mobile-row:hover > td) {
    background-color: transparent !important;
  }

  .contract-table :deep(.v-table__wrapper) {
    overflow: clip;
  }

  .contract-table :deep(.v-table__wrapper > table) {
    inline-size: 100%;
    min-inline-size: 0;
    table-layout: fixed;
  }

  .contract-table :deep(.v-data-table__thead) {
    display: none;
  }

  .contract-table :deep(.responsive-data-table__mobile-row > td) {
    padding: 0.75rem;
  }
}
</style>
