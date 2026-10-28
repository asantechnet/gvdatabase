<script setup lang="ts">
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import ErpField from '@/components/erp/ErpField.vue'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import ContractCover from './templates/contract_cover_template.vue'
import ContractPageTemplate, { type CompanyDetails } from './templates/contract_page_template.vue'
import ContractApi from '@/api/contracts'
import ContractTemplateApi from '@/api/contractTemplate'
import type { Contract, ContractParams } from '@/api/contracts'
import SettingApi, { type Setting } from '@/api/setting'
import ContractForm, { type ContractFormPayload } from '@/components/ContractForm.vue'
import PrintableListReport from '@/components/PrintableListReport.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'
import { useContracts } from '@/composables/useContracts'
import { legacyContentToHtml } from '@/utils/contractPlaceholders'
import { printDocument } from '@/utils/printDocument'
import { resolveContractArtwork } from '@/utils/printArtwork'
import { canPrintPartyDetails, partyDetailsFor } from '@/utils/contractPartyDetails'
import { buildContractPrintHeader, canPrintContractHeader } from '@/composables/useContractPrintHeader'

const props = withDefaults(defineProps<{
  contractAudience?: 'employees' | 'other'
}>(), {
  contractAudience: 'other',
})

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
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
// Filled from every matching contract right before printing.
const reportRows = ref<string[][]>([])
const counterparties = ref<string[]>([])

const search = ref('')
const filterCounterparty = ref<string | null>(null)
const dateFrom = ref('')
const dateTo = ref('')
const filterStatus = ref<string | null>(null)
const isDialogVisible = ref(false)
const dialogMode = ref<'add' | 'edit'>('add')
const selectedContract = ref<Contract | null>(null)
// Set only while the form creates an extension of an existing agreement.
const extensionParent = ref<Contract | null>(null)

// Print Specific State
const isPrintDialogVisible = ref(false)
const includeEmptyLastPage = ref(false)
const includeSignatureSection = ref(true)
const includeCustomCover = ref(true)
const includeCustomLetterhead = ref(true)
const includePartyDetails = ref(false)
const includeContractHeader = ref(false)
const contractToPrint = ref<Contract | null>(null)

const combinedPrintRef = ref<HTMLElement | null>(null)
const isPrintLoading = ref(false)
const isCancelling = ref(false)
const toast = useToast()
const isContractActionDialogVisible = ref(false)
const pendingContractAction = ref<{ type: 'delete' | 'cancel'; contract: Contract } | null>(null)
// Other contracts are free-form templates the office completes by hand, so they
// carry no counterparty at all: no input, no column, no filter and no print
// party block. Only employment contracts are drawn for a named counterparty.
const isOtherAudience = computed(() => props.contractAudience === 'other')
const contractRequest = computed(() => ({ audience: props.contractAudience }))
const pageTitle = computed(() => t(audienceCopy.value.title))
const pageDescription = computed(() => t(audienceCopy.value.description))

const statuses = ['draft', 'active', 'completed', 'terminated', 'cancelled']
const canShowPartyDetails = computed(() => !isOtherAudience.value && contractToPrint.value !== null && canPrintPartyDetails(contractToPrint.value))
const canShowContractHeader = computed(() => contractToPrint.value !== null && canPrintContractHeader(contractToPrint.value))
const contractHeader = computed(() => (contractToPrint.value ? buildContractPrintHeader(contractToPrint.value) : null))
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
  date_from: dateFrom.value || undefined,
  date_to: dateTo.value || undefined,
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

function updateTableOptions(options: { page: number; itemsPerPage: number }) {
  if (page.value === options.page && perPage.value === options.itemsPerPage)
    return

  page.value = options.page
  perPage.value = options.itemsPerPage
  void load()
}

// Typing in the search box is a filter change, so it starts from the first page.
const debouncedSearch = useDebounceFn(reload, 300)

async function loadCounterparties() {
  if (isOtherAudience.value)
    return

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
    { title: t('common.messages.contract'), key: 'contract', sortable: true },
    { title: t('agencyStaff.contractVersion'), key: 'version', sortable: true },
  ]

  if (!isOtherAudience.value)
    rows.push({ title: t('pages.contracts.allContracts.allContracts.counterparty'), key: 'counterparty', sortable: true })

  rows.push(
    { title: t('common.fields.startDate'), key: 'start_date', sortable: true },
    { title: t('common.fields.status'), key: 'status', sortable: true },
    { title: t('common.messages.actions'), key: 'actions', sortable: false },
  )

  return rows
})

