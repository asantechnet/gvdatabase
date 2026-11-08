<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { VDialog } from 'vuetify/components'
import { useToast } from 'vue-toastification'
import { formatActiveDate, formatActiveMoney, translate } from '@/utils/localization'
import { toIsoDate } from '@/utils/money'
import AssetApi, { type AssetCondition, type AssetItem, type AssetParams, type AssetPayload } from '@/api/asset'
import CurrencyApi, { type Currency } from '@/api/currency'
import EmployeeApi, { type Employee } from '@/api/employee'
import OfficeApi, { type Office } from '@/api/office'
import { canAccess } from '@/utils/authorization'
import { printDocument } from '@/utils/printDocument'
import PrintableListReport from '@/components/PrintableListReport.vue'
import ResponsiveDataTableServer from '@/components/responsive-data-table/ResponsiveDataTableServer.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'
import { moveCreatedRowFirst } from '@/utils/createdFirst'

const toast = useToast()

type ValidatableForm = { validate: () => Promise<{ valid: boolean }> }

const assets = ref<AssetItem[]>([])
// The table asks the server for one page at a time.
const page = ref(1)
const perPage = ref(25)
const total = ref(0)
// Filled from every filtered row right before printing, so the shared report
// covers the whole result set instead of the visible page.
const reportRows = ref<string[][]>([])
const employees = ref<Employee[]>([])
const isAddAssetDialogVisible = ref(false)
const isEditAssetDialogVisible = ref(false)
const isDeleteAssetDialogVisible = ref(false)
const selectedAsset = ref<AssetItem | null>(null)
const search = ref('')
const filterModel = ref<string | null>(null)
const filterOffice = ref<number | null>(null)
const filterCondition = ref<AssetCondition | null>(null)
const isLoading = ref(false)
const employeesLoading = ref(false)
const officeDirectory = ref<Office[]>([])
const models = ref<string[]>([])
const currencies = ref<Currency[]>([])
const addFormRef = ref<ValidatableForm | null>(null)
const editFormRef = ref<ValidatableForm | null>(null)
const canCreateAssets = computed(() => canAccess('create_assets'))
const canEditAssets = computed(() => canAccess('edit_assets'))
const canDeleteAssets = computed(() => canAccess('delete_assets'))

const officeOptions = computed(() => {
  const byId = new Map<number, { title: string; value: number; active: boolean }>()

  officeDirectory.value.forEach(office => byId.set(office.id, {
    title: `${office.name} (${office.code})`,
    value: office.id,
    active: office.status === 'active',
  }))
  assets.value.forEach(asset => {
    if (asset.office && !byId.has(asset.office.id))
      byId.set(asset.office.id, { title: `${asset.office.name} (${asset.office.code})`, value: asset.office.id, active: asset.office.status === 'active' })
  })

  return [...byId.values()].sort((left, right) => left.title.localeCompare(right.title))
})

const activeOfficeOptions = computed(() => officeOptions.value.filter(office => office.active || office.value === selectedAsset.value?.office_id))

const currencyOptions = computed(() => currencies.value.map(currency => ({
  title: `${currency.code} — ${currency.name}`,
  value: currency.id,
})))
const baseCurrency = computed(() => currencies.value.find(currency => currency.isBase) ?? null)
const selectedCurrency = computed(() => currencies.value.find(currency => currency.id === selectedAsset.value?.currency_id) ?? null)
const selectedAssetTotal = computed(() => Number(selectedAsset.value?.quantity ?? 0) * Number(selectedAsset.value?.price ?? 0))
const valuationSummary = computed(() => {
  const total = translate('pages.finance.asset.asset.totalValuePreview', {
    amount: formatActiveMoney(selectedAssetTotal.value, selectedCurrency.value),
  })
  const book = selectedAsset.value?.book_value

  if (book !== null && book !== undefined)
    return `${total} · ${translate('pages.finance.asset.asset.bookValuePreview', { amount: formatActiveMoney(book, selectedCurrency.value) })}`

  return `${total} · ${translate('pages.finance.asset.asset.valuationAfterSave')}`
})

