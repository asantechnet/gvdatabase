<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import AttachmentsApi, { type Attachment, type AttachmentType } from '@/api/attachments'
import { apiErrorMessage } from '@/utils/apiErrors'
import { confirmActionWarning } from '@/composables/useActionWarning'

const props = withDefaults(defineProps<{
  type: AttachmentType
  recordId: number | null
  canEdit?: boolean
}>(), {
  canEdit: true,
})

const emit = defineEmits<{
  (e: 'update:count', value: number): void
}>()

const toast = useToast()

// The accepted formats intentionally mirror the task-attachment contract so a
// PDF, image or office file is accepted everywhere.
const ACCEPT = '.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp'

const attachments = ref<Attachment[]>([])
const loading = ref(false)
const uploading = ref(false)
const removingId = ref<string | null>(null)
const error = ref('')
const fileInput = ref<HTMLInputElement | null>(null)
const pickedFile = ref<File | null>(null)
const previewingId = ref<string | null>(null)
const previewAttachment = ref<Attachment | null>(null)
const previewUrl = ref('')
const previewOpen = ref(false)

const canUpload = computed(() => props.canEdit && !!props.recordId)

const formatFileSize = (size: number) => {
  if (!size)
    return '0 B'
  const units = ['B', 'KB', 'MB', 'GB']
  const index = Math.min(Math.floor(Math.log(size) / Math.log(1024)), units.length - 1)

  return `${(size / (1024 ** index)).toFixed(index === 0 ? 0 : 1)} ${units[index]}`
}

const isImage = (attachment: Attachment) => attachment.mimeType?.startsWith('image/') ?? false
const isPdf = (attachment: Attachment) => attachment.mimeType === 'application/pdf'
const canPreviewInline = (attachment: Attachment) => isImage(attachment) || isPdf(attachment) || attachment.mimeType?.startsWith('text/')

const clearPreview = () => {
  if (previewUrl.value)
    URL.revokeObjectURL(previewUrl.value)

  previewUrl.value = ''
  previewAttachment.value = null
}

const load = async () => {
  if (!props.recordId) {
    attachments.value = []
    emit('update:count', 0)

    return
  }
  loading.value = true
  error.value = ''
  try {
    attachments.value = await AttachmentsApi.list(props.type, props.recordId)
    emit('update:count', attachments.value.length)
  }
  catch (err) {
    error.value = apiErrorMessage(err, translate('common.attachments.loadFailed'))
  }
  finally {
    loading.value = false
  }
}

const pickFile = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null
  pickedFile.value = file
  if (file)
    void upload()

  ;(event.target as HTMLInputElement).value = ''
}

const upload = async () => {
  if (!canUpload.value || !pickedFile.value || !props.recordId)
    return
  uploading.value = true
  error.value = ''
  try {
    const created = await AttachmentsApi.upload(props.type, props.recordId, pickedFile.value)
    attachments.value = [created, ...attachments.value]
    pickedFile.value = null
    emit('update:count', attachments.value.length)
    toast.success(translate('common.attachments.uploaded'))
  }
  catch (err) {
    error.value = apiErrorMessage(err, translate('common.attachments.uploadFailed'))
  }
  finally {
    uploading.value = false
  }
}

const download = async (attachment: Attachment) => {
  try {
    const response = await AttachmentsApi.download(props.type, props.recordId as number, attachment.id)
    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = url
    link.download = attachment.name
    link.click()
    URL.revokeObjectURL(url)
  }
  catch (err) {
    error.value = apiErrorMessage(err, translate('common.attachments.downloadFailed'))
  }
}

const preview = async (attachment: Attachment) => {
  if (!props.recordId)
    return

  previewingId.value = attachment.id
  error.value = ''
  try {
    clearPreview()
    const response = await AttachmentsApi.download(props.type, props.recordId, attachment.id)
    previewUrl.value = URL.createObjectURL(response.data)
    previewAttachment.value = attachment
    previewOpen.value = true
  }
  catch (err) {
    error.value = apiErrorMessage(err, translate('common.attachments.downloadFailed'))
  }
  finally {
    previewingId.value = null
  }
}

const remove = async (attachment: Attachment) => {
  if (!props.recordId)
    return

  const confirmed = await confirmActionWarning({
    key: `remove-attachment-${attachment.id}`,
    title: translate('common.attachments.remove'),
    message: `${attachment.name} will be permanently removed. This action cannot be undone.`,
    confirmText: translate('common.attachments.remove'),
    cancelText: translate('common.actions.cancel'),
    icon: 'tabler-trash',
    tone: 'error',
  })
  if (!confirmed)
    return

  removingId.value = attachment.id
  error.value = ''
  try {
    await AttachmentsApi.remove(props.type, props.recordId, attachment.id)
    attachments.value = attachments.value.filter(item => item.id !== attachment.id)
    emit('update:count', attachments.value.length)
    toast.success(translate('common.attachments.removed'))
  }
  catch (err) {
    error.value = apiErrorMessage(err, translate('common.attachments.removeFailed'))
  }
  finally {
    removingId.value = null
  }
}

watch(() => [props.type, props.recordId], load, { immediate: true })
watch(previewOpen, open => {
  if (!open)
    clearPreview()
})
</script>

