<script setup lang="ts">
import { useTaskStore } from '@/stores/useTaskStore'
import ProjectApi, { type Project } from '@/api/project'
import ServiceApi, { type Service } from '@/api/services'
import { canAccess } from '@/utils/authorization'
import { computed, onMounted, ref } from 'vue'
import CalendarView from './views/CalendarView.vue'
import GanttView from './views/GanttView.vue'
import KanbanView from './views/KanbanView.vue'
import TodoMatrixView from './views/TodoMatrixView.vue'
import TaskSettingsView from './views/TaskSettingsView.vue'

const store = useTaskStore()
const projects = ref<Project[]>([])
const services = ref<Service[]>([])
const isProjectDialogOpen = ref(false)
const savingProject = ref(false)
const newProject = ref({ name: '', description: '', due_date: null as string | null, service_ids: [] as number[] })

const projectOptions = computed(() => [
  { title: translate('components.taskManager.taskappshell.allWork'), value: null },
  ...projects.value.map(project => ({
    title: `${project.name}${project.status === 'active' ? '' : ` (${project.status.replace('_', ' ')})`}`,
    value: project.id,
  })),
])

const serviceOptions = computed(() => services.value
  .filter(service => service.status === 'active')
  .map(service => ({ title: service.type, value: service.id })))

const changeProject = async (projectId: number | null) => {
  await store.selectProject(projectId)
}

const createProject = async () => {
  if (!newProject.value.name.trim()) return
  savingProject.value = true
  try {
    const project = await ProjectApi.create({
      name: newProject.value.name.trim(),
      description: newProject.value.description || null,
      due_date: newProject.value.due_date,
      service_ids: newProject.value.service_ids,
    })
    // Reload from the authoritative list so the new project is visible even
    // when an API response omits fields used by the select's item renderer.
    projects.value = await ProjectApi.getAll()
    isProjectDialogOpen.value = false
    newProject.value = { name: '', description: '', due_date: null, service_ids: [] }
    await changeProject(project.id)
  } finally {
    savingProject.value = false
  }
}

onMounted(async () => {
  const [projectResult, serviceResult] = await Promise.allSettled([ProjectApi.getAll(), ServiceApi.getAll()])
  if (projectResult.status === 'fulfilled') projects.value = projectResult.value
  if (serviceResult.status === 'fulfilled' && serviceResult.value.success) services.value = serviceResult.value.data
})

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
      <div class="task-app-shell__nav d-flex flex-wrap align-center gap-2 px-3">
        <VTabs v-model="activeTab" color="primary" align-tabs="start" show-arrows height="60" slider-color="primary"
          class="task-app-shell__tabs" @update:model-value="store.setView($event as any)">
          <VTab v-for="tab in tabs" :key="tab.value" :value="tab.value" class="px-5 text-none">
            <VIcon :icon="tab.icon" size="22" class="me-2" />
            <span class="text-subtitle-2 font-weight-medium">{{ tab.title }}</span>
          </VTab>
        </VTabs>

        <VSelect :model-value="store.selectedProjectId" :items="projectOptions" density="compact" hide-details
          variant="outlined" class="project-context__select" prepend-inner-icon="tabler-folders"
          :aria-label="translate('components.taskManager.taskappshell.selectProjectWorkspace')" @update:model-value="changeProject" />
        <VBtn v-if="canAccess('create_projects')" size="small" variant="tonal" color="primary"
          prepend-icon="tabler-plus" @click="isProjectDialogOpen = true">
          {{ $t('components.taskManager.taskappshell.newProject') }}
        </VBtn>
      </div>
    </VCard>

    <!-- View Container (Populates the view directly without route changes) -->
    <div class="view-container">
      <Transition name="fade-transition" mode="out-in">
        <component :is="currentComponent" :key="store.activeView" />
      </Transition>
    </div>

    <VDialog v-model="isProjectDialogOpen" max-width="640">
      <VCard>
        <VCardTitle>{{ $t('components.taskManager.taskappshell.createProjectWorkspace') }}</VCardTitle>
        <VCardText>
          <p class="text-body-2 text-medium-emphasis mb-5">
            {{ $t('components.taskManager.taskappshell.projectWorkspaceDescription') }}
          </p>
          <VTextField v-model="newProject.name" :label="$t('components.taskManager.taskappshell.projectName')" class="mb-4" autofocus />
          <VTextarea v-model="newProject.description" :label="$t('common.fields.description')" rows="2" class="mb-4" />
          <AppDateTimePicker v-model="newProject.due_date" :label="$t('components.taskManager.taskappshell.targetDate')" class="mb-4"
            :config="{ dateFormat: 'Y-m-d' }" clearable />
          <VAutocomplete v-model="newProject.service_ids" :items="serviceOptions" :label="$t('components.taskManager.taskappshell.linkedServices')"
            multiple chips closable-chips clearable prepend-inner-icon="tabler-briefcase" />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="isProjectDialogOpen = false">Cancel</VBtn>
          <VBtn color="primary" :loading="savingProject" @click="createProject">{{ $t('components.taskManager.taskappshell.createProject') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
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

.project-context__select {
  flex: 0 1 15rem;
  min-inline-size: min(100%, 12rem);
}

.task-app-shell__tabs {
  flex: 1 1 34rem;
  min-inline-size: 0;
}

@media (max-width: 760px) {
  .task-app-shell__nav {
    padding-block: 0.5rem;
  }

  .project-context__select {
    flex: 1 1 13rem;
  }
}
</style>