const conditions: Array<{ title: string; value: AssetCondition }> = [
  { title: translate('pages.finance.asset.asset.new'), value: 'new' },
  { title: translate('pages.finance.asset.asset.good'), value: 'good' },
  { title: translate('pages.finance.asset.asset.fair'), value: 'fair' },
  { title: translate('pages.finance.asset.asset.poor'), value: 'poor' },
  { title: translate('pages.finance.asset.asset.damaged'), value: 'damaged' },
]

const conditionColor = (condition: AssetCondition) => ({
  new: 'info',
  good: 'success',
  fair: 'warning',
  poor: 'orange-darken-2',
  damaged: 'error',
}[condition])

const isBlank = (value: unknown) => value === null || value === undefined || String(value).trim() === ''
const requiredRule = (value: unknown) => !isBlank(value) || translate('common.validation.required')
const wholeNumberAtLeast = (min: number) => (value: unknown) =>
  (Number.isInteger(Number(value)) && Number(value) >= min) || translate('pages.finance.asset.asset.wholeNumberAtLeast', { min })
const nonNegativeNumber = (value: unknown) =>
  (!isBlank(value) && Number.isFinite(Number(value)) && Number(value) >= 0) || translate('pages.finance.asset.asset.nonNegativeNumber')
const optionalRule = (rule: (value: unknown) => true | string) => (value: unknown) => isBlank(value) ? true : rule(value)

const currencyFor = (asset: AssetItem) => asset.currency ?? baseCurrency.value
const assetMoney = (value: number | string | null | undefined, asset: AssetItem) => formatActiveMoney(value, currencyFor(asset))
const totalValueFor = (asset: AssetItem) => asset.total_value ?? Number(asset.quantity || 0) * Number(asset.price || 0)
const conditionLabel = (condition: AssetCondition) => conditions.find(entry => entry.value === condition)?.title || condition

const toPayload = (asset: AssetItem): AssetPayload => {
  const { id, office, currency, allocated_quantity, available_quantity, total_value, book_value, ...payload } = asset

  return {
    ...payload,
    currency_id: Number(payload.currency_id),
    acquisition_date: payload.acquisition_date ?? '',
    useful_life_years: isBlank(payload.useful_life_years) ? null : Number(payload.useful_life_years),
    salvage_value: isBlank(payload.salvage_value) ? 0 : Number(payload.salvage_value),
  }
}

const allocationTotal = (asset: AssetItem | null) => asset?.allocations.reduce((total, allocation) => total + Number(allocation.quantity || 0), 0) ?? 0
const addAllocation = () => selectedAsset.value?.allocations.push({ employee_id: 0, quantity: 1 })
const removeAllocation = (index: number) => selectedAsset.value?.allocations.splice(index, 1)

const assetReportRow = (asset: AssetItem): string[] => [
  asset.serial_number,
  asset.item,
  `${asset.quantity}`,
  assetMoney(asset.price, asset),
  assetMoney(totalValueFor(asset), asset),
  asset.currency?.code ?? baseCurrency.value?.code ?? '—',
  formatActiveDate(asset.acquisition_date),
  formatActiveMoney(asset.book_value, currencyFor(asset)),
  asset.model,
  conditionLabel(asset.condition),
  asset.office?.name ?? '—',
  asset.allocations.map(allocation => `${allocation.employee?.name || 'Employee'}: ${allocation.quantity}`).join(', '),
]

const headers = computed(() => [
  { title: translate('pages.finance.asset.asset.serialNumber'), key: 'serial_number', sortable: true },
  { title: translate('pages.finance.asset.asset.item'), key: 'item', sortable: true },
  { title: translate('pages.finance.asset.asset.quantity'), key: 'quantity', sortable: true },
  { title: translate('pages.finance.asset.asset.unitPrice'), key: 'price', sortable: true },
  { title: translate('pages.finance.asset.asset.totalValue'), key: 'total_value', sortable: false },
  { title: translate('common.fields.currency'), key: 'currency.code', sortable: false },
  { title: translate('pages.finance.asset.asset.acquisitionDate'), key: 'acquisition_date', sortable: true },
  { title: translate('pages.finance.asset.asset.bookValue'), key: 'book_value', sortable: false },
  { title: translate('pages.finance.asset.asset.model'), key: 'model', sortable: true },
  { title: translate('pages.finance.asset.asset.condition'), key: 'condition', sortable: true },
  { title: translate('common.fields.office'), key: 'office.name', sortable: true },
  { title: translate('common.ui.allocatedTo'), key: 'allocations', sortable: false },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false },
])

