<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import TaskAssigneeApi, { type TaskAssigneeOption } from '@/api/taskAssignee'
import { useTaskStore } from '@/stores/useTaskStore'
import { canAccess, canManagePersonalTasks } from '@/utils/authorization'
import type { EditKanbanItem, KanbanItem } from '@/types/legacyKanban'
import KanbanTaskEditForm from '@/components/forms/KanbanTaskEditForm.vue'
import TaskBonusDialog from '@/components/task-manager/components/TaskBonusDialog.vue'

const props = withDefaults(defineProps<{ kanbanItem?: EditKanbanItem; isDrawerOpen: boolean }>(), { kanbanItem: () => ({ item: { title: '', dueDate: '', labels: [], members: [], id: 0, commentsCount: 0 }, boardId: 0, boardName: '' }) })
const emit = defineEmits<{ (e: 'update:isDrawerOpen', value: boolean): void; (e: 'update:kanbanItem', value: EditKanbanItem): void; (e: 'deleteKanbanItem', value: EditKanbanItem): void }>()
const store = useTaskStore()
const employees = ref<TaskAssigneeOption[]>([])
const isLoadingEmployees = ref(false)
const canAssignMembers = () => canAccess('assign_task_members')
const canManageTags = () => canAccess('manage_task_tags')
const canManageDependencies = () => canAccess('manage_task_dependencies')
const currentTask = computed(() => store.tasks.find(task => task.id === String(props.kanbanItem?.item.id)))
const currentBoard = computed(() => store.boards.find(board => String(board.id) === String(props.kanbanItem?.boardId)))
const canManagePersonal = computed(() => canManagePersonalTasks(currentBoard.value?.is_personal, currentBoard.value?.is_owned))
const canEditTask = () => canAccess('edit_assigned_tasks') || canManagePersonal.value
const canDeleteTask = () => canAccess('delete_assigned_tasks') || canManagePersonal.value
const canReviewBonus = computed(() => !!currentTask.value && currentTask.value.progress === 100 && canAccess('edit_all_tasks'))
const taskOptions = computed(() => store.tasks.filter(task => task.id !== String(props.kanbanItem?.item.id)).map(task => ({ title: task.title, value: task.id })))
const localKanbanItem = ref<KanbanItem & Record<string, any>>({ ...props.kanbanItem.item, labels: [], members: [], startDate: '', description: '', priority: 'medium', dependencies: [] })

const hydrate = () => {
  const task = currentTask.value
  if (!task)
    return
  localKanbanItem.value = { ...props.kanbanItem.item, title: task.title, dueDate: task.dueDate, startDate: task.startDate, description: task.description, priority: task.priority, dependencies: [...task.dependencies], labels: task.tags.map(tag => tag.name), members: task.assignees.map(user => employees.value.find(employee => String(employee.id) === user.id) ?? user) }
}
const fetchEmployees = async () => {
  if (!canAssignMembers()) return
  isLoadingEmployees.value = true
  try { employees.value = await TaskAssigneeApi.getAll(); hydrate() } finally { isLoadingEmployees.value = false }
}
watch(() => props.kanbanItem, hydrate, { deep: true })
const updateKanbanItem = async (updatedItem: typeof localKanbanItem.value) => {
  if (!props.kanbanItem || !currentTask.value) return
  const tagIds = updatedItem.labels.map((label: string) => store.tags.find(tag => tag.name === label)?.id).filter(Boolean) as string[]
  const userIds = updatedItem.members.map(member => String(member.id)).filter(Boolean)
  await store.updateTask(currentTask.value.id, { title: updatedItem.title, description: updatedItem.description, priority: updatedItem.priority, start_date: updatedItem.startDate, due_date: updatedItem.dueDate, ...(canManageTags() ? { tags: tagIds } : {}), ...(canAssignMembers() ? { assignees: userIds } : {}), ...(canManageDependencies() ? { dependencies: updatedItem.dependencies } : {}) })
  emit('update:kanbanItem', { item: updatedItem, boardId: props.kanbanItem.boardId, boardName: props.kanbanItem.boardName })
  emit('update:isDrawerOpen', false)
  await nextTick()
}
const deleteKanbanItem = () => { if (props.kanbanItem) { emit('deleteKanbanItem', props.kanbanItem); emit('update:isDrawerOpen', false) } }
onMounted(() => { void fetchEmployees(); if (canManageTags()) void store.fetchTags() })
</script>

