<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import type { ReceiptPayload } from '@/api/receipts'
import ReceiptTemplate, { type ReceiptPaperSize } from '@/pages/templates/receipt_template.vue'
import SlipPrintDialog from '@/components/documents/SlipPrintDialog.vue'

// Shared preview/print dialog for A5 receipt-shaped documents. Callers pass
// their own loader so each ledger keeps its own API contract.
const props = defineProps<{
  modelValue: boolean
  recordId: number | null
  loader: (id: number) => Promise<ReceiptPayload>
}>()

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

const { t } = useI18n()
const toast = useToast()
const receipt = ref<ReceiptPayload | null>(null)
const loading = ref(false)
const templateRef = ref<InstanceType<typeof ReceiptTemplate> | null>(null)

const paperSize = ref<ReceiptPaperSize>('A4 2x A5')
// Two receipts are always issued together: the customer copy and the office copy.
const copies = 2
let requestGeneration = 0

const paperSizes = computed<Array<{ title: string; value: ReceiptPaperSize }>>(() => [
  { title: t('documents.receipt.paperA4TwoA5'), value: 'A4 2x A5' },
  { title: t('documents.receipt.paperA5Two'), value: 'A5 2x' },
  { title: t('documents.receipt.paperA4Portrait'), value: 'A4' },
  { title: t('documents.receipt.paperA5Portrait'), value: 'A5' },
])

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
      toast.error(error?.response?.data?.message || t('documents.receipt.loadFailed'))
  }
  finally {
    if (generation === requestGeneration)
      loading.value = false
  }
}, { immediate: true })

const print = async () => {
  if (!templateRef.value)
    throw new Error('documents.receipt.printFailed')
  await templateRef.value.print()
}
</script>

<template>
  <SlipPrintDialog
    :model-value="modelValue"
    :title="t('documents.receipt.title')"
    :subtitle="receipt?.serial_number"
    icon="tabler-receipt"
    :paper-size="paperSize"
    :paper-sizes="paperSizes"
    :loading="loading"
    :ready="!!receipt"
    :print-action="print"
    :print-label="t('documents.receipt.printReceipt')"
    error-fallback="documents.receipt.printFailed"
    @update:model-value="emit('update:modelValue', $event)"
    @update:paper-size="paperSize = $event as ReceiptPaperSize"
  >
    <ReceiptTemplate
      v-if="receipt"
      ref="templateRef"
      :receipt="receipt"
      :copies="copies"
      :paper-size="paperSize"
    />
  </SlipPrintDialog>
</template>