const filterParams = (): AssetParams => ({
  search: search.value || undefined,
  model: filterModel.value || undefined,
  office_id: filterOffice.value || undefined,
  condition: filterCondition.value || undefined,
})

const fetchAssets = async () => {
  isLoading.value = true
  try {
    const result = await AssetApi.listPage({ ...filterParams(), page: page.value, perPage: perPage.value })

    assets.value = result.rows
    total.value = result.total
  }
  catch (error) {
    console.error('Error fetching assets:', error)
    toast.error(translate('pages.finance.asset.asset.failedToFetchAssetRecords'))
  }
  finally {
    isLoading.value = false
  }
}

function updateTableOptions(options: { page: number; itemsPerPage: number }) {
  if (page.value === options.page && perPage.value === options.itemsPerPage)
    return

  page.value = options.page
  perPage.value = options.itemsPerPage
  fetchAssets()
}

const fetchCurrencies = async () => {
  try {
    const response = await CurrencyApi.getAll()
    currencies.value = response.data
  }
  catch (error) {
    console.error('Error fetching currencies:', error)
    toast.error(translate('pages.finance.asset.asset.failedToFetchCurrencies'))
  }
}

const fetchEmployees = async () => {
  try {
    employeesLoading.value = true
    employees.value = await EmployeeApi.getOptions()
  }
  catch (error) {
    console.error('Error fetching employees:', error)
    toast.error(translate('pages.finance.asset.asset.failedToFetchEmployees'))
  }
  finally {
    employeesLoading.value = false
  }
}

// Print is global: the report is filled from every filtered row first.
const printTable = async () => {
  try {
    reportRows.value = (await AssetApi.getAll(filterParams())).map(assetReportRow)
    await printDocument({ bodyClass: 'printing-table-asset', pageStyle: 'size: A4 landscape; margin: 12mm;' })
  }
  catch (error) {
    console.error('Error preparing asset report:', error)
    toast.error(translate('pages.finance.asset.asset.failedToFetchAssetRecords'))
  }
}

const openAddAssetDialog = () => {
  selectedAsset.value = {
    id: 0,
    serial_number: '',
    item: '',
    quantity: 1,
    price: 0,
    model: '',
    condition: 'good',
    office_id: officeDirectory.value.find(office => office.status === 'active')?.id ?? null,
    currency_id: currencies.value.find(currency => currency.isBase)?.id ?? currencies.value[0]?.id ?? null,
    acquisition_date: toIsoDate(new Date()),
    useful_life_years: null,
    salvage_value: 0,
    allocations: [],
  }
  isAddAssetDialogVisible.value = true
}

const openEditAssetDialog = (asset: AssetItem) => {
  selectedAsset.value = { ...asset }
  isEditAssetDialogVisible.value = true
}

const openDeleteAssetDialog = (asset: AssetItem) => {
  selectedAsset.value = asset
  isDeleteAssetDialogVisible.value = true
}

const addAsset = async () => {
  const validation = await addFormRef.value?.validate()
  if (validation && !validation.valid)
    return

  if (selectedAsset.value) {
    try {
      const created = await AssetApi.create(toPayload(selectedAsset.value))
      // A new row belongs on the first page, and the paging counts changed.
      page.value = 1
      await fetchAssets()
      assets.value = moveCreatedRowFirst(assets.value, created).slice(0, perPage.value)
      isAddAssetDialogVisible.value = false
      toast.success(translate('pages.finance.asset.asset.assetItemAdded'))
    }
    catch (error) {
      console.error('Error adding asset:', error)
      toast.error(translate('pages.finance.asset.asset.failedToAddAssetItem'))
    }
  }
}

const updateAsset = async () => {
  const validation = await editFormRef.value?.validate()
  if (validation && !validation.valid)
    return

  if (selectedAsset.value) {
    try {
      await AssetApi.update(selectedAsset.value.id, toPayload(selectedAsset.value))
      await fetchAssets()
      isEditAssetDialogVisible.value = false
      toast.success(translate('pages.finance.asset.asset.assetItemUpdated'))
    }
    catch (error) {
      console.error('Error updating asset:', error)
      toast.error(translate('pages.finance.asset.asset.failedToUpdateAssetItem'))
    }
  }
}

