<script setup lang="ts">
import KanbanCard from './KanbanCard.vue'
import type { AddNewKanbanItem, EditKanbanItem, KanbanData, KanbanState, RenameKanbanBoard } from '@db/apps/kanban/types'
import { animations, handleEnd, performTransfer } from '@formkit/drag-and-drop'
import { dragAndDrop } from '@formkit/drag-and-drop/vue'
import { canAccess } from '@/utils/authorization'
import { VForm } from 'vuetify/components/VForm'

const props = defineProps<{
  kanbanIds: number[]
  groupName: string
  boardName: string
  boardId: number
  kanbanData: KanbanData
}>()

const emit = defineEmits<{
  (e: 'renameBoard', value: RenameKanbanBoard): void
  (e: 'deleteBoard', value: number): void
  (e: 'addNewItem', value: AddNewKanbanItem): void
  (e: 'editItem', value: EditKanbanItem | undefined): void
  (e: 'updateItemsState', value: KanbanState): void
  (e: 'deleteItem', value: EditKanbanItem): void
  (e: 'commentsClick', value: EditKanbanItem): void
}>()

const refKanbanBoard = ref<HTMLElement>()
const localBoardName = ref(props.boardName)
const localIds = ref<number[]>(props.kanbanIds)

const isAddNewFormVisible = ref(false)
const isBoardNameEditing = ref(false)
const refForm = ref<VForm>()
const newTaskTitle = ref<string>('')
const refKanbanBoardTitle = ref<VForm>()

const boardActions = computed(() => [
  ...(canAccess('edit_assigned_boards') ? [{
    title: translate('pages.tasks.kanbanitems.kanbanitems.rename'),
    prependIcon: 'tabler-pencil',
    onClick: () => { isBoardNameEditing.value = true },
  }] : []),
  ...(canAccess('delete_assigned_boards') ? [{
    title: translate('common.actions.delete'),
    prependIcon: 'tabler-trash',
    onClick: () => (emit('deleteBoard', props.boardId)),
  }] : []),
])

const renameBoard = () => {
  refKanbanBoardTitle.value?.validate().then(valid => {
    if (valid.valid) {
      emit('renameBoard', {
        oldName: props.boardName,
        newName: localBoardName.value,
        boardId: props.boardId,
      })
      isBoardNameEditing.value = false
    }
  })
}

const addNewItem = () => {
  refForm.value?.validate().then(valid => {
    if (valid.valid) {
      emit('addNewItem', { itemTitle: newTaskTitle.value, boardName: props.boardName, boardId: props.boardId })
      isAddNewFormVisible.value = false
      newTaskTitle.value = ''
    }
  })
}

if (canAccess('move_assigned_tasks')) {
  dragAndDrop({
    parent: refKanbanBoard,
    values: localIds,
    group: props.groupName,
    draggable: child => child.classList.contains('kanban-card'),
    plugins: [animations()],
    performTransfer: (state, data) => {
      performTransfer(state, data)
      emit('updateItemsState', { boardId: props.boardId, ids: localIds.value })
    },
    handleEnd: data => {
      handleEnd(data)
      emit('updateItemsState', { boardId: props.boardId, ids: localIds.value })
    },
  })
}

watch(() => props, () => {
  localIds.value = props.kanbanIds
}, { immediate: true, deep: true })

const resolveItemUsingId = (id: number) => props.kanbanData.items.find(item => item.id === id)

const deleteItem = (item: EditKanbanItem) => {
  emit('deleteItem', item)
}

const hideAddNewForm = () => {
  isAddNewFormVisible.value = false
  refForm.value?.reset()
}

onClickOutside(refForm, hideAddNewForm)
onClickOutside(refKanbanBoardTitle, () => { isBoardNameEditing.value = false })

const hideResetBoardNameForm = () => {
  isBoardNameEditing.value = false
  localBoardName.value = props.boardName
}

const handleEnterKeydown = (event: { key: string; shiftKey: any }) => {
  if (event.key === 'Enter' && !event.shiftKey)
    addNewItem()
}
</script>

<template>
  <div class="kanban-board">
    <div class="kanban-board-header pb-4">
      <VForm v-if="isBoardNameEditing" ref="refKanbanBoardTitle" @submit.prevent="renameBoard">
        <VTextField v-model="localBoardName" autofocus variant="underlined" :rules="[requiredValidator]" hide-details
          class="border-0" @keydown.esc="hideResetBoardNameForm">
          <template #append-inner>
            <VIcon size="20" color="success" icon="tabler-check" class="me-1" @click="renameBoard" />
            <VIcon size="20" color="error" icon="tabler-x" @click="hideResetBoardNameForm" />
          </template>
        </VTextField>
      </VForm>

      <div v-else class="d-flex align-center justify-space-between ">
        <h4 class="text-lg font-weight-medium text-truncate">
          {{ boardName }}
        </h4>

        <div class="d-flex align-center">
          <VIcon v-if="canAccess('reorder_boards')" class="drag-handler" size="20" icon="tabler-arrows-move" />

          <MoreBtn v-if="boardActions.length" size="28" icon-size="20" class="text-high-emphasis" :menu-list="boardActions" item-props />
        </div>
      </div>
    </div>

    <div v-if="localIds" ref="refKanbanBoard" class="kanban-board-drop-zone rounded d-flex flex-column gap-4"
      :class="localIds.length ? 'mb-4' : ''">
      <template v-for="id in localIds" :key="id">
        <KanbanCard :item="resolveItemUsingId(id)" :board-id="props.boardId" :board-name="props.boardName"
          @delete-kanban-item="deleteItem" @comments-click="emit('commentsClick', $event)"
          @click="canAccess('edit_assigned_tasks') && emit('editItem', { item: resolveItemUsingId(id), boardId: props.boardId, boardName: props.boardName })" />
      </template>

      <div v-if="canAccess('create_tasks')" class="add-new-form">
        <h6 class="text-base font-weight-regular cursor-pointer ms-4"
          @click="isAddNewFormVisible = !isAddNewFormVisible">
          <VIcon size="15" icon="tabler-plus" /> {{ $t('pages.tasks.kanbanitems.kanbanitems.addNewItem') }}
        </h6>

        <VForm v-if="isAddNewFormVisible" ref="refForm" class="mt-4" validate-on="submit" @submit.prevent="addNewItem">
          <div class="mb-4">
            <VTextarea v-model="newTaskTitle" :rules="[requiredValidator]" :placeholder="$t('pages.tasks.kanbanitems.kanbanitems.addContent')" autofocus rows="2"
              @keydown.enter="handleEnterKeydown" @keydown.esc="hideAddNewForm" />
          </div>
          <div class="d-flex gap-4 flex-wrap">
            <VBtn size="small" type="submit">
              {{ $t('common.actions.add') }}
            </VBtn>
            <VBtn size="small" variant="tonal" color="secondary" @click="hideAddNewForm">
              {{ $t('common.actions.cancel') }}
            </VBtn>
          </div>
        </VForm>
      </div>
    </div>
  </div>
</template>

<style lang="scss">
.kanban-board-header {
  .drag-handler {
    cursor: grab;
    opacity: 0;

    &:active {
      cursor: grabbing;
    }
  }

  &:hover {
    .drag-handler {
      opacity: 1;
    }
  }
}
</style>
