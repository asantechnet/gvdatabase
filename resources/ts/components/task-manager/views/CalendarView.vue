<script setup lang="ts">
import { useTaskStore } from '@/stores/useTaskStore'
import { canAccess } from '@/utils/authorization'
import type { Task } from '@/types/taskManager'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import listPlugin from '@fullcalendar/list'
import timeGridPlugin from '@fullcalendar/timegrid'
import FullCalendar from '@fullcalendar/vue3'
import { computed, ref } from 'vue'

const store = useTaskStore()

// Modal Controls
const isDetailModalOpen = ref(false)
const isDeleteConfirmOpen = ref(false)
const selectedTask = ref<Task | null>(null)

// Map priorities to Vuexy badge colors
const priorityColorMap: Record<string, string> = {
  low: 'success',
  medium: 'info',
  high: 'warning',
  urgent: 'error'
}

const formatDate = (date: Date) => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

const addDays = (date: string, days: number) => {
  const [year, month, day] = date.split('-').map(Number)
  const value = new Date(year, month - 1, day)
  value.setDate(value.getDate() + days)

  return formatDate(value)
}

const daysBetween = (startDate: string, endDate: string) => {
  const start = new Date(`${startDate}T00:00:00`)
  const end = new Date(`${endDate}T00:00:00`)

  return Math.max(0, Math.round((end.getTime() - start.getTime()) / 86_400_000))
}

const moveTaskToDate = (taskId: string, startDate: string) => {
  if (!canAccess('edit_assigned_tasks')) return
  const task = store.tasks.find(candidate => candidate.id === taskId)
  if (!task) return

  const duration = daysBetween(task.startDate, task.dueDate)
  store.updateTaskDates(task.id, startDate, addDays(startDate, duration))
}

// --------------------------------------------------------------------------
// Event Drag & Drop / Resize Sync Handlers
// --------------------------------------------------------------------------
const handleEventChange = (info: any) => {
  if (!canAccess('edit_assigned_tasks')) {
    info.revert?.()
    return
  }
  const rawTask = info.event.extendedProps.rawTask as Task
  if (!rawTask) return

  // Extract ISO YYYY-MM-DD date strings
  const startDate = info.event.startStr.split('T')[0]

  // FullCalendar endStr can be null if same-day event
  let dueDate = info.event.endStr ? info.event.endStr.split('T')[0] : startDate

  // FullCalendar all-day events use an exclusive end date, while tasks use an
  // inclusive due date in the database.
  if (info.event.allDay && info.event.endStr) dueDate = addDays(dueDate, -1)

  // Update central Pinia store
  store.updateTaskDates(rawTask.id, startDate, dueDate)
}

// --------------------------------------------------------------------------
// FullCalendar Configuration (Interactive Mode Enabled)
// --------------------------------------------------------------------------
const calendarOptions = computed(() => ({
  plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
  initialView: 'dayGridMonth',
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,timeGridWeek,listMonth'
  },
  events: store.calendarEvents,

  // Interactive features enabled
  editable: canAccess('edit_assigned_tasks'),
  selectable: true,     // Enables date selection
  navLinks: true,
  height: 'auto',

  // Drag & Drop / Resize Handlers
  eventDrop: handleEventChange,
  eventResize: handleEventChange,

  // FullCalendar's list view intentionally disables built-in event dragging.
  // Provide native drag/drop between list-day rows and preserve the task span.
  eventDidMount: (info: any) => {
    if (!canAccess('edit_assigned_tasks') || !info.view.type.startsWith('list')) return

    const element = info.el as HTMLElement
    element.draggable = true
    element.classList.add('fc-list-event-draggable')
    element.addEventListener('dragstart', (event: DragEvent) => {
      event.dataTransfer?.setData('application/x-task-id', info.event.id)
      if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
    })
  },
  dayHeaderDidMount: (info: any) => {
    if (!canAccess('edit_assigned_tasks') || !info.view.type.startsWith('list')) return

    const element = info.el as HTMLElement
    const clearDropTarget = () => element.classList.remove('fc-list-drop-target')
    element.addEventListener('dragover', (event: DragEvent) => {
      event.preventDefault()
      if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
      element.classList.add('fc-list-drop-target')
    })
    element.addEventListener('dragleave', clearDropTarget)
    element.addEventListener('drop', (event: DragEvent) => {
      event.preventDefault()
      clearDropTarget()
      const taskId = event.dataTransfer?.getData('application/x-task-id')
      if (taskId) moveTaskToDate(taskId, formatDate(info.date))
    })
  },

  // Click event to view task modal
  eventClick: (info: any) => {
    const rawTask = info.event.extendedProps.rawTask as Task
    if (rawTask) {
      selectedTask.value = rawTask
      isDetailModalOpen.value = true
    }
  },

  eventClassNames: (arg: any) => {
    const priority = arg.event.extendedProps.priority || 'medium'
    return [`task-calendar-event`, `task-calendar-event--${priorityColorMap[priority]}`]
  }
}))

