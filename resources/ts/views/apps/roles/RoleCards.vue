<script setup lang="ts">
import RoleApi, { type Role } from '@/api/role'
import PermissionApi, { type Permission } from '@/api/permission'
import { apiErrorMessage } from '@/utils/apiErrors'
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'

const toast = useToast()
const permissions = ref<Permission[]>([])
const roles = ref<Role[]>([])
const loading = ref(false)

// Role CRUD state
const roleDialog = ref(false)
const editingRole = ref<Role | null>(null)
const savingRole = ref(false)
const roleName = ref('')
const deleteRoleDialog = ref(false)
const deletingRole = ref<Role | null>(null)

// Permission edit state (per-role, track dirty bits)
const editingPermissions = ref<Record<number, string[]>>({})
const savingPermissions = ref<Record<number, boolean>>({})

const categoryLabel = (category: string) => category.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase())

const groupedPermissions = computed(() =>
  permissions.value.reduce<Record<string, Permission[]>>((groups, permission) => {
    ;(groups[permission.category] ??= []).push(permission)
    return groups
  }, {}),
)

const roleDescription = (role: Role) => role.name.replace('_', ' ')

function isGranted(role: Role, permission: string): boolean {
  // When editing, use the dirty buffer; otherwise the loaded role
  const buffer = editingPermissions.value[role.id]
  return buffer !== undefined ? buffer.includes(permission) : role.permissions.includes(permission)
}

function togglePermission(role: Role, permission: string): void {
  const buffer = editingPermissions.value[role.id] ?? [...role.permissions]
  const idx = buffer.indexOf(permission)
  if (idx >= 0) {
    buffer.splice(idx, 1)
  } else {
    buffer.push(permission)
  }
  editingPermissions.value[role.id] = buffer
}

function isDirty(role: Role): boolean {
  const buffer = editingPermissions.value[role.id]
  if (!buffer) return false
  const orig = [...role.permissions].sort()
  const cur = [...buffer].sort()
  return JSON.stringify(orig) !== JSON.stringify(cur)
}

async function savePermissions(role: Role): Promise<void> {
  const buffer = editingPermissions.value[role.id]
  if (!buffer) return
  savingPermissions.value[role.id] = true
  try {
    const updated = await RoleApi.updateRolePermissions(role.id, buffer)
    const idx = roles.value.findIndex(r => r.id === role.id)
    if (idx !== -1) roles.value[idx] = updated
    delete editingPermissions.value[role.id]
    toast.success('Permissions updated.')
  } catch (error) {
    toast.error(apiErrorMessage(error, 'Unable to save permissions.'))
  } finally {
    savingPermissions.value[role.id] = false
  }
}

const loadAll = async () => {
  loading.value = true
  try {
    ;[permissions.value, roles.value] = await Promise.all([PermissionApi.getAll(), RoleApi.getRoles()])
  } catch (error) {
    toast.error(apiErrorMessage(error, 'Unable to load roles and permissions.'))
  } finally {
    loading.value = false
  }
}

// --- Role CRUD ---
const openCreateRole = () => {
  editingRole.value = null
  roleName.value = ''
  roleDialog.value = true
}

const openEditRole = (role: Role) => {
  editingRole.value = role
  roleName.value = role.name
  roleDialog.value = true
}

const saveRole = async () => {
  if (!roleName.value.trim()) {
    toast.error('Role name is required.')
    return
  }
  savingRole.value = true
  try {
    if (editingRole.value) {
      const saved = await RoleApi.updateRole(editingRole.value.id, { name: roleName.value })
      const idx = roles.value.findIndex(r => r.id === saved.id)
      if (idx !== -1) roles.value[idx] = saved
      toast.success('Role updated.')
    } else {
      roles.value.push(await RoleApi.createRole({ name: roleName.value }))
      toast.success('Role created.')
    }
    roleDialog.value = false
  } catch (error) {
    toast.error(apiErrorMessage(error, 'Unable to save role.'))
  } finally {
    savingRole.value = false
  }
}

const askDeleteRole = (role: Role) => {
  deletingRole.value = role
  deleteRoleDialog.value = true
}

const confirmDeleteRole = async () => {
  if (!deletingRole.value) return
  try {
    await RoleApi.deleteRole(deletingRole.value.id)
    roles.value = roles.value.filter(r => r.id !== deletingRole.value?.id)
    deleteEditingPermissions(deletingRole.value.id)
    deleteRoleDialog.value = false
    toast.success('Role deleted.')
  } catch (error) {
    toast.error(apiErrorMessage(error, 'Unable to delete role.'))
  }
}

