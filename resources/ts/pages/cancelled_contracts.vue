<script setup lang="ts">
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import FullDetailsButton from '@/components/FullDetailsButton.vue'
import PrintableListReport from '@/components/PrintableListReport.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'
import { printDocument } from '@/utils/printDocument'
import { computed, onMounted, ref } from 'vue'
import { VAlert } from 'vuetify/components'
import ContractApi from '@/api/contracts'
import type { Contract, ContractParams } from '@/api/contracts'
import ResponsiveDataTableServer from '@/components/responsive-data-table/ResponsiveDataTableServer.vue'
import { useContracts } from '@/composables/useContracts'

const {
  contracts,
  listContracts,
  isLoading,
  error,
} = useContracts()

const dateFrom = ref('')
const dateTo = ref('')
const search = ref('')
const filterCounterparty = ref<string | null>(null)

// The table asks the server for one page at a time.
const page = ref(1)
const perPage = ref(25)
const total = ref(0)
const sortByKey = ref<'contract_number' | 'counterparty' | 'start_date' | 'status' | 'created_at'>('start_date')
const sortDirection = ref<'asc' | 'desc'>('desc')
const counterparties = ref<string[]>([])
// Filled from every matching contract right before printing.
const printedRows = ref<(string | number)[][]>([])

const listParams = (): ContractParams => ({
  only_cancelled: true,
  search: search.value || undefined,
  counterparty: filterCounterparty.value || undefined,
  date_from: dateFrom.value || undefined,
  date_to: dateTo.value || undefined,
  sort_by: sortByKey.value,
  sort_direction: sortDirection.value,
})

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

const debouncedSearch = useDebounceFn(reload, 300)

async function loadCounterparties() {
  try {
    counterparties.value = await ContractApi.counterparties()
  }
  catch (err) {
    console.error('Failed to fetch counterparties:', err)
  }
}

const headers = computed(() => [
  { title: translate('common.messages.contractNumber'), key: 'contract_number', sortable: true },
  { title: translate('common.messages.contract'), key: 'contract', sortable: true },
  { title: translate('pages.contracts.allContracts.allContracts.counterparty'), key: 'counterparty', sortable: true },
  { title: translate('common.fields.startDate'), key: 'start_date', sortable: true },
  // Cancellation keeps the record alive, so the cancellation date is part of
  // the audit trail shown on this page.
  { title: translate('common.fields.cancelledAt'), key: 'cancelled_at', sortable: true },
  { title: translate('common.fields.status'), key: 'status', sortable: true },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

const reportColumns = computed(() => headers.value.filter(header => header.key !== 'actions'))

const reportValue = (row: Contract, key: string): string | number => {
  switch (key) {
    case 'contract':
      return row.contract || row.contract_template?.name || ''
    case 'counterparty':
      return row.counterparty || ''
    case 'start_date':
      return row.start_date ? formatLocalizedDate(row.start_date) : ''
    case 'cancelled_at':
      return row.cancelled_at ? formatLocalizedDate(row.cancelled_at, { dateStyle: 'medium', timeStyle: 'short' }) : ''
    case 'status':
      return row.status ? translate(`common.statuses.${row.status}`) : ''
    default:
      return row.contract_number || ''
  }
}

// Keep the printed sheet aligned with the on-screen columns, including the
// cancellation date, without duplicating the row shape by hand. Print is
// global: the report walks the filtered pages first.
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

    printedRows.value = collected.map(row => reportColumns.value.map(column => reportValue(row, column.key)))
    await printDocument({ bodyClass: 'printing-table-cancelled-contracts', pageStyle: 'size: A4 landscape; margin: 12mm;' })

    // Leave the table showing the page the operator was on.
    await load()
  }
  catch (err) {
    console.error('Failed to prepare the cancelled contracts report:', err)
  }
}

onMounted(async () => {
  await Promise.all([load(), loadCounterparties()])
})
</script>

<script lang="ts">
export default {
  methods: {
    getStatusColor(status: string) {
      if (!status)
        return 'default'

      const statusMap: Record<string, string> = {
        Active: 'success',
        Expired: 'error',
        Pending: 'warning',
      }

      return statusMap[status] || 'default'
    },
  },
}
</script>