const contractReportRow = (contract: Contract): string[] => [
  contract.contract_number ?? '—',
  contract.contract || contract.contract_template?.name || '—',
  contract.snapshot_data?.template_version?.name || '—',
  ...(isOtherAudience.value ? [] : [contract.counterparty || '—']),
  contract.start_date ?? '—',
  contract.status,
]

const companySetting = ref<Setting | null>(null)
const snapshotDesign = computed(() => contractToPrint.value?.snapshot_data?.document_design)
const printedContractTitle = computed(() => {
  const name = contractToPrint.value?.contract || contractToPrint.value?.contract_template?.name || translate('pages.contracts.allContracts.allContracts.untitledContract')
  const version = contractToPrint.value?.snapshot_data?.template_version

  return version?.version_number ? [name, version.name, `v${version.version_number}`, version.country_name, version.language_name].filter(Boolean).join(' - ') : name
})

const company = computed<CompanyDetails>(() => ({
  brandName: companySetting.value?.original_company_name || companySetting.value?.short_company_name || companySetting.value?.website_title || 'Company',
  logo: companySetting.value?.website_logo_url || undefined,
  address: companySetting.value?.physical_address || undefined,
  phone: companySetting.value?.phone || undefined,
  website: companySetting.value?.website_address || undefined,
  email: companySetting.value?.company_email || undefined,
  licence: companySetting.value?.business_licence || undefined,
  signatureUrl: companySetting.value?.company_signature_url || undefined,
  letterheadUrl: snapshotDesign.value?.letterhead_url || companySetting.value?.contract_letterhead_url || undefined,
  letterheadSafeX: snapshotDesign.value?.safe_x ?? companySetting.value?.contract_letterhead_safe_x,
  letterheadSafeY: snapshotDesign.value?.safe_y ?? companySetting.value?.contract_letterhead_safe_y,
  letterheadSafeWidth: snapshotDesign.value?.safe_width ?? companySetting.value?.contract_letterhead_safe_width,
  letterheadSafeHeight: snapshotDesign.value?.safe_height ?? companySetting.value?.contract_letterhead_safe_height,
}))

// Configured artwork whose file is gone must never replace the built-in cover
// or letterhead with a blank page (see `utils/printArtwork`).
const coverArtworkAvailable = ref(true)
const letterheadArtworkAvailable = ref(true)
const useCustomCover = computed(() => includeCustomCover.value
  && Boolean(snapshotDesign.value?.cover_design_url || companySetting.value?.contract_cover_design_url)
  && coverArtworkAvailable.value)
const printableCompany = computed<CompanyDetails>(() => ({
  ...company.value,
  letterheadUrl: includeCustomLetterhead.value && letterheadArtworkAvailable.value ? company.value.letterheadUrl : undefined,
}))
const printedPages = computed(() => (contractToPrint.value ? getContractPagesHtml(contractToPrint.value) : []))

const loadPrintArtwork = async () => {
  coverArtworkAvailable.value = true
  letterheadArtworkAvailable.value = true

  const coverUrl = snapshotDesign.value?.cover_design_url || companySetting.value?.contract_cover_design_url || null
  const letterheadUrl = company.value.letterheadUrl || null
  const availability = await resolveContractArtwork({ cover: coverUrl, letterhead: letterheadUrl })

  coverArtworkAvailable.value = availability.cover
  letterheadArtworkAvailable.value = availability.letterhead

  if ((coverUrl && !availability.cover) || (letterheadUrl && !availability.letterhead))
    toast.info(translate('pages.contracts.allContracts.allContracts.artworkUnavailable'))
}

