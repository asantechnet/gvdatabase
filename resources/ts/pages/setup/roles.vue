<script setup lang="ts">

import PermissionApi, { type Permission } from '@/api/permission'
import RoleApi from '@/api/role'
import { apiErrorMessage } from '@/utils/apiErrors'
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'

// Extend internal type representation to match the new API payload
export interface AppUser {
  id: number;
  name: string;
  employee?: { avatar?: string | null };
}
export interface AppRole {
  id: number;
  name: string;
  users_count?: number;
  permissions: any[];
  users?: AppUser[];
}

const toast = useToast()
const permissions = ref<Permission[]>([])
const roles = ref<AppRole[]>([])
const loading = ref(false)

// Role CRUD state
const roleDialog = ref(false)
const editingRole = ref<AppRole | null>(null)
const savingRole = ref(false)
const roleForm = ref<{ name: string }>({ name: '' })
const selectedPermissions = ref<string[]>([])
const deleteRoleDialog = ref(false)
const deletingRole = ref<AppRole | null>(null)

// Avatar generation helpers
const getInitials = (name: string) => {
  if (!name) return 'U'
  const parts = name.split(' ')
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase()
  return name.substring(0, 2).toUpperCase()
}

const avatarColors = ['primary', 'secondary', 'success', 'info', 'warning', 'error']
const getAvatarColor = (name: string) => {
  if (!name) return 'primary'
  let hash = 0
  for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash)
  return avatarColors[Math.abs(hash) % avatarColors.length]
}

const categoryLabel = (category: string) => category.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase())

