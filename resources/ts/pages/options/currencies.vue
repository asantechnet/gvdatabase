<script setup lang="ts">
import CurrencyApi, { type Currency, type CurrencyForm } from '@/api/currency'
import CurrencyFormDialog from '@/components/forms/CurrencyForm.vue'
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'

type StatusFilter = 'all' | 'active' | 'inactive'

const searchQuery = ref('')
const statusFilter = ref<StatusFilter>('all')
const isDialogVisible = ref(false)
const isDeleteDialogVisible = ref(false)
const isBaseCurrencyDialogVisible = ref(false)
const editingId = ref<number | null>(null)
const currencyToDelete = ref<Currency | null>(null)
const currencyToSetAsBase = ref<Currency | null>(null)
const currencies = ref<Currency[]>([])
const isLoading = ref(false)
const toast = useToast()

const defaultFormData: CurrencyForm = {
  name: '',
  code: '',
  symbol: '',
  rate: 1.0,
  isActive: true,
  decimalPlaces: 2,
  isBase: false,
}

const formData = ref<CurrencyForm>({ ...defaultFormData })
const formErrors = ref<Record<string, string>>({})

const currentBaseCurrency = computed(() => {
  return currencies.value.find((c) => c.isBase) || currencies.value[0]
})

const filteredCurrencies = computed(() => {
  return currencies.value.filter((item) => {
    const searchTerm = searchQuery.value.toLowerCase()

    const matchesSearch =
      (item.name && item.name.toLowerCase().includes(searchTerm)) ||
      (item.code && item.code.toLowerCase().includes(searchTerm)) ||
      (item.symbol && item.symbol.includes(searchQuery.value))

    const matchesStatus =
      statusFilter.value === 'all' ||
      (statusFilter.value === 'active' && item.isActive) ||
      (statusFilter.value === 'inactive' && !item.isActive)

    return matchesSearch && matchesStatus
  })
})

const totalCurrenciesCount = computed(() => currencies.value.length)
const activeCurrenciesCount = computed(() => currencies.value.filter((c) => c.isActive).length)

onMounted(() => {
  fetchCurrencies()
})

const fetchCurrencies = async () => {
  try {
    isLoading.value = true
    const response = await CurrencyApi.getAll()
    currencies.value = response.data
  } catch (error: any) {
    toast.error(translate('common.messages.failedToFetchCurrencies'))
    console.error('Error fetching currencies:', error)
  } finally {
    isLoading.value = false
  }
}

const createCurrency = async () => {
  try {
    formErrors.value = {}
    await CurrencyApi.create(formData.value)
    await fetchCurrencies()
    toast.success(translate('pages.finance.currencies.currencies.currencyCreatedSuccessfully'))
    return true
  } catch (error: any) {
    if (error.errors) {
      formErrors.value = error.errors
      Object.entries(error.errors as Record<string, string[]>).forEach(([field, messages]) => {
        toast.error(`${field}: ${messages.join(', ')}`)
      })
    } else {
      toast.error(error.message || 'Failed to create currency')
    }
    console.error('Error creating currency:', error)
    return false
  }
}

const updateCurrency = async () => {
  if (!editingId.value) return false

  try {
    formErrors.value = {}
    await CurrencyApi.update(editingId.value, formData.value)
    // Changing the base recalculates every exchange rate, so the whole list
    // must be refreshed rather than replacing only the edited row.
    await fetchCurrencies()
    toast.success(translate('pages.finance.currencies.currencies.currencyUpdatedSuccessfully'))
    return true
  } catch (error: any) {
    if (error.errors) {
      formErrors.value = error.errors
      Object.entries(error.errors as Record<string, string[]>).forEach(([field, messages]) => {
        toast.error(`${field}: ${messages.join(', ')}`)
      })
    } else {
      toast.error(error.message || 'Failed to update currency')
    }
    console.error('Error updating currency:', error)
    return false
  }
}

const deleteCurrency = async () => {
  if (!currencyToDelete.value) return false

  try {
    const response = await CurrencyApi.delete(Number(currencyToDelete.value.id))
    if (response.success) {
      currencies.value = currencies.value.filter(c => c.id !== currencyToDelete.value?.id)
      toast.success(translate('pages.finance.currencies.currencies.currencyDeletedSuccessfully'))
      return true
    } else {
      toast.error(response.message || 'Failed to delete currency')
      return false
    }
  } catch (error: any) {
    toast.error(error.message || 'Failed to delete currency')
    console.error('Error deleting currency:', error)
    return false
  }
}

