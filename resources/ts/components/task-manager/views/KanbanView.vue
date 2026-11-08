<script setup lang="ts">
import type Viewer from 'viewerjs'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import 'viewerjs/dist/viewer.css'
import TaskCommentsDrawer from '../components/TaskCommentsDrawer.vue'
import TaskDetailDrawer from '../components/TaskDetailDrawer.vue'
import TaskViewDialog from '../components/TaskViewDialog.vue'
import { useTaskStore } from '@/stores/useTaskStore'
import KanbanBoard from '@/pages/kanban/KanbanBoard.vue'
import TaskApi from '@/api/task'
import type { Task, TaskAttachment } from '@/api/kanban'

interface AddNewKanbanItem { boardId: string | number; itemTitle: string }
interface EditKanbanItem { item: { id: string | number } }

const store = useTaskStore()
const isViewDialogOpen = ref(false)
const isFullEditorOpen = ref(false)

// The full editor owns the fields the quick editor does not: description,
// priority, start date, dependencies and the completion toggle.
const handleEditFullItem = (payload: EditKanbanItem) => {
  store.selectTask(String(payload.item.id))
  isFullEditorOpen.value = true
}
const isCommentsDrawerOpen = ref(false)
const isDeleteDialogOpen = ref(false)
const pendingDeleteId = ref<string | null>(null)
const isLoading = ref(true)
const editorItemId = ref<string | null>(null)
const isAttachmentsDialogOpen = ref(false)
const attachmentTask = ref<Task | null>(null)
const attachmentImageContainer = ref<HTMLElement>()
const imagePreviewUrls = ref<Record<string, string>>({})
let imageViewer: Viewer | null = null

// Load data when component mounts
onMounted(async () => {
  try {
    isLoading.value = true
    await store.fetchBoardData()
  }
  finally {
    isLoading.value = false
  }
})

// Transform Pinia state to Vuexy's expected KanbanData format
const vuexyKanbanData = computed(() => {
  return {
    boards: store.columns.map(col => ({
      id: col.id,
      title: col.title,
      isPersonal: col.isPersonal,
      isOwned: col.isOwned,
      itemsIds: store.tasks
        .filter(t => t.boardId === col.id)
        .sort((a, b) => a.order - b.order)
        .map(t => t.id),
    })),
    items: store.tasks.map(task => ({
      id: task.id,
      title: task.title,
      dueDate: task.dueDate,
      labels: task.tags.map(t => t.name),
      labelColors: Object.fromEntries(task.tags.map(tag => [tag.name, tag.color])),
      members: task.assignees.map(assignee => ({
        id: assignee.id,
        name: assignee.name,
        img: assignee.avatar,
      })),
      commentsCount: task.comments.length,
      attachmentsCount: task.attachments?.length ?? 0,
      progress: task.progress,
      image: '',
      // Keep the complete plain-text description. The card clamps it visually and
      // offers the whole text on hover and keyboard focus, so a long note is never
      // silently cut to a 50-character fragment.
      comments: task.description.replace(/<[^>]*>?/g, '').trim(),
    })),
  }
})

const handleAddNewItem = async (payload: AddNewKanbanItem) => {
  const task = await store.addTask({
    title: payload.itemTitle,
    boardId: String(payload.boardId),
    description: '',
    priority: 'medium',
    start_date: new Date().toISOString().substring(0, 10),
    due_date: new Date().toISOString().substring(0, 10),
  })

  if (task) {
    store.selectTask(task.id)

    // Wait until the new card has been rendered before asking the Kanban
    // component to open its own editor drawer for it.
    await nextTick()
    editorItemId.value = task.id
  }
}

// Cards open the read-only view. Editing is an explicit action from the card menu.
const handleViewItem = (payload: EditKanbanItem) => {
  const task = store.tasks.find(candidate => candidate.id === String(payload.item.id))
  if (!task)
    return

  store.selectTask(task.id)
  isViewDialogOpen.value = true
}