const deleteAsset = async () => {
  if (selectedAsset.value) {
    try {
      await AssetApi.delete(selectedAsset.value.id)
      // The deleted row was the last one on this page, so step back instead of
      // showing an empty page.
      if (page.value > 1 && assets.value.length === 1)
        page.value -= 1
      await fetchAssets()
      isDeleteAssetDialogVisible.value = false
      toast.success(translate('pages.finance.asset.asset.assetItemDeleted'))
    }
    catch (error) {
      console.error('Error deleting asset:', error)
      toast.error(translate('pages.finance.asset.asset.failedToDeleteAssetItem'))
    }
  }
}

const debouncedFetchAssets = useDebounceFn(() => {
  page.value = 1
  void fetchAssets()
}, 300)

watch([search, filterModel, filterOffice, filterCondition], () => {
  void debouncedFetchAssets()
})

onMounted(() => {
  fetchAssets()
  fetchCurrencies()
  fetchEmployees()
  AssetApi.models().then(data => { models.value = data }).catch(error => {
    console.error('Error fetching asset models:', error)
  })
  OfficeApi.getAll().then(data => { officeDirectory.value = data }).catch(error => {
    console.error('Error fetching offices:', error)
    toast.error(translate('pages.finance.asset.asset.failedToFetchOffices'))
  })
})
</script>

