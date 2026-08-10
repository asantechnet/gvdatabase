<script setup lang="ts">
import ContractApi from '@/api/contracts'
import ContractForm from '@/components/ContractForm.vue'
import { useContracts } from '@/composables/useContracts'
import { computed, onMounted, ref } from 'vue'
import { VAlert } from 'vuetify/components'
import ContractCover from './templates/contract_cover_template.vue'
import ContractTemplate, { type CompanyDetails } from './templates/contract_page_template.vue'

interface Contract {
  id: number
  contract_number: string
  contract: string
  status: "Active" | "Expired" | "Pending"
  start_date: string
  counterparty: string
  is_cancelled: boolean
  template_id: number
  contract_template?: {
    id: number
    name: string
    type: string
    content: any
    cover_text: string | null
  }
}

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
const contractToPrint = ref<Contract | null>(null)
const combinedPrintRef = ref<HTMLElement | null>(null)
const isPrintLoading = ref(false)
const isCancelling = ref(false)

const counterparties = computed(() => [...new Set(contracts.value.map(c => c.counterparty).filter(c => c))])
const statuses = ['Active', 'Expired', 'Pending']

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
  { title: 'Contract Number', key: 'contract_number', sortable: true },
  { title: 'Contract', key: 'contract', sortable: true },
  { title: 'Counterparty', key: 'counterparty', sortable: true },
  { title: 'Start Date', key: 'start_date', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false },
]

const sampleCompany: CompanyDetails = {
  brandName: 'Acme Global Services',
  slogan: 'Excellence in every contract',
  address: '100 Innovation Way, Tech City, TC 90210',
  phone: '+1 (555) 019-2834',
  email: 'legal@acmeglobal.com',
  website: 'www.acmeglobal.com'
}

const deltaToHtml = (delta: any): string => {
  if (!delta || !delta.ops) return ''

  return delta.ops.map((op: any) => {
    if (typeof op.insert !== 'string') return ''

    let content = op.insert
    if (op.attributes) {
      if (op.attributes.bold) content = `<strong>${content}</strong>`
      if (op.attributes.italic) content = `<em>${content}</em>`
      if (op.attributes.underline) content = `<u>${content}</u>`
      if (op.attributes.header) content = `<h${op.attributes.header}>${content}</h${op.attributes.header}>`
      if (op.attributes.list === 'bullet') content = `<li>${content}</li>`
      if (op.attributes.list === 'ordered') content = `<li>${content}</li>`
      if (op.attributes.link) content = `<a href="${op.attributes.link}" target="_blank">${content}</a>`
    }

    // Handle newlines
    if (content === '\n') {
      if (op.attributes && op.attributes.header) return `</h${op.attributes.header}>`
      if (op.attributes && op.attributes.list === 'bullet') return '</ul>'
      if (op.attributes && op.attributes.list === 'ordered') return '</ol>'
      return '<br>'
    }

    return content
  }).join('')
}

