<script setup lang="ts">
import type { Project } from '@/api/project'

const { t } = useI18n()

const props = defineProps<{ projects: Project[] }>()
const emit = defineEmits<{ select: [id: number] }>()
const active = computed(() => props.projects.filter(project => project.status === 'active').length)
const overdue = computed(() => props.projects.filter(project => project.status !== 'completed' && project.due_date && new Date(project.due_date) < new Date()).length)
const taskCount = (project: Project) => project.boards?.reduce((sum, board) => sum + (board.tasks?.length ?? 0), 0) ?? 0
const statusColor = (status: string) => ({ active: 'success', on_hold: 'warning', completed: 'primary', archived: 'secondary' }[status] || 'secondary')
const statusLabel = (status: string) => t(`components.taskManager.projectPortfolio.statuses.${status}`)
const headers = computed(() => [
  { title: t('components.taskManager.projectPortfolio.headers.project'), key: 'name' },
  { title: t('components.taskManager.projectPortfolio.headers.customer'), key: 'applicant.name' },
  { title: t('components.taskManager.projectPortfolio.headers.services'), key: 'services' },
  { title: t('components.taskManager.projectPortfolio.headers.tasks'), key: 'tasks' },
  { title: t('components.taskManager.projectPortfolio.headers.due'), key: 'due_date' },
  { title: t('components.taskManager.projectPortfolio.headers.status'), key: 'status' },
  { title: '', key: 'actions', sortable: false },
])
</script>

<template>
  <div>
    <VRow class="mb-3">
      <VCol
        cols="12"
        sm="4"
      >
        <VCard border>
          <VCardText>
            <div class="text-caption">
              {{ t('components.taskManager.projectPortfolio.total') }}
            </div><div class="text-h4 font-weight-bold">
              {{ projects.length }}
            </div>
          </VCardText>
        </VCard>
      </VCol><VCol
        cols="6"
        sm="4"
      >
        <VCard border>
          <VCardText>
            <div class="text-caption">
              {{ t('components.taskManager.projectPortfolio.active') }}
            </div><div class="text-h4 font-weight-bold text-success">
              {{ active }}
            </div>
          </VCardText>
        </VCard>
      </VCol><VCol
        cols="6"
        sm="4"
      >
        <VCard border>
          <VCardText>
            <div class="text-caption">
              {{ t('components.taskManager.projectPortfolio.attention') }}
            </div><div class="text-h4 font-weight-bold text-error">
              {{ overdue }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
    <VCard border>
      <VCardTitle>{{ t('components.taskManager.projectPortfolio.title') }}</VCardTitle><VCardSubtitle>{{ t('components.taskManager.projectPortfolio.subtitle') }}</VCardSubtitle>
      <ResponsiveDataTable
        :items="projects"
        :headers="headers"
      >
        <template #item.name="{ item }">
          <div class="font-weight-bold">
            {{ item.name }}
          </div><div
            class="text-caption text-medium-emphasis text-truncate"
            style="max-width:260px"
          >
            {{ item.description }}
          </div>
        </template>
        <template #item.applicant.name="{ item }">
          <div>{{ item.applicant?.name }}</div><div class="text-caption">
            {{ item.applicant?.contact }}
          </div>
        </template>
        <template #item.services="{ item }">
          <div class="d-flex flex-wrap ga-1">
            <VChip
              v-for="service in item.services?.slice(0, 2)"
              :key="service.id"
              size="x-small"
              variant="tonal"
            >
              {{ service.type }}
            </VChip><VChip
              v-if="(item.services?.length || 0) > 2"
              size="x-small"
            >
              +{{ item.services!.length - 2 }}
            </VChip>
          </div>
        </template>
        <template #item.tasks="{ item }">
          {{ taskCount(item) }}
        </template>
        <template #item.status="{ item }">
          <VChip
            :color="statusColor(item.status)"
            size="small"
            variant="tonal"
          >
            {{ statusLabel(item.status) }}
          </VChip>
        </template>
        <template #item.actions="{ item }">
          <VBtn
            size="small"
            variant="tonal"
            @click="emit('select', item.id)"
          >
            {{ t('components.taskManager.projectPortfolio.openWorkspace') }}
          </VBtn>
        </template>
      </ResponsiveDataTable>
    </VCard>
  </div>
</template>
