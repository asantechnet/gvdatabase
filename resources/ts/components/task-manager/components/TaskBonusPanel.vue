<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import TaskApi, { type TaskBonusPreview } from '@/api/task'
import type { Task } from '@/api/kanban'
import { apiErrorMessage } from '@/utils/apiErrors'
import { currentLocale } from '@/utils/localization'
import { formatMoney as formatCurrency } from '@/utils/money'
import ErpField from '@/components/erp/ErpField.vue'

const props = defineProps<{ task: Task }>()
const { t } = useI18n()
const toast = useToast()

const preview = ref<TaskBonusPreview | null>(null)
const isLoading = ref(false)
const isSaving = ref(false)
const error = ref('')
const quality = ref(3)
const rejected = ref(false)
const contributions = ref<Record<string, number>>({})

const round4 = (value: number) => Math.round(value * 10000) / 10000

const load = async (resetInputs = true) => {
  isLoading.value = true
  error.value = ''
  try {
    const data = await TaskApi.getBonusPreview(props.task.id)

    preview.value = data
    if (resetInputs) {
      quality.value = props.task.managerQualityScore ?? 3
      rejected.value = false
      // The save endpoint keys contributions by user id, not employee id.
      contributions.value = Object.fromEntries(
        data.assignees.map(row => [String(row.userId), row.contributionPercent]),
      )
    }
  }
  catch (loadError) {
    error.value = apiErrorMessage(loadError, t('components.taskManager.taskbonuspanel.error'))
  }
  finally {
    isLoading.value = false
  }
}

watch(() => props.task.id, () => load(true), { immediate: true })

const hasAssignees = computed(() => (preview.value?.assignees.length ?? 0) > 0)

const qualityFactor = computed(() => {
  const rules = preview.value?.rules

  if (!rules)
    return 1

  return rules.task_quality_weighting_enabled
    ? Math.min(1.5, Math.max(0.5, quality.value / 3))
    : 1
})

const revisionFactor = computed(() => Math.max(0.5, 1 - ((props.task.rejectedCount + (rejected.value ? 1 : 0)) * 0.1)))

const rows = computed(() => {
  const current = preview.value
  if (!current)
    return []

  const rules = current.rules
  const decimals = current.currency?.decimalPlaces ?? 2
  const scale = 10 ** decimals

  return current.assignees.map(row => {
    const key = String(row.userId)
    const contribution = Number(contributions.value[key] ?? row.contributionPercent ?? 0)
    const thisTaskCredit = round4((contribution / 100) * qualityFactor.value * revisionFactor.value)
    const monthCredit = round4(row.otherCredit + thisTaskCredit)
    const reasons = row.reasons.filter(reason => ['employee_unavailable', 'excluded_employee', 'excluded_office'].includes(reason))

    if (monthCredit < rules.minimum_completed_tasks)
      reasons.push('minimum_task_credit')
    if (row.casesWon < rules.minimum_cases_won)
      reasons.push('minimum_cases')

    const rawAmount = monthCredit * rules.amount_per_completed_task + row.casesWon * rules.amount_per_case_won
    const capped = rawAmount > rules.maximum_monthly_bonus

    return {
      ...row,
      key,
      contribution,
      thisTaskCredit,
      monthCredit,
      reasons,
      eligible: reasons.length === 0,
      capped,
      uncappedAmount: Math.round(rawAmount * scale) / scale,
      projectedAmount: Math.round(Math.min(rawAmount, rules.maximum_monthly_bonus) * scale) / scale,
    }
  })
})

const totalContribution = computed(() => rows.value.reduce((total, row) => total + Number(row.contribution || 0), 0))
const totalsBalanced = computed(() => Math.abs(totalContribution.value - 100) <= 0.01)

const minimumsText = computed(() => {
  const rules = preview.value?.rules
  if (!rules)
    return ''

  const parts: string[] = []

  if (rules.minimum_completed_tasks > 0) {
    parts.push(t('components.taskManager.taskbonuspanel.minimumTaskCredit', {
      amount: formatCredit(rules.minimum_completed_tasks),
    }))
  }
  if (rules.minimum_cases_won > 0) {
    parts.push(t('components.taskManager.taskbonuspanel.minimumCaseWins', {
      count: rules.minimum_cases_won,
    }))
  }

  return parts.join(' · ')
})

const rulesSummaryText = computed(() => {
  const rules = preview.value?.rules
  if (!rules)
    return ''

  return t('components.taskManager.taskbonuspanel.rulesSummary', {
    taskReward: formatMoney(rules.amount_per_completed_task),
    caseReward: formatMoney(rules.amount_per_case_won),
    cap: formatMoney(rules.maximum_monthly_bonus),
  })
})

const monthLabel = computed(() => preview.value
  ? formatLocalizedDate(new Date(`${preview.value.month}-01T00:00:00`), { month: 'long', year: 'numeric' })
  : '')

