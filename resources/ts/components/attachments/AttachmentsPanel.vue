<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import AttachmentsApi, { type Attachment, type AttachmentType } from '@/api/attachments'
import { apiErrorMessage } from '@/utils/apiErrors'

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

const canUpload = computed(() => props.canEdit && !!props.recordId)

const formatFileSize = (size: number) => {
  if (!size)
    return '0 B'
  const units = ['B', 'KB', 'MB', 'GB']
  const index = Math.min(Math.floor(Math.log(size) / Math.log(1024)), units.length - 1)

  return `${(size / (1024 ** index)).toFixed(index === 0 ? 0 : 1)} ${units[index]}`
}

const isImage = (attachment: Attachment) => attachment.mimeType?.startsWith('image/') ?? false

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

const remove = async (attachment: Attachment) => {
  if (!props.recordId)
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
</script>

<template>
  <div class="attachments-panel">
    <div
      v-if="canEdit"
      class="attachments-panel__toolbar"
    >
      <input
        ref="fileInput"
        type="file"
        :accept="ACCEPT"
        class="d-none"
        :disabled="!canUpload || uploading"
        @change="pickFile"
      >
      <VBtn
        color="primary"
        variant="tonal"
        size="small"
        :loading="uploading"
        :disabled="!canUpload"
        @click="fileInput?.click()"
      >
        <VIcon
          icon="tabler-file-upload"
          start
        />
        {{ $t('common.attachments.add') }}
      </VBtn>
      <span class="text-caption text-medium-emphasis ms-2">
        {{ $t('common.attachments.acceptedHint') }}
      </span>
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
      class="text-body-2 text-medium-emphasis mt-3"
    >
      {{ $t('common.attachments.empty') }}
    </p>
  </div>
</template>

<style scoped>
.attachments-panel__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
}

.attachments-panel__list {
  background: transparent;
}

.attachments-panel__item {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  margin-block-end: 6px;
}
</style>
