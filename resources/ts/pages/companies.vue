<script setup lang="ts">
import CompanyApi, { type Company, type CompanyCreateData } from '@/api/company'
import ContractApi, { type Contract } from '@/api/contracts'
import ContractTemplateApi, { type ContractTemplate } from '@/api/contractTemplate'
import ContractForm, { type ContractFormPayload } from '@/components/ContractForm.vue'
import ContractDocumentPrinter from '@/components/contract/ContractDocumentPrinter.vue'
import { formatLocalizedDate } from '@/utils/localization'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'

const router = useRouter()
const toast = useToast()

const search = ref('')
const printDialogVisible = ref(false)
const contractToPrint = ref<Contract | null>(null)
const isLoading = ref(false)
const companies = ref<Company[]>([])
const companyContracts = ref<Contract[]>([])
const companyTemplates = ref<ContractTemplate[]>([])

const isDialogVisible = ref(false)
const dialogMode = ref<'add' | 'edit'>('add')
const formRef = ref()
const saving = ref(false)
const isContractDialogVisible = ref(false)
const contractDialogMode = ref<'add' | 'edit'>('add')
const selectedContract = ref<Contract | null>(null)
const selectedContractCompany = ref<Company | null>(null)
const savingContract = ref(false)

interface CompanyForm {
  legal_name: string
  trade_name: string | null
  registration_number: string | null
  tax_number: string | null
  national_id: string | null
  legal_form: string | null
  email: string | null
  phone: string | null
  website: string | null
  country: string | null
  province: string | null
  city: string | null
  postal_code: string | null
  address_line: string | null
  industry: string | null
  description: string | null
  representative_name: string | null
  representative_title: string | null
  representative_phone: string | null
  representative_email: string | null
}

const emptyForm = (): CompanyForm => ({
  legal_name: '',
  trade_name: null,
  registration_number: null,
  tax_number: null,
  national_id: null,
  legal_form: null,
  email: null,
  phone: null,
  website: null,
  country: null,
  province: null,
  city: null,
  postal_code: null,
  address_line: null,
  industry: null,
  description: null,
  representative_name: null,
  representative_title: null,
  representative_phone: null,
  representative_email: null,
})

const form = ref<CompanyForm>(emptyForm())
const selectedCompanyId = ref<number | null>(null)
const companyPendingDeletion = ref<Company | null>(null)

const headers = [
  { title: 'Legal Name', key: 'legal_name', sortable: true },
  { title: 'Trade Name', key: 'trade_name', sortable: true },
  { title: 'Registration No.', key: 'registration_number', sortable: false },
  { title: 'Tax No.', key: 'tax_number', sortable: false },
  { title: 'City', key: 'city', sortable: true },
  { title: 'Representative', key: 'representative_name', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false },
]

const contractHeaders = [
  { title: 'Contract', key: 'contract', sortable: true },
  { title: 'Number', key: 'contract_number', sortable: true },
  { title: 'Company', key: 'company_name', sortable: true },
  { title: 'Start Date', key: 'start_date', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false },
]

// company_id → most relevant (active first, then newest) contract
const latestContractFor = (companyId: number): Contract | null => {
  const list = companyContracts.value
    .filter(contract => contract.company_id === companyId)
    .sort((a, b) => {
      const activeDelta = Number(a.status === 'active') - Number(b.status === 'active')
      if (activeDelta !== 0) return -activeDelta
      return Number(b.id) - Number(a.id)
    })

  return list[0] ?? null
}

const companyNameFor = (companyId: number | null | undefined) =>
  companies.value.find(company => company.id === companyId)?.legal_name || '—'

const contractStatusColor = (status: string) => ({
  'active': 'success',
  'draft': 'info',
  'pending': 'warning',
  'expired': 'error',
  'terminated': 'secondary',
  'cancelled': 'error',
}[status] || 'default')

const formattedDate = (value: string | null | undefined) => {
  if (!value) return '—'
  return formatLocalizedDate(value)
}

const printCompanyContract = (contract: Contract) => {
  contractToPrint.value = contract
  printDialogVisible.value = true
}

const filtered = computed(() => {
  const term = search.value.trim().toLowerCase()
  if (!term) return companies.value

  return companies.value.filter(company =>
    [company.legal_name, company.trade_name, company.registration_number, company.tax_number]
      .some(value => (value || '').toLowerCase().includes(term)))
})

