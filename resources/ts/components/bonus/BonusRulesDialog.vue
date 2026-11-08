<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import AutomationApi, { type BonusRuleSet, type BonusRules } from '@/api/automation'
import EmployeeApi, { type Employee } from '@/api/employee'
import OfficeApi, { type Office } from '@/api/office'
import { apiErrorMessage, apiFieldErrors } from '@/utils/apiErrors'
import { useI18n } from 'vue-i18n'
import ErpField from '@/components/erp/ErpField.vue'

const props = defineProps<{ modelValue: boolean }>()
const { t } = useI18n()
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void }>()
const dialog = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
const ruleSet = ref<BonusRuleSet | null>(null)
const defaultRules = (): BonusRules => ({ minimum_completed_tasks: 1, amount_per_completed_task: 0, minimum_cases_won: 1, amount_per_case_won: 0, task_quality_weighting_enabled: false, maximum_monthly_bonus: 0, excluded_employee_ids: [], excluded_office_ids: [] })
const form = ref<BonusRules>(defaultRules())
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
  form.value = defaultRules()
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
    class="bonus-rules-overlay"
  >
    <VCard class="bonus-rules-dialog">
      <VCardTitle class="bonus-rules-dialog__header px-5 py-4 d-flex justify-space-between align-center">
        <div>
          <div class="text-h5 font-weight-bold">
            {{ $t('components.bonusRules.title') }}
          </div><div v-if="false" class="text-caption text-medium-emphasis">
            {{ $t('components.bonusRules.version', { version: ruleSet?.version ?? '—' }) }}
          </div>
        </div>
        <VBtn
          icon="tabler-x"
          variant="tonal"
          color="error"
          :disabled="isSaving"
          @click="dialog = false"
        />
      </VCardTitle>
      <VCardText class="bonus-rules-dialog__body pa-5">
        <VAlert
          v-if="errors.form"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ errors.form }}
        </VAlert>
        <VForm @submit.prevent="save">
          <VRow class="bonus-rules-fields">
            <VCol
              cols="12"
              md="4"
            >
              <ErpField
                v-model.number="form.minimum_completed_tasks"
                type="number"
                min="1"
                :label="$t('components.bonusRules.minimumTasks')"
                :error-messages="errors.minimum_completed_tasks"
              />
            </VCol>
            <VCol cols="12" md="4">
              <ErpField
                v-model.number="form.minimum_cases_won"
                type="number"
                min="1"
                :label="$t('components.bonusRules.minimumCases')"
                :error-messages="errors.minimum_cases_won"
              />
            </VCol>
            <VCol cols="12" md="4">
              <ErpField
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
              md="6"
            >
              <ErpField
                v-model.number="form.amount_per_completed_task"
                type="number"
                min="0"
                :label="$t('components.bonusRules.amountPerTask')"
                :error-messages="errors.amount_per_completed_task"
              />
            </VCol>
            <VCol cols="12" md="6">
              <ErpField
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
              <ErpField
                v-model="form.excluded_employee_ids"
                :items="employees"
                item-title="name"
                item-value="id"
                kind="select"
                multiple
                chips
                closable-chips
                :label="$t('components.bonusRules.excludedEmployees')"
              />
            </VCol>
            <VCol cols="12">
              <ErpField
                v-model="form.excluded_office_ids"
                :items="offices"
                item-title="name"
                item-value="id"
                kind="select"
                multiple
                chips
                closable-chips
                :label="$t('components.bonusRules.excludedOffices')"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
      <VCardActions class="bonus-rules-dialog__footer px-5 py-4 justify-end">
        <VBtn
          variant="flat"
          color="secondary"
          class="bonus-rules-dialog__cancel"
          :disabled="isSaving"
          @click="dialog = false"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn><VBtn
          color="primary"
          class="bonus-rules-dialog__save"
          @click="save"
        >
          {{ $t('components.bonusRules.save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.bonus-rules-dialog { overflow: hidden; }
.bonus-rules-dialog__header, .bonus-rules-dialog__footer { background: color-mix(in srgb, rgb(var(--v-theme-surface)) 93%, rgb(var(--v-theme-on-surface)) 7%); }
.bonus-rules-dialog__header { border-block-end: 1px solid rgba(var(--v-border-color), .5); }
.bonus-rules-dialog__footer { border-block-start: 1px solid rgba(var(--v-border-color), .5); }
.bonus-rules-dialog__body { background: rgb(var(--v-theme-surface)); }
.bonus-rules-fields { row-gap: 10px; }
.bonus-rules-dialog__cancel { border: 1px solid rgba(var(--v-theme-secondary), .52) !important; background: rgba(var(--v-theme-secondary), .13) !important; color: rgb(var(--v-theme-secondary)) !important; }
.bonus-rules-dialog__cancel:hover { background: rgb(var(--v-theme-secondary)) !important; color: rgb(var(--v-theme-on-secondary)) !important; }
.bonus-rules-dialog__save { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
:global(.bonus-rules-overlay .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .3) !important; backdrop-filter: blur(7px); opacity: 1 !important; }
</style>
