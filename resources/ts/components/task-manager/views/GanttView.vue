<script setup lang="ts">
import { useTaskStore } from '@/stores/useTaskStore'
import type { FrappeGanttTask } from '@/types/taskManager'
import { ref } from 'vue'
import FrappeGanttWrapper from '../components/FrappeGanttWrapper.vue'
import TaskDetailDrawer from '../components/TaskDetailDrawer.vue'

const store = useTaskStore() //

// State
const activeViewMode = ref<'Day' | 'Week' | 'Month'>('Week') //
const viewOptions = ['Day', 'Week', 'Month'] //
const isDrawerOpen = ref(false) //

// Snackbar for constraint enforcement
const snackbar = ref({
  show: false,
  message: ''
}) //

// Handlers
const handleTaskClick = (ganttTask: FrappeGanttTask) => {
  store.selectTask(ganttTask.id) //
  isDrawerOpen.value = true //
}

const handleDateChange = (ganttTask: FrappeGanttTask, start: Date, end: Date) => {
  // Format dates back to ISO strings for our standardized JSON backend
  const startStr = start.toISOString().split('T')[0] //
  const endStr = end.toISOString().split('T')[0] //
  store.updateTaskDates(ganttTask.id, startStr, endStr) //
}

const handleProgressChange = (ganttTask: FrappeGanttTask, attemptedProgress: number) => {
  // CONSTRAINT ENFORCEMENT:
  // We do not allow manual progress dragging. It must come from the Todo Matrix.

  snackbar.value = {
    show: true,
    message: 'Progress is calculated automatically from sub-tasks (Todos). Edit the task to manage them!'
  } //

  // Force a Pinia reactivity tick to snap the Gantt bar back to its true calculated percentage
  const originalTask = store.rawTasks.find(t => t.id === ganttTask.id) //
  if (originalTask) {
    // A harmless reactive "nudge" to force the computed property to re-evaluate and send data down to the wrapper
    store.updateTask(ganttTask.id, { updatedAt: new Date().toISOString() }) //
  }
}
</script>

<template>
  <div class="gantt-wrapper position-relative">
    <VCard elevation="0" class="border">
      <!-- Header & Controls -->
      <VCardItem class="bg-surface border-b pb-4">
        <template #title>
          <div class="d-flex align-center">
            <VIcon icon="tabler-chart-bar" color="primary" class="mr-2" />
            <span class="text-h5 font-weight-bold">Project Gantt Timeline</span>
          </div>
        </template>
        <template #append>
          <div class="d-flex align-center gap-4">
            <VSelect v-model="activeViewMode" :items="viewOptions" density="compact" variant="outlined" hide-details
              prepend-inner-icon="tabler-zoom-in" style="width: 140px" />
          </div>
        </template>
      </VCardItem>

      <!-- Gantt Component Wrapper -->
      <VCardText class="pa-0 bg-surface">
        <!-- FIX: Added a dedicated scroll container for the SVG canvas -->
        <div class="gantt-scroll-container overflow-x-auto">
          <FrappeGanttWrapper :tasks="store.ganttTasks" :view-mode="activeViewMode" @task-click="handleTaskClick"
            @date-change="handleDateChange" @progress-change="handleProgressChange" />
        </div>
      </VCardText>
    </VCard>

    <!-- Unified Task Editor Drawer -->
    <TaskDetailDrawer v-model:is-open="isDrawerOpen" />

    <!-- Constraint Enforcement Toast -->
    <VSnackbar v-model="snackbar.show" color="warning" location="top right" :timeout="4000">
      <div class="d-flex align-center">
        <VIcon icon="tabler-alert-circle" class="mr-2" />
        {{ snackbar.message }}
      </div>
      <template #actions>
        <VBtn variant="text" icon="tabler-x" @click="snackbar.show = false" />
      </template>
    </VSnackbar>
  </div>
</template>

<style scoped>
/* Ensures the SVG canvas does not collapse vertically and handles horizontal bounds properly */
.gantt-scroll-container {
  min-height: 450px;
  width: 100%;
  position: relative;
  /* Adds momentum scrolling on mobile devices */
  -webkit-overflow-scrolling: touch;
}
</style>
