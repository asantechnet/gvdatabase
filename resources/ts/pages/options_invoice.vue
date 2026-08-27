<script setup lang="ts">
import { ref } from 'vue'

// Placeholder types and data
interface InvoiceOption {
  id: number
  name: string
  type: string
  description: string
  default: boolean
}

const invoiceOptions = ref<InvoiceOption[]>([
  { id: 1, name: 'Standard Invoice', type: 'Standard', description: translate('pages.finance.optionsInvoice.optionsInvoice.defaultInvoiceTemplate'), default: true },
  { id: 2, name: 'Proforma Invoice', type: 'Proforma', description: translate('pages.finance.optionsInvoice.optionsInvoice.prePaymentInvoice'), default: false },
  { id: 3, name: 'Credit Note', type: 'Credit Note', description: translate('pages.finance.optionsInvoice.optionsInvoice.refundInvoice'), default: false },
])

// Placeholder reactive states
const searchQuery = ref('')
const filter1 = ref<string | null>(null)
const filter2 = ref<string | null>(null)
const isLoading = ref(false)

// Placeholder filter options
const typeOptions = ['Standard', 'Proforma', 'Credit Note']
const statusOptions = ['Default', 'Custom']
</script>

<template>
  <div>
    <DataTablePageKpis :items="invoiceOptions" title="Invoice options" description="Configure reusable billing options and keep invoice workflows consistent." icon="tabler-file-invoice" status-key="active" />
    <VCard class="mb-6">
      <VCardText>
        <VRow align="center">
          <VCol cols="12" md="8">
            <VTextField
              v-model="searchQuery"
              class="data-table-search" prepend-inner-icon="tabler-search"
              :placeholder="$t('pages.finance.optionsInvoice.optionsInvoice.searchInvoiceOptions')"
              density="compact"
              clearable
              hide-details
            />
          </VCol>

          <VCol cols="12" md="4" class="d-flex justify-md-end">
            <DataTableFilterMenu :active="Boolean(filter1 || filter2)" @reset="filter1 = null; filter2 = null">
              <VSelect v-model="filter1" :items="typeOptions" :label="$t('common.messages.filterByType')" density="compact" clearable hide-details />
              <VSelect v-model="filter2" :items="statusOptions" :label="$t('common.messages.filterByStatus')" density="compact" clearable hide-details />
            </DataTableFilterMenu>
            <VBtn color="primary">
              <VIcon icon="tabler-plus" class="me-1" />
              {{ $t('common.messages.addNew') }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <div class="table-container">
        <ResponsiveDataTable
          :items="invoiceOptions"
          :headers="[
            { title: translate('common.messages.id'), key: 'id' },
            { title: translate('common.fields.name'), key: 'name' },
            { title: translate('common.fields.type'), key: 'type' },
            { title: translate('common.fields.description'), key: 'description' },
            { title: translate('common.messages.default'), key: 'default' },
            { title: translate('common.messages.actions'), key: 'actions', sortable: false }
          ]"
          item-value="id"
          class="text-no-wrap"
          :loading="isLoading"
          mobile-breakpoint="sm"
          density="compact"
        >
          <template #item.default="{ item }">
            <VChip
              size="small"
              :color="item.default ? 'success' : 'default'"
              variant="tonal"
            >
              {{ item.default ? 'Yes' : 'No' }}
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <FullDetailsButton :item="item" :title="item.name" />
              <VBtn icon="tabler-edit" variant="text" size="small" color="medium-emphasis" />
              <VBtn icon="tabler-trash" variant="text" size="small" color="error" />
            </div>
          </template>
        </ResponsiveDataTable>
      </div>
    </VCard>
  </div>
</template>

<style scoped>
.table-container {
  overflow-x: auto;
}
</style>
