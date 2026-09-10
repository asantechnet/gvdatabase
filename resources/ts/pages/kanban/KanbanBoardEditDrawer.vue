<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { VForm } from 'vuetify/components/VForm'
import TaskAssigneeApi, { type TaskAssigneeOption } from '@/api/taskAssignee'
import TagApi from '@/api/tag'
import { useTaskStore } from '@/stores/useTaskStore'
import { canAccess } from '@/utils/authorization'
import type { EditKanbanItem, KanbanItem } from '@/types/legacyKanban'
import KanbanTaskEditForm from '@/components/forms/KanbanTaskEditForm.vue'

interface Emit {
  (e: 'update:isDrawerOpen', value: boolean): void
  (e: 'update:kanbanItem', value: EditKanbanItem): void
  (e: 'deleteKanbanItem', value: EditKanbanItem): void
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
const config = { enableTime: false, dateFormat: 'Y-m-d' }
const employees = ref<TaskAssigneeOption[]>([])
const isLoadingEmployees = ref(false)
const canEditTask = () => canAccess('edit_assigned_tasks')
const canAssignMembers = () => canAccess('assign_task_members')
const canManageTags = () => canAccess('manage_task_tags')
const canDeleteTask = () => canAccess('delete_assigned_tasks')

// Get current task from store safely
const currentTask = computed(() => {
  return store.tasks?.find(task => task.id === String(props.kanbanItem?.item.id))
})

// Initialize local kanban item
const localKanbanItem = ref<KanbanItem & { members: TaskAssigneeOption[] }>({
  ...props.kanbanItem.item,
  labels: currentTask.value?.tags?.map(tag => tag.name) || [],
  members: currentTask.value?.assignees
    ?.map(user => employees.value.find(employee => employee.id.toString() === user.id) || null)
    .filter((employee): employee is TaskAssigneeOption => employee !== null) || [],
})

// Fetch employees from API
const fetchEmployees = async () => {
  if (!canAssignMembers())
    return
  try {
    isLoadingEmployees.value = true
    employees.value = await TaskAssigneeApi.getAll()

    if (currentTask.value) {
      localKanbanItem.value.members = currentTask.value.assignees
        ?.map(user => employees.value.find(employee => employee.id.toString() === user.id) || null)
        .filter((employee): employee is TaskAssigneeOption => employee !== null) || []
    }
  }
  catch (error) {
    console.error('Failed to fetch employees:', error)
  }
  finally {
    isLoadingEmployees.value = false
  }
}

const getEmployeeInitial = (name: string) => {
  return name.trim().charAt(0).toUpperCase() || '?'
}

const resolveLabelColor = (color: string) => {
  const colorMap: Record<string, string> = {
    error: 'error',
    primary: 'primary',
    info: 'info',
    warning: 'warning',
    secondary: 'secondary',
    success: 'success',
  }

  return colorMap[color] || 'primary'
}

const handleDrawerModelValueUpdate = (val: boolean) => {
  emit('update:isDrawerOpen', val)
  if (!val)
    refEditTaskForm.value?.reset()
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
          .filter((employee): employee is TaskAssigneeOption => employee !== null) || [],
      }
    }
  }
}, { deep: true })

const updateKanbanItem = async (updatedItem = localKanbanItem.value) => {
  if (!props.kanbanItem || !currentTask.value)
    return

  localKanbanItem.value = updatedItem

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
      ...(canManageTags() ? { tags: tagIds } : {}),
      ...(canAssignMembers() ? { assignees: userIds } : {}),
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
          avatar: member.avatar || '',
          email: '',
        })),
      },
      boardId: props.kanbanItem.boardId,
      boardName: props.kanbanItem.boardName,
    })

    emit('update:isDrawerOpen', false)
    await nextTick()
    refEditTaskForm.value?.reset()
  }
  catch (error) {
    console.error('Failed to update task:', error)
  }
}

const deleteKanbanItem = () => {
  if (!props.kanbanItem)
    return
  emit('deleteKanbanItem', props.kanbanItem)
  emit('update:isDrawerOpen', false)
}

onMounted(() => {
  fetchEmployees()
  if (canManageTags())
    store.fetchTags()
})
</script>

