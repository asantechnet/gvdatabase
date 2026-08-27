<script setup lang="ts">
import TagApi, { type Tag } from '@/api/tag'
import { useTaskStore } from '@/stores/useTaskStore'
import { useToast } from 'vue-toastification'

const store = useTaskStore()
const toast = useToast()
const dialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const saving = ref(false)
const editing = ref<Tag | null>(null)
const deleting = ref<Tag | null>(null)
const form = ref({ name: '', color: '#7367f0' })

const headers = [
  { title: translate('components.taskManager.tasksettingsview.label'), key: 'name' },
  { title: translate('components.taskManager.tasksettingsview.color'), key: 'color', sortable: false },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
]

const openCreate = () => {
  editing.value = null
  form.value = { name: '', color: '#7367f0' }
  dialogOpen.value = true
}
const openEdit = (tag: Tag) => {
  editing.value = tag
  form.value = { name: tag.name, color: tag.color }
  dialogOpen.value = true
}
const save = async () => {
  if (!form.value.name.trim()) return toast.error(translate('components.taskManager.tasksettingsview.labelNameIsRequired'))
  saving.value = true
  try {
    if (editing.value) await TagApi.update(editing.value.id, { name: form.value.name.trim(), color: form.value.color })
    else await TagApi.create({ name: form.value.name.trim(), color: form.value.color })
    await store.fetchTags()
    dialogOpen.value = false
    toast.success(translate(editing.value
      ? 'components.taskManager.tasksettingsview.labelUpdated'
      : 'components.taskManager.tasksettingsview.labelCreated'))
  } catch (error: any) {
    toast.error(error.errors?.name?.[0] || error.message || 'Could not save task label.')
  } finally { saving.value = false }
}
const askDelete = (tag: Tag) => { deleting.value = tag; deleteDialogOpen.value = true }
const confirmDelete = async () => {
  if (!deleting.value) return
  saving.value = true
  try {
    await TagApi.delete(deleting.value.id)
    await store.fetchTags()
    deleteDialogOpen.value = false
    toast.success(translate('components.taskManager.tasksettingsview.taskLabelDeleted'))
  } catch (error: any) { toast.error(error.message || 'Could not delete task label.') }
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
        <template #append><VBtn prepend-icon="tabler-plus" @click="openCreate">{{ $t('components.taskManager.tasksettingsview.newLabel') }}</VBtn></template>
      </VCardItem>
      <VDivider />
      <ResponsiveDataTable :headers="headers" :items="store.tags" :loading="store.isLoading" item-value="id">
        <template #item.name="{ item }"><VChip :color="item.color" label>{{ item.name }}</VChip></template>
        <template #item.color="{ item }"><div class="d-flex align-center ga-2"><span class="color-dot" :style="{ backgroundColor: item.color }" /><code>{{ item.color }}</code></div></template>
        <template #item.actions="{ item }"><FullDetailsButton :item="item" :title="item.name" /><IconBtn :aria-label="$t('components.taskManager.tasksettingsview.editLabel')" @click="openEdit(item)"><VIcon icon="tabler-pencil" /></IconBtn><IconBtn color="error" :aria-label="$t('components.taskManager.tasksettingsview.deleteLabel')" @click="askDelete(item)"><VIcon icon="tabler-trash" /></IconBtn></template>
      </ResponsiveDataTable>
    </VCard>

    <VDialog v-model="dialogOpen" max-width="520"><VCard :title="editing ? 'Edit task label' : 'New task label'"><VCardText><VTextField v-model="form.name" :label="$t('components.taskManager.tasksettingsview.labelName')" autofocus class="mb-4" /><VColorPicker v-model="form.color" mode="hex" width="100%" /><VChip :color="form.color" label class="mt-4">{{ form.name || 'Preview' }}</VChip></VCardText><VCardActions><VSpacer /><VBtn variant="tonal" @click="dialogOpen = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn :loading="saving" @click="save">{{ $t('common.actions.save') }}</VBtn></VCardActions></VCard></VDialog>
    <VDialog v-model="deleteDialogOpen" max-width="420"><VCard :title="$t('components.taskManager.tasksettingsview.deleteTaskLabel')"><VCardText>{{ $t('components.taskManager.tasksettingsview.deleteConfirmation', { label: deleting?.name }) }}</VCardText><VCardActions><VSpacer /><VBtn variant="tonal" @click="deleteDialogOpen = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="error" :loading="saving" @click="confirmDelete">{{ $t('common.actions.delete') }}</VBtn></VCardActions></VCard></VDialog>
  </div>
</template>

<style scoped>.color-dot { width: 1rem; height: 1rem; border: 1px solid currentColor; border-radius: 50%; }</style>
