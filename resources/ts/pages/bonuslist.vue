<script setup lang="ts">
import { translate } from '@/utils/localization'
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import BonusListApi, { type BonusType, type WorthyEmployee } from '@/api/bonuslist'
import EmployeeApi, { type Employee } from '@/api/employee'
import { apiErrorMessage, apiFieldErrors } from '@/utils/apiErrors'
import { canAccess } from '@/utils/authorization'
import BonusRulesDialog from '@/components/bonus/BonusRulesDialog.vue'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import ConfirmDialog from '@/components/dialogs/ConfirmDialog.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import ResponsiveDataTable from '@/components/responsive-data-table/ResponsiveDataTable.vue'

const toast = useToast()

// --------------------------------------------------------------------------
// State & Mock Data
// --------------------------------------------------------------------------
const searchQuery = ref('')
const departmentFilter = ref<string>('All')
const reasonFilter = ref<string>('All')
const statusFilter = ref<string>('All')
const monthFilter = ref<string>('All')

const isNominateDialogVisible = ref(false)
const isConfirmGrantVisible = ref(false)
const isCalculationDialogVisible = ref(false)
const isRulesDialogVisible = ref(false)
const selectedCalculation = ref<WorthyEmployee | null>(null)
const selectedForGrant = ref<WorthyEmployee | null>(null)
const grantError = ref('')
const granting = ref(false)
const grantAmount = computed(() => Number(selectedForGrant.value?.selectedAmount ?? 0))
const grantAmountInvalid = computed(() => !selectedForGrant.value || !Number.isFinite(grantAmount.value) || grantAmount.value <= 0)
const worthyEmployees = ref<WorthyEmployee[]>([])
const employees = ref<Employee[]>([])
const isLoadingEmployees = ref(false)
const isLoadingNominations = ref(true)
const nominationErrors = ref<Record<string, string>>({})
const filterMenuOpen = ref(false)
const dismissTarget = ref<WorthyEmployee | null>(null)
const bonusLoadingHeaders = computed(() => [
  { title: translate('pages.bonuses.bonuslist.bonuslist.candidate'), key: 'candidate' },
  { title: translate('pages.bonuses.bonuslist.bonuslist.eligibilityAchievements'), key: 'achievement' },
  { title: translate('common.messages.bonusMonth'), key: 'month' },
  { title: translate('pages.bonuses.bonuslist.bonuslist.selectedPayout'), key: 'amount' },
  { title: translate('common.ui.paymentCurrency'), key: 'currency' },
  { title: translate('common.ui.bookedRate'), key: 'rate' },
  { title: translate('common.fields.status'), key: 'status' },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

// Options for Dropdowns
const departments = ['Engineering', 'Product Design', 'Sales & Marketing', 'Operations', 'Finance']
const bonusTypes: BonusType[] = ['Performance', 'Customer Appreciation', 'Task Completion', 'Work Anniversary', 'Manager Recognition']
const quickAmounts = [500, 1000, 1500, 2500, 4000, 5000]

// Generate month options for the last 12 months
const monthOptions = computed(() => {
  const options = []
  const currentDate = new Date()

  // Add "All" option first
  options.push({ value: 'All', title: translate('common.messages.allMonths') })

  for (let i = 0; i < 12; i++) {
    const date = new Date(currentDate)

    date.setMonth(currentDate.getMonth() - i)

    const yearMonth = date.toISOString().slice(0, 7)
    const displayText = formatLocalizedDate(date, { month: 'long', year: 'numeric' })

    options.push({ value: yearMonth, title: displayText })
  }

  return options
})

const defaultNomination = {
  employeeId: null as number | null,
  achievementDetails: '',
  suggestedAmount: 1000,
  selectedAmount: 1000,
  selectedType: 'Manager Recognition' as BonusType,
  bonusMonth: new Date().toISOString().slice(0, 7), // Current month in YYYY-MM format
}

const nominationForm = ref({ ...defaultNomination })

const resetNominationForm = () => {
  nominationForm.value = { ...defaultNomination }
}

// --------------------------------------------------------------------------
// API Integration
// --------------------------------------------------------------------------
const fetchWorthyEmployees = async () => {
  isLoadingNominations.value = true
  try {
    const response = await BonusListApi.getAll()

    worthyEmployees.value = response.data.data
  }
  catch (error) {
    console.error('Failed to fetch worthy employees:', error)
  }
  finally {
    isLoadingNominations.value = false
  }
}

const fetchEmployees = async () => {
  try {
    isLoadingEmployees.value = true

    const response = await EmployeeApi.getAll()

    employees.value = response.data.data
  }
  catch (error) {
    console.error('Failed to fetch employees:', error)
  }
  finally {
    isLoadingEmployees.value = false
  }
}

const submitNomination = async () => {
  const employeeId = nominationForm.value.employeeId

  nominationErrors.value = {}
  if (!employeeId)
    nominationErrors.value.employeeId = translate('pages.bonuses.bonuslist.bonuslist.selectAnEmployee')
  if (!nominationForm.value.achievementDetails.trim())
    nominationErrors.value.achievementDetails = translate('pages.bonuses.bonuslist.bonuslist.addAnAchievementOrJustification')
  if (nominationForm.value.selectedAmount < 0)
    nominationErrors.value.selectedAmount = translate('pages.bonuses.bonuslist.bonuslist.selectedAmountCannotBeNegative')
  if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(nominationForm.value.bonusMonth))
    nominationErrors.value.bonusMonth = translate('pages.bonuses.bonuslist.bonuslist.selectAValidBonusMonth')
  if (Object.keys(nominationErrors.value).length)
    return
  if (!employeeId)
    return

  try {
    const response = await BonusListApi.create({
      employeeId,
      achievementDetails: nominationForm.value.achievementDetails,
      amount: nominationForm.value.selectedAmount,
      selectedType: nominationForm.value.selectedType,
      bonusMonth: nominationForm.value.bonusMonth,
    })

    worthyEmployees.value.unshift(response.data.data)
    isNominateDialogVisible.value = false
    resetNominationForm()
  }
  catch (error) {
    console.error('Failed to submit nomination:', error)
    nominationErrors.value = apiFieldErrors(error)
    if (!Object.keys(nominationErrors.value).length)
      nominationErrors.value.form = apiErrorMessage(error, 'Unable to add this nomination.')
  }
}

const updateEmployee = async (employee: WorthyEmployee) => {
  try {
    const response = await BonusListApi.update(employee.id, {
      selectedAmount: employee.selectedAmount,
      amount: employee.selectedAmount,
    })

    const index = worthyEmployees.value.findIndex(item => item.id === employee.id)
    if (index >= 0)
      worthyEmployees.value[index] = response.data.data
  }
  catch (error) {
    console.error('Failed to update employee:', error)
  }
}

const triggerGrantConfirmation = (employee: WorthyEmployee) => {
  selectedForGrant.value = employee
  grantError.value = ''
  isConfirmGrantVisible.value = true
}

const confirmGrantBonus = async () => {
  const target = selectedForGrant.value
  if (!target || granting.value)
    return

  const amount = Number(target.selectedAmount)
  if (!Number.isFinite(amount) || amount <= 0) {
    grantError.value = translate('pages.bonuses.bonuslist.bonuslist.grantAmountRequired')

    return
  }

  granting.value = true
  grantError.value = ''
  try {
    const response = await BonusListApi.update(target.id, {
      isGranted: true,
      grantedAmount: amount,
    })

    // Update the local state to reflect the granted status
    const index = worthyEmployees.value.findIndex(e => e.id === target.id)
    if (index >= 0)
      worthyEmployees.value[index] = response.data.data

    isConfirmGrantVisible.value = false
    selectedForGrant.value = null
    toast.success(translate('pages.bonuses.bonuslist.bonuslist.grantedToast', {
      amount: formatBonusAmount(target, amount),
      employee: target.employeeName,
      month: formatMonth(target.bonusMonth),
    }))
  }
  catch (error) {
    // ApiErrorNotifier owns the single error toast; keep the dialog open and
    // put the specific field message beside the amount.
    grantError.value = apiFieldErrors(error).grantedAmount || apiErrorMessage(error)
  }
  finally {
    granting.value = false
  }
}

const dismissEmployee = async (id: string) => {
  try {
    await BonusListApi.delete(id)
    worthyEmployees.value = worthyEmployees.value.filter(e => e.id !== id)
  }
  catch (error) {
    console.error('Failed to dismiss employee:', error)
  }
}

const askDismiss = (employee: WorthyEmployee) => { dismissTarget.value = employee }
const confirmDismiss = async (confirmed: boolean) => {
  if (!confirmed || !dismissTarget.value)
    return
  await dismissEmployee(dismissTarget.value.id)
  dismissTarget.value = null
}

const showCalculation = (employee: WorthyEmployee) => {
  selectedCalculation.value = employee
  isCalculationDialogVisible.value = true
}

// --------------------------------------------------------------------------
// Computed Properties
// --------------------------------------------------------------------------
const filteredEmployees = computed(() => {
  return worthyEmployees.value.filter(item => {
    const query = searchQuery.value.toLowerCase()

    const matchesSearch
            = item.employeeName.toLowerCase().includes(query)
            || item.employeeEmail.toLowerCase().includes(query)
            || item.achievementDetails.toLowerCase().includes(query)

    const matchesDept = departmentFilter.value === 'All' || item.department === departmentFilter.value
    const matchesReason = reasonFilter.value === 'All' || item.selectedType === reasonFilter.value
    const matchesMonth = monthFilter.value === 'All' || item.bonusMonth === monthFilter.value

    let matchesStatus = true
    if (statusFilter.value === 'Eligible')
      matchesStatus = !item.isGranted
    else if (statusFilter.value === 'Granted')
      matchesStatus = item.isGranted

    return matchesSearch && matchesDept && matchesReason && matchesStatus && matchesMonth
  })
})

const totalEligibleCount = computed(() => {
  return worthyEmployees.value.filter(e => !e.isGranted && (monthFilter.value === 'All' || e.bonusMonth === monthFilter.value)).length
})

const totalGrantedCount = computed(() => {
  return worthyEmployees.value.filter(e => e.isGranted && (monthFilter.value === 'All' || e.bonusMonth === monthFilter.value)).length
})

const currentBaseCurrency = computed(() => worthyEmployees.value.find(item => item.currentBaseCurrency)?.currentBaseCurrency ?? null)

const totalCurrentBase = (granted: boolean) => worthyEmployees.value
  .filter(item => item.isGranted === granted && (monthFilter.value === 'All' || item.bonusMonth === monthFilter.value))
  .reduce((total, item) => total + Number(item.currentBaseAmount ?? 0), 0)

const recommendedCurrentBaseTotal = computed(() => totalCurrentBase(false))
const grantedCurrentBaseTotal = computed(() => totalCurrentBase(true))

const poolByCurrency = (granted: boolean) => worthyEmployees.value
  .filter(item => item.isGranted === granted && (monthFilter.value === 'All' || item.bonusMonth === monthFilter.value))
  .reduce<Record<string, { amount: number; symbol: string; code: string; decimals: number }>>((pools, item) => {
    const code = item.currencyCode ?? 'Legacy'
    if (!pools[code]) {
      pools[code] = {
        amount: 0,
        symbol: item.currencySymbol ?? '',
        code,
        decimals: item.currencyDecimalPlaces ?? 2,
      }
    }
    pools[code].amount += Number(granted ? (item.grantedAmount ?? item.selectedAmount) : item.selectedAmount) || 0

    return pools
  }, {})

const recommendedPoolsByCurrency = computed(() => poolByCurrency(false))
const grantedPoolsByCurrency = computed(() => poolByCurrency(true))

const getEmployeeName = (employeeId: number) => {
  const employee = employees.value.find(e => e.id === employeeId)

  return employee ? `${employee.name} (${employee.email})` : 'Unknown Employee'
}

const formatMonth = (monthString: string) => {
  const [year, month] = monthString.split('-')
  const date = new Date(Number.parseInt(year), Number.parseInt(month) - 1)

  return formatLocalizedDate(date, { month: 'long', year: 'numeric' })
}

// --------------------------------------------------------------------------
// Lifecycle Hooks
// --------------------------------------------------------------------------
onMounted(() => {
  fetchWorthyEmployees()
  fetchEmployees()
})

// --------------------------------------------------------------------------
// Actions & Helpers
// --------------------------------------------------------------------------
const formatCurrency = (amount: number) => {
  return formatLocalizedNumber(Number(amount || 0), {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

const formatBonusAmount = (employee: WorthyEmployee, amount: number | null | undefined) => {
  const decimals = employee.currencyDecimalPlaces ?? 2
  const formatted = formatLocalizedNumber(Number(amount || 0), { minimumFractionDigits: decimals, maximumFractionDigits: decimals })

  return `${employee.currencySymbol ?? ''}${formatted}${employee.currencyCode ? ` ${employee.currencyCode}` : ''}`
}

const formatCurrentBaseAmount = (amount: number) => {
  const currency = currentBaseCurrency.value
  const decimals = currency?.decimalPlaces ?? 2
  const formatted = formatLocalizedNumber(Number(amount || 0), { minimumFractionDigits: decimals, maximumFractionDigits: decimals })

  return `${currency?.symbol ?? ''}${formatted}${currency?.code ? ` ${currency.code}` : ''}`.trim()
}

const getAvatarInitials = (name: string) => {
  return name
    .split(' ')
    .map(n => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
}

const getReasonColor = (reason: BonusType) => {
  switch (reason) {
    case 'Performance': return 'primary'
    case 'Task Completion': return 'success'
    case 'Customer Appreciation': return 'info'
    case 'Work Anniversary': return 'warning'
    default: return 'secondary'
  }
}

const getStatusColor = (isGranted: boolean) => {
  return isGranted ? 'success' : 'primary'
}
</script>

<template>
  <div class="worthy-employees-page">
    <ErpPageHeader :title="$t('pages.bonuses.bonuslist.bonuslist.bonusEligibilityNominations')" section="hr" />

    <!-- Summary KPI Cards -->
    <div class="bonus-kpis mb-5">
      <!-- Total Eligible -->
      <VCard class="bonus-kpi" border variant="flat">
        <VCard
          class="pa-4"
          variant="flat"
        >
          <div class="d-flex align-center justify-space-between">
            <div>
              <span class="text-caption text-uppercase text-medium-emphasis font-weight-medium">
                {{ $t('pages.bonuses.bonuslist.bonuslist.eligibleCandidates') }}
              </span>
              <h3 class="text-h4 font-weight-bold mt-1">
                {{ totalEligibleCount }}
              </h3>
            </div>
            <VAvatar
              color="primary"
              variant="tonal"
              size="48"
              rounded="lg"
            >
              <VIcon
                icon="tabler-users"
                size="24"
              />
            </VAvatar>
          </div>
        </VCard>
      </VCard>

      <!-- Projected Total Pool -->
      <VCard class="bonus-kpi" border variant="flat">
        <VCard
          class="pa-4"
          variant="flat"
        >
          <div class="d-flex align-center justify-space-between">
            <div>
              <span class="text-caption text-uppercase text-medium-emphasis font-weight-medium">
                {{ $t('pages.bonuses.bonuslist.bonuslist.projectedGrantPoolBase') }}
              </span>
              <h3 class="text-h4 font-weight-bold mt-1 text-primary">
                {{ formatCurrentBaseAmount(recommendedCurrentBaseTotal) }}
              </h3>
            </div>
            <VAvatar
              color="success"
              variant="tonal"
              size="48"
              rounded="lg"
            >
              <VIcon
                icon="tabler-coin"
                size="24"
              />
            </VAvatar>
          </div>
        </VCard>
      </VCard>

      <!-- Granted Count -->
      <VCard class="bonus-kpi" border variant="flat">
        <VCard
          class="pa-4"
          variant="flat"
        >
          <div class="d-flex align-center justify-space-between">
            <div>
              <span class="text-caption text-uppercase text-medium-emphasis font-weight-medium">
                {{ $t('pages.bonuses.bonuslist.bonuslist.grantedBonuses') }}
              </span>
              <h3 class="text-h4 font-weight-bold mt-1 text-success">
                {{ totalGrantedCount }}
              </h3>
            </div>
            <VAvatar
              color="info"
              variant="tonal"
              size="48"
              rounded="lg"
            >
              <VIcon
                icon="tabler-award"
                size="24"
              />
            </VAvatar>
          </div>
        </VCard>
      </VCard>

      <!-- Granted Pool -->
      <VCard class="bonus-kpi" border variant="flat">
        <VCard
          class="pa-4"
          variant="flat"
        >
          <div class="d-flex align-center justify-space-between">
            <div>
              <span class="text-caption text-uppercase text-medium-emphasis font-weight-medium">
                {{ $t('common.ui.grantedPayoutPoolCurrentBase') }}
              </span>
              <h3 class="text-h4 font-weight-bold mt-1 text-success">
                {{ formatCurrentBaseAmount(grantedCurrentBaseTotal) }}
              </h3>
            </div>
            <VAvatar
              color="success"
              variant="tonal"
              size="48"
              rounded="lg"
            >
              <VIcon
                icon="tabler-currency-dollar"
                size="24"
              />
            </VAvatar>
          </div>
        </VCard>
      </VCard>
    </div>

    <!-- Main Data Table Card -->
    <VCard
      variant="outlined"
      class="overflow-hidden"
    >
      <!-- Search & Filters Toolbar -->
      <VCardText class="bonus-toolbar">
        <VRow align="center">
          <VCol cols="12" md="5"><ErpSearchField v-model="searchQuery" :placeholder="$t('pages.bonuses.bonuslist.bonuslist.searchCandidateEmailOrAchievement')" /></VCol>
          <VCol cols="12" md="7" class="d-flex align-center justify-md-end ga-2 flex-wrap">
            <DataTableFilterMenu v-model="filterMenuOpen" :active="departmentFilter !== 'All' || reasonFilter !== 'All' || statusFilter !== 'All' || monthFilter !== 'All'" @reset="departmentFilter = 'All'; reasonFilter = 'All'; statusFilter = 'All'; monthFilter = 'All'">
              <ErpField v-model="departmentFilter" kind="select" :items="['All', ...departments]" :label="$t('common.fields.department')" />
              <ErpField v-model="reasonFilter" kind="select" :items="['All', ...bonusTypes]" :item-title="translateBackendValue" :label="$t('common.ui.bonusCategory')" />
              <ErpField v-model="statusFilter" kind="select" :items="['All', 'Eligible', 'Granted']" :item-title="translateBackendValue" :label="$t('common.fields.status')" />
              <ErpField v-model="monthFilter" kind="select" :items="monthOptions" item-title="title" item-value="value" :label="$t('common.messages.bonusMonth')" />
            </DataTableFilterMenu>
            <VBtn v-if="canAccess('manage_bonus_rules')" size="small" variant="tonal" prepend-icon="tabler-adjustments" @click="isRulesDialogVisible = true">{{ $t('pages.bonuses.bonuslist.bonuslist.bonusRules') }}</VBtn>
            <VBtn color="primary" size="small" prepend-icon="tabler-user-plus" @click="isNominateDialogVisible = true">{{ $t('pages.bonuses.bonuslist.bonuslist.nominateEmployee') }}</VBtn>
          </VCol>
        </VRow>
      </VCardText>

      <!-- Data Table -->
      <ResponsiveDataTable v-if="isLoadingNominations" :headers="bonusLoadingHeaders" :items="[]" :loading="true" :skeleton-rows="6" :sticky-key-column="false" :sticky-action-column="false" mobile-cards mobile-title-key="candidate" :mobile-fields="['achievement', 'status']" class="bonus-loading-table" />
      <VTable v-else class="text-no-wrap bonus-desktop-table">
        <thead>
          <tr>
            <th class="text-left font-weight-bold text-uppercase text-caption">
              {{ $t('pages.bonuses.bonuslist.bonuslist.candidate') }}
            </th>
            <th class="text-left font-weight-bold text-uppercase text-caption">
              {{ $t('pages.bonuses.bonuslist.bonuslist.eligibilityAchievements') }}
            </th>
            <th class="text-left font-weight-bold text-uppercase text-caption">
              {{ $t('common.messages.bonusMonth') }}
            </th>
            <th
              class="text-left font-weight-bold text-uppercase text-caption"
              style="min-width: 180px;"
            >
              {{ $t('pages.bonuses.bonuslist.bonuslist.selectedPayout') }}
            </th>
            <th class="text-left font-weight-bold text-uppercase text-caption">{{ $t('common.ui.paymentCurrency') }}</th>
            <th class="text-left font-weight-bold text-uppercase text-caption">{{ $t('common.ui.bookedRate') }}</th>
            <th class="text-left font-weight-bold text-uppercase text-caption">
              {{ $t('common.fields.status') }}
            </th>
            <th class="text-center font-weight-bold text-uppercase text-caption">
              {{ $t('common.messages.actions') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!filteredEmployees.length"><td colspan="8" class="pa-0"><TableEmptyState icon="tabler-award-off" :title="$t('pages.bonuses.bonuslist.bonuslist.bonusEligibilityNominations')" :description="$t('common.messages.noDataAvailable')" /></td></tr>
          <tr
            v-for="employee in filteredEmployees"
            :key="employee.id"
            class="align-middle"
          >
            <!-- Employee Column -->
            <td class="py-4">
              <div class="d-flex align-center gap-3">
                <VAvatar
                  color="primary"
                  variant="tonal"
                  size="40"
                  class="font-weight-bold"
                >
                  <span>{{ getAvatarInitials(employee.employeeName) }}</span>
                </VAvatar>
                <div>
                  <div class="font-weight-medium text-body-1">
                    {{ employee.employeeName }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ employee.department || 'No Department' }} • {{
                      employee.employeeEmail }}
                  </div>
                </div>
              </div>
            </td>

            <!-- Achievements & Reason -->
            <td class="py-4">
              <div class="mb-1">
                <VChip
                  :color="getReasonColor(employee.selectedType)"
                  size="small"
                  variant="tonal"
                  class="font-weight-bold"
                >
                  {{ translateBackendValue(employee.selectedType) }}
                </VChip>
              </div>
              <VTooltip
                :text="employee.achievementDetails"
                location="top"
              >
                <template #activator="{ props }">
                  <div
                    v-bind="props"
                    class="text-caption text-medium-emphasis achievement-text"
                  >
                    {{ employee.achievementDetails }}
                  </div>
                </template>
              </VTooltip>
            </td>

            <!-- Bonus Month -->
            <td class="py-4">
              <div class="text-body-1 font-weight-medium">
                {{ formatMonth(employee.bonusMonth) }}
              </div>
            </td>

            <!-- Interactive Inline Amount Selection -->
            <td class="py-4">
              <VCombobox
                v-model.number="employee.selectedAmount"
                :items="quickAmounts"
                density="compact"
                variant="outlined"
                hide-details
                type="number"
                step="100"
                class="amount-selector font-weight-bold text-primary"
                :disabled="employee.isGranted"
                @update:model-value="!employee.isGranted && updateEmployee(employee)"
              />
              <div class="text-caption text-medium-emphasis mt-1">
                {{ $t('pages.bonuses.bonuslist.suggestedAmount', { amount: formatBonusAmount(employee, employee.suggestedAmount) }) }}
                <span class="d-block">{{ $t('common.ui.paymentCurrency') }}: {{ employee.currencySymbol || '' }} {{ employee.currencyCode || $t('common.ui.legacyRecord') }} · {{ $t('common.ui.bookedRate') }} {{ employee.rate ?? '—' }}</span>
              </div>
            </td>

            <td class="py-4 text-medium-emphasis">
              {{ employee.currencySymbol || '' }} {{ employee.currencyCode || $t('common.ui.legacyRecord') }}
            </td>
            <td class="py-4 text-medium-emphasis">{{ employee.rate ?? '—' }}</td>

            <!-- Status -->
            <td class="py-4">
              <VChip
                :color="getStatusColor(employee.isGranted)"
                size="small"
                variant="tonal"
                class="font-weight-bold"
              >
                {{ employee.isGranted ? 'Granted' : 'Eligible' }}
              </VChip>
            </td>

            <!-- Actions -->
            <td class="text-center py-4">
              <VMenu location="bottom end">
                <template #activator="{ props }"><VBtn v-bind="props" class="bonus-actions-menu" icon="tabler-dots-vertical" size="small" variant="tonal" :aria-label="$t('common.messages.actions')" /></template>
                <VList density="compact" min-width="210">
                  <VListItem v-if="employee.isAutomatic" prepend-icon="tabler-calculator" :title="$t('pages.bonuses.bonuslist.bonuslist.viewCalculationEvidence')" @click="showCalculation(employee)" />
                  <VListItem v-if="!employee.isGranted" prepend-icon="tabler-check" :title="$t('common.actions.grant')" @click="triggerGrantConfirmation(employee)" />
                  <VListItem prepend-icon="tabler-user-x" :title="$t('pages.bonuses.bonuslist.bonuslist.dismissFromList')" @click="askDismiss(employee)" />
                </VList>
              </VMenu>
              <div class="bonus-legacy-actions d-flex justify-center align-center gap-2">
                <FullDetailsButton
                  :item="employee"
                  :title="employee.employeeName"
                />
                <VTooltip
                  :text="$t('pages.bonuses.bonuslist.bonuslist.viewCalculationEvidence')"
                  location="top"
                >
                  <template #activator="{ props }">
                    <VBtn
                      v-if="employee.isAutomatic"
                      v-bind="props"
                      icon="tabler-calculator"
                      size="small"
                      variant="tonal"
                      color="info"
                      @click="showCalculation(employee)"
                    />
                  </template>
                </VTooltip>
                <!-- Grant Button (only for eligible employees) -->
                <VBtn
                  v-if="!employee.isGranted"
                  color="primary"
                  variant="elevated"
                  size="small"
                  prepend-icon="tabler-check"
                  @click="triggerGrantConfirmation(employee)"
                >
                  {{ $t('common.actions.grant') }}
                </VBtn>

                <!-- Dismiss/Ignore Tooltip -->
                <VTooltip
                  :text="$t('pages.bonuses.bonuslist.bonuslist.dismissFromList')"
                  location="top"
                >
                  <template #activator="{ props }">
                    <VBtn
                      v-bind="props"
                      icon="tabler-x"
                      size="small"
                      variant="text"
                      color="medium-emphasis"
                      @click="askDismiss(employee)"
                    />
                  </template>
                </VTooltip>
              </div>
            </td>
          </tr>

          <!-- Empty State -->
          <tr v-if="filteredEmployees.length === 0">
            <td
              colspan="8"
              class="text-center py-10 text-medium-emphasis"
            >
              <VIcon
                icon="tabler-award-off"
                size="48"
                class="mb-2"
              />
              <div class="text-h6">
                {{ $t('pages.bonuses.bonuslist.bonuslist.noMatchingCandidatesFound') }}
              </div>
              <div class="text-caption">
                {{ $t('pages.bonuses.bonuslist.bonuslist.tryAdjustingYourFiltersOrNominateANewEmployee') }}
              </div>
            </td>
          </tr>
        </tbody>
      </VTable>
      <div v-if="!isLoadingNominations" class="bonus-mobile-cards">
        <TableEmptyState
          v-if="!filteredEmployees.length"
          icon="tabler-award-off"
          :title="$t('pages.bonuses.bonuslist.bonuslist.bonusEligibilityNominations')"
          :description="$t('common.messages.noDataAvailable')"
        />
        <article v-for="employee in filteredEmployees" :key="employee.id" class="bonus-mobile-card">
          <div class="bonus-mobile-card__header">
            <div class="d-flex align-center gap-3 min-w-0">
              <VAvatar color="primary" variant="tonal" size="40" class="font-weight-bold"><span>{{ getAvatarInitials(employee.employeeName) }}</span></VAvatar>
              <div class="min-w-0">
                <div class="font-weight-medium text-body-1 text-truncate">{{ employee.employeeName }}</div>
                <div class="text-caption text-medium-emphasis text-truncate">{{ employee.department || 'No Department' }} · {{ employee.employeeEmail }}</div>
              </div>
            </div>
            <VMenu location="bottom end">
              <template #activator="{ props }"><VBtn v-bind="props" class="bonus-actions-menu" icon="tabler-dots-vertical" size="small" variant="tonal" :aria-label="$t('common.messages.actions')" /></template>
              <VList density="compact" min-width="210">
                <VListItem v-if="employee.isAutomatic" prepend-icon="tabler-calculator" :title="$t('pages.bonuses.bonuslist.bonuslist.viewCalculationEvidence')" @click="showCalculation(employee)" />
                <VListItem v-if="!employee.isGranted" prepend-icon="tabler-check" :title="$t('common.actions.grant')" @click="triggerGrantConfirmation(employee)" />
                <VListItem prepend-icon="tabler-user-x" :title="$t('pages.bonuses.bonuslist.bonuslist.dismissFromList')" @click="askDismiss(employee)" />
              </VList>
            </VMenu>
          </div>
          <div class="bonus-mobile-card__field"><span>{{ $t('pages.bonuses.bonuslist.bonuslist.eligibilityAchievements') }}</span><div><VChip :color="getReasonColor(employee.selectedType)" size="small" variant="tonal">{{ translateBackendValue(employee.selectedType) }}</VChip><p class="text-caption text-medium-emphasis mt-1 mb-0">{{ employee.achievementDetails }}</p></div></div>
          <div class="bonus-mobile-card__field"><span>{{ $t('common.messages.bonusMonth') }}</span><strong>{{ formatMonth(employee.bonusMonth) }}</strong></div>
          <div class="bonus-mobile-card__field bonus-mobile-card__field--amount"><span>{{ $t('pages.bonuses.bonuslist.bonuslist.selectedPayout') }}</span><div><VCombobox v-model.number="employee.selectedAmount" :items="quickAmounts" density="compact" variant="outlined" hide-details type="number" step="100" class="amount-selector font-weight-bold text-primary" :disabled="employee.isGranted" @update:model-value="!employee.isGranted && updateEmployee(employee)" /><p class="text-caption text-medium-emphasis mt-1 mb-0">{{ $t('pages.bonuses.bonuslist.suggestedAmount', { amount: formatBonusAmount(employee, employee.suggestedAmount) }) }}</p></div></div>
          <div class="bonus-mobile-card__field"><span>{{ $t('common.ui.paymentCurrency') }}</span><strong>{{ employee.currencySymbol || '' }} {{ employee.currencyCode || $t('common.ui.legacyRecord') }}</strong></div>
          <div class="bonus-mobile-card__field"><span>{{ $t('common.ui.bookedRate') }}</span><strong>{{ employee.rate ?? '—' }}</strong></div>
          <div class="bonus-mobile-card__field"><span>{{ $t('common.fields.status') }}</span><VChip :color="getStatusColor(employee.isGranted)" size="small" variant="tonal">{{ employee.isGranted ? 'Granted' : 'Eligible' }}</VChip></div>
        </article>
      </div>
    </VCard>

    <ConfirmDialog
      :is-dialog-visible="Boolean(dismissTarget)"
      tone="warning"
      icon="tabler-user-x"
      :confirmation-question="$t('pages.bonuses.bonuslist.bonuslist.dismissFromList')"
      :confirmation-message="dismissTarget?.employeeName || ''"
      :confirm-text="$t('pages.bonuses.bonuslist.bonuslist.dismissFromList')"
      :cancel-text="$t('common.actions.cancel')"
      :confirm-title="$t('common.actions.confirm')"
      confirm-msg=""
      :cancel-title="$t('common.actions.cancel')"
      cancel-msg=""
      @update:is-dialog-visible="visible => { if (!visible) dismissTarget = null }"
      @confirm="confirmDismiss"
    />

    <!-- Grant Confirmation Modal -->
    <VDialog
      v-model="isConfirmGrantVisible"
      max-width="460"
      class="bonus-grant-overlay"
    >
      <VCard class="bonus-grant-dialog">
        <VCardTitle class="bonus-dialog__header px-5 py-4 d-flex align-center gap-2 text-h5 font-weight-bold">
          <VIcon
            icon="tabler-gift"
            color="success"
          />
          {{ $t('pages.bonuses.bonuslist.bonuslist.confirmBonusGrant') }}
        </VCardTitle>

        <VCardText class="pa-5 text-body-1">
          {{ $t('pages.bonuses.bonuslist.grantConfirmation', {
            type: translateBackendValue(selectedForGrant?.selectedType),
            employee: selectedForGrant?.employeeName,
            month: selectedForGrant ? formatMonth(selectedForGrant.bonusMonth) : '',
          }) }}

          <div class="d-flex justify-space-between align-center pa-4 mt-4 bg-var-theme-surface rounded border">
            <span class="text-medium-emphasis font-weight-medium">{{ $t('pages.bonuses.bonuslist.bonuslist.finalAmount') }}</span>
            <span class="text-h5 font-weight-bold text-primary">
              {{ selectedForGrant ? formatBonusAmount(selectedForGrant, grantAmount) : formatCurrency(0) }}
            </span>
          </div>

          <VAlert v-if="grantError || grantAmountInvalid" type="error" variant="tonal" class="mt-4">
            {{ grantError || $t('pages.bonuses.bonuslist.bonuslist.grantAmountRequired') }}
          </VAlert>
        </VCardText>

        <VCardActions class="bonus-dialog__footer px-5 py-4 justify-end gap-2">
          <VBtn
            variant="outlined"
            color="secondary"
            class="bonus-dialog__cancel"
            @click="isConfirmGrantVisible = false; grantError = ''"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="success"
            class="bonus-grant-dialog__confirm"
            elevation="1"
            :loading="granting"
            :disabled="grantAmountInvalid"
            @click="confirmGrantBonus"
          >
            {{ $t('pages.bonuses.bonuslist.bonuslist.confirmGrant') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="isCalculationDialogVisible"
      max-width="900"
    >
      <VCard rounded="lg">
        <VCardTitle class="pa-5 d-flex align-center justify-space-between">
          <div>
            <div class="text-h5 font-weight-bold">
              {{ $t('pages.bonuses.bonuslist.bonuslist.bonusCalculationEvidence') }}
            </div><div class="text-caption">
              {{ $t('pages.bonuses.bonuslist.calculationSummary', { version: selectedCalculation?.ruleSnapshot?.version, score: selectedCalculation?.calculationScore }) }}
            </div>
          </div>
          <VBtn
            icon="tabler-x"
            variant="text"
            @click="isCalculationDialogVisible = false"
          />
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-5">
          <VAlert
            type="info"
            variant="tonal"
            class="mb-4"
          >
            {{ selectedCalculation?.achievementDetails }}
          </VAlert>
          <VTable density="compact">
            <thead><tr><th>{{ $t('pages.bonuses.bonuslist.bonuslist.task') }}</th><th>{{ $t('common.ui.completedOn') }}</th><th>{{ $t('common.messages.actions') }}</th></tr></thead>
            <tbody>
              <tr
                v-for="task in selectedCalculation?.calculationInputs?.tasks ?? []"
                :key="String(task.task_id)"
              >
                <td>{{ task.task_title }}</td><td>{{ task.completed_at ? formatLocalizedDate(task.completed_at) : '—' }}</td><td>
                  <FullDetailsButton
                    :item="task"
                    :title="task.task_title"
                  />
                </td>
              </tr>
            </tbody>
          </VTable>
          <div class="text-caption text-medium-emphasis mt-4">
            {{ selectedCalculation?.calculationInputs?.formula }}
          </div>
        </VCardText>
      </VCard>
    </VDialog>

    <BonusRulesDialog v-model="isRulesDialogVisible" />

    <!-- Nominate Employee Modal -->
    <VDialog
      v-model="isNominateDialogVisible"
      max-width="600"
      class="bonus-form-overlay"
    >
      <VCard class="bonus-form-dialog">
        <VCardTitle class="bonus-dialog__header px-5 py-4 d-flex justify-space-between align-center">
          <span class="text-h5 font-weight-bold">{{ $t('pages.bonuses.bonuslist.bonuslist.nominateForBonus') }}</span>
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="error"
            density="compact"
            @click="isNominateDialogVisible = false"
          />
        </VCardTitle>

        <VCardText class="bonus-dialog__body pa-5">
          <VForm
            class="app-form"
            @submit.prevent="submitNomination"
          >
            <VAlert
              v-if="nominationErrors.form"
              type="error"
              variant="tonal"
              class="mb-4"
            >
              {{ nominationErrors.form }}
            </VAlert>
            <VRow class="bonus-form-fields">
              <VCol cols="12" md="6">
                <ErpField
                  v-model="nominationForm.employeeId"
                  kind="select"
                  :items="employees"
                  item-title="name"
                  item-value="id"
                  :label="$t('pages.bonuses.bonuslist.bonuslist.selectEmployee')"
                  :error-messages="nominationErrors.employeeId"
                  required
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <ErpField
                  v-model="nominationForm.bonusMonth"
                  kind="select"
                  :items="monthOptions.filter(m => m.value !== 'All')"
                  :label="$t('pages.bonuses.bonuslist.bonuslist.bonusMonth')"
                  item-title="title"
                  item-value="value"
                  :error-messages="nominationErrors.bonusMonth"
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <ErpField
                  v-model="nominationForm.selectedType"
                  kind="select"
                  :items="bonusTypes"
                  :item-title="translateBackendValue"
                  :label="$t('common.ui.bonusCategory')"
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <ErpField
                  v-model.number="nominationForm.selectedAmount"
                  :label="$t('common.fields.bonusAmount')"
                  type="number"
                  step="100"
                  required
                  :error-messages="nominationErrors.selectedAmount"
                />
              </VCol>

              <VCol cols="12">
                <ErpField
                  v-model="nominationForm.achievementDetails"
                  kind="textarea"
                  :label="$t('pages.bonuses.bonuslist.bonuslist.achievementJustification')"
                  :placeholder="$t('pages.bonuses.bonuslist.bonuslist.explainWhyThisEmployeeDeservesRecognition')"
                  rows="3"
                  :error-messages="nominationErrors.achievementDetails"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VCardActions class="bonus-dialog__footer px-5 py-4 justify-end gap-2">
          <VBtn
            variant="outlined"
            color="secondary"
            class="bonus-dialog__cancel"
            @click="isNominateDialogVisible = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            elevation="1"
            class="bonus-dialog__submit"
            @click="submitNomination"
          >
            {{ $t('pages.bonuses.bonuslist.bonuslist.addToWorthyList') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.worthy-employees-page { max-inline-size: 100%; }
.bonus-kpis { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
.bonus-kpis::-webkit-scrollbar { display: none; }
.bonus-kpi { min-inline-size: 0; border-radius: 12px !important; }
.bonus-kpi > .v-card { box-shadow: none !important; }
.bonus-kpi :deep(.text-h4) { font-size: 1.15rem !important; line-height: 1.3; }
@media (max-width: 959px) { .bonus-kpis { display: flex; overflow-x: auto; padding-block-end: .25rem; scrollbar-width: none; } .bonus-kpi { flex: 0 0 17rem; } }
@media (max-width: 959px) {
  .bonus-toolbar { padding: 1rem !important; }
  .bonus-toolbar :deep(.v-row) { row-gap: .75rem; }
  .bonus-toolbar :deep(.v-col) { min-inline-size: 0; }
  .bonus-desktop-table { display: none; }
  .bonus-mobile-cards { display: grid; gap: .75rem; padding: 1rem; }
  .bonus-mobile-card { overflow: hidden; border: 1px solid rgba(var(--v-border-color), .58); border-radius: .75rem; background: rgb(var(--v-theme-surface)); box-shadow: 0 1px 2px rgb(var(--v-theme-on-surface) / .04); }
  .bonus-mobile-card__header { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .875rem; border-block-end: 1px solid rgba(var(--v-border-color), .38); }
  .bonus-mobile-card__field { display: grid; grid-template-columns: minmax(7.5rem, .85fr) minmax(0, 1.15fr); align-items: center; gap: .75rem; min-block-size: 3rem; padding: .625rem .875rem; border-block-end: 1px solid rgba(var(--v-border-color), .38); }
  .bonus-mobile-card__field:last-child { border-block-end: 0; }
  .bonus-mobile-card__field > span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .75rem; font-weight: 700; }
  .bonus-mobile-card__field > :not(span) { min-inline-size: 0; text-align: end; }
  .bonus-mobile-card__field--amount { align-items: start; padding-block: .75rem; }
  .bonus-mobile-card__field--amount .amount-selector { max-inline-size: none; }
}
@media (min-width: 960px) { .bonus-mobile-cards { display: none; } }
.bonus-toolbar { padding: 1.25rem 1.5rem; background: rgb(var(--v-theme-surface)) !important; border-block-end: 0; }
.bonus-form-dialog, .bonus-grant-dialog { overflow: hidden; }
.bonus-dialog__header, .bonus-dialog__footer { background: color-mix(in srgb, rgb(var(--v-theme-surface)) 93%, rgb(var(--v-theme-on-surface)) 7%); }
.bonus-dialog__header { border-block-end: 1px solid rgba(var(--v-border-color), .5); }
.bonus-dialog__footer { border-block-start: 1px solid rgba(var(--v-border-color), .5); }
.bonus-dialog__body { background: rgb(var(--v-theme-surface)); }
.bonus-form-fields { row-gap: 10px; }
.bonus-dialog__cancel { border: 1px solid rgba(var(--v-theme-secondary), .52) !important; background: rgba(var(--v-theme-secondary), .13) !important; color: rgb(var(--v-theme-secondary)) !important; }
.bonus-dialog__cancel:hover { background: rgb(var(--v-theme-secondary)) !important; color: rgb(var(--v-theme-on-secondary)) !important; }
.bonus-dialog__submit { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
.bonus-grant-dialog__confirm { background: rgb(var(--v-theme-success)) !important; color: rgb(var(--v-theme-on-success)) !important; }
:global(.bonus-form-overlay .v-overlay__scrim), :global(.bonus-grant-overlay .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .3) !important; backdrop-filter: blur(7px); opacity: 1 !important; }
.worthy-employees-page :deep(thead th) { background: rgba(var(--v-theme-on-surface), .045) !important; }
.worthy-employees-page :deep(tbody tr) { border-block-end: 8px solid transparent; background-clip: padding-box; }
.worthy-employees-page :deep(.v-table__wrapper) { scrollbar-width: none; }
.worthy-employees-page :deep(.v-table__wrapper::-webkit-scrollbar) { display: none; }
.worthy-employees-page :deep(table) { min-inline-size: 74rem; }
.worthy-employees-page :deep(tbody td:first-child .text-caption) { display: none; }
.bonus-actions-menu { background: rgba(var(--v-theme-on-surface), .07) !important; color: rgba(var(--v-theme-on-surface), .72) !important; }
.bonus-actions-menu:hover { background: rgba(var(--v-theme-on-surface), .12) !important; }
.bonus-legacy-actions { display: none !important; }
.amount-selector + .text-caption > span { display: none !important; }
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

.achievement-text {
    max-width: 280px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.amount-selector {
    max-width: 140px;
}

:deep(thead th) {
    background: rgba(var(--v-theme-on-surface), 0.045) !important;
}
</style>