const fetchCompanies = async () => {
  try {
    isLoading.value = true
    const [companyResult, contractsResult, templatesResult] = await Promise.allSettled([
      CompanyApi.getAll({ include_inactive: true }),
      ContractApi.getAll({ type_slug: 'company' }),
      ContractTemplateApi.getAll(),
    ])
    if (companyResult.status === 'rejected') throw companyResult.reason

    companies.value = companyResult.value
    companyContracts.value = contractsResult.status === 'fulfilled' ? contractsResult.value : []
    companyTemplates.value = templatesResult.status === 'fulfilled'
      ? templatesResult.value.filter(template => template.type_slug === 'company')
      : []
  } catch (error) {
    console.error('Failed to load companies:', error)
    toast.error('Failed to load companies.')
  } finally {
    isLoading.value = false
  }
}

onMounted(fetchCompanies)

const openAdd = () => {
  dialogMode.value = 'add'
  selectedCompanyId.value = null
  form.value = emptyForm()
  isDialogVisible.value = true
}

const openEdit = (company: Company) => {
  dialogMode.value = 'edit'
  selectedCompanyId.value = company.id
  const next = emptyForm()
  const source = company as unknown as Record<string, unknown>
  for (const key of Object.keys(next) as (keyof CompanyForm)[]) {
    const value = source[key]
    if (value !== undefined && value !== null) {
      (next as unknown as Record<string, unknown>)[key] = value
    }
  }
  form.value = next
  isDialogVisible.value = true
}

const submit = async () => {
  const validation = await formRef.value?.validate()
  if (validation?.valid === false) return

  try {
    saving.value = true
    const payload: CompanyCreateData = { ...form.value }

    if (dialogMode.value === 'add') {
      const created = await CompanyApi.create(payload)
      companies.value.unshift(created)
      toast.success('Company created.')
    } else if (selectedCompanyId.value) {
      const updated = await CompanyApi.update(selectedCompanyId.value, payload)
      const index = companies.value.findIndex(company => company.id === selectedCompanyId.value)
      if (index !== -1) companies.value[index] = updated
      toast.success('Company updated.')
    }
    isDialogVisible.value = false
  } catch (error) {
    console.error('Failed to save company:', error)
    toast.error('Failed to save the company.')
  } finally {
    saving.value = false
  }
}

const confirmDelete = async () => {
  const target = companyPendingDeletion.value
  if (!target) return
  companyPendingDeletion.value = null

  try {
    await CompanyApi.delete(target.id)
    companies.value = companies.value.filter(company => company.id !== target.id)
    toast.success('Company deleted.')
  } catch {
    await fetchCompanies()
    toast.warning('This company is referenced by contracts and has been deactivated instead of deleted.')
  }
}

const fullAddress = (company: Company) =>
  [company.address_line, company.city, company.province, company.country].filter(Boolean).join(', ')

const createContract = (company: Company) => {
  contractDialogMode.value = 'add'
  selectedContractCompany.value = company
  selectedContract.value = {
    id: 0,
    contract_number: '',
    contract: '',
    status: 'active',
    start_date: new Date().toISOString().slice(0, 10),
    end_date: null,
    counterparty: company.legal_name,
    is_cancelled: false,
    template_id: companyTemplates.value[0]?.id || null,
    company_id: company.id,
  }
  isContractDialogVisible.value = true
}

const editContract = (contract: Contract) => {
  const company = companies.value.find(item => item.id === contract.company_id)
  if (!company) {
    toast.error('The company for this contract could not be found.')
    return
  }

  contractDialogMode.value = 'edit'
  selectedContractCompany.value = company
  selectedContract.value = { ...contract }
  isContractDialogVisible.value = true
}

const saveContract = async (payload: ContractFormPayload) => {
  const company = selectedContractCompany.value
  if (!company) return

  try {
    savingContract.value = true
    const contractPayload = { ...payload, company_id: company.id }
    const saved = contractDialogMode.value === 'add'
      ? await ContractApi.create(contractPayload)
      : await ContractApi.update(selectedContract.value!.id, contractPayload)

    const index = companyContracts.value.findIndex(contract => contract.id === saved.id)
    if (index === -1) companyContracts.value.unshift(saved)
    else companyContracts.value[index] = saved

    isContractDialogVisible.value = false
    toast.success(contractDialogMode.value === 'add' ? 'Company contract created.' : 'Company contract updated.')
  } catch (error) {
    console.error('Failed to save company contract:', error)
    toast.error('Failed to save the company contract.')
  } finally {
    savingContract.value = false
  }
}

</script>

