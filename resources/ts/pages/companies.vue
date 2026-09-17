<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import CompanyApi, { type Company, type CompanyCreateData } from '@/api/company'
import ContractApi, { type Contract } from '@/api/contracts'
import ContractTemplateApi, { type ContractTemplate } from '@/api/contractTemplate'
import ContractForm, { type ContractFormPayload } from '@/components/ContractForm.vue'
import ContractDocumentPrinter from '@/components/contract/ContractDocumentPrinter.vue'
import { formatLocalizedDate } from '@/utils/localization'

const router = useRouter()
const toast = useToast()
const { t } = useI18n()
const tr = (key: string, values?: Record<string, unknown>) => t(`pages.companies.${key}`, values ?? {})

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

const headers = computed(() => [
  { title: tr('legalName'), key: 'legal_name', sortable: true },
  { title: tr('tradeName'), key: 'trade_name', sortable: true },
  { title: tr('registrationNumber'), key: 'registration_number', sortable: false },
  { title: tr('taxNumber'), key: 'tax_number', sortable: false },
  { title: tr('city'), key: 'city', sortable: true },
  { title: tr('representative'), key: 'representative_name', sortable: false },
  { title: t('common.messages.actions'), key: 'actions', sortable: false },
])

const contractHeaders = computed(() => [
  { title: t('common.messages.contract'), key: 'contract', sortable: true },
  { title: tr('number'), key: 'contract_number', sortable: true },
  { title: tr('company'), key: 'company_name', sortable: true },
  { title: t('common.fields.startDate'), key: 'start_date', sortable: true },
  { title: t('common.fields.status'), key: 'status', sortable: true },
  { title: t('common.messages.actions'), key: 'actions', sortable: false },
])

// company_id → most relevant (active first, then newest) contract
const latestContractFor = (companyId: number): Contract | null => {
  const list = companyContracts.value
    .filter(contract => contract.company_id === companyId)
    .sort((a, b) => {
      const activeDelta = Number(a.status === 'active') - Number(b.status === 'active')
      if (activeDelta !== 0)
        return -activeDelta

      return Number(b.id) - Number(a.id)
    })

  return list[0] ?? null
}

const companyNameFor = (companyId: number | null | undefined) =>
  companies.value.find(company => company.id === companyId)?.legal_name || tr('notAvailable')

const contractStatusColor = (status: string) => ({
  active: 'success',
  draft: 'info',
  pending: 'warning',
  expired: 'error',
  terminated: 'secondary',
  cancelled: 'error',
}[status] || 'default')

const formattedDate = (value: string | null | undefined) => {
  if (!value)
    return '—'

  return formatLocalizedDate(value)
}

const printCompanyContract = (contract: Contract) => {
  contractToPrint.value = contract
  printDialogVisible.value = true
}

const filtered = computed(() => {
  const term = search.value.trim().toLowerCase()
  if (!term)
    return companies.value

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

    if (companyResult.status === 'rejected')
      throw companyResult.reason

    companies.value = companyResult.value
    companyContracts.value = contractsResult.status === 'fulfilled' ? contractsResult.value : []
    companyTemplates.value = templatesResult.status === 'fulfilled'
      ? templatesResult.value.filter(template => template.type_slug === 'company')
      : []
  }
  catch (error) {
    console.error('Failed to load companies:', error)
    toast.error(tr('failedToLoad'))
  }
  finally {
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
    if (value !== undefined && value !== null)
      (next as unknown as Record<string, unknown>)[key] = value
  }
  form.value = next
  isDialogVisible.value = true
}

const submit = async () => {
  const validation = await formRef.value?.validate()
  if (validation?.valid === false)
    return

  try {
    saving.value = true

    const payload: CompanyCreateData = { ...form.value }

    if (dialogMode.value === 'add') {
      const created = await CompanyApi.create(payload)

      companies.value.unshift(created)
      toast.success(tr('created'))
    }
    else if (selectedCompanyId.value) {
      const updated = await CompanyApi.update(selectedCompanyId.value, payload)
      const index = companies.value.findIndex(company => company.id === selectedCompanyId.value)
      if (index !== -1)
        companies.value[index] = updated
      toast.success(tr('updated'))
    }
    isDialogVisible.value = false
  }
  catch (error) {
    console.error('Failed to save company:', error)
    toast.error(tr('failedToSave'))
  }
  finally {
    saving.value = false
  }
}

const confirmDelete = async () => {
  const target = companyPendingDeletion.value
  if (!target)
    return
  companyPendingDeletion.value = null

  try {
    await CompanyApi.delete(target.id)
    companies.value = companies.value.filter(company => company.id !== target.id)
    toast.success(tr('deleted'))
  }
  catch {
    await fetchCompanies()
    toast.warning(tr('deactivatedInsteadOfDeleted'))
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
    toast.error(tr('contractCompanyNotFound'))

    return
  }

  contractDialogMode.value = 'edit'
  selectedContractCompany.value = company
  selectedContract.value = { ...contract }
  isContractDialogVisible.value = true
}

