<script setup lang="ts">
import { useTaskStore } from '@/stores/useTaskStore'
import KanbanBoard from '@/pages/kanban/KanbanBoard.vue'
import TaskApi from '@/api/task'
import type { Task, TaskAttachment } from '@/api/kanban'
import type Viewer from 'viewerjs'
import 'viewerjs/dist/viewer.css'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import TaskCommentsDrawer from '../components/TaskCommentsDrawer.vue'
import TaskDetailDrawer from '../components/TaskDetailDrawer.vue'

interface AddNewKanbanItem { boardId: string | number; itemTitle: string }
interface EditKanbanItem { item: { id: string | number } }
interface KanbanState { boardId: string | number; ids: Array<string | number> }
interface RenameKanbanBoard { boardId: string | number; newName: string }

const store = useTaskStore()
const isDetailDrawerOpen = ref(false)
const isCommentsDrawerOpen = ref(false)
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
  } finally {
    isLoading.value = false
  }
})

// Transform Pinia state to Vuexy's expected KanbanData format
const vuexyKanbanData = computed(() => {
  return {
    boards: store.columns.map(col => ({
      id: col.id,
      title: col.title,
      itemsIds: store.tasks
        .filter(t => t.boardId === col.id)
        .sort((a, b) => a.order - b.order)
        .map(t => t.id)
    })),
    items: store.tasks.map(task => ({
      id: task.id,
      title: task.title,
      dueDate: task.dueDate,
      labels: task.tags.map(t => t.name),
      members: task.assignees.map(assignee => ({
        id: assignee.id,
        name: assignee.name,
        img: assignee.avatar,
      })),
      commentsCount: task.comments.length,
      attachmentsCount: task.attachments?.length ?? 0,
      progress: task.progress,
      image: '',
      comments: task.description.replace(/<[^>]*>?/gm, '').substring(0, 50) + '...'
    }))
  }
})

// Handle Vuexy Drag/Drop and Event Emissions
const handleUpdateItemsState = async (payload: KanbanState) => {
  // Persist in order. Sending every move at once lets requests overtake each
  // other and leaves the column order different from the UI.
  for (const [index, itemId] of payload.ids.entries()) {
    await store.moveTaskBoard(String(itemId), String(payload.boardId), index)
  }
}

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

// Opens the original Task Detail Drawer when clicking card
const handleEditItem = (payload: EditKanbanItem) => {
  store.selectTask(String(payload.item.id))
  isDetailDrawerOpen.value = true
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
  if (!attachmentTask.value) return
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
  if (!attachmentTask.value) return

  const images = attachmentTask.value.attachments.filter(isImage)
  const previews = await Promise.all(images.map(async attachment => {
    try {
      const response = await TaskApi.downloadAttachment(attachmentTask.value!.id, attachment.id)
      return [attachment.id, URL.createObjectURL(response.data)] as const
    } catch {
      return null
    }
  }))

  imagePreviewUrls.value = Object.fromEntries(previews.filter((preview): preview is readonly [string, string] => preview !== null))
}

watch(isAttachmentsDialogOpen, async isOpen => {
  imageViewer?.destroy()
  imageViewer = null
  clearImagePreviews()
  if (!isOpen) return
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

const handleDeleteItem = (payload: EditKanbanItem) => {
  store.deleteTask(String(payload.item.id))
}

const handleRenameBoard = (payload: RenameKanbanBoard) => {
  store.updateBoard(String(payload.boardId), payload.newName)
}

const handleAddNewBoard = (title: string) => {
  store.createBoard(title)
}

const handleDeleteBoard = (boardId: number) => {
  store.deleteBoard(String(boardId))
}

const handleUpdateBoardState = async (boardIds: number[]) => {
  await store.reorderBoards(boardIds.map(String))
}
</script>

<template>
  <div class="kanban-wrapper h-100 position-relative">
    <VRow>
      <VCol cols="12">
        <VCard elevation="0" class="border">
          <VCardItem class="bg-surface pb-4">
            <template #title>
              <div class="d-flex align-center">
                <VIcon icon="tabler-layout-kanban" color="primary" class="me-2" />
                <span class="text-h5 font-weight-bold">{{ $t('components.taskManager.kanbanview.projectBoard') }}</span>
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
              @edit-item="handleEditItem"
              @delete-item="handleDeleteItem"
              @update-items-state="handleUpdateItemsState"
              @rename-board="handleRenameBoard"
              @comments-click="handleCommentsClick"
              @attachments-click="handleAttachmentsClick"
              @add-new-board="handleAddNewBoard"
              @delete-board="handleDeleteBoard"
              @update-board-state="handleUpdateBoardState"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Original Task Detail Editor Drawer -->
    <TaskDetailDrawer v-model:is-open="isDetailDrawerOpen" />

    <!-- Dedicated Task Comments Sliding Drawer -->
    <TaskCommentsDrawer v-model:is-open="isCommentsDrawerOpen" />

    <VDialog v-model="isAttachmentsDialogOpen" max-width="760">
      <VCard>
        <VCardItem class="border-b">
          <template #title><span class="text-h6">{{ $t('components.taskManager.kanbanview.attachments') }}</span></template>
          <template #subtitle>{{ attachmentTask?.title }}</template>
          <template #append><VBtn icon="tabler-x" variant="text" :aria-label="$t('common.actions.close')" :title="$t('common.actions.close')" @click="isAttachmentsDialogOpen = false" /></template>
        </VCardItem>
        <VCardText v-if="attachmentTask" class="pa-5">
          <div v-if="attachmentTask.attachments.length" ref="attachmentImageContainer" class="attachment-gallery">
            <div v-for="attachment in attachmentTask.attachments" :key="attachment.id" class="attachment-entry border rounded pa-3">
              <img v-if="isImage(attachment) && imagePreviewUrls[attachment.id]" :src="imagePreviewUrls[attachment.id]" :alt="attachment.name" class="attachment-preview rounded cursor-pointer" />
              <VProgressCircular v-else-if="isImage(attachment)" indeterminate size="32" width="3" color="primary" />
              <VIcon v-else icon="tabler-file" size="36" color="secondary" />
              <div class="flex-grow-1 min-width-0">
                <div class="text-body-1 text-truncate">{{ attachment.name }}</div>
                <div class="text-caption text-medium-emphasis">{{ attachment.section === 'input' ? $t('components.taskManager.kanbanview.taskFiles') : $t('components.taskManager.kanbanview.resultFiles') }}</div>
              </div>
              <VBtn icon="tabler-download" variant="text" :aria-label="$t('components.taskManager.kanbanview.downloadAttachment')" :title="$t('components.taskManager.kanbanview.downloadAttachment')" @click="downloadAttachment(attachment)" />
            </div>
          </div>
          <VAlert v-else type="info" variant="tonal">{{ $t('components.taskManager.kanbanview.noAttachments') }}</VAlert>
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
