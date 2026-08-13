<script setup lang="ts">
import QuillEditor from '@/components/QuillEditor.vue'
import { useTaskStore } from '@/stores/useTaskStore'
import { canAccess } from '@/utils/authorization'
import { computed, ref, watch } from 'vue'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { VForm } from 'vuetify/components/VForm'
import type { TaskPriority } from '@/types/taskManager'
import TaskApi from '@/api/task'

const props = defineProps<{
  isOpen: boolean
}>()

const emit = defineEmits<{
  (e: 'update:isOpen', value: boolean): void
}>()

const store = useTaskStore()
const refEditTaskForm = ref<VForm>()
const isDeleteConfirmOpen = ref(false)
const canEditTask = () => canAccess('edit_assigned_tasks')
const canDeleteTask = () => canAccess('delete_assigned_tasks')
const canReviewBonus = () => canAccess('edit_all_tasks')
const bonusQuality = ref(3)
const markRejected = ref(false)
const contributions = ref<Record<string, number>>({})
const bonusReviewError = ref('')
const isSavingBonusReview = ref(false)

// Priority options
const priorityOptions = [
  { title: translate('components.taskManager.taskdetaildrawer.low'), value: 'low' },
  { title: translate('components.taskManager.taskdetaildrawer.medium'), value: 'medium' },
  { title: translate('components.taskManager.taskdetaildrawer.high'), value: 'high' },
  { title: translate('components.taskManager.taskdetaildrawer.urgent'), value: 'urgent' }
]

// Local task data
const localTask = ref({
  title: '',
  description: '',
  priority: 'medium' as TaskPriority,
  startDate: '',
  dueDate: '',
  dependencies: [] as string[],
})

