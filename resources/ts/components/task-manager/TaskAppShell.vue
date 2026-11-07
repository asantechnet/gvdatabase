<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import KanbanView from './views/KanbanView.vue'
import TodoMatrixView from './views/TodoMatrixView.vue'
import TaskSettingsView from './views/TaskSettingsView.vue'
import { useTaskStore } from '@/stores/useTaskStore'

const store = useTaskStore()
const route = useRoute()

const validViews = ['kanban', 'todos', 'settings'] as const

// A case task can deep-link into the matrix. Load the board state first: the
// matrix view is mounted here, not by the Kanban view's own onMounted fetch.
const applyRouteQuery = () => {
  const view = String(route.query.view ?? '')
  if (validViews.includes(view as typeof validViews[number]))
    store.setView(view as typeof validViews[number])

  const taskId = Number(route.query.task)
  if (Number.isInteger(taskId) && taskId > 0)
    store.selectTask(String(taskId))
}

onMounted(async () => {
  applyRouteQuery()
  if (store.activeView !== 'kanban' && !store.boards.length)
    await store.fetchBoardData()
})

watch(() => route.query, applyRouteQuery, { deep: true })

// The selected id is applied before data exists when the route is opened
// directly; re-apply after the board payload arrives so the matrix selects the
// row rather than rendering an empty panel.
watch(() => store.tasks.length, () => {
  const taskId = Number(route.query.task)
  if (Number.isInteger(taskId) && taskId > 0)
    store.selectTask(String(taskId))
})

// Bi-directional binding to the Pinia state
const activeTab = computed({
  get: () => store.activeView,
  set: val => store.setView(val as any),
})

const tabs = computed(() => [
  { title: translate('components.taskManager.taskappshell.kanbanBoard'), value: 'kanban', icon: 'tabler-layout-kanban' },
  { title: translate('components.taskManager.taskappshell.todoMatrix'), value: 'todos', icon: 'tabler-list-check' },
  { title: translate('common.messages.settings'), value: 'settings', icon: 'tabler-settings' },
])

// Dynamically swap views directly in this layout
const currentComponent = computed(() => {
  switch (store.activeView) {
    case 'kanban': return KanbanView
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
    <VCard
      elevation="0"
      class="border mb-6 bg-surface"
    >
      <div class="task-app-shell__nav d-flex flex-wrap align-center gap-2 px-3">
        <VTabs
          v-model="activeTab"
          color="primary"
          align-tabs="start"
          show-arrows
          height="60"
          slider-color="primary"
          class="task-app-shell__tabs"
          @update:model-value="store.setView($event as any)"
        >
          <VTab
            v-for="tab in tabs"
            :key="tab.value"
            :value="tab.value"
            class="px-5 text-none"
          >
            <VIcon
              :icon="tab.icon"
              size="22"
              class="me-2"
            />
            <span class="text-subtitle-2 font-weight-medium">{{ tab.title }}</span>
          </VTab>
        </VTabs>
      </div>
    </VCard>

    <!-- View Container (Populates the view directly without route changes) -->
    <div class="view-container">
      <Transition
        name="fade-transition"
        mode="out-in"
      >
        <component
          :is="currentComponent"
          :key="store.activeView"
        />
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

.task-app-shell__tabs {
  flex: 1 1 34rem;
  min-inline-size: 0;
}

@media (max-width: 760px) {
  .task-app-shell__nav {
    padding-block: 0.5rem;
  }
}
</style>
