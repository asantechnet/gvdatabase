<script setup lang="ts">
import AutomationApi, { type BonusRules, type BonusRuleSet } from '@/api/automation'
import EmployeeApi, { type Employee } from '@/api/employee'
import { apiErrorMessage, apiFieldErrors } from '@/utils/apiErrors'
import { computed, ref, watch } from 'vue'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void }>()
const dialog = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
const ruleSet = ref<BonusRuleSet | null>(null)
const form = ref<BonusRules | null>(null)
const employees = ref<Employee[]>([])
const isLoading = ref(false)
const isSaving = ref(false)
const errors = ref<Record<string, string>>({})

watch(() => props.modelValue, async open => {
  if (!open) return
  isLoading.value = true
  errors.value = {}
  try {
    const [rules, employeeResponse] = await Promise.all([AutomationApi.getBonusRules(), EmployeeApi.getAll()])
    ruleSet.value = rules
    form.value = structuredClone(rules.rules)
    employees.value = employeeResponse.data.data
  } catch (error) {
    errors.value.form = apiErrorMessage(error, 'Unable to load bonus rules.')
  } finally {
    isLoading.value = false
  }
})

const save = async () => {
  if (!form.value) return
  isSaving.value = true
  errors.value = {}
  try {
    ruleSet.value = await AutomationApi.updateBonusRules(form.value)
    form.value = structuredClone(ruleSet.value.rules)
    dialog.value = false
  } catch (error) {
    errors.value = apiFieldErrors(error)
    if (!Object.keys(errors.value).length) errors.value.form = apiErrorMessage(error, 'Unable to save bonus rules.')
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <VDialog v-model="dialog" max-width="980" persistent>
    <VCard>
      <VCardTitle class="pa-5 d-flex justify-space-between align-center">
        <div>
          <div class="text-h5 font-weight-bold">{{ $t('pages.bonuses.bonusrulesdialog.bonusrulesdialog.taskBonusRules') }}</div>
          <div class="text-caption text-medium-emphasis">{{ $t('components.dialogs.bonusRules.currentVersion', { version: ruleSet?.version ?? '—' }) }}</div>
        </div>
        <VBtn icon="tabler-x" variant="text" :disabled="isSaving" @click="dialog = false" />
      </VCardTitle>
      <VDivider />
      <VCardText class="pa-5">
        <VProgressLinear v-if="isLoading" indeterminate class="mb-4" />
        <VAlert v-if="errors.form" type="error" variant="tonal" class="mb-4">{{ errors.form }}</VAlert>
        <VForm v-if="form" @submit.prevent="save">
          <VRow>
            <VCol cols="12"><div class="text-subtitle-1 font-weight-bold">{{ $t('pages.bonuses.bonusrulesdialog.bonusrulesdialog.scoring') }}</div></VCol>
            <VCol cols="12" md="3"><VTextField v-model.number="form.completed_task_points" type="number" min="0" :label="$t('pages.bonuses.bonusrulesdialog.bonusrulesdialog.completedTaskPoints')" :error-messages="errors.completed_task_points" /></VCol>
            <VCol v-for="priority in (['low', 'medium', 'high', 'urgent'] as const)" :key="priority" cols="6" md="2"><VTextField v-model.number="form.priority_weights[priority]" type="number" min="0" step="0.1" :label="$t('components.dialogs.bonusRules.priorityWeight', { priority: translateBackendValue(priority) })" /></VCol>
            <VCol cols="6" md="3"><VTextField v-model.number="form.before_deadline_points" type="number" :label="$t('pages.bonuses.bonusrulesdialog.bonusrulesdialog.onTimePoints')" /></VCol>
            <VCol cols="6" md="3"><VTextField v-model.number="form.after_deadline_points" type="number" :label="$t('pages.bonuses.bonusrulesdialog.bonusrulesdialog.latePoints')" /></VCol>
            <VCol cols="6" md="3"><VTextField v-model.number="form.reopened_penalty" type="number" max="0" :label="$t('pages.bonuses.bonusrulesdialog.bonusrulesdialog.reopenPenalty')" /></VCol>
            <VCol cols="6" md="3"><VTextField v-model.number="form.rejected_penalty" type="number" max="0" :label="$t('pages.bonuses.bonusrulesdialog.bonusrulesdialog.rejectionPenalty')" /></VCol>
            <VCol cols="6" md="3"><VTextField v-model.number="form.default_quality_score" type="number" min="1" max="5" step="0.5" :label="$t('pages.bonuses.bonusrulesdialog.bonusrulesdialog.defaultQuality15')" /></VCol>
            <VCol cols="6" md="3"><VSwitch v-model="form.quality_multiplier_enabled" :label="$t('pages.bonuses.bonusrulesdialog.bonusrulesdialog.applyQualityMultiplier')" color="primary" /></VCol>

            <VCol cols="12"><VDivider class="my-2" /><div class="text-subtitle-1 font-weight-bold mt-4">{{ $t('pages.bonuses.bonusrulesdialog.bonusrulesdialog.eligibilityAndPayout') }}</div></VCol>
            <VCol cols="6" md="3"><VTextField v-model.number="form.minimum_participation_percent" type="number" min="0" max="100" suffix="%" :label="$t('pages.bonuses.bonusrulesdialog.bonusrulesdialog.minimumParticipation')" /></VCol>
            <VCol cols="6" md="3"><VTextField v-model.number="form.minimum_completed_tasks" type="number" min="1" :label="$t('pages.bonuses.bonusrulesdialog.bonusrulesdialog.minimumTasks')" /></VCol>
            <VCol cols="6" md="3"><VTextField v-model.number="form.monthly_score_threshold" type="number" min="0" :label="$t('pages.bonuses.bonusrulesdialog.bonusrulesdialog.scoreThreshold')" /></VCol>
            <VCol cols="6" md="3"><VTextField v-model.number="form.amount_per_point" type="number" min="0" :label="$t('pages.bonuses.bonusrulesdialog.bonusrulesdialog.baseAmountPerPoint')" /></VCol>
            <VCol cols="12" md="4"><VTextField v-model.number="form.maximum_monthly_bonus" type="number" min="0" :label="$t('pages.bonuses.bonusrulesdialog.bonusrulesdialog.maximumMonthlyBonus')" /></VCol>
            <VCol cols="12" md="8"><VSelect v-model="form.excluded_employee_ids" :items="employees" item-title="name" item-value="id" multiple chips closable-chips :label="$t('pages.bonuses.bonusrulesdialog.bonusrulesdialog.excludedEmployees')" /></VCol>
            <VCol cols="12"><VCombobox v-model="form.excluded_departments" multiple chips closable-chips :label="$t('pages.bonuses.bonusrulesdialog.bonusrulesdialog.excludedDepartments')" :hint="$t('pages.bonuses.bonusrulesdialog.bonusrulesdialog.enterADepartmentAndPressEnter')" persistent-hint /></VCol>
          </VRow>
        </VForm>
      </VCardText>
      <VDivider />
      <VCardActions class="pa-4 justify-end">
        <VBtn variant="outlined" :disabled="isSaving" @click="dialog = false">{{ $t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :loading="isSaving" :disabled="!form" @click="save">{{ $t('pages.bonuses.bonusrulesdialog.bonusrulesdialog.saveAsNewVersion') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
