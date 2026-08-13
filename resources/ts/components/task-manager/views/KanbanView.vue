<script setup lang="ts">
import { useTaskStore } from '@/stores/useTaskStore'
import KanbanBoard from '@/pages/kanban/KanbanBoard.vue'
import { computed, nextTick, onMounted, ref } from 'vue'
import TaskCommentsDrawer from '../components/TaskCommentsDrawer.vue'
import TaskDetailDrawer from '../components/TaskDetailDrawer.vue'

interface AddNewKanbanItem { boardId: string | number; itemTitle: string }
interface EditKanbanItem { item: { id: string | number } }
interface KanbanState { boardId: string | number; ids: Array<string | number> }
interface RenameKanbanBoard { boardId: string | number; newName: string }

const store = useTaskStore()
const isDetailDrawerOpen = ref(false)
const isCommentsDrawerOpen = ref(false)
const isLoading = ref(true)
const editorItemId = ref<string | null>(null)

// Load data when component mounts
onMounted(async () => {
  try {
    isLoading.value = true
    await store.fetchBoardData()
  } finally {
    isLoading.value = false
  }
})

// Transform Pinia state to Vuexy's expected KanbanData format
const vuexyKanbanData = computed(() => {
  return {
    boards: store.columns.map(col => ({
      id: col.id,
      title: col.title,
      itemsIds: store.tasks
        .filter(t => t.boardId === col.id)
        .sort((a, b) => a.order - b.order)
        .map(t => t.id)
    })),
    items: store.tasks.map(task => ({
      id: task.id,
      title: task.title,
      dueDate: task.dueDate,
      labels: task.tags.map(t => t.name),
      members: task.assignees,
      commentsCount: task.comments.length,
      progress: task.progress,
      image: '',
      comments: task.description.replace(/<[^>]*>?/gm, '').substring(0, 50) + '...'
    }))
  }
})

// Handle Vuexy Drag/Drop and Event Emissions
const handleUpdateItemsState = async (payload: KanbanState) => {
  // Persist in order. Sending every move at once lets requests overtake each
  // other and leaves the column order different from the UI.
  for (const [index, itemId] of payload.ids.entries()) {
    await store.moveTaskBoard(String(itemId), String(payload.boardId), index)
  }
}

const handleAddNewItem = async (payload: AddNewKanbanItem) => {
  const task = await store.addTask({
    title: payload.itemTitle,
    boardId: String(payload.boardId),
    description: '',
    priority: 'medium',
    start_date: new Date().toISOString().substring(0, 10),
    due_date: new Date().toISOString().substring(0, 10),
  })

  if (task) {
    store.selectTask(task.id)
    // Wait until the new card has been rendered before asking the Kanban
    // component to open its own editor drawer for it.
    await nextTick()
    editorItemId.value = task.id
  }
}

// Opens the original Task Detail Drawer when clicking card
const handleEditItem = (payload: EditKanbanItem) => {
  store.selectTask(String(payload.item.id))
  isDetailDrawerOpen.value = true
}

// Opens the Task Comments Drawer when clicking comment icon
const handleCommentsClick = (payload: EditKanbanItem) => {
  store.selectTask(String(payload.item.id))
  isCommentsDrawerOpen.value = true
}

const handleDeleteItem = (payload: EditKanbanItem) => {
  store.deleteTask(String(payload.item.id))
}

const handleRenameBoard = (payload: RenameKanbanBoard) => {
  store.updateBoard(String(payload.boardId), payload.newName)
}

const handleAddNewBoard = (title: string) => {
  store.createBoard(title)
}

const handleDeleteBoard = (boardId: number) => {
  store.deleteBoard(String(boardId))
}

const handleUpdateBoardState = async (boardIds: number[]) => {
  await store.reorderBoards(boardIds.map(String))
}
</script>

<template>
  <div class="kanban-wrapper h-100 position-relative">
    <VRow>
      <VCol cols="12">
        <VCard elevation="0" class="border">
          <VCardItem class="bg-surface pb-4">
            <template #title>
              <div class="d-flex align-center">
                <VIcon icon="tabler-layout-kanban" color="primary" class="mr-2" />
                <span class="text-h5 font-weight-bold">{{ $t('components.taskManager.kanbanview.projectBoard') }}</span>
              </div>
            </template>
          </VCardItem>

          <VCardText class="kanban-scroll-area bg-grey-50">
            <VProgressLinear
              v-if="isLoading"
              indeterminate
              color="primary"
              class="position-absolute"
              style="z-index: 1;"
            />

            <KanbanBoard
              :kanban-data="vuexyKanbanData"
              :open-item-id="editorItemId"
              @add-new-item="handleAddNewItem"
              @edit-item="handleEditItem"
              @delete-item="handleDeleteItem"
              @update-items-state="handleUpdateItemsState"
              @rename-board="handleRenameBoard"
              @comments-click="handleCommentsClick"
              @add-new-board="handleAddNewBoard"
              @delete-board="handleDeleteBoard"
              @update-board-state="handleUpdateBoardState"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Original Task Detail Editor Drawer -->
    <TaskDetailDrawer v-model:is-open="isDetailDrawerOpen" />

    <!-- Dedicated Task Comments Sliding Drawer -->
    <TaskCommentsDrawer v-model:is-open="isCommentsDrawerOpen" />
  </div>
</template>

<style scoped>
.kanban-scroll-area {
  min-height: 65vh;
  overflow-x: auto;
  position: relative;
  padding: 1.5rem;
}
</style>
