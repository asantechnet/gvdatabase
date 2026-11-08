<script setup lang="ts">
import { animations, performSort, performTransfer } from '@formkit/drag-and-drop'
import { dragAndDrop } from '@formkit/drag-and-drop/vue'
import KanbanCard from './KanbanCard.vue'
import type { AddNewKanbanItem, EditKanbanItem, KanbanData, KanbanId, KanbanTaskMove } from '@/types/legacyKanban'
import { canAccess } from '@/utils/authorization'

const props = defineProps<{
  kanbanIds: KanbanId[]
  groupName: string
  boardName: string
  boardId: KanbanId
  kanbanData: KanbanData
}>()

const emit = defineEmits<{
  (e: 'addNewItem', value: AddNewKanbanItem): void
  (e: 'editItem', value: EditKanbanItem | undefined): void
  (e: 'updateItemsState', value: KanbanTaskMove): void
  (e: 'deleteItem', value: EditKanbanItem): void
  (e: 'commentsClick', value: EditKanbanItem): void
  (e: 'attachmentsClick', value: EditKanbanItem): void
}>()

const refKanbanBoard = ref<HTMLElement>()
const localIds = ref<KanbanId[]>(props.kanbanIds)

const addNewItem = () => {
  emit('addNewItem', { itemTitle: '', boardName: props.boardName, boardId: props.boardId })
}

if (canAccess('move_assigned_tasks')) {
  const persistMove = (state: { draggedNode: { data: { value: KanbanId } }; targetIndex: number }) => {
    emit('updateItemsState', { taskId: state.draggedNode.data.value, boardId: props.boardId, order: localIds.value.length - 1 - state.targetIndex })
  }
  dragAndDrop({
    parent: refKanbanBoard,
    values: localIds,
    group: props.groupName,
    draggable: child => child.classList.contains('kanban-card'),
    plugins: [animations()],
    performTransfer: (state, data) => {
      performTransfer(state, data)
      persistMove(state)
    },
    performSort: (state, data) => {
      performSort(state, data)
      persistMove(state)
    },
  })
}

watch(() => props, () => {
  localIds.value = props.kanbanIds
}, { immediate: true, deep: true })

const resolveItemUsingId = (id: KanbanId) => props.kanbanData.items.find(item => item.id === id)

const editItem = (id: KanbanId) => {
  const item = resolveItemUsingId(id)
  if (item)
    emit('editItem', { item, boardId: props.boardId, boardName: props.boardName })
}

const deleteItem = (item: EditKanbanItem) => {
  emit('deleteItem', item)
}

</script>

<template>
  <div class="kanban-board">
    <div class="kanban-board-header pb-4">
      <div class="d-flex align-center justify-space-between gap-2">
        <h4 class="text-lg font-weight-medium text-truncate">
          {{ boardName }}
        </h4>

        <div class="d-flex align-center">
          <VTooltip
            v-if="canAccess('create_tasks')"
            location="top"
          >
            <template #activator="{ props: tooltipProps }">
              <VBtn
                v-bind="tooltipProps"
                icon
                size="small"
                variant="tonal"
                color="primary"
                :aria-label="$t('common.ui.addNewTask')"
                @click="addNewItem"
              >
                <VIcon
                  icon="tabler-plus"
                  size="18"
                />
              </VBtn>
            </template>
            {{ $t('pages.tasks.kanbanitems.kanbanitems.addNewItem') }}
          </VTooltip>
        </div>
      </div>
    </div>

    <div
      v-if="localIds"
      ref="refKanbanBoard"
      class="kanban-board-drop-zone rounded d-flex flex-column gap-4"
      :class="localIds.length ? 'mb-4' : ''"
    >
      <template
        v-for="id in localIds"
        :key="id"
      >
        <KanbanCard
          :item="resolveItemUsingId(id)"
          :board-id="props.boardId"
          :board-name="props.boardName"
          @delete-kanban-item="deleteItem"
          @comments-click="emit('commentsClick', $event)"
          @attachments-click="emit('attachmentsClick', $event)"
          @click="canAccess('edit_assigned_tasks') && editItem(id)"
        />
      </template>
    </div>
  </div>
</template>
