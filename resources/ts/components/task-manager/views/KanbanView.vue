<script setup lang="ts">
import type Viewer from 'viewerjs'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import 'viewerjs/dist/viewer.css'
import TaskCommentsDrawer from '../components/TaskCommentsDrawer.vue'
import { useTaskStore } from '@/stores/useTaskStore'
import KanbanBoard from '@/pages/kanban/KanbanBoard.vue'
import TaskApi from '@/api/task'
import TaskAssigneeApi, { type TaskAssigneeOption } from '@/api/taskAssignee'
import KanbanTaskEditForm from '@/components/forms/KanbanTaskEditForm.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import type { Task, TaskAttachment } from '@/api/kanban'
import { confirmActionWarning } from '@/composables/useActionWarning'
import { canAccess } from '@/utils/authorization'

interface AddNewKanbanItem { boardId: string | number; boardName: string; itemTitle: string }
interface EditKanbanItem { item: { id: string | number } }
interface KanbanTaskMove { taskId: string | number; boardId: string | number; order: number }

const store = useTaskStore()
const { t: translate } = useI18n()
const toast = useToast()
const isCommentsDrawerOpen = ref(false)
const isLoading = ref(true)
const isCreateDialogOpen = ref(false)
const creatingBoard = ref<{ id: string | number; title: string } | null>(null)
const createEmployees = ref<TaskAssigneeOption[]>([])
const isLoadingCreateEmployees = ref(false)
const isCreating = ref(false)
const today = new Date().toISOString().slice(0, 10)
const createTaskDraft = ref<Record<string, any>>({})
const isAttachmentsDialogOpen = ref(false)
const attachmentTask = ref<Task | null>(null)
const attachmentImageContainer = ref<HTMLElement>()
const imagePreviewUrls = ref<Record<string, string>>({})
const previewAttachment = ref<TaskAttachment | null>(null)
const previewUrl = ref('')
const isAttachmentPreviewOpen = ref(false)
let imageViewer: Viewer | null = null
let moveWarningOpen = false
let moveBatchTimer: number | undefined
const pendingMoves = new Map<string, KanbanTaskMove>()
const canAssignMembers = () => canAccess('assign_task_members')
const canManageTags = () => canAccess('manage_task_tags')
const canManageDependencies = () => canAccess('manage_task_dependencies')
const taskOptions = computed(() => store.tasks.map(task => ({ title: task.title, value: task.id })))

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
      itemsIds: store.tasks
        .filter(t => t.boardId === col.id)
        .sort((a, b) => b.order - a.order)
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

// Handle Vuexy Drag/Drop and Event Emissions
const commitPendingCardMove = async () => {
  if (moveWarningOpen)
    return

  const moves = [...pendingMoves.values()]

  pendingMoves.clear()
  if (!moves.length)
    return

  moveWarningOpen = true
  try {
    const confirmed = await confirmActionWarning({
      key: 'move-kanban-card',
      title: translate('common.actionWarnings.moveCardTitle'),
      message: translate('common.actionWarnings.moveCardMessage'),
      confirmText: translate('common.actionWarnings.moveCardConfirm'),
      cancelText: translate('common.actionWarnings.keepCard'),
      icon: 'tabler-arrows-move',
      tone: 'info',
    })

    if (!confirmed) {
      await store.fetchBoardData()

      return
    }

    // Cross-column drops report both affected columns. Persist the collected
    // states sequentially so requests cannot overtake one another.
    for (const move of moves) {
      if (!await store.moveTaskBoard(String(move.taskId), String(move.boardId), move.order)) {
        await store.fetchBoardData()
        toast.error(translate('components.taskManager.kanbanview.taskMoveFailed'))

        return
      }
    }
    toast.success(translate('components.taskManager.kanbanview.taskMoved'))
  }
  finally {
    moveWarningOpen = false
    if (pendingMoves.size)
      await store.fetchBoardData()
    pendingMoves.clear()
  }
}

const handleUpdateItemsState = (payload: KanbanTaskMove) => {
  pendingMoves.set(String(payload.taskId), payload)
  if (moveBatchTimer !== undefined)
    window.clearTimeout(moveBatchTimer)
  moveBatchTimer = window.setTimeout(() => {
    moveBatchTimer = undefined
    commitPendingCardMove().catch(() => {})
  }, 80)
}