// Opens the Task Comments Drawer when clicking comment icon
const handleCommentsClick = (payload: EditKanbanItem) => {
  store.selectTask(String(payload.item.id))
  isCommentsDrawerOpen.value = true
}

const handleAttachmentsClick = (payload: EditKanbanItem) => {
  attachmentTask.value = store.tasks.find(task => task.id === String(payload.item.id)) ?? null
  isAttachmentsDialogOpen.value = true
}

const isImage = (attachment: TaskAttachment) => attachment.mimeType?.startsWith('image/') ?? false

const downloadAttachment = async (attachment: TaskAttachment) => {
  if (!attachmentTask.value)
    return
  const response = await TaskApi.downloadAttachment(attachmentTask.value.id, attachment.id)
  const url = URL.createObjectURL(response.data)
  const link = document.createElement('a')

  link.href = url
  link.download = attachment.name
  link.click()
  URL.revokeObjectURL(url)
}

const clearImagePreviews = () => {
  Object.values(imagePreviewUrls.value).forEach(URL.revokeObjectURL)
  imagePreviewUrls.value = {}
}

const loadImagePreviews = async () => {
  if (!attachmentTask.value)
    return

  const images = attachmentTask.value.attachments.filter(isImage)

  const previews = await Promise.all(images.map(async attachment => {
    try {
      const response = await TaskApi.downloadAttachment(attachmentTask.value!.id, attachment.id)

      return [attachment.id, URL.createObjectURL(response.data)] as const
    }
    catch {
      return null
    }
  }))

  imagePreviewUrls.value = Object.fromEntries(previews.filter((preview): preview is readonly [string, string] => preview !== null))
}

watch(isAttachmentsDialogOpen, async isOpen => {
  imageViewer?.destroy()
  imageViewer = null
  clearImagePreviews()
  if (!isOpen)
    return
  await loadImagePreviews()
  await nextTick()
  if (attachmentImageContainer.value) {
    const { default: Viewer } = await import('viewerjs')

    imageViewer = new Viewer(attachmentImageContainer.value, {
      navbar: true,
      title: false,
      toolbar: true,
      tooltip: true,
      movable: true,
      zoomable: true,
      rotatable: true,
      zIndex: 3000,
    })
  }
})

onBeforeUnmount(() => {
  imageViewer?.destroy()
  clearImagePreviews()
})

const pendingDeleteTitle = computed(() => {
  const id = pendingDeleteId.value

  return id ? store.tasks.find(task => task.id === id)?.title ?? '' : ''
})

const handleDeleteItem = (payload: EditKanbanItem) => {
  pendingDeleteId.value = String(payload.item.id)
  isDeleteDialogOpen.value = true
}

const cancelTaskDelete = () => {
  isDeleteDialogOpen.value = false
  pendingDeleteId.value = null
}

const confirmTaskDelete = async () => {
  if (!pendingDeleteId.value)
    return

  await store.deleteTask(pendingDeleteId.value)
  isViewDialogOpen.value = false
  cancelTaskDelete()
}
</script>

