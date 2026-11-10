<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import KanbanView from './views/KanbanView.vue'
import TodoMatrixView from './views/TodoMatrixView.vue'
import TaskSettingsView from './views/TaskSettingsView.vue'
import { useTaskStore } from '@/stores/useTaskStore'
import { canAccess } from '@/utils/authorization'

const store = useTaskStore()
const route = useRoute()
const canViewKanban = () => canAccess('view_kanban')
const canViewSettings = () => canAccess('view_task_settings')

const validViews = ['kanban', 'todos', 'settings'] as const

const applyRouteQuery = () => {
  const view = String(route.query.view ?? '')
  if (validViews.includes(view as typeof validViews[number])) {
    const next = (view === 'kanban' && !canViewKanban())
      ? 'todos'
      : (view === 'settings' && !canViewSettings() ? 'todos' : view)

    store.setView(next as typeof validViews[number])
  }

  const taskId = Number(route.query.task)
  if (Number.isInteger(taskId) && taskId > 0)
    store.selectTask(String(taskId))
}

onMounted(async () => {
  if (!canViewKanban() && store.activeView === 'kanban')
    store.setView('todos')

  applyRouteQuery()
  if (!store.boards.length)
    await store.fetchBoardData()
})

watch(() => route.query, applyRouteQuery, { deep: true })

watch(() => store.tasks.length, () => {
  const taskId = Number(route.query.task)
  if (Number.isInteger(taskId) && taskId > 0)
    store.selectTask(String(taskId))
})

watch(() => store.activeView, view => {
  if (view === 'kanban' && !canViewKanban())
    store.setView('todos')
  if (view === 'settings' && !canViewSettings())
    store.setView('todos')
})

const activeTab = computed({
  get: () => store.activeView,
  set: val => store.setView(val as any),
})

const tabs = computed(() => {
  const items: Array<{ title: string; value: string; icon: string }> = []

  if (canViewKanban())
    items.push({ title: translate('components.taskManager.taskappshell.kanbanBoard'), value: 'kanban', icon: 'tabler-layout-kanban' })

  items.push({ title: translate('components.taskManager.taskappshell.todoMatrix'), value: 'todos', icon: 'tabler-list-check' })

  if (canViewSettings())
    items.push({ title: translate('common.messages.settings'), value: 'settings', icon: 'tabler-settings' })

  return items
})

const currentComponent = computed(() => {
  switch (store.activeView) {
    case 'kanban': return canViewKanban() ? KanbanView : TodoMatrixView
    case 'todos': return TodoMatrixView
    case 'settings': return canViewSettings() ? TaskSettingsView : TodoMatrixView
    default: return canViewKanban() ? KanbanView : TodoMatrixView
  }
})
</script>

<template>
  <div class="task-app-shell">
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
