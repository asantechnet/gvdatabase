<script setup lang="ts">
import { useTaskStore } from '@/stores/useTaskStore'
import { computed } from 'vue'
import CalendarView from './views/CalendarView.vue'
import GanttView from './views/GanttView.vue'
import KanbanView from './views/KanbanView.vue'
import TodoMatrixView from './views/TodoMatrixView.vue'
import TaskSettingsView from './views/TaskSettingsView.vue'

const store = useTaskStore()

// Bi-directional binding to the Pinia state
const activeTab = computed({
  get: () => store.activeView,
  set: (val) => store.setView(val as any)
})

const tabs = [
  { title: translate('components.taskManager.taskappshell.kanbanBoard'), value: 'kanban', icon: 'tabler-layout-kanban' },
  { title: translate('common.messages.calendar'), value: 'calendar', icon: 'tabler-calendar-event' },
  { title: translate('components.taskManager.taskappshell.ganttTimeline'), value: 'gantt', icon: 'tabler-chart-bar' },
  { title: translate('components.taskManager.taskappshell.todoMatrix'), value: 'todos', icon: 'tabler-list-check' },
  { title: translate('common.messages.settings'), value: 'settings', icon: 'tabler-settings' },
]

// Dynamically swap views directly in this layout
const currentComponent = computed(() => {
  switch (store.activeView) {
    case 'kanban': return KanbanView
    case 'calendar': return CalendarView
    case 'gantt': return GanttView
    case 'todos': return TodoMatrixView
    case 'settings': return TaskSettingsView
    default: return KanbanView
  }
})
</script>

<template>
  <div class="task-app-shell">
    <!-- Master Navigation -->
    <!-- Added elevation="0" and border for a flatter, premium Vuexy look -->
    <VCard elevation="0" class="border mb-6 bg-surface">
      <!-- Added show-arrows for mobile responsiveness and removed "stacked" to save vertical space -->
      <VTabs v-model="activeTab" color="primary" align-tabs="start" show-arrows height="60" slider-color="primary">
        <VTab v-for="tab in tabs" :key="tab.value" :value="tab.value" class="px-5 text-none">
          <!-- Shifted icon to the left of the text for a horizontal layout -->
          <VIcon :icon="tab.icon" size="22" class="mr-2" />
          <span class="text-subtitle-2 font-weight-medium">{{ tab.title }}</span>
        </VTab>
      </VTabs>
    </VCard>

    <!-- View Container (Populates the view directly without route changes) -->
    <div class="view-container">
      <Transition name="fade-transition" mode="out-in">
        <component :is="currentComponent" />
      </Transition>
    </div>
  </div>
</template>

<style scoped>
/* Optional: Ensures smooth transitions between views if not already defined globally */
.fade-transition-enter-active,
.fade-transition-leave-active {
  transition: opacity 0.2s ease;
}

.fade-transition-enter-from,
.fade-transition-leave-to {
  opacity: 0;
}
</style>
