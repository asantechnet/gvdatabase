<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import SearchableApplicantSelect from './SearchableApplicantSelect.vue'
import ErpField from '@/components/erp/ErpField.vue'
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
const confirmOpen = ref(false)
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

// Overrides are audited and change the collectible balance, so the save is
// gated behind an explicit confirmation rather than firing on the first click.
function requestSave() {
  if (!canSave.value || !line.value)
    return
  confirmOpen.value = true
}

async function confirmSave() {
  confirmOpen.value = false
  await save()
}

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
  <VDialog :model-value="modelValue" max-width="600" :persistent="saving" class="income-support-overlay" content-class="income-support-dialog" @update:model-value="emit('update:modelValue', $event)">
    <VCard class="income-support-dialog__card">
      <VCardTitle class="income-support-dialog__header">
        <div class="d-flex align-center ga-3">
          <VAvatar color="primary" variant="tonal" size="38"><VIcon icon="tabler-settings" /></VAvatar>
          <span class="text-h5 font-weight-bold">{{ t('finance.paymentOverrides.title') }}</span>
        </div>
        <VBtn icon size="small" class="income-support-dialog__close" :disabled="saving" :aria-label="t('common.actions.close')" @click="emit('update:modelValue', false)"><VIcon icon="tabler-x" /></VBtn>
      </VCardTitle>
      <VCardText class="pt-5">
        <VAlert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert>
        <VAlert type="warning" variant="tonal" class="mb-4">{{ t('finance.paymentOverrides.warning') }}</VAlert>
        <SearchableApplicantSelect v-model="applicantId" :active="modelValue" :disabled="saving" class="mb-4" />
        <ErpField v-model="lineId" kind="select" :items="lineOptions" :label="t('finance.paymentOverrides.service')" :placeholder="t('finance.paymentOverrides.service')" :loading="loadingLines" :disabled="!applicantId || saving || loadingLines" />
        <template v-if="line">
          <dl class="payment-override-totals mt-4">
            <dt>{{ t('finance.paymentOverrides.recorded') }}</dt><dd>{{ line.recorded_amount_paid }} {{ line.currency?.code }}</dd>
            <dt>{{ t('finance.paymentOverrides.balance') }}</dt><dd>{{ line.remaining_total }} {{ line.currency?.code }}</dd>
          </dl>
          <VSwitch v-model="amountOverride" :label="t('finance.paymentOverrides.amountOverride')" :disabled="saving" />
          <ErpField v-if="amountOverride" v-model="amount" type="number" min="0" max="9999999999999" :step="10 ** -(line.currency?.decimalPlaces ?? 2)" :suffix="line.currency?.code" :label="t('finance.paymentOverrides.amount')" :placeholder="t('finance.paymentOverrides.amount')" :disabled="saving" />
          <VSwitch v-model="statusOverride" :label="t('finance.paymentOverrides.statusOverride')" :disabled="saving" />
          <ErpField v-if="statusOverride" v-model="status" kind="select" :items="statusOptions" :label="t('finance.paymentOverrides.status')" :placeholder="t('finance.paymentOverrides.status')" :disabled="saving" />
        </template>
      </VCardText>
      <VCardActions class="income-support-dialog__footer justify-end flex-wrap">
        <VBtn class="income-support-dialog__cancel" :disabled="saving" @click="emit('update:modelValue', false)">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" variant="flat" :loading="saving" :disabled="!canSave" @click="requestSave">{{ t('common.actions.save') }}</VBtn>
      </VCardActions>
      <VDialog v-model="confirmOpen" max-width="420" :persistent="saving">
        <VCard :title="t('finance.paymentOverrides.confirmTitle')">
          <VCardText>{{ t('finance.paymentOverrides.confirmWarning') }}</VCardText>
          <VCardActions>
            <VSpacer />
            <VBtn variant="tonal" :disabled="saving" @click="confirmOpen = false">{{ t('common.actions.cancel') }}</VBtn>
            <VBtn color="primary" :loading="saving" @click="confirmSave">{{ t('finance.paymentOverrides.confirmSave') }}</VBtn>
          </VCardActions>
        </VCard>
      </VDialog>
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

.income-support-dialog__card { overflow: hidden; }
.income-support-dialog__header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.25rem; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%); }
.income-support-dialog__close { background: rgb(var(--v-theme-error) / .14) !important; color: rgb(var(--v-theme-error)) !important; }
.income-support-dialog__footer { padding: 1rem 1.25rem; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 96%, rgb(var(--v-theme-on-surface)) 4%); }
.income-support-dialog__footer :deep(.v-btn--variant-flat) { color: rgb(var(--v-theme-on-primary)) !important; }
.income-support-dialog__cancel { background: rgb(var(--v-theme-secondary) / .13) !important; color: rgb(var(--v-theme-secondary)) !important; }
.income-support-dialog__cancel:hover { background: rgb(var(--v-theme-secondary)) !important; color: rgb(var(--v-theme-on-secondary)) !important; }
:global(.income-support-overlay .v-overlay__scrim), :global(.v-overlay:has(.income-support-dialog) .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .3) !important; backdrop-filter: blur(7px); opacity: 1 !important; }
:global(.income-support-dialog .income-support-dialog__header) { background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%) !important; border-block-end: 1px solid rgba(var(--v-theme-on-surface), .07); }
:global(.income-support-dialog .income-support-dialog__footer) { background: color-mix(in srgb, rgb(var(--v-theme-surface)) 96%, rgb(var(--v-theme-on-surface)) 4%) !important; border-block-start: 1px solid rgba(var(--v-theme-on-surface), .07); }
:global(.income-support-dialog .income-support-dialog__close) { min-inline-size: 2.25rem !important; block-size: 2.25rem !important; border-radius: 999px !important; background: rgba(var(--v-theme-error), .14) !important; color: rgb(var(--v-theme-error)) !important; }
:global(.income-support-dialog .income-support-dialog__close .v-icon) { color: inherit !important; }
:global(.income-support-dialog .income-support-dialog__close .v-btn__overlay) { opacity: 0 !important; }
:global(.income-support-dialog .income-support-dialog__cancel) { border: 1px solid rgba(var(--v-theme-secondary), .35) !important; background: rgba(var(--v-theme-secondary), .13) !important; color: rgb(var(--v-theme-secondary)) !important; box-shadow: none !important; }
:global(.income-support-dialog .income-support-dialog__cancel:hover) { background: rgb(var(--v-theme-secondary)) !important; color: rgb(var(--v-theme-on-secondary)) !important; }
:global(.income-support-dialog .income-support-dialog__footer .v-btn--variant-flat) { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
</style>
