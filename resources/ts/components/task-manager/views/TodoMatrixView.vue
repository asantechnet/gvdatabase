<script setup lang="ts">
import { useTaskStore } from '@/stores/useTaskStore'
import { canAccess } from '@/utils/authorization'
import { useCookie } from '@core/composable/useCookie'
import type { Subtask, Task } from '@/api/kanban'
import type { TaskPriority } from '@/types/taskManager'
import { ref } from 'vue'

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
                <VAvatar :color="getSubtaskProgress(task) === 100 ? 'success' : 'primary'" variant="tonal" class="mr-3">
                  <VIcon :icon="getSubtaskProgress(task) === 100 ? 'tabler-check' : 'tabler-list-details'" />
                </VAvatar>
              </template>

              <VListItemTitle class="font-weight-medium mb-1">
                {{ task.title }}
              </VListItemTitle>

              <VListItemSubtitle>
                <VChip size="x-small" color="secondary" variant="tonal" class="mb-2">
                  <VIcon icon="tabler-layout-kanban" size="13" class="mr-1" />
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
              <span class="text-caption text-medium-emphasis ml-2">
                {{ $t('components.taskManager.todos.dueDate', { date: formatLocalizedDate(`${store.selectedTask.dueDate}T00:00:00`) }) }}
              </span>
            </div>
          </template>
        </VCardItem>

        <!-- Outputs Quill Data strictly as raw HTML for contract templating capabilities -->
        <VCardText class="pa-5 border-b">
          <div class="text-subtitle-2 mb-2 font-weight-bold">{{ $t('components.taskManager.todomatrixview.descriptionNotes') }}</div>
          <div class="html-content text-body-1 text-medium-emphasis" v-html="store.selectedTask.description"></div>
        </VCardText>

        <VCardText class="pa-5">
          <div class="d-flex align-center justify-space-between mb-4">
            <h6 class="text-h6 font-weight-medium ma-0">{{ $t('components.taskManager.todomatrixview.actionItemsTodos') }}</h6>
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
/* Ensure Quill images fit nicely in the view */
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