// Money follows the shared presentation rule: the currency code is printed
// once. The raw AFN symbol is an RTL character and reorders around digits.
const formatMoney = (amount: number | null | undefined) => amount === null || amount === undefined
  ? '—'
  : formatCurrency(Number(amount), preview.value?.currency, currentLocale())

const formatCredit = (value: number) => formatLocalizedNumber(Number(value || 0), {
  minimumFractionDigits: 0,
  maximumFractionDigits: 2,
})

const initials = (name: string) => name
  .split(' ')
  .filter(Boolean)
  .map(part => part[0])
  .join('')
  .toUpperCase()
  .slice(0, 2)

const reasonText = (row: (typeof rows.value)[number]) => {
  if (row.reasons.includes('excluded_employee') || row.reasons.includes('excluded_office'))
    return t('components.taskManager.taskbonuspanel.excluded')
  if (row.reasons.includes('employee_unavailable'))
    return t('components.taskManager.taskbonuspanel.employeeUnavailable')

  const parts: string[] = []
  const rules = preview.value?.rules

  if (rules && row.reasons.includes('minimum_task_credit')) {
    parts.push(t('components.taskManager.taskbonuspanel.needsCredit', {
      amount: formatCredit(Math.max(0, rules.minimum_completed_tasks - row.monthCredit)),
    }))
  }
  if (rules && row.reasons.includes('minimum_cases')) {
    parts.push(t('components.taskManager.taskbonuspanel.needsCases', {
      count: rules.minimum_cases_won - row.casesWon,
    }))
  }

  return parts.join(' · ')
}

const save = async () => {
  if (!preview.value)
    return

  error.value = ''

  if (hasAssignees.value && !totalsBalanced.value) {
    error.value = t('components.taskManager.taskbonuspanel.contributionRequired')

    return
  }

  isSaving.value = true
  try {
    const updated = await TaskApi.reviewBonusInputs(props.task.id, {
      quality_score: quality.value,
      rejected: rejected.value,
      ...(hasAssignees.value ? { contributions: contributions.value } : {}),
    })

    Object.assign(props.task, updated)
    rejected.value = false
    toast.success(t('components.taskManager.taskbonuspanel.saved'))
    await load(false)
  }
  catch (saveError) {
    error.value = apiErrorMessage(saveError, t('components.taskManager.taskbonuspanel.error'))
  }
  finally {
    isSaving.value = false
  }
}
</script>

