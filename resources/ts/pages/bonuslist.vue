<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import BonusListApi, { type WorthyEmployee, type EligibilityReason, type BonusType } from '@/api/bonuslist'
import EmployeeApi, { type Employee } from '@/api/employee'
import { apiFieldErrors, apiErrorMessage } from '@/utils/apiErrors'
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
const reasons: EligibilityReason[] = ['Top Performer', 'Project Milestone', 'Client Praise', 'Work Anniversary', 'Manager Nomination']
const bonusTypes: BonusType[] = ['Performance', 'Spot Bonus', 'Project Completion', 'Retention']
const quickAmounts = [500, 1000, 1500, 2500, 4000, 5000]
const performanceScores = [1.0, 1.5, 2.0, 2.5, 3.0, 3.5, 4.0, 4.5, 5.0]

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
    performanceScore: 4.5,
    eligibilityReason: 'Manager Nomination' as EligibilityReason,
    achievementDetails: '',
    suggestedAmount: 1000,
    selectedAmount: 1000,
    selectedType: 'Spot Bonus' as BonusType,
    bonusMonth: new Date().toISOString().slice(0, 7) // Current month in YYYY-MM format
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
    } catch (error) {
        console.error('Failed to fetch worthy employees:', error)
    }
}

const fetchEmployees = async () => {
    try {
        isLoadingEmployees.value = true
        const response = await EmployeeApi.getAll()
        employees.value = response.data.data
    } catch (error) {
        console.error('Failed to fetch employees:', error)
    } finally {
        isLoadingEmployees.value = false
    }
}

const submitNomination = async () => {
    const employeeId = nominationForm.value.employeeId
    nominationErrors.value = {}
    if (!employeeId) nominationErrors.value.employeeId = translate('pages.bonuses.bonuslist.bonuslist.selectAnEmployee')
    if (!nominationForm.value.achievementDetails.trim()) nominationErrors.value.achievementDetails = translate('pages.bonuses.bonuslist.bonuslist.addAnAchievementOrJustification')
    if (nominationForm.value.performanceScore < 1 || nominationForm.value.performanceScore > 5) nominationErrors.value.performanceScore = translate('pages.bonuses.bonuslist.bonuslist.performanceScoreMustBeBetween1And5')
    if (nominationForm.value.suggestedAmount < 0) nominationErrors.value.suggestedAmount = translate('pages.bonuses.bonuslist.bonuslist.suggestedAmountCannotBeNegative')
    if (nominationForm.value.selectedAmount < 0) nominationErrors.value.selectedAmount = translate('pages.bonuses.bonuslist.bonuslist.selectedAmountCannotBeNegative')
    if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(nominationForm.value.bonusMonth)) nominationErrors.value.bonusMonth = translate('pages.bonuses.bonuslist.bonuslist.selectAValidBonusMonth')
    if (Object.keys(nominationErrors.value).length) return
    if (!employeeId) return

    try {
        const response = await BonusListApi.create({
            employeeId,
            performanceScore: nominationForm.value.performanceScore,
            eligibilityReason: nominationForm.value.eligibilityReason,
            achievementDetails: nominationForm.value.achievementDetails,
            suggestedAmount: nominationForm.value.suggestedAmount,
            selectedAmount: nominationForm.value.selectedAmount,
            selectedType: nominationForm.value.selectedType,
            bonusMonth: nominationForm.value.bonusMonth,
        })

        worthyEmployees.value.unshift(response.data.data)
        isNominateDialogVisible.value = false
        resetNominationForm()
    } catch (error) {
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
            selectedType: employee.selectedType,
        })
        const index = worthyEmployees.value.findIndex(item => item.id === employee.id)
        if (index >= 0) worthyEmployees.value[index] = response.data.data
    } catch (error) {
        console.error('Failed to update employee:', error)
    }
}

const triggerGrantConfirmation = async (employee: WorthyEmployee) => {
    selectedForGrant.value = employee
    isConfirmGrantVisible.value = true
}