const saveContract = async (payload: ContractFormPayload) => {
  const company = selectedContractCompany.value
  if (!company)
    return

  try {
    savingContract.value = true

    const contractPayload = { ...payload, company_id: company.id }

    const saved = contractDialogMode.value === 'add'
      ? await ContractApi.create(contractPayload)
      : await ContractApi.update(selectedContract.value!.id, contractPayload)

    const index = companyContracts.value.findIndex(contract => contract.id === saved.id)
    if (index === -1)
      companyContracts.value.unshift(saved)
    else companyContracts.value[index] = saved

    isContractDialogVisible.value = false
    toast.success(contractDialogMode.value === 'add' ? tr('contractCreated') : tr('contractUpdated'))
  }
  catch (error) {
    console.error('Failed to save company contract:', error)
    toast.error(tr('failedToSaveContract'))
  }
  finally {
    savingContract.value = false
  }
}
</script>

<template>
  <DataTablePageKpis
    :items="filtered"
    :title="tr('title')"
    :description="tr('description')"
    icon="tabler-building"
  />
  <VCard>
    <VCardText>
      <VRow
        align="center"
        class="table-toolbar"
      >
        <VCol
          cols="12"
          md="6"
          lg="4"
        >
          <VTextField
            v-model="search"
            class="data-table-search"
            prepend-inner-icon="tabler-search"
            :placeholder="tr('search')"
            density="compact"
            clearable
            hide-details
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
          lg="8"
          class="d-flex justify-end gap-2"
        >
          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            @click="openAdd"
          >
            {{ tr('addCompany') }}
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>

    <ResponsiveDataTable
      :headers="headers"
      :items="filtered"
      item-value="id"
      class="text-no-wrap"
      :loading="isLoading"
      :loading-text="tr('loading')"
      style="overflow-x: auto;"
      :no-data-text="tr('empty')"
    >
      <template #no-data>
        {{ tr('empty') }}
      </template>
      <template #item.legal_name="{ item }">
        <div class="d-flex flex-column py-1">
          <span class="font-weight-medium text-no-wrap">{{ item.legal_name }}</span>
          <span
            v-if="fullAddress(item)"
            class="text-caption text-medium-emphasis text-truncate"
            style="max-inline-size: 280px;"
          >
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
        <VBtn
          icon="tabler-file-plus"
          variant="text"
          size="small"
          color="primary"
          class="me-2"
          @click="createContract(item)"
        >
          <VIcon icon="tabler-file-plus" /><VTooltip
            activator="parent"
            location="top"
          >
            {{ tr('createContract') }}
          </VTooltip>
        </VBtn>
        <VBtn
          icon="tabler-edit"
          variant="text"
          size="small"
          class="me-2"
          @click="openEdit(item)"
        >
          <VIcon icon="tabler-edit" /><VTooltip
            activator="parent"
            location="top"
          >
            {{ tr('editCompany') }}
          </VTooltip>
        </VBtn>
        <VBtn
          icon="tabler-trash"
          variant="text"
          size="small"
          color="error"
          @click="companyPendingDeletion = item"
        >
          <VIcon icon="tabler-trash" /><VTooltip
            activator="parent"
            location="top"
          >
            {{ tr('deleteCompany') }}
          </VTooltip>
        </VBtn>
      </template>
    </ResponsiveDataTable>
  </VCard>
  <ContractDocumentPrinter
    v-model="printDialogVisible"
    :contract="contractToPrint"
  />

  <!-- Company contracts: every contract whose template is a company type -->
  <VCard class="mt-6">
    <VCardItem>
      <template #prepend>
        <VIcon
          icon="tabler-building"
          size="24"
          class="text-primary mr-2"
        />
      </template>
      <VCardTitle>{{ tr('contractsTitle') }}</VCardTitle>
      <VCardSubtitle>{{ tr('contractsDescription') }}</VCardSubtitle>
      <template #append>
        <VBtn
          variant="tonal"
          color="primary"
          size="small"
          prepend-icon="tabler-plus"
          :disabled="!filtered.length"
          @click="createContract(filtered[0])"
        >
          {{ tr('newContract') }}
        </VBtn>
      </template>
    </VCardItem>

    <VDivider />

    <ResponsiveDataTable
      :headers="contractHeaders"
      :items="companyContracts"
      item-value="id"
      class="text-no-wrap"
      :loading="isLoading"
      :loading-text="tr('loadingContracts')"
      style="overflow-x: auto;"
      :no-data-text="tr('emptyContracts')"
    >
      <template #no-data>
        {{ tr('emptyContracts') }}
      </template>
      <template #item.contract="{ item }">
        {{ item.contract || item.contract_template?.name || tr('untitledContract') }}
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
        <VChip
          :color="contractStatusColor(item.status)"
          size="small"
          class="font-weight-medium text-capitalize"
        >
          {{ translateBackendValue(item.status) }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <VBtn
          icon="tabler-printer"
          variant="text"
          size="small"
          @click="printCompanyContract(item)"
        >
          <VIcon icon="tabler-printer" /><VTooltip
            activator="parent"
            location="top"
          >
            {{ tr('printContract') }}
          </VTooltip>
        </VBtn>
        <VBtn
          icon="tabler-edit"
          variant="text"
          size="small"
          @click="editContract(item)"
        >
          <VIcon icon="tabler-edit" /><VTooltip
            activator="parent"
            location="top"
          >
            {{ tr('editContract') }}
          </VTooltip>
        </VBtn>
      </template>
    </ResponsiveDataTable>
  </VCard>

  <!-- Create / Edit Dialog -->
  <VDialog
    v-model="isDialogVisible"
    max-width="760px"
    persistent
    scrollable
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="tabler-building" />
        {{ dialogMode === 'add' ? tr('addCompany') : tr('editCompany') }}
      </VCardTitle>

      <VCardText>
        <VForm
          ref="formRef"
          class="app-form"
          @submit.prevent="submit"
        >
          <p class="app-form__section">
            {{ tr('identity') }}
          </p>
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.legal_name"
                :label="tr('legalNameRequired')"
                :rules="[v => !!v?.trim() || tr('legalNameRequiredMessage')]"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.trade_name"
                :label="tr('tradeName')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <VTextField
                v-model="form.registration_number"
                :label="tr('registrationNumber')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <VTextField
                v-model="form.tax_number"
                :label="tr('taxNumber')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <VTextField
                v-model="form.national_id"
                :label="tr('nationalId')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <VTextField
                v-model="form.legal_form"
                :label="tr('legalForm')"
              />
            </VCol>
          </VRow>

          <p class="app-form__section mt-4">
            {{ tr('contactAndAddress') }}
          </p>
          <VRow>
            <VCol
              cols="12"
              sm="4"
            >
              <VTextField
                v-model="form.email"
                :label="tr('email')"
                type="email"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <VTextField
                v-model="form.phone"
                :label="tr('phone')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <VTextField
                v-model="form.website"
                :label="tr('website')"
              />
            </VCol>
            <!-- Keep the short fields readable at the dialog's desktop width. -->
            <VCol
              cols="12"
              sm="6"
              md="4"
            >
              <VTextField
                v-model="form.address_line"
                :label="tr('addressLine')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="2"
            >
              <VTextField
                v-model="form.city"
                :label="tr('city')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="2"
            >
              <VTextField
                v-model="form.province"
                :label="tr('province')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="2"
            >
              <VTextField
                v-model="form.postal_code"
                :label="tr('postalCode')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="2"
            >
              <VTextField
                v-model="form.country"
                :label="tr('country')"
              />
            </VCol>
          </VRow>

          <p class="app-form__section mt-4">
            {{ tr('businessInformation') }}
          </p>
          <VRow>
            <VCol
              cols="12"
              sm="4"
            >
              <VTextField
                v-model="form.industry"
                :label="tr('industry')"
              />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="form.description"
                :label="tr('formDescription')"
                rows="2"
                auto-grow
              />
            </VCol>
          </VRow>

          <p class="app-form__section mt-4">
            {{ tr('authorizedRepresentative') }}
          </p>
          <VRow>
            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <VTextField
                v-model="form.representative_name"
                :label="tr('name')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <VTextField
                v-model="form.representative_title"
                :label="tr('representativeTitle')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <VTextField
                v-model="form.representative_phone"
                :label="tr('phone')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <VTextField
                v-model="form.representative_email"
                :label="tr('email')"
                type="email"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardActions class="justify-end px-6 pb-4">
        <VBtn
          variant="outlined"
          color="secondary"
          @click="isDialogVisible = false"
        >
          {{ t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="saving"
          @click="submit"
        >
          {{ dialogMode === 'add' ? t('common.actions.create') : tr('saveChanges') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Company contract dialog -->
  <VDialog
    v-model="isContractDialogVisible"
    max-width="600px"
    persistent
  >
    <VCard>
      <VCardTitle>
        {{ contractDialogMode === 'add' ? tr('createCompanyContract') : tr('editCompanyContract') }}
      </VCardTitle>
      <VCardText>
        <ContractForm
          :contract="selectedContract || undefined"
          :templates="companyTemplates"
          audience="companies"
          @submit="saveContract"
          @cancel="isContractDialogVisible = false"
        />
      </VCardText>
      <VProgressLinear
        v-if="savingContract"
        indeterminate
        color="primary"
      />
    </VCard>
  </VDialog>

  <!-- Delete confirmation -->
  <VDialog
    :model-value="companyPendingDeletion !== null"
    max-width="440px"
    @update:model-value="companyPendingDeletion = null"
  >
    <VCard>
      <VCardTitle>{{ tr('deleteCompany') }}</VCardTitle>
      <VCardText>
        {{ tr('deleteConfirmation', { company: companyPendingDeletion?.legal_name ?? '' }) }}
      </VCardText>
      <VCardActions class="justify-end">
        <VBtn
          variant="text"
          color="secondary"
          @click="companyPendingDeletion = null"
        >
          {{ t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          color="error"
          @click="confirmDelete"
        >
          {{ t('common.actions.delete') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
