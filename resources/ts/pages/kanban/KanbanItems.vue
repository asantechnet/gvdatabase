<script setup lang="ts">
import { animations, handleEnd, performTransfer } from '@formkit/drag-and-drop'
import { dragAndDrop } from '@formkit/drag-and-drop/vue'
import { VForm } from 'vuetify/components/VForm'
import KanbanCard from './KanbanCard.vue'
import type { AddNewKanbanItem, EditKanbanItem, KanbanData, KanbanId, KanbanState } from '@/types/legacyKanban'
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
  (e: 'updateItemsState', value: KanbanState): void
  (e: 'deleteItem', value: EditKanbanItem): void
  (e: 'commentsClick', value: EditKanbanItem): void
  (e: 'attachmentsClick', value: EditKanbanItem): void
}>()

const refKanbanBoard = ref<HTMLElement>()
const localIds = ref<KanbanId[]>(props.kanbanIds)

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

const resolveItemUsingId = (id: KanbanId) => props.kanbanData.items.find(item => item.id === id)

const editItem = (id: KanbanId) => {
  const item = resolveItemUsingId(id)
  if (item)
    emit('editItem', { item, boardId: props.boardId, boardName: props.boardName })
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