const openCreateTaskDialog = async (payload: AddNewKanbanItem) => {
  creatingBoard.value = { id: payload.boardId, title: payload.boardName }
  createTaskDraft.value = { title: '', description: '', priority: 'medium', startDate: today, dueDate: today, labels: [], members: [], dependencies: [] }
  isCreateDialogOpen.value = true
  if (canManageTags())
    await store.fetchTags()
  if (canAssignMembers() && !createEmployees.value.length) {
    isLoadingCreateEmployees.value = true
    try { createEmployees.value = await TaskAssigneeApi.getAll() }
    finally { isLoadingCreateEmployees.value = false }
  }
}

const createTask = async (draft: Record<string, any>) => {
  if (!creatingBoard.value)
    return
  isCreating.value = true
  const tags = draft.labels.map((label: string) => Number(store.tags.find(tag => tag.name === label)?.id)).filter(Boolean)
  const assignees = draft.members.map((member: TaskAssigneeOption) => Number(member.id)).filter(Boolean)
  const task = await store.addTask({ boardId: String(creatingBoard.value.id), title: draft.title, description: draft.description, priority: draft.priority, start_date: draft.startDate, due_date: draft.dueDate, ...(canManageTags() ? { tags } : {}), ...(canAssignMembers() ? { assignees } : {}) })
  isCreating.value = false
  if (!task) {
    toast.error(translate('components.taskManager.kanbanview.taskCreateFailed'))

    return
  }
  if (canManageDependencies() && draft.dependencies.length)
    await store.updateTask(task.id, { dependencies: draft.dependencies })
  isCreateDialogOpen.value = false
  toast.success(translate('components.taskManager.kanbanview.taskCreated'))
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

const previewAttachmentFile = async (attachment: TaskAttachment) => {
  if (!attachmentTask.value)
    return
  if (previewUrl.value)
    URL.revokeObjectURL(previewUrl.value)
  const response = await TaskApi.downloadAttachment(attachmentTask.value.id, attachment.id)
  previewAttachment.value = attachment
  previewUrl.value = URL.createObjectURL(response.data)
  isAttachmentPreviewOpen.value = true
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
  if (!isOpen && previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = ''
  }
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
  if (moveBatchTimer !== undefined)
    window.clearTimeout(moveBatchTimer)
  imageViewer?.destroy()
  clearImagePreviews()
  if (previewUrl.value)
    URL.revokeObjectURL(previewUrl.value)
})

