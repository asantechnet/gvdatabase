<script setup lang="ts">
import PermissionApi, { type Permission, type RolePermissions } from '@/api/permission'
import { apiErrorMessage } from '@/utils/apiErrors'
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'

const toast = useToast()
const permissions = ref<Permission[]>([])
const roles = ref<RolePermissions[]>([])
const loading = ref(false)
const savingRole = ref<string | null>(null)
const permissionDialog = ref(false)
const editingPermission = ref<Permission | null>(null)
const permissionForm = ref({ name: '', category: 'general' })

const categoryLabel = (category: string) => category.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase())
const groupedPermissions = computed(() => permissions.value.reduce<Record<string, Permission[]>>((groups, permission) => {
  ;(groups[permission.category] ??= []).push(permission)
  return groups
}, {}))

const roleDescription: Record<string, string> = {
  super_admin: 'Full system access. This role is intentionally not editable.',
  admin: 'Operational administration without identity, role, or audit-reversal control.',
  manager: 'Supervisory access to operational records and all task work.',
  staff: 'Assigned task work, own subtasks/comments, and personal notifications.',
  viewer: 'Read-only assigned task access and personal notifications.',
}

const isGranted = (role: RolePermissions, permission: string) => role.permissions.includes(permission)

const toggle = (role: RolePermissions, permission: string, enabled: boolean) => {
  if (role.name === 'super_admin') return
  role.permissions = enabled
    ? [...new Set([...role.permissions, permission])]
    : role.permissions.filter(current => current !== permission)
}

const save = async (role: RolePermissions) => {
  if (role.name === 'super_admin') return
  try {
    savingRole.value = role.name
    const saved = await PermissionApi.updateRolePermissions(role.name, role.permissions)
    role.permissions = saved.permissions
    toast.success(`${role.name.replace('_', ' ')} permissions saved.`)
  } catch (error) {
    toast.error(apiErrorMessage(error, 'Unable to update role permissions.'))
  } finally {
    savingRole.value = null
  }
}

const openPermissionDialog = (permission?: Permission) => {
  editingPermission.value = permission || null
  permissionForm.value = permission ? { name: permission.name, category: permission.category } : { name: '', category: 'general' }
  permissionDialog.value = true
}

const savePermission = async () => {
  try {
    if (editingPermission.value) await PermissionApi.update(editingPermission.value.id, permissionForm.value)
    else await PermissionApi.create(permissionForm.value)
    permissions.value = await PermissionApi.getAll()
    roles.value = await PermissionApi.getRoles()
    permissionDialog.value = false
    toast.success('Permission saved.')
  } catch (error) { toast.error(apiErrorMessage(error, 'Unable to save permission.')) }
}

const deletePermission = async (permission: Permission) => {
  if (!confirm(`Delete permission ${permission.name}?`)) return
  try { await PermissionApi.delete(permission.id); permissions.value = await PermissionApi.getAll(); roles.value = await PermissionApi.getRoles() }
  catch (error) { toast.error(apiErrorMessage(error, 'Unable to delete permission.')) }
}

onMounted(async () => {
  try {
    loading.value = true
    ;[permissions.value, roles.value] = await Promise.all([PermissionApi.getAll(), PermissionApi.getRoles()])
  } catch (error) {
    toast.error(apiErrorMessage(error, 'Unable to load roles and permissions.'))
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="d-flex justify-end mb-4"><VBtn prepend-icon="tabler-plus" @click="openPermissionDialog()">Add permission</VBtn></div>
  <VAlert type="info" variant="tonal" class="mb-6">
    {{ $t('pages.settings.rolecards.rolecards.rolesGrantPermissionsPositionsRemainHRJobTitlesChangesTakeEffectWhenAf') }}
  </VAlert>

  <VProgressLinear v-if="loading" indeterminate class="mb-4" />

  <VExpansionPanels v-else variant="accordion">
    <VExpansionPanel v-for="role in roles" :key="role.name">
      <VExpansionPanelTitle>
        <div>
          <div class="text-h6 text-capitalize">{{ role.name.replace('_', ' ') }}</div>
          <div class="text-caption text-medium-emphasis">{{ roleDescription[role.name] }}</div>
        </div>
      </VExpansionPanelTitle>
      <VExpansionPanelText>
        <VRow>
          <VCol v-for="(categoryPermissions, category) in groupedPermissions" :key="String(category)" cols="12" md="6" lg="4">
            <VCard variant="tonal" class="h-100">
              <VCardItem><VCardTitle class="text-subtitle-1">{{ categoryLabel(String(category)) }}</VCardTitle></VCardItem>
              <VCardText class="pt-0">
                <div v-for="permission in categoryPermissions" :key="permission.name" class="d-flex align-center">
                  <VCheckbox :model-value="isGranted(role, permission.name)" :label="permission.name.replaceAll('_', ' ')" density="compact" hide-details class="flex-grow-1" :disabled="role.name === 'super_admin'" @update:model-value="toggle(role, permission.name, Boolean($event))" />
                  <template v-if="role.name === 'super_admin'"><VBtn icon="tabler-edit" size="x-small" variant="text" @click="openPermissionDialog(permission)" /><VBtn icon="tabler-trash" size="x-small" variant="text" color="error" @click="deletePermission(permission)" /></template>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
        <div v-if="role.name !== 'super_admin'" class="d-flex justify-end mt-5">
          <VBtn color="primary" :loading="savingRole === role.name" @click="save(role)">{{ $t('pages.settings.rolecards.rolecards.savePermissions') }}</VBtn>
        </div>
      </VExpansionPanelText>
    </VExpansionPanel>
  </VExpansionPanels>
  <VDialog v-model="permissionDialog" max-width="520"><VCard :title="editingPermission ? 'Edit permission' : 'Add permission'"><VCardText><VTextField v-model="permissionForm.name" label="Machine name" class="mb-3" /><VTextField v-model="permissionForm.category" label="Category" /></VCardText><VCardActions><VSpacer /><VBtn variant="tonal" @click="permissionDialog=false">Cancel</VBtn><VBtn :disabled="!permissionForm.name.trim() || !permissionForm.category.trim()" @click="savePermission">Save</VBtn></VCardActions></VCard></VDialog>
</template>
