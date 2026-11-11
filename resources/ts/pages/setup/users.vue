<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { type ManagedUser, type UserApiError, type UserCreatePayload, type UserRole, type UserStatus, createUser, deleteUser, getUsers, updateUser } from '@/api/user'
import TableEmptyState from '@/components/TableEmptyState.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import UserForm from '@/components/forms/UserForm.vue'
import DataTableFilterMenu from '@/components/responsive-data-table/DataTableFilterMenu.vue'
import ResponsiveDataTable from '@/components/responsive-data-table/ResponsiveDataTable.vue'
import EmployeeAvatar from '@/components/hr/EmployeeAvatar.vue'
import { confirmActionWarning } from '@/composables/useActionWarning'
import { formatLocalizedDate } from '@/utils/localization'

const { t } = useI18n()
const toast = useToast()
const users = ref<ManagedUser[]>([])
const loading = ref(false)
const loadFailed = ref(false)
const saving = ref(false)
const search = ref('')
const roleFilter = ref<UserRole | null>(null)
const statusFilter = ref<UserStatus | null>(null)
const filterDraft = ref<{ role: UserRole | null; status: UserStatus | null }>({ role: null, status: null })
const filterOpen = ref(false)
const dialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const editingUser = ref<ManagedUser | null>(null)
const deletingUser = ref<ManagedUser | null>(null)
const passwordMode = ref(false)
const formErrors = ref<string[]>([])
const initialForm = ref('')
const minimumPasswordLength = 8
const emptyForm = (): UserCreatePayload => ({ name: '', email: '', phone: '', address: '', roles: ['staff'], acc_status: 'active', password: '', password_confirmation: '' })
const form = ref<UserCreatePayload>(emptyForm())
const roles: UserRole[] = ['super_admin', 'admin', 'manager', 'team_lead', 'senior_counselor', 'finance_officer', 'counselor', 'case_officer', 'staff', 'document_officer', 'viewer', 'applicant']
const statuses: UserStatus[] = ['active', 'inactive', 'deactivated']
const editableStatuses: UserStatus[] = ['active', 'inactive']
const roleOptions = computed(() => roles.map(value => ({ value, title: t(`pages.settings.users.roleOptions.${value}`) })))
const statusOptions = computed(() => statuses.map(value => ({ value, title: t(`common.statuses.${value}`) })))
const filterStatusOptions = computed(() => [{ value: null, title: t('pages.whatsapp.filters.all') }, ...statusOptions.value])
const editableStatusOptions = computed(() => editableStatuses.map(value => ({ value, title: t(`common.statuses.${value}`) })))
const headers = computed(() => [
  { title: t('pages.settings.users.user'), key: 'name', minWidth: '245px' },
  { title: t('common.messages.roles'), key: 'roles', sortable: false, minWidth: '190px' },
  { title: t('common.fields.status'), key: 'acc_status', minWidth: '125px' },
  { title: t('pages.settings.users.verification.label'), key: 'email_verified', minWidth: '135px' },
  { title: t('pages.settings.users.created'), key: 'created_at', minWidth: '130px' },
  { title: t('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const, minWidth: '92px' },
])
const filteredUsers = computed(() => users.value.filter(user => {
  const query = search.value.trim().toLocaleLowerCase()
  const matchesSearch = !query || [user.name, user.email, user.phone ?? ''].some(value => value.toLocaleLowerCase().includes(query))
  return matchesSearch && (!roleFilter.value || user.roles.includes(roleFilter.value)) && (!statusFilter.value || user.acc_status === statusFilter.value)
}))
const hasActiveFilters = computed(() => Boolean(roleFilter.value || statusFilter.value))
const isDirty = computed(() => JSON.stringify({ form: form.value, passwordMode: passwordMode.value }) !== initialForm.value)
const statusColor = (status: UserStatus) => ({ active: 'success', inactive: 'secondary', deactivated: 'warning' }[status])
const formatDate = (date: string | null) => date ? formatLocalizedDate(date, 'short') : t('common.messages.notAvailable')
const roleLabel = (role: UserRole) => t(`pages.settings.users.roleOptions.${role}`)
const verificationLabel = (user: ManagedUser) => user.password_setup_pending ? t('pages.settings.users.verification.invited') : user.email_verified ? t('pages.settings.users.verification.verified') : t('pages.settings.users.verification.pending')
const canDeleteUser = (user: ManagedUser) => !user.is_primary && !user.is_self
const deleteBlockedReason = (user: ManagedUser) => user.is_primary ? t('pages.settings.users.primaryAccountProtected') : t('pages.settings.users.selfDeleteBlocked')
const snapshot = () => JSON.stringify({ form: form.value, passwordMode: passwordMode.value })

async function loadUsers() {
  loading.value = true
  loadFailed.value = false
  try { users.value = await getUsers() }
  catch (error: any) { users.value = []; loadFailed.value = true; toast.error(error.message || t('pages.settings.users.couldNotLoad')) }
  finally { loading.value = false }
}
function syncFilterDraft() { filterDraft.value = { role: roleFilter.value, status: statusFilter.value } }
function applyFilters() { roleFilter.value = filterDraft.value.role; statusFilter.value = filterDraft.value.status; filterOpen.value = false }
function resetFilters() { roleFilter.value = null; statusFilter.value = null; filterDraft.value = { role: null, status: null }; filterOpen.value = false }
function openCreate() { editingUser.value = null; passwordMode.value = false; formErrors.value = []; form.value = emptyForm(); initialForm.value = snapshot(); dialogOpen.value = true }
function openEdit(user: ManagedUser) { editingUser.value = user; passwordMode.value = false; formErrors.value = []; form.value = { name: user.name, email: user.email, phone: user.phone, address: user.address, roles: [...user.roles], acc_status: user.acc_status }; initialForm.value = snapshot(); dialogOpen.value = true }
async function closeForm() {
  if (isDirty.value && !await confirmActionWarning({ key: 'discard-form-changes', title: t('common.actionWarnings.discardTitle'), message: t('common.actionWarnings.discardMessage'), confirmText: t('common.actionWarnings.discardConfirm'), cancelText: t('common.actions.cancel'), icon: 'tabler-file-alert', tone: 'primary' })) return
  dialogOpen.value = false
}
function localErrors() {
  const errors: string[] = []
  if (!form.value.name.trim()) errors.push(t('common.fields.name'))
  if (!form.value.email.trim()) errors.push(t('common.fields.email'))
  if (!form.value.roles.length) errors.push(t('pages.settings.users.roles'))
  if (!editingUser.value && passwordMode.value) {
    if (!form.value.password) errors.push(t('pages.settings.users.passwordRequired'))
    else if (form.value.password.length < minimumPasswordLength) errors.push(t('pages.settings.users.passwordTooShort', { count: minimumPasswordLength }))
    if (form.value.password !== form.value.password_confirmation) errors.push(t('pages.settings.users.passwordsDoNotMatch'))
  }
  return errors
}
async function save() {
  formErrors.value = localErrors()
  if (formErrors.value.length) return
  saving.value = true
  try {
    const manualPassword = !editingUser.value && passwordMode.value
    const payload: UserCreatePayload = { ...form.value }
    if (!manualPassword) { delete payload.password; delete payload.password_confirmation }
    const user = editingUser.value ? await updateUser(editingUser.value.id, payload) : await createUser(payload)
    const index = users.value.findIndex(item => item.id === user.id)
    if (index === -1) users.value.unshift(user)
    else users.value[index] = user
    dialogOpen.value = false
    toast.success(t(editingUser.value ? 'pages.settings.users.updated' : manualPassword ? 'pages.settings.users.userCreatedWithoutEmail' : 'pages.settings.users.invitationSent'))
  }
  catch (error) {
    const validation = (error as UserApiError).validationErrors
    formErrors.value = validation ? Object.values(validation).flat().filter(Boolean) : [(error as Error).message || t('common.messages.unableToSaveChanges')]
  }
  finally { saving.value = false }
}
function askDelete(user: ManagedUser) { if (canDeleteUser(user)) { deletingUser.value = user; deleteDialogOpen.value = true } }
async function confirmDelete() {
  if (!deletingUser.value) return
  saving.value = true
  try { await deleteUser(deletingUser.value.id); users.value = users.value.filter(user => user.id !== deletingUser.value?.id); deleteDialogOpen.value = false; deletingUser.value = null; toast.success(t('pages.settings.users.userDeleted')) }
  catch (error: any) { toast.error(error.message || t('pages.settings.users.deleteUser')) }
  finally { saving.value = false }
}

onMounted(loadUsers)
</script>

<template>
  <div class="users-workspace">
    <div class="users-workspace__heading"><VBreadcrumbs :items="[{ title: t('erp.home'), to: { name: 'home' } }, { title: t('common.navigation.settings'), disabled: true }]" density="compact" class="pa-0" /><h1 class="text-h5 font-weight-bold mb-0">{{ t('pages.settings.users.pageTitle') }}</h1></div>
    <VCard class="users-panel">
      <VCardText class="users-toolbar pa-4"><VRow align="center" class="ma-0"><VCol cols="12" md="6" lg="5" class="pa-0"><ErpSearchField v-model="search" :placeholder="t('pages.settings.users.searchUsers')" /></VCol><VCol cols="12" md="6" lg="7" class="d-flex flex-wrap justify-md-end ga-2 pa-0 pt-3 pt-md-0"><DataTableFilterMenu v-model="filterOpen" :active="hasActiveFilters" :show-reset="false" @update:model-value="open => { if (open) syncFilterDraft() }"><ErpField v-model="filterDraft.role" kind="select" :items="roleOptions" clearable :label="t('common.fields.role')" :placeholder="t('common.fields.role')" /><ErpField v-model="filterDraft.status" kind="select" :items="filterStatusOptions" clearable :label="t('common.fields.status')" :placeholder="t('common.fields.status')" /><template #actions><VBtn class="users-filter-reset" variant="flat" @click="resetFilters">{{ t('common.messages.reset') }}</VBtn><VBtn class="users-filter-apply" variant="flat" @click="applyFilters">{{ t('educational.apply') }}</VBtn></template></DataTableFilterMenu><VBtn class="users-add-button" prepend-icon="tabler-plus" @click="openCreate">{{ t('pages.settings.users.addUser') }}</VBtn></VCol></VRow></VCardText>
      <ResponsiveDataTable :headers="headers" :items="filteredUsers" :loading="loading" :skeleton-rows="6" item-value="id" mobile-cards mobile-expand-all mobile-title-key="name" mobile-action-key="actions" :sticky-key-column="false" :sticky-action-column="false" class="users-table">
        <template #item.name="{ item }"><div class="d-flex align-center ga-2"><EmployeeAvatar :name="item.name" :src="item.avatar" size="32" /><div><div class="d-flex align-center flex-wrap ga-2"><div class="font-weight-medium">{{ item.name }}</div><VChip v-if="item.is_primary" size="x-small" color="primary" variant="tonal">{{ t('pages.settings.users.primaryAccount') }}</VChip></div><div class="text-caption text-medium-emphasis">{{ item.email }}</div></div></div></template>
        <template #item.roles="{ item }"><div class="d-flex flex-wrap ga-1"><VChip v-for="role in item.roles" :key="role" size="small" variant="tonal">{{ roleLabel(role) }}</VChip></div></template>
        <template #item.acc_status="{ item }"><VChip size="small" :color="statusColor(item.acc_status)" variant="tonal">{{ t(`common.statuses.${item.acc_status}`) }}</VChip></template>
        <template #item.email_verified="{ item }"><VChip size="small" :color="item.email_verified && !item.password_setup_pending ? 'success' : 'warning'" variant="tonal">{{ verificationLabel(item) }}</VChip></template>
        <template #item.created_at="{ item }">{{ formatDate(item.created_at) }}</template>
        <template #item.actions="{ item }"><VMenu location="bottom end"><template #activator="{ props }"><VBtn v-bind="props" icon="tabler-dots-vertical" size="small" variant="tonal" :aria-label="t('common.messages.actions')" /></template><VList density="compact"><FullDetailsButton :item="item" :title="item.name" menu-action /><VListItem prepend-icon="tabler-pencil" :title="t('pages.settings.users.editUser')" @click="openEdit(item)" /><VListItem v-if="canDeleteUser(item)" class="users-table__delete" prepend-icon="tabler-trash" :title="t('pages.settings.users.deleteUser')" @click="askDelete(item)" /><VListItem v-else prepend-icon="tabler-lock" :title="deleteBlockedReason(item)" disabled /></VList></VMenu></template>
        <template #no-data><div class="users-empty-state"><TableEmptyState :min-height="0" :icon="loadFailed ? 'tabler-alert-circle' : 'tabler-users'" :title="loadFailed ? t('pages.settings.users.couldNotLoad') : t('$vuetify.noDataText')" :description="loadFailed ? t('pages.settings.users.couldNotLoad') : (search || hasActiveFilters ? t('$vuetify.dataIterator.noResultsText') : t('$vuetify.noDataText'))" /></div></template>
      </ResponsiveDataTable>
    </VCard>
  </div>

  <VDialog :model-value="dialogOpen" max-width="820" scrollable content-class="users-editor-overlay" @update:model-value="value => { if (!value) void closeForm() }"><VCard class="users-editor"><VCardTitle class="users-editor__header px-5 py-4"><span>{{ t(editingUser ? 'pages.settings.users.editUserDialog' : 'pages.settings.users.addUserDialog') }}</span><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="users-editor__close" :disabled="saving" :aria-label="t('common.actions.close')" @click="closeForm" /></VCardTitle><VCardText class="users-editor__body pa-5"><VAlert v-if="editingUser?.roles.includes('super_admin')" type="info" variant="tonal" class="mb-4">{{ t('pages.settings.users.lastSuperAdminRoleHint') }}</VAlert><VForm @submit.prevent="save"><UserForm v-model:password-mode="passwordMode" :form="form" :roles="roleOptions" :status-items="editingUser?.acc_status === 'deactivated' ? statusOptions : editableStatusOptions" :status-disabled="editingUser?.acc_status === 'deactivated'" :status-hint="editingUser?.acc_status === 'deactivated' ? t('pages.settings.users.deactivatedStatusHint') : undefined" :editing="Boolean(editingUser)" :errors="formErrors" /></VForm></VCardText><VCardActions class="users-editor__footer justify-end px-5 py-4"><VBtn class="users-editor__cancel" :disabled="saving" @click="closeForm">{{ t('common.actions.cancel') }}</VBtn><VBtn class="users-editor__save" :loading="saving" @click="save">{{ t(editingUser ? 'pages.settings.users.saveUser' : passwordMode ? 'pages.settings.users.createUser' : 'pages.settings.users.sendInvitation') }}</VBtn></VCardActions></VCard></VDialog>
  <VDialog :model-value="deleteDialogOpen" max-width="480" content-class="users-delete-overlay" @update:model-value="value => { if (!value) deleteDialogOpen = false }"><VCard class="users-delete-dialog"><VCardText class="users-delete-dialog__content"><div class="users-delete-dialog__icon"><VIcon icon="tabler-trash" size="30" /></div><h2>{{ t('common.actions.delete') }}</h2><p>{{ t('pages.settings.users.deleteConfirmation', { user: deletingUser?.name ?? '' }) }}</p></VCardText><VCardActions class="users-delete-dialog__actions"><VBtn class="users-editor__cancel" :disabled="saving" @click="deleteDialogOpen = false">{{ t('common.actions.cancel') }}</VBtn><VBtn class="users-delete-dialog__confirm" :loading="saving" @click="confirmDelete">{{ t('common.actions.delete') }}</VBtn></VCardActions></VCard></VDialog>
</template>

<style lang="scss">
.users-workspace { display: flex; flex-direction: column; gap: .75rem; }.users-workspace__heading { display: flex; flex-direction: column; gap: .15rem; }.users-workspace__heading h1 { line-height: 1.35; }.users-panel { overflow: hidden; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: .9rem; }.users-toolbar { border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }.users-toolbar .data-table-filter-menu__trigger, .users-add-button { min-block-size: 2.75rem; }.users-toolbar .data-table-filter-menu__trigger { min-inline-size: 2.75rem; }.users-add-button, .users-filter-apply, .users-editor__save { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }.users-filter-reset { border: 1px solid rgba(var(--v-theme-primary), .35) !important; background: rgba(var(--v-theme-primary), .13) !important; color: rgb(var(--v-theme-primary)) !important; }.users-filter-reset:hover { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }.users-table .v-table__wrapper > table { min-inline-size: 970px; }.users-table .v-table__wrapper > table > thead > tr > th { background: rgba(var(--v-theme-on-surface), .045); font-size: .82rem; font-weight: 700; letter-spacing: .025em; white-space: nowrap; }.users-table .v-table__wrapper > table > tbody > tr > td { white-space: nowrap; }.users-table .v-table__wrapper:has(.users-empty-state) { overflow-x: hidden; }.users-table .v-table__wrapper > table:has(.users-empty-state) { min-inline-size: 100%; }.users-table .v-table__wrapper > table:has(.users-empty-state) > tbody > tr > td { padding: 0 !important; }.users-empty-state { display: grid; min-block-size: 17rem; place-items: center; }.users-table__delete { color: rgb(var(--v-theme-error)) !important; }
.users-editor, .users-delete-dialog { overflow: hidden; background: rgb(var(--v-theme-surface)); }.users-editor__header, .users-editor__footer { display: flex; align-items: center; border-color: rgba(var(--v-border-color), .52); border-style: solid; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 93%, rgb(var(--v-theme-on-surface)) 7%); }.users-editor__header { border-width: 0 0 1px; color: rgb(var(--v-theme-on-surface)); font-size: 1.2rem; font-weight: 700; }.users-editor__footer { gap: .75rem; border-width: 1px 0 0; }.users-editor__body { background: rgba(var(--v-theme-on-surface), .018); }.users-editor__close { border: 1px solid rgba(var(--v-theme-error), .4) !important; border-radius: 999px !important; }.users-editor__cancel { border: 1px solid rgba(var(--v-theme-secondary), .5) !important; background: rgba(var(--v-theme-secondary), .13) !important; color: rgb(var(--v-theme-secondary)) !important; }.users-delete-dialog__content { display: flex; flex-direction: column; align-items: center; padding: 2rem 2rem 1.25rem !important; text-align: center; }.users-delete-dialog__content h2 { margin: 0; font-size: 1.2rem; }.users-delete-dialog__content p { max-inline-size: 23rem; margin: .65rem 0 0; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); }.users-delete-dialog__icon { display: grid; inline-size: 4.25rem; block-size: 4.25rem; margin-block-end: 1.25rem; border-radius: 50%; background: rgba(var(--v-theme-error), .14); color: rgb(var(--v-theme-error)); place-items: center; }.users-delete-dialog__actions { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; padding: 0 2rem 2rem !important; }.users-delete-dialog__actions .v-btn { min-block-size: 2.85rem; }.users-delete-dialog__confirm { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }.v-overlay:has(.users-editor-overlay) .v-overlay__scrim, .v-overlay:has(.users-delete-overlay) .v-overlay__scrim { background: rgba(20, 25, 35, .3) !important; backdrop-filter: blur(6px); opacity: 1 !important; }
@media (max-width: 599px) { .users-toolbar .v-row { gap: .75rem; }.users-toolbar .v-col { padding: 0 !important; }.users-toolbar .v-btn { flex: 1 1 auto; }.users-toolbar .data-table-filter-menu__trigger { flex: 0 0 2.75rem !important; block-size: 2.75rem; inline-size: 2.75rem; padding: 0 !important; }.users-delete-dialog__content { padding-inline: 1.25rem !important; }.users-delete-dialog__actions { padding-inline: 1.25rem !important; } }
</style>