<template>
  <VDialog :model-value="isDrawerOpen" max-width="900" scrollable content-class="kanban-task-form-dialog" @update:model-value="emit('update:isDrawerOpen', $event)">
    <VCard class="kanban-task-form-dialog__card">
      <VCardItem class="kanban-task-form-dialog__header px-5 py-4">
        <template #prepend><VAvatar color="primary" variant="tonal" size="38"><VIcon icon="tabler-edit" /></VAvatar></template>
        <VCardTitle>{{ $t('common.messages.editTask') }}</VCardTitle><VCardSubtitle>{{ kanbanItem?.boardName }}</VCardSubtitle>
        <template #append>
          <TaskBonusDialog v-if="canReviewBonus && currentTask" :task="currentTask" />
          <VBtn icon="tabler-x" variant="tonal" color="error" size="small" class="kanban-task-form-dialog__close" :aria-label="$t('common.actions.close')" @click="emit('update:isDrawerOpen', false)" />
        </template>
      </VCardItem>
      <VCardText class="kanban-task-form-dialog__body pa-5 pa-sm-6"><KanbanTaskEditForm v-if="localKanbanItem" form-id="edit-kanban-task-form" :item="localKanbanItem" :employees="employees" :tags="store.tags" :task-options="taskOptions" :loading-employees="isLoadingEmployees" :can-edit="canEditTask()" :can-assign="canAssignMembers()" :can-manage-tags="canManageTags()" :can-manage-dependencies="canManageDependencies()" @submit="updateKanbanItem" /></VCardText>
      <VCardActions class="kanban-task-form-dialog__footer px-5 py-4"><VBtn v-if="canDeleteTask()" color="error" variant="tonal" prepend-icon="tabler-trash" class="kanban-task-form-dialog__delete" @click="deleteKanbanItem">{{ $t('common.actions.delete') }}</VBtn><VSpacer /><VBtn variant="tonal" color="secondary" @click="emit('update:isDrawerOpen', false)">{{ $t('common.actions.cancel') }}</VBtn><VBtn v-if="canEditTask()" form="edit-kanban-task-form" type="submit" color="primary" variant="flat" class="kanban-task-form-dialog__primary-action" prepend-icon="tabler-check">{{ $t('common.actions.update') }}</VBtn></VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss">
.kanban-task-form-dialog { max-inline-size: 100vw; }
.kanban-task-form-dialog__card { display: flex; overflow: hidden; flex-direction: column; max-block-size: calc(100dvh - 2rem); }
.kanban-task-form-dialog__header, .kanban-task-form-dialog__footer { background: color-mix(in srgb, rgb(var(--v-theme-surface)) 93%, rgb(var(--v-theme-on-surface)) 7%); }
.kanban-task-form-dialog__header { border-block-end: 1px solid rgba(var(--v-border-color), .5); }
.kanban-task-form-dialog__footer { border-block-start: 1px solid rgba(var(--v-border-color), .5); }
.kanban-task-form-dialog__body { overflow-y: auto; scrollbar-width: none; }
.kanban-task-form-dialog__body::-webkit-scrollbar { display: none; }
.kanban-task-form-dialog__close { border: 1px solid rgba(var(--v-theme-error), .38) !important; border-radius: 50% !important; }
.kanban-task-form-dialog__close:hover { background: rgba(var(--v-theme-error), .18) !important; }
.kanban-task-form-dialog__delete:hover { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }
.kanban-task-form-dialog__primary-action { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
.kanban-task-form-dialog__primary-action .v-icon { color: rgb(var(--v-theme-on-primary)) !important; }
.v-overlay:has(.kanban-task-form-dialog) .v-overlay__scrim { background: rgba(var(--v-theme-on-surface), .28); backdrop-filter: blur(7px); opacity: 1 !important; }
</style>
