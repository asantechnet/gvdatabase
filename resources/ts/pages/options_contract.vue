<script setup lang="ts">
import { ref } from 'vue'

// Placeholder types and data
interface ContractOption {
  id: number
  name: string
  type: string
  description: string
  active: boolean
}

const contractOptions = ref<ContractOption[]>([
  { id: 1, name: 'NDA Template', type: 'NDA', description: 'Standard NDA agreement', active: true },
  { id: 2, name: 'SLA Template', type: 'SLA', description: 'Service level agreement', active: true },
  { id: 3, name: 'Employment Contract', type: 'Employment', description: 'Standard employment terms', active: false },
])

// Placeholder reactive states
const searchQuery = ref('')
const filter1 = ref<string | null>(null)
const filter2 = ref<string | null>(null)
const isLoading = ref(false)

// Placeholder filter options
const typeOptions = ['NDA', 'SLA', 'Employment', 'Partnership']
const statusOptions = ['Active', 'Inactive']
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
              placeholder="Search contract options..."
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
          :items="contractOptions"
          :headers="[
            { title: 'ID', key: 'id' },
            { title: 'Name', key: 'name' },
            { title: 'Type', key: 'type' },
            { title: 'Description', key: 'description' },
            { title: 'Active', key: 'active' },
            { title: 'Actions', key: 'actions', sortable: false }
          ]"
          item-value="id"
          class="text-no-wrap"
          :loading="isLoading"
          mobile-breakpoint="sm"
          density="compact"
        >
          <template #item.active="{ item }">
            <VChip
              size="small"
              :color="item.active ? 'success' : 'default'"
              variant="tonal"
            >
              {{ item.active ? 'Yes' : 'No' }}
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
