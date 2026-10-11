<script setup lang="ts">
import { ref, watch } from 'vue'
import { VForm } from 'vuetify/components/VForm'
import { requiredValidator } from '@core/utils/validators'

const props = defineProps<{ item: any; employees: any[]; tags: any[]; loadingEmployees: boolean; canEdit: boolean; canAssign: boolean; canManageTags: boolean; canDelete: boolean }>()
const emit = defineEmits<{ submit: [item: any]; delete: [] }>()
const formRef = ref<VForm>()
const draft = ref<any>({})
const config = { enableTime: false, dateFormat: 'Y-m-d' }

watch(() => props.item, item => { draft.value = { ...item, labels: [...(item?.labels ?? [])], members: [...(item?.members ?? [])] } }, { deep: true, immediate: true })

const getEmployeeInitial = (name: string) => name.trim().charAt(0).toUpperCase() || '?'
const resolveLabelColor = (color: string) => ({ error: 'error', primary: 'primary', info: 'info', warning: 'warning', secondary: 'secondary', success: 'success' }[color] || 'primary')
async function submit() {
  const { valid } = await formRef.value?.validate() || { valid: false }; if (valid)
    emit('submit', { ...draft.value })
}
</script>

<template>
  <VForm
    v-if="draft"
    ref="formRef"
    class="app-form"
    @submit.prevent="submit"
  >
    <VCardText class="kanban-editor-drawer">
      <VRow>
        <VCol cols="12" md="6">
          <p class="app-form__section">
            {{ $t('common.messages.details') }}
          </p><p class="app-form__section-help">
            {{ $t('common.messages.taskFormHint') }}
          </p>
        </VCol>
        <VCol cols="12" md="6">
          <AppTextField
            v-model="draft.title"
            :label="$t('common.fields.title')"
            prepend-inner-icon="tabler-checkbox"
            :rules="[requiredValidator]"
            :disabled="!canEdit"
          />
        </VCol>
        <VCol cols="12">
          <AppDateTimePicker
            v-model="draft.dueDate"
            :label="$t('common.messages.dueDate')"
            :config="config"
            :disabled="!canEdit"
          />
        </VCol>
        <VCol
          v-if="canManageTags"
          cols="12"
          md="6"
        >
          <AppSelect
            v-model="draft.labels"
            :items="tags"
            item-title="name"
            item-value="name"
            :label="$t('common.messages.label')"
            multiple
            eager
          >
            <template #chip="{ item }">
              <VChip :color="resolveLabelColor(item.raw.color)">
                {{ item.raw.name }}
              </VChip>
            </template>
          </AppSelect>
        </VCol>
        <VCol
          v-if="canAssign"
          cols="12"
          md="6"
        >
          <p class="mb-1 text-body-2 text-high-emphasis assigned-label">
            {{ $t('pages.tasks.kanbanboardeditdrawer.kanbanboardeditdrawer.assigned') }}
          </p><VSelect
            v-model="draft.members"
            :items="employees"
            item-title="name"
            item-value="id"
            multiple
            return-object
            variant="plain"
            :loading="loadingEmployees"
            :menu-props="{ offset: 10 }"
            class="assignee-select"
          >
            <template #selection="{ item }">
              <VAvatar
                size="26"
                color="primary"
                variant="tonal"
              >
                <VImg
                  v-if="item.raw.avatar"
                  :src="item.raw.avatar"
                  :alt="item.raw.name"
                /><span
                  v-else
                  class="text-caption font-weight-medium"
                >{{ getEmployeeInitial(item.raw.name) }}</span><VTooltip activator="parent">
                  {{ item.raw.name }}
                </VTooltip>
              </VAvatar>
            </template><template #prepend-inner>
              <IconBtn
                size="26"
                variant="tonal"
                color="secondary"
              >
                <VIcon
                  size="20"
                  icon="tabler-plus"
                />
              </IconBtn>
            </template><template #item="{ props: itemProps, item }">
              <VListItem v-bind="itemProps">
                <template #prepend>
                  <VAvatar
                    size="32"
                    color="primary"
                    variant="tonal"
                  >
                    <VImg
                      v-if="item.raw.avatar"
                      :src="item.raw.avatar"
                      :alt="item.raw.name"
                    /><span
                      v-else
                      class="text-caption font-weight-medium"
                    >{{ getEmployeeInitial(item.raw.name) }}</span>
                  </VAvatar>
                </template>
              </VListItem>
            </template>
          </VSelect>
        </VCol>
        <VCol cols="12">
          <div class="app-form__actions">
            <VBtn
              v-if="canDelete"
              color="error"
              variant="tonal"
              prepend-icon="tabler-trash"
              @click="emit('delete')"
            >
              {{ $t('common.actions.delete') }}
            </VBtn><VBtn
              v-if="canEdit"
              type="submit"
              prepend-icon="tabler-check"
            >
              {{ $t('common.actions.update') }}
            </VBtn>
          </div>
        </VCol>
      </VRow>
    </VCardText>
  </VForm>
</template>

<style lang="scss" scoped>
.assigned-label { line-height: 15px; }.assignee-select :deep(.v-field__append-inner .v-select__menu-icon) { display: none; }
</style>
