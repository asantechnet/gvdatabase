<script setup lang="ts">
import { onMounted } from 'vue'
import { useContracts } from '@/composables/useContracts'
import { computed, ref } from 'vue'
import { VAlert } from 'vuetify/components'

const {
  contracts,
  isLoading,
  error,
  fetchContracts,
} = useContracts()

const search = ref('')
const filterClient = ref<string | null>(null)

const clients = computed(() => [...new Set(contracts.value.map(c => c.counterparty).filter(c => c))])

const filtered = computed(() => {
  return contracts.value.filter(c => {
    const contractName = c.contract || c.contract_template?.name || ''
    const counterpartyName = c.counterparty || ''
    const matchesSearch = contractName.toLowerCase().includes(search.value.toLowerCase()) ||
      counterpartyName.toLowerCase().includes(search.value.toLowerCase()) ||
      (c.contract_number || '').toLowerCase().includes(search.value.toLowerCase())
    const matchesClient = !filterClient.value || c.counterparty === filterClient.value
    return matchesSearch && matchesClient
  })
})

const headers = [
  { title: 'Contract Number', key: 'contract_number', sortable: true },
  { title: 'Contract', key: 'contract', sortable: true },
  { title: 'Client', key: 'counterparty', sortable: true },
  { title: 'Start Date', key: 'start_date', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
]

const printTable = () => {
  window.print()
}

onMounted(() => {
  fetchContracts(true)
})
</script>

<template>
  <VCard>
    <VCardText>
      <VRow align="center">
        <VCol cols="12" md="4">
          <VTextField v-model="search" prepend-inner-icon="tabler-search" placeholder="Search cancelled contracts..."
            density="compact" clearable hide-details />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect v-model="filterClient" :items="clients" label="Filter by Client" density="compact" clearable
            hide-details />
        </VCol>
        <VCol cols="12" md="2" class="text-end">
          <VBtn icon="tabler-printer" variant="text" @click="printTable" />
        </VCol>
      </VRow>
    </VCardText>

    <VAlert v-if="error" type="error" class="ma-4">
      {{ error }}
    </VAlert>

    <VDataTable :headers="headers" :items="filtered" item-value="id" class="text-no-wrap" mobile-breakpoint="sm"
      :loading="isLoading" loading-text="Loading cancelled contracts..." style="overflow-x: auto;">
      <template #item.contract="{ item }">
        {{ item.contract || item.contract_template?.name || 'Untitled Contract' }}
      </template>
      <template #item.counterparty="{ item }">
        {{ item.counterparty || 'Unknown Counterparty' }}
      </template>
      <template #item.start_date="{ item }">
        {{ item.start_date ? new Date(item.start_date).toLocaleDateString() : 'N/A' }}
      </template>
      <template #item.status="{ item }">
        <VChip color="error" size="small" class="font-weight-medium">
          Cancelled
        </VChip>
      </template>
    </VDataTable>
  </VCard>
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
