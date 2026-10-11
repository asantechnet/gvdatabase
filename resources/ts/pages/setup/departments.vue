<script setup lang="ts">
import { translate } from '@/utils/localization'
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

const headers = computed(() => [
  { title: translate('common.fields.department'), key: 'name' },
  { title: translate('common.fields.code'), key: 'code' },
  { title: translate('pages.settings.users.roleOptions.manager'), key: 'manager_name' },
  { title: translate('common.ui.members'), key: 'employees_count', align: 'center' as const },
  { title: translate('serviceContracts.status'), key: 'is_active' },
  { title: translate('serviceContracts.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

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
    toast.error(apiErrorMessage(error, translate('common.ui.unableToLoadDepartments')))
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
    toast.error(translate('common.ui.departmentNameIsRequired'))

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
    toast.success(editing.value ? translate('common.ui.departmentUpdated') : translate('common.ui.departmentCreated'))
  }
  catch (error) {
    toast.error(apiErrorMessage(error, translate('common.ui.unableToSaveTheDepartment')))
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
    toast.success(translate('common.ui.departmentRemoved'))
  }
  catch (error) {
    toast.error(apiErrorMessage(error, translate('common.ui.aDepartmentWithMembersCannotBeRemoved')))
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
    :title="$t('common.navigation.departments')"
    :description="$t('common.ui.createStaffGroupsAndAssignASeniorEmployeeAsTheGroupManagerThenSetEachEmployeeSDepartmentAndDirectSupervisorInStaffDirectory')"
    icon="tabler-sitemap"
  />

  <VCard :loading="loading">
    <VCardTitle class="d-flex align-center justify-space-between flex-wrap ga-3">
      <span>{{ $t('common.navigation.departments') }}</span>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="create">{{ $t('common.ui.createDepartment') }}</VBtn>
    </VCardTitle>
    <ResponsiveDataTable :headers="headers" :items="departments" item-value="id">
      <template #item.code="{ item }">{{ item.code || '—' }}</template>
      <template #item.manager_name="{ item }">{{ item.manager_name || $t('common.ui.notAssigned') }}</template>
      <template #item.is_active="{ item }">
        <VChip size="small" :color="item.is_active ? 'success' : 'secondary'">{{ item.is_active ? $t('serviceContracts.active') : $t('common.statuses.inactive') }}</VChip>
      </template>
      <template #item.actions="{ item }">
        <IconBtn :aria-label="$t('common.ui.editDepartment')" @click="edit(item)"><VIcon icon="tabler-pencil" /></IconBtn>
        <IconBtn :aria-label="$t('common.ui.deleteDepartment')" @click="deleting = item; deleteDialogOpen = true"><VIcon icon="tabler-trash" /></IconBtn>
      </template>
    </ResponsiveDataTable>
  </VCard>

  <VDialog v-model="dialogOpen" max-width="620">
    <VCard>
      <VCardTitle>{{ editing ? $t('common.ui.editDepartment') : $t('common.ui.createDepartment') }}</VCardTitle>
      <VCardText>
        <VAlert type="info" variant="tonal" class="mb-4">
          {{ $t('common.ui.onlyAHigherRankedEmployeeWithSupervisoryAccessCanManageADepartmentAssignDirectSupervisorsFromStaffDirectoryAfterSaving') }}
        </VAlert>
        <VTextField v-model="form.name" :label="$t('common.ui.departmentName')" class="mb-3" />
        <VTextField v-model="form.code" :label="$t('common.ui.codeOptional')" class="mb-3" />
        <VSelect v-model="form.manager_employee_id" :items="employeeOptions" :label="$t('common.ui.departmentManager')" clearable class="mb-3" />
        <VTextarea v-model="form.description" :label="$t('common.ui.descriptionOptional')" rows="3" class="mb-3" />
        <VSwitch v-model="form.is_active" :label="$t('common.ui.activeDepartment')" color="primary" hide-details />
      </VCardText>
      <VCardActions><VSpacer /><VBtn variant="tonal" @click="dialogOpen = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="primary" :loading="loading" @click="save">{{ $t('common.actions.save') }}</VBtn></VCardActions>
    </VCard>
  </VDialog>

  <VDialog v-model="deleteDialogOpen" max-width="420">
    <VCard>
      <VCardTitle>{{ $t('common.ui.deleteDepartment') }}</VCardTitle>
      <VCardText>{{ $t('common.ui.onlyEmptyDepartmentsCanBeDeletedMoveItsEmployeesFirst') }}</VCardText>
      <VCardActions><VSpacer /><VBtn variant="tonal" @click="deleteDialogOpen = false; deleting = null">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="error" :loading="loading" @click="remove">{{ $t('common.actions.delete') }}</VBtn></VCardActions>
    </VCard>
  </VDialog>
</template>
