<script setup lang="ts">
import type { EditKanbanItem, KanbanId, KanbanItem } from '@/types/legacyKanban'
import { canAccess } from '@/utils/authorization'

const props = defineProps<{
  item: KanbanItem | undefined
  boardId: KanbanId
  boardName: string
}>()

const emit = defineEmits<{
  (e: 'deleteKanbanItem', value: EditKanbanItem): void
  (e: 'commentsClick', value: EditKanbanItem): void
  (e: 'attachmentsClick', value: EditKanbanItem): void
}>()

const resolveLabelColor: any = {
  'UX': 'success',
  'Image': 'warning',
  'Code Review': 'error',
  'Dashboard': 'info',
  'App': 'secondary',
  'Charts & Maps': 'primary',
}
</script>

<template>
  <VCard
    v-if="item"
    :ripple="false"
    :link="false"
    class="kanban-card position-relative"
  >
    <VCardText class="d-flex flex-column gap-2">
      <div class="d-flex align-start gap-2">
        <div
          v-if="item.labels && item.labels.length"
          class="d-flex flex-wrap gap-2"
        >
          <VChip
            v-for="text in item.labels"
            :key="text"
            size="small"
            :color="resolveLabelColor[text]"
          >
            {{ text }}
          </VChip>
        </div>
      </div>

      <!-- Task Img -->
      <VImg
        v-if="item.image && item.image.length"
        :src="item.image"
        class="rounded"
      />

      <!-- Task title -->
      <p class="text-base text-high-emphasis mb-0">
        {{ item.title }}
      </p>

      <!-- Footer -->
      <div class="task-footer d-flex align-center flex-wrap justify-space-between">
        <div class="d-flex align-center gap-3">
          <div
            v-if="canAccess('create_comments_on_assigned_tasks')"
            class="cursor-pointer d-flex align-center"
            @click.stop="emit('commentsClick', { item, boardId: props.boardId, boardName: props.boardName })"
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
              @click.stop="emit('attachmentsClick', { item, boardId: props.boardId, boardName: props.boardName })"
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
  cursor: grab;

  :active {
    cursor: grabbing;
  }

  &[style^="z-index"] {
    cursor: grabbing !important;
  }
}

.kanban-attachment-button {
  inline-size: 30px;
  block-size: 30px;
}
</style>
