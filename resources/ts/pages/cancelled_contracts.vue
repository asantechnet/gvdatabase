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
const filterClient = ref<string | null>(null)

const clients = computed(() => [...new Set(contracts.value.map(c => c.counterparty).filter(c => c))])

const filtered = computed(() => {
  return contracts.value.filter(c => {
    const contractName = c.contract || c.contract_template?.name || ''
    const counterpartyName = c.counterparty || ''

    const matchesSearch = contractName.toLowerCase().includes(search.value.toLowerCase())
      || counterpartyName.toLowerCase().includes(search.value.toLowerCase())
      || (c.contract_number || '').toLowerCase().includes(search.value.toLowerCase())

    const matchesClient = !filterClient.value || c.counterparty === filterClient.value

    return matchesSearch && matchesClient
  })
})

const headers = computed(() => [
  { title: translate('common.messages.contractNumber'), key: 'contract_number', sortable: true },
  { title: translate('common.messages.contract'), key: 'contract', sortable: true },
  { title: translate('common.messages.client'), key: 'counterparty', sortable: true },
  { title: translate('common.fields.startDate'), key: 'start_date', sortable: true },
  { title: translate('common.fields.status'), key: 'status', sortable: true },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

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
    <VCardText>
      <VRow align="center">

        <VCol
          cols="12"
          md="3"
          class="text-end d-flex justify-end ga-2"
        >
          <DataTableFilterMenu
            :active="Boolean(search || filterClient || dateFrom || dateTo)"
            @reset="search = ''; filterClient = null; dateFrom = ''; dateTo = ''; fetchContracts({ only_cancelled: true })"
          >
            <VTextField
            v-model="search"
            class="data-table-search"
            prepend-inner-icon="tabler-search"
            :placeholder="$t('pages.contracts.cancelledContracts.cancelledContracts.searchCancelledContracts')"
            density="compact"
            clearable
            hide-details
          />
            <ErpDateRange v-model:from="dateFrom" v-model:to="dateTo" class="mb-4" @apply="fetchContracts({ only_cancelled: true, date_from: dateFrom || undefined, date_to: dateTo || undefined })" />
            <VSelect
              v-model="filterClient"
              :items="clients"
              :label="$t('pages.contracts.cancelledContracts.cancelledContracts.filterByClient')"
              density="compact"
              clearable
              hide-details
            />
          </DataTableFilterMenu>
          <VBtn
            icon="tabler-printer"
            variant="text"
            @click="printTable"
          />
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
  <PrintableListReport :title="$t('common.navigation.cancelledContracts')" :columns="headers.slice(0, -1).map(header => header.title)" :rows="filtered.map(row => [row.contract_number, row.contract, row.counterparty, row.start_date, row.status])" />
  </div>
</template>