const confirmGrantBonus = async () => {
    if (!selectedForGrant.value) return

    try {
        const response = await BonusListApi.update(selectedForGrant.value.id, {
            isGranted: true
        })

        // Update the local state to reflect the granted status
        const index = worthyEmployees.value.findIndex((e) => e.id === selectedForGrant.value?.id)
        if (index >= 0) worthyEmployees.value[index] = response.data.data

        isConfirmGrantVisible.value = false
        selectedForGrant.value = null
    } catch (error) {
        console.error('Failed to grant bonus:', error)
    }
}

const dismissEmployee = async (id: string) => {
    try {
        await BonusListApi.delete(id)
        worthyEmployees.value = worthyEmployees.value.filter((e) => e.id !== id)
    } catch (error) {
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
    return worthyEmployees.value.filter((item) => {
        const query = searchQuery.value.toLowerCase()
        const matchesSearch =
            item.employeeName.toLowerCase().includes(query) ||
            item.employeeEmail.toLowerCase().includes(query) ||
            item.achievementDetails.toLowerCase().includes(query)

        const matchesDept = departmentFilter.value === 'All' || item.department === departmentFilter.value
        const matchesReason = reasonFilter.value === 'All' || item.eligibilityReason === reasonFilter.value
        const matchesMonth = monthFilter.value === 'All' || item.bonusMonth === monthFilter.value

        let matchesStatus = true
        if (statusFilter.value === 'Eligible') {
            matchesStatus = !item.isGranted
        } else if (statusFilter.value === 'Granted') {
            matchesStatus = item.isGranted
        }

        return matchesSearch && matchesDept && matchesReason && matchesStatus && matchesMonth
    })
})

const totalEligibleCount = computed(() => {
    return worthyEmployees.value.filter((e) => !e.isGranted && (monthFilter.value === 'All' || e.bonusMonth === monthFilter.value)).length
})

const totalGrantedCount = computed(() => {
    return worthyEmployees.value.filter((e) => e.isGranted && (monthFilter.value === 'All' || e.bonusMonth === monthFilter.value)).length
})

const totalRecommendedPool = computed(() => {
    return worthyEmployees.value
        .filter(e => !e.isGranted && (monthFilter.value === 'All' || e.bonusMonth === monthFilter.value))
        .reduce((sum, item) => sum + (Number(item.selectedAmount) || 0), 0)
})

const totalGrantedPool = computed(() => {
    return worthyEmployees.value
        .filter(e => e.isGranted && (monthFilter.value === 'All' || e.bonusMonth === monthFilter.value))
        .reduce((sum, item) => sum + (Number(item.grantedAmount ?? item.selectedAmount) || 0), 0)
})

const getEmployeeName = (employeeId: number) => {
    const employee = employees.value.find(e => e.id === employeeId)
    return employee ? `${employee.name} (${employee.email})` : 'Unknown Employee'
}

const formatMonth = (monthString: string) => {
    const [year, month] = monthString.split('-')
    const date = new Date(parseInt(year), parseInt(month) - 1)
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
    const formatted = formatLocalizedNumber(Number(amount || 0), {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })

    return formatted
}

const getAvatarInitials = (name: string) => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
}

