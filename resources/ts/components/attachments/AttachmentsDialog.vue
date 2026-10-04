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
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between text-h6">
        <span class="text-truncate">{{ props.title || $t('common.attachments.title') }}</span>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          :aria-label="$t('common.actions.close')"
          @click="close"
        />
      </VCardTitle>
      <VDivider />
      <VCardText>
        <AttachmentsPanel
          :type="props.type"
          :record-id="props.recordId"
          :can-edit="props.canEdit"
        />
      </VCardText>
    </VCard>
  </VDialog>
</template>
