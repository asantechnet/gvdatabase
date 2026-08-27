<script setup lang="ts">
import { ref } from 'vue'

// Placeholder types and data
interface FinanceOption {
  id: number
  name: string
  category: string
  description: string
  default: boolean
}

const financeOptions = ref<FinanceOption[]>([
  { id: 1, name: 'Default Tax Rate', category: 'Tax', description: translate('pages.finance.optionsFinance.optionsFinance.standardTaxRate'), default: true },
  { id: 2, name: 'Payment Terms', category: 'Payment', description: translate('pages.finance.optionsFinance.optionsFinance.standardPaymentTerms'), default: true },
  { id: 3, name: 'Currency', category: 'General', description: translate('pages.finance.optionsFinance.optionsFinance.defaultCurrency'), default: true },
])

// Placeholder reactive states
const searchQuery = ref('')
const filter1 = ref<string | null>(null)
const filter2 = ref<string | null>(null)
const isLoading = ref(false)

// Placeholder filter options
const categoryOptions = ['Tax', 'Payment', 'General', 'Accounting']
const statusOptions = ['Default', 'Custom']
</script>

<template>
  <div>
    <DataTablePageKpis :items="financeOptions" title="Finance options" description="Keep financial defaults organized and ready for everyday operations." icon="tabler-settings-dollar" status-key="active" />
    <VCard class="mb-6">
      <VCardText>
        <VRow align="center">
          <VCol cols="12" md="8">
            <VTextField
              v-model="searchQuery"
              class="data-table-search" prepend-inner-icon="tabler-search"
              :placeholder="$t('pages.finance.optionsFinance.optionsFinance.searchFinanceOptions')"
              density="compact"
              clearable
              hide-details
            />
          </VCol>

          <VCol cols="12" md="4" class="d-flex justify-md-end">
            <DataTableFilterMenu :active="Boolean(filter1 || filter2)" @reset="filter1 = null; filter2 = null">
              <VSelect v-model="filter1" :items="categoryOptions" :label="$t('common.messages.filterByCategory')" density="compact" clearable hide-details />
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
          :items="financeOptions"
          :headers="[
            { title: translate('common.messages.id'), key: 'id' },
            { title: translate('common.fields.name'), key: 'name' },
            { title: translate('common.fields.category'), key: 'category' },
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
