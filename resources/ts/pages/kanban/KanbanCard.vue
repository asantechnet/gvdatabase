<script setup lang="ts">
import { computed } from 'vue'
import type { EditKanbanItem, KanbanId, KanbanItem } from '@/types/legacyKanban'
import { canAccess, canDeleteTaskOnBoard, canEditTaskOnBoard } from '@/utils/authorization'
import { formatActiveDate } from '@/utils/localization'

const props = defineProps<{
  item: KanbanItem | undefined
  boardId: KanbanId
  boardName: string
  isPersonal?: boolean
  isOwned?: boolean
}>()

const emit = defineEmits<{
  (e: 'viewItem'): void
  (e: 'editItem'): void
  (e: 'editFullItem'): void
  (e: 'deleteKanbanItem', value: EditKanbanItem): void
  (e: 'commentsClick', value: EditKanbanItem): void
  (e: 'attachmentsClick', value: EditKanbanItem): void
}>()

const canEditTask = computed(() => canEditTaskOnBoard(props.isPersonal, props.isOwned))
const canComment = computed(() => canAccess('create_comments_on_assigned_tasks'))
const canDeleteTask = computed(() => canDeleteTaskOnBoard(props.isPersonal, props.isOwned))

const actionPayload = (): EditKanbanItem => ({
  item: props.item as KanbanItem,
  boardId: props.boardId,
  boardName: props.boardName,
})
</script>

