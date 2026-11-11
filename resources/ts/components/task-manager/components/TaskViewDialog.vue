<script setup lang="ts">
import { computed } from 'vue'
import EmployeeAvatar from '@/components/hr/EmployeeAvatar.vue'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import TaskCompletionChip from '../TaskCompletionChip.vue'
import { useTaskStore } from '@/stores/useTaskStore'
import TaskApi from '@/api/task'
import type { TaskAttachment } from '@/api/kanban'
import { formatActiveDate } from '@/utils/localization'

const props = defineProps<{
  isOpen: boolean
}>()

const emit = defineEmits<{
  (e: 'update:isOpen', value: boolean): void
}>()

const store = useTaskStore()

const task = computed(() => store.selectedTask)

const boardTitle = computed(() => {
  return store.boards.find(board => board.id === task.value?.boardId)?.title ?? ''
})

const assignees = computed(() => task.value?.assignees ?? [])
const tags = computed(() => task.value?.tags ?? [])
const subtasks = computed(() => task.value?.subtasks ?? [])
const attachments = computed(() => task.value?.attachments ?? [])
const completedSubtasks = computed(() => subtasks.value.filter(subtask => subtask.isCompleted).length)

// Descriptions were historically stored as HTML by the rich-text editor.
// Render the readable text without injecting markup into the read-only view.
const descriptionText = computed(() => task.value?.description.replace(/<[^>]*>?/g, '').trim() ?? '')

const formatFileSize = (size: number) => size < 1024 * 1024
  ? `${Math.ceil(size / 1024)} KB`
  : `${(size / (1024 * 1024)).toFixed(1)} MB`

const close = () => emit('update:isOpen', false)

const handleModelValue = (value: boolean) => {
  if (!value)
    close()
}

