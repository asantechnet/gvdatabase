<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { useToast } from 'vue-toastification'
import ApplicantApi from '@/api/applicants'
import type { ExpenseFormData } from '@/api/expenses'

const props = defineProps<{
  modelValue: boolean
  initialExpense: ExpenseFormData | null
  currencyOptions: Array<{ title: string; value: number; rate: number }>
  typeOptions: Array<{ title: string; value: string }>
  mode: 'create' | 'edit'
  canCreateDirect: boolean
  canCreateDraft: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [value: boolean]; submit: [value: ExpenseFormData, mode: 'direct' | 'draft'] }>()
const form = ref<Partial<ExpenseFormData>>({})
const saveMode = ref<'direct' | 'draft'>('direct')
const formError = ref('')
const errors = ref<Record<string, string>>({})
const applicants = ref<Array<{ id: number; name: string }>>([])
const applicantSearch = ref('')
const loadingApplicants = ref(false)
const toast = useToast()
let applicantRequest = 0
onBeforeUnmount(() => { ++applicantRequest })

async function loadApplicants() {
  if (!props.modelValue)
    return
  const request = ++applicantRequest
  loadingApplicants.value = true
  try {
    const response = await ApplicantApi.list({ search: applicantSearch.value, per_page: 50 })
    if (request !== applicantRequest)
      return
    const selected = applicants.value.find(item => item.id === form.value.applicant_id)
    applicants.value = selected && !response.data.some(item => item.id === selected.id)
      ? [selected, ...response.data]
      : response.data
  }
  catch {
    if (request === applicantRequest)
      toast.error(translate('educational.messages.loadApplicantsFailed'))
  }
  finally {
    if (request === applicantRequest)
      loadingApplicants.value = false
  }
}

const searchApplicants = useDebounceFn(loadApplicants, 250)
watch(applicantSearch, () => {
  ++applicantRequest
  void searchApplicants()
})

watch(() => props.modelValue, open => {
  if (!open) {
    ++applicantRequest
    loadingApplicants.value = false
    return
  }
  form.value = { ...props.initialExpense }
  saveMode.value = props.canCreateDirect ? 'direct' : 'draft'
  errors.value = {}
  formError.value = ''
  applicantSearch.value = ''
  const selected = props.initialExpense && 'applicant' in props.initialExpense ? props.initialExpense.applicant : null
  applicants.value = selected ? [selected] : []
  void loadApplicants()
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
    emit('submit', { ...form.value } as ExpenseFormData, saveMode.value)
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
            color="primary"
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
            <VCol
              cols="12"
              md="4"
            >
              <VAutocomplete
                v-model="form.applicant_id"
                v-model:search="applicantSearch"
                :items="applicants"
                item-title="name"
                item-value="id"
                :label="$t('common.fields.applicant')"
                prepend-inner-icon="tabler-user"
                :loading="loadingApplicants"
                clearable
                no-filter
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <VSelect
                v-model="form.type"
                :items="typeOptions"
                :label="$t('common.messages.expenseType')"
                :error-messages="errors.type"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
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
              v-if="mode === 'create'"
              cols="12"
            >
              <div class="d-flex flex-wrap gap-4 mb-1">
                <VBtn
                  v-if="canCreateDirect"
                  :color="saveMode === 'direct' ? 'primary' : 'secondary'"
                  :variant="saveMode === 'direct' ? 'flat' : 'outlined'"
                  prepend-icon="tabler-device-floppy"
                  @click="saveMode = 'direct'"
                >
                  {{ $t('pages.finance.expenses.expenses.saveModeDirect') }}
                </VBtn>
                <VBtn
                  v-if="canCreateDraft"
                  :color="saveMode === 'draft' ? 'primary' : 'secondary'"
                  :variant="saveMode === 'draft' ? 'flat' : 'outlined'"
                  prepend-icon="tabler-send"
                  @click="saveMode = 'draft'"
                >
                  {{ $t('pages.finance.expenses.expenses.saveModeDraft') }}
                </VBtn>
              </div>
              <p class="text-caption text-medium-emphasis mb-0">
                {{ saveMode === 'draft' ? $t('pages.finance.expenses.expenses.saveModeDraftHint') : $t('pages.finance.expenses.expenses.saveModeDirectHint') }}
              </p>
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
            variant="text"
            @click="emit('update:modelValue', false)"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn><VBtn
            color="primary"
            variant="flat"
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
