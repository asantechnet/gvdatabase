<script setup lang="ts">
import EmployeeApi from '@/api/employee'
import TagApi from '@/api/tag'
import { useTaskStore } from '@/stores/useTaskStore'
import type { EditKanbanItem, KanbanItem } from '@db/apps/kanban/types'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { VForm } from 'vuetify/components/VForm'

interface Emit {
  (e: 'update:isDrawerOpen', value: boolean): void
  (e: 'update:kanbanItem', value: EditKanbanItem): void
  (e: 'deleteKanbanItem', value: EditKanbanItem): void
}

type EmployeeOption = {
  id: number
  name: string
  avatarUrl: string | null
}

const props = withDefaults(defineProps<{
  kanbanItem?: EditKanbanItem | undefined
  isDrawerOpen: boolean
}>(), {
  kanbanItem: () => ({
    item: {
      title: '',
      dueDate: '2022-01-01T00:00:00Z',
      labels: [],
      members: [],
      id: 0,
      attachments: 0,
      commentsCount: 0,
      image: '',
      comments: '',
    },
    boardId: 0,
    boardName: '',
  }),
})

const emit = defineEmits<Emit>()
const store = useTaskStore()
const refEditTaskForm = ref<VForm>()
const employees = ref<EmployeeOption[]>([])
const isLoadingEmployees = ref(false)

// Get current task from store safely
const currentTask = computed(() => {
  return store.tasks?.find(task => task.id === String(props.kanbanItem?.item.id))
})

// Initialize local kanban item
const localKanbanItem = ref<KanbanItem & { members: EmployeeOption[] }>({
  ...props.kanbanItem.item,
  labels: currentTask.value?.tags?.map(tag => tag.name) || [],
  members: currentTask.value?.assignees
    ?.map(user => employees.value.find(employee => employee.id.toString() === user.id) || null)
    .filter((employee): employee is EmployeeOption => employee !== null) || []
})

// Fetch employees from API
const fetchEmployees = async () => {
  try {
    isLoadingEmployees.value = true
    const response = await EmployeeApi.getAll({ perPage: 100 })
    employees.value = response.data.data.map(emp => ({
      id: emp.id,
      name: emp.name,
      avatarUrl: emp.avatar_url || null
    }))

    if (currentTask.value) {
      localKanbanItem.value.members = currentTask.value.assignees
        ?.map(user => employees.value.find(employee => employee.id.toString() === user.id) || null)
        .filter((employee): employee is EmployeeOption => employee !== null) || []
    }
  } catch (error) {
    console.error('Failed to fetch employees:', error)
  } finally {
    isLoadingEmployees.value = false
  }
}

const getEmployeeInitial = (name: string) => {
  return name.trim().charAt(0).toUpperCase() || '?'
}

const resolveLabelColor = (color: string) => {
  const colorMap: Record<string, string> = {
    'error': 'error',
    'primary': 'primary',
    'info': 'info',
    'warning': 'warning',
    'secondary': 'secondary',
    'success': 'success'
  }
  return colorMap[color] || 'primary'
}

const handleDrawerModelValueUpdate = (val: boolean) => {
  emit('update:isDrawerOpen', val)
  if (!val) {
    refEditTaskForm.value?.reset()
  }
}

watch(() => props.kanbanItem, () => {
  if (props.kanbanItem) {
    const task = store.tasks?.find(t => t.id === String(props.kanbanItem?.item.id))
    if (task) {
      localKanbanItem.value = {
        ...props.kanbanItem.item,
        title: task.title,
        dueDate: task.dueDate,
        labels: task.tags?.map(tag => tag.name) || [],
        members: task.assignees
          ?.map(user => employees.value.find(employee => employee.id.toString() === user.id) || null)
          .filter((employee): employee is EmployeeOption => employee !== null) || []
      }
    }
  }
}, { deep: true })

const updateKanbanItem = async () => {
  if (!props.kanbanItem || !currentTask.value) return

  const { valid } = await refEditTaskForm.value?.validate() || { valid: false }
  if (!valid) return

  try {
    // Get tag IDs for selected labels
    const tagIds = localKanbanItem.value.labels.map(label => {
      const tag = store.tags.find(t => t.name === label)
      return tag ? tag.id : null
    }).filter(id => id !== null) as string[]

    // Get user IDs for selected members
    const userIds = localKanbanItem.value.members.map(member => {
      return member?.id ? String(member.id) : null
    }).filter(id => id !== null) as string[]

    // Update task in Pinia store
    await store.updateTask(currentTask.value.id, {
      title: localKanbanItem.value.title,
      due_date: localKanbanItem.value.dueDate,
      tags: tagIds,
      assignees: userIds
    })

    // Emit updated payload
    emit('update:kanbanItem', {
        item: {
          ...localKanbanItem.value,
          id: props.kanbanItem.item.id,
          labels: localKanbanItem.value.labels,
          members: localKanbanItem.value.members.map(member => ({
            id: member.id.toString(),
            name: member.name,
            avatar: member.avatarUrl || '',
            email: ''
          }))
        },
      boardId: props.kanbanItem.boardId,
      boardName: props.kanbanItem.boardName
    })

    emit('update:isDrawerOpen', false)
    await nextTick()
    refEditTaskForm.value?.reset()
  } catch (error) {
    console.error('Failed to update task:', error)
  }
}

