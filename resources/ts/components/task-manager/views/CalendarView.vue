<script setup lang="ts">
import { useTaskStore } from '@/stores/useTaskStore'
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

// --------------------------------------------------------------------------
// Event Drag & Drop / Resize Sync Handlers
// --------------------------------------------------------------------------
const handleEventChange = (info: any) => {
  const rawTask = info.event.extendedProps.rawTask as Task
  if (!rawTask) return

  // Extract ISO YYYY-MM-DD date strings
  const startDate = info.event.startStr.split('T')[0]

  // FullCalendar endStr can be null if same-day event
  let dueDate = info.event.endStr ? info.event.endStr.split('T')[0] : startDate

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
  editable: true,       // Enables dragging events and resizing duration
  selectable: true,     // Enables date selection
  navLinks: true,
  height: 'auto',

  // Drag & Drop / Resize Handlers
  eventDrop: handleEventChange,
  eventResize: handleEventChange,

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
    return [`bg-light-${priorityColorMap[priority]}`, `text-${priorityColorMap[priority]}`]
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
            <span class="text-h5 font-weight-bold">Schedule & Timeline View</span>
          </div>
        </template>
        <template #append>
          <VChip variant="tonal" color="success" size="small">
            <VIcon icon="tabler-arrows-move" size="14" class="mr-1" /> Drag & Drop Active
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
            <VChip :color="priorityColorMap[selectedTask.priority]" size="small"
              class="text-uppercase font-weight-bold">
              <VIcon icon="tabler-flag" size="14" class="mr-1" />
              {{ selectedTask.priority }} Priority
            </VChip>

            <VChip color="secondary" size="small" variant="tonal">
              <VIcon icon="tabler-calendar-time" size="14" class="mr-1" />
              {{ selectedTask.startDate }} — {{ selectedTask.dueDate }}
            </VChip>

            <VChip v-for="tag in selectedTask.tags" :key="tag.id" :color="tag.color" size="small">
              {{ tag.name }}
            </VChip>
          </div>

          <!-- Calculated Progress Meter -->
          <div class="pa-4 bg-grey-50 rounded border mb-5">
            <div class="d-flex justify-space-between align-center mb-2">
              <span class="text-subtitle-2 font-weight-bold">Task Progress Rollup</span>
              <span class="text-subtitle-2 font-weight-bold">{{ selectedTask.progress }}%</span>
            </div>
            <VProgressLinear :model-value="selectedTask.progress"
              :color="selectedTask.progress === 100 ? 'success' : 'primary'" height="8" rounded />
            <div class="d-flex align-center justify-space-between mt-2 text-caption text-medium-emphasis">
              <span>Sub-tasks Completed</span>
              <span>
                {{selectedTask.subtasks.filter(st => st.isCompleted).length}} / {{ selectedTask.subtasks.length }}
                Items
              </span>
            </div>
          </div>

          <!-- HTML Content Display (Label Fixed) -->
          <div class="mb-2">
            <span class="text-subtitle-1 font-weight-bold">Description</span>
          </div>
          <div class="html-preview-box pa-4 border rounded bg-surface text-body-1">
            <div v-if="selectedTask.description" v-html="selectedTask.description"></div>
            <span v-else class="text-disabled italic">No description provided for this task.</span>
          </div>
        </VCardText>

        <!-- Modal Action Footer -->
        <VCardActions class="pa-4 border-t d-flex justify-space-between bg-surface">
          <VBtn color="error" variant="tonal" prepend-icon="tabler-trash" @click="triggerDeleteWarning">
            Delete Task
          </VBtn>

          <VBtn color="secondary" variant="outlined" @click="isDetailModalOpen = false">
            Close
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

          <h4 class="text-h4 font-weight-bold mb-2">Are you sure?</h4>
          <p class="text-body-1 text-medium-emphasis mb-0">
            You are about to delete <strong>"{{ selectedTask?.title }}"</strong>. This action cannot be undone and will
            permanently erase all associated mini-todos and timeline history.
          </p>
        </VCardText>

        <VCardActions class="justify-center gap-3 pb-4">
          <VBtn color="secondary" variant="tonal" @click="isDeleteConfirmOpen = false">
            Cancel
          </VBtn>

          <VBtn color="error" variant="elevated" prepend-icon="tabler-trash-x" @click="executeDeleteTask">
            Yes, Delete It!
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

:deep(.fc-event:active) {
  cursor: grabbing;
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
