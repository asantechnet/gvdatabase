<script setup lang="ts">
import CurrencyApi, { type Currency, type CurrencyForm } from '@/api/currency'
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'

type StatusFilter = 'all' | 'active' | 'inactive'

const searchQuery = ref('')
const statusFilter = ref<StatusFilter>('all')
const isDialogVisible = ref(false)
const isDeleteDialogVisible = ref(false)
const editingId = ref<number | null>(null)
const currencyToDelete = ref<Currency | null>(null)
const currencies = ref<Currency[]>([])
const isLoading = ref(false)
const toast = useToast()

const defaultFormData: CurrencyForm = {
  name: '',
  code: '',
  symbol: '',
  rateToBase: 1.0,
  isActive: true,
  decimalPlaces: 2,
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
    rateToBase: currency.rateToBase,
    isActive: currency.isActive,
    decimalPlaces: currency.decimalPlaces,
    isBase: currency.isBase,
  }
  formErrors.value = {}
  isDialogVisible.value = true
}

const saveCurrency = async () => {
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

  try {
    await CurrencyApi.update(targetId, { isBase: true })
    await fetchCurrencies()
    toast.success(`Successfully set ${target.code} as base currency`)
  } catch (error: any) {
    toast.error(error.message || 'Failed to set as base currency')
    console.error('Error setting base currency:', error)
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
                <span v-else>1 {{ currentBaseCurrency?.code || 'N/A' }} = {{ currency.rateToBase }} {{
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

    <v-dialog v-model="isDialogVisible" max-width="540">
      <v-card rounded="lg">
        <v-card-title class="pa-5 d-flex justify-space-between align-center">
          <span class="text-h5 font-weight-bold">
            {{ editingId ? 'Edit Currency' : 'Add New Currency' }}
          </span>
          <v-btn icon="tabler-x" variant="text" density="compact" @click="isDialogVisible = false" />
        </v-card-title>

        <v-divider />

        <v-card-text class="pa-5">
          <v-form @submit.prevent="saveCurrency">
            <v-row density="comfortable">
              <v-col cols="12" sm="8">
                <v-text-field v-model="formData.name" :label="$t('pages.finance.currencies.currencies.currencyName')" :placeholder="$t('pages.finance.currencies.currencies.eGAustralianDollar')"
                  variant="outlined" required :error-messages="formErrors.name" />
              </v-col>

              <v-col cols="12" sm="4">
                <v-text-field v-model="formData.code" :label="$t('pages.finance.currencies.currencies.codeShortName')" :placeholder="$t('pages.finance.currencies.currencies.eGAUD')"
                  variant="outlined" maxlength="5" required :error-messages="formErrors.code" />
              </v-col>

              <v-col cols="12" sm="6">
                <v-text-field v-model="formData.symbol" :label="$t('pages.finance.currencies.currencies.symbol')" :placeholder="$t('pages.finance.currencies.symbolExample')" variant="outlined"
                  :error-messages="formErrors.symbol" />
              </v-col>

              <v-col cols="12" sm="6">
                <v-text-field v-model.number="formData.decimalPlaces" :label="$t('pages.finance.currencies.currencies.decimalPrecision')" type="number" min="0"
                  max="6" variant="outlined" :error-messages="formErrors.decimal_places" />
              </v-col>

              <v-col cols="12">
                <v-text-field v-model.number="formData.rateToBase"
              :label="$t('pages.finance.currencies.rateToBase', { currency: currentBaseCurrency?.code || $t('common.messages.notAvailable') })" placeholder="1.0" type="number"
                  step="0.000001" min="0" variant="outlined" :disabled="formData.isBase"
                  :hint="formData.isBase ? 'Base currency rate is always 1.0' : `1 ${currentBaseCurrency?.code || 'N/A'} equals how many units of this currency?`"
                  persistent-hint :error-messages="formErrors.rate_to_base" />
              </v-col>

              <v-col cols="12" sm="6" class="pt-2">
                <v-switch v-model="formData.isBase" :label="$t('pages.finance.currencies.currencies.setAsPrimaryBase')" color="primary" hide-details
                  :error-messages="formErrors.is_base" />
              </v-col>

              <v-col cols="12" sm="6" class="pt-2">
                <v-switch v-model="formData.isActive" :label="$t('pages.finance.currencies.currencies.activeStatus')" color="success" :disabled="formData.isBase"
                  hide-details :error-messages="formErrors.is_active" />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider />

        <v-card-actions class="pa-4 justify-end gap-2">
          <v-btn variant="outlined" color="secondary" @click="isDialogVisible = false">{{ $t('common.actions.cancel') }}</v-btn>
          <v-btn color="primary" elevation="1" @click="saveCurrency">
            {{ editingId ? 'Save Changes' : 'Create Currency' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

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