const downloadAttachment = async (attachment: TaskAttachment) => {
  if (!task.value)
    return

  const response = await TaskApi.downloadAttachment(task.value.id, attachment.id)
  const url = URL.createObjectURL(response.data)
  const link = document.createElement('a')

  link.href = url
  link.download = attachment.name
  link.click()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <VDialog
    :model-value="props.isOpen"
    max-width="880"
    scrollable
    content-class="task-view-dialog"
    @update:model-value="handleModelValue"
  >
    <VCard
      v-if="task"
      elevation="0"
      class="task-view-card"
    >
      <VCardItem class="task-view-dialog__header pa-5">
        <template #title>
          <div class="d-flex flex-wrap align-center gap-2">
            <span class="text-h5 font-weight-bold task-view-title">{{ task.title }}</span>
            <TaskCompletionChip :progress="task.progress" />
          </div>
        </template>
        <template #subtitle>
          <div class="d-flex flex-wrap align-center gap-2 mt-2">
            <VChip
              v-if="boardTitle"
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-layout-kanban"
            >
              {{ boardTitle }}
            </VChip>
            <VChip
              size="small"
              variant="tonal"
              color="primary"
            >
              {{ $t(`components.taskManager.taskdetaildrawer.${task.priority}`) }}
            </VChip>
            <span
              v-if="task.dueDate"
              class="text-caption text-medium-emphasis d-inline-flex align-center gap-1"
            >
              <VIcon
                size="16"
                icon="tabler-calendar"
              />
              {{ $t('components.taskManager.kanbanview.dueDate') }}: {{ formatActiveDate(task.dueDate) }}
            </span>
          </div>
        </template>
        <template #append>
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="error"
            size="small"
            :aria-label="$t('common.actions.close')"
            :title="$t('common.actions.close')"
            @click="close"
          />
        </template>
      </VCardItem>

      <PerfectScrollbar class="task-view-scroll">
        <VCardText class="pa-5">
          <div class="task-view-meta mb-6">
            <div>
              <span class="task-view-meta__label">{{ $t('common.fields.board') }}</span>
              <span class="text-body-2 font-weight-medium">{{ boardTitle || $t('common.messages.notAvailable') }}</span>
            </div>
            <div>
              <span class="task-view-meta__label">{{ $t('common.fields.startDate') }}</span>
              <span class="text-body-2 font-weight-medium">{{ task.startDate ? formatActiveDate(task.startDate) : $t('common.messages.notAvailable') }}</span>
            </div>
            <div>
              <span class="task-view-meta__label">{{ $t('components.taskManager.kanbanview.dueDate') }}</span>
              <span class="text-body-2 font-weight-medium">{{ task.dueDate ? formatActiveDate(task.dueDate) : $t('common.messages.notAvailable') }}</span>
            </div>
          </div>

          <section
            v-if="assignees.length"
            class="task-view-section"
          >
            <h3 class="text-subtitle-2 font-weight-bold mb-3">
              {{ $t('components.taskManager.kanbanview.assignees') }}
            </h3>
            <div class="d-flex flex-wrap gap-3">
              <div
                v-for="assignee in assignees"
                :key="assignee.id"
                class="task-view-assignee"
              >
                <EmployeeAvatar :name="assignee.name" :src="assignee.avatar" size="36" />
                <div class="d-flex flex-column">
                  <span class="text-body-2 font-weight-medium">{{ assignee.name }}</span>
                  <span
                    v-if="assignee.email"
                    class="text-caption text-medium-emphasis"
                  >{{ assignee.email }}</span>
                </div>
              </div>
            </div>
          </section>

          <section
            v-if="tags.length"
            class="task-view-section"
          >
            <h3 class="text-subtitle-2 font-weight-bold mb-3">
              {{ $t('common.fields.tags') }}
            </h3>
            <div class="d-flex flex-wrap gap-2">
              <VChip
                v-for="tag in tags"
                :key="tag.id"
                color="primary"
                size="small"
                variant="tonal"
              >
                {{ tag.name }}
              </VChip>
            </div>
          </section>

          <section class="task-view-section">
            <h3 class="text-subtitle-2 font-weight-bold mb-3">
              {{ $t('components.taskManager.taskdetaildrawer.taskContent') }}
            </h3>
            <p
              v-if="descriptionText"
              class="task-view-description text-body-2 mb-0"
            >
              {{ descriptionText }}
            </p>
            <p
              v-else
              class="text-body-2 text-medium-emphasis mb-0"
            >
              {{ $t('components.taskManager.taskdetaildrawer.noTaskContent') }}
            </p>
          </section>

          <section
            v-if="subtasks.length"
            class="task-view-section"
          >
            <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-3">
              <h3 class="text-subtitle-2 font-weight-bold mb-0">
                {{ $t('common.ui.checklist') }}
              </h3>
              <span class="text-caption text-medium-emphasis">
                {{ $t('components.taskManager.kanbanview.subtasksProgress', {
                  completed: completedSubtasks,
                  total: subtasks.length,
                }) }}
              </span>
            </div>
            <VProgressLinear
              :model-value="task.progress"
              :color="task.progress === 100 ? 'success' : 'primary'"
              height="6"
              rounded
              class="mb-3"
            />
            <VList class="pa-0">
              <VListItem
                v-for="subtask in subtasks"
                :key="subtask.id"
                class="border rounded mb-2 px-3"
                :class="{ 'task-view-checklist__item--done': subtask.isCompleted }"
              >
                <template #prepend>
                  <VIcon
                    :icon="subtask.isCompleted ? 'tabler-circle-check-filled' : 'tabler-circle'"
                    :color="subtask.isCompleted ? 'success' : 'secondary'"
                    size="20"
                    class="me-2"
                  />
                </template>
                <VListItemTitle :class="{ 'text-disabled text-decoration-line-through': subtask.isCompleted }">
                  {{ subtask.title }}
                </VListItemTitle>
              </VListItem>
            </VList>
          </section>

          <section
            v-if="attachments.length"
            class="task-view-section"
          >
            <h3 class="text-subtitle-2 font-weight-bold mb-3">
              {{ $t('components.taskManager.kanbanview.attachments') }}
            </h3>
            <VList class="border rounded pa-0">
              <VListItem
                v-for="attachment in attachments"
                :key="attachment.id"
              >
                <template #prepend>
                  <VIcon
                    icon="tabler-paperclip"
                    size="20"
                  />
                </template>
                <VListItemTitle>{{ attachment.name }}</VListItemTitle>
                <VListItemSubtitle>{{ formatFileSize(attachment.size) }}</VListItemSubtitle>
                <template #append>
                  <VBtn
                    icon="tabler-download"
                    variant="text"
                    size="small"
                    :aria-label="$t('components.taskManager.kanbanview.downloadAttachment')"
                    :title="$t('components.taskManager.kanbanview.downloadAttachment')"
                    @click="downloadAttachment(attachment)"
                  />
                </template>
              </VListItem>
            </VList>
          </section>
        </VCardText>
      </PerfectScrollbar>

    </VCard>
    <VCard v-else class="task-view-card pa-5" aria-busy="true">
      <V-SkeletonLoader type="heading" class="mb-5" />
      <V-SkeletonLoader type="list-item-three-line" class="mb-4" />
      <V-SkeletonLoader type="paragraph" class="mb-4" />
      <V-SkeletonLoader type="list-item-two-line" />
    </VCard>
  </VDialog>
</template>

<style scoped>
.task-view-card {
  display: flex;
  overflow: hidden;
  flex-direction: column;
  max-block-size: calc(100vh - 48px);
}

:global(.v-overlay:has(.task-view-dialog) .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .3) !important; backdrop-filter: blur(7px); opacity: 1 !important; }
.task-view-dialog__header { border-block-end: 1px solid rgba(var(--v-border-color), .5); background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-primary)) 6%); }

.task-view-scroll {
  flex: 1 1 auto;
  min-block-size: 0;
  overflow-y: hidden;
}

.task-view-title {
  overflow-wrap: anywhere;
}

.task-view-meta {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0.75rem;
  padding: 1rem;
}
.task-view-meta > div { padding: .75rem; border-radius: .6rem; background: rgba(var(--v-theme-on-surface), .045); }
.task-view-meta > div:last-child { background: rgba(var(--v-theme-primary), .075); }

.task-view-meta__label {
  display: block;
  margin-block-end: 0.15rem;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.task-view-section {
  margin-block-end: 1.5rem;
  padding: 1rem;
  border: 1px solid rgba(var(--v-border-color), .42);
  border-radius: .8rem;
  background: rgba(var(--v-theme-on-surface), .03);
}

.task-view-section:nth-of-type(even) { background: rgba(var(--v-theme-primary), .045); }

.task-view-section:last-child {
  margin-block-end: 0;
}

.task-view-description {
  overflow-wrap: anywhere;
  white-space: pre-line;
}

.task-view-assignee {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-inline-size: 12rem;
}

.task-view-checklist__item--done {
  background: rgba(var(--v-theme-primary), 0.08);
}

@media (max-width: 600px) {
  .task-view-meta {
    grid-template-columns: 1fr 1fr;
    padding: 0.75rem;
  }

  .task-view-assignee {
    min-inline-size: 100%;
  }
}
</style>