const deleteKanbanItem = () => {
  if (!props.kanbanItem) return
  emit('deleteKanbanItem', props.kanbanItem)
  emit('update:isDrawerOpen', false)
}

onMounted(() => {
  fetchEmployees()
  store.fetchTags()
})
</script>

<template>
  <VNavigationDrawer data-allow-mismatch location="end" :width="370" temporary border="0"
    :model-value="props.isDrawerOpen" @update:model-value="handleDrawerModelValueUpdate"
    style="z-index: 99999 !important; padding-top: 1em !important; height: 100vh !important; position: fixed !important; top: 0;">
    <!-- 👉 Header -->
    <AppDrawerHeaderSection title="Edit Task" @cancel="$emit('update:isDrawerOpen', false)" />

    <VDivider />

    <PerfectScrollbar :options="{ wheelPropagation: false }" style="block-size: calc(100vh - 4rem);">
      <VForm v-if="localKanbanItem" ref="refEditTaskForm" @submit.prevent="updateKanbanItem">
        <VCardText class="kanban-editor-drawer">
          <VRow>
            <!-- Title -->
            <VCol cols="12">
              <AppTextField v-model="localKanbanItem.title" label="Title" :rules="[requiredValidator]" />
            </VCol>

            <!-- Due Date -->
            <VCol cols="12">
              <AppDateTimePicker v-model="localKanbanItem.dueDate" label="Due date" :config="config" />
            </VCol>

            <!-- Labels -->
            <VCol cols="12">
              <AppSelect v-model="localKanbanItem.labels" :items="store.tags" item-title="name" item-value="name"
                label="Label" multiple eager>
                <template #chip="{ item }">
                  <VChip :color="resolveLabelColor(item.raw.color)">
                    {{ item.raw.name }}
                  </VChip>
                </template>
              </AppSelect>
            </VCol>

            <!-- Assignees -->
            <VCol cols="12">
              <p class="mb-1 text-body-2 text-high-emphasis" style="line-height: 15px;">
                Assigned
              </p>

              <div>
                <VSelect v-model="localKanbanItem.members" :items="employees" item-title="name" item-value="id"
                  multiple return-object variant="plain" :loading="isLoadingEmployees" :menu-props="{
                    offset: 10,
                  }" class="assignee-select">
                  <template #selection="{ item }">
                    <VAvatar size="26" color="primary" variant="tonal">
                      <VImg v-if="item.raw.avatarUrl" :src="item.raw.avatarUrl" :alt="item.raw.name" />
                      <span v-else class="text-caption font-weight-medium">
                        {{ getEmployeeInitial(item.raw.name) }}
                      </span>
                      <VTooltip activator="parent">
                        {{ item.raw.name }}
                      </VTooltip>
                    </VAvatar>
                  </template>

                  <template #prepend-inner>
                    <IconBtn size="26" variant="tonal" color="secondary">
                      <VIcon size="20" icon="tabler-plus" />
                    </IconBtn>
                  </template>

                  <template #item="{ props, item }">
                    <VListItem v-bind="props">
                      <template #prepend>
                        <VAvatar size="32" color="primary" variant="tonal">
                          <VImg v-if="item.raw.avatarUrl" :src="item.raw.avatarUrl" :alt="item.raw.name" />
                          <span v-else class="text-caption font-weight-medium">
                            {{ getEmployeeInitial(item.raw.name) }}
                          </span>
                        </VAvatar>
                      </template>
                    </VListItem>
                  </template>
                </VSelect>
              </div>
            </VCol>

            <!-- Form Actions -->
            <VCol cols="12" class="pt-4">
              <VBtn type="submit" class="me-4">
                Update
              </VBtn>
              <VBtn color="error" variant="tonal" @click="deleteKanbanItem">
                Delete
              </VBtn>
            </VCol>
          </VRow>
        </VCardText>
      </VForm>
    </PerfectScrollbar>
  </VNavigationDrawer>
</template>

<style lang="scss">
.kanban-editor-drawer {
  .assignee-select {
    .v-field__append-inner {
      .v-select__menu-icon {
        display: none;
      }
    }
  }
}
</style>