// --------------------------------------------------------------------------
// Deletion Safety Action
// --------------------------------------------------------------------------
const triggerDeleteWarning = () => {
  isDeleteConfirmOpen.value = true
}

const executeDeleteTask = () => {
  if (selectedTask.value) {
    store.deleteTask(selectedTask.value.id)
    isDeleteConfirmOpen.value = false
    isDetailModalOpen.value = false
    selectedTask.value = null
  }
}
</script>

<template>
  <div class="calendar-wrapper">
    <!-- Main Calendar Card Container -->
    <VCard elevation="0" class="border">
      <VCardItem class="bg-surface border-b pb-4">
        <template #title>
          <div class="d-flex align-center">
            <VIcon icon="tabler-calendar-event" color="primary" class="mr-2" />
            <span class="text-h5 font-weight-bold">{{ $t('components.taskManager.calendarview.scheduleTimelineView') }}</span>
          </div>
        </template>
        <template #append>
          <VChip variant="tonal" color="success" size="small">
            <VIcon icon="tabler-arrows-move" size="14" class="mr-1" /> {{ $t('components.taskManager.calendarview.dragDropActive') }}
          </VChip>
        </template>
      </VCardItem>

      <!-- Reduced padding to give more room for the calendar -->
      <VCardText class="pa-4 pa-sm-6">
        <FullCalendar :options="calendarOptions" />
      </VCardText>
    </VCard>

    <!-- 1. TASK INSPECTION MODAL (Increased max-width to 800) -->
    <VDialog v-model="isDetailModalOpen" max-width="800" scrollable>
      <VCard v-if="selectedTask">
        <!-- Modal Header -->
        <VCardItem class="border-b bg-light-primary pa-4">
          <template #title>
            <div class="text-h5 font-weight-bold">
              {{ selectedTask.title }}
            </div>
          </template>
          <template #append>
            <VBtn icon="tabler-x" variant="text" color="secondary" density="compact"
              @click="isDetailModalOpen = false" />
          </template>
        </VCardItem>

        <!-- Reduced padding here to utilize space better -->
        <VCardText class="pa-4">
          <!-- Metadata Bar -->
          <div class="d-flex align-center flex-wrap gap-3 mb-5">
            <TaskCompletionChip :progress="selectedTask.progress" />
            <VChip :color="priorityColorMap[selectedTask.priority]" size="small"
              class="text-uppercase font-weight-bold">
              <VIcon icon="tabler-flag" size="14" class="mr-1" />
              {{ $t('components.taskManager.event.priority', { priority: translateBackendValue(selectedTask.priority) }) }}
            </VChip>

            <VChip color="secondary" size="small" variant="tonal">
              <VIcon icon="tabler-calendar-time" size="14" class="mr-1" />
              {{ $t('components.taskManager.event.dateRange', { start: formatLocalizedDate(`${selectedTask.startDate}T00:00:00`), end: formatLocalizedDate(`${selectedTask.dueDate}T00:00:00`) }) }}
            </VChip>

            <VChip v-for="tag in selectedTask.tags" :key="tag.id" :color="tag.color" size="small">
              {{ tag.name }}
            </VChip>
          </div>

          <!-- Calculated Progress Meter -->
          <div class="pa-4 bg-grey-50 rounded border mb-5">
            <div class="d-flex justify-space-between align-center mb-2">
              <span class="text-subtitle-2 font-weight-bold">{{ $t('components.taskManager.calendarview.taskProgressRollup') }}</span>
              <span class="text-subtitle-2 font-weight-bold">{{ selectedTask.progress }}%</span>
            </div>
            <VProgressLinear :model-value="selectedTask.progress"
              :color="selectedTask.progress === 100 ? 'success' : 'primary'" height="8" rounded />
            <div class="d-flex align-center justify-space-between mt-2 text-caption text-medium-emphasis">
              <span>{{ $t('components.taskManager.calendarview.subTasksCompleted') }}</span>
              <span>
                {{ $t('components.taskManager.calendarview.itemProgress', { completed: selectedTask.subtasks.filter(st => st.isCompleted).length, total: selectedTask.subtasks.length }) }}
              </span>
            </div>
          </div>

          <!-- HTML Content Display (Label Fixed) -->
          <div class="mb-2">
            <span class="text-subtitle-1 font-weight-bold">{{ $t('common.fields.description') }}</span>
          </div>
          <div class="html-preview-box pa-4 border rounded bg-surface text-body-1">
            <div v-if="selectedTask.description" v-html="selectedTask.description"></div>
            <span v-else class="text-disabled italic">{{ $t('components.taskManager.calendarview.noDescriptionProvidedForThisTask') }}</span>
          </div>
        </VCardText>

        <!-- Modal Action Footer -->
        <VCardActions class="pa-4 border-t d-flex justify-space-between bg-surface">
          <VBtn v-if="canAccess('delete_assigned_tasks')" color="error" variant="tonal" prepend-icon="tabler-trash" @click="triggerDeleteWarning">
            {{ $t('common.messages.deleteTask') }}
          </VBtn>

          <VBtn color="secondary" variant="outlined" @click="isDetailModalOpen = false">
            {{ $t('common.actions.close') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- 2. VUEXY DESTRUCTIVE ACTION WARNING DIALOG -->
    <VDialog v-model="isDeleteConfirmOpen" max-width="450" persistent>
      <VCard class="text-center pa-4">
        <VCardText class="pt-6">
          <VAvatar color="warning" variant="tonal" size="70" class="mb-4">
            <VIcon icon="tabler-alert-triangle" size="40" />
          </VAvatar>

          <h4 class="text-h4 font-weight-bold mb-2">{{ $t('common.confirmations.areYouSure') }}</h4>
          <p class="text-body-1 text-medium-emphasis mb-0">
            {{ $t('components.taskManager.calendarview.youAreAboutToDelete') }} <strong>"{{ selectedTask?.title }}"</strong>{{ $t('common.confirmations.thisActionCannotBeUndoneAndWillPermanentlyEraseAllAssociatedMiniTodosA') }}
          </p>
        </VCardText>

        <VCardActions class="justify-center gap-3 pb-4">
          <VBtn color="secondary" variant="tonal" @click="isDeleteConfirmOpen = false">
            {{ $t('common.actions.cancel') }}
          </VBtn>

          <VBtn color="error" variant="elevated" prepend-icon="tabler-trash-x" @click="executeDeleteTask">
            {{ $t('components.taskManager.calendarview.yesDeleteIt') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
:deep(.fc) {
  font-family: inherit;
}

:deep(.fc-toolbar-title) {
  font-size: 1.25rem !important;
  font-weight: 700;
}

:deep(.fc-button-primary) {
  background-color: rgb(var(--v-theme-primary)) !important;
  border-color: rgb(var(--v-theme-primary)) !important;
  text-transform: capitalize;
  box-shadow: none !important;
}

:deep(.fc-button-primary:disabled) {
  opacity: 0.6;
}

:deep(.fc-event) {
  cursor: grab;
  border: none !important;
  padding: 2px 6px;
  border-radius: 4px;
  font-weight: 600;
  font-size: 0.825rem;
}

:deep(.task-calendar-event) {
  border: none !important;
  opacity: 1 !important;
}

:deep(.task-calendar-event--success) { background-color: rgb(var(--v-theme-success)) !important; color: rgb(var(--v-theme-on-success)) !important; }
:deep(.task-calendar-event--info) { background-color: rgb(var(--v-theme-info)) !important; color: rgb(var(--v-theme-on-info)) !important; }
:deep(.task-calendar-event--warning) { background-color: rgb(var(--v-theme-warning)) !important; color: rgb(var(--v-theme-on-warning)) !important; }
:deep(.task-calendar-event--error) { background-color: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }

:deep(.fc-event:active) {
  cursor: grabbing;
}

:deep(.fc-list-event-draggable) {
  cursor: grab;
}

:deep(.fc-list-drop-target > th) {
  outline: 2px dashed rgb(var(--v-theme-primary));
  outline-offset: -3px;
}

.html-preview-box {
  min-height: 120px;
  /* Removed max-height so the dialog handles the scrolling naturally */
}

.html-preview-box :deep(img) {
  max-width: 100%;
  border-radius: 6px;
}
</style>