const handleDeleteItem = (payload: EditKanbanItem) => {
  store.deleteTask(String(payload.item.id))
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
            <div v-if="isLoading" class="kanban-skeleton" aria-busy="true">
              <VCard v-for="column in 3" :key="column" class="kanban-skeleton__column pa-4" variant="outlined">
                <V-SkeletonLoader type="heading" class="mb-5" />
                <V-SkeletonLoader v-for="card in 4" :key="card" type="card" class="mb-4" />
              </VCard>
            </div>
            <div v-else-if="!store.columns.length || !store.tasks.length" class="kanban-empty-state">
              <TableEmptyState icon="tabler-layout-kanban" :title="$t('common.messages.noRecordsFound')" :description="$t('components.taskManager.kanbanview.emptyDescription')" :min-height="360" />
              <VBtn v-if="store.columns.length && canAccess('create_tasks')" prepend-icon="tabler-plus" @click="openCreateTaskDialog({ boardId: store.columns[0].id, boardName: store.columns[0].title, itemTitle: '' })">{{ $t('common.messages.createTask') }}</VBtn>
            </div>
            <KanbanBoard
              v-else
              :kanban-data="vuexyKanbanData"
              @add-new-item="openCreateTaskDialog"
              @delete-item="handleDeleteItem"
              @update-items-state="handleUpdateItemsState"
              @comments-click="handleCommentsClick"
              @attachments-click="handleAttachmentsClick"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <TaskCommentsDrawer v-model:is-open="isCommentsDrawerOpen" />

    <VDialog
      v-model="isAttachmentsDialogOpen"
      max-width="760"
      content-class="kanban-attachments-dialog"
    >
      <VCard class="kanban-attachments-dialog__card">
        <VCardItem class="kanban-attachments-dialog__header border-b">
          <template #title>
            <span class="text-h6">{{ $t('components.taskManager.kanbanview.attachments') }}</span>
          </template>
          <template #subtitle>
            {{ attachmentTask?.title }}
          </template>
          <template #append>
            <VBtn
              icon="tabler-x"
              variant="tonal"
              color="error"
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
                icon="tabler-eye"
                variant="text"
                :aria-label="$t('common.actions.viewFullDetails')"
                :title="$t('common.actions.viewFullDetails')"
                @click="previewAttachmentFile(attachment)"
              />
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

    <VDialog v-model="isAttachmentPreviewOpen" max-width="900" content-class="kanban-attachment-preview-dialog" @update:model-value="open => { if (!open) previewAttachment = null }">
      <VCard class="kanban-attachment-preview-dialog__card">
        <VCardTitle class="kanban-attachment-preview-dialog__header px-5 py-4"><span class="text-truncate">{{ previewAttachment?.name }}</span><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" :aria-label="$t('common.actions.close')" @click="isAttachmentPreviewOpen = false" /></VCardTitle>
        <VCardText class="kanban-attachment-preview-dialog__body pa-4"><VImg v-if="previewAttachment && isImage(previewAttachment)" :src="previewUrl" :alt="previewAttachment.name" contain /><iframe v-else :src="previewUrl" :title="previewAttachment?.name" /></VCardText>
      </VCard>
    </VDialog>

    <VDialog v-model="isCreateDialogOpen" max-width="900" scrollable content-class="kanban-task-form-dialog">
      <VCard class="kanban-task-form-dialog__card">
        <VCardItem class="kanban-task-form-dialog__header px-5 py-4">
          <template #prepend><VAvatar color="primary" variant="tonal" size="38"><VIcon icon="tabler-plus" /></VAvatar></template>
          <VCardTitle>{{ $t('common.messages.createTask') }}</VCardTitle>
          <VCardSubtitle>{{ creatingBoard?.title }}</VCardSubtitle>
          <template #append><VBtn icon="tabler-x" variant="tonal" color="error" size="small" class="kanban-task-form-dialog__close" :aria-label="$t('common.actions.close')" :disabled="isCreating" @click="isCreateDialogOpen = false" /></template>
        </VCardItem>
        <VCardText class="kanban-task-form-dialog__body pa-5 pa-sm-6"><KanbanTaskEditForm form-id="create-kanban-task-form" :item="createTaskDraft" :employees="createEmployees" :tags="store.tags" :task-options="taskOptions" :loading-employees="isLoadingCreateEmployees" :can-edit="true" :can-assign="canAssignMembers()" :can-manage-tags="canManageTags()" :can-manage-dependencies="canManageDependencies()" @submit="createTask" /></VCardText>
        <VCardActions class="kanban-task-form-dialog__footer px-5 py-4"><VSpacer /><VBtn variant="tonal" color="secondary" :disabled="isCreating" @click="isCreateDialogOpen = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn form="create-kanban-task-form" type="submit" color="primary" variant="flat" class="kanban-task-form-dialog__primary-action" prepend-icon="tabler-check" :loading="isCreating">{{ $t('common.messages.createTask') }}</VBtn></VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.kanban-scroll-area {
  background: rgb(var(--v-theme-background));
  min-height: 65vh;
  overflow: auto hidden;
  position: relative;
  padding: 1.5rem;
}

.kanban-scroll-area::-webkit-scrollbar,
.kanban-skeleton::-webkit-scrollbar { display: none; }

.kanban-skeleton { display: flex; gap: 1.5rem; min-inline-size: min-content; overflow-x: auto; scrollbar-width: none; }
.kanban-skeleton__column { flex: 0 0 16.875rem; inline-size: 16.875rem; background: rgb(var(--v-theme-surface)); }
.kanban-empty-state { display: grid; justify-items: center; min-block-size: 420px; }

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

:global(.v-overlay:has(.kanban-attachments-dialog) .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .24); backdrop-filter: blur(5px); opacity: 1 !important; }
:global(.kanban-attachments-dialog__card) { overflow: hidden; }
.kanban-attachments-dialog__header { background: rgba(var(--v-theme-on-surface), .025); }

:global(.viewer-container) {
  z-index: 3000 !important;
}

:global(.v-overlay:has(.kanban-attachment-preview-dialog) .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .28); backdrop-filter: blur(5px); opacity: 1 !important; }
:global(.kanban-attachment-preview-dialog__card) { overflow: hidden; }
.kanban-attachment-preview-dialog__header { display: flex; align-items: center; background: rgba(var(--v-theme-on-surface), .025); }
.kanban-attachment-preview-dialog__body { display: grid; min-block-size: min(60vh, 36rem); place-items: center; background: rgba(var(--v-theme-on-surface), .025); }
.kanban-attachment-preview-dialog__body :deep(.v-img), .kanban-attachment-preview-dialog__body iframe { inline-size: 100%; block-size: min(60vh, 36rem); border: 0; }
.kanban-task-form-dialog__primary-action { background-color: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
.kanban-task-form-dialog__primary-action :deep(.v-icon) { color: rgb(var(--v-theme-on-primary)) !important; }
</style>
