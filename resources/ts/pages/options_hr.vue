<script setup lang="ts">
import { translate } from '@/utils/localization'
import { ref } from 'vue'

// Placeholder types and data
interface HROption {
  id: number
  name: string
  category: string
  description: string
  active: boolean
}

const hrOptions = ref<HROption[]>([
  { id: 1, name: 'Leave Policy', category: 'Policies', description: translate('pages.settings.optionsHr.optionsHr.standardLeavePolicy'), active: true },
  { id: 2, name: 'Work Hours', category: 'Settings', description: translate('pages.settings.optionsHr.optionsHr.defaultWorkHours'), active: true },
  { id: 3, name: 'Holiday Calendar', category: 'Policies', description: translate('pages.settings.optionsHr.optionsHr.companyHolidays'), active: true },
])

// Placeholder reactive states
const searchQuery = ref('')
const filter1 = ref<string | null>(null)
const filter2 = ref<string | null>(null)
const isLoading = ref(false)

// Placeholder filter options
const categoryOptions = ['Policies', 'Settings', 'Benefits', 'Compliance']
const statusOptions = computed(() => [{ title: translate('serviceContracts.active'), value: 'Active' }, { title: translate('common.statuses.inactive'), value: 'Inactive' }])
</script>

<template>
  <div>
    <DataTablePageKpis
      :items="hrOptions"
      :title="$t('components.dataTablePageKpis.pages.hrOptions.title')"
      :description="$t('components.dataTablePageKpis.pages.hrOptions.description')"
      icon="tabler-users-group"
      status-key="active"
    />
    <VCard class="mb-6">
      <VCardText>
        <VRow align="center">
          <VCol
            cols="12"
            md="8"
          >
            <VTextField
              v-model="searchQuery"
              class="data-table-search"
              prepend-inner-icon="tabler-search"
              :placeholder="$t('pages.settings.optionsHr.optionsHr.searchHROptions')"
              density="compact"
              clearable
              hide-details
            />
          </VCol>

          <VCol
            cols="12"
            md="4"
            class="d-flex justify-md-end"
          >
            <DataTableFilterMenu
              :active="Boolean(filter1 || filter2)"
              @reset="filter1 = null; filter2 = null"
            >
              <VSelect
                v-model="filter1"
                :items="categoryOptions"
                :label="$t('common.messages.filterByCategory')"
                density="compact"
                clearable
                hide-details
              />
              <VSelect
                v-model="filter2"
                :items="statusOptions"
                :label="$t('common.messages.filterByStatus')"
                density="compact"
                clearable
                hide-details
              />
            </DataTableFilterMenu>
            <VBtn color="primary">
              <VIcon
                icon="tabler-plus"
                class="me-1"
              />
              {{ $t('common.messages.addNew') }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <div class="table-container">
        <ResponsiveDataTable
          :items="hrOptions"
          :headers="[
            { title: translate('common.messages.id'), key: 'id' },
            { title: translate('common.fields.name'), key: 'name' },
            { title: translate('common.fields.category'), key: 'category' },
            { title: translate('common.fields.description'), key: 'description' },
            { title: translate('common.statuses.active'), key: 'active' },
            { title: translate('common.messages.actions'), key: 'actions', sortable: false },
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

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <FullDetailsButton
                :item="item"
                :title="item.name"
              />
              <VBtn
                icon="tabler-edit"
                variant="text"
                size="small"
                color="medium-emphasis"
              />
              <VBtn
                icon="tabler-trash"
                variant="text"
                size="small"
                color="error"
              />
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
