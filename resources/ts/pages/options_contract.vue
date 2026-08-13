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
  { id: 1, name: 'NDA Template', type: 'NDA', description: translate('pages.contracts.optionsContract.optionsContract.standardNDAAgreement'), active: true },
  { id: 2, name: 'SLA Template', type: 'SLA', description: translate('pages.contracts.optionsContract.optionsContract.serviceLevelAgreement'), active: true },
  { id: 3, name: 'Employment Contract', type: 'Employment', description: translate('pages.contracts.optionsContract.optionsContract.standardEmploymentTerms'), active: false },
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
              :placeholder="$t('pages.contracts.optionsContract.optionsContract.searchContractOptions')"
              density="compact"
              clearable
              hide-details
            />
          </VCol>

          <VCol cols="12" sm="6" md="2">
            <VSelect
              v-model="filter1"
              :items="typeOptions"
              :label="$t('common.messages.filterByType')"
              density="compact"
              clearable
              hide-details
            />
          </VCol>

          <VCol cols="12" sm="6" md="2">
            <VSelect
              v-model="filter2"
              :items="statusOptions"
              :label="$t('common.messages.filterByStatus')"
              density="compact"
              clearable
              hide-details
            />
          </VCol>

          <VCol cols="12" md="4" class="d-flex justify-md-end">
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
        <VDataTable
          :items="contractOptions"
          :headers="[
            { title: translate('common.messages.id'), key: 'id' },
            { title: translate('common.fields.name'), key: 'name' },
            { title: translate('common.fields.type'), key: 'type' },
            { title: translate('common.fields.description'), key: 'description' },
            { title: translate('common.statuses.active'), key: 'active' },
            { title: translate('common.messages.actions'), key: 'actions', sortable: false }
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
