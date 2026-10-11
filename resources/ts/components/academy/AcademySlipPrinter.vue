<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import AcademySlipApi, { type AcademySlipPayload } from '@/api/academySlip'
import AcademySlipTemplate, { type AcademySlipPaperSize } from '@/pages/templates/academy_slip_template.vue'

const props = defineProps<{ modelValue: boolean; contractId: number | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

const { t } = useI18n()
const toast = useToast()
const slip = ref<AcademySlipPayload | null>(null)
const loading = ref(false)
const printing = ref(false)
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

const previewScale = computed(() => (paperSize.value === 'A5' || paperSize.value === 'A5 2x' ? 0.85 : paperSize.value === 'A4 2x A5' ? 0.9 : 1))

watch(() => [props.modelValue, props.contractId] as const, async ([open, contractId]) => {
  const generation = ++requestGeneration
  slip.value = null
  loading.value = false
  if (!open || !contractId)
    return

  loading.value = true
  try {
    const result = await AcademySlipApi.getForContract(Number(contractId))
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
  if (!templateRef.value || printing.value || loading.value)
    return
  printing.value = true
  try {
    await templateRef.value.print()
  }
  catch {
    toast.error(t('documents.academySlip.printFailed'))
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
    max-width="1100"
    :persistent="printing"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard flat>
      <VCardTitle class="d-flex flex-wrap align-center ga-3">
        <VAvatar color="primary" variant="tonal" size="38"><VIcon icon="tabler-school" /></VAvatar>
        <div>
          <div class="text-h6 font-weight-bold">{{ t('documents.academySlip.title') }}</div>
          <div class="text-caption text-medium-emphasis">{{ slip?.reference || '—' }}</div>
        </div>
        <VSpacer />
        <VSelect v-model="paperSize" :items="paperSizes" :label="t('documents.academySlip.paperSize')" density="compact" hide-details :disabled="printing" style="min-width: 200px;" />
        <VBtn variant="tonal" color="secondary" :disabled="printing" @click="emit('update:modelValue', false)">{{ t('common.actions.close') }}</VBtn>
        <VBtn color="primary" prepend-icon="tabler-printer" :disabled="!slip || loading || printing" :loading="printing" @click="print">{{ t('documents.academySlip.print') }}</VBtn>
      </VCardTitle>

      <VCardText class="academy-slip-preview">
        <VProgressCircular v-if="loading" indeterminate color="primary" />
        <div
          v-else-if="slip"
          class="academy-slip-preview__paper"
          :style="{ transform: `scale(${previewScale})` }"
        >
          <AcademySlipTemplate
            ref="templateRef"
            :slip="slip"
            :copies="copies"
            :paper-size="paperSize"
          />
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.academy-slip-preview {
  display: grid;
  place-items: start center;
  padding: 1.5rem;
  max-block-size: 74vh;
  overflow: auto;
  background: #edf2f7;
}

.academy-slip-preview__paper {
  width: 100%;
  transform-origin: top center;
}
</style>
