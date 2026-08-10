<script setup lang="ts">
import PositionApi from '@/api/position'
import PermissionApi from '@/api/permission'

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
  employee_count?: number
}

const positions = ref<Position[]>([])
const permissions = ref<Permission[]>([])
const isLoading = ref(false)
const errorMessage = ref('')
const isDialogVisible = ref(false)
const selectedPosition = ref<Position | null>(null)

const fetchRoles = async () => {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const [positionsResponse, permissionsResponse] = await Promise.all([
      PositionApi.getAll(),
      PermissionApi.getAll(),
    ])

    positions.value = positionsResponse.data.data
    permissions.value = permissionsResponse.data.data
  } catch (error: any) {
    errorMessage.value = error.message || 'Unable to load roles.'
  } finally {
    isLoading.value = false
  }
}

const openCreate = () => {
  selectedPosition.value = null
  isDialogVisible.value = true
}

const openEdit = (position: Position) => {
  selectedPosition.value = position
  isDialogVisible.value = true
}

const deletePosition = async (position: Position) => {
  if (!confirm(`Delete the ${position.title} role?`)) return

  try {
    await PositionApi.delete(position.id)
    await fetchRoles()
  } catch (error: any) {
    errorMessage.value = error.message || 'Unable to delete the role.'
  }
}

const formatSalary = (salary: number) => new Intl.NumberFormat().format(salary || 0)

await fetchRoles()
</script>

<template>
  <VAlert
    v-if="errorMessage"
    type="error"
    class="mb-4"
    closable
    @click:close="errorMessage = ''"
  >
    {{ errorMessage }}
  </VAlert>

  <VProgressLinear v-if="isLoading" indeterminate class="mb-4" />

  <VRow>
    <VCol
      v-for="position in positions"
      :key="position.id"
      cols="12"
      sm="6"
      lg="4"
    >
      <VCard class="h-100">
        <VCardText>
          <div class="d-flex justify-space-between align-start gap-3">
            <div>
              <h5 class="text-h5">{{ position.title }}</h5>
              <div class="text-body-2 text-medium-emphasis mt-1">
                {{ position.description || 'No description provided.' }}
              </div>
            </div>
            <VChip size="small" :color="position.status === 'active' ? 'success' : 'secondary'" label>
              {{ position.status }}
            </VChip>
          </div>

          <div class="d-flex justify-space-between text-body-2 mt-5">
            <span>{{ position.permissions?.length || 0 }} permissions</span>
            <span>Salary: {{ formatSalary(position.salary) }}</span>
          </div>

          <div class="d-flex flex-wrap gap-2 mt-4">
            <VChip
              v-for="permission in position.permissions"
              :key="permission.id"
              size="small"
              variant="tonal"
            >
              {{ permission.name }}
            </VChip>
            <span v-if="!position.permissions?.length" class="text-body-2 text-medium-emphasis">
              No permissions assigned.
            </span>
          </div>
        </VCardText>

        <VCardActions>
          <VBtn variant="text" @click="openEdit(position)">Edit</VBtn>
          <VBtn color="error" variant="text" @click="deletePosition(position)">Delete</VBtn>
        </VCardActions>
      </VCard>
    </VCol>

    <VCol cols="12" sm="6" lg="4">
      <VCard class="h-100 d-flex align-center justify-center" :ripple="false">
        <VCardText class="text-center">
          <VIcon icon="tabler-shield-plus" size="42" class="mb-3" />
          <h5 class="text-h5 mb-2">Add New Role</h5>
          <p class="text-body-2 text-medium-emphasis mb-4">Create a position and assign its permissions.</p>
          <VBtn @click="openCreate">Add New Role</VBtn>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>

  <AddEditRoleDialog
    v-model:is-dialog-visible="isDialogVisible"
    :position="selectedPosition"
    :permissions="permissions"
    @saved="fetchRoles"
  />
</template>