<template>
  <DataTablePageKpis :items="filtered" title="Companies" description="Manage counterparties, their details, and the agreements connected to them." icon="tabler-building" />
  <VCard>
    <VCardText>
      <VRow align="center" class="table-toolbar">
        <VCol cols="12" md="6" lg="4">
          <VTextField v-model="search" class="data-table-search" prepend-inner-icon="tabler-search" placeholder="Search companies"
            density="compact" clearable hide-details />
        </VCol>
        <VCol cols="12" md="6" lg="8" class="d-flex justify-end gap-2">
          <VBtn color="primary" prepend-icon="tabler-plus" @click="openAdd">
            Add Company
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>

    <ResponsiveDataTable :headers="headers" :items="filtered" item-value="id" class="text-no-wrap" :loading="isLoading"
      loading-text="Loading companies..." style="overflow-x: auto;"
      no-data-text="No companies yet — add your first counterparty.">
      <template #no-data>
        No companies yet — add your first counterparty.
      </template>
      <template #item.legal_name="{ item }">
        <div class="d-flex flex-column py-1">
          <span class="font-weight-medium text-no-wrap">{{ item.legal_name }}</span>
          <span v-if="fullAddress(item)" class="text-caption text-medium-emphasis text-truncate"
            style="max-inline-size: 280px;">
            {{ fullAddress(item) }}
          </span>
        </div>
      </template>
      <template #item.trade_name="{ item }">
        {{ item.trade_name || '—' }}
      </template>
      <template #item.representative_name="{ item }">
        {{ item.representative_name ? `${item.representative_name}${item.representative_title ? ` —
        ${item.representative_title}` : ''}` : '—' }}
      </template>
      <template #item.actions="{ item }">
        <VBtn icon="tabler-file-plus" variant="text" size="small" color="primary" class="me-2" @click="createContract(item)"><VIcon icon="tabler-file-plus" /><VTooltip activator="parent" location="top">Create contract</VTooltip></VBtn>
        <VBtn icon="tabler-edit" variant="text" size="small" class="me-2" @click="openEdit(item)"><VIcon icon="tabler-edit" /><VTooltip activator="parent" location="top">Edit company</VTooltip></VBtn>
        <VBtn icon="tabler-trash" variant="text" size="small" color="error" @click="companyPendingDeletion = item"><VIcon icon="tabler-trash" /><VTooltip activator="parent" location="top">Delete company</VTooltip></VBtn>
      </template>
    </ResponsiveDataTable>
  </VCard>
  <ContractDocumentPrinter v-model="printDialogVisible" :contract="contractToPrint" />

  <!-- Company contracts: every contract whose template is a company type -->
  <VCard class="mt-6">
    <VCardItem>
      <template #prepend>
        <VIcon icon="tabler-building" size="24" class="text-primary mr-2" />
      </template>
      <VCardTitle>Company Contracts</VCardTitle>
      <VCardSubtitle>Contracts issued with company-type templates</VCardSubtitle>
      <template #append>
        <VBtn variant="tonal" color="primary" size="small" prepend-icon="tabler-plus" :disabled="!filtered.length"
          @click="createContract(filtered[0])">
          New Company Contract
        </VBtn>
      </template>
    </VCardItem>

    <VDivider />

    <ResponsiveDataTable :headers="contractHeaders" :items="companyContracts" item-value="id" class="text-no-wrap"
      :loading="isLoading" loading-text="Loading company contracts..." style="overflow-x: auto;"
      no-data-text="No company contracts yet — create one from a company row above.">
      <template #no-data>
        No company contracts yet — create one from a company row above.
      </template>
      <template #item.contract="{ item }">
        {{ item.contract || item.contract_template?.name || 'Untitled contract' }}
      </template>
      <template #item.contract_number="{ item }">
        {{ item.contract_number || '—' }}
      </template>
      <template #item.company_name="{ item }">
        {{ companyNameFor(item.company_id) }}
      </template>
      <template #item.start_date="{ item }">
        {{ formattedDate(item.start_date) }}
      </template>
      <template #item.status="{ item }">
        <VChip :color="contractStatusColor(item.status)" size="small" class="font-weight-medium text-capitalize">
          {{ item.status }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <VBtn icon="tabler-printer" variant="text" size="small" @click="printCompanyContract(item)"><VIcon icon="tabler-printer" /><VTooltip activator="parent" location="top">Print contract</VTooltip></VBtn>
        <VBtn icon="tabler-edit" variant="text" size="small" @click="editContract(item)"><VIcon icon="tabler-edit" /><VTooltip activator="parent" location="top">Edit contract</VTooltip></VBtn>
      </template>
    </ResponsiveDataTable>
  </VCard>

  <!-- Create / Edit Dialog -->
  <VDialog v-model="isDialogVisible" max-width="760px" persistent scrollable>
    <VCard>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="tabler-building" />
        {{ dialogMode === 'add' ? 'Add Company' : 'Edit Company' }}
      </VCardTitle>

      <VCardText>
        <VForm ref="formRef" class="app-form" @submit.prevent="submit">
          <p class="app-form__section">Identity</p>
          <VRow>
            <VCol cols="12" md="6">
              <VTextField v-model="form.legal_name" label="Legal Name *"
                :rules="[v => !!v?.trim() || 'Legal name is required']" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="form.trade_name" label="Trade Name" />
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <VTextField v-model="form.registration_number" label="Registration Number" />
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <VTextField v-model="form.tax_number" label="Tax Number" />
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <VTextField v-model="form.national_id" label="National ID" />
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <VTextField v-model="form.legal_form" label="Legal Form (LLC, Ltd…)" />
            </VCol>
          </VRow>

          <p class="app-form__section mt-4">Contact & Address</p>
          <VRow>
            <VCol cols="12" sm="4">
              <VTextField v-model="form.email" label="Email" type="email" />
            </VCol>
            <VCol cols="12" sm="4">
              <VTextField v-model="form.phone" label="Phone" />
            </VCol>
            <VCol cols="12" sm="4">
              <VTextField v-model="form.website" label="Website" />
            </VCol>
            <!-- Keep the short fields readable at the dialog's desktop width. -->
            <VCol cols="12" sm="6" md="4">
              <VTextField v-model="form.address_line" label="Address Line" />
            </VCol>
            <VCol cols="12" sm="6" md="2">
              <VTextField v-model="form.city" label="City" />
            </VCol>
            <VCol cols="12" sm="6" md="2">
              <VTextField v-model="form.province" label="Province" />
            </VCol>
            <VCol cols="12" sm="6" md="2">
              <VTextField v-model="form.postal_code" label="Postal" />
            </VCol>
            <VCol cols="12" sm="6" md="2">
              <VTextField v-model="form.country" label="Country" />
            </VCol>
          </VRow>

          <p class="app-form__section mt-4">Business Information</p>
          <VRow>
            <VCol cols="12" sm="4">
              <VTextField v-model="form.industry" label="Industry" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="form.description" label="Description" rows="2" auto-grow />
            </VCol>
          </VRow>

          <p class="app-form__section mt-4">Authorized Representative</p>
          <VRow>
            <VCol cols="12" sm="6" md="3">
              <VTextField v-model="form.representative_name" label="Name" />
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <VTextField v-model="form.representative_title" label="Title" />
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <VTextField v-model="form.representative_phone" label="Phone" />
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <VTextField v-model="form.representative_email" label="Email" type="email" />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardActions class="justify-end px-6 pb-4">
        <VBtn variant="outlined" color="secondary" @click="isDialogVisible = false">Cancel</VBtn>
        <VBtn color="primary" :loading="saving" @click="submit">
          {{ dialogMode === 'add' ? 'Create' : 'Save Changes' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Company contract dialog -->
  <VDialog v-model="isContractDialogVisible" max-width="600px" persistent>
    <VCard>
      <VCardTitle>
        {{ contractDialogMode === 'add' ? 'Create Company Contract' : 'Edit Company Contract' }}
      </VCardTitle>
      <VCardText>
        <ContractForm :contract="selectedContract || undefined" :templates="companyTemplates" audience="companies"
          @submit="saveContract" @cancel="isContractDialogVisible = false" />
      </VCardText>
      <VProgressLinear v-if="savingContract" indeterminate color="primary" />
    </VCard>
  </VDialog>

  <!-- Delete confirmation -->
  <VDialog :model-value="companyPendingDeletion !== null" max-width="440px"
    @update:model-value="companyPendingDeletion = null">
    <VCard>
      <VCardTitle>Delete company</VCardTitle>
      <VCardText>
        Delete <strong>{{ companyPendingDeletion?.legal_name }}</strong>?
        Companies referenced by existing contracts are deactivated instead of deleted so historical documents remain
        valid.
      </VCardText>
      <VCardActions class="justify-end">
        <VBtn variant="text" color="secondary" @click="companyPendingDeletion = null">Cancel</VBtn>
        <VBtn color="error" @click="confirmDelete">Delete</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
