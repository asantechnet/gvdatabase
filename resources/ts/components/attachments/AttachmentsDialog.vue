<script setup lang="ts">
import type { AttachmentType } from '@/api/attachments'
import AttachmentsPanel from './AttachmentsPanel.vue'

const props = withDefaults(defineProps<{
  modelValue: boolean
  type: AttachmentType
  recordId: number | null
  title?: string
  canEdit?: boolean
}>(), {
  title: '',
  canEdit: true,
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
}>()

const close = () => emit('update:modelValue', false)
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="560"
    content-class="attachments-dialog-overlay"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="attachments-dialog-card">
      <VCardTitle class="attachments-dialog-title px-5 py-4">
        <div class="min-w-0">
          <div class="attachments-dialog-eyebrow">{{ $t('common.attachments.title') }}</div>
          <div class="attachments-dialog-heading text-truncate">{{ props.title || $t('common.attachments.title') }}</div>
        </div>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="tonal"
          color="error"
          class="attachments-dialog-close"
          :aria-label="$t('common.actions.close')"
          @click="close"
        />
      </VCardTitle>
      <VCardText class="attachments-dialog-body pa-5">
        <AttachmentsPanel
          :type="props.type"
          :record-id="props.recordId"
          :can-edit="props.canEdit"
        />
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
:global(.v-overlay:has(.attachments-dialog-overlay) .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .28); backdrop-filter: blur(5px); opacity: 1 !important; }
.attachments-dialog-card { overflow: hidden; }
.attachments-dialog-title { display: flex; align-items: center; border-block-end: 1px solid rgba(var(--v-theme-on-surface), .06); background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%); }
.attachments-dialog-eyebrow { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
.attachments-dialog-heading { font-size: 1.25rem; font-weight: 700; }
.attachments-dialog-close { inline-size: 36px; block-size: 36px; border: 1px solid rgba(var(--v-theme-error), .38); border-radius: 50%; }
.attachments-dialog-body { min-block-size: 20rem; }
</style>
