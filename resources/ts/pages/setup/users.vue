<script setup lang="ts">
import { createUser, deleteUser, getUsers, updateUser, type ManagedUser, type UserPayload, type UserRole, type UserStatus } from '@/api/user'
import { useToast } from 'vue-toastification'
import UserForm from '@/components/forms/UserForm.vue'

const toast = useToast()
const users = ref<ManagedUser[]>([])
const loading = ref(false)
const search = ref('')
const roleFilter = ref<UserRole | null>(null)
const statusFilter = ref<UserStatus | null>(null)
const dialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const editingUser = ref<ManagedUser | null>(null)
const deletingUser = ref<ManagedUser | null>(null)
const emptyForm = (): UserPayload => ({ name: '', email: '', phone: '', address: '', roles: ['staff'], acc_status: 'active' })
const form = ref<UserPayload>(emptyForm())

const roles: UserRole[] = ['super_admin', 'admin', 'manager', 'staff', 'viewer']
const statuses: UserStatus[] = ['active', 'inactive', 'suspended', 'deactivated']
const editableStatuses: UserStatus[] = ['active', 'inactive', 'suspended']
const headers = [
  { title: translate('pages.settings.users.user'), key: 'name' },
  { title: translate('common.messages.roles'), key: 'roles', sortable: false },
  { title: translate('common.fields.status'), key: 'acc_status' },
  { title: translate('common.fields.email'), key: 'email_verified' },
  { title: translate('pages.settings.users.created'), key: 'created_at' },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
]

const filteredUsers = computed(() => users.value.filter(user => {
  const matchesSearch = `${user.name} ${user.email}`.toLowerCase().includes(search.value.toLowerCase())
  return matchesSearch && (!roleFilter.value || user.roles.includes(roleFilter.value)) && (!statusFilter.value || user.acc_status === statusFilter.value)
}))
const statusColor = (status: UserStatus) => ({ active: 'success', inactive: 'secondary', suspended: 'error', deactivated: 'warning' }[status])
const formatDate = (date: string | null) => date ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(date)) : '—'

async function loadUsers() {
  loading.value = true
  try { users.value = await getUsers() }
  catch (error: any) { toast.error(error.message || 'Could not load users.') }
  finally { loading.value = false }
}

function openCreate() {
  editingUser.value = null
  form.value = emptyForm()
  dialogOpen.value = true
}

function openEdit(user: ManagedUser) {
  editingUser.value = user
  form.value = { name: user.name, email: user.email, phone: user.phone, address: user.address, roles: [...user.roles], acc_status: user.acc_status }
  dialogOpen.value = true
}

async function save() {
  if (!form.value.name || !form.value.email || !form.value.roles.length) return toast.error(translate('pages.settings.users.nameEmailAndAtLeastOneRoleAreRequired'))
  loading.value = true
  try {
    const user = editingUser.value ? await updateUser(editingUser.value.id, form.value) : await createUser(form.value)
    const index = users.value.findIndex(item => item.id === user.id)
    if (index === -1) users.value.unshift(user)
    else users.value[index] = user
    dialogOpen.value = false
    toast.success(translate(editingUser.value ? 'pages.settings.users.updated' : 'pages.settings.users.invitationSent'))
  } catch (error: any) {
    toast.error(error.validationErrors?.email?.[0] || error.message)
  } finally { loading.value = false }
}

function askDelete(user: ManagedUser) { deletingUser.value = user; deleteDialogOpen.value = true }
async function confirmDelete() {
  if (!deletingUser.value) return
  try {
    await deleteUser(deletingUser.value.id)
    users.value = users.value.filter(user => user.id !== deletingUser.value?.id)
    toast.success(translate('pages.settings.users.userDeleted'))
    deleteDialogOpen.value = false
  } catch (error: any) { toast.error(error.message) }
}

onMounted(loadUsers)
</script>

