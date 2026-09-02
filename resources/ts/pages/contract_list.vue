<script setup lang="ts">
import ContractApi from '@/api/contracts'
import ContractTemplateApi from '@/api/contractTemplate'
import type { Contract } from '@/api/contracts'
import SettingApi, { type Setting } from '@/api/setting'
import ContractForm, { type ContractFormPayload } from '@/components/ContractForm.vue'
import PrintableListReport from '@/components/PrintableListReport.vue'
import { useContracts } from '@/composables/useContracts'
import { legacyContentToHtml } from '@/utils/contractPlaceholders'
import { printDocument } from '@/utils/printDocument'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { VAlert } from 'vuetify/components'
import ContractCover from './templates/contract_cover_template.vue'
import ContractPageTemplate, { type CompanyDetails } from './templates/contract_page_template.vue'
import { canPrintPartyDetails, partyDetailsFor } from '@/utils/contractPartyDetails'

const props = withDefaults(defineProps<{
  contractAudience?: 'customers' | 'employees' | 'companies'
}>(), {
  contractAudience: 'customers',
})
const route = useRoute()
const router = useRouter()

const currentListRoute = () => ({
  customers: 'contracts',
  employees: 'employee-contracts',
  companies: 'company-contracts',
}[props.contractAudience])

const {
  contracts,
  templates,
  isLoading,
  error,
  fetchContracts,
  fetchTemplates,
  addContract,
  editContract,
  removeContract
} = useContracts()

const search = ref('')
const filterCounterparty = ref<string | null>(null)
const filterStatus = ref<string | null>(null)
const isDialogVisible = ref(false)
const dialogMode = ref<'add' | 'edit'>('add')
const selectedContract = ref<Contract | null>(null)

// Print Specific State
const isPrintDialogVisible = ref(false)
const includeEmptyLastPage = ref(false)
const includeSignatureSection = ref(true)
const includeCustomCover = ref(true)
const includeCustomLetterhead = ref(true)
const includePartyDetails = ref(false)
const contractToPrint = ref<Contract | null>(null)
const combinedPrintRef = ref<HTMLElement | null>(null)
const isPrintLoading = ref(false)
const isCancelling = ref(false)
const toast = useToast()
const isContractActionDialogVisible = ref(false)
const pendingContractAction = ref<{ type: 'delete' | 'cancel'; contract: Contract } | null>(null)

const counterparties = computed(() => [...new Set(contracts.value.map(c => c.counterparty).filter(c => c))])
const statuses = ['draft', 'active', 'expired', 'terminated', 'cancelled']
const canShowPartyDetails = computed(() => contractToPrint.value !== null && canPrintPartyDetails(contractToPrint.value))

const filtered = computed(() => {
  return contracts.value.filter(c => {
    const contractName = c.contract || c.contract_template?.name || ''
    const counterpartyName = c.counterparty || ''
    const matchesSearch = contractName.toLowerCase().includes(search.value.toLowerCase()) ||
      counterpartyName.toLowerCase().includes(search.value.toLowerCase()) ||
      (c.contract_number || '').toLowerCase().includes(search.value.toLowerCase())
    const matchesCounterparty = !filterCounterparty.value || c.counterparty === filterCounterparty.value
    const matchesStatus = !filterStatus.value || c.status === filterStatus.value
    return matchesSearch && matchesCounterparty && matchesStatus
  })
})

const headers = [
  { title: translate('common.messages.contractNumber'), key: 'contract_number', sortable: true },
  { title: translate('common.messages.contract'), key: 'contract', sortable: true },
  { title: translate('common.fields.type'), key: 'type', sortable: true },
  { title: translate('pages.contracts.allContracts.allContracts.counterparty'), key: 'counterparty', sortable: true },
  { title: 'Services', key: 'services', sortable: false },
  { title: translate('common.fields.startDate'), key: 'start_date', sortable: true },
  { title: translate('common.fields.status'), key: 'status', sortable: true },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false },
]