const openCreateDialog = () => {
  editingId.value = null
  formData.value = { ...defaultFormData, isBase: false }
  formErrors.value = {}
  isDialogVisible.value = true
}

const openEditDialog = (currency: Currency) => {
  editingId.value = currency.id
  formData.value = {
    name: currency.name,
    code: currency.code,
    symbol: currency.symbol,
    rate: currency.rate,
    isActive: currency.isActive,
    decimalPlaces: currency.decimalPlaces,
    isBase: currency.isBase,
  }
  formErrors.value = {}
  isDialogVisible.value = true
}

const saveCurrency = async (submittedForm?: CurrencyForm & { isBase?: boolean }) => {
  if (submittedForm)
    formData.value = { ...formData.value, ...submittedForm }

  if (!formData.value.name || !formData.value.code) {
    toast.error(translate('pages.finance.currencies.currencies.nameAndCodeAreRequired'))
    return
  }

  const success = editingId.value
    ? await updateCurrency()
    : await createCurrency()

  if (success) {
    isDialogVisible.value = false
  }
}

const setAsBaseCurrency = async (targetId: number) => {
  const target = currencies.value.find((c) => c.id === targetId)
  if (!target || target.isBase) return

  currencyToSetAsBase.value = target
  isBaseCurrencyDialogVisible.value = true
}

const confirmSetAsBaseCurrency = async () => {
  const target = currencyToSetAsBase.value
  if (!target) return

  isBaseCurrencyDialogVisible.value = false

  try {
    await CurrencyApi.update(target.id, { isBase: true })
    await fetchCurrencies()
    toast.success(`Successfully set ${target.code} as base currency`)
  } catch (error: any) {
    toast.error(error.message || 'Failed to set as base currency')
    console.error('Error setting base currency:', error)
  } finally {
    currencyToSetAsBase.value = null
  }
}

const confirmDelete = (currency: Currency) => {
  currencyToDelete.value = currency
  isDeleteDialogVisible.value = true
}

const handleDelete = async () => {
  const success = await deleteCurrency()
  if (success) {
    isDeleteDialogVisible.value = false
    currencyToDelete.value = null
  }
}

const toggleStatus = async (currency: Currency) => {
  if (currency.isBase) return

  try {
    const response = await CurrencyApi.update(Number(currency.id), { isActive: !currency.isActive })
    // Update the currency in the list with the server response
    const index = currencies.value.findIndex(c => c.id === currency.id)
    if (index !== -1) {
      currencies.value[index] = response.data
    }
    toast.success(translate(currency.isActive ? 'pages.finance.currencies.deactivated' : 'pages.finance.currencies.activated'))
  } catch (error: any) {
    toast.error(error.message || 'Failed to update currency status')
    console.error('Error updating currency status:', error)
  }
}
</script>