<template>
  <DataTablePageKpis :items="filteredUsers" title="Users" description="Manage access, invitations, and the current health of user accounts." icon="tabler-user-cog" status-key="acc_status" />
  <VCard :title="$t('common.messages.users')" :loading="loading">
    <VCardText>
      <VRow align="center">
        <VCol cols="12" md="8"><VTextField v-model="search" class="data-table-search" prepend-inner-icon="tabler-search" :label="$t('pages.settings.users.searchUsers')" density="compact" hide-details clearable /></VCol>
        <VCol cols="12" md="4" class="d-flex justify-end ga-2"><DataTableFilterMenu :active="Boolean(roleFilter || statusFilter)" @reset="roleFilter = null; statusFilter = null"><VSelect v-model="roleFilter" :items="roles" :label="$t('common.fields.role')" density="compact" hide-details clearable /><VSelect v-model="statusFilter" :items="statuses" :label="$t('common.fields.status')" density="compact" hide-details clearable /></DataTableFilterMenu><VBtn color="primary" prepend-icon="tabler-plus" @click="openCreate">{{ $t('pages.settings.users.inviteUser') }}</VBtn></VCol>
      </VRow>
    </VCardText>
    <ResponsiveDataTable :headers="headers" :items="filteredUsers" :loading="loading" item-value="id" class="text-no-wrap">
      <template #item.name="{ item }"><div><div class="font-weight-medium">{{ item.name }}</div><div class="text-caption text-medium-emphasis">{{ item.email }}</div></div></template>
      <template #item.roles="{ item }"><div class="d-flex flex-wrap ga-1"><VChip v-for="role in item.roles" :key="role" size="small" label>{{ role.replace('_', ' ') }}</VChip></div></template>
      <template #item.acc_status="{ item }"><VChip size="small" label :color="statusColor(item.acc_status)">{{ item.acc_status }}</VChip></template>
      <template #item.email_verified="{ item }"><VChip size="small" label :color="item.email_verified && !item.password_setup_pending ? 'success' : 'warning'">{{ item.password_setup_pending ? 'Invited' : item.email_verified ? 'Verified' : 'Pending verification' }}</VChip></template>
      <template #item.created_at="{ item }">{{ formatDate(item.created_at) }}</template>
      <template #item.actions="{ item }"><FullDetailsButton :item="item" :title="item.name" /><IconBtn :aria-label="$t('pages.settings.users.editUser')" @click="openEdit(item)"><VIcon icon="tabler-pencil" /></IconBtn><IconBtn :aria-label="$t('pages.settings.users.deleteUser')" @click="askDelete(item)"><VIcon icon="tabler-trash" /></IconBtn></template>
    </ResponsiveDataTable>
  </VCard>

  <VDialog v-model="dialogOpen" max-width="700">
    <VCard :title="editingUser ? 'Edit User' : 'Invite User'">
      <VCardText>
        <VAlert v-if="!editingUser" type="info" variant="tonal" class="mb-5">{{ $t('pages.settings.users.theUserWillReceiveAVerificationEmailThenCreateTheirOwnPassword') }}</VAlert>
        <UserForm :form="form" :roles="roles" :status-items="editingUser?.acc_status === 'deactivated' ? statuses : editableStatuses" :status-disabled="editingUser?.acc_status === 'deactivated'" :status-hint="editingUser?.acc_status === 'deactivated' ? 'Use the audited reactivation action in Deactivation Requests.' : undefined" :editing="Boolean(editingUser)" :loading="loading" @submit="save" @cancel="dialogOpen = false" />
      </VCardText>
    </VCard>
  </VDialog>

  <VDialog v-model="deleteDialogOpen" max-width="420"><VCard :title="$t('pages.settings.users.deleteUser2')"><VCardText>{{ $t('pages.settings.users.deleteConfirmation', { user: deletingUser?.name }) }}</VCardText><VCardActions><VSpacer /><VBtn variant="tonal" @click="deleteDialogOpen = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="error" @click="confirmDelete">{{ $t('common.actions.delete') }}</VBtn></VCardActions></VCard></VDialog>
</template>
