<script setup lang="ts">
import { useToast } from 'vue-toastification'
import DepartmentApi, { type Department, type DepartmentPayload } from '@/api/department'
import EmployeeApi, { type Employee } from '@/api/employee'
import { apiErrorMessage } from '@/utils/apiErrors'

const toast = useToast()
const departments = ref<Department[]>([])
const employees = ref<Employee[]>([])
const loading = ref(false)
const dialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const deleting = ref<Department | null>(null)
const editing = ref<Department | null>(null)

const blank = (): DepartmentPayload => ({ name: '', code: null, description: null, manager_employee_id: null, is_active: true })
const form = ref<DepartmentPayload>(blank())
const employeeOptions = computed(() => employees.value.map(employee => ({ title: employee.name, value: employee.id })))

const headers = [
  { title: 'Department', key: 'name' },
  { title: 'Code', key: 'code' },
  { title: 'Manager', key: 'manager_name' },
  { title: 'Members', key: 'employees_count', align: 'center' as const },
  { title: 'Status', key: 'is_active' },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' as const },
]

async function load() {
  loading.value = true
  try {
    const [departmentRows, employeeRows] = await Promise.all([
      DepartmentApi.getAll(true),
      EmployeeApi.getOptions(),
    ])
    departments.value = departmentRows
    employees.value = employeeRows
  }
  catch (error) {
    toast.error(apiErrorMessage(error, 'Unable to load departments.'))
  }
  finally {
    loading.value = false
  }
}

function create() {
  editing.value = null
  form.value = blank()
  dialogOpen.value = true
}

function edit(department: Department) {
  editing.value = department
  form.value = {
    name: department.name,
    code: department.code,
    description: department.description,
    manager_employee_id: department.manager_employee_id,
    is_active: department.is_active,
  }
  dialogOpen.value = true
}

async function save() {
  if (!form.value.name.trim()) {
    toast.error('Department name is required.')

    return
  }
  loading.value = true
  try {
    const saved = editing.value
      ? await DepartmentApi.update(editing.value.id, form.value)
      : await DepartmentApi.create(form.value)
    const index = departments.value.findIndex(item => item.id === saved.id)
    if (index >= 0) departments.value[index] = saved
    else departments.value.unshift(saved)
    dialogOpen.value = false
    toast.success(editing.value ? 'Department updated.' : 'Department created.')
  }
  catch (error) {
    toast.error(apiErrorMessage(error, 'Unable to save the department.'))
  }
  finally {
    loading.value = false
  }
}

async function remove() {
  if (!deleting.value) return
  loading.value = true
  try {
    await DepartmentApi.delete(deleting.value.id)
    departments.value = departments.value.filter(item => item.id !== deleting.value?.id)
    deleting.value = null
    deleteDialogOpen.value = false
    toast.success('Department removed.')
  }
  catch (error) {
    toast.error(apiErrorMessage(error, 'A department with members cannot be removed.'))
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <DataTablePageKpis
    :items="departments"
    title="Departments"
    description="Create staff groups and assign a senior employee as the group manager. Then set each employee’s department and direct supervisor in Staff Directory."
    icon="tabler-sitemap"
  />

  <VCard :loading="loading">
    <VCardTitle class="d-flex align-center justify-space-between flex-wrap ga-3">
      <span>Departments</span>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="create">Create department</VBtn>
    </VCardTitle>
    <ResponsiveDataTable :headers="headers" :items="departments" item-value="id">
      <template #item.code="{ item }">{{ item.code || '—' }}</template>
      <template #item.manager_name="{ item }">{{ item.manager_name || 'Not assigned' }}</template>
      <template #item.is_active="{ item }">
        <VChip size="small" :color="item.is_active ? 'success' : 'secondary'">{{ item.is_active ? 'Active' : 'Inactive' }}</VChip>
      </template>
      <template #item.actions="{ item }">
        <IconBtn aria-label="Edit department" @click="edit(item)"><VIcon icon="tabler-pencil" /></IconBtn>
        <IconBtn aria-label="Delete department" @click="deleting = item; deleteDialogOpen = true"><VIcon icon="tabler-trash" /></IconBtn>
      </template>
    </ResponsiveDataTable>
  </VCard>

  <VDialog v-model="dialogOpen" max-width="620">
    <VCard>
      <VCardTitle>{{ editing ? 'Edit department' : 'Create department' }}</VCardTitle>
      <VCardText>
        <VAlert type="info" variant="tonal" class="mb-4">
          Only a higher-ranked employee with supervisory access can manage a department. Assign direct supervisors from Staff Directory after saving.
        </VAlert>
        <VTextField v-model="form.name" label="Department name" class="mb-3" />
        <VTextField v-model="form.code" label="Code (optional)" class="mb-3" />
        <VSelect v-model="form.manager_employee_id" :items="employeeOptions" label="Department manager" clearable class="mb-3" />
        <VTextarea v-model="form.description" label="Description (optional)" rows="3" class="mb-3" />
        <VSwitch v-model="form.is_active" label="Active department" color="primary" hide-details />
      </VCardText>
      <VCardActions><VSpacer /><VBtn variant="tonal" @click="dialogOpen = false">Cancel</VBtn><VBtn color="primary" :loading="loading" @click="save">Save</VBtn></VCardActions>
    </VCard>
  </VDialog>

  <VDialog v-model="deleteDialogOpen" max-width="420">
    <VCard>
      <VCardTitle>Delete department?</VCardTitle>
      <VCardText>Only empty departments can be deleted. Move its employees first.</VCardText>
      <VCardActions><VSpacer /><VBtn variant="tonal" @click="deleteDialogOpen = false; deleting = null">Cancel</VBtn><VBtn color="error" :loading="loading" @click="remove">Delete</VBtn></VCardActions>
    </VCard>
  </VDialog>
</template>
