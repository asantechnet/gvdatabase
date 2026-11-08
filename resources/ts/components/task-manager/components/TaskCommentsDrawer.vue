<script setup lang="ts">
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { ref, watch } from 'vue'
import { useTaskStore } from '@/stores/useTaskStore'
import { canAccess } from '@/utils/authorization'

const props = defineProps<{
  isOpen: boolean
}>()

const emit = defineEmits<{
  (e: 'update:isOpen', value: boolean): void
}>()

const store = useTaskStore()
const newComment = ref('')
const editingCommentId = ref<string | null>(null)
const editingContent = ref('')
const userData = useCookie<{ id?: string | number } | null>('userData')
const { t, locale } = useI18n()
const initials = (name: string) => name.trim().split(/\s+/).filter(Boolean).map(part => part[0]).slice(0, 2).join('').toUpperCase() || '?'

const canManageComment = (comment: { senderId: string }) => canAccess('edit_all_comments')
  || (canAccess('edit_own_comments') && comment.senderId === String(userData.value?.id ?? ''))

const canDeleteComment = (comment: { senderId: string }) => canAccess('delete_all_comments')
  || (canAccess('delete_own_comments') && comment.senderId === String(userData.value?.id ?? ''))

const commentTime = (comment: { createdAt?: string | null; timestamp?: string }) => {
  if (!comment.createdAt)
    return comment.timestamp ?? ''

  const date = new Date(comment.createdAt)
  if (Number.isNaN(date.getTime()))
    return comment.timestamp ?? ''

  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}

const startEditing = (comment: { id: string; content: string; canEdit: boolean }) => {
  if (!comment.canEdit)
    return
  editingCommentId.value = comment.id
  editingContent.value = comment.content
}

const cancelEditing = () => {
  editingCommentId.value = null
  editingContent.value = ''
}

const saveComment = async () => {
  if (!store.selectedTaskId || !editingCommentId.value || !editingContent.value.trim())
    return
  await store.updateComment(store.selectedTaskId, editingCommentId.value, editingContent.value.trim())
  cancelEditing()
}

const deleteComment = async (commentId: string) => {
  if (!store.selectedTaskId)
    return
  await store.deleteComment(store.selectedTaskId, commentId)
}

const handleClose = () => {
  emit('update:isOpen', false)
}

const handleSendComment = async () => {
  if (newComment.value.trim() && store.selectedTaskId) {
    try {
      await store.addComment(store.selectedTaskId, newComment.value.trim())
      newComment.value = ''
    }
    catch (error) {
      console.error('Failed to add comment:', error)
    }
  }
}

// Scroll to bottom when comments change
watch(() => store.selectedTask?.comments, () => {
  setTimeout(() => {
    const container = document.querySelector('.comments-scroll-container')
    if (container)
      container.scrollTop = container.scrollHeight
  }, 100)
}, { deep: true })
</script>