const getContractPagesHtml = (contract: Contract): string[] => {
  // Use the actual template content if available
  if (contract.contract_template?.content) {
    try {
      // If content is in Delta format (Quill)
      if (typeof contract.contract_template.content === 'object' && contract.contract_template.content.ops) {
        const html = deltaToHtml(contract.contract_template.content)
        return [html]
      }
      // If content is already HTML
      if (typeof contract.contract_template.content === 'string') {
        return [contract.contract_template.content]
      }
    } catch (e) {
      console.error('Error parsing template content:', e)
    }
  }

  // Fallback to default content if no template content is available
  const startDate = new Date(contract.start_date).toLocaleDateString()
  const contractName = contract.contract || contract.contract_template?.name || 'Untitled Contract'
  const pages = [
    `
    <h1>Contract Agreement</h1>
    <p>This agreement is made between <strong>${sampleCompany.brandName}</strong> and <strong>${contract.counterparty}</strong> on <strong>${startDate}</strong>.</p>

    <h2>1. Parties</h2>
    <p><strong>Service Provider:</strong> ${sampleCompany.brandName}, located at ${sampleCompany.address}</p>
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
  window.print()
}

const printContract = async (contract: Contract) => {
  console.log('CONTRACT ', contract, contract.template_id);

  try {
    isPrintLoading.value = true

    // If the contract template is not loaded, fetch it
    if (!contract.contract_template) {
      const response = await ContractApi.getTemplateById(contract.template_id)

      if (response.success && response.data) {
        contract.contract_template = response.data
      }
    }

    contractToPrint.value = contract
    includeEmptyLastPage.value = false
    includeSignatureSection.value = true
    isPrintDialogVisible.value = true
  } catch (err) {
    console.error('Failed to load contract template:', err)
    alert('Failed to load contract template. Using default content.')
    contractToPrint.value = contract
    includeEmptyLastPage.value = false
    includeSignatureSection.value = true
    isPrintDialogVisible.value = true
  } finally {
    isPrintLoading.value = false
  }
}

const executePrint = () => {
  if (!combinedPrintRef.value || !contractToPrint.value) return

  const printWindow = window.open('', '_blank', 'width=900,height=1100')
  if (!printWindow) {
    alert("Could not open print window. Please allow pop-ups for this site.")
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
        <title>${contractToPrint.value.contract_number} - ${contractToPrint.value.contract || contractToPrint.value.contract_template?.name || 'Untitled Contract'}</title>
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
              font-family: Arial, sans-serif;
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
              padding-left: 2em;
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

const addNew = () => {
  dialogMode.value = 'add'
  selectedContract.value = {
    id: 0,
    contract_number: '',
    contract: '',
    status: 'Active',
    start_date: new Date().toISOString().substr(0, 10),
    counterparty: '',
    is_cancelled: false,
    template_id: 0
  }
  isDialogVisible.value = true
}

const editContractUI = (contract: Contract) => {
  dialogMode.value = 'edit'
  selectedContract.value = { ...contract }
  isDialogVisible.value = true
}

const deleteContractUI = async (contract: Contract) => {
  const contractName = contract.contract || contract.contract_template?.name || 'this contract'
  if (confirm(`Are you sure you want to delete ${contractName}?`)) {
    try {
      await removeContract(contract.id)
    } catch (err) {
      alert('Failed to delete contract. Please try again.')
    }
  }
}

const cancelContract = async (contract: Contract) => {
  const contractName = contract.contract || contract.contract_template?.name || 'this contract'
  if (confirm(`Are you sure you want to cancel ${contractName}? This action cannot be undone.`)) {
    try {
      isCancelling.value = true
      await ContractApi.cancel(contract.id)
      await fetchContracts()
    } catch (err) {
      console.error('Failed to cancel contract:', err)
      alert('Failed to cancel contract. Please try again.')
    } finally {
      isCancelling.value = false
    }
  }
}

const handleFormSubmit = async (contractData: {
  template_id: number
  status: 'Active' | 'Expired' | 'Pending'
  start_date: string
  counterparty: string
}) => {
  try {
    if (dialogMode.value === 'add') {
      await addContract(contractData)
    } else if (selectedContract.value) {
      await editContract(selectedContract.value.id, contractData)
    }
    isDialogVisible.value = false
  } catch (err) {
    alert('Failed to save contract. Please try again.')
  }
}

onMounted(() => {
  fetchContracts()
  fetchTemplates()
})
</script>

<template>
  <VCard>
    <VCardText>
      <VRow align="center">
        <VCol cols="12" md="4">
          <VTextField v-model="search" prepend-inner-icon="tabler-search" placeholder="Search contracts..."
            density="compact" clearable hide-details />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect v-model="filterCounterparty" :items="counterparties" label="Filter by Counterparty" density="compact"
            clearable hide-details />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect v-model="filterStatus" :items="statuses" label="Filter by Status" density="compact" clearable
            hide-details />
        </VCol>
        <VCol cols="12" md="2" class="text-end d-flex gap-2 justify-end">
          <VBtn icon="tabler-printer" variant="text" title="Print table" @click="printTable" />
          <VBtn color="primary" @click="addNew" :loading="isLoading">
            <VIcon icon="tabler-plus" class="me-1" />
            Add Contract
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>

    <VAlert v-if="error" type="error" class="ma-4">
      {{ error }}
    </VAlert>

    <VDataTable :headers="headers" :items="filtered" item-value="id" class="text-no-wrap" mobile-breakpoint="sm"
      :loading="isLoading" loading-text="Loading contracts..." style="overflow-x: auto;">
      <template #item.contract="{ item }">
        {{ item.contract || item.contract_template?.name || 'Untitled Contract' }}
        <VChip v-if="item.is_cancelled" color="error" size="x-small" class="ml-2">
          Cancelled
        </VChip>
      </template>
      <template #item.counterparty="{ item }">
        {{ item.counterparty || 'Unknown Counterparty' }}
      </template>
      <template #item.start_date="{ item }">
        {{ item.start_date ? new Date(item.start_date).toLocaleDateString() : 'N/A' }}
      </template>
      <template #item.status="{ item }">
        <VChip :color="getStatusColor(item.status)" size="small" class="font-weight-medium">
          {{ item.status || 'Unknown' }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <VBtn v-if="!item.is_cancelled" icon="tabler-ban" variant="text" size="small" @click="cancelContract(item)"
          :loading="isCancelling" class="me-2" title="Cancel contract" color="error" />
        <VBtn icon="tabler-edit" variant="text" size="small" @click="editContractUI(item)" class="me-2"
          title="Edit contract" />
        <VBtn icon="tabler-trash" variant="text" size="small" @click="deleteContractUI(item)" class="me-2"
          title="Delete contract" />
        <VBtn icon="tabler-printer" variant="text" size="small" @click="printContract(item)" :loading="isPrintLoading"
          title="Print contract" />
      </template>
    </VDataTable>
  </VCard>

  <!-- Contract Form Dialog -->
  <VDialog v-model="isDialogVisible" max-width="600px" persistent>
    <VCard>
      <VCardTitle>
        <span class="headline">{{ dialogMode === 'add' ? 'Add New Contract' : 'Edit Contract' }}</span>
      </VCardTitle>

      <VCardText>
        <ContractForm :contract="selectedContract || undefined" :templates="templates" @submit="handleFormSubmit"
          @cancel="isDialogVisible = false" />
      </VCardText>
    </VCard>
  </VDialog>

  <!-- Print Options Dialog -->
  <VDialog v-model="isPrintDialogVisible" max-width="480px">
    <VCard>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="tabler-printer" />
        <span>Print Contract Options</span>
      </VCardTitle>
      <VCardText>
        <p class="mb-4">
          Prepare <strong>{{ contractToPrint?.contract || contractToPrint?.contract_template?.name ||
            'UntitledContract' }}</strong> for A4 printing. The document will include the cover
          page and all contract terms.
        </p>
        <div class="d-flex flex-column gap-2">
          <VCheckbox v-model="includeSignatureSection" label="Include formal signature & stamp section at the end"
            density="compact" hide-details />
          <VCheckbox v-model="includeEmptyLastPage" label="Add a last fully empty page" density="compact"
            hide-details />
        </div>
      </VCardText>
      <VCardActions class="justify-end px-4 pb-4">
        <VBtn variant="outlined" color="secondary" @click="isPrintDialogVisible = false">
          Cancel
        </VBtn>
        <VBtn color="primary" @click="executePrint">
          Print A4 Document
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Hidden Printable Document Structure -->
  <div ref="combinedPrintRef" style="display: none;">
    <template v-if="contractToPrint">
      <ContractCover :contractee-name="contractToPrint.counterparty"
        :contract-number="contractToPrint.contract_number"
        :contract-title="contractToPrint.contract || contractToPrint.contract_template?.name || 'Untitled Contract'"
        :contract-type="contractToPrint.contract_template?.type || 'Service Agreement'"
        :contract-date="new Date(contractToPrint.start_date).toLocaleDateString()" company-website="www.acmeglobal.com"
        company-address="100 Innovation Way, Tech City, TC 90210" company-email="legal@acmeglobal.com"
        company-number="+1 (555) 019-2834" />

      <ContractTemplate v-for="(pageHtml, idx) in getContractPagesHtml(contractToPrint)" :key="idx"
        :document-number="contractToPrint.contract_number"
        :document-title="contractToPrint.contract || contractToPrint.contract_template?.name || 'Untitled Contract'"
        :company="sampleCompany" :html-content="pageHtml"
        :page-number-string="`Page ${idx + 1} of ${getContractPagesHtml(contractToPrint).length}`"
        :show-signature="includeSignatureSection && idx === getContractPagesHtml(contractToPrint).length - 1"
        :contractee-name="contractToPrint.counterparty" footer-text="Confidential - All Rights Reserved"
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
        'Active': 'success',
        'Expired': 'error',
        'Pending': 'warning'
      }
      return statusMap[status] || 'default'
    }
  }
}
</script>
