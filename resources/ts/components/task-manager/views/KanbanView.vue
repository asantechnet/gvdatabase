<script setup lang="ts">
import { useTaskStore } from '@/stores/useTaskStore'
import KanbanBoard from '@/pages/kanban/KanbanBoard.vue'
import type { AddNewKanbanItem, EditKanbanItem, KanbanState, RenameKanbanBoard } from '@db/apps/kanban/types'
import { computed, onMounted, ref } from 'vue'
import TaskCommentsDrawer from '../components/TaskCommentsDrawer.vue'
import TaskDetailDrawer from '../components/TaskDetailDrawer.vue'

const store = useTaskStore()
const isDetailDrawerOpen = ref(false)
const isCommentsDrawerOpen = ref(false)
const isLoading = ref(true)

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
      attachments: task.dependencies.length,
      commentsCount: task.comments.length,
      image: '',
      comments: task.description.replace(/<[^>]*>?/gm, '').substring(0, 50) + '...'
    }))
  }
})

// Handle Vuexy Drag/Drop and Event Emissions
const handleUpdateItemsState = (payload: KanbanState) => {
  payload.ids.forEach((itemId, index) => {
    store.moveTaskBoard(String(itemId), String(payload.boardId), index)
  })
}

const handleAddNewItem = (payload: AddNewKanbanItem) => {
  store.addTask({
    title: payload.itemTitle,
    boardId: String(payload.boardId),
    description: '',
    priority: 'medium',
    startDate: new Date().toISOString().substring(0, 10),
    dueDate: new Date().toISOString().substring(0, 10),
    order: 999
  })
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
  store.updateBoard(payload.boardId, payload.newName)
}

const handleAddNewBoard = (title: string) => {
  store.createBoard(title)
}

const handleDeleteBoard = (boardId: number) => {
  store.deleteBoard(String(boardId))
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
                <span class="text-h5 font-weight-bold">Project Board</span>
              </div>
            </template>
          </VCardItem>

          <VCardText class="pa-0 kanban-scroll-area bg-grey-50">
            <VProgressLinear
              v-if="isLoading"
              indeterminate
              color="primary"
              class="position-absolute"
              style="z-index: 1;"
            />

            <KanbanBoard
              :kanban-data="vuexyKanbanData"
              @add-new-item="handleAddNewItem"
              @edit-item="handleEditItem"
              @delete-item="handleDeleteItem"
              @update-items-state="handleUpdateItemsState"
              @rename-board="handleRenameBoard"
              @comments-click="handleCommentsClick"
              @add-new-board="handleAddNewBoard"
              @delete-board="handleDeleteBoard"
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
}
</style>