const getReasonColor = (reason: EligibilityReason) => {
    switch (reason) {
        case 'Top Performer': return 'primary'
        case 'Project Milestone': return 'success'
        case 'Client Praise': return 'info'
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
                <h2 class="text-h4 font-weight-bold mb-1">{{ $t('pages.bonuses.bonuslist.bonuslist.bonusEligibilityNominations') }}</h2>
                <p class="text-body-2 text-medium-emphasis mb-0">
                    {{ $t('pages.bonuses.bonuslist.bonuslist.reviewHighPerformingEmployeesWorthyOfRecognitionCustomizePayoutAmounts') }}
                </p>
            </div>
            <div class="d-flex gap-2">
                <v-btn v-if="canAccess('manage_bonus_rules')" variant="outlined" prepend-icon="tabler-adjustments" size="large" @click="isRulesDialogVisible = true">{{ $t('pages.bonuses.bonuslist.bonuslist.bonusRules') }}</v-btn>
                <v-btn color="primary" prepend-icon="tabler-user-plus" elevation="2" size="large" @click="isNominateDialogVisible = true">{{ $t('pages.bonuses.bonuslist.bonuslist.nominateEmployee') }}</v-btn>
            </div>
        </div>

        <!-- Summary KPI Cards -->
        <v-row class="mb-6">
            <!-- Total Eligible -->
            <v-col cols="12" sm="6" md="3">
                <v-card class="pa-4" variant="outlined">
                    <div class="d-flex align-center justify-space-between">
                        <div>
                            <span class="text-caption text-uppercase text-medium-emphasis font-weight-medium">
                                {{ $t('pages.bonuses.bonuslist.bonuslist.eligibleCandidates') }}
                            </span>
                            <h3 class="text-h4 font-weight-bold mt-1">{{ totalEligibleCount }}</h3>
                        </div>
                        <v-avatar color="primary" variant="tonal" size="48" rounded="lg">
                            <v-icon icon="tabler-users" size="24" />
                        </v-avatar>
                    </div>
                </v-card>
            </v-col>

            <!-- Projected Total Pool -->
            <v-col cols="12" sm="6" md="3">
                <v-card class="pa-4" variant="outlined">
                    <div class="d-flex align-center justify-space-between">
                        <div>
                            <span class="text-caption text-uppercase text-medium-emphasis font-weight-medium">
                                {{ $t('pages.bonuses.bonuslist.bonuslist.projectedGrantPoolBase') }}
                            </span>
                            <h3 class="text-h4 font-weight-bold mt-1 text-primary">
                                {{ formatCurrency(totalRecommendedPool) }}
                            </h3>
                        </div>
                        <v-avatar color="success" variant="tonal" size="48" rounded="lg">
                            <v-icon icon="tabler-coin" size="24" />
                        </v-avatar>
                    </div>
                </v-card>
            </v-col>

            <!-- Granted Count -->
            <v-col cols="12" sm="6" md="3">
                <v-card class="pa-4" variant="outlined">
                    <div class="d-flex align-center justify-space-between">
                        <div>
                            <span class="text-caption text-uppercase text-medium-emphasis font-weight-medium">
                                {{ $t('pages.bonuses.bonuslist.bonuslist.grantedBonuses') }}
                            </span>
                            <h3 class="text-h4 font-weight-bold mt-1 text-success">
                                {{ totalGrantedCount }}
                            </h3>
                        </div>
                        <v-avatar color="info" variant="tonal" size="48" rounded="lg">
                            <v-icon icon="tabler-award" size="24" />
                        </v-avatar>
                    </div>
                </v-card>
            </v-col>

            <!-- Granted Pool -->
            <v-col cols="12" sm="6" md="3">
                <v-card class="pa-4" variant="outlined">
                    <div class="d-flex align-center justify-space-between">
                        <div>
                            <span class="text-caption text-uppercase text-medium-emphasis font-weight-medium">
                                {{ $t('pages.bonuses.bonuslist.bonuslist.grantedPoolBase') }}
                            </span>
                            <h3 class="text-h4 font-weight-bold mt-1 text-success">
                                {{ formatCurrency(totalGrantedPool) }}
                            </h3>
                        </div>
                        <v-avatar color="success" variant="tonal" size="48" rounded="lg">
                            <v-icon icon="tabler-currency-dollar" size="24" />
                        </v-avatar>
                    </div>
                </v-card>
            </v-col>
        </v-row>

        <!-- Main Data Table Card -->
        <v-card variant="outlined" class="overflow-hidden">
            <!-- Search & Filters Toolbar -->
            <v-card-text class="pa-4 border-b">
                <v-row density="compact" align="center">
                    <v-col cols="12" md="3">
                        <v-text-field v-model="searchQuery" :placeholder="$t('pages.bonuses.bonuslist.bonuslist.searchCandidateEmailOrAchievement')"
                            prepend-inner-icon="tabler-search" hide-details density="compact" variant="outlined"
                            clearable />
                    </v-col>
                    <v-col cols="12" sm="6" md="2">
                        <v-select v-model="departmentFilter" :items="['All', ...departments]" :label="$t('common.fields.department')"
                            hide-details density="compact" variant="outlined" />
                    </v-col>
                    <v-col cols="12" sm="6" md="2">
                    <v-select v-model="reasonFilter" :items="['All', ...reasons]" :item-title="translateBackendValue" :label="$t('pages.bonuses.bonuslist.bonuslist.eligibilityReason')"
                            hide-details density="compact" variant="outlined" />
                    </v-col>
                    <v-col cols="12" sm="6" md="2">
                    <v-select v-model="statusFilter" :items="['All', 'Eligible', 'Granted']" :item-title="translateBackendValue" :label="$t('common.fields.status')"
                            hide-details density="compact" variant="outlined" />
                    </v-col>
                    <v-col cols="12" sm="6" md="3">
                        <v-select
                            v-model="monthFilter"
                            :items="monthOptions"
                            :label="$t('common.messages.bonusMonth')"
                            item-title="title"
                            item-value="value"
                            hide-details
                            density="compact"
                            variant="outlined"
                        />
                    </v-col>
                </v-row>
            </v-card-text>

            <!-- Data Table -->
            <v-table class="text-no-wrap">
                <thead>
                    <tr>
                        <th class="text-left font-weight-bold text-uppercase text-caption">{{ $t('pages.bonuses.bonuslist.bonuslist.candidate') }}</th>
                        <th class="text-left font-weight-bold text-uppercase text-caption">{{ $t('pages.bonuses.bonuslist.bonuslist.eligibilityAchievements') }}
                        </th>
                        <th class="text-left font-weight-bold text-uppercase text-caption">{{ $t('pages.bonuses.bonuslist.bonuslist.performance') }}</th>
                        <th class="text-left font-weight-bold text-uppercase text-caption">{{ $t('common.messages.bonusMonth') }}</th>
                        <th class="text-left font-weight-bold text-uppercase text-caption" style="min-width: 180px;">
                            {{ $t('pages.bonuses.bonuslist.bonuslist.selectedPayout') }}
                        </th>
                        <th class="text-left font-weight-bold text-uppercase text-caption" style="min-width: 160px;">
                            {{ $t('pages.bonuses.bonuslist.bonuslist.bonusType') }}
                        </th>
                        <th class="text-left font-weight-bold text-uppercase text-caption">{{ $t('common.fields.status') }}</th>
                        <th class="text-center font-weight-bold text-uppercase text-caption">{{ $t('common.messages.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="employee in filteredEmployees" :key="employee.id" class="align-middle">
                        <!-- Employee Column -->
                        <td class="py-4">
                            <div class="d-flex align-center gap-3">
                                <v-avatar color="primary" variant="tonal" size="40" class="font-weight-bold">
                                    <span>{{ getAvatarInitials(employee.employeeName) }}</span>
                                </v-avatar>
                                <div>
                                    <div class="font-weight-medium text-body-1">{{ employee.employeeName }}</div>
                                    <div class="text-caption text-medium-emphasis">{{ employee.department || 'No Department' }} • {{
                                        employee.employeeEmail }}</div>
                                </div>
                            </div>
                        </td>

                        <!-- Achievements & Reason -->
                        <td class="py-4">
                            <div class="mb-1">
                                <v-chip :color="getReasonColor(employee.eligibilityReason)" size="small" variant="flat"
                                    class="font-weight-bold">
                                    {{ translateBackendValue(employee.eligibilityReason) }}
                                </v-chip>
                            </div>
                            <VTooltip :text="employee.achievementDetails" location="top">
                                <template #activator="{ props }">
                                    <div v-bind="props" class="text-caption text-medium-emphasis achievement-text">
                                        {{ employee.achievementDetails }}
                                    </div>
                                </template>
                            </VTooltip>
                        </td>

                        <!-- Performance Rating -->
                        <td class="py-4">
                            <div class="d-flex align-center gap-1">
                                <v-icon icon="tabler-star-filled" color="warning" size="18" />
                                <span class="font-weight-bold text-body-1">{{ employee.performanceScore.toFixed(1)
                                    }}</span>
                                <span class="text-caption text-medium-emphasis">/ 5.0</span>
                            </div>
                        </td>

                        <!-- Bonus Month -->
                        <td class="py-4">
                            <div class="text-body-1 font-weight-medium">
                                {{ formatMonth(employee.bonusMonth) }}
                            </div>
                        </td>

                        <!-- Interactive Inline Amount Selection -->
                        <td class="py-4">
                            <v-combobox
                                v-model.number="employee.selectedAmount"
                                :items="quickAmounts"
                                density="compact"
                                variant="outlined"
                                hide-details
                                type="number"
                                step="100"
                                class="amount-selector font-weight-bold text-primary"
                                :disabled="employee.isGranted"
                                @update:modelValue="!employee.isGranted && updateEmployee(employee)"
                            />
                            <div class="text-caption text-medium-emphasis mt-1">
                                {{ $t('pages.bonuses.bonuslist.suggestedAmount', { amount: formatCurrency(employee.suggestedAmount) }) }}
                            </div>
                        </td>

                        <!-- Interactive Inline Bonus Type -->
                        <td class="py-4">
                            <v-select
                                v-model="employee.selectedType"
                                :items="bonusTypes" :item-title="translateBackendValue"
                                density="compact"
                                variant="outlined"
                                hide-details
                                :disabled="employee.isGranted"
                                @update:modelValue="!employee.isGranted && updateEmployee(employee)"
                            />
                        </td>

                        <!-- Status -->
                        <td class="py-4">
                            <v-chip :color="getStatusColor(employee.isGranted)" size="small" variant="flat"
                                class="font-weight-bold">
                                {{ employee.isGranted ? 'Granted' : 'Eligible' }}
                            </v-chip>
                        </td>

                        <!-- Actions -->
                        <td class="text-center py-4">
                            <div class="d-flex justify-center align-center gap-2">
                                <FullDetailsButton :item="employee" :title="employee.employeeName" />
                                <v-tooltip :text="$t('pages.bonuses.bonuslist.bonuslist.viewCalculationEvidence')" location="top">
                                    <template #activator="{ props }">
                                        <v-btn v-if="employee.isAutomatic" v-bind="props" icon="tabler-calculator" size="small" variant="tonal" color="info"
                                            @click="showCalculation(employee)" />
                                    </template>
                                </v-tooltip>
                                <!-- Grant Button (only for eligible employees) -->
                                <v-btn v-if="!employee.isGranted" color="primary" variant="elevated" size="small" prepend-icon="tabler-check"
                                    @click="triggerGrantConfirmation(employee)">
                                    {{ $t('common.actions.grant') }}
                                </v-btn>

                                <!-- Dismiss/Ignore Tooltip -->
                                <v-tooltip :text="$t('pages.bonuses.bonuslist.bonuslist.dismissFromList')" location="top">
                                    <template #activator="{ props }">
                                        <v-btn v-bind="props" icon="tabler-x" size="small" variant="text"
                                            color="medium-emphasis" @click="dismissEmployee(employee.id)" />
                                    </template>
                                </v-tooltip>
                            </div>
                        </td>
                    </tr>

                    <!-- Empty State -->
                    <tr v-if="filteredEmployees.length === 0">
                        <td colspan="8" class="text-center py-10 text-medium-emphasis">
                            <v-icon icon="tabler-award-off" size="48" class="mb-2" />
                            <div class="text-h6">{{ $t('pages.bonuses.bonuslist.bonuslist.noMatchingCandidatesFound') }}</div>
                            <div class="text-caption">{{ $t('pages.bonuses.bonuslist.bonuslist.tryAdjustingYourFiltersOrNominateANewEmployee') }}</div>
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </v-card>

        <!-- Grant Confirmation Modal -->
        <v-dialog v-model="isConfirmGrantVisible" max-width="460">
            <v-card rounded="lg">
                <v-card-title class="pa-5 pb-3 d-flex align-center gap-2 text-h5 font-weight-bold">
                    <v-icon icon="tabler-gift" color="primary" />
                    {{ $t('pages.bonuses.bonuslist.bonuslist.confirmBonusGrant') }}
                </v-card-title>

                <v-divider />

                <v-card-text class="pa-5 text-body-1">
                    {{ $t('pages.bonuses.bonuslist.grantConfirmation', {
                        type: translateBackendValue(selectedForGrant?.selectedType),
                        employee: selectedForGrant?.employeeName,
                        month: selectedForGrant ? formatMonth(selectedForGrant.bonusMonth) : '',
                    }) }}

                    <div
                        class="d-flex justify-space-between align-center pa-4 mt-4 bg-var-theme-surface rounded border">
                        <span class="text-medium-emphasis font-weight-medium">{{ $t('pages.bonuses.bonuslist.bonuslist.finalAmount') }}</span>
                        <span class="text-h5 font-weight-bold text-primary">
                            {{ formatCurrency(selectedForGrant?.selectedAmount || 0) }}
                        </span>
                    </div>
                </v-card-text>

                <v-card-actions class="pa-4 justify-end gap-2">
                    <v-btn variant="outlined" color="secondary" @click="isConfirmGrantVisible = false">{{ $t('common.actions.cancel') }}</v-btn>
                    <v-btn color="primary" elevation="1" @click="confirmGrantBonus">{{ $t('pages.bonuses.bonuslist.bonuslist.confirmGrant') }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="isCalculationDialogVisible" max-width="900">
            <v-card rounded="lg">
                <v-card-title class="pa-5 d-flex align-center justify-space-between">
                    <div><div class="text-h5 font-weight-bold">{{ $t('pages.bonuses.bonuslist.bonuslist.bonusCalculationEvidence') }}</div><div class="text-caption">{{ $t('pages.bonuses.bonuslist.calculationSummary', { version: selectedCalculation?.ruleSnapshot?.version, score: selectedCalculation?.calculationScore }) }}</div></div>
                    <v-btn icon="tabler-x" variant="text" @click="isCalculationDialogVisible = false" />
                </v-card-title>
                <v-divider />
                <v-card-text class="pa-5">
                    <v-alert type="info" variant="tonal" class="mb-4">{{ selectedCalculation?.achievementDetails }}</v-alert>
                    <v-table density="compact">
                        <thead><tr><th>{{ $t('pages.bonuses.bonuslist.bonuslist.task') }}</th><th>{{ $t('common.fields.priority') }}</th><th>{{ $t('pages.bonuses.bonuslist.bonuslist.participation') }}</th><th>{{ $t('pages.bonuses.bonuslist.bonuslist.quality') }}</th><th>{{ $t('pages.bonuses.bonuslist.bonuslist.timing') }}</th><th>{{ $t('pages.bonuses.bonuslist.bonuslist.penalties') }}</th><th>{{ $t('pages.bonuses.bonuslist.bonuslist.score') }}</th><th>{{ $t('common.messages.actions') }}</th></tr></thead>
                        <tbody><tr v-for="task in selectedCalculation?.calculationInputs?.tasks ?? []" :key="String(task.task_id)">
                            <td>{{ task.task_title }}</td><td>{{ translateBackendValue(task.priority) }} × {{ task.priority_weight }}</td><td>{{ task.participation_percent }}%</td>
                            <td>{{ task.quality_score }}/5</td><td>{{ task.timing_points }}</td><td>{{ Number(task.reopened_points || 0) + Number(task.rejected_points || 0) }}</td><td>{{ task.score }}</td><td><FullDetailsButton :item="task" :title="task.task_title" /></td>
                        </tr></tbody>
                    </v-table>
                    <div class="text-caption text-medium-emphasis mt-4">{{ selectedCalculation?.calculationInputs?.formula }}</div>
                </v-card-text>
            </v-card>
        </v-dialog>

        <BonusRulesDialog v-model="isRulesDialogVisible" />

        <!-- Nominate Employee Modal -->
        <v-dialog v-model="isNominateDialogVisible" max-width="600">
            <v-card rounded="lg">
                <v-card-title class="pa-5 d-flex justify-space-between align-center">
                    <span class="text-h5 font-weight-bold">{{ $t('pages.bonuses.bonuslist.bonuslist.nominateForBonus') }}</span>
                    <v-btn icon="tabler-x" variant="text" density="compact" @click="isNominateDialogVisible = false" />
                </v-card-title>

                <v-divider />

                <v-card-text class="pa-5">
                    <v-form class="app-form" @submit.prevent="submitNomination">
                        <v-alert v-if="nominationErrors.form" type="error" variant="tonal" class="mb-4">
                            {{ nominationErrors.form }}
                        </v-alert>
                        <v-row density="comfortable">
                            <v-col cols="12">
                                <v-select
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
                                    <template v-slot:item="{ props, item }">
                                        <v-list-item
                                            v-bind="props"
                                            :title="`${item.raw.name} (${item.raw.email})`"
                                            :subtitle="item.raw.department || 'No Department'"
                                        ></v-list-item>
                                    </template>
                                </v-select>
                            </v-col>

                            <v-col cols="12" sm="6">
                                <v-select v-model="nominationForm.eligibilityReason" :items="reasons" :item-title="translateBackendValue"
                                    :label="$t('pages.bonuses.bonuslist.bonuslist.eligibilityTrigger')" variant="outlined" />
                            </v-col>

                            <v-col cols="12" sm="6">
                                <v-select
                                    v-model="nominationForm.bonusMonth"
                                    :items="monthOptions.filter(m => m.value !== 'All')"
                                    :label="$t('pages.bonuses.bonuslist.bonuslist.bonusMonth')"
                                    item-title="title"
                                    item-value="value"
                                    variant="outlined"
                                    :error-messages="nominationErrors.bonusMonth"
                                />
                            </v-col>

                            <v-col cols="12" sm="6">
                                <v-select v-model="nominationForm.performanceScore" :items="performanceScores"
                                    :label="$t('pages.bonuses.bonuslist.bonuslist.performanceScore')" variant="outlined" suffix="/5.0" :error-messages="nominationErrors.performanceScore" />
                            </v-col>

                            <v-col cols="12" sm="6">
                                <v-select v-model="nominationForm.selectedType" :items="bonusTypes" :item-title="translateBackendValue"
                                    :label="$t('pages.bonuses.bonuslist.bonuslist.bonusCategory')" variant="outlined" />
                            </v-col>

                            <v-col cols="12" sm="6">
                                <v-text-field v-model.number="nominationForm.suggestedAmount"
                                    :label="$t('pages.bonuses.bonuslist.bonuslist.suggestedAmount')" type="number" step="100" variant="outlined"
                                    readonly :hint="$t('pages.bonuses.bonuslist.bonuslist.calculatedRecommendationAdjustTheSelectedAmountIfNeeded')"
                                    persistent-hint required :error-messages="nominationErrors.suggestedAmount" />
                            </v-col>

                            <v-col cols="12" sm="6">
                                <v-text-field v-model.number="nominationForm.selectedAmount"
                                    :label="$t('pages.bonuses.bonuslist.bonuslist.selectedAmount')" type="number" step="100" variant="outlined"
                                    required :error-messages="nominationErrors.selectedAmount" />
                            </v-col>

                            <v-col cols="12">
                                <v-textarea v-model="nominationForm.achievementDetails"
                                    :label="$t('pages.bonuses.bonuslist.bonuslist.achievementJustification')"
                                    :placeholder="$t('pages.bonuses.bonuslist.bonuslist.explainWhyThisEmployeeDeservesRecognition')" variant="outlined"
                                    rows="3" :error-messages="nominationErrors.achievementDetails" />
                            </v-col>
                        </v-row>
                    </v-form>
                </v-card-text>

                <v-divider />

                <v-card-actions class="pa-4 justify-end gap-2">
                    <v-btn variant="outlined" color="secondary" @click="isNominateDialogVisible = false">{{ $t('common.actions.cancel') }}</v-btn>
                    <v-btn color="primary" elevation="1" @click="submitNomination">{{ $t('pages.bonuses.bonuslist.bonuslist.addToWorthyList') }}</v-btn>
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
