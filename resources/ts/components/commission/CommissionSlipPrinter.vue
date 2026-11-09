<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import CommissionApi, { type CommissionSlipPayload } from '@/api/commission'
import CommissionSlipTemplate, { type CommissionSlipPaperSize } from '@/pages/templates/commission_slip_template.vue'
import SlipPrintDialog from '@/components/documents/SlipPrintDialog.vue'

const props = defineProps<{ modelValue: boolean; settlementId: number | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

const { t } = useI18n()
const toast = useToast()
const slip = ref<CommissionSlipPayload | null>(null)
const loading = ref(false)
const templateRef = ref<InstanceType<typeof CommissionSlipTemplate> | null>(null)

const paperSize = ref<CommissionSlipPaperSize>('A4 2x A5')
// Two slips are always issued together: the employee copy and the office copy.
const copies = 2
let requestGeneration = 0

const paperSizes = computed<Array<{ title: string; value: CommissionSlipPaperSize }>>(() => [
  { title: t('documents.commissionSlip.paperA4TwoA5'), value: 'A4 2x A5' },
  { title: t('documents.commissionSlip.paperA5Two'), value: 'A5 2x' },
  { title: t('documents.commissionSlip.paperA4'), value: 'A4' },
  { title: t('documents.commissionSlip.paperA5'), value: 'A5' },
])

watch(() => [props.modelValue, props.settlementId] as const, async ([open, settlementId]) => {
  const generation = ++requestGeneration
  slip.value = null
  loading.value = false
  if (!open || !settlementId)
    return

  loading.value = true
  try {
    const result = await CommissionApi.slip(Number(settlementId))
    if (generation === requestGeneration)
      slip.value = result
  }
  catch (error: any) {
    if (generation === requestGeneration)
      toast.error(error?.response?.data?.message || t('documents.commissionSlip.loadFailed'))
  }
  finally {
    if (generation === requestGeneration)
      loading.value = false
  }
}, { immediate: true })

const print = async () => {
  if (!templateRef.value)
    throw new Error('documents.commissionSlip.printFailed')
  await templateRef.value.print()
}
</script>

<template>
  <SlipPrintDialog
    :model-value="modelValue"
    :title="t('documents.commissionSlip.title')"
    :subtitle="slip?.reference"
    icon="tabler-receipt"
    :paper-size="paperSize"
    :paper-sizes="paperSizes"
    :loading="loading"
    :ready="!!slip"
    :print-action="print"
    :print-label="t('documents.commissionSlip.print')"
    error-fallback="documents.commissionSlip.printFailed"
    @update:model-value="emit('update:modelValue', $event)"
    @update:paper-size="paperSize = $event as CommissionSlipPaperSize"
  >
    <CommissionSlipTemplate
      v-if="slip"
      ref="templateRef"
      :slip="slip"
      :copies="copies"
      :paper-size="paperSize"
    />
  </SlipPrintDialog>
</template>
