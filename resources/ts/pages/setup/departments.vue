<script setup lang="ts">
import { translate } from '@/utils/localization'
import { useToast } from 'vue-toastification'
import DepartmentApi, { type Department, type DepartmentDeleteRefusal, type DepartmentManagerOption, type DepartmentPayload } from '@/api/department'
import { clearFieldError, fieldErrorMessages } from '@/utils/formErrors'
import { canAccess } from '@/utils/authorization'
import DepartmentMembersDialog from '@/components/DepartmentMembersDialog.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'

const toast = useToast()
const departments = ref<Department[]>([])
const loading = ref(false)
const dialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const deleting = ref<Department | null>(null)
const editing = ref<Department | null>(null)
const managerOptions = ref<DepartmentManagerOption[]>([])
const managerLoading = ref(false)
const deleteRefusal = ref<DepartmentDeleteRefusal | null>(null)
const membersDialogOpen = ref(false)
const membersTarget = ref<Department | null>(null)
const membersTrigger = ref<HTMLElement | null>(null)

const canManageDepartments = computed(() => canAccess('manage_departments'))

const blank = (): DepartmentPayload => ({ name: '', code: null, description: null, manager_employee_id: null, is_active: true })
const form = ref<DepartmentPayload>(blank())
const managerError = computed(() => fieldErrorMessages('manager_id'))

const managerSelectOptions = computed(() => {
  const options: Array<{ title: string; value: number; props?: { disabled: boolean } }> = managerOptions.value.map(option => ({
    title: option.departmentName ? `${option.name} · ${option.departmentName}` : option.name,
    value: option.id,
  }))

  // Keep a saved manager visible even after they lose eligibility. Dropping
  // the value silently would make the form look like it has no manager.
  const currentId = editing.value?.manager_employee_id
  if (currentId && !options.some(option => option.value === currentId)) {
    options.unshift({
      title: `${editing.value?.manager_name ?? `#${currentId}`} · ${translate('common.ui.currentManagerNoLongerEligible')}`,
      value: currentId,
      props: { disabled: true },
    })
  }

  return options
})

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
    departments.value = await DepartmentApi.getAll(true, true)
  }
  catch {
    // ApiErrorNotifier owns API failure feedback; this page only reports success.
  }
  finally {
    loading.value = false
  }
}

async function openDialog(department: Department | null = null) {
  editing.value = department
  form.value = department
    ? {
        name: department.name,
        code: department.code,
        description: department.description,
        manager_employee_id: department.manager_employee_id,
        is_active: department.is_active,
      }
    : blank()
  clearFieldError('manager_id')
  dialogOpen.value = true
  managerOptions.value = []
  managerLoading.value = true
  try {
    managerOptions.value = await DepartmentApi.getManagerOptions(department?.id ?? null)
  }
  catch {
    managerOptions.value = []
  }
  finally {
    managerLoading.value = false
  }
}

function create() {
  void openDialog()
}

function edit(department: Department) {
  void openDialog(department)
}

function openMembers(department: Department, event?: MouseEvent) {
  if (!canManageDepartments.value)
    return

  membersTrigger.value = (event?.currentTarget as HTMLElement | null) ?? null
  membersTarget.value = department
  membersDialogOpen.value = true
}

function onMembersSaved(saved: Department) {
  const index = departments.value.findIndex(item => item.id === saved.id)
  if (index >= 0)
    departments.value[index] = saved

  membersTarget.value = saved
}

function restoreMembersFocus() {
  void nextTick(() => membersTrigger.value?.focus())
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
  catch {
    // Leave the dialog open: the global notifier shows one API error and the
    // shared form-error store renders manager_id inline.
  }
  finally {
    loading.value = false
  }
}

