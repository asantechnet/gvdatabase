<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import AutomationApi, { type BonusRuleSet, type BonusRules } from '@/api/automation'
import EmployeeApi, { type Employee } from '@/api/employee'
import OfficeApi, { type Office } from '@/api/office'
import { apiErrorMessage, apiFieldErrors } from '@/utils/apiErrors'
import { useI18n } from 'vue-i18n'

const props = defineProps<{ modelValue: boolean }>()
const { t } = useI18n()
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void }>()
const dialog = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
const ruleSet = ref<BonusRuleSet | null>(null)
const form = ref<BonusRules | null>(null)
const employees = ref<Employee[]>([])
const offices = ref<Office[]>([])
const isLoading = ref(false)
const isSaving = ref(false)
const errors = ref<Record<string, string>>({})

watch(() => props.modelValue, async open => {
  if (!open)
    return
  isLoading.value = true
  errors.value = {}
  try {
    const [rules, employeeResponse, officeRows] = await Promise.all([AutomationApi.getBonusRules(), EmployeeApi.getAll(), OfficeApi.getAll()])

    ruleSet.value = rules
    form.value = structuredClone(rules.rules)
    employees.value = employeeResponse.data.data
    offices.value = officeRows.filter(office => office.status === 'active')
  }
  catch (error) {
    errors.value.form = apiErrorMessage(error, t('components.bonusRules.errors.load'))
  }
  finally {
    isLoading.value = false
  }
})

const save = async () => {
  if (!form.value)
    return
  isSaving.value = true
  errors.value = {}
  try {
    ruleSet.value = await AutomationApi.updateBonusRules(form.value)
    form.value = structuredClone(ruleSet.value.rules)
    dialog.value = false
  }
  catch (error) {
    errors.value = apiFieldErrors(error)
    if (!Object.keys(errors.value).length)
      errors.value.form = apiErrorMessage(error, t('components.bonusRules.errors.save'))
  }
  finally {
    isSaving.value = false
  }
}
</script>

<template>
  <VDialog
    v-model="dialog"
    max-width="720"
    persistent
  >
    <VCard>
      <VCardTitle class="pa-5 d-flex justify-space-between align-center">
        <div>
          <div class="text-h5 font-weight-bold">
            {{ $t('components.bonusRules.title') }}
          </div><div class="text-caption text-medium-emphasis">
            {{ $t('components.bonusRules.version', { version: ruleSet?.version ?? '—' }) }}
          </div>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          :disabled="isSaving"
          @click="dialog = false"
        />
      </VCardTitle>
      <VDivider />
      <VCardText class="pa-5">
        <VProgressLinear
          v-if="isLoading"
          indeterminate
          class="mb-4"
        />
        <VAlert
          v-if="errors.form"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ errors.form }}
        </VAlert>
        <VForm
          v-if="form"
          @submit.prevent="save"
        >
          <VAlert
            type="info"
            variant="tonal"
            class="mb-5"
            icon="tabler-sparkles"
          >
            {{ $t('components.bonusRules.explanation') }}
          </VAlert>
          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model.number="form.minimum_completed_tasks"
                type="number"
                min="1"
                :label="$t('components.bonusRules.minimumTasks')"
                :hint="$t('components.bonusRules.minimumTasksHint')"
                persistent-hint
                :error-messages="errors.minimum_completed_tasks"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="form.minimum_cases_won"
                type="number"
                min="1"
                :label="$t('components.bonusRules.minimumCases')"
                :hint="$t('components.bonusRules.minimumCasesHint')"
                persistent-hint
                :error-messages="errors.minimum_cases_won"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="form.amount_per_case_won"
                type="number"
                min="0"
                :label="$t('components.bonusRules.amountPerCase')"
                :error-messages="errors.amount_per_case_won"
              />
            </VCol>
            <VCol cols="12">
              <VSwitch
                v-model="form.task_quality_weighting_enabled"
                :label="$t('components.bonusRules.qualityWeighting')"
                :error-messages="errors.task_quality_weighting_enabled"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model.number="form.amount_per_completed_task"
                type="number"
                min="0"
                :label="$t('components.bonusRules.amountPerTask')"
                :error-messages="errors.amount_per_completed_task"
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model.number="form.maximum_monthly_bonus"
                type="number"
                min="0"
                :label="$t('components.bonusRules.maximumMonthlyBonus')"
                :error-messages="errors.maximum_monthly_bonus"
              />
            </VCol>
            <VCol cols="12">
              <VDivider class="my-2" /><div class="text-subtitle-2 mt-4">
                {{ $t('components.bonusRules.exclusions') }}
              </div><p class="text-caption text-medium-emphasis">
                {{ $t('components.bonusRules.exclusionsHint') }}
              </p>
            </VCol>
            <VCol cols="12">
              <VSelect
                v-model="form.excluded_employee_ids"
                :items="employees"
                item-title="name"
                item-value="id"
                multiple
                chips
                closable-chips
                :label="$t('components.bonusRules.excludedEmployees')"
              />
            </VCol>
            <VCol cols="12">
              <VSelect
                v-model="form.excluded_office_ids"
                :items="offices"
                item-title="name"
                item-value="id"
                multiple
                chips
                closable-chips
                :label="$t('components.bonusRules.excludedOffices')"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
      <VDivider />
      <VCardActions class="pa-4 justify-end">
        <VBtn
          variant="outlined"
          :disabled="isSaving"
          @click="dialog = false"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn><VBtn
          color="primary"
          :loading="isSaving"
          :disabled="!form"
          @click="save"
        >
          {{ $t('components.bonusRules.save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