const companySetting = ref<Setting | null>(null)
const company = computed<CompanyDetails>(() => ({
  brandName: companySetting.value?.original_company_name || companySetting.value?.short_company_name || companySetting.value?.website_title || 'Company',
  logo: companySetting.value?.website_logo_url || undefined,
  address: companySetting.value?.physical_address || undefined,
  phone: companySetting.value?.phone || undefined,
  website: companySetting.value?.website_address || undefined,
  email: companySetting.value?.company_email || undefined,
  licence: companySetting.value?.business_licence || undefined,
  signatureUrl: companySetting.value?.company_signature_url || undefined,
  letterheadUrl: companySetting.value?.contract_letterhead_url || undefined,
  letterheadSafeX: companySetting.value?.contract_letterhead_safe_x,
  letterheadSafeY: companySetting.value?.contract_letterhead_safe_y,
  letterheadSafeWidth: companySetting.value?.contract_letterhead_safe_width,
  letterheadSafeHeight: companySetting.value?.contract_letterhead_safe_height,
}))

const getContractPagesHtml = (contract: Contract): string[] => {
  // Contracts retain their own content snapshot so later template edits never
  // rewrite a document that has already been issued.
  const content = contract.content ?? contract.contract_template?.content
  const html = legacyContentToHtml(content)
  if (html) return [html]

  // Fallback to default content if no template content is available
  const startDate = formatLocalizedDate(contract.start_date)
  const contractName = contract.contract || contract.contract_template?.name || translate('pages.contracts.allContracts.allContracts.untitledContract')
  const pages = [
    `
    <h1>Contract Agreement</h1>
    <p>This agreement is made between <strong>${company.value.brandName}</strong> and <strong>${contract.counterparty}</strong> on <strong>${startDate}</strong>.</p>

    <h2>1. Parties</h2>
    <p><strong>Service Provider:</strong> ${company.value.brandName}, located at ${company.value.address || 'the registered company address'}</p>
    <p><strong>Client:</strong> ${contract.counterparty}</p>

    <h2>2. Contract Details</h2>
    <p><strong>Contract Name:</strong> ${contractName}</p>
    <p><strong>Status:</strong> ${contract.status}</p>
    <p><strong>Start Date:</strong> ${startDate}</p>
    `,

    `
    <h2>3. Terms and Conditions</h2>
    <p>The parties agree to the following terms:</p>
    <ol>
      <li>Both parties will fulfill their obligations as specified in this agreement.</li>
      <li>Any changes to this agreement must be made in writing and signed by both parties.</li>
      <li>The contract will remain in effect until completion of the agreed services or termination as specified herein.</li>
    </ol>

    <h2>4. Termination</h2>
    <p>This contract may be terminated by either party with 30 days written notice.</p>
    `
  ]

  return pages
}

const printTable = () => {
  void printDocument({ bodyClass: 'printing-table-contracts', pageStyle: 'size: A4 landscape; margin: 12mm;' })
}

const printContract = async (contract: Contract) => {
  console.log('CONTRACT ', contract, contract.template_id);

  try {
    isPrintLoading.value = true

    // If the contract template is not loaded, fetch it
    if (!contract.contract_template && contract.template_id) {
      const template = await ContractTemplateApi.getById(contract.template_id)
      contract.contract_template = {
        id: template.id,
        name: template.name,
        type: template.type,
        cover_text: template.cover_text,
        content: template.content,
      }
    }

    contractToPrint.value = contract
    includeEmptyLastPage.value = false
    includeSignatureSection.value = true
    includePartyDetails.value = false
    isPrintDialogVisible.value = true
  } catch (err) {
    console.error('Failed to load contract template:', err)
    toast.warning(translate('pages.contracts.allContracts.allContracts.failedToLoadContractTemplateUsingDefaultContent'))
    contractToPrint.value = contract
    includeEmptyLastPage.value = false
    includeSignatureSection.value = true
    includePartyDetails.value = false
    isPrintDialogVisible.value = true
  } finally {
    isPrintLoading.value = false
  }
}

