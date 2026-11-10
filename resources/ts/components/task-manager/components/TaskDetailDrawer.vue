<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { VForm } from 'vuetify/components/VForm'
import { useTaskStore } from '@/stores/useTaskStore'
import { canAccess, canManagePersonalTasks } from '@/utils/authorization'
import type { TaskPriority } from '@/types/taskManager'
import TaskBonusDialog from './TaskBonusDialog.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ConfirmDialog from '@/components/dialogs/ConfirmDialog.vue'

const props = defineProps<{
  isOpen: boolean
}>()

const emit = defineEmits<{
  (e: 'update:isOpen', value: boolean): void
}>()

const store = useTaskStore()
const refEditTaskForm = ref<VForm>()
const isDeleteConfirmOpen = ref(false)
const selectedBoard = computed(() => store.boards.find(board => board.id === store.selectedTask?.boardId))
const canManagePersonal = computed(() => canManagePersonalTasks(selectedBoard.value?.is_personal, selectedBoard.value?.is_owned))
const canEditTask = () => canAccess('edit_assigned_tasks') || canManagePersonal.value
const canDeleteTask = () => canAccess('delete_assigned_tasks') || canManagePersonal.value
const canManageDependencies = () => canAccess('manage_task_dependencies')
const canReviewBonus = () => canAccess('edit_all_tasks')

// Priority options
const priorityOptions = computed(() => [
  { title: translate('components.taskManager.taskdetaildrawer.low'), value: 'low' },
  { title: translate('components.taskManager.taskdetaildrawer.medium'), value: 'medium' },
  { title: translate('components.taskManager.taskdetaildrawer.high'), value: 'high' },
  { title: translate('components.taskManager.taskdetaildrawer.urgent'), value: 'urgent' },
])

// Local task data
const localTask = ref({
  title: '',
  description: '',
  priority: 'medium' as TaskPriority,
  startDate: '',
  dueDate: '',
  dependencies: [] as string[],
  progress: 0,
})


const dependencyOptions = computed(() => {
  return store.tasks
    .filter(task => task.id !== store.selectedTaskId)
    .map(task => ({
      title: task.title,
      value: task.id,
    }))
})



// Watch for changes in selected task
watch(() => store.selectedTask, async newTask => {
  if (newTask) {
    localTask.value = {
      title: newTask.title,
      description: newTask.description ?? '',
      priority: newTask.priority,
      startDate: newTask.startDate,
      dueDate: newTask.dueDate,
      dependencies: [...newTask.dependencies],
      progress: newTask.progress,
    }

  }
}, { immediate: true })

const handleClose = () => {
  emit('update:isOpen', false)
}

const updateTask = async () => {
  if (!store.selectedTaskId)
    return

  const { valid } = await refEditTaskForm.value?.validate() || { valid: false }
  if (!valid)
    return

  try {
    await store.updateTask(store.selectedTaskId, {
      title: localTask.value.title,
      description: localTask.value.description,
      priority: localTask.value.priority,
      start_date: localTask.value.startDate,
      due_date: localTask.value.dueDate,
      ...(canManageDependencies() ? { dependencies: localTask.value.dependencies } : {}),
      ...(store.selectedTask?.subtasks.length === 0 ? { progress: localTask.value.progress } : {}),
    })
    emit('update:isOpen', false)
  }
  catch (error) {
    console.error('Failed to update task:', error)
  }
}

const deleteTask = async () => {
  if (store.selectedTaskId) {
    try {
      await store.deleteTask(store.selectedTaskId)
      emit('update:isOpen', false)
    }
    catch (error) {
      console.error('Failed to delete task:', error)
    }
  }
}

const confirmDelete = () => {
  isDeleteConfirmOpen.value = true
}

const cancelDelete = () => {
  isDeleteConfirmOpen.value = false
}
</script>

