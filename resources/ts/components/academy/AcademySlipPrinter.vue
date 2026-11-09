<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import AcademySlipApi, { type AcademySlipPayload } from '@/api/academySlip'
import AcademySlipTemplate, { type AcademySlipPaperSize } from '@/pages/templates/academy_slip_template.vue'
import SlipPrintDialog from '@/components/documents/SlipPrintDialog.vue'

const props = defineProps<{ modelValue: boolean; contractId?: number | null; enrollmentId?: number | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

const { t } = useI18n()
const toast = useToast()
const slip = ref<AcademySlipPayload | null>(null)
const loading = ref(false)
const templateRef = ref<InstanceType<typeof AcademySlipTemplate> | null>(null)

const paperSize = ref<AcademySlipPaperSize>('A4 2x A5')
// Two slips are always issued together: the academy copy and the office copy.
const copies = 2
let requestGeneration = 0

const paperSizes = computed<Array<{ title: string; value: AcademySlipPaperSize }>>(() => [
  { title: t('documents.academySlip.paperA4TwoA5'), value: 'A4 2x A5' },
  { title: t('documents.academySlip.paperA5Two'), value: 'A5 2x' },
  { title: t('documents.academySlip.paperA4'), value: 'A4' },
  { title: t('documents.academySlip.paperA5'), value: 'A5' },
])

watch(() => [props.modelValue, props.contractId, props.enrollmentId] as const, async ([open, contractId, enrollmentId]) => {
  const generation = ++requestGeneration
  slip.value = null
  loading.value = false
  if (!open || (!contractId && !enrollmentId))
    return

  loading.value = true
  try {
    const result = enrollmentId
      ? await AcademySlipApi.getForEnrollment(Number(enrollmentId))
      : await AcademySlipApi.getForContract(Number(contractId))
    if (generation === requestGeneration)
      slip.value = result
  }
  catch (error: any) {
    if (generation === requestGeneration)
      toast.error(error?.response?.data?.message || t('documents.academySlip.loadFailed'))
  }
  finally {
    if (generation === requestGeneration)
      loading.value = false
  }
}, { immediate: true })

const print = async () => {
  if (!templateRef.value)
    throw new Error('documents.academySlip.printFailed')
  await templateRef.value.print()
}
</script>

<template>
  <SlipPrintDialog
    :model-value="modelValue"
    :title="t('documents.academySlip.title')"
    :subtitle="slip?.reference"
    icon="tabler-school"
    :paper-size="paperSize"
    :paper-sizes="paperSizes"
    :loading="loading"
    :ready="!!slip"
    :print-action="print"
    :print-label="t('documents.academySlip.print')"
    error-fallback="documents.academySlip.printFailed"
    @update:model-value="emit('update:modelValue', $event)"
    @update:paper-size="paperSize = $event as AcademySlipPaperSize"
  >
    <AcademySlipTemplate
      v-if="slip"
      ref="templateRef"
      :slip="slip"
      :copies="copies"
      :paper-size="paperSize"
    />
  </SlipPrintDialog>
</template>
