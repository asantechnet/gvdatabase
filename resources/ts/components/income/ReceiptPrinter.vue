<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import type { ReceiptPayload } from '@/api/receipts'
import ReceiptTemplate, { type ReceiptPaperSize } from '@/pages/templates/receipt_template.vue'

// Shared preview/print dialog for A5 receipt-shaped documents. Incomes and
// loans both render the same template; callers pass their own loader so the
// income and loan API contracts stay separate.
const props = withDefaults(defineProps<{
  modelValue: boolean
  recordId: number | null
  loader: (id: number) => Promise<ReceiptPayload>
  document?: 'receipt' | 'loan'
}>(), {
  document: 'receipt',
})

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

const toast = useToast()
const receipt = ref<ReceiptPayload | null>(null)
const loading = ref(false)
const printing = ref(false)
const templateRef = ref<InstanceType<typeof ReceiptTemplate> | null>(null)

const paperSize = ref<ReceiptPaperSize>('A5')
const copies = ref(1)
let requestGeneration = 0

const isLoan = computed(() => props.document === 'loan' || receipt.value?.document === 'loan')
// A payment request prints as an income receipt when money comes in, and as a
// payment voucher when money goes out (expense or salary).
const isVoucher = computed(() => isLoan.value && !!receipt.value && receipt.value.loan?.transaction_type !== 'income')
const documentTitle = computed(() => isVoucher.value
  ? translate('documents.paymentVoucher.title')
  : translate(isLoan.value ? 'documents.loan.title' : 'documents.receipt.title'))
const printLabel = computed(() => isVoucher.value
  ? translate('documents.paymentVoucher.print')
  : translate(isLoan.value ? 'documents.loan.print' : 'documents.receipt.printReceipt'))
const documentIcon = computed(() => isVoucher.value ? 'tabler-file-invoice' : isLoan.value ? 'tabler-clock-dollar' : 'tabler-receipt')

const paperSizes = computed<Array<{ title: string; value: ReceiptPaperSize }>>(() => [
  { title: translate('documents.receipt.paperA3Landscape'), value: 'A3 landscape' },
  { title: translate('documents.receipt.paperA4Portrait'), value: 'A4' },
  { title: translate('documents.receipt.paperA4Landscape'), value: 'A4 landscape' },
  { title: translate('documents.receipt.paperA4TwoA5'), value: 'A4 2x A5' },
  { title: translate('documents.receipt.paperA5Two'), value: 'A5 2x' },
  { title: translate('documents.receipt.paperA5Portrait'), value: 'A5' },
  { title: translate('documents.receipt.paperLetterPortrait'), value: 'letter' },
])

const previewScale = computed(() => (paperSize.value === 'A5' || paperSize.value === 'A5 2x' ? 0.85 : paperSize.value === 'A4 2x A5' ? 0.9 : 1))

watch(() => [props.modelValue, props.recordId] as const, async ([open, recordId]) => {
  const generation = ++requestGeneration
  receipt.value = null
  loading.value = false
  if (!open || !recordId)
    return

  loading.value = true
  try {
    const result = await props.loader(Number(recordId))
    if (generation === requestGeneration)
      receipt.value = result
  }
  catch (error: any) {
    if (generation === requestGeneration)
      toast.error(error?.response?.data?.message || translate('documents.receipt.loadFailed'))
  }
  finally {
    if (generation === requestGeneration)
      loading.value = false
  }
}, { immediate: true })

const print = async () => {
  if (!templateRef.value || printing.value || loading.value)
    return
  printing.value = true
  try {
    await templateRef.value.print()
  }
  catch (error) {
    const key = error instanceof Error && error.message.startsWith('documents.receipt.') ? error.message : 'documents.receipt.printFailed'
    toast.error(translate(key))
  }
  finally {
    printing.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    width="92vw"
    max-width="1200"
    content-class="receipt-preview-modal"
    :persistent="printing"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard
      flat
      class="receipt-preview-dialog"
    >
      <VCardTitle class="receipt-preview-dialog__header">
        <div class="d-flex align-center ga-3">
          <VAvatar
            color="primary"
            variant="tonal"
            size="38"
          >
            <VIcon :icon="documentIcon" />
          </VAvatar>
          <div>
            <div class="text-h6 font-weight-bold">
              {{ documentTitle }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ receipt?.serial_number || '—' }}
            </div>
          </div>
        </div>
        <div class="d-flex flex-wrap align-center justify-end ga-3">
          <VSelect
            v-model="paperSize"
            :items="paperSizes"
            :label="$t('documents.receipt.paperSize')"
            density="compact"
            hide-details
            :disabled="printing"
            style="min-width: 200px;"
          />
          <VTextField
            v-model.number="copies"
            type="number"
            min="1"
            max="4"
            :label="$t('documents.receipt.copies')"
            density="compact"
            hide-details
            :disabled="printing"
            style="max-width: 110px;"
          />
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="printing"
            @click="emit('update:modelValue', false)"
          >
            {{ $t('common.actions.close') }}
          </VBtn>
          <VBtn
            color="primary"
            prepend-icon="tabler-printer"
            :disabled="!receipt || loading || printing"
            :loading="printing"
            @click="print"
          >
            {{ printLabel }}
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="receipt-preview-dialog__body">
        <VProgressCircular
          v-if="loading"
          indeterminate
          color="primary"
        />
        <div
          v-else-if="receipt"
          class="receipt-preview-dialog__paper"
          :style="{ transform: `scale(${previewScale})` }"
        >
          <ReceiptTemplate
            ref="templateRef"
            :receipt="receipt"
            :copies="Number(copies) || 1"
            :paper-size="paperSize"
          />
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.receipt-preview-dialog {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  max-block-size: 92vh;
  background: #edf2f7;
  overflow: hidden;
}

.receipt-preview-dialog__header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.875rem 1.25rem;
  background: rgb(var(--v-theme-surface));
}

.receipt-preview-dialog__body {
  display: grid;
  place-items: start center;
  padding: 1.5rem;
  overflow: auto;
}

.receipt-preview-dialog__paper {
  width: 100%;
  transform-origin: top center;
}
</style>
