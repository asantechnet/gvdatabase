<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import AutomationApi, { type BonusRuleSet, type BonusRules } from '@/api/automation'
import EmployeeApi, { type Employee } from '@/api/employee'
import OfficeApi, { type Office } from '@/api/office'
import { apiErrorMessage, apiFieldErrors } from '@/utils/apiErrors'

const props = defineProps<{ modelValue: boolean }>()
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
    errors.value.form = apiErrorMessage(error, 'Unable to load bonus settings.')
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
      errors.value.form = apiErrorMessage(error, 'Unable to save bonus settings.')
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
            Automatic bonus
          </div><div class="text-caption text-medium-emphasis">
            Version {{ ruleSet?.version ?? '—' }}
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
            Each completed task earns the reward below. When the monthly limit is reached, no more is suggested for that month.
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
                label="Completed tasks needed"
                hint="A person must complete at least this many tasks before a bonus is suggested."
                persistent-hint
                :error-messages="errors.minimum_completed_tasks"
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
                label="Reward for each completed task"
                :error-messages="errors.amount_per_completed_task"
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model.number="form.maximum_monthly_bonus"
                type="number"
                min="0"
                label="Maximum bonus per person each month"
                :error-messages="errors.maximum_monthly_bonus"
              />
            </VCol>
            <VCol cols="12">
              <VDivider class="my-2" /><div class="text-subtitle-2 mt-4">
                Optional exclusions
              </div><p class="text-caption text-medium-emphasis">
                Choose people or offices that should never receive automatic bonus suggestions.
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
                label="Do not suggest for these employees"
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
                label="Do not suggest for these offices"
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
          Save bonus settings
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