function deleteEditingPermissions(id: number): void {
  delete editingPermissions.value[id]
  delete savingPermissions.value[id]
}

onMounted(loadAll)
</script>

<template>
  <div class="d-flex justify-end mb-4">
    <VBtn prepend-icon="tabler-plus" @click="openCreateRole">Create role</VBtn>
  </div>
  <VAlert type="info" variant="tonal" class="mb-6">
    {{ $t('pages.settings.rolecards.rolecards.rolesGrantPermissionsPositionsRemainHRJobTitlesChangesTakeEffectWhenAf') }}
  </VAlert>

  <VProgressLinear v-if="loading" indeterminate class="mb-4" />

  <VExpansionPanels v-else variant="accordion">
    <VExpansionPanel v-for="role in roles" :key="role.name">
      <VExpansionPanelTitle>
        <div class="d-flex align-center w-100">
          <div class="flex-grow-1">
            <div class="text-h6 text-capitalize">{{ role.name.replace('_', ' ') }}</div>
            <div class="text-caption text-medium-emphasis">{{ roleDescription(role) }}</div>
            <VChip v-if="role.users_count > 0" size="x-small" variant="tonal" class="mt-1">
              {{ role.users_count }} {{ role.users_count === 1 ? 'user' : 'users' }}
            </VChip>
          </div>
          <div v-if="role.name !== 'super_admin'" class="d-flex align-center">
            <IconBtn :aria-label="`Edit ${role.name}`" size="x-small" variant="text" @click.stop="openEditRole(role)">
              <VIcon icon="tabler-pencil" />
            </IconBtn>
            <IconBtn :aria-label="`Delete ${role.name}`" size="x-small" variant="text" color="error" @click.stop="askDeleteRole(role)">
              <VIcon icon="tabler-trash" />
            </IconBtn>
          </div>
        </div>
      </VExpansionPanelTitle>
      <VExpansionPanelText>
        <VRow>
          <VCol v-for="(categoryPermissions, category) in groupedPermissions" :key="String(category)" cols="12" md="6" lg="4">
            <VCard variant="tonal" class="h-100">
              <VCardItem><VCardTitle class="text-subtitle-1">{{ categoryLabel(String(category)) }}</VCardTitle></VCardItem>
              <VCardText class="pt-0">
                <div v-for="permission in categoryPermissions" :key="permission.name" class="d-flex align-center">
                  <VCheckbox
                    :model-value="isGranted(role, permission.name)"
                    :label="permission.name.replaceAll('_', ' ')"
                    density="compact"
                    hide-details
                    class="flex-grow-1"
                    :disabled="role.name === 'super_admin'"
                    @update:model-value="togglePermission(role, permission.name)"
                  />
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
        <div v-if="role.name !== 'super_admin'" class="d-flex justify-end mt-4">
          <VBtn
            :disabled="!isDirty(role)"
            :loading="savingPermissions[role.id]"
            color="primary"
            size="small"
            @click="savePermissions(role)"
          >
            Save permissions
          </VBtn>
        </div>
      </VExpansionPanelText>
    </VExpansionPanel>
  </VExpansionPanels>

  <!-- Create/Edit Role -->
  <VDialog v-model="roleDialog" max-width="500">
    <VCard :title="editingRole ? 'Edit Role' : 'Create Role'">
      <VCardText>
        <VForm @submit.prevent="saveRole">
          <VTextField
            v-model="roleName"
            label="Role name"
            placeholder="e.g. editor"
            required
            :disabled="!!editingRole"
            hide-details="auto"
          />
        </VForm>
      </VCardText>
      <VDivider />
      <VCardActions>
        <VSpacer />
        <VBtn variant="tonal" @click="roleDialog = false">Cancel</VBtn>
        <VBtn :loading="savingRole" @click="saveRole">{{ editingRole ? 'Save' : 'Create' }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Delete Confirmation -->
  <VDialog v-model="deleteRoleDialog" max-width="420">
    <VCard title="Delete Role">
      <VCardText>
        Are you sure you want to delete <strong>{{ deletingRole?.name?.replace('_', ' ') }}</strong>? This will remove its permission grants.
      </VCardText>
      <VDivider />
      <VCardActions>
        <VSpacer />
        <VBtn variant="tonal" @click="deleteRoleDialog = false">Cancel</VBtn>
        <VBtn color="error" @click="confirmDeleteRole">Delete</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
