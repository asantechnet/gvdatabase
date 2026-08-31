<script setup lang="ts">
import { useTaskStore } from '@/stores/useTaskStore'
import { canAccess } from '@/utils/authorization'
import { useCookie } from '@core/composable/useCookie'
import TaskApi from '@/api/task'
import { apiErrorMessage } from '@/utils/apiErrors'
import type { Subtask, Task, TaskAttachment } from '@/api/kanban'
import type { TaskPriority } from '@/types/taskManager'
import { computed, ref, watch } from 'vue'

const store = useTaskStore()
const userData = useCookie<{ id?: string | number } | null>('userData')
const newTaskTitle = ref('')
const newTaskCategory = ref('General')
const newTaskPriority = ref<'low' | 'medium' | 'high' | 'urgent'>('medium')

const priorityColorMap: Record<'low' | 'medium' | 'high' | 'urgent', string> = {
  low: 'success',
  medium: 'info',
  high: 'warning',
  urgent: 'error',
}

const getBoardTitle = (boardId: string) => {
  return store.boards.find(board => board.id === boardId)?.title ?? 'Unknown board'
}

const getSubtaskProgress = (task: Task) => {
  if (task.subtasks.length === 0) return 0

  const completedCount = task.subtasks.filter(subtask => subtask.isCompleted).length
  return Math.round((completedCount / task.subtasks.length) * 100)
}

const canEditSubtask = (userId: string) => canAccess('edit_all_subtasks')
  || (canAccess('edit_own_subtasks') && userId === String(userData.value?.id ?? ''))

const canDeleteSubtask = (userId: string) => canAccess('delete_all_subtasks')
  || (canAccess('delete_own_subtasks') && userId === String(userData.value?.id ?? ''))
const { t } = useI18n()
const editingTodo = ref<Subtask | null>(null)
const editTodoForm = ref({ title: '', category: '', priority: 'medium' as TaskPriority })
const resultsContent = ref('')
const taskAttachmentFile = ref<File | null>(null)
const resultFile = ref<File | null>(null)
const isSavingResults = ref(false)
const isUploadingResult = ref(false)
const attachmentError = ref('')
const canEditTask = () => canAccess('edit_assigned_tasks')

watch(() => store.selectedTask, task => {
  resultsContent.value = task?.results ?? ''
  taskAttachmentFile.value = null
  resultFile.value = null
  attachmentError.value = ''
}, { immediate: true })

const taskAttachments = computed(() => store.selectedTask?.attachments.filter(attachment => attachment.section === 'input') ?? [])
const resultAttachments = computed(() => store.selectedTask?.attachments.filter(attachment => attachment.section === 'results') ?? [])

const saveResults = async () => {
  if (!store.selectedTaskId) return
  isSavingResults.value = true
  try {
    await store.updateTask(store.selectedTaskId, { results: resultsContent.value })
  } finally {
    isSavingResults.value = false
  }
}

const uploadAttachment = async (section: 'input' | 'results') => {
  const file = section === 'input' ? taskAttachmentFile.value : resultFile.value
  if (!store.selectedTask || !file) return
  attachmentError.value = ''
  isUploadingResult.value = true
  try {
    const response = await TaskApi.uploadAttachment(store.selectedTask.id, file, section)
    store.selectedTask.attachments.push(response.data.data as TaskAttachment)
    if (section === 'input') taskAttachmentFile.value = null
    else resultFile.value = null
  } catch (error: unknown) {
    attachmentError.value = apiErrorMessage(error, t('components.taskManager.todomatrixview.attachmentUploadFailed'))
  } finally {
    isUploadingResult.value = false
  }
}

const uploadResultImage = async (file: File) => {
  if (!store.selectedTask) throw new Error(t('components.taskManager.todomatrixview.noTaskSelected'))
  const response = await TaskApi.uploadAttachment(store.selectedTask.id, file, 'results')
  const attachment = response.data.data as TaskAttachment
  store.selectedTask.attachments.push(attachment)
  return attachment.url
}

const deleteAttachment = async (attachment: TaskAttachment) => {
  if (!store.selectedTask) return
  await TaskApi.deleteAttachment(store.selectedTask.id, attachment.id)
  store.selectedTask.attachments = store.selectedTask.attachments.filter(item => item.id !== attachment.id)
}

const downloadAttachment = async (attachment: TaskAttachment) => {
  if (!store.selectedTask) return
  const response = await TaskApi.downloadAttachment(store.selectedTask.id, attachment.id)
  const url = URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = url
  link.download = attachment.name
  link.click()
  URL.revokeObjectURL(url)
}

const formatFileSize = (size: number) => size < 1024 * 1024
  ? `${Math.ceil(size / 1024)} KB`
  : `${(size / (1024 * 1024)).toFixed(1)} MB`