<template>
  <div class="attachments-panel">
    <div
      v-if="canEdit"
      class="attachments-panel__dropzone"
    >
      <input
        ref="fileInput"
        type="file"
        :accept="ACCEPT"
        class="d-none"
        :disabled="!canUpload || uploading"
        @change="pickFile"
      >
      <VAvatar color="primary" variant="tonal" size="42"><VIcon icon="tabler-cloud-upload" /></VAvatar>
      <div class="attachments-panel__dropzone-copy">
        <div class="text-body-2 font-weight-bold">{{ $t('common.attachments.add') }}</div>
        <div class="text-caption text-medium-emphasis">{{ $t('common.attachments.acceptedHint') }}</div>
      </div>
      <VBtn color="primary" variant="tonal" :loading="uploading" :disabled="!canUpload" @click="fileInput?.click()"><VIcon icon="tabler-file-upload" start />{{ $t('common.attachments.add') }}</VBtn>
    </div>

    <VAlert
      v-if="error"
      type="error"
      variant="tonal"
      density="compact"
      class="mt-2"
      closable
      @click:close="error = ''"
    >
      {{ error }}
    </VAlert>

    <div
      v-if="loading"
      class="d-flex justify-center pa-4"
    >
      <VProgressCircular
        indeterminate
        size="24"
      />
    </div>

    <VList
      v-else-if="attachments.length"
      class="attachments-panel__list"
      density="comfortable"
    >
      <VListItem
        v-for="attachment in attachments"
        :key="attachment.id"
        class="attachments-panel__item"
      >
        <template #prepend>
          <VAvatar
            size="34"
            variant="tonal"
            color="primary"
          >
            <VImg
              v-if="isImage(attachment)"
              :src="attachment.downloadUrl"
              :alt="attachment.name"
            />
            <VIcon
              v-else
              icon="tabler-paperclip"
            />
          </VAvatar>
        </template>
        <VListItemTitle class="text-body-2">
          {{ attachment.name }}
        </VListItemTitle>
        <VListItemSubtitle>{{ formatFileSize(attachment.size) }}</VListItemSubtitle>
        <template #append>
          <div class="d-flex align-center">
            <VBtn
              icon="tabler-eye"
              variant="text"
              size="small"
              :loading="previewingId === attachment.id"
              :aria-label="$t('common.actions.viewFullDetails')"
              :title="$t('common.actions.viewFullDetails')"
              @click="preview(attachment)"
            />
            <VBtn
              icon="tabler-download"
              variant="text"
              size="small"
              :aria-label="$t('common.attachments.download')"
              :title="$t('common.attachments.download')"
              @click="download(attachment)"
            />
            <VBtn
              v-if="canEdit"
              icon="tabler-trash"
              variant="text"
              size="small"
              color="error"
              :loading="removingId === attachment.id"
              :aria-label="$t('common.attachments.remove')"
              :title="$t('common.attachments.remove')"
              @click="remove(attachment)"
            />
          </div>
        </template>
      </VListItem>
    </VList>

    <p
      v-else
      class="attachments-panel__empty text-body-2 text-medium-emphasis mt-3"
    >
      {{ $t('common.attachments.empty') }}
    </p>

    <VDialog v-model="previewOpen" max-width="960" content-class="attachments-preview-dialog" @update:model-value="open => !open && clearPreview()">
      <VCard class="attachments-preview-dialog__card">
        <VCardTitle class="attachments-preview-dialog__title px-5 py-4"><span class="text-truncate">{{ previewAttachment?.name }}</span><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" :aria-label="$t('common.actions.close')" @click="previewOpen = false" /></VCardTitle>
        <VCardText class="attachments-preview-dialog__body pa-4">
          <VImg v-if="previewAttachment && isImage(previewAttachment)" :src="previewUrl" :alt="previewAttachment.name" class="attachments-preview-dialog__image" contain />
          <iframe v-else-if="previewAttachment && canPreviewInline(previewAttachment)" :src="previewUrl" :title="previewAttachment.name" class="attachments-preview-dialog__frame" />
          <div v-else class="attachments-preview-dialog__fallback"><VIcon icon="tabler-file-description" size="48" color="primary" /><p>{{ previewAttachment?.name }}</p><VBtn color="primary" variant="tonal" prepend-icon="tabler-download" @click="previewAttachment && download(previewAttachment)">{{ $t('common.attachments.download') }}</VBtn></div>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.attachments-panel__dropzone {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  min-block-size: 96px;
  padding: 16px;
  border: 1px dashed rgba(var(--v-theme-primary), .45);
  border-radius: 12px;
  background: rgba(var(--v-theme-primary), .045);
}
.attachments-panel__dropzone-copy { display: grid; flex: 1 1 12rem; gap: 2px; min-inline-size: 0; }
.attachments-panel__empty { padding: 1.25rem; border: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 12px; text-align: center; }

.attachments-panel__list {
  background: transparent;
}

.attachments-panel__item {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  margin-block-end: 6px;
}
:global(.v-overlay:has(.attachments-preview-dialog) .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .28); backdrop-filter: blur(5px); opacity: 1 !important; }
:global(.attachments-preview-dialog__card) { overflow: hidden; }
:global(.attachments-preview-dialog__title) { display: flex; align-items: center; border-block-end: 1px solid rgba(var(--v-theme-on-surface), .06); background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%); font-size: 1rem; font-weight: 700; }
:global(.attachments-preview-dialog__body) { display: grid; min-block-size: min(60vh, 36rem); place-items: center; background: rgba(var(--v-theme-on-surface), .025); }
:global(.attachments-preview-dialog__image) { inline-size: 100%; block-size: min(60vh, 36rem); }
:global(.attachments-preview-dialog__frame) { inline-size: 100%; block-size: min(60vh, 36rem); border: 0; background: rgb(var(--v-theme-surface)); }
:global(.attachments-preview-dialog__fallback) { display: grid; justify-items: center; gap: 1rem; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); text-align: center; }
</style>
