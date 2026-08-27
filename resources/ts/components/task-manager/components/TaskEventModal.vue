<script setup lang="ts">
import { useTaskStore } from '@/stores/useTaskStore';
import type { Task } from '@/types/taskManager';
import { computed, ref } from 'vue';

const props = defineProps<{
  isOpen: boolean
  task: Task | null
}>()

const emit = defineEmits(['update:isOpen'])

const store = useTaskStore()
const isConfirmDeleteOpen = ref(false)

// v-model computed binding for modal state
const modalModel = computed({
  get: () => props.isOpen,
  set: (val) => emit('update:isOpen', val)
})

// Priority badge color mapper
const priorityColor = computed(() => {
  if (!props.task) return 'primary'
  const map: Record<string, string> = {
    low: 'success',
    medium: 'info',
    high: 'warning',
    urgent: 'error'
  }
  return map[props.task.priority] || 'primary'
})

const closeModal = () => {
  modalModel.value = false
}

// Danger Action Handler
const triggerDeleteConfirmation = () => {
  isConfirmDeleteOpen.value = true
}

const confirmAndDelete = () => {
  if (props.task) {
    store.deleteTask(props.task.id)
    isConfirmDeleteOpen.value = false
    closeModal()
  }
}
</script>

<template>
  <div>
    <!-- Main Task Inspector Dialog (Read-Only) -->
    <VDialog v-model="modalModel" max-width="650" scrollable>
      <VCard v-if="task">
        <!-- Header -->
        <VCardItem class="bg-surface border-b pa-5">
          <template #title>
            <div class="d-flex align-center justify-space-between width-100">
              <div class="d-flex align-center gap-2">
                <VChip :color="priorityColor" size="small" class="text-uppercase font-weight-bold">
                  {{ $t('components.taskManager.event.priority', { priority: translateBackendValue(task.priority) }) }}
                </VChip>
                <VChip color="secondary" variant="tonal" size="small">
                  {{ task.boardId.replace('_', ' ').toUpperCase() }}
                </VChip>
              </div>
              <VBtn icon="tabler-x" variant="text" size="small" color="secondary" @click="closeModal" />
            </div>
          </template>
        </VCardItem>

        <VCardText class="pa-5">
          <!-- Title -->
          <h4 class="text-h4 font-weight-bold mb-3 text-high-emphasis">
            {{ task.title }}
          </h4>

          <!-- Timeline Metrics -->
          <div class="d-flex align-center gap-4 mb-6 pa-3 bg-grey-50 rounded border">
            <div class="d-flex align-center gap-2">
              <VIcon icon="tabler-calendar" size="20" color="primary" />
              <span class="text-body-2 font-weight-medium">
                {{ $t('components.taskManager.event.dateRange', { start: formatLocalizedDate(`${task.startDate}T00:00:00`), end: formatLocalizedDate(`${task.dueDate}T00:00:00`) }) }}
              </span>
            </div>
            <VSpacer />
            <div class="d-flex align-center gap-2">
              <VIcon icon="tabler-paperclip" size="20" color="secondary" />
              <span class="text-body-2 text-medium-emphasis">
                {{ $t('components.taskManager.event.dependencyCount', { count: task.dependencies.length }) }}
              </span>
            </div>
          </div>

          <!-- Tags & Assignees -->
          <VRow class="mb-4">
            <VCol cols="12" sm="6" v-if="task.tags.length">
              <div class="text-caption text-medium-emphasis mb-1 font-weight-bold">{{ $t('common.fields.tags') }}</div>
              <div class="d-flex flex-wrap gap-1">
                <VChip v-for="tag in task.tags" :key="tag.id" :color="tag.color" size="small">
                  {{ tag.name }}
                </VChip>
              </div>
            </VCol>

            <VCol cols="12" sm="6" v-if="task.assignees.length">
              <div class="text-caption text-medium-emphasis mb-1 font-weight-bold">{{ $t('components.taskManager.taskeventmodal.assignedTO') }}</div>
              <div class="d-flex align-center gap-2">
                <VAvatar v-for="user in task.assignees" :key="user.id" size="30" color="primary" variant="tonal">
                  <span class="text-caption font-weight-bold">{{ user.name.charAt(0) }}</span>
                </VAvatar>
              </div>
            </VCol>
          </VRow>

          <!-- Progress Visualizer -->
          <div class="mb-6">
            <div class="d-flex justify-space-between align-center mb-1">
              <span class="text-subtitle-2 font-weight-bold">{{ $t('components.taskManager.taskeventmodal.overallProgress') }}</span>
              <span class="text-caption font-weight-bold">{{ task.progress }}%</span>
            </div>
            <VProgressLinear :model-value="task.progress" :color="task.progress === 100 ? 'success' : 'primary'"
              height="8" rounded />
          </div>

          <VDivider class="my-5" />

          <!-- Raw HTML task content -->
          <div class="text-subtitle-2 font-weight-bold mb-2">{{ $t('components.taskManager.taskeventmodal.descriptionScope') }}</div>
          <div v-if="task.description" class="task-html-preview pa-4 rounded bg-surface border text-body-1" v-html="task.description"></div>
          <div v-else class="task-html-preview pa-4 rounded bg-surface border text-body-1">
            <em class="text-disabled">{{ $t('components.taskManager.taskeventmodal.noDescriptionProvided') }}</em>
          </div>
        </VCardText>

        <!-- Actions -->
        <VCardActions class="pa-5 border-t bg-grey-50 d-flex justify-space-between">
          <VBtn color="error" variant="tonal" prepend-icon="tabler-trash" @click="triggerDeleteConfirmation">
            {{ $t('common.messages.deleteTask') }}
          </VBtn>

          <VBtn color="secondary" variant="outlined" @click="closeModal">
            {{ $t('common.actions.close') }}
          </VBtn>
        </VCardActions>
      </VCard>
      </Dialog>

      <!-- Vuexy Safety Guard Warning Dialog -->
      <VDialog v-model="isConfirmDeleteOpen" max-width="450" persistent>
        <VCard class="text-center pa-4">
          <VCardText>
            <VAvatar color="error" variant="tonal" size="70" class="mb-4">
              <VIcon icon="tabler-alert-triangle" size="40" />
            </VAvatar>

            <h5 class="text-h5 font-weight-bold mb-2">{{ $t('common.confirmations.areYouSure') }}</h5>
            <p class="text-body-2 text-medium-emphasis mb-0">
              {{ $t('components.taskManager.taskeventmodal.youAreAboutToPermanentlyDelete') }} <strong>"{{ task?.title }}"</strong>{{ $t('common.confirmations.thisActionWillDestroyAllAttachedSubTasksAndCannotBeUndone') }}
            </p>
          </VCardText>

          <VCardActions class="d-flex justify-center gap-3 pt-2">
            <VBtn color="secondary" variant="outlined" @click="isConfirmDeleteOpen = false">
              {{ $t('common.actions.cancel') }}
            </VBtn>

            <VBtn color="error" variant="elevated" prepend-icon="tabler-trash" @click="confirmAndDelete">
              {{ $t('components.taskManager.taskeventmodal.yesDeleteIt') }}
            </VBtn>
          </VCardActions>
        </VCard>
      </VDialog>
  </div>
</template>

<style scoped>
.task-html-preview :deep(img) {
  max-width: 100%;
  border-radius: 6px;
}
</style>
