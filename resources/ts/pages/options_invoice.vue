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
  { id: 1, name: 'Standard Invoice', type: 'Standard', description: 'Default invoice template', default: true },
  { id: 2, name: 'Proforma Invoice', type: 'Proforma', description: 'Pre-payment invoice', default: false },
  { id: 3, name: 'Credit Note', type: 'Credit Note', description: 'Refund invoice', default: false },
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
    <VCard class="mb-6">
      <VCardText>
        <VRow align="center">
          <VCol cols="12" md="4">
            <VTextField
              v-model="searchQuery"
              prepend-inner-icon="tabler-search"
              placeholder="Search invoice options..."
              density="compact"
              clearable
              hide-details
            />
          </VCol>

          <VCol cols="12" sm="6" md="2">
            <VSelect
              v-model="filter1"
              :items="typeOptions"
              label="Filter by Type"
              density="compact"
              clearable
              hide-details
            />
          </VCol>

          <VCol cols="12" sm="6" md="2">
            <VSelect
              v-model="filter2"
              :items="statusOptions"
              label="Filter by Status"
              density="compact"
              clearable
              hide-details
            />
          </VCol>

          <VCol cols="12" md="4" class="d-flex justify-md-end">
            <VBtn color="primary">
              <VIcon icon="tabler-plus" class="me-1" />
              Add New
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <div class="table-container">
        <VDataTable
          :items="invoiceOptions"
          :headers="[
            { title: 'ID', key: 'id' },
            { title: 'Name', key: 'name' },
            { title: 'Type', key: 'type' },
            { title: 'Description', key: 'description' },
            { title: 'Default', key: 'default' },
            { title: 'Actions', key: 'actions', sortable: false }
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

          <template #item.actions>
            <div class="d-flex gap-1">
              <VBtn icon="tabler-edit" variant="text" size="small" color="medium-emphasis" />
              <VBtn icon="tabler-trash" variant="text" size="small" color="error" />
            </div>
          </template>
        </VDataTable>
      </div>
    </VCard>
  </div>
</template>

<style scoped>
.table-container {
  overflow-x: auto;
}
</style>
