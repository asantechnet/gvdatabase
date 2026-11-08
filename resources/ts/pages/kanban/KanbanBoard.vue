<script setup lang="ts">
import KanbanBoardEditDrawer from './KanbanBoardEditDrawer.vue'
import KanbanItems from './KanbanItems.vue'
import type { AddNewKanbanItem, EditKanbanItem, KanbanBoard, KanbanData } from '@/types/legacyKanban'

const props = withDefaults(defineProps<{
  kanbanData: KanbanData
  groupName?: string
  openItemId?: string | null
}>(), {
  groupName: 'kanban',
})

const emit = defineEmits<{
  (e: 'addNewItem', value: AddNewKanbanItem): void
  (e: 'viewItem', value: EditKanbanItem): void
  (e: 'editFullItem', value: EditKanbanItem): void
  (e: 'deleteItem', value: EditKanbanItem): void
  (e: 'commentsClick', value: any): void
  (e: 'attachmentsClick', value: any): void
}>()

const localKanbanData = ref<KanbanBoard[]>(props.kanbanData.boards)
const isKanbanBoardEditVisible = ref(false)
const editKanbanItem = ref<EditKanbanItem>()

const addNewItem = (item: AddNewKanbanItem) => {
  emit('addNewItem', item)
}

const editKanbanItemFn = (item: EditKanbanItem | undefined) => {
  if (item) {
    editKanbanItem.value = item
    isKanbanBoardEditVisible.value = true
  }
}

// A task created from a column should be immediately editable. The parent
// supplies its id after the API call and this component owns the editor drawer.
watch(() => props.openItemId, itemId => {
  if (!itemId)
    return

  const item = props.kanbanData.items.find(candidate => String(candidate.id) === itemId)

  const board = props.kanbanData.boards.find(candidate =>
    candidate.itemsIds.some(candidateId => String(candidateId) === itemId),
  )

  if (item && board) {
    editKanbanItemFn({
      item,
      boardId: board.id,
      boardName: board.title,
    })
  }
}, { flush: 'post' })

watch(() => props, () => {
  localKanbanData.value = props.kanbanData.boards
}, { deep: true })

const emitViewItemFn = (item: EditKanbanItem | undefined) => {
  if (item)
    emit('viewItem', item)
}

const deleteKanbanItemFn = (item: EditKanbanItem) => {
  emit('deleteItem', item)
}

const emitEditFullItemFn = (item: EditKanbanItem | undefined) => {
  if (item)
    emit('editFullItem', item)
}
</script>

<template>
  <div class="kanban-main-wrapper d-flex gap-4 h-100">
    <div class="d-flex ga-6">
      <template
        v-for="kb in localKanbanData"
        :key="kb.id"
      >
        <KanbanItems
          :group-name="groupName"
          :kanban-ids="kb.itemsIds"
          :board-name="kb.title"
          :board-id="kb.id"
          :is-personal="kb.isPersonal"
          :is-owned="kb.isOwned"
          :kanban-items="kanbanData.items"
          :kanban-data="kanbanData"
          @add-new-item="addNewItem"
          @view-item="emitViewItemFn"
          @edit-item="editKanbanItemFn"
          @edit-full-item="emitEditFullItemFn"
          @delete-item="deleteKanbanItemFn"
          @comments-click="emit('commentsClick', $event)"
          @attachments-click="emit('attachmentsClick', $event)"
        />
      </template>
    </div>
  </div>

  <KanbanBoardEditDrawer
    v-model:is-drawer-open="isKanbanBoardEditVisible"
    :kanban-item="editKanbanItem"
    @delete-kanban-item="deleteKanbanItemFn"
  />
</template>

<style lang="scss">
@use "@styles/variables/_vuetify.scss" as vuetify;

.kanban-main-wrapper {
  overflow: auto hidden;
  min-block-size: calc(100vh - 10.5rem);

  .kanban-board {
    inline-size: 16.875rem;
    min-inline-size: 16.875rem;

    .kanban-board-drop-zone {
      min-block-size: 100%;
    }
  }
}
</style>
