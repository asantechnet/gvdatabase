<script setup lang="ts">
import { VForm } from 'vuetify/components/VForm'
import KanbanCard from './KanbanCard.vue'
import type { AddNewKanbanItem, EditKanbanItem, KanbanData, KanbanId } from '@/types/legacyKanban'
import { canCreateTaskOnBoard } from '@/utils/authorization'

const props = defineProps<{
  kanbanIds: KanbanId[]
  groupName: string
  boardName: string
  boardId: KanbanId
  isPersonal?: boolean
  isOwned?: boolean
  kanbanData: KanbanData
}>()

const emit = defineEmits<{
  (e: 'addNewItem', value: AddNewKanbanItem): void
  (e: 'viewItem', value: EditKanbanItem | undefined): void
  (e: 'editItem', value: EditKanbanItem | undefined): void
  (e: 'editFullItem', value: EditKanbanItem | undefined): void
  (e: 'deleteItem', value: EditKanbanItem): void
  (e: 'commentsClick', value: EditKanbanItem): void
  (e: 'attachmentsClick', value: EditKanbanItem): void
}>()

const isAddNewFormVisible = ref(false)
const refForm = ref<VForm>()
const newTaskTitle = ref<string>('')

const addNewItem = () => {
  refForm.value?.validate().then(valid => {
    if (valid.valid) {
      emit('addNewItem', { itemTitle: newTaskTitle.value, boardName: props.boardName, boardId: props.boardId })
      isAddNewFormVisible.value = false
      newTaskTitle.value = ''
    }
  })
}

// Drag-and-drop is intentionally disabled on the task board.

const resolveItemUsingId = (id: KanbanId) => props.kanbanData.items.find(item => item.id === id)

const editItem = (id: KanbanId) => {
  const item = resolveItemUsingId(id)
  if (item)
    emit('editItem', { item, boardId: props.boardId, boardName: props.boardName })
}

const editFullItem = (id: KanbanId) => {
  const item = resolveItemUsingId(id)
  if (item)
    emit('editFullItem', { item, boardId: props.boardId, boardName: props.boardName })
}

const viewItem = (id: KanbanId) => {
  const item = resolveItemUsingId(id)
  if (item)
    emit('viewItem', { item, boardId: props.boardId, boardName: props.boardName })
}

const deleteItem = (item: EditKanbanItem) => {
  emit('deleteItem', item)
}

const hideAddNewForm = () => {
  isAddNewFormVisible.value = false
  refForm.value?.reset()
}

onClickOutside(refForm, hideAddNewForm)

const handleEnterKeydown = (event: { key: string; shiftKey: any }) => {
  if (event.key === 'Enter' && !event.shiftKey)
    addNewItem()
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
            v-if="canCreateTaskOnBoard(props.isPersonal, props.isOwned)"
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
                @click="isAddNewFormVisible = !isAddNewFormVisible"
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

    <VForm
      v-if="isAddNewFormVisible"
      ref="refForm"
      class="app-form kanban-add-new-form mb-4"
      validate-on="submit"
      @submit.prevent="addNewItem"
    >
      <div class="mb-3">
        <VTextarea
          v-model="newTaskTitle"
          :rules="[requiredValidator]"
          :placeholder="$t('pages.tasks.kanbanitems.kanbanitems.addContent')"
          autofocus
          rows="2"
          @keydown.enter="handleEnterKeydown"
          @keydown.esc="hideAddNewForm"
        />
      </div>
      <div class="d-flex gap-3 flex-wrap">
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
          @click="hideAddNewForm"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
      </div>
    </VForm>

    <div
      class="kanban-board-drop-zone rounded d-flex flex-column gap-4"
      :class="kanbanIds.length ? 'mb-4' : ''"
    >
      <template
        v-for="id in kanbanIds"
        :key="id"
      >
        <KanbanCard
          :item="resolveItemUsingId(id)"
          :board-id="props.boardId"
          :board-name="props.boardName"
          :is-personal="props.isPersonal"
          :is-owned="props.isOwned"
          @view-item="viewItem(id)"
          @edit-item="editItem(id)"
          @edit-full-item="editFullItem(id)"
          @delete-kanban-item="deleteItem"
          @comments-click="emit('commentsClick', $event)"
          @attachments-click="emit('attachmentsClick', $event)"
        />
      </template>
    </div>
  </div>
</template>