<template>
  <div>
    <DataTablePageKpis
      :items="assets"
      :title="$t('components.dataTablePageKpis.pages.assetInventory.title')"
      :description="$t('components.dataTablePageKpis.pages.assetInventory.description')"
      icon="tabler-packages"
    />
    <VCard class="mb-6">
      <VCardText>
        <VRow align="center">
          <VCol
            cols="12"
            md="7"
          >
            <VTextField
              v-model="search"
              class="data-table-search"
              prepend-inner-icon="tabler-search"
              :placeholder="$t('pages.finance.asset.asset.searchAsset')"
              density="compact"
              clearable
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            md="5"
            class="text-end d-flex gap-2 justify-end"
          >
            <DataTableFilterMenu
              :active="Boolean(filterModel || filterOffice || filterCondition)"
              @reset="filterModel = null; filterOffice = null; filterCondition = null"
            >
              <VSelect
                v-model="filterModel"
                :items="models"
                :label="$t('pages.finance.asset.asset.filterByModel')"
                density="compact"
                clearable
                hide-details
              />
              <VSelect
                v-model="filterOffice"
                :items="officeOptions"
                :label="$t('pages.finance.asset.asset.filterByOffice')"
                density="compact"
                clearable
                hide-details
              />
              <VSelect
                v-model="filterCondition"
                :items="conditions"
                :label="$t('pages.finance.asset.asset.filterByCondition')"
                density="compact"
                clearable
                hide-details
              />
            </DataTableFilterMenu>
            <VBtn
              icon
              variant="text"
              :aria-label="$t('pages.finance.asset.asset.printAssetRecords')"
              @click="printTable"
            >
              <VIcon icon="tabler-printer" />
              <VTooltip
                activator="parent"
                location="top"
              >
                {{ $t('pages.finance.asset.asset.printAssetRecords') }}
              </VTooltip>
            </VBtn>
            <VBtn
              v-if="canCreateAssets"
              color="primary"
              @click="openAddAssetDialog"
            >
              <VIcon
                icon="tabler-plus"
                class="me-1"
              />
              {{ $t('pages.finance.asset.asset.addAsset') }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <div class="table-container">
        <ResponsiveDataTableServer
          :headers="headers"
          :items="assets"
          :items-length="total"
          :page="page"
          :items-per-page="perPage"
          :items-per-page-options="[10, 25, 50, 100]"
          item-value="id"
          class="text-no-wrap"
          :loading="isLoading"
          mobile-breakpoint="sm"
          density="compact"
          sticky-last-column
          @update:options="updateTableOptions"
        >
          <template #loading>
            <TableLoadingSkeleton
              :columns="headers.length"
              :label="$t('common.messages.loading')"
            />
          </template>

          <template #no-data>
            <TableEmptyState
              icon="tabler-packages"
              :description="$t('common.messages.noMatchingRecords')"
            />
          </template>

          <template #item.allocations="{ item }">
            <div class="d-flex flex-wrap ga-1">
              <VChip
                v-for="allocation in item.allocations"
                :key="allocation.employee_id"
                size="x-small"
                color="primary"
                variant="tonal"
              >
                {{ allocation.employee?.name || 'Employee' }}: {{ allocation.quantity }}
              </VChip>
              <span
                v-if="!item.allocations.length"
                class="text-medium-emphasis"
              >{{ $t('common.crmUi.unassigned') }}</span>
            </div>
          </template>

          <template #item.office.name="{ item }">
            {{ item.office ? `${item.office.name} (${item.office.code})` : '—' }}
          </template>

          <template #item.price="{ item }">
            {{ assetMoney(item.price, item) }}
          </template>

          <template #item.total_value="{ item }">
            {{ assetMoney(totalValueFor(item), item) }}
          </template>

          <template #item.currency.code="{ item }">
            {{ item.currency?.code ?? baseCurrency?.code ?? '—' }}
          </template>

          <template #item.acquisition_date="{ item }">
            {{ formatActiveDate(item.acquisition_date) }}
          </template>

          <template #item.book_value="{ item }">
            {{ assetMoney(item.book_value, item) }}
          </template>

          <template #item.condition="{ item }">
            <VChip
              :color="conditionColor(item.condition)"
              size="small"
              variant="tonal"
              label
            >
              {{ conditionLabel(item.condition) }}
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <FullDetailsButton
                :item="item"
                :title="item.item_name || item.name || item.serial_number"
              />
              <VBtn
                v-if="canEditAssets"
                icon
                variant="text"
                size="small"
                color="medium-emphasis"
                :aria-label="$t('pages.finance.asset.asset.editAssetRecord')"
                @click="openEditAssetDialog(item)"
              >
                <VIcon icon="tabler-edit" />
                <VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t('pages.finance.asset.asset.editAssetRecord') }}
                </VTooltip>
              </VBtn>
              <VBtn
                v-if="canDeleteAssets"
                icon
                variant="text"
                size="small"
                color="error"
                :aria-label="$t('pages.finance.asset.asset.deleteAssetRecord')"
                @click="openDeleteAssetDialog(item)"
              >
                <VIcon icon="tabler-trash" />
                <VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t('pages.finance.asset.asset.deleteAssetRecord') }}
                </VTooltip>
              </VBtn>
            </div>
          </template>
        </ResponsiveDataTableServer>
      </div>
    </VCard>

    <PrintableListReport
      :title="$t('common.crmUi.printReports.assets.title')"
      :subtitle="$t('common.crmUi.printReports.assets.subtitle')"
      :columns="[
        $t('common.crmUi.printReports.columns.serialNumber'),
        $t('common.crmUi.printReports.columns.item'),
        $t('common.crmUi.printReports.columns.quantity'),
        $t('pages.finance.asset.asset.unitPrice'),
        $t('pages.finance.asset.asset.totalValue'),
        $t('common.fields.currency'),
        $t('pages.finance.asset.asset.acquisitionDate'),
        $t('pages.finance.asset.asset.bookValue'),
        $t('common.crmUi.printReports.columns.model'),
        $t('common.crmUi.printReports.columns.condition'),
        $t('common.crmUi.printReports.columns.office'),
        $t('common.crmUi.printReports.columns.allocations'),
      ]"
      :rows="reportRows"
    />

    <!-- Add Asset Dialog -->
    <VDialog
      v-model="isAddAssetDialogVisible"
      max-width="1000px"
      scrollable
    >
      <VCard v-if="selectedAsset">
        <VCardTitle class="pa-5">
          <span class="headline">{{ $t('pages.finance.asset.asset.addNewAssetItem') }}</span>
        </VCardTitle>

        <VCardText>
          <VForm ref="addFormRef" class="app-form">
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedAsset.serial_number"
                  :label="$t('common.messages.serialNumber')"
                  :rules="[requiredRule]"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedAsset.item"
                  :label="$t('common.messages.itemName')"
                  :rules="[requiredRule]"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="selectedAsset.quantity"
                  :label="$t('common.messages.quantity')"
                  type="number"
                  :rules="[requiredRule, wholeNumberAtLeast(1)]"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="selectedAsset.price"
                  :label="$t('pages.finance.asset.asset.unitPrice')"
                  type="number"
                  min="0"
                  :rules="[requiredRule, nonNegativeNumber]"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="selectedAsset.currency_id"
                  :items="currencyOptions"
                  :label="$t('common.fields.currency')"
                  :rules="[requiredRule]"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedAsset.acquisition_date"
                  :label="$t('pages.finance.asset.asset.acquisitionDate')"
                  type="date"
                  :rules="[requiredRule]"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="selectedAsset.useful_life_years"
                  :label="$t('pages.finance.asset.asset.usefulLifeYears')"
                  type="number"
                  min="1"
                  :rules="[optionalRule(wholeNumberAtLeast(1))]"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="selectedAsset.salvage_value"
                  :label="$t('pages.finance.asset.asset.salvageValue')"
                  type="number"
                  min="0"
                  :rules="[optionalRule(nonNegativeNumber)]"
                />
              </VCol>
              <VCol cols="12">
                <VAlert
                  type="info"
                  variant="tonal"
                  density="compact"
                >
                  {{ valuationSummary }}
                </VAlert>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedAsset.model"
                  :label="$t('common.messages.model')"
                  :rules="[requiredRule]"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="selectedAsset.office_id"
                  :items="activeOfficeOptions"
                  :label="$t('common.fields.office')"
                  :rules="[requiredRule]"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="selectedAsset.condition"
                  :items="conditions"
                  :label="$t('common.messages.condition')"
                  :rules="[requiredRule]"
                  required
                />
              </VCol>
              <VCol cols="12">
                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-subtitle-2">{{ $t('pages.finance.asset.asset.employeeAllocations') }}</span><VBtn
                    size="small"
                    variant="tonal"
                    prepend-icon="tabler-plus"
                    @click="addAllocation"
                  >
                    {{ $t('pages.finance.asset.asset.addEmployee') }}
                  </VBtn>
                </div>
                <VAlert
                  type="info"
                  variant="tonal"
                  density="compact"
                  class="mb-3"
                >
                  {{ $t('pages.finance.asset.asset.allocationTotalCannotExceed', { allocated: allocationTotal(selectedAsset), quantity: selectedAsset.quantity }) }}
                </VAlert>
                <VRow
                  v-for="(allocation, index) in selectedAsset.allocations"
                  :key="index"
                >
                  <VCol
                    cols="12"
                    sm="7"
                  >
                    <VAutocomplete
                      v-model="allocation.employee_id"
                      :label="$t('pages.finance.asset.asset.employee')"
                      :items="employees"
                      item-title="name"
                      item-value="id"
                      :loading="employeesLoading"
                    />
                  </VCol>
                  <VCol
                    cols="9"
                    sm="4"
                  >
                    <VTextField
                      v-model.number="allocation.quantity"
                      :label="$t('pages.finance.asset.asset.quantity')"
                      type="number"
                      min="1"
                      :max="selectedAsset.quantity"
                    />
                  </VCol>
                  <VCol
                    cols="3"
                    sm="1"
                    class="d-flex align-center"
                  >
                    <VBtn
                      icon
                      variant="text"
                      color="error"
                      :aria-label="$t('pages.finance.asset.asset.removeAllocation')"
                      @click="removeAllocation(index)"
                    >
                      <VIcon icon="tabler-trash" />
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        {{ $t('pages.finance.asset.asset.removeAllocation') }}
                      </VTooltip>
                    </VBtn>
                  </VCol>
                </VRow>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isAddAssetDialogVisible = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            @click="addAsset"
          >
            {{ $t('common.actions.save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit Asset Dialog -->
    <VDialog
      v-model="isEditAssetDialogVisible"
      max-width="1000px"
      scrollable
    >
      <VCard v-if="selectedAsset">
        <VCardTitle class="pa-5">
          <span class="headline">{{ $t('pages.finance.asset.asset.editAssetItem') }}</span>
        </VCardTitle>

        <VCardText>
          <VForm ref="editFormRef" class="app-form">
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedAsset.serial_number"
                  :label="$t('common.messages.serialNumber')"
                  :rules="[requiredRule]"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedAsset.item"
                  :label="$t('common.messages.itemName')"
                  :rules="[requiredRule]"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="selectedAsset.quantity"
                  :label="$t('common.messages.quantity')"
                  type="number"
                  :rules="[requiredRule, wholeNumberAtLeast(1)]"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="selectedAsset.price"
                  :label="$t('pages.finance.asset.asset.unitPrice')"
                  type="number"
                  min="0"
                  :rules="[requiredRule, nonNegativeNumber]"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="selectedAsset.currency_id"
                  :items="currencyOptions"
                  :label="$t('common.fields.currency')"
                  :rules="[requiredRule]"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedAsset.acquisition_date"
                  :label="$t('pages.finance.asset.asset.acquisitionDate')"
                  type="date"
                  :rules="[requiredRule]"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="selectedAsset.useful_life_years"
                  :label="$t('pages.finance.asset.asset.usefulLifeYears')"
                  type="number"
                  min="1"
                  :rules="[optionalRule(wholeNumberAtLeast(1))]"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="selectedAsset.salvage_value"
                  :label="$t('pages.finance.asset.asset.salvageValue')"
                  type="number"
                  min="0"
                  :rules="[optionalRule(nonNegativeNumber)]"
                />
              </VCol>
              <VCol cols="12">
                <VAlert
                  type="info"
                  variant="tonal"
                  density="compact"
                >
                  {{ valuationSummary }}
                </VAlert>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedAsset.model"
                  :label="$t('common.messages.model')"
                  :rules="[requiredRule]"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="selectedAsset.office_id"
                  :items="activeOfficeOptions"
                  :label="$t('common.fields.office')"
                  :rules="[requiredRule]"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="selectedAsset.condition"
                  :items="conditions"
                  :label="$t('common.messages.condition')"
                  :rules="[requiredRule]"
                  required
                />
              </VCol>
              <VCol cols="12">
                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-subtitle-2">{{ $t('pages.finance.asset.asset.employeeAllocations') }}</span><VBtn
                    size="small"
                    variant="tonal"
                    prepend-icon="tabler-plus"
                    @click="addAllocation"
                  >
                    {{ $t('pages.finance.asset.asset.addEmployee') }}
                  </VBtn>
                </div>
                <VAlert
                  type="info"
                  variant="tonal"
                  density="compact"
                  class="mb-3"
                >
                  {{ $t('pages.finance.asset.asset.allocationReduceBeforeLowering', { allocated: allocationTotal(selectedAsset), quantity: selectedAsset.quantity }) }}
                </VAlert>
                <VRow
                  v-for="(allocation, index) in selectedAsset.allocations"
                  :key="index"
                >
                  <VCol
                    cols="12"
                    sm="7"
                  >
                    <VAutocomplete
                      v-model="allocation.employee_id"
                      :label="$t('pages.finance.asset.asset.employee')"
                      :items="employees"
                      item-title="name"
                      item-value="id"
                      :loading="employeesLoading"
                    />
                  </VCol>
                  <VCol
                    cols="9"
                    sm="4"
                  >
                    <VTextField
                      v-model.number="allocation.quantity"
                      :label="$t('pages.finance.asset.asset.quantity')"
                      type="number"
                      min="1"
                      :max="selectedAsset.quantity"
                    />
                  </VCol>
                  <VCol
                    cols="3"
                    sm="1"
                    class="d-flex align-center"
                  >
                    <VBtn
                      icon
                      variant="text"
                      color="error"
                      :aria-label="$t('pages.finance.asset.asset.removeAllocation')"
                      @click="removeAllocation(index)"
                    >
                      <VIcon icon="tabler-trash" />
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        {{ $t('pages.finance.asset.asset.removeAllocation') }}
                      </VTooltip>
                    </VBtn>
                  </VCol>
                </VRow>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isEditAssetDialogVisible = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            @click="updateAsset"
          >
            {{ $t('common.actions.save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Asset Dialog -->
    <VDialog
      v-model="isDeleteAssetDialogVisible"
      max-width="500px"
    >
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">{{ $t('common.messages.confirmDelete') }}</span>
        </VCardTitle>

        <VCardText>
          {{ $t('common.confirmations.areYouSureYouWantToDeleteThisAssetItem') }}
          <br>
          {{ $t('common.confirmations.thisActionCannotBeUndone') }}
        </VCardText>

        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isDeleteAssetDialogVisible = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="error"
            @click="deleteAsset"
          >
            {{ $t('common.actions.delete') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.table-container {
  min-inline-size: 0;
}
</style>
