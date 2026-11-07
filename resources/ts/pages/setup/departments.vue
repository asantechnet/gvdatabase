<script setup lang="ts">
import { translate } from '@/utils/localization'
import { useToast } from 'vue-toastification'
import DepartmentApi, { type Department, type DepartmentDeleteRefusal, type DepartmentManagerOption, type DepartmentPayload } from '@/api/department'
import { clearFieldError, fieldErrorMessages } from '@/utils/formErrors'
import { canAccess } from '@/utils/authorization'
import DepartmentMembersDialog from '@/components/DepartmentMembersDialog.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'

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
const search = ref('')

const canManageDepartments = computed(() => canAccess('manage_departments'))

const blank = (): DepartmentPayload => ({ name: '', description: null, manager_employee_id: null, is_active: true })
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
  { title: translate('pages.settings.users.roleOptions.manager'), key: 'manager_name' },
  { title: translate('common.ui.members'), key: 'employees_count', align: 'center' as const },
  { title: translate('serviceContracts.status'), key: 'is_active' },
  { title: translate('serviceContracts.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

const filteredDepartments = computed(() => {
  const term = search.value.trim().toLocaleLowerCase()
  if (!term)
    return departments.value

  return departments.value.filter(department => [department.name, department.manager_name, department.description]
    .filter(Boolean)
    .some(value => String(value).toLocaleLowerCase().includes(term)))
})

const debouncedSearch = useDebounceFn(() => {}, 200)

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
  <div class="erp-workspace departments-workspace">
  <ErpPageHeader :title="$t('common.navigation.departments')" section="hr" />
  <VCard>
    <VCardText>
      <VRow align="center" class="table-toolbar">
        <VCol cols="12" md="6" lg="5"><ErpSearchField v-model="search" :placeholder="$t('common.navigation.departments')" @input="debouncedSearch" /></VCol>
        <VCol cols="12" md="6" lg="7" class="table-toolbar-actions d-flex justify-end"><VBtn color="primary" prepend-icon="tabler-plus" :disabled="loading" @click="create">{{ $t('common.ui.createDepartment') }}</VBtn></VCol>
      </VRow>
    </VCardText>
    <ResponsiveDataTable :headers="headers" :items="filteredDepartments" item-value="id" class="departments-table" mobile-cards mobile-expand-all mobile-actions-as-menu mobile-title-key="name" mobile-action-key="actions" :mobile-fields="['manager_name', 'employees_count', 'is_active']" :skeleton-rows="5" :show-scroll-affordance="false" :loading="loading">
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
        <VMenu location="bottom end">
          <template #activator="{ props }"><VBtn v-bind="props" icon="tabler-dots-vertical" variant="tonal" size="small" :aria-label="$t('serviceContracts.actions')" /></template>
          <VList density="compact" min-width="200">
            <VListItem prepend-icon="tabler-pencil" :title="$t('common.ui.editDepartment')" @click="edit(item)" />
            <VListItem prepend-icon="tabler-trash" class="departments-delete-action" :title="$t('common.ui.deleteDepartment')" @click="deleting = item; deleteRefusal = null; deleteDialogOpen = true" />
          </VList>
        </VMenu>
      </template>
      <template #no-data>
        <div class="departments-empty-state"><TableEmptyState icon="tabler-sitemap" :title="$t('common.navigation.departments')" :description="$t('common.ui.noDepartments')" :min-height="0" /><VBtn color="primary" variant="tonal" @click="create"><VIcon icon="tabler-plus" class="me-1" />{{ $t('common.ui.createDepartment') }}</VBtn></div>
      </template>
    </ResponsiveDataTable>
  </VCard>

  <DepartmentMembersDialog
    v-model="membersDialogOpen"
    :department="membersTarget"
    @saved="onMembersSaved"
    @closed="restoreMembersFocus"
  />

  <VDialog v-model="dialogOpen" max-width="620" content-class="department-form-dialog" scroll-strategy="block">
    <VCard class="department-form-card">
      <VCardTitle class="department-dialog-title px-5 py-4"><span class="text-h5 department-dialog-heading">{{ editing ? $t('common.ui.editDepartment') : $t('common.ui.createDepartment') }}</span><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="department-dialog-close" :aria-label="$t('common.actions.close')" @click="dialogOpen = false" /></VCardTitle>
      <VCardText class="department-dialog-body pa-5 pa-sm-6">
        <VAlert type="info" variant="tonal" class="mb-4">
          {{ $t('common.ui.onlyAHigherRankedEmployeeWithSupervisoryAccessCanManageADepartmentAssignDirectSupervisorsFromStaffDirectoryAfterSaving') }}
        </VAlert>
        <ErpField v-model="form.name" :label="$t('common.ui.departmentName')" placeholder="e.g. Admissions" class="mb-4" required />
        <ErpField
          v-model="form.manager_employee_id"
          kind="select"
          :items="managerSelectOptions"
          :label="$t('common.ui.departmentManager')"
          placeholder="Select a manager"
          :loading="managerLoading"
          :error-messages="managerError"
          :no-data-text="$t('common.ui.noEligibleDepartmentManagers')"
          clearable
          class="mb-3"
          @update:model-value="clearFieldError('manager_id')"
        />
        <ErpField v-model="form.description" kind="textarea" :label="$t('common.ui.descriptionOptional')" placeholder="What does this team own?" rows="3" class="mb-3" />
        <VSwitch v-model="form.is_active" :label="$t('common.ui.activeDepartment')" color="primary" hide-details />
      </VCardText>
      <VCardActions class="department-dialog-actions px-5 py-4"><VBtn class="department-dialog-cancel" variant="flat" color="error" :disabled="loading" @click="dialogOpen = false">{{ $t('common.actions.cancel') }}</VBtn><VSpacer /><VBtn class="department-dialog-submit" color="primary" variant="flat" :loading="loading" @click="save">{{ $t('common.actions.save') }}</VBtn></VCardActions>
    </VCard>
  </VDialog>

  <VDialog v-model="deleteDialogOpen" max-width="480" class="department-delete-overlay">
    <VCard class="department-delete-dialog">
      <VCardText class="department-delete-dialog__content">
        <div class="department-delete-dialog__icon"><VIcon icon="tabler-trash" size="30" /></div>
        <h2 class="department-delete-dialog__title">{{ $t('common.ui.deleteDepartment') }}</h2>
        <div>{{ $t('common.ui.onlyEmptyDepartmentsCanBeDeletedMoveItsEmployeesFirst') }}</div>
        <VAlert v-if="deleteRefusal" type="error" variant="tonal" class="mt-4">
          <div>{{ deleteRefusal.message || $t('common.ui.aDepartmentWithMembersCannotBeRemoved') }}</div>
          <div v-if="deleteRefusal.blocking_employee_count" class="mt-2">
            {{ $t('common.ui.members') }} ({{ deleteRefusal.blocking_employee_count }}):
            {{ (deleteRefusal.blocking_employees || []).join(', ') }}
          </div>
        </VAlert>
      </VCardText>
      <VCardActions class="department-delete-dialog__actions"><VBtn class="department-delete-dialog__button department-delete-dialog__button--cancel" :disabled="loading" @click="deleteDialogOpen = false; deleting = null; deleteRefusal = null">{{ $t('common.actions.cancel') }}</VBtn><VBtn class="department-delete-dialog__button department-delete-dialog__button--delete" :loading="loading" @click="remove">{{ $t('common.actions.delete') }}</VBtn></VCardActions>
    </VCard>
  </VDialog>
  </div>
</template>

<style scoped lang="scss">
.departments-empty-state { display: grid; justify-items: center; padding-block: 1.25rem 2rem; }
.departments-delete-action { color: rgb(var(--v-theme-error)) !important; }

:global(.department-form-dialog .v-overlay__scrim),
:global(.v-overlay:has(.department-form-dialog) .v-overlay__scrim),
:global(.department-delete-overlay .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .28) !important; backdrop-filter: blur(6px); opacity: 1 !important; }
.department-form-card, .department-delete-dialog { overflow: hidden; }
.department-dialog-title, .department-dialog-actions { display: flex; align-items: center; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%); }
.department-dialog-title { border-block-end: 1px solid rgba(var(--v-theme-on-surface), .06); }
.department-dialog-actions { border-block-start: 1px solid rgba(var(--v-theme-on-surface), .06); }
.department-dialog-heading { font-weight: 700; }
.department-dialog-close { inline-size: 36px; block-size: 36px; border: 1px solid rgba(var(--v-theme-error), .38); border-radius: 50%; }
.department-dialog-cancel { border: 1px solid rgba(var(--v-theme-error), .62) !important; background: rgba(var(--v-theme-error), .12) !important; color: rgb(var(--v-theme-error)) !important; }
.department-dialog-submit { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
.department-delete-dialog { border-radius: 16px !important; text-align: center; }
.department-delete-dialog__content { display: flex; flex-direction: column; align-items: center; padding: 2rem 2rem 1.25rem !important; }
.department-delete-dialog__icon { display: grid; inline-size: 4.25rem; block-size: 4.25rem; margin-block-end: 1.25rem; border-radius: 50%; place-items: center; background: rgba(var(--v-theme-error), .14); color: rgb(var(--v-theme-error)); }
.department-delete-dialog__title { margin: 0 0 .75rem; font-size: 1.2rem; }
.department-delete-dialog__actions { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; padding: 0 2rem 2rem !important; }
.department-delete-dialog__button { min-block-size: 2.85rem; border-radius: 10px !important; font-weight: 700; }
.department-delete-dialog__button--cancel { border: 1px solid rgba(var(--v-theme-secondary), .52) !important; color: rgb(var(--v-theme-secondary)) !important; }
.department-delete-dialog__button--delete { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }
</style>
