<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
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
const editingMentionIds = ref<number[]>([])
const newMentionIds = ref<number[]>([])
const commentInputRef = ref<any>()
const mentionMenuOpen = ref(false)
const mentionQuery = ref('')
const mentionStart = ref(-1)
const userData = useCookie<{ id?: string | number } | null>('userData')
const { t, locale } = useI18n()
const initials = (name: string) => name.trim().split(/\s+/).filter(Boolean).map(part => part[0]).slice(0, 2).join('').toUpperCase() || '?'

const canManageComment = (comment: { senderId: string }) => canAccess('edit_own_comments')
  && comment.senderId === String(userData.value?.id ?? '')

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


const mentionUsers = computed(() => {
  const task = store.selectedTask
  if (!task)
    return []

  const users = [...(task.assignees ?? [])]
  if (task.creator)
    users.push(task.creator)

  const seen = new Set<string>()
  return users.filter(user => {
    if (!user || seen.has(String(user.id)))
      return false
    seen.add(String(user.id))
    return true
  })
})

const mentionCandidates = computed(() => {
  const query = mentionQuery.value.trim().toLowerCase()
  if (!query)
    return mentionUsers.value

  return mentionUsers.value.filter(user => user.name.toLowerCase().includes(query))
})

const commentSegments = (content: string, mentions: Array<{ name: string }>) => {
  const names = mentions.map(mention => mention.name).filter(Boolean).sort((a, b) => b.length - a.length)
  if (!names.length)
    return [{ text: content, mention: false }]

  const escaped = names.map(name => name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
  const pattern = new RegExp(`(@(?:${escaped.join('|')}))`, 'g')

  return content.split(pattern).filter(Boolean).map(text => ({
    text,
    mention: text.startsWith('@') && names.includes(text.slice(1)),
  }))
}

const textareaElement = () => {
  const root = commentInputRef.value?.$el as HTMLElement | undefined

  return root?.querySelector('textarea') ?? null
}

const closeMentionMenu = () => {
  mentionMenuOpen.value = false
  mentionQuery.value = ''
  mentionStart.value = -1
}

const handleCommentInput = (value: string) => {
  const textarea = textareaElement()
  const cursor = textarea?.selectionStart ?? value.length
  const before = value.slice(0, cursor)
  const at = before.lastIndexOf('@')

  if (at === -1 || /\s/.test(before.slice(at + 1))) {
    closeMentionMenu()
    return
  }

  mentionStart.value = at
  mentionQuery.value = before.slice(at + 1)
  mentionMenuOpen.value = true
}

const insertMention = (user: { id: string; name: string }) => {
  const textarea = textareaElement()
  const cursor = textarea?.selectionStart ?? newComment.value.length
  const start = mentionStart.value >= 0 ? mentionStart.value : cursor
  const token = `@${user.name} `

  newComment.value = `${newComment.value.slice(0, start)}${token}${newComment.value.slice(cursor)}`
  const numericId = Number(user.id)
  if (Number.isFinite(numericId) && !newMentionIds.value.includes(numericId))
    newMentionIds.value = [...newMentionIds.value, numericId]

  closeMentionMenu()
  nextTick(() => {
    const nextCursor = start + token.length
    textarea?.focus()
    textarea?.setSelectionRange(nextCursor, nextCursor)
  })
}

const tagsForMention = (content: string, ids: number[]) => {
  const users = mentionUsers.value
  return ids.filter(id => {
    const user = users.find(candidate => Number(candidate.id) === id)
    return user ? content.includes(`@${user.name}`) : false
  })
}

const startEditing = (comment: { id: string; content: string; canEdit: boolean; mentionIds?: string[] }) => {
  if (!comment.canEdit)
    return
  editingCommentId.value = comment.id
  editingContent.value = comment.content
  editingMentionIds.value = (comment.mentionIds ?? []).map(Number).filter(Number.isFinite)
}

const cancelEditing = () => {
  editingCommentId.value = null
  editingContent.value = ''
  editingMentionIds.value = []
}

const saveComment = async () => {
  if (!store.selectedTaskId || !editingCommentId.value || !editingContent.value.trim())
    return
  await store.updateComment(
    store.selectedTaskId,
    editingCommentId.value,
    editingContent.value.trim(),
    tagsForMention(editingContent.value, editingMentionIds.value),
  )
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
      await store.addComment(
        store.selectedTaskId,
        newComment.value.trim(),
        tagsForMention(newComment.value, newMentionIds.value),
      )
      newComment.value = ''
      newMentionIds.value = []
      closeMentionMenu()
    }
    catch (error) {
      console.error('Failed to add comment:', error)
    }
  }
}

watch(() => store.selectedTask?.comments, () => {
  setTimeout(() => {
    const container = document.querySelector('.comments-scroll-container')
    if (container)
      container.scrollTop = container.scrollHeight
  }, 100)
}, { deep: true })

watch(() => store.selectedTaskId, () => {
  closeMentionMenu()
  newMentionIds.value = []
})
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
                    <template
                      v-for="(segment, index) in commentSegments(comment.content, comment.mentions ?? [])"
                      :key="index"
                    >
                      <span :class="{ 'comment-mention': segment.mention }">{{ segment.text }}</span>
                    </template>
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

        class="task-comments-drawer__footer border-t pa-4 position-relative flex-column align-stretch"
      >
        <div
          v-if="mentionMenuOpen"
          class="mention-menu border rounded bg-surface elevation-4"
        >
          <VList
            v-if="mentionCandidates.length"
            density="compact"
            role="listbox"
          >
            <VListItem
              v-for="user in mentionCandidates"
              :key="user.id"
              :title="user.name"
              @click="insertMention(user)"
            >
              <template #prepend>
                <VAvatar
                  size="26"
                  color="primary"
                  variant="tonal"
                >
                  <span class="text-caption font-weight-medium">{{ initials(user.name) }}</span>
                </VAvatar>
              </template>
            </VListItem>
          </VList>
          <div
            v-else
            class="pa-3 text-caption text-medium-emphasis"
          >
            {{ $t('components.taskManager.taskcommentsdrawer.noMatchingPeople') }}
          </div>
        </div>

        <div class="d-flex align-start w-100">
          <VTextarea
            ref="commentInputRef"
            v-model="newComment"
            :placeholder="$t('components.taskManager.taskcommentsdrawer.addAComment')"
            rows="2"
            auto-grow
            hide-details
            persistent-placeholder
            class="mr-2"
            @update:model-value="handleCommentInput"
            @keydown.esc="closeMentionMenu"
            @keyup.enter.exact.prevent="handleSendComment"
          />
          <VBtn
            icon="tabler-send"
            color="primary"
            :disabled="!newComment.trim()"
            @click="handleSendComment"
          />
        </div>
        <div class="text-caption text-medium-emphasis mt-2">
          {{ $t('components.taskManager.taskcommentsdrawer.mentionHint') }}
        </div>
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

.comment-mention {
  border-radius: 4px;
  background: rgba(var(--v-theme-primary), 0.12);
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
  padding-inline: 2px;
}

.comments-scroll-container {
  min-block-size: 0;
}

.mention-menu {
  position: absolute;
  inset-block-end: 100%;
  inset-inline: 1rem;
  z-index: 2;
  max-block-size: 14rem;
  overflow-y: auto;
}
</style>
