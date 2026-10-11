<script setup lang="ts">
import { translate } from '@/utils/localization'
import { computed, onMounted, ref } from 'vue'
import BonusListApi, { type BonusType, type WorthyEmployee } from '@/api/bonuslist'
import EmployeeApi, { type Employee } from '@/api/employee'
import { apiErrorMessage, apiFieldErrors } from '@/utils/apiErrors'
import { canAccess } from '@/utils/authorization'
import BonusRulesDialog from '@/components/bonus/BonusRulesDialog.vue'

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
const worthyEmployees = ref<WorthyEmployee[]>([])
const employees = ref<Employee[]>([])
const isLoadingEmployees = ref(false)
const nominationErrors = ref<Record<string, string>>({})

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
  try {
    const response = await BonusListApi.getAll()

    worthyEmployees.value = response.data.data
  }
  catch (error) {
    console.error('Failed to fetch worthy employees:', error)
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

const triggerGrantConfirmation = async (employee: WorthyEmployee) => {
  selectedForGrant.value = employee
  isConfirmGrantVisible.value = true
}

const confirmGrantBonus = async () => {
  if (!selectedForGrant.value)
    return

  try {
    const response = await BonusListApi.update(selectedForGrant.value.id, {
      isGranted: true,
    })

    // Update the local state to reflect the granted status
    const index = worthyEmployees.value.findIndex(e => e.id === selectedForGrant.value?.id)
    if (index >= 0)
      worthyEmployees.value[index] = response.data.data

    isConfirmGrantVisible.value = false
    selectedForGrant.value = null
  }
  catch (error) {
    console.error('Failed to grant bonus:', error)
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
  <div class="worthy-employees-page pa-4 pa-md-6">
    <!-- Header Section -->
    <div class="d-flex flex-column flex-sm-row justify-space-between align-sm-center mb-6 gap-4">
      <div>
        <h2 class="text-h4 font-weight-bold mb-1">
          {{ $t('pages.bonuses.bonuslist.bonuslist.bonusEligibilityNominations') }}
        </h2>
        <p class="text-body-2 text-medium-emphasis mb-0">
          {{ $t('pages.bonuses.bonuslist.bonuslist.reviewHighPerformingEmployeesWorthyOfRecognitionCustomizePayoutAmounts') }}
        </p>
      </div>
      <div class="d-flex gap-2">
        <VBtn
          v-if="canAccess('manage_bonus_rules')"
          variant="outlined"
          prepend-icon="tabler-adjustments"
          size="large"
          @click="isRulesDialogVisible = true"
        >
          {{ $t('pages.bonuses.bonuslist.bonuslist.bonusRules') }}
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-user-plus"
          elevation="2"
          size="large"
          @click="isNominateDialogVisible = true"
        >
          {{ $t('pages.bonuses.bonuslist.bonuslist.nominateEmployee') }}
        </VBtn>
      </div>
    </div>

    <!-- Summary KPI Cards -->
    <VRow class="mb-6">
      <!-- Total Eligible -->
      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VCard
          class="pa-4"
          variant="outlined"
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
      </VCol>

      <!-- Projected Total Pool -->
      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VCard
          class="pa-4"
          variant="outlined"
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
      </VCol>

      <!-- Granted Count -->
      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VCard
          class="pa-4"
          variant="outlined"
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
      </VCol>

      <!-- Granted Pool -->
      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VCard
          class="pa-4"
          variant="outlined"
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
      </VCol>
    </VRow>

    <!-- Main Data Table Card -->
    <VCard
      variant="outlined"
      class="overflow-hidden"
    >
      <!-- Search & Filters Toolbar -->
      <VCardText class="pa-4 border-b">
        <VRow
          density="compact"
          align="center"
        >
          <VCol
            cols="12"
            md="3"
          >
            <VTextField
              v-model="searchQuery"
              :placeholder="$t('pages.bonuses.bonuslist.bonuslist.searchCandidateEmailOrAchievement')"
              prepend-inner-icon="tabler-search"
              hide-details
              density="compact"
              variant="outlined"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="2"
          >
            <VSelect
              v-model="departmentFilter"
              :items="['All', ...departments]"
              :label="$t('common.fields.department')"
              hide-details
              density="compact"
              variant="outlined"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="2"
          >
            <VSelect
              v-model="reasonFilter"
              :items="['All', ...bonusTypes]"
              :item-title="translateBackendValue"
              :label="$t('common.ui.bonusCategory')"
              hide-details
              density="compact"
              variant="outlined"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="2"
          >
            <VSelect
              v-model="statusFilter"
              :items="['All', 'Eligible', 'Granted']"
              :item-title="translateBackendValue"
              :label="$t('common.fields.status')"
              hide-details
              density="compact"
              variant="outlined"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VSelect
              v-model="monthFilter"
              :items="monthOptions"
              :label="$t('common.messages.bonusMonth')"
              item-title="title"
              item-value="value"
              hide-details
              density="compact"
              variant="outlined"
            />
          </VCol>
        </VRow>
      </VCardText>

      <!-- Data Table -->
      <VTable class="text-no-wrap">
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
            <th class="text-left font-weight-bold text-uppercase text-caption">
              {{ $t('common.fields.status') }}
            </th>
            <th class="text-center font-weight-bold text-uppercase text-caption">
              {{ $t('common.messages.actions') }}
            </th>
          </tr>
        </thead>
        <tbody>
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
                  variant="flat"
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

            <!-- Status -->
            <td class="py-4">
              <VChip
                :color="getStatusColor(employee.isGranted)"
                size="small"
                variant="flat"
                class="font-weight-bold"
              >
                {{ employee.isGranted ? 'Granted' : 'Eligible' }}
              </VChip>
            </td>

            <!-- Actions -->
            <td class="text-center py-4">
              <div class="d-flex justify-center align-center gap-2">
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
                      @click="dismissEmployee(employee.id)"
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
    </VCard>

    <!-- Grant Confirmation Modal -->
    <VDialog
      v-model="isConfirmGrantVisible"
      max-width="460"
    >
      <VCard rounded="lg">
        <VCardTitle class="pa-5 pb-3 d-flex align-center gap-2 text-h5 font-weight-bold">
          <VIcon
            icon="tabler-gift"
            color="primary"
          />
          {{ $t('pages.bonuses.bonuslist.bonuslist.confirmBonusGrant') }}
        </VCardTitle>

        <VDivider />

        <VCardText class="pa-5 text-body-1">
          {{ $t('pages.bonuses.bonuslist.grantConfirmation', {
            type: translateBackendValue(selectedForGrant?.selectedType),
            employee: selectedForGrant?.employeeName,
            month: selectedForGrant ? formatMonth(selectedForGrant.bonusMonth) : '',
          }) }}

          <div class="d-flex justify-space-between align-center pa-4 mt-4 bg-var-theme-surface rounded border">
            <span class="text-medium-emphasis font-weight-medium">{{ $t('pages.bonuses.bonuslist.bonuslist.finalAmount') }}</span>
            <span class="text-h5 font-weight-bold text-primary">
              {{ selectedForGrant ? formatBonusAmount(selectedForGrant, selectedForGrant.selectedAmount) : formatCurrency(0) }}
            </span>
          </div>
        </VCardText>

        <VCardActions class="pa-4 justify-end gap-2">
          <VBtn
            variant="outlined"
            color="secondary"
            @click="isConfirmGrantVisible = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            elevation="1"
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
    >
      <VCard rounded="lg">
        <VCardTitle class="pa-5 d-flex justify-space-between align-center">
          <span class="text-h5 font-weight-bold">{{ $t('pages.bonuses.bonuslist.bonuslist.nominateForBonus') }}</span>
          <VBtn
            icon="tabler-x"
            variant="text"
            density="compact"
            @click="isNominateDialogVisible = false"
          />
        </VCardTitle>

        <VDivider />

        <VCardText class="pa-5">
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
            <VRow density="comfortable">
              <VCol cols="12">
                <VSelect
                  v-model="nominationForm.employeeId"
                  :items="employees"
                  item-title="name"
                  item-value="id"
                  :label="$t('pages.bonuses.bonuslist.bonuslist.selectEmployee')"
                  :loading="isLoadingEmployees"
                  :hint="nominationForm.employeeId ? getEmployeeName(nominationForm.employeeId) : ''"
                  persistent-hint
                  variant="outlined"
                  :error-messages="nominationErrors.employeeId"
                  required
                >
                  <template #item="{ props, item }">
                    <VListItem
                      v-bind="props"
                      :title="`${item.raw.name} (${item.raw.email})`"
                      :subtitle="item.raw.department || 'No Department'"
                    />
                  </template>
                </VSelect>
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VSelect
                  v-model="nominationForm.bonusMonth"
                  :items="monthOptions.filter(m => m.value !== 'All')"
                  :label="$t('pages.bonuses.bonuslist.bonuslist.bonusMonth')"
                  item-title="title"
                  item-value="value"
                  variant="outlined"
                  :error-messages="nominationErrors.bonusMonth"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VSelect
                  v-model="nominationForm.selectedType"
                  :items="bonusTypes"
                  :item-title="translateBackendValue"
                  :label="$t('common.ui.bonusCategory')"
                  variant="outlined"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VTextField
                  v-model.number="nominationForm.selectedAmount"
                  :label="$t('common.fields.bonusAmount')"
                  type="number"
                  step="100"
                  variant="outlined"
                  required
                  :error-messages="nominationErrors.selectedAmount"
                />
              </VCol>

              <VCol cols="12">
                <VTextarea
                  v-model="nominationForm.achievementDetails"
                  :label="$t('pages.bonuses.bonuslist.bonuslist.achievementJustification')"
                  :placeholder="$t('pages.bonuses.bonuslist.bonuslist.explainWhyThisEmployeeDeservesRecognition')"
                  variant="outlined"
                  rows="3"
                  :error-messages="nominationErrors.achievementDetails"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4 justify-end gap-2">
          <VBtn
            variant="outlined"
            color="secondary"
            @click="isNominateDialogVisible = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            elevation="1"
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
</style>