<template>
  <VDialog
    :model-value="props.isOpen"
    scrim
    content-class="task-comments-overlay"
    @update:model-value="handleClose"
  >
    <VCard
      elevation="0"
      class="task-comments-drawer d-flex flex-column"
    >
      <VCardItem class="task-comments-drawer__header border-b">
        <template #title>
          <div class="d-flex align-center">
            <VIcon
              icon="tabler-message-circle"
              class="me-2"
            />
            <span class="text-h6 font-weight-bold">{{ $t('components.taskManager.taskcommentsdrawer.taskComments') }}</span>
          </div>
        </template>
        <template #append>
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="error"
            size="small"
            @click="handleClose"
          />
        </template>
      </VCardItem>

      <div class="flex-grow-1 overflow-y-auto comments-scroll-container pa-4">
        <div
          v-if="store.selectedTask"
          class="comments-container"
        >
          <div
            v-if="!store.selectedTask.comments || store.selectedTask.comments.length === 0"
            class="text-center text-disabled mt-10"
          >
            <VIcon
              icon="tabler-messages"
              size="40"
              class="mb-2 opacity-50"
            />
            <p>{{ $t('components.taskManager.taskcommentsdrawer.noCommentsYet') }}<br>{{ $t('components.taskManager.taskcommentsdrawer.beTheFirstToStartTheDiscussion') }}</p>
          </div>

          <div
            v-for="comment in store.selectedTask.comments"
            :key="comment.id"
            class="comment mb-4"
          >
            <div class="d-flex">
              <VAvatar
                size="32"
                class="mr-3"
                color="primary"
                variant="tonal"
              >
                <VImg
                  v-if="comment.senderAvatar"
                  :src="comment.senderAvatar"
                  :alt="comment.senderName"
                />
                <span
                  v-else
                  class="text-caption font-weight-medium"
                >{{ initials(comment.senderName) }}</span>
              </VAvatar>
              <div class="flex-grow-1">
                <div class="d-flex justify-space-between align-center mb-1">
                  <span class="font-weight-medium">{{ comment.senderName }}</span>
                  <span class="text-caption text-disabled">{{ commentTime(comment) }}</span>
                </div>
                <template v-if="editingCommentId === comment.id">
                  <VTextarea
                    v-model="editingContent"
                    rows="2"
                    auto-grow
                    class="mt-2"
                  />
                  <div class="d-flex ga-2 mt-2">
                    <VBtn
                      size="small"
                      @click="saveComment"
                    >
                      {{ $t('common.actions.save') }}
                    </VBtn>
                    <VBtn
                      size="small"
                      variant="tonal"
                      @click="cancelEditing"
                    >
                      {{ $t('common.actions.cancel') }}
                    </VBtn>
                  </div>
                </template>
                <div
                  v-else
                  class="d-flex align-start justify-space-between ga-2"
                >
                  <p class="mb-0">
                    {{ comment.content }}
                  </p>
                  <div class="d-flex align-center ga-1">
                    <VTooltip
                      v-if="canManageComment(comment)"
                      :text="comment.editWindowExpired && !comment.canEdit ? t('common.messages.editWindowExpired') : t('components.taskManager.comments.editComment')"
                    >
                      <template #activator="{ props: tooltipProps }">
                        <IconBtn v-bind="tooltipProps" size="small" :disabled="!comment.canEdit" @click="startEditing(comment)">
                          <VIcon icon="tabler-pencil" size="16" />
                        </IconBtn>
                      </template>
                    </VTooltip>
                    <VTooltip v-if="canDeleteComment(comment)" :text="$t('common.actions.delete')">
                      <template #activator="{ props: tooltipProps }">
                        <IconBtn v-bind="tooltipProps" size="small" color="error" @click="deleteComment(comment.id)">
                          <VIcon icon="tabler-trash" size="16" />
                        </IconBtn>
                      </template>
                    </VTooltip>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <VCardActions
        v-if="canAccess('create_comments_on_assigned_tasks')"
        class="task-comments-drawer__footer border-t pa-4"
      >
        <VTextarea
          v-model="newComment"
          :placeholder="$t('components.taskManager.taskcommentsdrawer.addAComment')"
          rows="2"
          auto-grow
          hide-details
          persistent-placeholder
          class="mr-2"
          @keyup.enter.exact.prevent="handleSendComment"
        />
        <VBtn
          icon="tabler-send"
          color="primary"
          :disabled="!newComment.trim()"
          @click="handleSendComment"
        />
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
:global(.v-overlay:has(.task-comments-overlay)) { z-index: 3000 !important; }
:global(.v-overlay:has(.task-comments-drawer) .v-overlay__scrim),
:global(.v-overlay:has(.task-comments-overlay) .v-overlay__scrim) {
  background: rgba(var(--v-theme-on-surface), .3) !important;
  backdrop-filter: blur(7px);
  opacity: 1 !important;
}

:global(.task-comments-overlay) {
  position: fixed !important;
  inset: .75rem .75rem .75rem auto !important;
  display: flex;
  inline-size: min(calc(100vw - 1.5rem), 32rem) !important;
  block-size: calc(100dvh - 1.5rem) !important;
  max-inline-size: none !important;
  margin: 0 !important;
  transform: none !important;
}

:global(.task-comments-drawer) {
  z-index: 3001 !important;
  inline-size: 100%;
  block-size: 100%;
  border-radius: 12px;
}

.task-comments-drawer__header,
.task-comments-drawer__footer { background: rgba(var(--v-theme-on-surface), .025); }

.comment {
  word-break: break-word;
}

.comments-scroll-container {
  min-block-size: 0;
}
</style>
