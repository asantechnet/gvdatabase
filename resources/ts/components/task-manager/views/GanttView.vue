<script setup lang="ts">
import { ref } from 'vue'
import FrappeGanttWrapper from '../components/FrappeGanttWrapper.vue'
import TaskDetailDrawer from '../components/TaskDetailDrawer.vue'
import { useTaskStore } from '@/stores/useTaskStore'
import { canAccess } from '@/utils/authorization'
import type { FrappeGanttTask } from '@/types/taskManager'

const store = useTaskStore() //

// State
const activeViewMode = ref<'Day' | 'Week' | 'Month'>('Week') //
const viewOptions = ['Day', 'Week', 'Month'] //
const isDrawerOpen = ref(false) //

// Snackbar for constraint enforcement
const snackbar = ref({
  show: false,
  message: '',
}) //

// Handlers
const handleTaskClick = (ganttTask: FrappeGanttTask) => {
  store.selectTask(ganttTask.id) //
  isDrawerOpen.value = true //
}

const localDateString = (date: Date) => [
  date.getFullYear(),
  String(date.getMonth() + 1).padStart(2, '0'),
  String(date.getDate()).padStart(2, '0'),
].join('-')

const handleDateChange = (ganttTask: FrappeGanttTask, start: Date, end: Date) => {
  if (!canAccess('edit_assigned_tasks'))
    return

  // Frappe emits local calendar dates. Formatting through toISOString can
  // shift the task by a day in positive-offset timezones.
  const startStr = localDateString(start) //
  const endStr = localDateString(end) //

  store.updateTaskDates(ganttTask.id, startStr, endStr) //
}

const handleProgressChange = (ganttTask: FrappeGanttTask, attemptedProgress: number) => {
  // CONSTRAINT ENFORCEMENT:
  // We do not allow manual progress dragging. It must come from the Todo Matrix.

  snackbar.value = {
    show: true,
    message: translate('components.taskManager.ganttview.progressIsCalculatedAutomaticallyFromSubTasksTodosEditTheTaskToManageT'),
  } //

  // The wrapper restores the authoritative subtask-derived progress after
  // Frappe's drag preview; no unnecessary backend update is needed here.
}

</script>

<template>
  <div class="gantt-wrapper position-relative">
    <VCard
      elevation="0"
      class="border"
    >
      <!-- Header & Controls -->
      <VCardItem class="gantt-header bg-surface border-b">
        <template #title>
          <div class="d-flex align-center min-w-0">
            <VIcon
              icon="tabler-chart-bar"
              color="primary"
              class="me-2"
            />
            <span class="text-h5 font-weight-bold text-truncate">{{ $t('components.taskManager.ganttview.projectGanttTimeline') }}</span>
          </div>
        </template>
        <template #append>
          <div class="gantt-controls">
            <VSelect
              v-model="activeViewMode"
              :items="viewOptions"
              density="compact"
              variant="outlined"
              hide-details
              prepend-inner-icon="tabler-zoom-in"
              class="gantt-controls__zoom"
            />
          </div>
        </template>
      </VCardItem>

      <!-- Gantt Component Wrapper -->
      <VCardText class="pa-0 bg-surface">
        <div class="gantt-scroll-container">
          <FrappeGanttWrapper
            :tasks="store.ganttTasks"
            :view-mode="activeViewMode"
            @task-click="handleTaskClick"
            @date-change="handleDateChange"
            @progress-change="handleProgressChange"
          />
        </div>
      </VCardText>
    </VCard>

    <!-- Unified Task Editor Drawer -->
    <TaskDetailDrawer v-model:is-open="isDrawerOpen" />

    <!-- Constraint Enforcement Toast -->
    <VSnackbar
      v-model="snackbar.show"
      color="warning"
      location="top right"
      :timeout="4000"
    >
      <div class="d-flex align-center">
        <VIcon
          icon="tabler-alert-circle"
          class="me-2"
        />
        {{ snackbar.message }}
      </div>
      <template #actions>
        <VBtn
          variant="text"
          icon="tabler-x"
          @click="snackbar.show = false"
        />
      </template>
    </VSnackbar>
  </div>
</template>

<style scoped>
.gantt-wrapper {
  min-width: 0;
}

.gantt-header :deep(.v-card-item__append) {
  min-width: 0;
}

.gantt-controls {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 0.75rem;
}

.gantt-controls__zoom {
  flex: 0 1 8.75rem;
  min-width: 7.5rem;
}

/* The component owns the scroll viewport. Do not clip this outer layer: the
 * library positions popovers outside an individual bar. */
.gantt-scroll-container {
  min-width: 0;
  width: 100%;
  position: relative;
}

@media (max-width: 700px) {
  .gantt-header :deep(.v-card-item__content),
  .gantt-header :deep(.v-card-item__append) {
    flex-basis: 100%;
  }

  .gantt-header :deep(.v-card-item__append) {
    margin-inline-start: 0;
    margin-block-start: 0.75rem;
  }

  .gantt-controls {
    justify-content: space-between;
  }

  .gantt-controls__zoom {
    flex: 1 1 8.75rem;
  }
}
</style>
