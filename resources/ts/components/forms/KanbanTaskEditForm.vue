<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { VForm } from 'vuetify/components/VForm'
import { requiredValidator } from '@core/utils/validators'
import ErpField from '@/components/erp/ErpField.vue'
import EmployeeAvatar from '@/components/hr/EmployeeAvatar.vue'

const props = defineProps<{
  item: any
  employees: any[]
  tags: any[]
  taskOptions: Array<{ title: string; value: string }>
  loadingEmployees: boolean
  canEdit: boolean
  canAssign: boolean
  canManageTags: boolean
  canManageDependencies: boolean
  formId: string
}>()
const emit = defineEmits<{ submit: [item: any] }>()
const formRef = ref<VForm>()
const draft = ref<any>({})
const dockDialogCalendar = (_selectedDates: Date[], _dateString: string, instance: { calendarContainer: HTMLElement }) => {
  requestAnimationFrame(() => {
    instance.calendarContainer.style.transform = 'translateY(0) scale(0.86)'
    instance.calendarContainer.style.transformOrigin = 'top left'
    instance.calendarContainer.style.zIndex = '3100'
  })
}
const config = { enableTime: false, dateFormat: 'Y-m-d', static: false, onOpen: dockDialogCalendar }

watch(() => props.item, item => { draft.value = { ...item, labels: [...(item?.labels ?? [])], members: [...(item?.members ?? [])] } }, { deep: true, immediate: true })

const priorityOptions = computed(() => ['low', 'medium', 'high', 'urgent'].map(value => ({
  value,
  title: translateBackendValue(value),
})))
async function submit() {
  const { valid } = await formRef.value?.validate() || { valid: false }; if (valid)
    emit('submit', { ...draft.value })
}
</script>

<template>
  <VForm
    v-if="draft"
    :id="formId"
    ref="formRef"
    class="app-form"
    @submit.prevent="submit"
  >
    <VCardText class="kanban-editor-drawer pa-0">
      <VRow>
        <VCol cols="12" md="4">
          <ErpField
            v-model="draft.title"
            :label="$t('common.fields.title')"
            :placeholder="$t('common.fields.title')"
            :rules="[requiredValidator]"
            required
            :disabled="!canEdit"
          />
        </VCol>
        <VCol cols="12" md="4">
          <ErpField
            v-model="draft.startDate"
            kind="date"
            :label="$t('common.fields.startDate')"
            :placeholder="$t('common.fields.startDate')"
            :config="config"
            :disabled="!canEdit"
          />
        </VCol>
        <VCol cols="12" md="4">
          <ErpField
            v-model="draft.dueDate"
            kind="date"
            :label="$t('components.taskManager.taskdetaildrawer.dueDate')"
            :placeholder="$t('components.taskManager.taskdetaildrawer.dueDate')"
            :config="config"
            :disabled="!canEdit"
          />
        </VCol>
        <VCol v-if="canManageTags" cols="12" md="6">
          <ErpField
            v-model="draft.labels"
            class="task-form-multiselect"
            kind="select"
            :items="tags"
            item-title="name"
            item-value="name"
            :label="$t('common.messages.label')"
            :placeholder="$t('common.messages.label')"
            multiple
            chips
            closable-chips
            eager
            :disabled="!canEdit"
          />
        </VCol>
        <VCol v-if="canAssign" cols="12" md="6">
          <ErpField
            v-model="draft.members"
            class="task-form-multiselect"
            kind="select"
            :items="employees"
            item-title="name"
            item-value="id"
            :label="$t('pages.tasks.kanbanboardeditdrawer.kanbanboardeditdrawer.assigned')"
            :placeholder="$t('pages.tasks.kanbanboardeditdrawer.kanbanboardeditdrawer.assigned')"
            multiple
            chips
            closable-chips
            return-object
            :loading="loadingEmployees"
            :menu-props="{ offset: 10 }"
            :disabled="!canEdit"
          >
            <template #item="{ props: itemProps, item }">
              <VListItem v-bind="itemProps">
                <template #prepend>
                  <EmployeeAvatar :name="item.raw.name" :src="item.raw.avatar" size="32" />
                </template>
              </VListItem>
            </template>
          </ErpField>
        </VCol>
        <VCol v-if="canManageDependencies" cols="12" md="6">
          <ErpField
            v-model="draft.dependencies"
            class="task-form-multiselect"
            kind="select"
            :items="taskOptions"
            :label="$t('components.taskManager.taskdetaildrawer.dependsOn')"
            :placeholder="$t('components.taskManager.taskdetaildrawer.dependsOn')"
            multiple
            chips
            closable-chips
            :disabled="!canEdit"
          />
        </VCol>
        <VCol cols="12" md="6">
          <ErpField
            v-model="draft.priority"
            kind="select"
            :items="priorityOptions"
            :label="$t('common.fields.priority')"
            :placeholder="$t('common.fields.priority')"
            :disabled="!canEdit"
          />
        </VCol>
        <VCol cols="12">
          <ErpField
            v-model="draft.description"
            class="task-content-field"
            kind="textarea"
            rows="2"
            :label="$t('components.taskManager.taskdetaildrawer.taskContent')"
            :placeholder="$t('components.taskManager.taskdetaildrawer.noTaskContent')"
            :disabled="!canEdit"
          />
        </VCol>
      </VRow>
    </VCardText>
  </VForm>
</template>

<style lang="scss" scoped>
.task-form-multiselect :deep(.v-field__input.v-field__input) {
  display: flex;
  flex-wrap: nowrap !important;
  align-items: center;
  gap: 4px;
  overflow-x: scroll !important;
  overflow-y: hidden;
  scrollbar-width: none;
}

.task-form-multiselect :deep(.v-field__input::-webkit-scrollbar) { display: none; }
.task-form-multiselect :deep(.v-field) { overflow: hidden; }
.task-form-multiselect :deep(.v-select__selection) { flex: 0 0 auto !important; max-inline-size: 100%; min-inline-size: max-content; }
.task-form-multiselect :deep(.v-select__selection-comma) { display: none; }
.task-form-multiselect :deep(.v-select__selection .v-chip) { flex: 0 0 auto; }
.task-form-multiselect :deep(.v-select__selection-text) { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.task-form-multiselect :deep(.v-chip) { flex: 0 0 auto; max-inline-size: 16rem; }
.task-content-field :deep(.v-textarea .v-field),
.task-content-field :deep(.v-textarea .v-field__input) { min-block-size: 100px !important; }
</style>