const executePrint = () => {
  if (!combinedPrintRef.value || !contractToPrint.value) return

  const printWindow = window.open('', '_blank', 'width=900,height=1100')
  if (!printWindow) {
    toast.warning(translate('common.messages.couldNotOpenPrintWindowPleaseAllowPopUpsForThisSite'))
    return
  }

  const styles = Array.from(document.querySelectorAll('style, link[rel="stylesheet"]'))
    .map(el => el.outerHTML)
    .join('\n')

  const printContent = combinedPrintRef.value.innerHTML

  printWindow.document.open()
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <title>${contractToPrint.value.contract_number} - ${contractToPrint.value.contract || contractToPrint.value.contract_template?.name || translate('pages.contracts.allContracts.allContracts.untitledContract')}</title>
        ${styles}
        <style>
          @media print {
            @page {
              size: A4;
              margin: 0 !important;
            }
            body {
              margin: 0;
              padding: 0;
              background: #fff;
              -webkit-print-color-adjust: exact !important;
              print-color-adjust: exact !important;
            }
            .contract-cover {
              position: relative !important;
              width: 210mm !important;
              height: 297mm !important;
              max-width: none !important;
              margin: 0 !important;
              box-shadow: none !important;
              page-break-after: always !important;
              break-after: page !important;
              display: flex !important;
            }
            .custom-cover-page {
              position: relative !important;
              width: 210mm !important;
              height: 297mm !important;
              margin: 0 !important;
              page-break-after: always !important;
              break-after: page !important;
              overflow: hidden !important;
            }
            .custom-cover-page img { width: 100% !important; height: 100% !important; object-fit: cover !important; display: block !important; }
            .contract-page-container {
              position: relative !important;
              width: 210mm !important;
              height: 296mm !important;
              max-height: 296mm !important;
              margin: 0 !important;
              padding: 16mm 18mm 12mm 18mm !important;
              box-shadow: none !important;
              page-break-after: always !important;
              break-after: page !important;
              box-sizing: border-box !important;
              display: flex !important;
              flex-direction: column !important;
              justify-content: space-between !important;
              overflow: hidden !important;
            }
            .empty-print-page {
              width: 210mm !important;
              height: 297mm !important;
              page-break-after: always !important;
              break-after: page !important;
              background: #ffffff !important;
              display: block !important;
            }
            /* Additional print styles for contract content */
            .contract-content {
              font-family: 'Vazirmatn', 'Noto Sans Arabic', Tahoma, sans-serif;
              line-height: 1.6;
              color: #333;
            }
            .contract-content h1,
            .contract-content h2,
            .contract-content h3 {
              color: #222;
              margin-top: 1.5em;
              margin-bottom: 0.5em;
            }
            .contract-content p {
              margin-bottom: 1em;
            }
            .contract-content ul,
            .contract-content ol {
              margin-bottom: 1em;
              padding-inline-start: 2em;
            }
            .contract-content table {
              width: 100%;
              border-collapse: collapse;
              margin: 1em 0;
            }
            .contract-content th,
            .contract-content td {
              border: 1px solid #ddd;
              padding: 8px;
              text-align: left;
            }
            .contract-content th {
              background-color: #f2f2f2;
            }
          }
        </style>
      </head>
      <body>
        <div class="multi-page-print-wrapper">
          ${printContent}
        </div>
        <script>
          window.onload = () => {
            const pages = document.querySelectorAll('.contract-page-container');
            pages.forEach(page => {
              let zoom = 1.0;
              while (page.scrollHeight > page.clientHeight && zoom > 0.80) {
                zoom -= 0.02;
                page.style.zoom = zoom;
              }
            });

            const images = document.images;
            let loaded = 0;
            if (images.length === 0) {
              triggerPrint();
              return;
            }
            const checkDone = () => {
              loaded++;
              if (loaded === images.length) triggerPrint();
            };
            for (let i = 0; i < images.length; i++) {
              if (images[i].complete) {
                checkDone();
              } else {
                images[i].addEventListener('load', checkDone);
                images[i].addEventListener('error', checkDone);
              }
            }
          };
          function triggerPrint() {
            setTimeout(() => {
              window.focus();
              window.print();
              window.close();
            }, 300);
          }
        <\/script>
      </body>
    </html>
  `)
  printWindow.document.close()
  isPrintDialogVisible.value = false
}

const addNew = (companyId?: number) => {
  dialogMode.value = 'add'
  const companyTemplate = templates.value.find(template => template.type_slug === 'company')
  selectedContract.value = {
    id: 0,
    contract_number: '',
    contract: '',
    status: 'active',
    start_date: new Date().toISOString().slice(0, 10),
    end_date: null,
    counterparty: '',
    is_cancelled: false,
    template_id: companyId ? (companyTemplate?.id || 0) : (templates.value[0]?.id || 0),
    company_id: companyId ?? null,
  }
  isDialogVisible.value = true
}

const editContractUI = (contract: Contract) => {
  dialogMode.value = 'edit'
  selectedContract.value = { ...contract }
  isDialogVisible.value = true
}

const contractName = (contract: Contract) => contract.contract || contract.contract_template?.name || translate('pages.contracts.allContracts.allContracts.unnamedContract')

const requestContractAction = (type: 'delete' | 'cancel', contract: Contract) => {
  pendingContractAction.value = { type, contract }
  isContractActionDialogVisible.value = true
}

const confirmContractAction = async () => {
  const action = pendingContractAction.value
  if (!action) return

  isContractActionDialogVisible.value = false
  try {
    if (action.type === 'delete') {
      await removeContract(action.contract.id)
      toast.success(translate('pages.contracts.allContracts.allContracts.contractDeleted'))
    }
    else {
      isCancelling.value = true
      await ContractApi.cancel(action.contract.id)
      await fetchContracts({ audience: props.contractAudience })
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
      await addContract({
        ...contractData,
        content: contractData.content || undefined,
      })
    } else if (selectedContract.value) {
      await editContract(selectedContract.value.id, contractData)
    }
    isDialogVisible.value = false
    toast.success(translate('pages.contracts.allContracts.allContracts.contractSaved'))
  } catch (err) {
    toast.error(translate('pages.contracts.allContracts.allContracts.failedToSaveContractPleaseTryAgain'))
  }
}

onMounted(async () => {
  await Promise.all([
    fetchContracts({ audience: props.contractAudience }),
    fetchTemplates(),
  ])
  templates.value = templates.value.filter(template =>
    props.contractAudience === 'employees'
      ? template.type_slug === 'employment'
      : props.contractAudience === 'customers'
        ? template.type_slug === 'customer'
        : template.type_slug === 'company',
  )
  const employeeId = Number(route.query.employeeId)
  if (props.contractAudience === 'employees' && route.query.print === '1' && employeeId) {
    const contract = contracts.value.find(item => item.employee_id === employeeId && !item.is_cancelled)
    if (contract) await printContract(contract)
    else toast.warning(translate('pages.contracts.allContracts.allContracts.noActiveEmploymentContractFound'))
  }
  const companyId = Number(route.query.companyId)
  if (route.query.action === 'create' && companyId) {
    addNew(companyId)
    await router.replace({ name: currentListRoute() })
  }
  const contractId = Number(route.query.contractId)
  if (route.query.action === 'print' && contractId) {
    const contract = contracts.value.find(item => item.id === contractId) ?? await ContractApi.getById(contractId)
    await printContract(contract)
    await router.replace({ name: currentListRoute() })
  }
  try {
    companySetting.value = (await SettingApi.getCompanyProfile()).data
  } catch {
    // Printing still works with neutral fallback company details.
  }
})
</script>

<template>
  <DataTablePageKpis :items="filtered" title="Contracts" description="See the current contract portfolio and spot records that need follow-up." icon="tabler-file-description" />
  <VCard>
    <VCardText>
      <VRow align="center" class="table-toolbar">
        <VCol cols="12" md="7" lg="9">
          <VTextField v-model="search" class="data-table-search" prepend-inner-icon="tabler-search" :placeholder="$t('pages.contracts.allContracts.allContracts.searchContracts')"
            density="compact" clearable hide-details />
        </VCol>
        <VCol cols="12" md="5" lg="3" class="table-toolbar-actions d-flex flex-nowrap justify-end gap-2">
          <DataTableFilterMenu :active="Boolean(filterCounterparty || filterStatus)" @reset="filterCounterparty = null; filterStatus = null">
            <VSelect v-model="filterCounterparty" :items="counterparties"
              :label="$t('pages.contracts.allContracts.allContracts.filterByCounterparty')" density="compact" clearable hide-details />
            <VSelect v-model="filterStatus" :items="statuses" :label="$t('common.messages.filterByStatus')"
              density="compact" clearable hide-details />
          </DataTableFilterMenu>
          <VBtn icon="tabler-printer" variant="text" class="flex-shrink-0" @click="printTable"><VIcon icon="tabler-printer" /><VTooltip activator="parent" location="top">{{ $t('pages.contracts.allContracts.allContracts.printTable') }}</VTooltip></VBtn>
          <VBtn color="primary" class="flex-shrink-0" @click="addNew" :loading="isLoading">
            <VIcon icon="tabler-plus" class="me-1" />
            {{ $t('pages.contracts.allContracts.allContracts.addContract') }}
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>

    <VAlert v-if="error" type="error" class="ma-4">
      {{ error }}
    </VAlert>

    <ResponsiveDataTable :headers="headers" :items="filtered" item-value="id" class="text-no-wrap" mobile-breakpoint="sm"
      :loading="isLoading" :loading-text="$t('pages.contracts.allContracts.allContracts.loadingContracts')" style="overflow-x: auto;">
      <template #item.contract="{ item }">
        {{ item.contract || item.contract_template?.name || $t('pages.contracts.allContracts.allContracts.untitledContract') }}
        <VChip v-if="item.is_cancelled" color="error" size="x-small" class="ml-2">
          {{ $t('common.statuses.cancelled') }}
        </VChip>
      </template>
      <template #item.counterparty="{ item }">
        {{ item.counterparty || $t('pages.contracts.allContracts.allContracts.unknownCounterparty') }}
      </template>
      <template #item.services="{ item }">
        {{ item.services?.map(service => service.type).join(', ') || '—' }}
      </template>
      <template #item.start_date="{ item }">
                  {{ formatLocalizedDate(item.start_date) }}
      </template>
      <template #item.status="{ item }">
        <VChip :color="getStatusColor(item.status)" size="small" class="font-weight-medium">
          {{ translateBackendValue(item.status || 'unknown') }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <FullDetailsButton :item="item" :title="item.contract_number || item.contract" />
        <VBtn v-if="!item.is_cancelled" icon="tabler-ban" variant="text" size="small" @click="cancelContract(item)"
          :loading="isCancelling" class="me-2" color="error"><VIcon icon="tabler-ban" /><VTooltip activator="parent" location="top">{{ $t('pages.contracts.allContracts.allContracts.cancelContract') }}</VTooltip></VBtn>
        <VBtn icon="tabler-edit" variant="text" size="small" @click="editContractUI(item)" class="me-2"
          ><VIcon icon="tabler-edit" /><VTooltip activator="parent" location="top">{{ $t('pages.contracts.allContracts.allContracts.editContract') }}</VTooltip></VBtn>
        <VBtn icon="tabler-trash" variant="text" size="small" @click="deleteContractUI(item)" class="me-2"
          ><VIcon icon="tabler-trash" /><VTooltip activator="parent" location="top">{{ $t('pages.contracts.allContracts.allContracts.deleteContract') }}</VTooltip></VBtn>
        <VBtn icon="tabler-printer" variant="text" size="small" @click="printContract(item)" :loading="isPrintLoading"
          ><VIcon icon="tabler-printer" /><VTooltip activator="parent" location="top">{{ $t('pages.contracts.allContracts.allContracts.printContract') }}</VTooltip></VBtn>
      </template>
    </ResponsiveDataTable>
  </VCard>
  <PrintableListReport :title="contractAudience === 'employees'
    ? $t('pages.contracts.allContracts.allContracts.employeeContractsReport')
    : contractAudience === 'customers'
      ? $t('pages.contracts.allContracts.allContracts.customerContractsReport')
      : 'Contract records'" :subtitle="$t('pages.contracts.allContracts.allContracts.filteredContractRecords')" :columns="headers.slice(0, -1).map(header => header.title)" :rows="filtered.map(contract => [contract.contract_number, contract.contract || contract.contract_template?.name, contract.type || contract.contract_template?.type, contract.counterparty, contract.services?.map(service => service.type).join(', ') || '—', contract.start_date, contract.status])" />

  <!-- Contract Form Dialog -->
  <VDialog v-model="isDialogVisible" max-width="600px" persistent>
    <VCard>
      <VCardTitle>
        <span class="headline">{{ dialogMode === 'add' ? $t('pages.contracts.allContracts.allContracts.addNewContract') : $t('pages.contracts.allContracts.allContracts.editContract') }}</span>
      </VCardTitle>

      <VCardText>
        <ContractForm :contract="selectedContract || undefined" :templates="templates" :audience="contractAudience" @submit="handleFormSubmit"
          @cancel="isDialogVisible = false" />
      </VCardText>
    </VCard>
  </VDialog>

  <VDialog v-model="isContractActionDialogVisible" max-width="460px">
    <VCard>
      <VCardTitle>{{ $t('pages.contracts.allContracts.allContracts.confirmContractAction') }}</VCardTitle>
      <VCardText>
        {{ pendingContractAction?.type === 'delete'
          ? $t('pages.contracts.allContracts.allContracts.deleteContractConfirmation', { contract: pendingContractAction ? contractName(pendingContractAction.contract) : '' })
          : $t('pages.contracts.allContracts.allContracts.cancelContractConfirmation', { contract: pendingContractAction ? contractName(pendingContractAction.contract) : '' }) }}
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn variant="tonal" color="secondary" @click="isContractActionDialogVisible = false">
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn :color="pendingContractAction?.type === 'delete' ? 'error' : 'warning'" @click="confirmContractAction">
          {{ pendingContractAction?.type === 'delete' ? $t('common.actions.delete') : $t('pages.contracts.allContracts.allContracts.cancelContract') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Print Options Dialog -->
  <VDialog v-model="isPrintDialogVisible" max-width="480px">
    <VCard>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="tabler-printer" />
        <span>{{ $t('pages.contracts.allContracts.allContracts.printContractOptions') }}</span>
      </VCardTitle>
      <VCardText>
        <p class="mb-4">
          {{ $t('pages.contracts.allContracts.allContracts.prepare') }} <strong>{{ contractToPrint?.contract || contractToPrint?.contract_template?.name ||
            $t('pages.contracts.allContracts.allContracts.untitledContract') }}</strong> {{ $t('pages.contracts.allContracts.allContracts.forA4PrintingTheDocumentWillIncludeTheCoverPageAndAllContractTerms') }}
        </p>
        <div class="d-flex flex-column gap-2">
          <VCheckbox v-model="includeSignatureSection" :label="$t('pages.contracts.allContracts.allContracts.includeFormalSignatureStampSectionAtTheEnd')"
            density="compact" hide-details />
          <VCheckbox v-model="includeCustomCover" :label="$t('pages.contracts.allContracts.allContracts.useCustomCoverPageFromSettings')" density="compact" hide-details />
          <VCheckbox v-model="includeCustomLetterhead" :label="$t('pages.contracts.allContracts.allContracts.useCustomLetterheadFromSettings')" density="compact" hide-details />
          <VCheckbox v-if="canShowPartyDetails" v-model="includePartyDetails" label="Include core details for both contract parties" density="compact" hide-details />
          <VCheckbox v-model="includeEmptyLastPage" :label="$t('pages.contracts.allContracts.allContracts.addALastFullyEmptyPage')" density="compact"
            hide-details />
        </div>
      </VCardText>
      <VCardActions class="justify-end px-4 pb-4">
        <VBtn variant="outlined" color="secondary" @click="isPrintDialogVisible = false">
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn color="primary" @click="executePrint">
          {{ $t('pages.contracts.allContracts.allContracts.printA4Document') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Hidden Printable Document Structure -->
  <div ref="combinedPrintRef" style="display: none;">
    <template v-if="contractToPrint">
      <div v-if="includeCustomCover && companySetting?.contract_cover_design_url" class="custom-cover-page">
        <img :src="companySetting.contract_cover_design_url" alt="" />
      </div>
      <ContractCover v-else :contractee-name="contractToPrint.counterparty || ''"
        :logo="company.logo"
        cover-design-url=""
        :contract-number="contractToPrint.contract_number || ''"
        :contract-title="contractToPrint.contract || contractToPrint.contract_template?.name || $t('pages.contracts.allContracts.allContracts.untitledContract')"
        :cover-description="contractToPrint.cover_text || contractToPrint.contract_template?.cover_text || ''"
        :contract-type="contractToPrint.type || contractToPrint.contract_template?.type || 'Service Agreement'"
        :contract-date="formatLocalizedDate(contractToPrint.start_date)" :company-website="company.website || ''"
        :company-address="company.address || ''" :company-email="company.email || ''"
        :company-number="company.phone || ''" />

      <ContractPageTemplate v-if="includePartyDetails && canShowPartyDetails"
        document-title="Contract parties"
        :document-number="contractToPrint.contract_number || ''"
        :company="{ ...company, letterheadUrl: includeCustomLetterhead ? company.letterheadUrl : undefined }"
        :party-details="partyDetailsFor(contractToPrint, company)"
        :contractee-name="contractToPrint.counterparty || ''"
        :page-number-string="$t('pages.contracts.allContracts.allContracts.pageNumber', { page: 1, total: getContractPagesHtml(contractToPrint).length + 1 })"
        :footer-text="$t('pages.contracts.allContracts.allContracts.confidentialAllRightsReserved')"
        class="contract-content" />

      <ContractPageTemplate v-for="(pageHtml, idx) in getContractPagesHtml(contractToPrint)" :key="idx"
        :document-number="contractToPrint.contract_number || ''"
        :document-title="contractToPrint.contract || contractToPrint.contract_template?.name || $t('pages.contracts.allContracts.allContracts.untitledContract')"
        :company="{ ...company, letterheadUrl: includeCustomLetterhead ? company.letterheadUrl : undefined }" :html-content="pageHtml"
        :page-number-string="$t('pages.contracts.allContracts.allContracts.pageNumber', { page: idx + (includePartyDetails && canShowPartyDetails ? 2 : 1), total: getContractPagesHtml(contractToPrint).length + (includePartyDetails && canShowPartyDetails ? 1 : 0) })"
        :show-signature="includeSignatureSection && idx === getContractPagesHtml(contractToPrint).length - 1"
        :contractee-name="contractToPrint.counterparty || ''" :footer-text="$t('pages.contracts.allContracts.allContracts.confidentialAllRightsReserved')"
        class="contract-content" />

      <div v-if="includeEmptyLastPage" class="empty-print-page" style="width: 210mm; height: 297mm; background: white;">
      </div>
    </template>
  </div>
</template>

<script lang="ts">
export default {
  methods: {
    getStatusColor(status: string) {
      if (!status) return 'default'
      const statusMap: Record<string, string> = {
        'active': 'success',
        'draft': 'info',
        'pending': 'warning',
        'expired': 'error',
        'terminated': 'secondary',
        'cancelled': 'error',
      }
      return statusMap[status.toLowerCase()] || 'default'
    }
  }
}
</script>
