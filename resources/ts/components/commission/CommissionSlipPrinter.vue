<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import CommissionApi, { type CommissionSlipPayload } from '@/api/commission'
import CommissionSlipTemplate, { type CommissionSlipPaperSize } from '@/pages/templates/commission_slip_template.vue'

const props = withDefaults(defineProps<{ modelValue: boolean; settlementId: number | null; directPrint?: boolean }>(), {
  directPrint: false,
})
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

const { t } = useI18n()
const toast = useToast()
const slip = ref<CommissionSlipPayload | null>(null)
const loading = ref(false)
const printing = ref(false)
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

const previewScale = computed(() => (paperSize.value === 'A5' || paperSize.value === 'A5 2x' ? 0.85 : paperSize.value === 'A4 2x A5' ? 0.9 : 1))

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

  if (generation === requestGeneration && props.directPrint && slip.value) {
    await nextTick()
    await print()
    emit('update:modelValue', false)
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
    toast.error(t('documents.commissionSlip.printFailed'))
  }
  finally {
    printing.value = false
  }
}
</script>

<template>
  <div v-if="directPrint && slip" class="commission-slip-direct-print-host">
    <CommissionSlipTemplate ref="templateRef" :slip="slip" :copies="copies" :paper-size="paperSize" />
  </div>
  <VDialog
    v-if="!directPrint"
    :model-value="modelValue"
    width="92vw"
    max-width="1100"
    :persistent="printing"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard flat>
      <VCardTitle class="d-flex flex-wrap align-center ga-3">
        <VAvatar
          color="primary"
          variant="tonal"
          size="38"
        >
          <VIcon icon="tabler-receipt" />
        </VAvatar>
        <div>
          <div class="text-h6 font-weight-bold">
            {{ t('documents.commissionSlip.title') }}
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ slip?.reference || '—' }}
          </div>
        </div>
        <VSpacer />
        <VSelect
          v-model="paperSize"
          :items="paperSizes"
          :label="t('documents.commissionSlip.paperSize')"
          density="compact"
          hide-details
          :disabled="printing"
          style="min-width: 200px;"
        />
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="printing"
          @click="emit('update:modelValue', false)"
        >
          {{ t('common.actions.close') }}
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-printer"
          :disabled="!slip || loading || printing"
          :loading="printing"
          @click="print"
        >
          {{ t('documents.commissionSlip.print') }}
        </VBtn>
      </VCardTitle>

      <VCardText class="commission-slip-preview">
        <VProgressCircular
          v-if="loading"
          indeterminate
          color="primary"
        />
        <div
          v-else-if="slip"
          class="commission-slip-preview__paper"
          :style="{ transform: `scale(${previewScale})` }"
        >
          <CommissionSlipTemplate
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
.commission-slip-preview {
  display: grid;
  place-items: start center;
  padding: 1.5rem;
  max-block-size: 74vh;
  overflow: auto;
  background: #edf2f7;
}

.commission-slip-preview__paper {
  width: 100%;
  transform-origin: top center;
}

.commission-slip-direct-print-host { display: none; }
</style>
