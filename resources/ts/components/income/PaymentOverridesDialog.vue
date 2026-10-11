<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import SearchableApplicantSelect from './SearchableApplicantSelect.vue'
import { getApplicantContractPayments, setContractPaymentOverride, type ApplicantContractPaymentOption, type ContractPaymentOverrideData } from '@/api/incomes'
import { apiErrorMessage } from '@/utils/apiErrors'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const { t } = useI18n()
const toast = useToast()
const applicantId = ref<number | null>(null)
const lines = ref<ApplicantContractPaymentOption[]>([])
const lineId = ref<number | null>(null)
const loadingLines = ref(false)
const saving = ref(false)
const error = ref('')
const amountOverride = ref(false)
const statusOverride = ref(false)
const amount = ref<number | string>(0)
const status = ref<NonNullable<ContractPaymentOverrideData['payment_status']>>('unpaid')
const line = computed(() => lines.value.find(item => item.id === lineId.value))
const lineOptions = computed(() => lines.value.map(item => ({ title: [item.service_label, item.contract_number].filter(Boolean).join(' / '), value: item.id })))
const statusOptions = computed(() => ['paid', 'unpaid', 'partially_paid'].map(value => ({ title: t(`finance.paymentStatus.${value}`), value })))
const canSave = computed(() => !!line.value && !saving.value && !loadingLines.value
  && (!amountOverride.value || (amount.value != null && amount.value !== '' && Number.isFinite(Number(amount.value)) && Number(amount.value) >= 0 && Number(amount.value) <= 9999999999999)))
let lineRequest = 0

watch(() => props.modelValue, open => {
  if (open) {
    error.value = ''
    applicantId.value = null
    lineId.value = null
    lines.value = []
  }
  else {
    ++lineRequest
    loadingLines.value = false
  }
})

watch(applicantId, async id => {
  const request = ++lineRequest
  lineId.value = null
  lines.value = []
  error.value = ''
  loadingLines.value = !!id
  if (!id)
    return
  try {
    const result = await getApplicantContractPayments(id)
    if (request === lineRequest && props.modelValue)
      lines.value = result
  }
  catch (cause) {
    if (request === lineRequest)
      error.value = apiErrorMessage(cause)
  }
  finally {
    if (request === lineRequest)
      loadingLines.value = false
  }
})

watch(line, selected => {
  amountOverride.value = selected?.amount_paid_overridden ?? false
  statusOverride.value = selected?.payment_status_overridden ?? false
  amount.value = selected?.amount_paid ?? 0
  status.value = (selected?.payment_status ?? 'unpaid') as typeof status.value
})

async function save() {
  if (!canSave.value || !line.value)
    return
  saving.value = true
  error.value = ''
  try {
    await setContractPaymentOverride(line.value.id, {
      amount_paid: amountOverride.value ? Number(amount.value) : null,
      payment_status: statusOverride.value ? status.value : null,
    })
    toast.success(t('finance.paymentOverrides.saved'))
    emit('update:modelValue', false)
  }
  catch (cause) {
    error.value = apiErrorMessage(cause)
  }
  finally {
    saving.value = false
  }
}

onBeforeUnmount(() => {
  ++lineRequest
})
</script>

<template>
  <VDialog :model-value="modelValue" max-width="600" :persistent="saving" @update:model-value="emit('update:modelValue', $event)">
    <VCard :title="t('finance.paymentOverrides.title')">
      <VCardText>
        <VAlert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert>
        <SearchableApplicantSelect v-model="applicantId" :active="modelValue" :disabled="saving" class="mb-4" />
        <VSelect v-model="lineId" :items="lineOptions" :label="t('finance.paymentOverrides.service')" :loading="loadingLines" :disabled="!applicantId || saving || loadingLines" />
        <template v-if="line">
          <dl class="payment-override-totals mt-4">
            <dt>{{ t('finance.paymentOverrides.recorded') }}</dt><dd>{{ line.recorded_amount_paid }} {{ line.currency?.code }}</dd>
            <dt>{{ t('finance.paymentOverrides.balance') }}</dt><dd>{{ line.remaining_total }} {{ line.currency?.code }}</dd>
          </dl>
          <VSwitch v-model="amountOverride" :label="t('finance.paymentOverrides.amountOverride')" :disabled="saving" />
          <VTextField v-if="amountOverride" v-model="amount" type="number" min="0" max="9999999999999" :step="10 ** -(line.currency?.decimalPlaces ?? 2)" :suffix="line.currency?.code" :label="t('finance.paymentOverrides.amount')" :disabled="saving" />
          <VSwitch v-model="statusOverride" :label="t('finance.paymentOverrides.statusOverride')" :disabled="saving" />
          <VSelect v-if="statusOverride" v-model="status" :items="statusOptions" :label="t('finance.paymentOverrides.status')" :disabled="saving" />
        </template>
      </VCardText>
      <VCardActions class="justify-end flex-wrap">
        <VBtn :disabled="saving" @click="emit('update:modelValue', false)">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :loading="saving" :disabled="!canSave" @click="save">{{ t('common.actions.save') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.payment-override-totals {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 0.5rem 1rem;
  overflow-wrap: anywhere;
}

.payment-override-totals dd {
  margin: 0;
  text-align: end;
}
</style>
