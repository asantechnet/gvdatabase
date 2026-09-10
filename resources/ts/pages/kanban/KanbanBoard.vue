<script setup lang="ts">
import { animations, remapNodes } from '@formkit/drag-and-drop'
import { dragAndDrop } from '@formkit/drag-and-drop/vue'
import { VForm } from 'vuetify/components/VForm'
import KanbanBoardEditDrawer from './KanbanBoardEditDrawer.vue'
import KanbanItems from './KanbanItems.vue'
import { canAccess } from '@/utils/authorization'
import type { AddNewKanbanItem, EditKanbanItem, KanbanBoard, KanbanData, KanbanId, KanbanState, RenameKanbanBoard } from '@/types/legacyKanban'

const props = withDefaults(defineProps<{
  kanbanData: KanbanData
  groupName?: string
  openItemId?: string | null
}>(), {
  groupName: 'kanban',
})

const emit = defineEmits<{
  (e: 'addNewBoard', value: string): void
  (e: 'renameBoard', value: RenameKanbanBoard): void
  (e: 'deleteBoard', value: KanbanId): void
  (e: 'addNewItem', value: AddNewKanbanItem): void
  (e: 'editItem', value: EditKanbanItem): void
  (e: 'deleteItem', value: EditKanbanItem): void
  (e: 'updateItemsState', value: KanbanState): void
  (e: 'updateBoardState', value: KanbanId[]): void
  (e: 'commentsClick', value: any): void
  (e: 'attachmentsClick', value: any): void
}>()

const kanbanWrapper = ref<HTMLElement>()
const localKanbanData = ref<KanbanBoard[]>(props.kanbanData.boards)
const isKanbanBoardEditVisible = ref(false)

const isAddNewFormVisible = ref(false)
const refAddNewBoard = ref<VForm>()
const boardTitle = ref<string>('')

const editKanbanItem = ref<EditKanbanItem>()

const addNewBoard = () => {
  refAddNewBoard.value?.validate().then(valid => {
    if (valid.valid) {
      emit('addNewBoard', boardTitle.value)
      isAddNewFormVisible.value = false
      boardTitle.value = ''
    }
  })
}

const deleteBoard = (boardId: KanbanId) => {
  emit('deleteBoard', boardId)
}

const renameBoard = (boardName: RenameKanbanBoard) => {
  emit('renameBoard', boardName)
}

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

const updateStateFn = (kanbanState: KanbanState) => {
  emit('updateItemsState', kanbanState)
}

if (canAccess('reorder_boards')) {
  dragAndDrop({
    parent: kanbanWrapper,
    values: localKanbanData,
    dragHandle: '.drag-handler',
    plugins: [animations()],
  })
}

watch(() => props, () => {
  localKanbanData.value = props.kanbanData.boards
  if (canAccess('reorder_boards'))
    remapNodes(kanbanWrapper.value as any)
}, { deep: true })

const emitUpdatedTaskFn = (item: EditKanbanItem) => {
  emit('editItem', item)
}

const deleteKanbanItemFn = (item: EditKanbanItem) => {
  emit('deleteItem', item)
}

watch(localKanbanData, () => {
  const getIds = localKanbanData.value.map(board => board.id)

  emit('updateBoardState', getIds)
}, { deep: true })

const validateBoardTitle = () => {
  return props.kanbanData.boards.some(board => boardTitle.value && board.title.toLowerCase() === boardTitle.value.toLowerCase()) ? 'Board title already exists' : true
}

const hideAddNewForm = () => {
  isAddNewFormVisible.value = false
  refAddNewBoard.value?.reset()
}

onClickOutside(refAddNewBoard, hideAddNewForm)
</script>

<template>
  <div class="kanban-main-wrapper d-flex gap-4 h-100">
    <div
      ref="kanbanWrapper"
      class="d-flex ga-6"
    >
      <template
        v-for="kb in localKanbanData"
        :key="kb.id"
      >
        <KanbanItems
          :group-name="groupName"
          :kanban-ids="kb.itemsIds"
          :board-name="kb.title"
          :board-id="kb.id"
          :kanban-items="kanbanData.items"
          :kanban-data="kanbanData"
          @delete-board="deleteBoard"
          @rename-board="renameBoard"
          @add-new-item="addNewItem"
          @edit-item="editKanbanItemFn"
          @update-items-state="updateStateFn"
          @delete-item="deleteKanbanItemFn"
          @comments-click="emit('commentsClick', $event)"
          @attachments-click="emit('attachmentsClick', $event)"
        />
      </template>
    </div>

    <div
      v-if="canAccess('create_boards')"
      class="add-new-form text-no-wrap"
      style="inline-size: 10rem;"
    >
      <h6
        class="text-lg font-weight-medium cursor-pointer"
        @click="isAddNewFormVisible = !isAddNewFormVisible"
      >
        <VIcon
          size="18"
          icon="tabler-plus"
        /> {{ $t('pages.tasks.kanbanboard.kanbanboard.addNew') }}
      </h6>

      <VForm
        v-if="isAddNewFormVisible"
        ref="refAddNewBoard"
        class="app-form mt-4"
        validate-on="submit"
        @submit.prevent="addNewBoard"
      >
        <div class="mb-4">
          <VTextField
            v-model="boardTitle"
            :rules="[requiredValidator, validateBoardTitle]"
            autofocus
            :placeholder="$t('pages.tasks.kanbanboard.kanbanboard.addBoardTitle')"
            @keydown.esc="hideAddNewForm"
          />
        </div>
        <div class="d-flex gap-3">
          <VBtn
            size="small"
            type="submit"
          >
            {{ $t('common.actions.add') }}
          </VBtn>
          <VBtn
            size="small"
            variant="tonal"
            color="secondary"
            type="reset"
            @click="hideAddNewForm"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
        </div>
      </VForm>
    </div>
  </div>

  <KanbanBoardEditDrawer
    v-model:is-drawer-open="isKanbanBoardEditVisible"
    :kanban-item="editKanbanItem"
    @update:kanban-item="emitUpdatedTaskFn"
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

  .add-new-form {
    .v-field__field {
      border-radius: vuetify.$border-radius-root;
      background-color: rgb(var(--v-theme-surface));
    }
  }
}
</style>
