<script setup lang="ts">
import { useToast } from 'vue-toastification'
import { type ManagedUser, type UserCreatePayload, type UserPayload, type UserRole, type UserStatus, createUser, deleteUser, getUsers, updateUser } from '@/api/user'
import UserForm from '@/components/forms/UserForm.vue'
import { formatLocalizedDate } from '@/utils/localization'

const toast = useToast()
const { t } = useI18n()
const users = ref<ManagedUser[]>([])
const loading = ref(false)
const search = ref('')
const roleFilter = ref<UserRole | null>(null)
const statusFilter = ref<UserStatus | null>(null)
const dialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const editingUser = ref<ManagedUser | null>(null)
const deletingUser = ref<ManagedUser | null>(null)
// A manual password creates a ready-to-use account with no email at all;
// otherwise the user is invited and picks their own password.
const passwordMode = ref(false)
const minimumPasswordLength = 8
const emptyForm = (): UserCreatePayload => ({ name: '', email: '', phone: '', address: '', roles: ['staff'], acc_status: 'active', password: '', password_confirmation: '' })
const form = ref<UserCreatePayload>(emptyForm())

const roles: UserRole[] = ['super_admin', 'admin', 'manager', 'team_lead', 'senior_counselor', 'finance_officer', 'counselor', 'case_officer', 'staff', 'document_officer', 'viewer', 'applicant']
const statuses: UserStatus[] = ['active', 'inactive', 'deactivated']
const editableStatuses: UserStatus[] = ['active', 'inactive']

