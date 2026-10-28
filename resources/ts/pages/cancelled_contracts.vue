<script setup lang="ts">
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
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
const counterparties = ref<string[]>([])
// Filled from every matching contract right before printing.
const printedRows = ref<(string | number)[][]>([])

const listParams = (): ContractParams => ({
  only_cancelled: true,
  search: search.value || undefined,
  counterparty: filterCounterparty.value || undefined,
  date_from: dateFrom.value || undefined,
  date_to: dateTo.value || undefined,
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

function updateTableOptions(options: { page: number; itemsPerPage: number }) {
  if (page.value === options.page && perPage.value === options.itemsPerPage)
    return

  page.value = options.page
  perPage.value = options.itemsPerPage
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
    <!-- List actions live in the card's own toolbar corner, so the page header
         stays a plain title and the icons sit with the table (not mid-page). -->
    <VCardText class="d-flex align-center justify-end flex-wrap ga-2">
      <DataTableFilterMenu
        :active="Boolean(search || filterCounterparty || dateFrom || dateTo)"
        @reset="search = ''; filterCounterparty = null; dateFrom = ''; dateTo = ''; reload()"
      >
        <VTextField
          v-model="search"
          class="data-table-search"
          prepend-inner-icon="tabler-search"
          :placeholder="$t('pages.contracts.cancelledContracts.cancelledContracts.searchCancelledContracts')"
          density="compact"
          clearable
          hide-details
          style="min-width: 280px;"
          @update:model-value="debouncedSearch"
        />
        <ErpDateRange v-model:from="dateFrom" v-model:to="dateTo" class="mb-2" @apply="reload" />
        <VSelect
          v-model="filterCounterparty"
          :items="counterparties"
          :label="$t('pages.contracts.allContracts.allContracts.filterByCounterparty')"
          density="compact"
          clearable
          hide-details
          style="min-width: 200px;"
          @update:model-value="reload"
        />
      </DataTableFilterMenu>
      <VBtn
        icon="tabler-printer"
        variant="text"
        class="flex-shrink-0"
        @click="printTable"
      />
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
      <template #no-data><TableEmptyState icon="tabler-file-off" :description="$t('pages.contracts.cancelledContracts.cancelledContracts.noContracts')" /></template>
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
        <FullDetailsButton
          :item="item"
          :title="item.contract_number || item.contract"
        />
      </template>
    </ResponsiveDataTableServer>
  </VCard>
  <PrintableListReport :title="$t('common.navigation.cancelledContracts')" :columns="reportColumns.map(column => column.title)" :rows="printedRows" />
  </div>
</template>
