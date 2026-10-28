<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { VDialog } from 'vuetify/components'
import { useToast } from 'vue-toastification'
import { translate } from '@/utils/localization'
import AssetApi, { type AssetCondition, type AssetItem, type AssetParams } from '@/api/asset'
import EmployeeApi, { type Employee } from '@/api/employee'
import OfficeApi, { type Office } from '@/api/office'
import { printDocument } from '@/utils/printDocument'
import PrintableListReport from '@/components/PrintableListReport.vue'
import ResponsiveDataTableServer from '@/components/responsive-data-table/ResponsiveDataTableServer.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'

const toast = useToast()

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

const allocationTotal = (asset: AssetItem | null) => asset?.allocations.reduce((total, allocation) => total + Number(allocation.quantity || 0), 0) ?? 0
const addAllocation = () => selectedAsset.value?.allocations.push({ employee_id: 0, quantity: 1 })
const removeAllocation = (index: number) => selectedAsset.value?.allocations.splice(index, 1)

const assetReportRow = (asset: AssetItem): string[] => [
  asset.serial_number,
  asset.item,
  `${asset.quantity}`,
  `${asset.price}`,
  asset.model,
  asset.condition,
  asset.office?.name ?? '—',
  asset.allocations.map(allocation => `${allocation.employee?.name || 'Employee'}: ${allocation.quantity}`).join(', '),
]

const headers = computed(() => [
  { title: translate('pages.finance.asset.asset.serialNumber'), key: 'serial_number', sortable: true },
  { title: translate('pages.finance.asset.asset.item'), key: 'item', sortable: true },
  { title: translate('pages.finance.asset.asset.quantity'), key: 'quantity', sortable: true },
  { title: translate('common.crmUi.printReports.columns.price'), key: 'price', sortable: true },
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
  if (selectedAsset.value) {
    try {
      const { id, office, allocated_quantity, available_quantity, ...assetData } = selectedAsset.value

      await AssetApi.create(assetData)
      // A new row belongs on the first page, and the paging counts changed.
      page.value = 1
      await fetchAssets()
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
  if (selectedAsset.value) {
    try {
      const { id, office, allocated_quantity, available_quantity, ...assetData } = selectedAsset.value

      await AssetApi.update(id, assetData)
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

          <template #item.condition="{ item }">
            <VChip
              :color="conditionColor(item.condition)"
              size="small"
              variant="tonal"
              label
            >
              {{ conditions.find(condition => condition.value === item.condition)?.title || item.condition }}
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <FullDetailsButton
                :item="item"
                :title="item.item_name || item.name || item.serial_number"
              />
              <VBtn
                icon
                variant="text"
                size="small"
                color="medium-emphasis"
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
                icon
                variant="text"
                size="small"
                color="error"
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
      :columns="[$t('common.crmUi.printReports.columns.serialNumber'), $t('common.crmUi.printReports.columns.item'), $t('common.crmUi.printReports.columns.quantity'), $t('common.crmUi.printReports.columns.price'), $t('common.crmUi.printReports.columns.model'), $t('common.crmUi.printReports.columns.condition'), $t('common.crmUi.printReports.columns.office'), $t('common.crmUi.printReports.columns.allocations')]"
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
          <VForm class="app-form">
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedAsset.serial_number"
                  :label="$t('common.messages.serialNumber')"
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
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="selectedAsset.price"
                  :label="$t('common.crmUi.printReports.columns.price')"
                  type="number"
                  min="0"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedAsset.model"
                  :label="$t('common.messages.model')"
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
                      icon="tabler-trash"
                      variant="text"
                      color="error"
                      @click="removeAllocation(index)"
                    />
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
          <VForm class="app-form">
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedAsset.serial_number"
                  :label="$t('common.messages.serialNumber')"
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
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="selectedAsset.price"
                  :label="$t('common.crmUi.printReports.columns.price')"
                  type="number"
                  min="0"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedAsset.model"
                  :label="$t('common.messages.model')"
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
                      icon="tabler-trash"
                      variant="text"
                      color="error"
                      @click="removeAllocation(index)"
                    />
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
