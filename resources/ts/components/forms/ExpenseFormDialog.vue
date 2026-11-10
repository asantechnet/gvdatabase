<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { useToast } from 'vue-toastification'
import ApplicantApi from '@/api/applicants'
import type { ExpenseFormData } from '@/api/expenses'
import ErpField from '@/components/erp/ErpField.vue'

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

const dockDialogCalendar = (_selectedDates: Date[], _dateString: string, instance: any) => {
  window.setTimeout(() => {
    const calendar = instance?.calendarContainer as HTMLElement | undefined
    if (!calendar)
      return
    calendar.style.transform = 'translateY(0) scale(0.86)'
    calendar.style.transformOrigin = 'top left'
    calendar.style.zIndex = '3100'
  })
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
    class="expense-form-overlay"
    content-class="expense-form-dialog"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="expense-dialog app-form-dialog">
      <VCardTitle class="expense-dialog__header">
        <div class="d-flex align-center ga-3"><VAvatar color="primary" variant="tonal" size="38"><VIcon :icon="mode === 'create' ? 'tabler-receipt-2' : 'tabler-pencil'" /></VAvatar><span class="text-h5 font-weight-bold">{{ mode === 'create' ? $t('pages.finance.expenses.expenses.recordExpense2') : $t('pages.finance.expenses.expenses.editExpense') }}</span></div>
        <VBtn icon size="small" data-confirm-form-cancel class="expense-dialog__close" :aria-label="$t('common.actions.close')" @click="emit('update:modelValue', false)"><VIcon icon="tabler-x" /></VBtn>
      </VCardTitle>
      <VForm
        class="app-form"
        @submit.prevent="submit"
      >
        <VCardText class="pt-5">
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
              <ErpField
                v-model="form.applicant_id"
                v-model:search="applicantSearch"
                kind="autocomplete"
                :items="applicants"
                item-title="name"
                item-value="id"
                :label="$t('common.fields.applicant')"
                :placeholder="$t('common.fields.applicant')"
                :loading="loadingApplicants"
                clearable
                no-filter
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <ErpField
                v-model="form.type"
                kind="select"
                :items="typeOptions"
                :label="$t('common.messages.expenseType')"
                :placeholder="$t('common.messages.expenseType')"
                :error-messages="errors.type"
                required
              />
            </VCol>
            <VCol cols="12" md="4"><ErpField v-model="form.date" kind="date" :label="$t('common.messages.expenseDate')" :placeholder="$t('common.messages.expenseDate')" :error-messages="errors.date" required :config="{ dateFormat: 'Y-m-d', static: false, onOpen: dockDialogCalendar }" /></VCol>
            <VCol cols="12" md="4">
              <ErpField
                v-model="form.description"
                :label="$t('common.fields.description')"
                :error-messages="errors.description"
                :placeholder="$t('common.messages.whatWasThisExpenseFor')"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <ErpField
                v-model.number="form.quantity"
                :label="$t('common.messages.quantity')"
                :placeholder="$t('common.messages.quantity')"
                :error-messages="errors.quantity"
                type="number"
                min="1"
                required
                @update:model-value="calculateTotal"
              />
            </VCol><VCol
              cols="12"
              md="4"
            >
              <ErpField
                v-model.number="form.amount"
                :label="$t('common.fields.amount')"
                :placeholder="$t('common.fields.amount')"
                :error-messages="errors.amount"
                type="number"
                min="0"
                step="0.01"
                required
                @update:model-value="calculateTotal"
              />
            </VCol><VCol
              cols="12"
              md="4"
            >
              <ErpField
                v-model.number="form.rate"
                :label="$t('common.messages.exchangeRate')"
                :placeholder="$t('common.messages.exchangeRate')"
                :error-messages="errors.rate"
                type="number"
                min="0.00000001"
                step="any"
                required
                @update:model-value="calculateTotal"
              />
            </VCol><VCol cols="12" md="4"><ErpField
                v-model="form.currency_id"
                kind="select"
                :items="currencyOptions"
                :label="$t('common.fields.currency')"
                :placeholder="$t('common.fields.currency')"
                :error-messages="errors.currency_id"
                required
                @update:model-value="updateCurrency"
              /></VCol>
            <VCol cols="12" md="4">
              <ErpField
                :model-value="form.base_amount"
                :label="$t('common.messages.baseCurrencyTotal')"
                :placeholder="$t('common.messages.baseCurrencyTotal')"
                type="number"
                readonly
              />
            </VCol>
            <VCol v-if="mode === 'create'" cols="12"><div class="expense-save-mode"><VSwitch v-if="canCreateDirect && canCreateDraft" v-model="saveMode" true-value="direct" false-value="draft" color="primary" hide-details inset :label="saveMode === 'direct' ? $t('pages.finance.expenses.expenses.saveModeDirect') : $t('pages.finance.expenses.expenses.saveModeDraft')" /><p class="text-caption text-medium-emphasis mb-0">{{ saveMode === 'draft' ? $t('pages.finance.expenses.expenses.saveModeDraftHint') : $t('pages.finance.expenses.expenses.saveModeDirectHint') }}</p></div></VCol>
          </VRow>
        </VCardText><VCardActions class="expense-dialog__footer">
          <VSpacer /><VBtn
            data-confirm-form-cancel
            class="expense-dialog__cancel"
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
.expense-dialog { overflow: hidden; }
.expense-dialog__header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.5rem; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%); }
.expense-dialog__close { background: rgb(var(--v-theme-error) / .14) !important; color: rgb(var(--v-theme-error)) !important; }
.expense-dialog__footer { padding: 1rem 1.5rem; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 96%, rgb(var(--v-theme-on-surface)) 4%); }
.expense-dialog__footer :deep(.v-btn--variant-flat) { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
.expense-dialog__cancel { background: rgb(var(--v-theme-secondary) / .13) !important; color: rgb(var(--v-theme-secondary)) !important; }
.expense-dialog__cancel:hover { background: rgb(var(--v-theme-secondary)) !important; color: rgb(var(--v-theme-on-secondary)) !important; }
.expense-save-mode { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem 1.5rem; padding: .75rem 1rem; border-radius: 10px; background: rgb(var(--v-theme-primary) / .055); }
:global(.expense-form-overlay .v-overlay__scrim), :global(.v-overlay:has(.expense-form-dialog) .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .3) !important; backdrop-filter: blur(7px); opacity: 1 !important; }
:global(.expense-form-dialog .expense-dialog__header) { background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%) !important; border-block-end: 1px solid rgba(var(--v-theme-on-surface), .07); }
:global(.expense-form-dialog .expense-dialog__footer) { background: color-mix(in srgb, rgb(var(--v-theme-surface)) 96%, rgb(var(--v-theme-on-surface)) 4%) !important; border-block-start: 1px solid rgba(var(--v-theme-on-surface), .07); }
:global(.expense-form-dialog .expense-dialog__close) { min-inline-size: 2.25rem !important; block-size: 2.25rem !important; border-radius: 999px !important; background: rgba(var(--v-theme-error), .14) !important; color: rgb(var(--v-theme-error)) !important; }
:global(.expense-form-dialog .expense-dialog__close .v-icon) { color: inherit !important; }
:global(.expense-form-dialog .expense-dialog__close .v-btn__overlay) { opacity: 0 !important; }
:global(.expense-form-dialog .expense-dialog__cancel) { border: 1px solid rgba(var(--v-theme-secondary), .35) !important; background: rgba(var(--v-theme-secondary), .13) !important; color: rgb(var(--v-theme-secondary)) !important; box-shadow: none !important; }
:global(.expense-form-dialog .expense-dialog__cancel:hover) { background: rgb(var(--v-theme-secondary)) !important; color: rgb(var(--v-theme-on-secondary)) !important; }
:global(.expense-form-dialog .expense-dialog__footer .v-btn--variant-flat) { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
</style>