const openTodoEditor = (todo: Subtask) => {
  if (!todo.canEdit) return
  editingTodo.value = todo
  editTodoForm.value = { title: todo.title, category: todo.category, priority: todo.priority }
}

const saveTodo = async () => {
  if (!store.selectedTask || !editingTodo.value || !editTodoForm.value.title.trim()) return
  await store.updateSubTask(store.selectedTask.id, editingTodo.value.id, {
    ...editTodoForm.value,
    title: editTodoForm.value.title.trim(),
  })
  editingTodo.value = null
}

const handleAddTodo = () => {
  if (newTaskTitle.value.trim() && store.selectedTaskId) {
    store.addSubTask(
      store.selectedTaskId,
      newTaskTitle.value.trim(),
      newTaskCategory.value,
      newTaskPriority.value,
    )
    newTaskTitle.value = ''
    newTaskCategory.value = 'General'
    newTaskPriority.value = 'medium'
  }
}
</script>

<template>
  <VRow>
    <!-- Left Column: Master Task List -->
    <VCol cols="12" md="4">
      <VCard :title="$t('components.taskManager.todomatrixview.megaTasks')">
        <VCardText class="pa-0">
          <VList lines="two" select-strategy="single-independent">
            <VListItem v-for="task in store.tasks" :key="task.id" :value="task.id"
              :active="store.selectedTaskId === task.id" @click="store.selectTask(task.id)" class="border-b">
              <template #prepend>
                <VAvatar :color="getSubtaskProgress(task) === 100 ? 'success' : 'primary'" variant="tonal" class="me-3">
                  <VIcon :icon="getSubtaskProgress(task) === 100 ? 'tabler-check' : 'tabler-list-details'" />
                </VAvatar>
              </template>

              <VListItemTitle class="font-weight-medium mb-1">
                {{ task.title }}
              </VListItemTitle>

              <VListItemSubtitle>
                <VChip size="x-small" color="secondary" variant="tonal" class="mb-2">
                  <VIcon icon="tabler-layout-kanban" size="13" class="me-1" />
                  {{ getBoardTitle(task.boardId) }}
                </VChip>
                <div class="d-flex align-center gap-2 mt-1">
                  <VProgressLinear :model-value="getSubtaskProgress(task)" :color="getSubtaskProgress(task) === 100 ? 'success' : 'primary'"
                    height="6" rounded class="flex-grow-1" />
                  <span class="text-caption font-weight-bold">{{ getSubtaskProgress(task) }}%</span>
                </div>
              </VListItemSubtitle>
            </VListItem>
          </VList>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Right Column: Granular Todos & Task Details -->
    <VCol cols="12" md="8">
      <VCard v-if="store.selectedTask" elevation="0" class="border">
        <VCardItem class="bg-light-primary rounded-t pa-5">
          <template #title>
            <div class="text-h5 font-weight-bold mb-1">
              {{ store.selectedTask.title }}
            </div>
            <TaskCompletionChip :progress="store.selectedTask.progress" />
          </template>
          <template #subtitle>
            <div class="d-flex align-center flex-wrap gap-2 mt-2">
              <VChip v-for="tag in store.selectedTask.tags" :key="tag.id" :color="tag.color" size="small"
                variant="elevated">
                {{ tag.name }}
              </VChip>
              <span class="text-caption text-medium-emphasis ms-2">
                {{ $t('components.taskManager.todos.dueDate', { date: formatLocalizedDate(`${store.selectedTask.dueDate}T00:00:00`) }) }}
              </span>
            </div>
          </template>
        </VCardItem>

        <VCardText class="pa-5 border-b">
          <div class="text-subtitle-1 font-weight-bold mb-3">{{ $t('components.taskManager.todomatrixview.taskContent') }}</div>
          <div v-if="store.selectedTask.description" class="html-content text-body-1 text-medium-emphasis" v-html="store.selectedTask.description" />
          <div v-else class="text-body-2 text-disabled">{{ $t('components.taskManager.todomatrixview.noTaskContent') }}</div>
          <div v-if="canEditTask()" class="d-flex flex-column flex-sm-row align-sm-center gap-2 mt-3">
            <VFileInput v-model="taskAttachmentFile" :label="$t('components.taskManager.todomatrixview.attachTaskFile')" density="compact" hide-details prepend-icon="tabler-paperclip" :disabled="isUploadingResult" />
            <VBtn color="secondary" :disabled="!taskAttachmentFile" :loading="isUploadingResult" @click="uploadAttachment('input')">{{ $t('components.taskManager.todomatrixview.attach') }}</VBtn>
          </div>
          <div v-if="taskAttachments.length" class="text-caption text-medium-emphasis mt-3 mb-1">{{ $t('components.taskManager.todomatrixview.taskAttachments') }}</div>
          <VList v-if="taskAttachments.length" class="border rounded pa-0">
            <VListItem v-for="attachment in taskAttachments" :key="attachment.id">
              <template #prepend><VIcon icon="tabler-paperclip" /></template>
              <VListItemTitle>{{ attachment.name }}</VListItemTitle>
              <VListItemSubtitle>{{ formatFileSize(attachment.size) }}</VListItemSubtitle>
              <template #append>
                <VBtn icon="tabler-download" variant="text" size="small" :aria-label="$t('components.taskManager.todomatrixview.downloadAttachment')" :title="$t('components.taskManager.todomatrixview.downloadAttachment')" @click="downloadAttachment(attachment)" />
                <VBtn v-if="canEditTask()" icon="tabler-trash" variant="text" color="error" size="small" :aria-label="$t('components.taskManager.todomatrixview.removeAttachment')" :title="$t('components.taskManager.todomatrixview.removeAttachment')" @click="deleteAttachment(attachment)" />
              </template>
            </VListItem>
          </VList>
        </VCardText>

        <VCardText class="pa-5">
          <div class="d-flex align-center justify-space-between mb-4">
            <h6 class="text-h6 font-weight-medium ma-0">Checklist</h6>
            <VChip color="primary" size="small" variant="tonal">
              {{ $t('components.taskManager.todomatrixview.completedProgress', {
                completed: store.selectedTask.subtasks.filter(t => t.isCompleted).length,
                total: store.selectedTask.subtasks.length,
              }) }}
            </VChip>
          </div>

          <!-- Add New Todo -->
          <VForm v-if="canAccess('create_subtasks_on_assigned_tasks')" @submit.prevent="handleAddTodo" class="mb-5">
            <div class="d-flex flex-column gap-3">
              <VTextField
                v-model="newTaskTitle"
                :placeholder="$t('components.taskManager.todomatrixview.addANewSubTask')"
                variant="outlined"
                density="comfortable"
                hide-details
                @keydown.enter.prevent="handleAddTodo"
              >
                <template #append-inner>
                  <VBtn
                    icon="tabler-plus"
                    variant="text"
                    color="primary"
                    size="small"
                    type="submit"
                  />
                </template>
              </VTextField>
            <div class="d-flex flex-column flex-sm-row gap-3 mt-3">
              <VTextField v-model="newTaskCategory" :label="$t('common.fields.category')" variant="outlined" density="comfortable"
                hide-details />
              <VSelect v-model="newTaskPriority" :items="['low', 'medium', 'high', 'urgent']" :item-title="translateBackendValue" :label="$t('common.fields.priority')"
                variant="outlined" density="comfortable" hide-details />
            </div>
            </div>
          </VForm>

          <!-- Todos List -->
          <VList class="pa-0">
            <TransitionGroup name="list-transition">
              <VListItem v-for="todo in store.selectedTask.subtasks" :key="todo.id" class="border rounded mb-2 pa-2"
                :class="{ 'bg-light-success bg-opacity-10': todo.isCompleted }">
                <template #prepend>
                  <VCheckboxBtn :model-value="todo.isCompleted" color="success" :disabled="!canEditSubtask(todo.userId)"
                    @update:model-value="store.toggleSubTask(store.selectedTask.id, todo.id)" />
                </template>

                <div class="d-flex flex-column flex-grow-1">
                  <VListItemTitle class="font-weight-medium"
                    :class="{ 'text-decoration-line-through text-disabled': todo.isCompleted }">
                    {{ todo.title }}
                  </VListItemTitle>
                  <div class="d-flex flex-wrap align-center gap-2 mt-1">
                    <VChip size="x-small" variant="tonal" color="secondary">
                      {{ translateBackendValue(todo.category, 'backend.values') }}
                    </VChip>
                    <VChip size="x-small" variant="tonal" :color="priorityColorMap[todo.priority]">
                      {{ translateBackendValue(todo.priority) }}
                    </VChip>
                  </div>
                </div>

                <template #append>
                  <VTooltip v-if="canEditSubtask(todo.userId)" :text="todo.editWindowExpired && !todo.canEdit ? t('common.messages.editWindowExpired') : t('components.taskManager.todos.editActionItem')">
                    <template #activator="{ props: tooltipProps }">
                      <VBtn v-bind="tooltipProps" icon="tabler-pencil" variant="text" size="small" :disabled="!todo.canEdit" @click="openTodoEditor(todo)" />
                    </template>
                  </VTooltip>
                  <VBtn v-if="canDeleteSubtask(todo.userId)" icon="tabler-trash" variant="text" color="error" size="small"
                    @click="store.deleteSubTask(store.selectedTask.id, todo.id)" />
                </template>
              </VListItem>
            </TransitionGroup>
          </VList>
        </VCardText>

        <VCardText class="pa-5 border-t">
          <div class="d-flex align-center justify-space-between mb-3">
            <div class="text-subtitle-1 font-weight-bold">{{ $t('components.taskManager.todomatrixview.results') }}</div>
            <VBtn v-if="canEditTask()" size="small" color="primary" :loading="isSavingResults" @click="saveResults">{{ $t('components.taskManager.todomatrixview.saveResults') }}</VBtn>
          </div>
          <TiptapEditor v-if="canEditTask()" v-model="resultsContent" :image-upload="uploadResultImage" :placeholder="$t('components.taskManager.todomatrixview.recordTaskResults')" class="border rounded mb-4" />
          <div v-else-if="store.selectedTask.results" class="html-content text-body-1 text-medium-emphasis mb-4" v-html="store.selectedTask.results"></div>
          <div v-else class="text-body-2 text-disabled mb-4">{{ $t('components.taskManager.todomatrixview.noResultsRecorded') }}</div>

          <VAlert v-if="attachmentError" type="error" density="compact" class="mb-3">{{ attachmentError }}</VAlert>
          <div v-if="canEditTask()" class="d-flex flex-column flex-sm-row align-sm-center gap-2 mb-3">
            <VFileInput v-model="resultFile" :label="$t('components.taskManager.todomatrixview.attachResultFile')" density="compact" hide-details prepend-icon="tabler-paperclip" :disabled="isUploadingResult" />
            <VBtn color="secondary" :disabled="!resultFile" :loading="isUploadingResult" @click="uploadAttachment('results')">{{ $t('components.taskManager.todomatrixview.attach') }}</VBtn>
          </div>
          <div v-if="resultAttachments.length" class="text-caption text-medium-emphasis mb-1">{{ $t('components.taskManager.todomatrixview.resultAttachments') }}</div>
          <VList v-if="resultAttachments.length" class="border rounded pa-0">
            <VListItem v-for="attachment in resultAttachments" :key="attachment.id">
              <template #prepend><VIcon icon="tabler-paperclip" /></template>
              <VListItemTitle>{{ attachment.name }}</VListItemTitle>
              <VListItemSubtitle>{{ formatFileSize(attachment.size) }}</VListItemSubtitle>
              <template #append>
                <VBtn icon="tabler-download" variant="text" size="small" :aria-label="$t('components.taskManager.todomatrixview.downloadAttachment')" :title="$t('components.taskManager.todomatrixview.downloadAttachment')" @click="downloadAttachment(attachment)" />
                <VBtn v-if="canEditTask()" icon="tabler-trash" variant="text" color="error" size="small" :aria-label="$t('components.taskManager.todomatrixview.removeAttachment')" :title="$t('components.taskManager.todomatrixview.removeAttachment')" @click="deleteAttachment(attachment)" />
              </template>
            </VListItem>
          </VList>
        </VCardText>
      </VCard>

      <!-- Empty State -->
      <VCard v-else class="h-100 d-flex flex-column align-center justify-center pa-10 text-center" variant="outlined"
        style="border-style: dashed;">
        <VIcon icon="tabler-hand-click" size="64" color="disabled" class="mb-4" />
        <h5 class="text-h5 font-weight-medium text-medium-emphasis mb-2">{{ $t('components.taskManager.todomatrixview.noTaskSelected') }}</h5>
        <p class="text-body-2 text-disabled">{{ $t('components.taskManager.todomatrixview.selectAMegaTaskFromTheListToManageItsActionItems') }}</p>
      </VCard>

      <VDialog :model-value="Boolean(editingTodo)" max-width="520" @update:model-value="value => { if (!value) editingTodo = null }">
        <VCard :title="$t('components.taskManager.todomatrixview.editActionItem')">
          <VCardText>
            <VTextField v-model="editTodoForm.title" :label="$t('common.fields.title')" class="mb-3" />
            <VTextField v-model="editTodoForm.category" :label="$t('common.fields.category')" class="mb-3" />
            <VSelect v-model="editTodoForm.priority" :items="['low', 'medium', 'high', 'urgent']" :item-title="translateBackendValue" :label="$t('common.fields.priority')" />
          </VCardText>
          <VCardActions><VSpacer /><VBtn variant="tonal" @click="editingTodo = null">{{ $t('common.actions.cancel') }}</VBtn><VBtn :disabled="!editTodoForm.title.trim()" @click="saveTodo">{{ $t('common.actions.save') }}</VBtn></VCardActions>
        </VCard>
      </VDialog>
    </VCol>
  </VRow>
</template>

<style scoped>
/* Keep rich-content images contained within task content. */
.html-content :deep(img) {
  max-width: 100%;
  border-radius: 8px;
}

.list-transition-enter-active,
.list-transition-leave-active {
  transition: all 0.3s ease;
}

.list-transition-enter-from,
.list-transition-leave-to {
  opacity: 0;
  transform: translateX(-20px);
}
</style>
