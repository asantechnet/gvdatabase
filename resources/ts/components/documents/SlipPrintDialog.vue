<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'

interface PaperOption {
  title: string
  value: string
}

const props = withDefaults(defineProps<{
  modelValue: boolean
  title: string
  subtitle?: string | null
  icon?: string
  paperSize?: string
  paperSizes?: PaperOption[]
  loading?: boolean
  ready?: boolean
  printAction: () => Promise<void> | void
  printLabel?: string
  errorFallback?: string
  maxWidth?: string | number
}>(), {
  subtitle: null,
  icon: 'tabler-file-text',
  paperSize: 'A5',
  paperSizes: () => [],
  loading: false,
  ready: false,
  printLabel: '',
  errorFallback: 'documents.receipt.printFailed',
  maxWidth: 1100,
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'update:paperSize': [value: string]
}>()

const { t } = useI18n()
const toast = useToast()
const printing = ref(false)

// Preview scale mirrors the physical paper so the popup reads like the sheet.
const previewScale = computed(() => {
  const paper = props.paperSize
  if (paper === 'A5' || paper.includes('A5 2x'))
    return 0.85
  if (paper === 'A4 2x A5')
    return 0.9
  if (paper.startsWith('A3') || paper.includes('landscape'))
    return 0.6

  return 1
})

const selectedPaper = computed({
  get: () => props.paperSize,
  set: (value: string) => emit('update:paperSize', value),
})

const close = () => emit('update:modelValue', false)

/**
 * Printing is a deliberate action from this dialog. Nothing opens the browser
 * print engine before the operator presses this button.
 */
const print = async () => {
  if (printing.value || props.loading || !props.ready)
    return

  printing.value = true
  try {
    await props.printAction()
  }
  catch (error) {
    const key = error instanceof Error && error.message.startsWith('documents.')
      ? error.message
      : props.errorFallback
    toast.error(t(key))
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
    :max-width="maxWidth"
    :persistent="printing"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard flat class="slip-print-dialog">
      <VCardTitle class="slip-print-dialog__header">
        <div class="d-flex align-center ga-3">
          <VAvatar color="primary" variant="tonal" size="38">
            <VIcon :icon="icon" />
          </VAvatar>
          <div>
            <div class="text-h6 font-weight-bold">
              {{ title }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ subtitle || '—' }}
            </div>
          </div>
        </div>
        <div class="d-flex flex-wrap align-center justify-end ga-3">
          <VSelect
            v-if="paperSizes.length > 1"
            v-model="selectedPaper"
            :items="paperSizes"
            :label="t('documents.academySlip.paperSize')"
            density="compact"
            hide-details
            :disabled="printing"
            style="min-width: 200px;"
          />
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="printing"
            @click="close"
          >
            {{ t('common.actions.close') }}
          </VBtn>
          <VBtn
            color="primary"
            prepend-icon="tabler-printer"
            :disabled="!ready || loading || printing"
            :loading="printing"
            @click="print"
          >
            {{ printLabel || t('common.actions.print') }}
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="slip-print-dialog__body">
        <VProgressCircular
          v-if="loading"
          indeterminate
          color="primary"
        />
        <div
          v-else
          class="slip-print-dialog__paper"
          :style="{ transform: `scale(${previewScale})` }"
        >
          <slot />
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.slip-print-dialog {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  max-block-size: 92vh;
  overflow: hidden;
}

.slip-print-dialog__header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.875rem 1.25rem;
  background: rgb(var(--v-theme-surface));
}

.slip-print-dialog__body {
  display: grid;
  place-items: start center;
  padding: 1.5rem;
  max-block-size: 74vh;
  overflow: auto;
  background: rgb(var(--v-theme-surface-variant));
}

.slip-print-dialog__paper {
  width: 100%;
  transform-origin: top center;
}
</style>