<template>
  <VCard
    v-if="item"
    :ripple="false"
    :link="false"
    class="kanban-card position-relative"
    @click="emit('viewItem')"
  >
    <VCardText class="d-flex flex-column gap-2">
      <div
        class="d-flex align-start gap-2"
        :class="item.labels && item.labels.length ? 'justify-space-between' : 'justify-end'"
      >
        <div
          v-if="item.labels && item.labels.length"
          class="d-flex flex-wrap gap-2"
        >
          <VChip
            v-for="text in item.labels"
            :key="text"
            size="small"
            :color="item.labelColors?.[text] ?? 'primary'"
          >
            {{ text }}
          </VChip>
        </div>

        <VMenu
          location="bottom end"
          :close-on-content-click="true"
        >
          <template #activator="{ props: menuProps }">
            <VBtn
              v-bind="menuProps"
              icon
              variant="text"
              size="small"
              class="kanban-card__menu flex-shrink-0"
              :aria-label="$t('components.taskManager.kanbanview.taskActions')"
              :title="$t('components.taskManager.kanbanview.taskActions')"
              @click.stop
            >
              <VIcon
                icon="tabler-dots-vertical"
                size="18"
              />
            </VBtn>
          </template>

          <VList
            density="compact"
            min-width="210"
          >
            <VListItem @click="emit('viewItem')">
              <template #prepend>
                <VIcon
                  icon="tabler-eye"
                  size="18"
                />
              </template>
              <VListItemTitle>{{ $t('common.actions.viewFullDetails') }}</VListItemTitle>
            </VListItem>

            <VListItem
              v-if="canEditTask"
              @click="emit('editFullItem')"
            >
              <template #prepend>
                <VIcon
                  icon="tabler-file-description"
                  size="18"
                />
              </template>
              <VListItemTitle>{{ $t('common.messages.editTask') }}</VListItemTitle>
            </VListItem>

            <VListItem
              v-if="canComment"
              @click="emit('commentsClick', actionPayload())"
            >
              <template #prepend>
                <VIcon
                  icon="tabler-message-2"
                  size="18"
                />
              </template>
              <VListItemTitle>{{ $t('components.taskManager.taskcommentsdrawer.taskComments') }}</VListItemTitle>
            </VListItem>

            <VListItem @click="emit('attachmentsClick', actionPayload())">
              <template #prepend>
                <VIcon
                  icon="tabler-paperclip"
                  size="18"
                />
              </template>
              <VListItemTitle>{{ $t('components.taskManager.kanbanview.attachments') }}</VListItemTitle>
            </VListItem>

            <template v-if="canDeleteTask">
              <VDivider class="my-1" />
              <VListItem
                class="text-error"
                @click="emit('deleteKanbanItem', actionPayload())"
              >
                <template #prepend>
                  <VIcon
                    icon="tabler-trash"
                    size="18"
                  />
                </template>
                <VListItemTitle>{{ $t('common.messages.deleteTask') }}</VListItemTitle>
              </VListItem>
            </template>
          </VList>
        </VMenu>
      </div>

      <!-- Task Img -->
      <VImg
        v-if="item.image && item.image.length"
        :src="item.image"
        class="rounded"
      />

      <!-- Task title. The real button keeps keyboard access without nesting
           the menu button inside another button role. -->
      <p class="text-base text-high-emphasis mb-0">
        <button
          type="button"
          class="kanban-card__title"
          @click.stop="emit('viewItem')"
        >
          {{ item.title }}
        </button>
      </p>

      <!-- Description is clamped on the card; the complete text stays available
           on hover and on keyboard focus, without opening the task. -->
      <p
        v-if="item.comments"
        v-tooltip="item.comments"
        class="kanban-card__description text-body-2 text-medium-emphasis mb-0"
        tabindex="0"
      >
        {{ item.comments }}
      </p>

      <!-- Due date; assignees stay avatar-only so the card remains compact. -->
      <div
        v-if="item.dueDate"
        class="d-flex flex-wrap align-center gap-x-3 gap-y-1 text-caption text-medium-emphasis"
      >
        <span class="d-inline-flex align-center gap-1">
          <VIcon
            size="16"
            icon="tabler-calendar"
          />
          {{ $t('components.taskManager.kanbanview.dueDate') }}: {{ formatActiveDate(item.dueDate) }}
        </span>
      </div>

      <!-- Footer -->
      <div class="task-footer d-flex align-center flex-wrap justify-space-between">
        <div class="d-flex align-center gap-3">
          <div
            v-if="canComment"
            class="cursor-pointer d-flex align-center"
            @click.stop="emit('commentsClick', actionPayload())"
          >
            <VIcon
              size="20"
              icon="tabler-message-2"
              class="me-1 text-primary"
            />
            <span class="text-body-1 d-inline-block">{{ item.commentsCount ?? 0 }}</span>
          </div>
          <VBadge
            :model-value="Number((item as any).attachmentsCount ?? 0) > 0"
            :content="Number((item as any).attachmentsCount)"
            color="primary"
            location="top end"
            offset-x="2"
            offset-y="2"
          >
            <VBtn
              icon
              variant="tonal"
              color="secondary"
              size="small"
              class="kanban-attachment-button"
              @click.stop="emit('attachmentsClick', actionPayload())"
            >
              <VIcon
                icon="tabler-paperclip"
                size="18"
              />
              <VTooltip
                activator="parent"
                location="top"
              >
                {{ $t('components.taskManager.kanbanview.viewAttachments') }}
              </VTooltip>
            </VBtn>
          </VBadge>
          <TaskCompletionChip :progress="Number((item as any).progress ?? 0)" />
        </div>

        <div
          v-if="item.members && item.members.length"
          class="v-avatar-group"
        >
          <VAvatar
            v-for="avatar in item.members"
            :key="avatar.name"
            size="30"
            color="primary"
            variant="tonal"
          >
            <VImg
              v-if="avatar.img"
              :src="avatar.img"
              :alt="avatar.name"
            />
            <span
              v-else
              class="text-caption font-weight-medium"
            >{{ avatar.name.split(/\s+/).filter(Boolean).map(part => part[0]).slice(0, 2).join('').toUpperCase() }}</span>
            <VTooltip activator="parent">
              {{ avatar.name }}
            </VTooltip>
          </VAvatar>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.kanban-card {
  cursor: pointer;
}

.kanban-card__title {
  display: block;
  inline-size: 100%;
  padding: 0;
  border: 0;
  background: none;
  color: inherit;
  cursor: pointer;
  font: inherit;
  overflow-wrap: anywhere;
  text-align: start;
}

.kanban-card__title:focus-visible {
  border-radius: 4px;
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.kanban-card__menu {
  opacity: 0.72;
  transition: opacity 0.15s ease;

  &:hover,
  &:focus-visible {
    opacity: 1;
  }
}

.kanban-attachment-button {
  inline-size: 30px;
  block-size: 30px;
}

.kanban-card__description {
  display: -webkit-box;
  overflow: hidden;
  overflow-wrap: anywhere;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
}

.kanban-card__description:focus-visible {
  border-radius: 4px;
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}
</style>