<template>
  <div class="erp-workspace">
  <ErpPageHeader :title="$t('common.navigation.cancelledContracts')" section="contracts" />
  <VCard>
    <VCardText>
      <VRow align="center" class="table-toolbar">
        <VCol cols="12" md="6" lg="5">
          <ErpSearchField
            v-model="search"
            :placeholder="$t('pages.contracts.cancelledContracts.cancelledContracts.searchCancelledContracts')"
            @input="debouncedSearch"
          />
        </VCol>
        <VCol cols="12" md="6" lg="7" class="table-toolbar-actions d-flex flex-nowrap justify-end gap-2">
          <DataTableFilterMenu
            :active="Boolean(filterCounterparty || dateFrom || dateTo)"
            @reset="filterCounterparty = null; dateFrom = ''; dateTo = ''; reload()"
          >
            <ErpDateRange v-model:from="dateFrom" v-model:to="dateTo" :show-apply="false" />
            <ErpField
              v-model="filterCounterparty"
              kind="select"
              :items="counterparties"
              :label="$t('pages.contracts.allContracts.allContracts.filterByCounterparty')"
              clearable
            />
            <template #actions>
              <VBtn variant="tonal" color="primary" @click="filterCounterparty = null; dateFrom = ''; dateTo = ''; reload()">{{ $t('common.messages.reset') }}</VBtn>
              <VBtn color="primary" variant="flat" data-filter-apply @click="reload">{{ $t('educational.apply') }}</VBtn>
            </template>
          </DataTableFilterMenu>
          <VBtn icon="tabler-printer" color="primary" variant="tonal" size="small" class="cancelled-contract-print-button flex-shrink-0" :aria-label="$t('common.actions.print')" @click="printTable">
            <VTooltip activator="parent" location="top">{{ $t('common.actions.print') }}</VTooltip>
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
      :sort-by="[{ key: sortByKey, order: sortDirection }]"
      :items-per-page-options="[10, 25, 50, 100]"
      item-value="id"
      class="cancelled-contract-table text-no-wrap"
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
        <div class="cancelled-contract-empty-state">
          <TableEmptyState
            icon="tabler-file-off"
            :min-height="0"
            :title="$t('pages.contracts.cancelledContracts.cancelledContracts.noCancelledContractRecords')"
            :description="$t('pages.contracts.cancelledContracts.cancelledContracts.noCancelledContractRecordsDescription')"
          />
        </div>
      </template>
      <template #item.contract="{ item }">
        {{ item.contract || item.contract_template?.name || $t('pages.contracts.allContracts.allContracts.untitledContract') }}
      </template>
      <template #item.counterparty="{ item }">
        {{ item.counterparty || $t('pages.contracts.allContracts.allContracts.unknownCounterparty') }}
      </template>
      <template #item.start_date="{ item }">
        {{ formatLocalizedDate(item.start_date) }}
      </template>
      <template #item.cancelled_at="{ item }">
        {{ item.cancelled_at ? formatLocalizedDate(item.cancelled_at, { dateStyle: 'medium', timeStyle: 'short' }) : $t('common.messages.notAvailable') }}
      </template>
      <template #item.status="{ item }">
        <VChip
          color="error"
          size="small"
          class="font-weight-medium"
        >
          {{ $t('common.statuses.cancelled') }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <VMenu location="bottom end">
          <template #activator="{ props }">
            <VBtn
              v-bind="props"
              icon="tabler-dots-vertical"
              variant="tonal"
              size="small"
              class="cancelled-contract-actions-trigger"
              :aria-label="$t('common.messages.actions')"
            />
          </template>
          <VList density="compact" min-width="220">
            <FullDetailsButton
              :item="item"
              :title="item.contract_number || item.contract"
              menu-action
            />
          </VList>
        </VMenu>
      </template>
    </ResponsiveDataTableServer>
  </VCard>
  <PrintableListReport :title="$t('common.navigation.cancelledContracts')" :columns="reportColumns.map(column => column.title)" :rows="printedRows" />
  </div>
</template>

<style scoped>
.cancelled-contract-print-button {
  background-color: rgba(var(--v-theme-primary), 0.12) !important;
  color: rgb(var(--v-theme-primary)) !important;
}

.cancelled-contract-print-button :deep(.v-btn__content),
.cancelled-contract-print-button :deep(.v-icon) {
  color: rgb(var(--v-theme-primary)) !important;
}

.cancelled-contract-print-button:hover,
.cancelled-contract-print-button:focus-visible {
  background-color: rgba(var(--v-theme-primary), 0.18) !important;
  color: rgb(var(--v-theme-primary)) !important;
}

.cancelled-contract-actions-trigger {
  border-radius: 999px !important;
  background-color: rgba(var(--v-theme-on-surface), .04) !important;
  color: rgba(var(--v-theme-on-surface), .72) !important;
}

.cancelled-contract-actions-trigger:hover,
.cancelled-contract-actions-trigger:focus-visible {
  background-color: rgba(var(--v-theme-on-surface), .08) !important;
}

.cancelled-contract-table :deep(.v-table__wrapper > table) {
  min-inline-size: 900px;
}

.cancelled-contract-empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; min-block-size: 17rem; padding-block: 1.25rem 2rem; padding-inline: 1.25rem; }
.cancelled-contract-empty-state :deep(.table-empty-state) { min-block-size: 0 !important; padding-block: 1rem; }

.cancelled-contract-table :deep(.v-table__wrapper > table > tbody > tr:hover > td) {
  background-color: rgba(var(--v-theme-on-surface), 0.035) !important;
}

.cancelled-contract-table :deep(.v-table__wrapper:has(.table-empty-state)) {
  overflow-x: hidden;
}

.cancelled-contract-table :deep(.v-table__wrapper > table:has(.table-empty-state)) {
  min-inline-size: 100%;
  table-layout: fixed;
}

.cancelled-contract-table :deep(.v-table__wrapper > table:has(.table-empty-state) > tbody > tr:hover > td),
.cancelled-contract-table :deep(.v-table__wrapper > table > tbody > tr.responsive-data-table__skeleton-row:hover > td) {
  background-color: transparent !important;
}

@media (max-width: 959px) {
  .cancelled-contract-table.responsive-data-table--mobile-cards :deep(.v-table__wrapper > table > tbody > tr:hover > td),
  .cancelled-contract-table.responsive-data-table--mobile-cards :deep(.responsive-data-table__mobile-row:hover > td) {
    background-color: transparent !important;
  }

  .cancelled-contract-table :deep(.v-table__wrapper) {
    overflow: clip;
  }

  .cancelled-contract-table :deep(.v-table__wrapper > table) {
    inline-size: 100%;
    min-inline-size: 0;
    table-layout: fixed;
  }

  .cancelled-contract-table :deep(.v-data-table__thead) {
    display: none;
  }

  .cancelled-contract-table :deep(.responsive-data-table__mobile-row > td) {
    padding: 0.75rem;
  }
}
</style>
