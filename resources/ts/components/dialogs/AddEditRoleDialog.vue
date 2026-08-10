<script setup lang="ts">
import PositionApi from '@/api/position'

interface Permission {
  id: number
  name: string
  category: string
}

interface Position {
  id: number
  title: string
  description?: string | null
  salary: number
  status: string
  is_sys_user: boolean
  permissions: Permission[]
}

const props = defineProps<{
  isDialogVisible: boolean
  position?: Position | null
  permissions: Permission[]
}>()

const emit = defineEmits<{
  (event: 'update:isDialogVisible', value: boolean): void
  (event: 'saved'): void
}>()

const form = ref({
  title: '',
  description: '',
  salary: 0,
  status: 'active',
  is_sys_user: false,
  permission_ids: [] as number[],
})
const isSaving = ref(false)
const errorMessage = ref('')

const groupedPermissions = computed(() => props.permissions.reduce<Record<string, Permission[]>>((groups: Record<string, Permission[]>, permission: Permission) => {
  const category = permission.category || 'uncategorized'

  if (!groups[category]) groups[category] = []
  groups[category].push(permission)

  return groups
}, {}))

const formatCategory = (category: string) => category
  .replace(/[_-]+/g, ' ')
  .replace(/\b\w/g, character => character.toUpperCase())

const resetForm = () => {
  const position = props.position
  form.value = {
    title: position?.title || '',
    description: position?.description || '',
    salary: position?.salary || 0,
    status: position?.status || 'active',
    is_sys_user: position?.is_sys_user || false,
    permission_ids: position?.permissions?.map((permission: Permission) => permission.id) || [],
  }
  errorMessage.value = ''
}

watch(() => [props.isDialogVisible, props.position], resetForm, { immediate: true })

const onSubmit = async () => {
  if (!form.value.title.trim()) {
    errorMessage.value = 'Role title is required.'
    return
  }

  isSaving.value = true
  errorMessage.value = ''

  try {
    if (props.position) await PositionApi.update(props.position.id, form.value)
    else await PositionApi.create(form.value)

    emit('saved')
    emit('update:isDialogVisible', false)
  } catch (error: any) {
    errorMessage.value = error.message || 'Unable to save the role.'
  } finally {
    isSaving.value = false
  }
}

const onClose = () => emit('update:isDialogVisible', false)
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 700"
    :model-value="props.isDialogVisible"
    @update:model-value="onClose"
  >
    <DialogCloseBtn @click="onClose" />
    <VCard class="pa-sm-8 pa-4">
      <VCardTitle class="text-center">
        {{ props.position ? 'Edit Role' : 'Add New Role' }}
      </VCardTitle>
      <VCardText>
        <VAlert v-if="errorMessage" type="error" class="mb-4">{{ errorMessage }}</VAlert>
        <VTextField v-model="form.title" label="Role Title" class="mb-4" />
        <VTextarea v-model="form.description" label="Description" class="mb-4" />
        <VTextField v-model.number="form.salary" type="number" label="Salary" class="mb-4" />
        <VSelect v-model="form.status" :items="['active', 'inactive']" label="Status" class="mb-4" />
        <VSwitch v-model="form.is_sys_user" label="System user role" class="mb-4" />
        <div class="text-subtitle-1 mb-2">Permissions by category</div>
        <VExpansionPanels v-if="Object.keys(groupedPermissions).length" multiple variant="accordion">
          <VExpansionPanel
            v-for="(categoryPermissions, category) in groupedPermissions"
            :key="category"
            :title="formatCategory(category)"
          >
            <VExpansionPanelText>
              <VCheckbox
                v-for="permission in categoryPermissions"
                :key="permission.id"
                v-model="form.permission_ids"
                :value="permission.id"
                :label="permission.name"
                density="compact"
                hide-details
              />
            </VExpansionPanelText>
          </VExpansionPanel>
        </VExpansionPanels>
        <div v-else class="text-body-2 text-medium-emphasis">
          No permissions are available.
        </div>
      </VCardText>
      <VCardActions class="justify-center gap-3">
        <VBtn :loading="isSaving" @click="onSubmit">Save</VBtn>
        <VBtn color="secondary" variant="tonal" @click="onClose">Cancel</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