const getContractPagesHtml = (contract: Contract): string[] => {
  // Contracts retain their own content snapshot so later template edits never
  // rewrite a document that has already been issued.
  const content = contract.content
  const html = legacyContentToHtml(content)
  if (html)
    return [html]

  // Fallback to default content if no template content is available
  const startDate = formatLocalizedDate(contract.start_date)
  const contractName = contract.contract || contract.contract_template?.name || translate('pages.contracts.allContracts.allContracts.untitledContract')

  return [
    `
    <h1>${translate('pages.contracts.shared.contractAgreement')}</h1>
    <p>${translate('pages.contracts.shared.fallbackOpening')} <strong>${company.value.brandName}</strong> ${translate('pages.contracts.shared.and')} <strong>${contract.counterparty || '________________'}</strong> on <strong>${startDate}</strong>.</p>

    <h2>1. ${translate('pages.contracts.shared.contractParties')}</h2>
    <p><strong>${translate('pages.contracts.shared.serviceProvider')}:</strong> ${company.value.brandName}, ${translate('pages.contracts.shared.locatedAt')} ${company.value.address || translate('pages.contracts.shared.registeredCompanyAddress')}</p>
    <p><strong>${translate('pages.contracts.shared.client')}:</strong> ${contract.counterparty || '________________'}</p>

    <h2>2. ${translate('pages.contracts.shared.contractDetails')}</h2>
    <p><strong>${translate('pages.contracts.shared.contractName')}:</strong> ${contractName}</p>
    <p><strong>${translate('pages.contracts.shared.status')}:</strong> ${contract.status}</p>
    <p><strong>${translate('pages.contracts.shared.startDate')}:</strong> ${startDate}</p>
    `,

    `
    <h2>3. ${translate('pages.contracts.shared.termsAndConditions')}</h2>
    <p>${translate('pages.contracts.shared.partiesAgree')}</p>
    <ol>
      <li>${translate('pages.contracts.shared.obligationsTerm')}</li>
      <li>${translate('pages.contracts.shared.changesTerm')}</li>
      <li>${translate('pages.contracts.shared.durationTerm')}</li>
    </ol>

    <h2>4. ${translate('pages.contracts.shared.termination')}</h2>
    <p>${translate('pages.contracts.shared.terminationNotice')}</p>
    `,
  ]
}

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

const resetPrintOptions = () => {
  includeEmptyLastPage.value = false
  includeSignatureSection.value = true
  includePartyDetails.value = false
  includeContractHeader.value = false
}

const printContract = async (contract: Contract) => {
  try {
    isPrintLoading.value = true

    // The print body renders from the freshest contract: the server resolves
    // the artwork scoped to this contract's type (and service) — including the
    // print-safe area — for contracts that carry no design snapshot yet.
    let printable = contract

    try {
      printable = await ContractApi.getById(contract.id)
    }
    catch (error) {
      console.warn('Failed to refresh the contract before printing, using the listed row.', error)
    }

    // If the contract template is not loaded, fetch it
    if (!printable.contract_template && printable.template_id) {
      const template = await ContractTemplateApi.getById(printable.template_id)

      printable.contract_template = {
        id: template.id,
        name: template.name,
        type: template.type,
        cover_text: template.cover_text,
        content: template.content,
      }
    }

    contractToPrint.value = printable
    resetPrintOptions()
    await loadPrintArtwork()
    isPrintDialogVisible.value = true
  }
  catch (err) {
    console.error('Failed to load contract template:', err)
    toast.warning(translate('pages.contracts.allContracts.allContracts.failedToLoadContractTemplateUsingDefaultContent'))
    contractToPrint.value = contract
    resetPrintOptions()
    isPrintDialogVisible.value = true
  }
  finally {
    isPrintLoading.value = false
  }
}

const executePrint = async () => {
  if (!combinedPrintRef.value) return
  await printDocument({ bodyClass: 'printing-contract-document', pageStyle: 'size: A4; margin: 0;', element: combinedPrintRef.value })
  isPrintDialogVisible.value = false
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
  selectedContract.value = { ...contract }
  isDialogVisible.value = true
}

const contractName = (contract: Contract) => contract.contract || contract.contract_template?.name || translate('pages.contracts.allContracts.allContracts.unnamedContract')

// An agreement is extended from a live primary contract: cancelled, finished
// and already-extended records never spawn another extension.
const canExtend = (contract: Contract) => ability.can('read', 'create_contracts')
  && !contract.is_cancelled
  && contract.contract_role !== 'extension'
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
  }
  catch (err) {
    toast.error(translate('pages.contracts.allContracts.allContracts.failedToSaveContractPleaseTryAgain'))
  }
}

