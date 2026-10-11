<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { createIncome, getApplicantContractPayments, type Income } from '@/api/incomes'
import { apiErrorMessage } from '@/utils/apiErrors'
import { paymentEntryLimit, servicePaymentIssue, submitServicePayments, type ServicePaymentEntry } from '@/utils/incomePaymentBalance'
import SearchableApplicantSelect from './SearchableApplicantSelect.vue'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; created: [income: Income] }>()
const { t } = useI18n()
const applicantId = ref<number | null>(null)
const entries = ref<ServicePaymentEntry[]>([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const date = ref(new Date().toISOString().slice(0, 10))
const description = ref('')
let generation = 0
const hasResults = computed(() => entries.value.some(entry => entry.state === 'saved' || entry.state === 'uncertain'))
const pending = computed(() => entries.value.filter(entry => entry.selected && entry.state !== 'saved' && entry.state !== 'uncertain'))
const canSave = computed(() => !saving.value && !loading.value && pending.value.length > 0
  && !pending.value.some(entry => servicePaymentIssue(entry)) && /^\d{4}-\d{2}-\d{2}$/.test(date.value)
  && date.value <= new Date().toISOString().slice(0, 10))

watch(() => props.modelValue, open => {
  ++generation
  loading.value = false
  if (open) {
    applicantId.value = null
    entries.value = []
    error.value = ''
    description.value = ''
    date.value = new Date().toISOString().slice(0, 10)
  }
})

watch(applicantId, async id => {
  const request = ++generation
  entries.value = []
  error.value = ''
  loading.value = !!id
  if (!id)
    return
  try {
    const lines = await getApplicantContractPayments(id)
    if (request === generation && props.modelValue) {
      entries.value = lines.map(line => ({
        line, selected: false, amount: line.remaining_total, rate: line.currency_rate, state: 'pending',
      }))
    }
  }
  catch (cause) {
    if (request === generation)
      error.value = apiErrorMessage(cause)
  }
  finally {
    if (request === generation)
      loading.value = false
  }
})

function issueMessage(entry: ServicePaymentEntry) {
  if (entry.state === 'uncertain')
    return t('finance.servicePayments.unconfirmed')
  if (entry.error)
    return apiErrorMessage(entry.error)
  const issue = servicePaymentIssue(entry)

  return issue ? t(`finance.servicePayments.errors.${issue}`) : ''
}

async function save() {
  if (!canSave.value)
    return
  saving.value = true
  try {
    await submitServicePayments(entries.value, entry => createIncome({
      applicant_id: applicantId.value,
      applicant_contract_id: entry.line.id,
      currency_id: entry.line.currency!.id,
      type: 'Service', quantity: 1, amount: Number(entry.amount), rate: Number(entry.rate),
      date: date.value,
      description: description.value.trim() || [entry.line.service_label, entry.line.contract_number].filter(Boolean).join(' / '),
    }), income => emit('created', income))
  }
  finally {
    saving.value = false
  }
}

onBeforeUnmount(() => { ++generation })
</script>

<template>
  <VDialog :model-value="modelValue" max-width="840" :persistent="saving" @update:model-value="emit('update:modelValue', $event)">
    <VCard :title="t('finance.servicePayments.title')">
      <VCardText>
        <VAlert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert>
        <SearchableApplicantSelect v-model="applicantId" :active="modelValue" :disabled="saving || hasResults" />
        <VRow class="mt-2">
          <VCol cols="12" sm="5">
            <VTextField v-model="date" type="date" :max="new Date().toISOString().slice(0, 10)" :label="t('common.fields.date')" :disabled="saving || hasResults" />
          </VCol>
          <VCol cols="12" sm="7">
            <VTextField v-model="description" :label="t('common.messages.descriptionPaymentNoteOptional')" :disabled="saving || hasResults" />
          </VCol>
        </VRow>
        <VProgressLinear v-if="loading" indeterminate class="mt-4" />
        <div v-if="applicantId && !loading && !entries.length" class="text-medium-emphasis py-4">{{ t('finance.servicePayments.empty') }}</div>
        <div v-for="entry in entries" :key="entry.line.id" class="service-payment-row">
          <div class="service-payment-row__identity">
            <VCheckbox v-model="entry.selected" hide-details :disabled="saving || entry.state === 'saved' || entry.state === 'uncertain' || entry.line.is_cancelled || entry.line.remaining_total <= 0 || !entry.line.currency" :label="[entry.line.service_label, entry.line.contract_number].filter(Boolean).join(' / ')" />
            <span class="text-caption text-medium-emphasis">{{ t('finance.paymentOverrides.balance') }}: {{ entry.line.remaining_total }} {{ entry.line.currency?.code }}</span>
          </div>
          <VChip v-if="entry.state === 'saved'" color="success" variant="tonal">{{ entry.receipt?.receipt_number }}</VChip>
          <template v-else-if="entry.selected">
            <VTextField v-model="entry.amount" type="number" min="0" :max="paymentEntryLimit(entry.line, Number(entry.rate))" :step="10 ** -(entry.line.currency?.decimalPlaces ?? 2)" :label="t('common.fields.amount')" :suffix="entry.line.currency?.code" :disabled="saving || entry.state === 'uncertain'" />
            <VTextField v-model="entry.rate" type="number" min="0.00000001" step="0.00000001" :label="t('common.messages.exchangeRate')" :disabled="saving || entry.state === 'uncertain'" />
          </template>
          <VAlert v-if="entry.selected && issueMessage(entry)" type="warning" variant="tonal" density="compact" class="service-payment-row__error">
            {{ issueMessage(entry) }}
          </VAlert>
        </div>
      </VCardText>
      <VCardActions class="justify-end flex-wrap">
        <VBtn :disabled="saving" @click="emit('update:modelValue', false)">{{ t('common.actions.close') }}</VBtn>
        <VBtn color="primary" :loading="saving" :disabled="!canSave" @click="save">{{ t('common.actions.save') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.service-payment-row {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 1fr);
  align-items: start;
  gap: 0.75rem;
  padding-block: 1rem;
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.service-payment-row__identity {
  min-inline-size: 0;
  overflow-wrap: anywhere;
}

.service-payment-row__identity :deep(.v-label) {
  white-space: normal;
}

.service-payment-row__error {
  grid-column: 1 / -1;
}

@media (max-width: 600px) {
  .service-payment-row { grid-template-columns: minmax(0, 1fr); }
}
</style>
