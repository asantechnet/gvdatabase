<script setup lang="ts">
import { useToast } from 'vue-toastification'
import TagApi, { type Tag } from '@/api/tag'
import { useTaskStore } from '@/stores/useTaskStore'
import { moveCreatedRowFirst } from '@/utils/createdFirst'
import ErpField from '@/components/erp/ErpField.vue'
import ConfirmDialog from '@/components/dialogs/ConfirmDialog.vue'

const store = useTaskStore()
const toast = useToast()
const dialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const saving = ref(false)
const editing = ref<Tag | null>(null)
const deleting = ref<Tag | null>(null)
const form = ref({ name: '', color: '#7367f0' })
const nameError = ref('')

const headers = computed(() => [
  { title: translate('components.taskManager.tasksettingsview.label'), key: 'name' },
  { title: translate('components.taskManager.tasksettingsview.color'), key: 'color', sortable: false },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

const openCreate = () => {
  editing.value = null
  nameError.value = ''
  form.value = { name: '', color: '#7367f0' }
  dialogOpen.value = true
}

const openEdit = (tag: Tag) => {
  editing.value = tag
  nameError.value = ''
  form.value = { name: tag.name, color: tag.color }
  dialogOpen.value = true
}

const save = async () => {
  if (!form.value.name.trim()) {
    nameError.value = translate('components.taskManager.tasksettingsview.labelNameIsRequired')
    return
  }
  nameError.value = ''
  saving.value = true
  try {
    const wasNew = !editing.value
    const saved = editing.value
      ? await TagApi.update(editing.value.id, { name: form.value.name.trim(), color: form.value.color })
      : await TagApi.create({ name: form.value.name.trim(), color: form.value.color })
    await store.fetchTags()
    if (wasNew)
      store.tags = moveCreatedRowFirst(store.tags, saved)
    dialogOpen.value = false
    toast.success(translate(editing.value
      ? 'components.taskManager.tasksettingsview.labelUpdated'
      : 'components.taskManager.tasksettingsview.labelCreated'))
  }
  catch (error: any) {
    toast.error(error.errors?.name?.[0] || error.message || 'Could not save task label.')
  }
  finally { saving.value = false }
}

const askDelete = (tag: Tag) => { deleting.value = tag; deleteDialogOpen.value = true }

const confirmDelete = async () => {
  if (!deleting.value)
    return
  saving.value = true
  try {
    await TagApi.delete(deleting.value.id)
    await store.fetchTags()
    deleteDialogOpen.value = false
    toast.success(translate('components.taskManager.tasksettingsview.taskLabelDeleted'))
  }
  catch (error: any) { toast.error(error.message || 'Could not delete task label.') }
  finally { saving.value = false }
}

onMounted(store.fetchTags)
</script>

<template>
  <div class="task-settings-view">
    <VCard>
      <VCardItem>
        <VCardTitle>{{ $t('components.taskManager.tasksettingsview.taskLabels') }}</VCardTitle>
        <VCardSubtitle>{{ $t('components.taskManager.tasksettingsview.createReusableLabelsForKanbanCalendarGanttAndTodoViews') }}</VCardSubtitle>
        <template #append>
          <VBtn
            prepend-icon="tabler-plus"
            @click="openCreate"
          >
            {{ $t('components.taskManager.tasksettingsview.newLabel') }}
          </VBtn>
        </template>
      </VCardItem>
      <VDivider />
      <ResponsiveDataTable
        :headers="headers"
        :items="store.tags"
        :loading="store.isLoading"
        item-value="id"
        mobile-cards
        mobile-title-key="name"
        :mobile-fields="['color']"
        mobile-action-key="actions"
        mobile-actions-as-menu
        class="responsive-data-table--mobile-cards"
      >
        <template #item.name="{ item }">
          <VChip
            :color="item.color"
            label
          >
            {{ item.name }}
          </VChip>
        </template>
        <template #item.color="{ item }">
          <div class="d-flex align-center ga-2">
            <span
              class="color-dot"
              :style="{ backgroundColor: item.color }"
            /><code>{{ item.color }}</code>
          </div>
        </template>
        <template #item.actions="{ item }">
          <FullDetailsButton
            :item="item"
            :title="item.name"
          /><IconBtn
            :aria-label="$t('components.taskManager.tasksettingsview.editLabel')"
            @click="openEdit(item)"
          >
            <VIcon icon="tabler-pencil" />
          </IconBtn><IconBtn
            color="error"
            :aria-label="$t('components.taskManager.tasksettingsview.deleteLabel')"
            @click="askDelete(item)"
          >
            <VIcon icon="tabler-trash" />
          </IconBtn>
        </template>
      </ResponsiveDataTable>
    </VCard>

    <VDialog
      v-model="dialogOpen"
      max-width="520"
      content-class="task-label-dialog"
      :persistent="saving"
    >
      <VCard class="task-label-dialog__card">
        <VCardTitle class="task-label-dialog__header">
          <span class="text-h6 font-weight-bold">{{ editing ? $t('components.taskManager.tasksettingsview.editLabel') : $t('components.taskManager.tasksettingsview.newLabel') }}</span>
          <VBtn
            icon
            size="small"
            class="task-label-dialog__close"
            :disabled="saving"
            :aria-label="$t('common.actions.close')"
            @click="dialogOpen = false"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>
        <VCardText class="pt-5">
          <ErpField
            v-model="form.name"
            :label="$t('components.taskManager.tasksettingsview.labelName')"
            :placeholder="$t('components.taskManager.tasksettingsview.labelName')"
            :error-messages="nameError"
            required
            autofocus
            class="mb-4"
          /><VColorPicker
            v-model="form.color"
            mode="hex"
            width="100%"
          /><VChip
            :color="form.color"
            label
            class="mt-4"
          >
            {{ form.name || 'Preview' }}
          </VChip>
        </VCardText><VCardActions class="task-label-dialog__footer">
          <VSpacer /><VBtn
            class="task-label-dialog__cancel"
            :disabled="saving"
            @click="dialogOpen = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn><VBtn
            color="primary"
            variant="flat"
            :loading="saving"
            @click="save"
          >
            {{ $t('common.actions.save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
    <ConfirmDialog
      v-model:is-dialog-visible="deleteDialogOpen"
      tone="error"
      icon="tabler-trash"
      :confirmation-question="$t('components.taskManager.tasksettingsview.deleteTaskLabel')"
      :confirmation-message="$t('components.taskManager.tasksettingsview.deleteConfirmation', { label: deleting?.name })"
      :confirm-text="$t('common.actions.delete')"
      :cancel-text="$t('common.actions.cancel')"
      :confirm-title="$t('common.actions.delete')"
      confirm-msg=""
      :cancel-title="$t('common.actions.cancel')"
      cancel-msg=""
      @confirm="confirmed => { if (confirmed) confirmDelete() }"
    />
  </div>
</template>

<style scoped>
.color-dot { width: 1rem; height: 1rem; border: 1px solid currentColor; border-radius: 50%; }

:global(.v-overlay:has(.task-label-dialog) .v-overlay__scrim) {
  background: rgba(var(--v-theme-on-surface), .3) !important;
  backdrop-filter: blur(7px);
  opacity: 1 !important;
}

.task-label-dialog__card { overflow: hidden; }
.task-label-dialog__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.25rem;
  background: rgba(var(--v-theme-primary), .07);
}
.task-label-dialog__close {
  background: rgba(var(--v-theme-error), .14) !important;
  color: rgb(var(--v-theme-error)) !important;
}
.task-label-dialog__footer {
  padding: 1rem 1.25rem;
  background: rgba(var(--v-theme-primary), .05);
}
.task-label-dialog__cancel {
  background: rgba(var(--v-theme-secondary), .13) !important;
  color: rgb(var(--v-theme-secondary)) !important;
}
.task-label-dialog__cancel:hover {
  background: rgb(var(--v-theme-secondary)) !important;
  color: rgb(var(--v-theme-on-secondary)) !important;
}
</style>
