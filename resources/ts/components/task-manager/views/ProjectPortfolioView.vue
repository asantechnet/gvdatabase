<script setup lang="ts">
import type { Project } from '@/api/project'

const props = defineProps<{ projects: Project[] }>()
const emit = defineEmits<{ select: [id: number] }>()
const active = computed(() => props.projects.filter(project => project.status === 'active').length)
const overdue = computed(() => props.projects.filter(project => project.status !== 'completed' && project.due_date && new Date(project.due_date) < new Date()).length)
const taskCount = (project: Project) => project.boards?.reduce((sum, board) => sum + (board.tasks?.length ?? 0), 0) ?? 0
const statusColor = (status: string) => ({ active: 'success', on_hold: 'warning', completed: 'primary', archived: 'secondary' }[status] || 'secondary')
</script>

<template>
  <div>
    <VRow class="mb-3"><VCol cols="12" sm="4"><VCard border><VCardText><div class="text-caption">Total portfolio</div><div class="text-h4 font-weight-bold">{{ projects.length }}</div></VCardText></VCard></VCol><VCol cols="6" sm="4"><VCard border><VCardText><div class="text-caption">Active delivery</div><div class="text-h4 font-weight-bold text-success">{{ active }}</div></VCardText></VCard></VCol><VCol cols="6" sm="4"><VCard border><VCardText><div class="text-caption">Needs attention</div><div class="text-h4 font-weight-bold text-error">{{ overdue }}</div></VCardText></VCard></VCol></VRow>
    <VCard border><VCardTitle>Project portfolio</VCardTitle><VCardSubtitle>Customer ownership, inferred services, schedule, and workload in one view.</VCardSubtitle>
      <ResponsiveDataTable :items="projects" :headers="[{title:'Project',key:'name'},{title:'Customer',key:'customer.name'},{title:'Services',key:'services'},{title:'Tasks',key:'tasks'},{title:'Due',key:'due_date'},{title:'Status',key:'status'},{title:'',key:'actions',sortable:false}]">
        <template #item.name="{ item }"><div class="font-weight-bold">{{ item.name }}</div><div class="text-caption text-medium-emphasis text-truncate" style="max-width:260px">{{ item.description }}</div></template>
        <template #item.customer.name="{ item }"><div>{{ item.customer?.name }}</div><div class="text-caption">{{ item.customer?.company }}</div></template>
        <template #item.services="{ item }"><div class="d-flex flex-wrap ga-1"><VChip v-for="service in item.services?.slice(0, 2)" :key="service.id" size="x-small" variant="tonal">{{ service.type }}</VChip><VChip v-if="(item.services?.length || 0) > 2" size="x-small">+{{ item.services!.length - 2 }}</VChip></div></template>
        <template #item.tasks="{ item }">{{ taskCount(item) }}</template>
        <template #item.status="{ item }"><VChip :color="statusColor(item.status)" size="small" variant="tonal">{{ item.status.replace('_', ' ') }}</VChip></template>
        <template #item.actions="{ item }"><VBtn size="small" variant="tonal" @click="emit('select', item.id)">Open workspace</VBtn></template>
      </ResponsiveDataTable>
    </VCard>
  </div>
</template>