const permissionLabel = (permission: Permission) => {
  const prefix = `${permission.category}_`
  const raw = permission.name.startsWith(prefix) ? permission.name.slice(prefix.length) : permission.name
  return raw.replaceAll('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
}

const permissionSearch = ref('')

// Global permission search filter (space-separated input matches snake_case slugs)
const filteredPermissions = computed(() => {
  const q = permissionSearch.value.trim().toLowerCase().replace(/[_\s]+/g, '')
  if (!q) return permissions.value
  return permissions.value.filter(p =>
    p.name.toLowerCase().replace(/[_\s]+/g, '').includes(q) ||
    categoryLabel(p.category).toLowerCase().replace(/[_\s]+/g, '').includes(q),
  )
})

const groupedPermissions = computed(() => filteredPermissions.value.reduce<Record<string, Permission[]>>((groups, permission) => {
  ; (groups[permission.category] ??= []).push(permission)
  return groups
}, {}))

// Collapsible permission categories
const expandedCategories = ref<Record<string, boolean>>({})
const isCategoryExpanded = (category: string) => {
  if (permissionSearch.value.trim()) return true
  return expandedCategories.value[category] ?? true
}
const toggleCategory = (category: string) => {
  expandedCategories.value[category] = !isCategoryExpanded(category)
}

// Category meta helpers for indeterminate + counts (based on currently visible/filtered permissions)
// Use computed map to ensure Vuetify checkbox reactivity (Vuetify needs stable reactive source)
const categoryMetaMap = computed(() => {
  const map: Record<string, { total: number; selected: number; allSelected: boolean; indeterminate: boolean }> = {}
  for (const [cat, perms] of Object.entries(groupedPermissions.value)) {
    const total = perms.length
    const selected = perms.filter(p => selectedPermissions.value.includes(p.name)).length
    map[cat] = {
      total,
      selected,
      allSelected: total > 0 && selected === total,
      indeterminate: selected > 0 && selected < total,
    }
  }
  return map
})

const getCategoryMeta = (category: string) => {
  return categoryMetaMap.value[category] ?? { total: 0, selected: 0, allSelected: false, indeterminate: false }
}

const isCategoryAllSelected = (category: string) => getCategoryMeta(category).allSelected
const isCategoryIndeterminate = (category: string) => getCategoryMeta(category).indeterminate

const toggleCategoryPermissions = (category: string) => {
  if (editingRole.value?.name === 'super_admin') return
  const catPerms = (groupedPermissions.value[category] ?? []).map(p => p.name)
  if (!catPerms.length) return
  const allSelected = catPerms.every(p => selectedPermissions.value.includes(p))
  if (allSelected) {
    selectedPermissions.value = selectedPermissions.value.filter(p => !catPerms.includes(p))
  } else {
    const existing = new Set(selectedPermissions.value)
    catPerms.forEach(p => existing.add(p))
    selectedPermissions.value = [...existing]
  }
}

// Global selection (optional helper bar)
const allFilteredSelected = computed(() => {
  if (!filteredPermissions.value.length) return false
  return filteredPermissions.value.every(p => selectedPermissions.value.includes(p.name))
})
const isAllFilteredIndeterminate = computed(() => {
  const total = filteredPermissions.value.length
  const selected = filteredPermissions.value.filter(p => selectedPermissions.value.includes(p.name)).length
  return selected > 0 && selected < total
})
const toggleAllFiltered = () => {
  if (editingRole.value?.name === 'super_admin') return
  const names = filteredPermissions.value.map(p => p.name)
  const allSelected = names.every(n => selectedPermissions.value.includes(n))
  if (allSelected) {
    selectedPermissions.value = selectedPermissions.value.filter(p => !names.includes(p))
  } else {
    const set = new Set(selectedPermissions.value)
    names.forEach(n => set.add(n))
    selectedPermissions.value = [...set]
  }
}

const clearSearch = () => {
  permissionSearch.value = ''
}

const isProtected = computed(() => editingRole.value?.name === 'super_admin')

const loadAll = async () => {
  loading.value = true
  try {
    const [permsResponse, rolesResponse] = await Promise.all([
      PermissionApi.getAll(),
      RoleApi.getRoles()
    ])
    permissions.value = permsResponse
    roles.value = rolesResponse as unknown as AppRole[]
  } catch (error) {
    toast.error(apiErrorMessage(error, 'Unable to load roles and permissions.'))
  } finally {
    loading.value = false
  }
}

// --- Role CRUD ---
const openCreateRole = () => {
  editingRole.value = null
  roleForm.value = { name: '' }
  selectedPermissions.value = []
  permissionSearch.value = ''
  roleDialog.value = true
}

const openEditRole = (role: AppRole) => {
  editingRole.value = role
  roleForm.value = { name: role.name }
  selectedPermissions.value = role.permissions?.map((p: any) => p.name || p) || []
  permissionSearch.value = ''
  roleDialog.value = true
}

const saveRole = async () => {
  if (!roleForm.value.name.trim()) {
    toast.error('Role name is required.')
    return
  }
  savingRole.value = true
  try {
    let savedRole: any
    if (editingRole.value) {
      savedRole = await RoleApi.updateRole(editingRole.value.id, { name: roleForm.value.name })
      const idx = roles.value.findIndex(r => r.id === savedRole.id)
      if (idx !== -1) roles.value[idx] = savedRole
      // Persist the selected permissions only for editable roles
      if (editingRole.value.name !== 'super_admin') {
        await RoleApi.updateRolePermissions(editingRole.value.id, selectedPermissions.value)
      }
      toast.success('Role updated successfully.')
    } else {
      savedRole = await RoleApi.createRole({ name: roleForm.value.name })
      roles.value.push(savedRole)
      // Persist permissions for newly created roles
      await RoleApi.updateRolePermissions(savedRole.id, selectedPermissions.value)
      toast.success('Role created successfully.')
    }

    roleDialog.value = false
    await loadAll()
  } catch (error) {
    toast.error(apiErrorMessage(error, 'Unable to save role.'))
  } finally {
    savingRole.value = false
  }
}

const askDeleteRole = (role: AppRole) => {
  deletingRole.value = role
  deleteRoleDialog.value = true
}

const confirmDeleteRole = async () => {
  if (!deletingRole.value) return
  try {
    await RoleApi.deleteRole(deletingRole.value.id)
    roles.value = roles.value.filter(r => r.id !== deletingRole.value?.id)
    deleteRoleDialog.value = false
    toast.success('Role deleted successfully.')
  } catch (error) {
    toast.error(apiErrorMessage(error, 'Unable to delete role.'))
  }
}

onMounted(loadAll)
</script>

<template>
  <div>
    <VAlert type="info" variant="tonal" class="mb-6">
      {{ $t('pages.settings.rolecards.rolecards.rolesGrantPermissionsPositionsRemainHRJobTitlesChangesTakeEffectWhenAf')
      }}
    </VAlert>

    <VProgressLinear v-if="loading" indeterminate class="mb-4" />

    <VRow v-else>
      <!-- Existing Roles Cards -->
      <VCol v-for="role in roles" :key="role.name" cols="12" sm="6" lg="4">
        <VCard>
          <VCardText class="d-flex align-center pb-4">
            <span class="text-subtitle-1">Total {{ role.users_count || 0 }} users</span>
            <VSpacer />

            <!-- Dynamic Avatars based on Employee relation -->
            <div class="v-avatar-group">
              <template v-for="user in (role.users || []).slice(0, 4)" :key="user.id">
                <VAvatar v-if="user.employee?.avatar" size="36" :image="user.employee.avatar" />
                <VAvatar v-else size="36" :color="getAvatarColor(user.name)" variant="tonal">
                  <span class="text-sm font-weight-medium">{{ getInitials(user.name) }}</span>
                </VAvatar>
              </template>

              <VAvatar v-if="(role.users_count || 0) > 4" size="36" color="secondary" variant="tonal">
                <span class="text-sm">+{{ role.users_count! - 4 }}</span>
              </VAvatar>
            </div>
          </VCardText>

          <VCardText>
            <h4 class="text-h4 mb-1 text-capitalize">
              {{ role.name.replace('_', ' ') }}
            </h4>
            <div class="d-flex align-center">
              <a href="javascript:void(0)" class="text-primary font-weight-medium" @click.prevent="openEditRole(role)">
                Edit Role
              </a>
              <VSpacer />
              <IconBtn v-if="role.name !== 'super_admin'" :aria-label="`Delete ${role.name}`" color="error" size="small"
                @click.stop="askDeleteRole(role)">
                <VIcon icon="tabler-trash" />
              </IconBtn>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Add New Role Card -->
      <VCol cols="12" sm="6" lg="4">
        <VCard class="h-100 cursor-pointer" @click="openCreateRole">
          <VRow no-gutters class="h-100">
            <VCol cols="5" class="d-flex flex-column justify-end align-center mt-sm-8 mt-5">
              <img width="85" src="@images/illustrations/sitting-girl-with-laptop.png" alt="add-role" />
            </VCol>
            <VCol cols="7">
              <VCardText class="d-flex flex-column align-end justify-center h-100 text-right">
                <VBtn class="mb-2" @click.stop="openCreateRole">Add New Role</VBtn>
                <span class="text-caption">Add role, if it doesn't exist</span>
              </VCardText>
            </VCol>
          </VRow>
        </VCard>
      </VCol>
    </VRow>

    <!-- Create/Edit Role Dialog -->
    <VDialog v-model="roleDialog" max-width="860" scrollable>
      <VCard>
        <VCardItem class="pb-2">
          <VCardTitle class="text-h5 font-weight-bold">
            {{ editingRole ? 'Edit Role' : 'Create Role' }}
          </VCardTitle>
          <template #append>
            <IconBtn size="small" @click="roleDialog = false">
              <VIcon icon="tabler-x" size="20" />
            </IconBtn>
          </template>
        </VCardItem>
        <VDivider />
        <VCardText class="pt-6" style="max-height: 75vh; overflow-y: auto;">
          <VForm class="app-form" @submit.prevent="saveRole">
            <VRow>
              <VCol cols="12" class="mb-1">
                <VTextField v-model="roleForm.name" label="Role Name" placeholder="e.g. Editor, Billing Manager" required
                  :disabled="isProtected" hide-details="auto" density="comfortable" variant="outlined" />
                <p v-if="isProtected" class="text-caption text-warning mt-2 d-flex align-center gap-1">
                  <VIcon icon="tabler-lock" size="14" />
                  Super admin permissions cannot be modified
                </p>
              </VCol>

              <!-- Permissions Section -->
              <VCol cols="12">
                <div class="permissions-section">
                  <!-- Section header -->
                  <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-4">
                    <div>
                      <h6 class="text-h6 font-weight-bold d-flex align-center gap-2 mb-1">
                        <VIcon icon="tabler-shield-lock" size="22" color="primary" />
                        Role Permissions
                      </h6>
                      <p class="text-body-2 text-medium-emphasis mb-0" style="line-height: 1.5;">
                        Search and manage permissions by category. Toggle individual permissions or entire categories.
                      </p>
                    </div>
                    <VChip v-if="permissions.length" size="small" color="primary" variant="tonal"
                      class="font-weight-semibold flex-shrink-0">
                      {{ selectedPermissions.length }} / {{ permissions.length }} selected
                    </VChip>
                  </div>

                  <!-- Search + summary toolbar -->
                  <VCard variant="outlined" class="permission-toolbar mb-4" elevation="0">
                    <div class="d-flex align-center flex-wrap gap-3 pa-3">
                      <div class="flex-grow-1" style="min-width: 240px;">
                        <VTextField v-model="permissionSearch" placeholder="Search permissions (e.g. export, view users)"
                          prepend-inner-icon="tabler-search" density="compact" variant="outlined" hide-details clearable
                          :disabled="isProtected" @click:clear="clearSearch">
                          <template v-if="permissionSearch" #append-inner>
                            <VBtn size="x-small" variant="text" icon="tabler-x" @click="clearSearch" />
                          </template>
                        </VTextField>
                      </div>
                      <div class="d-flex align-center gap-2 ms-auto">
                        <span class="text-body-2 text-medium-emphasis d-none d-sm-inline">
                          {{ filteredPermissions.length }} permissions
                          <template v-if="permissionSearch"> · filtered</template>
                        </span>
                        <VDivider vertical inset class="mx-1 d-none d-sm-flex" />
                        <VCheckbox :model-value="allFilteredSelected" :indeterminate="isAllFilteredIndeterminate"
                          :disabled="isProtected || !filteredPermissions.length" label="Select all filtered"
                          density="compact" hide-details color="primary" class="toolbar-select-all"
                          @update:model-value="toggleAllFiltered" />
                      </div>
                    </div>
                  </VCard>

                  <!-- Category cards grid -->
                  <div v-if="Object.keys(groupedPermissions).length" class="permissions-grid">
                    <VCard v-for="(categoryPermissions, category) in groupedPermissions" :key="String(category)"
                      variant="outlined" elevation="0" class="permission-category-card overflow-hidden">
                      <!-- Category header (click to collapse/expand) -->
                      <div class="category-header d-flex align-center justify-space-between gap-3 px-4 py-3 cursor-pointer"
                        :class="{ 'category-header--collapsed': !isCategoryExpanded(String(category)) }"
                        @click="toggleCategory(String(category))">
                        <div class="d-flex align-center gap-3 min-w-0 flex-grow-1">
                          <VIcon :icon="isCategoryExpanded(String(category)) ? 'tabler-chevron-down' : 'tabler-chevron-right'"
                            size="18" class="text-medium-emphasis flex-shrink-0 chevron-icon" />
                          <div class="category-icon-wrapper d-flex align-center justify-center rounded flex-shrink-0">
                            <VIcon icon="tabler-folder" size="18" color="primary" />
                          </div>
                          <div class="min-w-0">
                            <div class="d-flex align-center gap-2 flex-wrap">
                              <span class="text-subtitle-2 font-weight-bold text-high-emphasis" style="letter-spacing: 0.02em;">
                                {{ categoryLabel(String(category)) }}
                              </span>
                              <VChip size="x-small" variant="tonal" :color="categoryMetaMap[String(category)]?.indeterminate ? 'warning' : categoryMetaMap[String(category)]?.allSelected ? 'success' : 'default'"
                                class="font-weight-medium count-chip">
                                {{ categoryMetaMap[String(category)]?.selected ?? 0 }}/{{ categoryMetaMap[String(category)]?.total ?? 0 }}
                              </VChip>
                            </div>
                            <span class="text-caption text-medium-emphasis">
                              {{ categoryMetaMap[String(category)]?.total ?? 0 }} permissions
                            </span>
                          </div>
                        </div>

                        <div class="d-flex align-center gap-2 flex-shrink-0" @click.stop>
                          <VCheckbox :model-value="categoryMetaMap[String(category)]?.allSelected ?? false"
                            :indeterminate="categoryMetaMap[String(category)]?.indeterminate ?? false" :disabled="isProtected"
                            :label="categoryMetaMap[String(category)]?.allSelected ? 'Deselect All' : 'Select All'"
                            density="compact" hide-details color="primary" class="category-select-checkbox"
                            @update:model-value="toggleCategoryPermissions(String(category))" @click.stop />
                        </div>
                      </div>

                      <VExpandTransition>
                        <div v-show="isCategoryExpanded(String(category))">
                          <VDivider />
                          <!-- Permissions body -->
                          <div class="category-body pa-3">
                            <div class="permissions-grid-inner">
                              <label v-for="permission in categoryPermissions" :key="permission.name"
                                class="permission-row d-flex align-center gap-2 px-3 py-2 rounded cursor-pointer"
                                :class="[
                                  selectedPermissions.includes(permission.name) ? 'permission-row--selected' : '',
                                  isProtected ? 'permission-row--disabled' : ''
                                ]"
                                @click.prevent="!isProtected && (selectedPermissions.includes(permission.name) ? selectedPermissions = selectedPermissions.filter(p => p !== permission.name) : selectedPermissions = [...selectedPermissions, permission.name])">
                                <VCheckboxBtn :model-value="selectedPermissions.includes(permission.name)"
                                  :value="permission.name" color="primary" density="compact" hide-details
                                  :disabled="isProtected" tabindex="-1" class="permission-checkbox" />
                                <span class="text-body-2 flex-grow-1"
                                  :class="selectedPermissions.includes(permission.name) ? 'text-high-emphasis font-weight-medium' : 'text-medium-emphasis'"
                                  style="line-height: 1.4;">
                                  {{ permissionLabel(permission as Permission) }}
                                </span>
                                <VIcon v-if="selectedPermissions.includes(permission.name)" icon="tabler-check" size="14"
                                  color="primary" class="flex-shrink-0" />
                              </label>
                            </div>
                          </div>
                        </div>
                      </VExpandTransition>
                    </VCard>
                  </div>

                  <!-- Empty state -->
                  <VCard v-else variant="outlined" class="empty-state-card text-center pa-8" elevation="0">
                    <div class="d-flex flex-column align-center gap-3">
                      <div class="empty-icon-wrapper d-flex align-center justify-center rounded-circle mx-auto">
                        <VIcon icon="tabler-search-off" size="28" color="disabled" />
                      </div>
                      <div>
                        <h6 class="text-h6 font-weight-bold mb-1">No permissions found</h6>
                        <p class="text-body-2 text-medium-emphasis mb-0">
                          Try searching for a different permission.
                          <template v-if="permissionSearch">
                            No results for "<strong class="text-high-emphasis">{{ permissionSearch }}</strong>"
                          </template>
                        </p>
                      </div>
                      <VBtn v-if="permissionSearch" variant="tonal" color="primary" size="small" class="mt-2"
                        @click="clearSearch">
                        Clear search
                      </VBtn>
                    </div>
                  </VCard>

                  <!-- Footer hint -->
                  <div v-if="Object.keys(groupedPermissions).length" class="d-flex align-center gap-2 mt-3 text-caption text-medium-emphasis">
                    <VIcon icon="tabler-info-circle" size="14" />
                    <span>Tip: Use search to quickly find permissions like "export" across all categories.</span>
                  </div>
                </div>
              </VCol>

            </VRow>
          </VForm>
        </VCardText>
        <VDivider />
        <VCardActions class="px-6 py-4">
          <VSpacer />
          <VBtn variant="tonal" color="secondary" @click="roleDialog = false">Cancel</VBtn>
          <VBtn color="primary" :loading="savingRole" :disabled="isProtected" @click="saveRole">
            {{ editingRole ? 'Save Changes' : 'Create Role' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Confirmation -->
    <VDialog v-model="deleteRoleDialog" max-width="420">
      <VCard title="Delete Role">
        <VCardText>
          Are you sure you want to delete <strong>{{ deletingRole?.name.replace('_', ' ') }}</strong>? This will remove
          its
          permission grants.
        </VCardText>
        <VDivider />
        <VCardActions>
          <VSpacer />
          <VBtn variant="tonal" @click="deleteRoleDialog = false">Cancel</VBtn>
          <VBtn color="error" @click="confirmDeleteRole">Delete</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
/* Vuexy Avatar Stack Styling */
.v-avatar-group {
  display: flex;
  align-items: center;
}

.v-avatar-group .v-avatar {
  border: 2px solid rgb(var(--v-theme-surface));
  margin-left: -0.8rem;
  transition: transform 0.15s ease;
}

.v-avatar-group .v-avatar:hover {
  transform: translateY(-4px);
  z-index: 10;
}

.v-avatar-group .v-avatar:first-child {
  margin-left: 0;
}

/* Permissions section */
.permissions-section {
  /* compact but comfortable spacing */
}

.permission-toolbar {
  border-color: rgba(var(--v-theme-on-surface), 0.10) !important;
  background: rgb(var(--v-theme-surface));
  border-radius: 12px !important;
}

.permission-toolbar :deep(.v-field) {
  border-radius: 10px;
}

.toolbar-select-all :deep(.v-label) {
  font-size: 0.8125rem;
  font-weight: 500;
  white-space: nowrap;
  opacity: 0.9;
}

/* Category cards grid */
.permissions-grid {
  display: grid;
  gap: 16px;
}

.permission-category-card {
  border-radius: 12px !important;
  border-color: rgba(var(--v-theme-on-surface), 0.10) !important;
  transition: box-shadow 0.2s ease, border-color 0.2s ease;
}

.permission-category-card:hover {
  border-color: rgba(var(--v-theme-primary), 0.18) !important;
  box-shadow: 0 2px 12px rgba(var(--v-theme-primary), 0.06);
}

.category-header {
  background: rgba(var(--v-theme-surface), 1);
  min-height: 64px;
  transition: background-color 0.15s ease;
  user-select: none;
}

.category-header:hover {
  background: rgba(var(--v-theme-primary), 0.03);
}

.category-header--collapsed {
  background: rgba(var(--v-theme-surface), 1);
}

.chevron-icon {
  transition: transform 0.2s ease, color 0.15s ease;
}

.category-icon-wrapper {
  width: 36px;
  height: 36px;
  background: rgba(var(--v-theme-primary), 0.10);
  border: 1px solid rgba(var(--v-theme-primary), 0.12);
}

.count-chip {
  height: 20px !important;
  font-size: 0.6875rem !important;
}

.category-select-checkbox :deep(.v-label) {
  font-size: 0.8125rem;
  font-weight: 600;
  white-space: nowrap;
}

.category-select-checkbox :deep(.v-selection-control__input) {
  flex-shrink: 0;
}

/* Permissions grid inner */
.category-body {
  background: rgba(var(--v-theme-background), 0.35);
}

.permissions-grid-inner {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 8px;
}

@media (max-width: 600px) {
  .permissions-grid-inner {
    grid-template-columns: 1fr;
  }
}

.permission-row {
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  transition: all 0.15s ease;
  min-height: 42px;
  user-select: none;
}

.permission-row:hover {
  background: rgba(var(--v-theme-primary), 0.04);
  border-color: rgba(var(--v-theme-primary), 0.16);
}

.permission-row--selected {
  background: rgba(var(--v-theme-primary), 0.06) !important;
  border-color: rgba(var(--v-theme-primary), 0.20) !important;
}

.permission-row--selected:hover {
  background: rgba(var(--v-theme-primary), 0.08) !important;
}

.permission-row--disabled {
  opacity: 0.6;
  cursor: not-allowed !important;
}

.permission-checkbox :deep(.v-selection-control__input) {
  flex-shrink: 0;
}

/* Empty state */
.empty-state-card {
  border-radius: 12px !important;
  border-style: dashed !important;
  border-color: rgba(var(--v-theme-on-surface), 0.14) !important;
  background: rgba(var(--v-theme-surface), 1);
}

.empty-icon-wrapper {
  width: 56px;
  height: 56px;
  background: rgba(var(--v-theme-on-surface), 0.06);
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

/* Responsive toolbar */
@media (max-width: 600px) {
  .category-header {
    flex-direction: column;
    align-items: flex-start !important;
  }

  .category-header .category-select-checkbox {
    align-self: flex-end;
  }
}
</style>
