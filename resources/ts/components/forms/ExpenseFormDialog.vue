<script setup lang="ts">
import { ref, watch } from 'vue'
import type { ExpenseFormData } from '@/api/expenses'

const props = defineProps<{
  modelValue: boolean
  initialExpense: ExpenseFormData | null
  currencyOptions: Array<{ title: string; value: number; rate: number }>
  companyOptions: Array<{ title: string; value: number }>
  projectOptions: Array<{ title: string; value: number }>
  mode: 'create' | 'edit'
}>()

const emit = defineEmits<{ 'update:modelValue': [value: boolean]; submit: [value: ExpenseFormData] }>()
const form = ref<Partial<ExpenseFormData>>({})
const formError = ref('')
const errors = ref<Record<string, string>>({})

watch(() => props.modelValue, open => {
  if (!open)
    return
  form.value = { ...props.initialExpense }
  errors.value = {}
  formError.value = ''
})

function calculateTotal() {
  form.value.base_amount = Number(form.value.amount || 0) * Number(form.value.quantity || 0) / Number(form.value.rate || 1)
}

function updateCurrency(currencyId: number) {
  const selected = props.currencyOptions.find(currency => currency.value === currencyId)
  if (selected)
    form.value.rate = selected.rate
  calculateTotal()
}

function validate() {
  const value = form.value
  const next: Record<string, string> = {}
  if (!value.type?.trim())
    next.type = translate('pages.finance.expenses.expenses.selectAnExpenseType')
  if (!Number.isInteger(Number(value.currency_id)) || Number(value.currency_id) < 1)
    next.currency_id = translate('common.messages.selectACurrency')
  if (!value.description?.trim())
    next.description = translate('pages.finance.expenses.expenses.enterADescription')
  if (!Number.isFinite(Number(value.amount)) || Number(value.amount) < 0)
    next.amount = translate('pages.finance.expenses.expenses.enterAnAmountOfZeroOrMore')
  if (!Number.isInteger(Number(value.quantity)) || Number(value.quantity) < 1)
    next.quantity = translate('pages.finance.expenses.expenses.enterAWholeQuantityOfAtLeast1')
  if (!Number.isFinite(Number(value.rate)) || Number(value.rate) <= 0)
    next.rate = translate('common.messages.exchangeRate')
  if (!value.date || Number.isNaN(Date.parse(value.date)))
    next.date = translate('pages.finance.expenses.expenses.chooseAValidExpenseDate')
  errors.value = next
  formError.value = Object.keys(next).length ? translate('pages.finance.expenses.expenses.pleaseCorrectTheHighlightedFields') : ''

  return !Object.keys(next).length
}

function submit() {
  if (validate())
    emit('submit', { ...form.value } as ExpenseFormData)
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="900px"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="expense-dialog app-form-dialog">
      <VCardTitle class="pa-6 pb-2">
        <div class="d-flex align-center gap-3">
          <VAvatar
            color="error"
            variant="tonal"
            :icon="mode === 'create' ? 'tabler-receipt-2' : 'tabler-pencil'"
          /><div>
            <div class="text-h5 font-weight-bold">
              {{ mode === 'create' ? $t('pages.finance.expenses.expenses.recordExpense2') : $t('pages.finance.expenses.expenses.editExpense') }}
            </div><div class="text-caption text-medium-emphasis">
              {{ mode === 'create' ? $t('pages.finance.expenses.expenses.captureOutgoingCashWithItsLiveCurrencyRelationship') : $t('pages.finance.expenses.expenses.updateTheRecordedSpendAndItsCurrency') }}
            </div>
          </div>
        </div>
      </VCardTitle>
      <VForm
        class="app-form"
        @submit.prevent="submit"
      >
        <VCardText>
          <VAlert
            v-if="formError"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ formError }}
          </VAlert><VRow>
            <VCol cols="12">
              <p class="app-form__section">
                Expense details
              </p><p class="app-form__section-help">
                Enter the original amount and quantity. The exchange rate starts from the selected currency and can be adjusted for this expense.
              </p>
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="form.type"
                :items="['Utilities', 'Software/SaaS', 'Marketing', 'Office Supplies', 'Travel & Dining', 'Salaries']"
                :label="$t('common.messages.expenseType')"
                :error-messages="errors.type"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="form.currency_id"
                :items="currencyOptions"
                :label="$t('common.fields.currency')"
                :error-messages="errors.currency_id"
                required
                @update:model-value="updateCurrency"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="form.company_id"
                :items="companyOptions"
                label="Company (optional)"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="form.project_id"
                :items="projectOptions"
                label="Project (optional)"
                clearable
              />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="form.description"
                :label="$t('common.fields.description')"
                :error-messages="errors.description"
                :placeholder="$t('common.messages.whatWasThisExpenseFor')"
                rows="2"
                auto-grow
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <VTextField
                v-model.number="form.amount"
                :label="$t('common.fields.amount')"
                :error-messages="errors.amount"
                type="number"
                min="0"
                step="0.01"
                required
                @input="calculateTotal"
              />
            </VCol><VCol
              cols="12"
              md="4"
            >
              <VTextField
                v-model.number="form.quantity"
                :label="$t('common.messages.quantity')"
                :error-messages="errors.quantity"
                type="number"
                min="1"
                required
                @input="calculateTotal"
              />
            </VCol><VCol
              cols="12"
              md="4"
            >
              <VTextField
                v-model.number="form.rate"
                :label="$t('common.messages.exchangeRate')"
                :error-messages="errors.rate"
                type="number"
                min="0.00000001"
                step="any"
                required
                @input="calculateTotal"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                :model-value="form.base_amount"
                :label="$t('common.messages.baseCurrencyTotal')"
                type="number"
                readonly
              />
            </VCol><VCol
              cols="12"
              md="6"
            >
              <AppDateTimePicker
                v-model="form.date"
                :label="$t('common.messages.expenseDate')"
                :error-messages="errors.date"
                :config="{ dateFormat: 'Y-m-d' }"
                required
              />
            </VCol>
          </VRow>
        </VCardText><VCardActions class="pa-5">
          <VSpacer /><VBtn
            color="secondary"
            variant="tonal"
            @click="emit('update:modelValue', false)"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn><VBtn
            color="error"
            type="submit"
          >
            {{ mode === 'create' ? $t('pages.finance.expenses.expenses.saveExpense') : $t('common.messages.saveChanges4') }}
          </VBtn>
        </VCardActions>
      </VForm>
    </VCard>
  </VDialog>
</template>

<style scoped>
/* Keep field values vertically centered while leaving their text left-aligned. */
.expense-dialog :deep(.v-field__field),
.expense-dialog :deep(.v-field__input),
.expense-dialog :deep(.v-select__selection) {
  align-items: center;
}
</style>