<template>
  <div class="currencies-page p-4 p-md-6">
    <div class="d-flex flex-column flex-sm-row justify-space-between align-sm-center mb-6 gap-4">
      <div>
        <h2 class="text-h4 font-weight-bold mb-1">{{ $t('pages.finance.currencies.currencies.currencyManagement') }}</h2>
        <p class="text-body-2 text-medium-emphasis mb-0">
          {{ $t('pages.finance.currencies.currencies.manageExchangeRatesSetYourApplicationSPrimaryBaseCurrencyAndToggleStat') }}
        </p>
      </div>
      <v-btn color="primary" prepend-icon="tabler-plus" elevation="2" size="large" @click="openCreateDialog">
        {{ $t('pages.finance.currencies.currencies.addCurrency') }}
      </v-btn>
    </div>

    <v-row class="mb-6">
      <v-col cols="12" sm="6" md="4">
        <v-card class="pa-4" variant="outlined">
          <div class="d-flex align-center justify-space-between">
            <div>
              <span class="text-caption text-uppercase text-medium-emphasis font-weight-medium">
                {{ $t('pages.finance.currencies.currencies.totalCurrencies') }}
              </span>
              <h3 class="text-h4 font-weight-bold mt-1">{{ totalCurrenciesCount }}</h3>
            </div>
            <v-avatar color="primary" variant="tonal" size="48" rounded="lg">
              <v-icon icon="tabler-currency-dollar" size="24" />
            </v-avatar>
          </div>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="4">
        <v-card class="pa-4" variant="outlined">
          <div class="d-flex align-center justify-space-between">
            <div>
              <span class="text-caption text-uppercase text-medium-emphasis font-weight-medium">
                {{ $t('common.messages.baseCurrency') }}
              </span>
              <h3 class="text-h4 font-weight-bold mt-1 text-primary">
                {{ currentBaseCurrency?.code || 'N/A' }} ({{ currentBaseCurrency?.symbol || '' }})
              </h3>
            </div>
            <v-avatar color="success" variant="tonal" size="48" rounded="lg">
              <v-icon icon="tabler-star" size="24" />
            </v-avatar>
          </div>
        </v-card>
      </v-col>

      <v-col cols="12" sm="12" md="4">
        <v-card class="pa-4" variant="outlined">
          <div class="d-flex align-center justify-space-between">
            <div>
              <span class="text-caption text-uppercase text-medium-emphasis font-weight-medium">
                {{ $t('pages.finance.currencies.currencies.activeCurrencies') }}
              </span>
              <h3 class="text-h4 font-weight-bold mt-1">{{ activeCurrenciesCount }}</h3>
            </div>
            <v-avatar color="info" variant="tonal" size="48" rounded="lg">
              <v-icon icon="tabler-check" size="24" />
            </v-avatar>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <v-card variant="outlined" class="overflow-hidden">
      <v-card-text class="pa-4 border-b">
        <v-row density="compact" align="center">
          <v-col cols="12" sm="6" md="4">
            <v-text-field v-model="searchQuery" :placeholder="$t('pages.finance.currencies.currencies.searchByNameShortCodeOrSymbol')"
              prepend-inner-icon="tabler-search" hide-details density="compact" variant="outlined" clearable />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <v-select v-model="statusFilter" :items="[
              { title: translate('pages.finance.currencies.currencies.allStatuses'), value: 'all' },
              { title: translate('pages.finance.currencies.currencies.activeOnly'), value: 'active' },
              { title: translate('pages.finance.currencies.currencies.inactiveOnly'), value: 'inactive' }
            ]" hide-details density="compact" variant="outlined" />
          </v-col>
        </v-row>
      </v-card-text>

      <v-table class="text-no-wrap">
        <thead>
          <tr>
            <th class="text-left font-weight-bold text-uppercase text-caption">{{ $t('common.fields.currency') }}</th>
            <th class="text-left font-weight-bold text-uppercase text-caption">{{ $t('pages.finance.currencies.currencies.symbol') }}</th>
            <th class="text-left font-weight-bold text-uppercase text-caption">
                {{ $t('pages.finance.currencies.rateToBase', { currency: currentBaseCurrency?.code || $t('common.messages.notAvailable') }) }}
            </th>
            <th class="text-left font-weight-bold text-uppercase text-caption">{{ $t('pages.finance.currencies.currencies.decimals') }}</th>
            <th class="text-left font-weight-bold text-uppercase text-caption">{{ $t('common.fields.status') }}</th>
            <th class="text-center font-weight-bold text-uppercase text-caption">{{ $t('common.messages.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="currency in filteredCurrencies" :key="currency.id"
            :class="{ 'bg-var-theme-surface': currency.isBase }">
            <td>
              <div class="d-flex align-center gap-3">
                <div>
                  <div class="font-weight-medium text-body-1">
                    {{ currency.name }}
                  </div>
                  <div class="d-flex align-center gap-2 mt-1">
                    <v-chip size="x-small" color="primary" variant="flat" class="font-weight-bold">
                      {{ currency.code }}
                    </v-chip>
                    <v-chip v-if="currency.isBase" size="x-small" color="warning" variant="tonal"
                      prepend-icon="tabler-star">
                      {{ $t('common.messages.baseCurrency') }}
                    </v-chip>
                  </div>
                </div>
              </div>
            </td>

            <td>
              <code class="text-body-1 font-weight-bold px-2 py-1 rounded bg-light">
                {{ currency.symbol || '—' }}
              </code>
            </td>

            <td>
              <div class="font-weight-medium">
                <span v-if="currency.isBase" class="text-success font-weight-bold">{{ $t('pages.finance.currencies.baseRate') }}</span>
                <span v-else>1 {{ currentBaseCurrency?.code || 'N/A' }} = {{ currency.rate }} {{
                  currency.code }}</span>
              </div>
                <div class="text-caption text-medium-emphasis">{{ $t('pages.finance.currencies.updatedAt', { date: formatLocalizedDate(currency.updatedAt, 'long') }) }}</div>
            </td>

            <td>
                <span class="text-body-2">{{ $t('pages.finance.currencies.decimalPlaces', { count: currency.decimalPlaces }) }}</span>
            </td>

            <td>
              <v-chip :color="currency.isActive ? 'success' : 'secondary'" size="small" variant="tonal"
                class="cursor-pointer" @click="toggleStatus(currency)">
                  {{ translateBackendValue(currency.isActive ? 'active' : 'inactive') }}
              </v-chip>
            </td>

            <td class="text-center">
              <div class="d-flex justify-center align-center gap-1">
                <FullDetailsButton :item="currency" :title="currency.name" />
                <v-tooltip :text="$t('pages.finance.currencies.currencies.setAsBaseCurrency')" location="top" v-if="!currency.isBase">
                  <template #activator="{ props }">
                    <v-btn v-bind="props" icon="tabler-star" size="small" variant="text" color="warning"
                      @click="setAsBaseCurrency(currency.id)" />
                  </template>
                </v-tooltip>

                <v-tooltip :text="$t('pages.finance.currencies.currencies.editCurrency')" location="top">
                  <template #activator="{ props }">
                    <v-btn v-bind="props" icon="tabler-pencil" size="small" variant="text" color="default"
                      @click="openEditDialog(currency)" />
                  </template>
                </v-tooltip>

                <v-tooltip :text="currency.isBase ? 'Cannot delete Base Currency' : 'Delete Currency'" location="top">
                  <template #activator="{ props }">
                    <v-btn v-bind="props" icon="tabler-trash" size="small" variant="text" color="error"
                      :disabled="currency.isBase" @click="confirmDelete(currency)" />
                  </template>
                </v-tooltip>
              </div>
            </td>
          </tr>

          <tr v-if="filteredCurrencies.length === 0">
            <td colspan="6" class="text-center py-8 text-medium-emphasis">
              <v-icon icon="tabler-currency-off" size="40" class="mb-2" />
              <div>{{ $t('pages.finance.currencies.currencies.noCurrenciesFoundMatchingYourSearch') }}</div>
            </td>
          </tr>

          <tr v-if="isLoading">
            <td colspan="6" class="text-center py-8">
              <v-progress-circular indeterminate color="primary" />
              <div class="mt-2">{{ $t('pages.finance.currencies.currencies.loadingCurrencies') }}</div>
            </td>
          </tr>
        </tbody>
      </v-table>
    </v-card>

    <CurrencyFormDialog v-model="isDialogVisible" :initial-data="formData" :current-base-currency="currentBaseCurrency" :errors="formErrors" :loading="isLoading" :editing="Boolean(editingId)" @submit="saveCurrency" />

    <v-dialog v-model="isDeleteDialogVisible" max-width="420">
      <v-card rounded="lg" class="pa-2">
        <v-card-title class="text-h6 font-weight-bold">{{ $t('pages.finance.currencies.currencies.deleteCurrency') }}</v-card-title>
        <v-card-text class="pt-2">
          {{ $t('common.confirmations.areYouSureYouWantToDelete') }}
          <strong>{{ currencyToDelete?.name }} ({{ currencyToDelete?.code }})</strong>{{ $t('common.confirmations.thisActionCannotBeUndone2') }}
        </v-card-text>
        <v-card-actions class="justify-end gap-2 pa-4">
          <v-btn variant="outlined" color="secondary" @click="isDeleteDialogVisible = false">{{ $t('common.actions.cancel') }}</v-btn>
          <v-btn color="error" elevation="1" @click="handleDelete">{{ $t('common.actions.delete') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="isBaseCurrencyDialogVisible" max-width="460">
      <v-card rounded="lg" class="pa-2">
        <v-card-title class="d-flex align-center ga-2 text-h6 font-weight-bold">
          <v-avatar color="warning" variant="tonal" size="36">
            <v-icon icon="tabler-star" size="20" />
          </v-avatar>
          <span>Change base currency?</span>
        </v-card-title>
        <v-card-text class="pt-3">
          <v-alert type="warning" variant="tonal" density="comfortable" class="mb-3">
            Dashboard and report totals will be displayed in
            <strong>{{ currencyToSetAsBase?.code }}</strong>.
          </v-alert>
          This will update the base currency for the application.
        </v-card-text>
        <v-card-actions class="justify-end gap-2 pa-4">
          <v-btn variant="outlined" color="secondary" @click="isBaseCurrencyDialogVisible = false">
            {{ $t('common.actions.cancel') }}
          </v-btn>
          <v-btn color="warning" variant="flat" @click="confirmSetAsBaseCurrency">
            Set as base currency
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.gap-1 {
  gap: 0.25rem;
}

.gap-2 {
  gap: 0.5rem;
}

.gap-3 {
  gap: 0.75rem;
}

.gap-4 {
  gap: 1rem;
}

.cursor-pointer {
  cursor: pointer;
}
</style>
