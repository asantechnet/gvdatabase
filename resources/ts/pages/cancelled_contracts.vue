<script setup lang="ts">
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import PrintableListReport from '@/components/PrintableListReport.vue'
import { printDocument } from '@/utils/printDocument'
import { computed, onMounted, ref } from 'vue'
import { VAlert } from 'vuetify/components'
import { useContracts } from '@/composables/useContracts'

const {
  contracts,
  isLoading,
  error,
  fetchContracts,
} = useContracts()

const dateFrom = ref('')
const dateTo = ref('')
const search = ref('')
const filterCounterparty = ref<string | null>(null)

const counterparties = computed(() => [...new Set(contracts.value.map(c => c.counterparty).filter(c => c))])

const filtered = computed(() => {
  return contracts.value.filter(c => {
    const contractName = c.contract || c.contract_template?.name || ''
    const counterpartyName = c.counterparty || ''

    const matchesSearch = contractName.toLowerCase().includes(search.value.toLowerCase())
      || counterpartyName.toLowerCase().includes(search.value.toLowerCase())
      || (c.contract_number || '').toLowerCase().includes(search.value.toLowerCase())

    const matchesCounterparty = !filterCounterparty.value || c.counterparty === filterCounterparty.value

    return matchesSearch && matchesCounterparty
  })
})

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

const reportValue = (row: (typeof contracts.value)[number], key: string): string | number => {
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
// cancellation date, without duplicating the row shape by hand.
const reportRows = computed(() => filtered.value.map(row => reportColumns.value.map(column => reportValue(row, column.key))))

const printTable = () => {
  printDocument({ bodyClass: 'printing-table-cancelled-contracts', pageStyle: 'size: A4 landscape; margin: 12mm;' })
}

onMounted(() => {
  fetchContracts({ only_cancelled: true })
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
        @reset="search = ''; filterCounterparty = null; dateFrom = ''; dateTo = ''; fetchContracts({ only_cancelled: true })"
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
        />
        <ErpDateRange v-model:from="dateFrom" v-model:to="dateTo" class="mb-2" @apply="fetchContracts({ only_cancelled: true, date_from: dateFrom || undefined, date_to: dateTo || undefined })" />
        <VSelect
          v-model="filterCounterparty"
          :items="counterparties"
          :label="$t('pages.contracts.allContracts.allContracts.filterByCounterparty')"
          density="compact"
          clearable
          hide-details
          style="min-width: 200px;"
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

    <ResponsiveDataTable
      :headers="headers"
      :items="filtered"
      item-value="id"
      class="text-no-wrap"
      mobile-breakpoint="sm"
      :loading="isLoading"
      :loading-text="$t('pages.contracts.cancelledContracts.cancelledContracts.loadingCancelledContracts')"
      :no-data-text="$t('pages.contracts.cancelledContracts.cancelledContracts.noContracts')"
      style="overflow-x: auto;"
    >
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
    </ResponsiveDataTable>
  </VCard>
  <PrintableListReport :title="$t('common.navigation.cancelledContracts')" :columns="reportColumns.map(column => column.title)" :rows="reportRows" />
  </div>
</template>