<template>
  <div class="task-bonus-panel">
    <VProgressLinear
      v-if="isLoading"
      indeterminate
      color="primary"
      class="mb-3"
    />

    <template v-if="preview">
      <div class="text-caption text-medium-emphasis">
        <span class="font-weight-medium">{{ monthLabel }}</span>
        <span class="mx-1">·</span>
        {{ rulesSummaryText }}
      </div>
      <div
        v-if="minimumsText"
        class="text-caption text-medium-emphasis mt-1"
      >
        {{ minimumsText }}
      </div>

      <VAlert
        v-if="preview.alreadyProcessed"
        type="info"
        variant="tonal"
        class="mt-3"
      >
        {{ t('components.taskManager.taskbonuspanel.alreadyProcessed', { month: monthLabel }) }}
      </VAlert>
      <VAlert
        v-else-if="!hasAssignees"
        type="warning"
        variant="tonal"
        class="mt-3"
      >
        {{ t('components.taskManager.taskbonuspanel.noAssignees') }}
      </VAlert>

      <div class="d-flex align-center justify-space-between ga-2 mt-4">
        <span class="text-body-2 d-flex align-center ga-1">
          {{ t('components.taskManager.taskbonuspanel.quality') }}
          <VTooltip
            location="top"
            :text="t('components.taskManager.taskbonuspanel.qualityHint')"
          >
            <template #activator="{ props: tooltipProps }">
              <VIcon
                v-bind="tooltipProps"
                icon="tabler-info-circle"
                size="15"
                class="text-medium-emphasis"
              />
            </template>
          </VTooltip>
        </span>
        <span class="text-caption text-medium-emphasis">
          {{ t('components.taskManager.taskbonuspanel.qualityScore', { score: Number(quality).toFixed(1) }) }}
          <span class="mx-1">·</span>
          {{ t('components.taskManager.taskbonuspanel.qualityFactor', { factor: qualityFactor.toFixed(2) }) }}
        </span>
      </div>
      <VSlider
        v-model="quality"
        :min="1"
        :max="5"
        :step="0.5"
        :aria-label="t('components.taskManager.taskbonuspanel.quality')"
        density="compact"
        hide-details
        class="mt-1"
      />

      <VCard
        v-for="row in rows"
        :key="row.key"
        variant="flat"
        class="task-bonus-panel__staff pa-4 mb-3 mt-3"
      >
        <div class="d-flex align-start justify-space-between ga-3">
          <div class="d-flex align-center ga-2 min-width-0">
            <VAvatar
              size="26"
              color="primary"
              variant="tonal"
            >
              <span class="text-caption font-weight-medium">{{ initials(row.name) }}</span>
            </VAvatar>
            <span class="font-weight-medium text-truncate">{{ row.name }}</span>
          </div>
          <div class="text-end flex-shrink-0">
            <div class="text-caption text-medium-emphasis">
              {{ t('components.taskManager.taskbonuspanel.projected') }}
            </div>
            <div
              class="font-weight-bold"
              :class="row.eligible ? 'text-primary' : 'text-medium-emphasis'"
            >
              {{ row.eligible ? formatMoney(row.projectedAmount) : '—' }}
            </div>
          </div>
        </div>

        <div class="d-flex flex-wrap align-center ga-2 mt-3">
          <div class="task-bonus-panel__contribution">
            <ErpField
              v-model.number="contributions[row.key]"
              type="number"
              min="0"
              max="100"
              suffix="%"
              :label="t('components.taskManager.taskbonuspanel.contribution')"
              density="compact"
              hide-details
            />
          </div>
          <VChip
            v-if="row.eligible"
            color="success"
            size="small"
            variant="tonal"
          >
            {{ t('components.taskManager.taskbonuspanel.qualifies') }}
          </VChip>
          <VChip
            v-else
            color="warning"
            size="small"
            variant="tonal"
          >
            {{ reasonText(row) }}
          </VChip>
        </div>

        <div class="text-caption text-medium-emphasis mt-2">
          {{ t('components.taskManager.taskbonuspanel.thisTask') }} {{ formatCredit(row.thisTaskCredit) }}
          <span class="mx-1">·</span>
          {{ t('components.taskManager.taskbonuspanel.monthToDate') }} {{ formatCredit(row.monthCredit) }}
          <span class="mx-1">·</span>
          {{ t('components.taskManager.taskbonuspanel.casesWon') }} {{ row.casesWon }}
        </div>

        <div
          v-if="row.eligible && row.capped"
          class="text-caption text-warning mt-1"
        >
          {{ t('components.taskManager.taskbonuspanel.capNote', { amount: formatMoney(row.uncappedAmount) }) }}
        </div>
        <div
          v-if="row.nomination?.isGranted"
          class="text-caption text-success mt-1"
        >
          {{ t('components.taskManager.taskbonuspanel.alreadyGranted', { month: monthLabel }) }}
        </div>
        <div
          v-else-if="row.nomination"
          class="text-caption text-medium-emphasis mt-1"
        >
          {{ t('components.taskManager.taskbonuspanel.nominated', {
            amount: formatMoney(row.nomination.suggestedAmount),
          }) }}
        </div>
      </VCard>

      <div class="d-flex flex-wrap justify-space-between align-center ga-2 text-caption mt-3 mb-1">
        <span class="text-medium-emphasis">{{ t('components.taskManager.taskbonuspanel.contributionHint') }}</span>
        <span :class="totalsBalanced ? 'text-success' : 'text-warning'">
          {{ t('components.taskManager.taskbonuspanel.totalContribution', { total: formatCredit(totalContribution) }) }}
        </span>
      </div>

      <VCheckbox
        v-model="rejected"
        :label="t('components.taskManager.taskbonuspanel.rejection')"
        color="warning"
        density="compact"
        hide-details
      />
      <div class="text-caption text-medium-emphasis mb-3">
        {{ t('components.taskManager.taskbonuspanel.rejectionHint', {
          count: props.task.rejectedCount,
          percent: Math.round((1 - revisionFactor) * 100),
        }) }}
      </div>

      <VAlert
        v-if="error"
        type="error"
        variant="tonal"
        class="mb-2"
      >
        {{ error }}
      </VAlert>
      <VBtn
        block
        color="primary"
        prepend-icon="tabler-device-floppy"
        :loading="isSaving"
        @click="save"
      >
        {{ t('components.taskManager.taskbonuspanel.save') }}
      </VBtn>
    </template>

    <VAlert
      v-else-if="error"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </VAlert>
  </div>
</template>

<style scoped>
.task-bonus-panel__contribution {
  flex: 0 0 auto;
  inline-size: 160px;
}

.task-bonus-panel > .text-caption:first-of-type { display: block; padding: .75rem 1rem; border-radius: .7rem; background: rgba(var(--v-theme-primary), .06); line-height: 1.6; }
.task-bonus-panel__staff { border: 1px solid rgba(var(--v-border-color), .45); border-radius: .85rem; background: rgba(var(--v-theme-on-surface), .025); }
.task-bonus-panel__staff:nth-of-type(even) { background: rgba(var(--v-theme-primary), .045); }
</style>
