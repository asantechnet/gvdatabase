<script setup lang="ts">
import type { FrappeGanttTask } from '@/types/taskManager'
import Gantt from 'frappe-gantt'
import 'frappe-gantt/dist/frappe-gantt.css'
import { computed, nextTick, onMounted, ref, watch } from 'vue'

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
let pendingInteractiveDateChange: { id: string; start: string; end: string } | null = null
let ganttPointerStart: { x: number; y: number } | null = null
let suppressTaskClickUntil = 0

const localDateString = (date: Date) => [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-')
const isGanttBarTarget = (target: EventTarget | null) => target instanceof Element && Boolean(target.closest('.bar-wrapper'))
const handlePointerDown = (event: PointerEvent) => { if (isGanttBarTarget(event.target)) ganttPointerStart = { x: event.clientX, y: event.clientY } }
const handlePointerMove = (event: PointerEvent) => {
  if (ganttPointerStart && Math.hypot(event.clientX - ganttPointerStart.x, event.clientY - ganttPointerStart.y) > 5)
    suppressTaskClickUntil = Date.now() + 350
}
const handlePointerUp = () => { ganttPointerStart = null }

// Frappe crashes when a dependency is not present in the visible task list.
const chartTasks = computed<FrappeGanttTask[]>(() => {
  const validIds = new Set(props.tasks.map(task => task.id))
  return props.tasks.map(task => ({
    ...task,
    dependencies: task.dependencies.split(',').map(id => id.trim()).filter(id => id && id !== task.id && validIds.has(id)).join(','),
  }))
})

const initGantt = () => {
  if (!ganttContainer.value || !chartTasks.value.length) return
  if (ganttInstance) ganttContainer.value.innerHTML = ''
  ganttInstance = new Gantt(ganttContainer.value, chartTasks.value, {
    header_height: 50,
    bar_height: 30,
    bar_corner_radius: 6,
    arrow_curve: 5,
    padding: 18,
    view_mode: props.viewMode,
    date_format: 'YYYY-MM-DD',
    container_height: 900,
    infinite_padding: false,
    on_click: (task: FrappeGanttTask) => { if (Date.now() >= suppressTaskClickUntil) emit('task-click', task) },
    on_date_change: (task: FrappeGanttTask, start: Date, end: Date) => {
      pendingInteractiveDateChange = { id: task.id, start: localDateString(start), end: localDateString(end) }
      emit('date-change', task, start, end)
    },
    on_progress_change: (task: FrappeGanttTask, progress: number) => {
      emit('progress-change', task, progress)
      nextTick(() => ganttInstance?.refresh(chartTasks.value))
    },
  })
}

watch(chartTasks, async tasks => {
  if (!tasks.length) {
    if (ganttContainer.value) ganttContainer.value.innerHTML = ''
    ganttInstance = null
    return
  }
  if (!ganttInstance) return void (await nextTick(), initGantt())
  if (pendingInteractiveDateChange) {
    const moved = tasks.find(task => task.id === pendingInteractiveDateChange?.id)
    if (moved?.start === pendingInteractiveDateChange.start && moved?.end === pendingInteractiveDateChange.end) {
      pendingInteractiveDateChange = null
      return
    }
  }
  ganttInstance.refresh(tasks)
}, { deep: true })

watch(() => props.viewMode, mode => ganttInstance?.change_view_mode(mode))
onMounted(initGantt)
</script>

<template>
  <div
    class="frappe-gantt-vue-wrapper w-100"
    @pointerdown.capture="handlePointerDown"
    @pointermove.capture="handlePointerMove"
    @pointerup.capture="handlePointerUp"
    @pointercancel.capture="handlePointerUp"
  >
    <div ref="ganttContainer" />
    <div v-if="tasks.length === 0" class="pa-10 text-center text-disabled">
      <VIcon icon="tabler-chart-bar-off" size="48" class="mb-3" />
      <div class="text-subtitle-1">{{ $t('components.taskManager.frappeganttwrapper.noTasksAvailableToPlotOnTheTimeline') }}</div>
    </div>
  </div>
</template>

<style scoped>
.frappe-gantt-vue-wrapper,
:deep(.gantt-container) {
  overflow: visible !important;
}

:deep(.gantt-container) {
  height: 900px;
  touch-action: pan-y;
}

:deep(.gantt .bar-wrapper) { cursor: pointer; }
:deep(.gantt .bar-progress) { fill: rgb(var(--v-theme-primary)); }
:deep(.gantt .bar) { fill: rgba(var(--v-theme-primary), 0.2); }
:deep(.gantt .grid-header) { fill: rgb(var(--v-theme-surface)); stroke: rgba(var(--v-border-color), var(--v-border-opacity)); }
</style>