<template>
  <div class="kanban-wrapper h-100 position-relative">
    <VRow>
      <VCol cols="12">
        <VCard
          elevation="0"
          class="border"
        >
          <VCardItem class="bg-surface pb-4">
            <template #title>
              <div class="d-flex align-center">
                <VIcon
                  icon="tabler-layout-kanban"
                  color="primary"
                  class="me-2"
                />
                <span class="text-h5 font-weight-bold">{{ $t('components.taskManager.kanbanview.taskBoard') }}</span>
              </div>
            </template>
          </VCardItem>

          <VCardText class="kanban-scroll-area bg-background">
            <VProgressLinear
              v-if="isLoading"
              indeterminate
              color="primary"
              class="position-absolute"
              style="z-index: 1;"
            />

            <KanbanBoard
              :kanban-data="vuexyKanbanData"
              :open-item-id="editorItemId"
              @add-new-item="handleAddNewItem"
              @view-item="handleViewItem"
              @edit-full-item="handleEditFullItem"
              @delete-item="handleDeleteItem"
              @comments-click="handleCommentsClick"
              @attachments-click="handleAttachmentsClick"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Read-only task view opened by clicking a card -->
    <TaskViewDialog v-model:is-open="isViewDialogOpen" />

    <!-- Full task editor (description, priority, dates, dependencies, completion) -->
    <TaskDetailDrawer v-model:is-open="isFullEditorOpen" />

    <!-- Dedicated Task Comments Sliding Drawer -->
    <TaskCommentsDrawer v-model:is-open="isCommentsDrawerOpen" />

    <!-- Delete confirmation for the card menu -->
    <VDialog
      v-model="isDeleteDialogOpen"
      max-width="440"
    >
      <VCard>
        <VCardTitle class="text-h6 pa-5 pb-2">
          {{ $t('common.messages.confirmDeletion') }}
        </VCardTitle>
        <VCardText class="pa-5 pt-2">
          {{ $t('common.confirmations.areYouSureYouWantToDeleteThisTaskThisActionCannotBeUndone') }}
          <div
            v-if="pendingDeleteTitle"
            class="mt-3 font-weight-medium"
          >
            “{{ pendingDeleteTitle }}”
          </div>
        </VCardText>
        <VCardActions class="pa-5 pt-0">
          <VSpacer />
          <VBtn
            variant="outlined"
            color="secondary"
            @click="cancelTaskDelete"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="error"
            @click="confirmTaskDelete"
          >
            {{ $t('common.actions.delete') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="isAttachmentsDialogOpen"
      max-width="760"
    >
      <VCard>
        <VCardItem class="border-b">
          <template #title>
            <span class="text-h6">{{ $t('components.taskManager.kanbanview.attachments') }}</span>
          </template>
          <template #subtitle>
            {{ attachmentTask?.title }}
          </template>
          <template #append>
            <VBtn
              icon="tabler-x"
              variant="text"
              :aria-label="$t('common.actions.close')"
              :title="$t('common.actions.close')"
              @click="isAttachmentsDialogOpen = false"
            />
          </template>
        </VCardItem>
        <VCardText
          v-if="attachmentTask"
          class="pa-5"
        >
          <div
            v-if="attachmentTask.attachments.length"
            ref="attachmentImageContainer"
            class="attachment-gallery"
          >
            <div
              v-for="attachment in attachmentTask.attachments"
              :key="attachment.id"
              class="attachment-entry border rounded pa-3"
            >
              <img
                v-if="isImage(attachment) && imagePreviewUrls[attachment.id]"
                :src="imagePreviewUrls[attachment.id]"
                :alt="attachment.name"
                class="attachment-preview rounded cursor-pointer"
              >
              <VProgressCircular
                v-else-if="isImage(attachment)"
                indeterminate
                size="32"
                width="3"
                color="primary"
              />
              <VIcon
                v-else
                icon="tabler-file"
                size="36"
                color="secondary"
              />
              <div class="flex-grow-1 min-width-0">
                <div class="text-body-1 text-truncate">
                  {{ attachment.name }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ $t('components.taskManager.kanbanview.taskFiles') }}
                </div>
              </div>
              <VBtn
                icon="tabler-download"
                variant="text"
                :aria-label="$t('components.taskManager.kanbanview.downloadAttachment')"
                :title="$t('components.taskManager.kanbanview.downloadAttachment')"
                @click="downloadAttachment(attachment)"
              />
            </div>
          </div>
          <VAlert
            v-else
            type="info"
            variant="tonal"
          >
            {{ $t('components.taskManager.kanbanview.noAttachments') }}
          </VAlert>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.kanban-scroll-area {
  background: rgb(var(--v-theme-background));
  min-height: 65vh;
  overflow-x: auto;
  position: relative;
  padding: 1.5rem;
}

.attachment-gallery {
  display: grid;
  gap: 0.75rem;
}

.attachment-entry {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.attachment-preview {
  width: 72px;
  height: 56px;
  object-fit: cover;
}

:global(.viewer-container) {
  z-index: 3000 !important;
}
</style>