const roleOptions = computed(() => roles.map(value => ({ value, title: t(`pages.settings.users.roleOptions.${value}`) })))
const statusOptions = computed(() => statuses.map(value => ({ value, title: t(`common.statuses.${value}`) })))
const editableStatusOptions = computed(() => editableStatuses.map(value => ({ value, title: t(`common.statuses.${value}`) })))
const headers = computed(() => [
  { title: t('pages.settings.users.user'), key: 'name' },
  { title: t('common.messages.roles'), key: 'roles', sortable: false },
  { title: t('common.fields.status'), key: 'acc_status' },
  { title: t('pages.settings.users.verification.label'), key: 'email_verified' },
  { title: t('pages.settings.users.created'), key: 'created_at' },
  { title: t('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

const filteredUsers = computed(() => users.value.filter(user => {
  const matchesSearch = `${user.name} ${user.email}`.toLowerCase().includes(search.value.toLowerCase())

  return matchesSearch && (!roleFilter.value || user.roles.includes(roleFilter.value)) && (!statusFilter.value || user.acc_status === statusFilter.value)
}))

const statusColor = (status: UserStatus) => ({ active: 'success', inactive: 'secondary', deactivated: 'warning' }[status])
const formatDate = (date: string | null) => date ? formatLocalizedDate(date, 'short') : t('common.messages.notAvailable')
const roleLabel = (role: UserRole) => t(`pages.settings.users.roleOptions.${role}`)
const verificationLabel = (user: ManagedUser) => user.password_setup_pending
  ? t('pages.settings.users.verification.invited')
  : user.email_verified ? t('pages.settings.users.verification.verified') : t('pages.settings.users.verification.pending')
const canDeleteUser = (user: ManagedUser) => !user.is_primary && !user.is_self
const deleteBlockedReason = (user: ManagedUser) => user.is_primary
  ? t('pages.settings.users.primaryAccountProtected')
  : t('pages.settings.users.selfDeleteBlocked')

async function loadUsers() {
  loading.value = true
  try { users.value = await getUsers() }
  catch (error: any) { toast.error(error.message || t('pages.settings.users.couldNotLoad')) }
  finally { loading.value = false }
}

function openCreate() {
  editingUser.value = null
  passwordMode.value = false
  form.value = emptyForm()
  dialogOpen.value = true
}

function openEdit(user: ManagedUser) {
  editingUser.value = user
  passwordMode.value = false
  form.value = { name: user.name, email: user.email, phone: user.phone, address: user.address, roles: [...user.roles], acc_status: user.acc_status }
  dialogOpen.value = true
}

async function save() {
  if (!form.value.name || !form.value.email || !form.value.roles.length)
    return toast.error(translate('pages.settings.users.nameEmailAndAtLeastOneRoleAreRequired'))

  const manualPassword = !editingUser.value && passwordMode.value
  if (manualPassword) {
    if (!form.value.password)
      return toast.error(translate('pages.settings.users.passwordRequired'))
    if (String(form.value.password).length < minimumPasswordLength)
      return toast.error(translate('pages.settings.users.passwordTooShort', { count: minimumPasswordLength }))
    if (form.value.password !== form.value.password_confirmation)
      return toast.error(translate('pages.settings.users.passwordsDoNotMatch'))
  }

  loading.value = true
  try {
    // Only a create may carry credentials; a manual password never leaves the
    // request, so no invitation email is triggered on the server.
    const payload: UserCreatePayload = { ...form.value }
    if (!manualPassword) {
      delete payload.password
      delete payload.password_confirmation
    }
    const user = editingUser.value ? await updateUser(editingUser.value.id, payload) : await createUser(payload)
    const index = users.value.findIndex(item => item.id === user.id)
    if (index === -1)
      users.value.unshift(user)
    else users.value[index] = user
    dialogOpen.value = false
    toast.success(translate(editingUser.value ? 'pages.settings.users.updated' : manualPassword ? 'pages.settings.users.userCreatedWithoutEmail' : 'pages.settings.users.invitationSent'))
  }
  catch (error: any) {
    toast.error(error.validationErrors?.email?.[0] || error.validationErrors?.password?.[0] || error.message)
  }
  finally { loading.value = false }
}

function askDelete(user: ManagedUser) {
  if (!canDeleteUser(user))
    return
  deletingUser.value = user
  deleteDialogOpen.value = true
}
async function confirmDelete() {
  if (!deletingUser.value)
    return
  try {
    await deleteUser(deletingUser.value.id)
    users.value = users.value.filter(user => user.id !== deletingUser.value?.id)
    toast.success(translate('pages.settings.users.userDeleted'))
    deleteDialogOpen.value = false
  }
  catch (error: any) { toast.error(error.message) }
}

onMounted(loadUsers)
</script>

<template>
  <DataTablePageKpis
    :items="filteredUsers"
    :title="$t('pages.settings.users.pageTitle')"
    :description="$t('pages.settings.users.pageDescription')"
    icon="tabler-user-cog"
    status-key="acc_status"
  />
  <VCard
    :title="$t('common.messages.users')"
    :loading="loading"
  >
    <VCardText>
      <VRow align="center">
        <VCol
          cols="12"
          md="8"
        >
          <VTextField
            v-model="search"
            class="data-table-search"
            prepend-inner-icon="tabler-search"
            :label="$t('pages.settings.users.searchUsers')"
            density="compact"
            hide-details
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
          class="d-flex justify-end ga-2"
        >
          <DataTableFilterMenu
            :active="Boolean(roleFilter || statusFilter)"
            @reset="roleFilter = null; statusFilter = null"
          >
            <VSelect
              v-model="roleFilter"
              :items="roleOptions"
              :label="$t('common.fields.role')"
              density="compact"
              hide-details
              clearable
            /><VSelect
              v-model="statusFilter"
              :items="statusOptions"
              :label="$t('common.fields.status')"
              density="compact"
              hide-details
              clearable
            />
          </DataTableFilterMenu><VBtn
            color="primary"
            prepend-icon="tabler-plus"
            @click="openCreate"
          >
            {{ $t('pages.settings.users.addUser') }}
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>
    <ResponsiveDataTable
      :headers="headers"
      :items="filteredUsers"
      :loading="loading"
      item-value="id"
      class="text-no-wrap"
    >
      <template #item.name="{ item }">
        <div>
          <div class="d-flex align-center flex-wrap gap-2">
            <div class="font-weight-medium">
              {{ item.name }}
            </div>
            <VChip
              v-if="item.is_primary"
              size="x-small"
              color="primary"
              variant="tonal"
            >
              {{ $t('pages.settings.users.primaryAccount') }}
            </VChip>
          </div><div class="text-caption text-medium-emphasis">
            {{ item.email }}
          </div>
        </div>
      </template>
      <template #item.roles="{ item }">
        <div class="d-flex flex-wrap ga-1">
          <VChip
            v-for="role in item.roles"
            :key="role"
            size="small"
            label
          >
            {{ roleLabel(role) }}
          </VChip>
        </div>
      </template>
      <template #item.acc_status="{ item }">
        <VChip
          size="small"
          label
          :color="statusColor(item.acc_status)"
        >
          {{ $t(`common.statuses.${item.acc_status}`) }}
        </VChip>
      </template>
      <template #item.email_verified="{ item }">
        <VChip
          size="small"
          label
          :color="item.email_verified && !item.password_setup_pending ? 'success' : 'warning'"
        >
          {{ verificationLabel(item) }}
        </VChip>
      </template>
      <template #item.created_at="{ item }">
        {{ formatDate(item.created_at) }}
      </template>
      <template #item.actions="{ item }">
        <FullDetailsButton
          :item="item"
          :title="item.name"
        /><IconBtn
          :aria-label="$t('pages.settings.users.editUser')"
          @click="openEdit(item)"
        >
          <VIcon icon="tabler-pencil" />
        </IconBtn><VTooltip
          v-if="!canDeleteUser(item)"
          :text="deleteBlockedReason(item)"
          location="top"
        >
          <template #activator="{ props }">
            <span v-bind="props"><IconBtn
              disabled
              :aria-label="deleteBlockedReason(item)"
            >
              <VIcon icon="tabler-lock" />
            </IconBtn></span>
          </template>
        </VTooltip><IconBtn
          v-else
          :aria-label="$t('pages.settings.users.deleteUser')"
          @click="askDelete(item)"
        >
          <VIcon icon="tabler-trash" />
        </IconBtn>
      </template>
    </ResponsiveDataTable>
  </VCard>

  <VDialog
    v-model="dialogOpen"
    max-width="700"
  >
  <VCard :title="$t(editingUser ? 'pages.settings.users.editUserDialog' : 'pages.settings.users.addUserDialog')">
      <VCardText>
        <template v-if="!editingUser">
          <VSwitch
            v-model="passwordMode"
            color="primary"
            inset
            :label="$t('pages.settings.users.setPasswordManually')"
            :hint="$t('pages.settings.users.setPasswordManuallyHint')"
            persistent-hint
            class="mb-5"
          />
          <VAlert
            :type="passwordMode ? 'warning' : 'info'"
            variant="tonal"
            class="mb-5"
          >
            {{ $t(passwordMode ? 'pages.settings.users.noEmailWillBeSent' : 'pages.settings.users.theUserWillReceiveAVerificationEmailThenCreateTheirOwnPassword') }}
          </VAlert>
        </template>
        <VAlert
          v-if="editingUser?.roles.includes('super_admin')"
          type="info"
          variant="tonal"
          class="mb-4"
        >
          {{ $t('pages.settings.users.lastSuperAdminRoleHint') }}
        </VAlert>
        <UserForm
          :form="form"
          :roles="roleOptions"
          :status-items="editingUser?.acc_status === 'deactivated' ? statusOptions : editableStatusOptions"
          :status-disabled="editingUser?.acc_status === 'deactivated'"
          :status-hint="editingUser?.acc_status === 'deactivated' ? $t('pages.settings.users.deactivatedStatusHint') : undefined"
          :editing="Boolean(editingUser)"
          :loading="loading"
          :password-mode="passwordMode"
          @submit="save"
          @cancel="dialogOpen = false"
        />
      </VCardText>
    </VCard>
  </VDialog>

  <VDialog
    v-model="deleteDialogOpen"
    max-width="420"
  >
    <VCard :title="$t('pages.settings.users.deleteUser2')">
      <VCardText>{{ $t('pages.settings.users.deleteConfirmation', { user: deletingUser?.name }) }}</VCardText><VCardActions>
        <VSpacer /><VBtn
          variant="tonal"
          @click="deleteDialogOpen = false"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn><VBtn
          color="error"
          @click="confirmDelete"
        >
          {{ $t('common.actions.delete') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