onMounted(async () => {
  await Promise.all([
    load(),
    fetchTemplates(),
    loadCounterparties(),
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
    const contract = contracts.value.find(item => item.id === contractId)
    if (contract)
      search.value = contract.contract_number || contract.counterparty || contract.contract
  }
  try {
    companySetting.value = (await SettingApi.getCompanyProfile()).data
  }
  catch {
    // Printing still works with neutral fallback company details.
  }
})

</script>

<template>
  <div class="erp-workspace">
  <ErpPageHeader :title="pageTitle" section="contracts" />
  <VCard>
    <VCardText>
      <VRow
        align="center"
        class="table-toolbar"
      >

        <VCol
          cols="12"
          md="8"
          lg="6"
          class="table-toolbar-actions d-flex flex-nowrap justify-end gap-2 ms-auto"
        >
          <DataTableFilterMenu
            :active="Boolean(search || filterCounterparty || filterStatus || dateFrom || dateTo)"
            @reset="search = ''; filterCounterparty = null; filterStatus = null; dateFrom = ''; dateTo = ''; reload()"
          >
            <VTextField
            v-model="search"
            class="data-table-search"
            prepend-inner-icon="tabler-search"
            :placeholder="$t('pages.contracts.allContracts.allContracts.searchContracts')"
            density="compact"
            clearable
            hide-details
            @update:model-value="debouncedSearch"
          />
            <ErpDateRange v-model:from="dateFrom" v-model:to="dateTo" class="mb-4" @apply="reload" />
            <VSelect
              v-if="!isOtherAudience"
              v-model="filterCounterparty"
              :items="counterparties"
              :label="$t('pages.contracts.allContracts.allContracts.filterByCounterparty')"
              density="compact"
              clearable
              hide-details
              @update:model-value="reload"
            />
            <VSelect
              v-model="filterStatus"
              :items="statuses"
              :label="$t('common.messages.filterByStatus')"
              density="compact"
              clearable
              hide-details
              @update:model-value="reload"
            />
          </DataTableFilterMenu>
          <VBtn
            icon="tabler-printer"
            variant="text"
            class="flex-shrink-0"
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
            :loading="isLoading"
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

    <ResponsiveDataTableServer
      :headers="headers"
      :items="contracts"
      :items-length="total"
      :page="page"
      :items-per-page="perPage"
      :items-per-page-options="[10, 25, 50, 100]"
      item-value="id"
      class="text-no-wrap"
      mobile-breakpoint="sm"
      :loading="isLoading"
      style="overflow-x: auto;"
      @update:options="updateTableOptions"
    >
      <template #loading><TableLoadingSkeleton :columns="headers.length" :label="$t('common.messages.loading')" /></template>
      <template #no-data><TableEmptyState icon="tabler-file-off" :description="$t('pages.contracts.allContracts.allContracts.noContracts')" /></template>
      <template #item.version="{ item }">{{ item.snapshot_data?.template_version?.name ?? '—' }}<span v-if="item.snapshot_data?.template_version?.version_number"> · v{{ item.snapshot_data.template_version.version_number }}</span></template>
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
      <template
        v-if="!isOtherAudience"
        #item.counterparty="{ item }"
      >
        {{ item.counterparty || $t('pages.contracts.allContracts.allContracts.unknownCounterparty') }}
      </template>
      <template #item.start_date="{ item }">
        {{ formatLocalizedDate(item.start_date) }}
      </template>
      <template #item.status="{ item }">
        <VChip
          :color="getStatusColor(item.status)"
          size="small"
          class="font-weight-medium"
        >
          {{ translateBackendValue(item.status || 'unknown') }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <FullDetailsButton
          :item="item"
          :title="item.contract_number || item.contract"
        />
        <VBtn
          v-if="ability.can('read', 'cancel_contracts') && !item.is_cancelled && ['draft', 'active'].includes(item.status)"
          icon="tabler-ban"
          variant="text"
          size="small"
          :loading="isCancelling"
          class="me-2"
          color="error"
          @click="cancelContract(item)"
        >
          <VIcon icon="tabler-ban" /><VTooltip
            activator="parent"
            location="top"
          >
            {{ $t('pages.contracts.allContracts.allContracts.cancelContract') }}
          </VTooltip>
        </VBtn>
        <VBtn
          v-if="item.status === 'draft' && !item.is_cancelled"
          icon="tabler-edit"
          variant="text"
          size="small"
          class="me-2"
          @click="editContractUI(item)"
        >
          <VIcon icon="tabler-edit" /><VTooltip
            activator="parent"
            location="top"
          >
            {{ $t('pages.contracts.allContracts.allContracts.editContract') }}
          </VTooltip>
        </VBtn>
        <VBtn
          v-if="item.status === 'draft' && !item.is_cancelled"
          icon="tabler-trash"
          variant="text"
          size="small"
          class="me-2"
          @click="deleteContractUI(item)"
        >
          <VIcon icon="tabler-trash" /><VTooltip
            activator="parent"
            location="top"
          >
            {{ $t('pages.contracts.allContracts.allContracts.deleteContract') }}
          </VTooltip>
        </VBtn>
        <VBtn
          v-if="canExtend(item)"
          icon="tabler-file-plus"
          variant="text"
          size="small"
          class="me-2"
          @click="extendContract(item)"
        >
          <VIcon icon="tabler-file-plus" /><VTooltip
            activator="parent"
            location="top"
          >
            {{ $t('pages.contracts.allContracts.allContracts.extendContract') }}
          </VTooltip>
        </VBtn>
        <!-- A cancelled contract keeps its history but is not printable; View
             stays as its only action. -->
        <VBtn
          v-if="!item.is_cancelled"
          icon="tabler-printer"
          variant="text"
          size="small"
          :loading="isPrintLoading"
          @click="printContract(item)"
        >
          <VIcon icon="tabler-printer" /><VTooltip
            activator="parent"
            location="top"
          >
            {{ $t('pages.contracts.allContracts.allContracts.printContract') }}
          </VTooltip>
        </VBtn>
      </template>
    </ResponsiveDataTableServer>
  </VCard>
  <PrintableListReport
    :title="t(audienceCopy.report)"
    :subtitle="$t('pages.contracts.allContracts.allContracts.filteredContractRecords')"
    :columns="headers.slice(0, -1).map(header => header.title)"
    :rows="reportRows"
  />

  <!-- Contract Form Dialog -->
  <VDialog
    v-model="isDialogVisible" class="erp-form"
    max-width="600px"
  >
    <VCard>
      <VCardTitle>
        <span class="headline">{{ extensionParent ? $t('pages.contracts.allContracts.allContracts.extendContract') : dialogMode === 'add' ? $t('pages.contracts.allContracts.allContracts.addNewContract') : $t('pages.contracts.allContracts.allContracts.editContract') }}</span>
      </VCardTitle>

      <VCardText>
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
  >
    <VCard>
      <VCardTitle>{{ $t('pages.contracts.allContracts.allContracts.confirmContractAction') }}</VCardTitle>
      <VCardText>
        {{ pendingContractAction?.type === 'delete'
          ? $t('pages.contracts.allContracts.allContracts.deleteContractConfirmation', { contract: pendingContractAction ? contractName(pendingContractAction.contract) : '' })
          : $t('pages.contracts.allContracts.allContracts.cancelContractConfirmation', { contract: pendingContractAction ? contractName(pendingContractAction.contract) : '' }) }}
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="isContractActionDialogVisible = false"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          :color="pendingContractAction?.type === 'delete' ? 'error' : 'warning'"
          @click="confirmContractAction"
        >
          {{ pendingContractAction?.type === 'delete' ? $t('common.actions.delete') : $t('pages.contracts.allContracts.allContracts.cancelContract') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Print Options Dialog -->
  <VDialog
    v-model="isPrintDialogVisible"
    max-width="480px"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="tabler-printer" />
        <span>{{ $t('pages.contracts.allContracts.allContracts.printContractOptions') }}</span>
      </VCardTitle>
      <VCardText>
        <p class="mb-4">
          {{ $t('pages.contracts.allContracts.allContracts.prepare') }} <strong>{{ contractToPrint?.contract || contractToPrint?.contract_template?.name
            || $t('pages.contracts.allContracts.allContracts.untitledContract') }}</strong> {{ $t('pages.contracts.allContracts.allContracts.forA4PrintingTheDocumentWillIncludeTheCoverPageAndAllContractTerms') }}
        </p>
        <div class="d-flex flex-column gap-2">
          <VCheckbox
            v-model="includeSignatureSection"
            :label="$t('pages.contracts.allContracts.allContracts.includeFormalSignatureStampSectionAtTheEnd')"
            density="compact"
            hide-details
          />
          <VCheckbox
            v-model="includeCustomCover"
            :label="$t('pages.contracts.allContracts.allContracts.useCustomCoverPageFromSettings')"
            density="compact"
            hide-details
          />
          <VCheckbox
            v-model="includeCustomLetterhead"
            :label="$t('pages.contracts.allContracts.allContracts.useCustomLetterheadFromSettings')"
            density="compact"
            hide-details
          />
          <VCheckbox
            v-if="canShowPartyDetails"
            v-model="includePartyDetails"
            :label="$t('pages.contracts.shared.includePartyDetails')"
            density="compact"
            hide-details
          />
          <VCheckbox
            v-if="canShowContractHeader"
            v-model="includeContractHeader"
            :label="$t('pages.contracts.shared.header.includeContractHeader')"
            :hint="$t('pages.contracts.shared.header.includeContractHeaderHint')"
            density="compact"
            hide-details
            persistent-hint
          />
          <VCheckbox
            v-model="includeEmptyLastPage"
            :label="$t('pages.contracts.allContracts.allContracts.addALastFullyEmptyPage')"
            density="compact"
            hide-details
          />
        </div>
      </VCardText>
      <VCardActions class="justify-end px-4 pb-4">
        <VBtn
          variant="outlined"
          color="secondary"
          @click="isPrintDialogVisible = false"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          @click="executePrint"
        >
          {{ $t('pages.contracts.allContracts.allContracts.printA4Document') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Hidden Printable Document Structure -->
  <div
    ref="combinedPrintRef"
    style="display: none;"
  >
    <template v-if="contractToPrint">
        <div
          v-if="useCustomCover"
        class="custom-cover-page"
      >
        <img
            :src="snapshotDesign?.cover_design_url || companySetting?.contract_cover_design_url || ''"
          alt=""
          @error="coverArtworkAvailable = false"
        >
      </div>
      <ContractCover
        v-else
        :contractee-name="contractToPrint.counterparty || ''"
        :logo="company.logo"
        cover-design-url=""
        :contract-number="contractToPrint.contract_number || ''"
            :contract-title="printedContractTitle"
        :cover-description="contractToPrint.cover_text || contractToPrint.contract_template?.cover_text || ''"
        :company-website="company.website || ''"
        :company-address="company.address || ''"
        :company-email="company.email || ''"
        :company-number="company.phone || ''"
      />

      <ContractPageTemplate
        v-if="includePartyDetails && canShowPartyDetails"
        :document-title="$t('pages.contracts.shared.contractParties')"
        :document-number="contractToPrint.contract_number || ''"
        :company="printableCompany"
        :party-details="partyDetailsFor(contractToPrint, company)"
        :contractee-name="contractToPrint.counterparty || ''"
        :page-number-string="$t('pages.contracts.allContracts.allContracts.pageNumber', { page: 1, total: printedPages.length + 1 })"
        :footer-text="$t('pages.contracts.allContracts.allContracts.confidentialAllRightsReserved')"
        class="contract-content"
      />

      <ContractPageTemplate
        v-for="(pageHtml, idx) in printedPages"
        :key="idx"
        :document-number="contractToPrint.contract_number || ''"
            :document-title="printedContractTitle"            :company="printableCompany"
        :header-details="includeContractHeader && idx === 0 ? contractHeader : null"
        :html-content="pageHtml"
        :page-number-string="$t('pages.contracts.allContracts.allContracts.pageNumber', { page: idx + (includePartyDetails && canShowPartyDetails ? 2 : 1), total: printedPages.length + (includePartyDetails && canShowPartyDetails ? 1 : 0) })"
        :show-signature="includeSignatureSection && idx === printedPages.length - 1"
        :contractee-name="contractToPrint.counterparty || ''"
        :company-signatory-name="contractToPrint.company_signatory_name || null"
        :footer-text="$t('pages.contracts.allContracts.allContracts.confidentialAllRightsReserved')"
        class="contract-content"
      />

      <div
        v-if="includeEmptyLastPage"
        class="empty-print-page"
        style="width: 210mm; height: 297mm; background: white;"
      />
    </template>
  </div>
  </div>
</template>