<template>
  <VDialog
    :model-value="props.isOpen"
    max-width="1000"
    scrollable
    content-class="task-detail-dialog"
    @update:model-value="handleClose"
  >
    <VCard
      elevation="0"
      class="task-detail-card d-flex flex-column"
    >
      <VCardItem class="task-detail-dialog__header px-5 py-4">
        <template #title>
          <div class="d-flex align-center">
            <VIcon
              icon="tabler-edit"
              class="me-2"
            />
            <span class="text-h6 font-weight-bold">{{ $t('common.messages.editTask') }}</span>
            <TaskCompletionChip
              v-if="store.selectedTask"
              :progress="store.selectedTask.progress"
              class="ms-3"
            />
          </div>
        </template>
        <template #append>
          <TaskBonusDialog
            v-if="canReviewBonus() && store.selectedTask && store.selectedTask.progress === 100"
            :task="store.selectedTask"
          />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="error"
            size="small"
            @click="handleClose"
          />
        </template>
      </VCardItem>

      <PerfectScrollbar class="task-detail-scroll flex-grow-1">
        <VForm
          ref="refEditTaskForm"
          class="pa-5 pt-7"
          @submit.prevent="updateTask"
        >
          <VRow>
            <!-- Title -->
            <VCol cols="12" md="6">
              <ErpField
                v-model="localTask.title"
                :label="$t('common.fields.title')"
                :rules="[v => !!v || $t('components.taskManager.taskdetaildrawer.titleRequired')]"
                :disabled="!canEditTask()"
                required
              />
            </VCol>

            <VCol cols="12" md="6">
              <ErpField
                v-model="localTask.priority"
                kind="select"
                :items="priorityOptions"
                :label="$t('common.fields.priority')"
                item-title="title"
                item-value="value"
                :disabled="!canEditTask()"
              />
            </VCol>

            <!-- Dates -->
            <VCol
              cols="12"
              md="6"
            >
              <ErpField
                v-model="localTask.startDate"
                :label="$t('common.fields.startDate')"
                kind="date"
                :config="{ dateFormat: 'Y-m-d', static: false }"
                :disabled="!canEditTask()"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <ErpField
                v-model="localTask.dueDate"
                :label="$t('components.taskManager.taskdetaildrawer.dueDate')"
                kind="date"
                :config="{ dateFormat: 'Y-m-d', static: false }"
                :disabled="!canEditTask()"
              />
            </VCol>

            <VCol
              v-if="store.selectedTask?.subtasks.length === 0"
              cols="12"
            >
              <VSwitch
                :model-value="localTask.progress === 100"
                color="success"
                :label="$t('components.taskManager.taskdetaildrawer.completed')"
                :disabled="!canEditTask()"
                @update:model-value="localTask.progress = $event ? 100 : 0"
              />
            </VCol>

            <!-- Gantt relationships -->
            <VCol
              v-if="canManageDependencies()"
              cols="12"
              md="12"
            >
              <ErpField
                v-model="localTask.dependencies"
                kind="select"
                :items="dependencyOptions"
                item-title="title"
                item-value="value"
                :label="$t('components.taskManager.taskdetaildrawer.dependsOn')"
                multiple
                chips
                closable-chips
                :hint="$t('components.taskManager.taskdetaildrawer.selectTasksThatMustBeCompletedBeforeThisOne')"
                persistent-hint
                :disabled="!canEditTask()"
              />
            </VCol>


            <VCol cols="12">
              <div class="mb-1 d-flex align-center gap-2">
                <VIcon
                  icon="tabler-align-justified"
                  size="20"
                />
                <span class="text-subtitle-1 font-weight-bold">{{ $t('components.taskManager.taskdetaildrawer.taskContent') }}</span>
              </div>
              <ErpField
                v-model="localTask.description"
                kind="textarea"
                auto-grow
                rows="5"
                :placeholder="$t('components.taskManager.taskdetaildrawer.noTaskContent')"
                :disabled="!canEditTask()"
                hide-details
              />
            </VCol>
          </VRow>
        </VForm>
      </PerfectScrollbar>

      <VCardActions class="task-detail-actions task-detail-dialog__footer pa-5">
        <VBtn
          v-if="canDeleteTask()"
          color="error"
          variant="tonal"
          prepend-icon="tabler-trash"
          @click="confirmDelete"
        >
          {{ $t('common.actions.delete') }}
        </VBtn>

        <div class="task-detail-actions__primary">
          <VBtn
            class="task-detail-dialog__cancel"
            @click="handleClose"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            v-if="canEditTask()"
            color="primary"
            variant="flat"
            class="task-detail-dialog__save"
            @click="updateTask"
          >
            {{ $t('common.actions.save') }}
          </VBtn>
        </div>
      </VCardActions>
    </VCard>

    <ConfirmDialog v-model:is-dialog-visible="isDeleteConfirmOpen" tone="error" icon="tabler-trash" :confirmation-question="$t('common.messages.confirmDeletion')" :confirmation-message="$t('common.confirmations.areYouSureYouWantToDeleteThisTaskThisActionCannotBeUndone')" :confirm-text="$t('common.actions.delete')" :cancel-text="$t('common.actions.cancel')" :confirm-title="$t('common.actions.delete')" confirm-msg="" :cancel-title="$t('common.actions.cancel')" cancel-msg="" @confirm="confirmed => { if (confirmed) deleteTask(); else cancelDelete() }" />
  </VDialog>
</template>

<style scoped>
.task-detail-card {
  max-height: calc(100vh - 48px);
}

:global(.v-overlay:has(.task-detail-dialog) .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .3) !important; backdrop-filter: blur(7px); opacity: 1 !important; }
.task-detail-dialog__header, .task-detail-dialog__footer { background: color-mix(in srgb, rgb(var(--v-theme-surface)) 93%, rgb(var(--v-theme-on-surface)) 7%); }
.task-detail-dialog__header { border-block-end: 1px solid rgba(var(--v-border-color), .5); }
.task-detail-dialog__footer { border-block-start: 1px solid rgba(var(--v-border-color), .5) !important; }
.task-detail-dialog__cancel { border: 1px solid rgba(var(--v-theme-secondary), .5) !important; background: rgba(var(--v-theme-secondary), .13) !important; color: rgb(var(--v-theme-secondary)) !important; }
.task-detail-dialog__cancel:hover { background: rgb(var(--v-theme-secondary)) !important; color: rgb(var(--v-theme-on-secondary)) !important; }
.task-detail-dialog__save { color: rgb(var(--v-theme-on-primary)) !important; }

.task-detail-scroll {
  min-height: 0;
}

.task-detail-actions {
  display: flex;
  flex: 0 0 auto;
  flex-wrap: wrap;
  gap: 0.5rem;
  justify-content: space-between;
}

.task-detail-actions__primary {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-inline-start: auto;
}

@media (max-width: 420px) {
  .task-detail-actions {
    padding: 0.75rem !important;
  }

  .task-detail-actions > .v-btn,
  .task-detail-actions__primary,
  .task-detail-actions__primary > .v-btn {
    flex: 1 1 auto;
  }
}

.task-content-preview {
  min-height: 7.5rem;
}

.task-content-preview :deep(img) {
  max-width: 100%;
  border-radius: 0.5rem;
}
</style>