const dependencyOptions = computed(() => {
  return store.tasks
    .filter(task => task.id !== store.selectedTaskId)
    .map(task => ({
      title: task.title,
      value: task.id,
    }))
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
      dependencies: [...newTask.dependencies],
    }
    bonusQuality.value = newTask.managerQualityScore ?? 3
    markRejected.value = false
    const equalShare = newTask.assignees.length ? 100 / newTask.assignees.length : 0
    contributions.value = Object.fromEntries(newTask.assignees.map(assignee => [assignee.id, assignee.contributionPercent ?? equalShare]))
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
      dependencies: localTask.value.dependencies,
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

const saveBonusReview = async () => {
  if (!store.selectedTaskId || !store.selectedTask) return
  bonusReviewError.value = ''
  const total = Object.values(contributions.value).reduce((sum, value) => sum + Number(value || 0), 0)
  if (Math.abs(total - 100) > 0.01) {
    bonusReviewError.value = translate('components.taskManager.taskdetaildrawer.contributionPercentagesMustTotal100')
    return
  }
  isSavingBonusReview.value = true
  try {
    const updated = await TaskApi.reviewBonusInputs(store.selectedTaskId, {
      quality_score: bonusQuality.value,
      rejected: markRejected.value,
      contributions: contributions.value,
    })
    Object.assign(store.selectedTask, updated)
    markRejected.value = false
  } catch (error: any) {
    bonusReviewError.value = error?.response?.data?.message ?? 'Unable to save the bonus review.'
  } finally {
    isSavingBonusReview.value = false
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
            <span class="text-h6 font-weight-bold">{{ $t('common.messages.editTask') }}</span>
            <TaskCompletionChip v-if="store.selectedTask" :progress="store.selectedTask.progress" class="ms-3" />
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
              <VTextField v-model="localTask.title" :label="$t('common.fields.title')" :rules="[v => !!v || 'Title is required']" :disabled="!canEditTask()" required />
            </VCol>

            <VCol v-if="canReviewBonus() && store.selectedTask?.progress === 100" cols="12">
              <VDivider class="my-3" />
              <div class="text-subtitle-1 font-weight-bold mb-3">{{ $t('components.taskManager.taskdetaildrawer.bonusCalculationInputs') }}</div>
              <VAlert v-if="bonusReviewError" type="error" variant="tonal" class="mb-3">{{ bonusReviewError }}</VAlert>
              <VSlider v-model="bonusQuality" min="1" max="5" step="0.5" :label="$t('components.taskManager.taskdetaildrawer.managerQuality')" thumb-label="always" />
              <div class="text-caption text-medium-emphasis mb-2">{{ $t('components.taskManager.taskdetaildrawer.participationMustTotal100TheseValuesAreSnapshottedWhenTheMonthlyCalcul') }}</div>
              <VTextField v-for="assignee in store.selectedTask.assignees" :key="assignee.id" v-model.number="contributions[assignee.id]" type="number" min="0" max="100" suffix="%" :label="assignee.name" density="compact" />
              <VCheckbox v-model="markRejected" :label="$t('components.taskManager.taskdetaildrawer.recordOneRejectionPenalty')" color="warning" hide-details />
              <VBtn color="primary" variant="tonal" class="mt-3" :loading="isSavingBonusReview" @click="saveBonusReview">{{ $t('components.taskManager.taskdetaildrawer.saveBonusInputs') }}</VBtn>
            </VCol>

            <!-- Priority -->
            <VCol cols="12">
              <VSelect v-model="localTask.priority" :items="priorityOptions" :label="$t('common.fields.priority')" item-title="title"
                item-value="value" :disabled="!canEditTask()" />
            </VCol>

            <!-- Dates -->
            <VCol cols="12" md="6">
              <VTextField v-model="localTask.startDate" :label="$t('common.fields.startDate')" type="date" :disabled="!canEditTask()" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="localTask.dueDate" :label="$t('components.taskManager.taskdetaildrawer.dueDate')" type="date" :disabled="!canEditTask()" />
            </VCol>

            <!-- Gantt relationships -->
            <VCol cols="12">
              <VSelect
                v-model="localTask.dependencies"
                :items="dependencyOptions"
                item-title="title"
                item-value="value"
                :label="$t('components.taskManager.taskdetaildrawer.dependsOn')"
                multiple
                chips
                closable-chips
                :hint="$t('components.taskManager.taskdetaildrawer.selectTasksThatMustBeCompletedBeforeThisOne')"
                persistent-hint
                :disabled="!canEditTask()"
              />
            </VCol>

            <!-- Description -->
            <VCol cols="12">
              <div class="mb-2 d-flex align-center gap-2">
                <VIcon icon="tabler-align-justified" size="20" />
                <span class="text-subtitle-1 font-weight-bold">{{ $t('common.fields.description') }}</span>
              </div>

              <div class="quill-wrapper border rounded">
                <QuillEditor v-if="canEditTask()" v-model:content="localTask.description" contentType="html" theme="snow" />
                <div v-else v-html="localTask.description" />
              </div>
            </VCol>
          </VRow>
        </VForm>
      </PerfectScrollbar>

      <VCardActions class="border-t pa-5 d-flex justify-space-between">
        <VBtn v-if="canDeleteTask()" color="error" variant="tonal" prepend-icon="tabler-trash" @click="confirmDelete">
          {{ $t('common.actions.delete') }}
        </VBtn>

        <div class="d-flex gap-2">
          <VBtn variant="outlined" color="secondary" @click="handleClose">
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn v-if="canEditTask()" color="primary" @click="updateTask">
            {{ $t('common.actions.save') }}
          </VBtn>
        </div>
      </VCardActions>
    </VCard>

    <!-- Delete Confirmation Dialog -->
    <VDialog v-model="isDeleteConfirmOpen" max-width="400" persistent>
      <VCard>
        <VCardTitle class="text-h6">
          {{ $t('common.messages.confirmDeletion') }}
        </VCardTitle>
        <VCardText>
          {{ $t('common.confirmations.areYouSureYouWantToDeleteThisTaskThisActionCannotBeUndone') }}
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="outlined" color="secondary" @click="cancelDelete">
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn color="error" @click="deleteTask">
            {{ $t('common.actions.delete') }}
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
  border-inline-start: none !important;
  border-inline-end: none !important;
  background-color: rgb(var(--v-theme-surface));
}

:deep(.ql-container) {
  border-inline-start: none !important;
  border-inline-end: none !important;
  border-bottom: none !important;
  min-height: 200px;
}
</style>