<template>
  <VNavigationDrawer
    data-allow-mismatch
    location="end"
    :width="370"
    temporary
    border="0"
    :model-value="props.isDrawerOpen"
    style="z-index: 99999 !important; padding-top: 1em !important; height: 100vh !important; position: fixed !important; top: 0;"
    @update:model-value="handleDrawerModelValueUpdate"
  >
    <!-- 👉 Header -->
    <AppDrawerHeaderSection
      :title="$t('common.messages.editTask')"
      @cancel="$emit('update:isDrawerOpen', false)"
    />

    <VDivider />

    <PerfectScrollbar
      :options="{ wheelPropagation: false }"
      style="block-size: calc(100vh - 4rem);"
    >
      <KanbanTaskEditForm
        v-if="localKanbanItem"
        :item="localKanbanItem"
        :employees="employees"
        :tags="store.tags"
        :loading-employees="isLoadingEmployees"
        :can-edit="canEditTask()"
        :can-assign="canAssignMembers()"
        :can-manage-tags="canManageTags()"
        :can-delete="canDeleteTask()"
        @submit="updateKanbanItem"
        @delete="deleteKanbanItem"
      />
      <VForm
        v-if="false"
        ref="refEditTaskForm"
        class="app-form"
        @submit.prevent="() => updateKanbanItem()"
      >
        <VCardText class="kanban-editor-drawer">
          <VRow>
            <!-- Title -->
            <VCol cols="12">
              <AppTextField
                v-model="localKanbanItem.title"
                :label="$t('common.fields.title')"
                :rules="[requiredValidator]"
                :disabled="!canEditTask()"
              />
            </VCol>

            <!-- Due Date -->
            <VCol cols="12">
              <AppDateTimePicker
                v-model="localKanbanItem.dueDate"
                :label="$t('common.messages.dueDate')"
                :config="config"
                :disabled="!canEditTask()"
              />
            </VCol>

            <!-- Labels -->
            <VCol
              v-if="canManageTags()"
              cols="12"
            >
              <AppSelect
                v-model="localKanbanItem.labels"
                :items="store.tags"
                item-title="name"
                item-value="name"
                :label="$t('common.messages.label')"
                multiple
                eager
              >
                <template #chip="{ item }">
                  <VChip :color="resolveLabelColor(item.raw.color)">
                    {{ item.raw.name }}
                  </VChip>
                </template>
              </AppSelect>
            </VCol>

            <!-- Assignees -->
            <VCol
              v-if="canAssignMembers()"
              cols="12"
            >
              <p
                class="mb-1 text-body-2 text-high-emphasis"
                style="line-height: 15px;"
              >
                {{ $t('pages.tasks.kanbanboardeditdrawer.kanbanboardeditdrawer.assigned') }}
              </p>

              <div>
                <VSelect
                  v-model="localKanbanItem.members"
                  :items="employees"
                  item-title="name"
                  item-value="id"
                  multiple
                  return-object
                  variant="plain"
                  :loading="isLoadingEmployees"
                  :menu-props="{
                    offset: 10,
                  }"
                  class="assignee-select"
                >
                  <template #selection="{ item }">
                    <VAvatar
                      size="26"
                      color="primary"
                      variant="tonal"
                    >
                      <VImg
                        v-if="item.raw.avatar"
                        :src="item.raw.avatar || undefined"
                        :alt="item.raw.name"
                      />
                      <span
                        v-else
                        class="text-caption font-weight-medium"
                      >
                        {{ getEmployeeInitial(item.raw.name) }}
                      </span>
                      <VTooltip activator="parent">
                        {{ item.raw.name }}
                      </VTooltip>
                    </VAvatar>
                  </template>

                  <template #prepend-inner>
                    <IconBtn
                      size="26"
                      variant="tonal"
                      color="secondary"
                    >
                      <VIcon
                        size="20"
                        icon="tabler-plus"
                      />
                    </IconBtn>
                  </template>

                  <template #item="{ props, item }">
                    <VListItem v-bind="props">
                      <template #prepend>
                        <VAvatar
                          size="32"
                          color="primary"
                          variant="tonal"
                        >
                          <VImg
                            v-if="item.raw.avatar"
                            :src="item.raw.avatar || undefined"
                            :alt="item.raw.name"
                          />
                          <span
                            v-else
                            class="text-caption font-weight-medium"
                          >
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
            <VCol
              cols="12"
              class="pt-4"
            >
              <VBtn
                v-if="canEditTask()"
                type="submit"
                class="me-4"
              >
                {{ $t('common.actions.update') }}
              </VBtn>
              <VBtn
                v-if="canDeleteTask()"
                color="error"
                variant="tonal"
                @click="deleteKanbanItem"
              >
                {{ $t('common.actions.delete') }}
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
