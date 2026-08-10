<script setup lang="ts">
import type { FrappeGanttTask } from '@/types/taskManager';
import Gantt from 'frappe-gantt';
import 'frappe-gantt/dist/frappe-gantt.css';
import { nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps<{
  tasks: FrappeGanttTask[]
  viewMode: 'Quarter Day' | 'Half Day' | 'Day' | 'Week' | 'Month' | 'Year'
}>()

const emit = defineEmits<{
  (e: 'task-click', task: FrappeGanttTask): void
  (e: 'date-change', task: FrappeGanttTask, start: Date, end: Date): void
  (e: 'progress-change', task: FrappeGanttTask, progress: number): void
}>()

const ganttContainer = ref<HTMLElement | null>(null)
let ganttInstance: any = null

const initGantt = () => {
  if (!ganttContainer.value || props.tasks.length === 0) return

  if (ganttInstance) {
    ganttContainer.value.innerHTML = ''
  }

  ganttInstance = new Gantt(ganttContainer.value, props.tasks, {
    header_height: 50,
    // FIX: Removed 'column_width' and 'step'. 
    // Frappe will now auto-calculate sizes based on the view_mode to prevent squished text!
    bar_height: 30,
    bar_corner_radius: 6,
    arrow_curve: 5,
    padding: 18,
    view_mode: props.viewMode,
    date_format: 'YYYY-MM-DD',

    on_click: (task: FrappeGanttTask) => {
      emit('task-click', task)
    },
    on_date_change: (task: FrappeGanttTask, start: Date, end: Date) => {
      emit('date-change', task, start, end)
    },
    on_progress_change: (task: FrappeGanttTask, progress: number) => {
      emit('progress-change', task, progress)
    }
  })
}

watch(() => props.tasks, async (newTasks) => {
  if (ganttInstance && newTasks.length > 0) {
    ganttInstance.refresh(newTasks)
  } else if (!ganttInstance && newTasks.length > 0) {
    await nextTick()
    initGantt()
  }
}, { deep: true })

watch(() => props.viewMode, (newMode) => {
  if (ganttInstance) {
    ganttInstance.change_view_mode(newMode)
  }
})

onMounted(() => {
  initGantt()
})
</script>

<template>
  <div class="frappe-gantt-vue-wrapper w-100">
    <div ref="ganttContainer"></div>
    <div v-if="tasks.length === 0" class="pa-10 text-center text-disabled">
      <VIcon icon="tabler-chart-bar-off" size="48" class="mb-3" />
      <div class="text-subtitle-1">No tasks available to plot on the timeline.</div>
    </div>
  </div>
</template>

<style scoped>
/* FIX: Force Frappe's internal container to hide vertical scroll and handle horizontal properly */
:deep(.gantt-container) {
  overflow-y: hidden !important;
  overflow-x: auto !important;
  /* Smooth scrolling for horizontal dragging */
  scroll-behavior: smooth;
}

/* Theme integration */
:deep(.gantt .bar-wrapper) {
  cursor: pointer;
}

:deep(.gantt .bar-progress) {
  fill: rgb(var(--v-theme-primary));
}

:deep(.gantt .bar) {
  fill: rgba(var(--v-theme-primary), 0.2);
}

:deep(.gantt .grid-header) {
  fill: rgb(var(--v-theme-surface));
  stroke: rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