async function remove() {
  const target = deleting.value
  if (!target) return
  loading.value = true
  deleteRefusal.value = null
  try {
    await DepartmentApi.delete(target.id)
    departments.value = departments.value.filter(item => item.id !== target.id)
    deleting.value = null
    deleteDialogOpen.value = false
    toast.success(`${target.name}: ${translate('common.ui.departmentRemoved')}`)
  }
  catch (error) {
    // One toast is raised by ApiErrorNotifier. Keep the dialog open and show
    // the server's blocking count/names in place.
    deleteRefusal.value = (error ?? {}) as DepartmentDeleteRefusal
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
  :show-scroll-hint="false"
  />

  <VCard>
    <VCardTitle class="d-flex align-center justify-space-between flex-wrap ga-3">
      <span>{{ $t('common.navigation.departments') }}</span>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="create">{{ $t('common.ui.createDepartment') }}</VBtn>
    </VCardTitle>
    <TableLoadingSkeleton v-if="loading" :columns="headers.length" :label="$t('common.messages.loading')" />
    <TableEmptyState v-else-if="!departments.length" icon="tabler-sitemap" :description="$t('common.ui.noDepartments')" />
    <ResponsiveDataTable v-else :headers="headers" :items="departments" item-value="id" class="departments-table text-no-wrap">
      <template #item.code="{ item }">{{ item.code || '—' }}</template>
      <template #item.manager_name="{ item }">{{ item.manager_name || $t('common.ui.notAssigned') }}</template>
      <template #item.employees_count="{ item }">
        <VBtn
          v-if="canManageDepartments"
          size="small"
          variant="tonal"
          prepend-icon="tabler-users"
          :aria-label="$t('common.ui.departmentMembersTitle')"
          @click="openMembers(item, $event)"
        >
          {{ item.employees_count }}
        </VBtn>
        <span v-else>{{ item.employees_count }}</span>
      </template>
      <template #item.is_active="{ item }">
        <VChip size="small" :color="item.is_active ? 'success' : 'secondary'">{{ item.is_active ? $t('serviceContracts.active') : $t('common.statuses.inactive') }}</VChip>
      </template>
      <template #item.actions="{ item }">
        <IconBtn :aria-label="$t('common.ui.editDepartment')" @click="edit(item)"><VIcon icon="tabler-pencil" /></IconBtn>
        <IconBtn :aria-label="$t('common.ui.deleteDepartment')" @click="deleting = item; deleteRefusal = null; deleteDialogOpen = true"><VIcon icon="tabler-trash" /></IconBtn>
      </template>
    </ResponsiveDataTable>
  </VCard>

  <DepartmentMembersDialog
    v-model="membersDialogOpen"
    :department="membersTarget"
    @saved="onMembersSaved"
    @closed="restoreMembersFocus"
  />

  <VDialog v-model="dialogOpen" max-width="620">
    <VCard>
      <VCardTitle>{{ editing ? $t('common.ui.editDepartment') : $t('common.ui.createDepartment') }}</VCardTitle>
      <VCardText>
        <VAlert type="info" variant="tonal" class="mb-4">
          {{ $t('common.ui.onlyAHigherRankedEmployeeWithSupervisoryAccessCanManageADepartmentAssignDirectSupervisorsFromStaffDirectoryAfterSaving') }}
        </VAlert>
        <VTextField v-model="form.name" :label="$t('common.ui.departmentName')" class="mb-3" />
        <VTextField v-model="form.code" :label="$t('common.ui.codeOptional')" class="mb-3" />
        <VSelect
          v-model="form.manager_employee_id"
          :items="managerSelectOptions"
          :label="$t('common.ui.departmentManager')"
          :loading="managerLoading"
          :error-messages="managerError"
          :no-data-text="$t('common.ui.noEligibleDepartmentManagers')"
          clearable
          class="mb-3"
          @update:model-value="clearFieldError('manager_id')"
        />
        <VTextarea v-model="form.description" :label="$t('common.ui.descriptionOptional')" rows="3" class="mb-3" />
        <VSwitch v-model="form.is_active" :label="$t('common.ui.activeDepartment')" color="primary" hide-details />
      </VCardText>
      <VCardActions><VSpacer /><VBtn variant="tonal" @click="dialogOpen = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="primary" :loading="loading" @click="save">{{ $t('common.actions.save') }}</VBtn></VCardActions>
    </VCard>
  </VDialog>

  <VDialog v-model="deleteDialogOpen" max-width="480">
    <VCard>
      <VCardTitle>{{ $t('common.ui.deleteDepartment') }}</VCardTitle>
      <VCardText>
        <div>{{ $t('common.ui.onlyEmptyDepartmentsCanBeDeletedMoveItsEmployeesFirst') }}</div>
        <VAlert v-if="deleteRefusal" type="error" variant="tonal" class="mt-4">
          <div>{{ deleteRefusal.message || $t('common.ui.aDepartmentWithMembersCannotBeRemoved') }}</div>
          <div v-if="deleteRefusal.blocking_employee_count" class="mt-2">
            {{ $t('common.ui.members') }} ({{ deleteRefusal.blocking_employee_count }}):
            {{ (deleteRefusal.blocking_employees || []).join(', ') }}
          </div>
        </VAlert>
      </VCardText>
      <VCardActions><VSpacer /><VBtn variant="tonal" @click="deleteDialogOpen = false; deleting = null; deleteRefusal = null">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="error" :loading="loading" @click="remove">{{ $t('common.actions.delete') }}</VBtn></VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.departments-table :deep(.v-data-table__wrapper) {
  overflow-x: auto;
  scrollbar-width: none;
}

.departments-table :deep(.v-data-table__wrapper::-webkit-scrollbar) {
  display: none;
}

.departments-table :deep(.v-data-table__wrapper > table) {
  min-inline-size: 900px;
}
</style>
