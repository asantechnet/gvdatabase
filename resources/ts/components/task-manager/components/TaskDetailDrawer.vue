<script setup lang="ts">
import QuillEditor from '@/components/QuillEditor.vue'
import { useTaskStore } from '@/stores/useTaskStore'
import { ref, watch } from 'vue'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { VForm } from 'vuetify/components/VForm'

const props = defineProps<{
  isOpen: boolean
}>()

const emit = defineEmits<{
  (e: 'update:isOpen', value: boolean): void
}>()

const store = useTaskStore()
const refEditTaskForm = ref<VForm>()
const isDeleteConfirmOpen = ref(false)

// Priority options
const priorityOptions = [
  { title: 'Low', value: 'low' },
  { title: 'Medium', value: 'medium' },
  { title: 'High', value: 'high' },
  { title: 'Urgent', value: 'urgent' }
]

// Local task data
const localTask = ref({
  title: '',
  description: '',
  priority: 'medium' as const,
  startDate: '',
  dueDate: '',
})

// Watch for changes in selected task
watch(() => store.selectedTask, (newTask) => {
  if (newTask) {
    localTask.value = {
      title: newTask.title,
      description: newTask.description,
      priority: newTask.priority,
      startDate: newTask.startDate,
      dueDate: newTask.dueDate,
    }
  }
}, { immediate: true })

const handleClose = () => {
  emit('update:isOpen', false)
}

const updateTask = async () => {
  if (!store.selectedTaskId) return

  const { valid } = await refEditTaskForm.value?.validate() || { valid: false }
  if (!valid) return

  try {
    await store.updateTask(store.selectedTaskId, {
      title: localTask.value.title,
      description: localTask.value.description,
      priority: localTask.value.priority,
      start_date: localTask.value.startDate,
      due_date: localTask.value.dueDate,
    })
    emit('update:isOpen', false)
  } catch (error) {
    console.error('Failed to update task:', error)
  }
}

const deleteTask = async () => {
  if (store.selectedTaskId) {
    try {
      await store.deleteTask(store.selectedTaskId)
      emit('update:isOpen', false)
    } catch (error) {
      console.error('Failed to delete task:', error)
    }
  }
}

const confirmDelete = () => {
  isDeleteConfirmOpen.value = true
}

const cancelDelete = () => {
  isDeleteConfirmOpen.value = false
}

const saveDescription = (htmlContent: string) => {
  if (store.selectedTaskId) {
    store.updateTask(store.selectedTaskId, { description: htmlContent })
  }
}
</script>

<template>
  <VNavigationDrawer v-model="props.isOpen" location="end" temporary :width="500" class="task-detail-drawer"
    @update:model-value="handleClose">
    <VCard elevation="0" class="h-100 d-flex flex-column">
      <VCardItem class="border-b">
        <template #title>
          <div class="d-flex align-center">
            <VIcon icon="tabler-edit" class="mr-2" />
            <span class="text-h6 font-weight-bold">Edit Task</span>
          </div>
        </template>
        <template #append>
          <VBtn icon="tabler-x" variant="text" size="small" @click="handleClose" />
        </template>
      </VCardItem>

      <PerfectScrollbar class="flex-grow-1">
        <VForm ref="refEditTaskForm" class="pa-5" @submit.prevent="updateTask">
          <VRow>
            <!-- Title -->
            <VCol cols="12">
              <VTextField v-model="localTask.title" label="Title" :rules="[v => !!v || 'Title is required']" required />
            </VCol>

            <!-- Priority -->
            <VCol cols="12">
              <VSelect v-model="localTask.priority" :items="priorityOptions" label="Priority" item-title="title"
                item-value="value" />
            </VCol>

            <!-- Dates -->
            <VCol cols="12" md="6">
              <VTextField v-model="localTask.startDate" label="Start Date" type="date" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="localTask.dueDate" label="Due Date" type="date" />
            </VCol>

            <!-- Description -->
            <VCol cols="12">
              <div class="mb-2 d-flex align-center gap-2">
                <VIcon icon="tabler-align-justified" size="20" />
                <span class="text-subtitle-1 font-weight-bold">Description</span>
              </div>

              <div class="quill-wrapper border rounded">
                <QuillEditor v-model:content="localTask.description" contentType="html" theme="snow" />
              </div>
            </VCol>
          </VRow>
        </VForm>
      </PerfectScrollbar>

      <VCardActions class="border-t pa-5 d-flex justify-space-between">
        <VBtn color="error" variant="tonal" prepend-icon="tabler-trash" @click="confirmDelete">
          Delete
        </VBtn>

        <div class="d-flex gap-2">
          <VBtn variant="outlined" color="secondary" @click="handleClose">
            Cancel
          </VBtn>
          <VBtn color="primary" @click="updateTask">
            Save
          </VBtn>
        </div>
      </VCardActions>
    </VCard>

    <!-- Delete Confirmation Dialog -->
    <VDialog v-model="isDeleteConfirmOpen" max-width="400" persistent>
      <VCard>
        <VCardTitle class="text-h6">
          Confirm Deletion
        </VCardTitle>
        <VCardText>
          Are you sure you want to delete this task? This action cannot be undone.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="outlined" color="secondary" @click="cancelDelete">
            Cancel
          </VBtn>
          <VBtn color="error" @click="deleteTask">
            Delete
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VNavigationDrawer>
</template>

<style scoped>
.task-detail-drawer {
  z-index: 1001;
}

.quill-wrapper {
  min-height: 250px;
}

:deep(.ql-toolbar) {
  border-top: none !important;
  border-left: none !important;
  border-right: none !important;
  background-color: rgb(var(--v-theme-surface));
}

:deep(.ql-container) {
  border-left: none !important;
  border-right: none !important;
  border-bottom: none !important;
  min-height: 200px;
}
</style>
