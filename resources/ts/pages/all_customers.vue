<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import ApplicantApi, { type Applicant } from '@/api/applicants'
import EmployeeApi, { type Employee } from '@/api/employee'
import { canAccess } from '@/utils/authorization'

const { t } = useI18n()
const toast = useToast()
const applicants = ref<Applicant[]>([])
const marketers = ref<Employee[]>([])
const loading = ref(false)
const search = ref('')
const dialog = ref(false)
const editing = ref<Applicant | null>(null)
const form = ref({ name: '', contact: '', email: '', address: '', tax_number: '', marketer_id: null as number | null })
const canCreate = computed(() => canAccess('create_applicants'))
const canEdit = computed(() => canAccess('edit_applicants'))
const canDelete = computed(() => canAccess('delete_applicants'))
const headers = [
  { title: t('common.fields.name'), key: 'name' },
  { title: t('common.messages.contact'), key: 'contact' },
  { title: t('common.fields.email'), key: 'email' },
  { title: t('common.messages.actions'), key: 'actions', sortable: false },
]
const filtered = computed(() => {
  const value = search.value.trim().toLowerCase()
  if (!value) return applicants.value
  return applicants.value.filter(row => [row.name, row.contact, row.email].some(item => item?.toLowerCase().includes(value)))
})

async function load() {
  loading.value = true
  try {
    // Applicants are the unified party/case record. There is no customer API.
    applicants.value = (await ApplicantApi.list({ per_page: 100, search: search.value || undefined })).data
  } catch { toast.error(t('educational.messages.loadApplicantsFailed')) } finally { loading.value = false }
}
function openCreate() {
  editing.value = null
  form.value = { name: '', contact: '', email: '', address: '', tax_number: '', marketer_id: null }
  dialog.value = true
}
function openEdit(row: Applicant) {
  editing.value = row
  form.value = { name: row.name, contact: row.contact ?? '', email: row.email ?? '', address: row.address ?? '', tax_number: row.tax_number ?? '', marketer_id: row.marketer_id ?? null }
  dialog.value = true
}
async function save() {
  if (!form.value.name.trim()) return
  const payload = { ...form.value, name: form.value.name.trim() }
  try {
    if (editing.value) await ApplicantApi.update(editing.value.id, payload)
    else await ApplicantApi.create(payload)
    dialog.value = false
    await load()
  } catch { toast.error(t('educational.messages.createCaseFailed')) }
}
async function remove(row: Applicant) {
  if (!confirm(t('common.messages.areYouSure'))) return
  try { await ApplicantApi.delete(row.id); await load() } catch { toast.error(t('common.messages.failedToDelete')) }
}
onMounted(async () => {
  await Promise.all([load(), EmployeeApi.getMarketerOptions().then(rows => marketers.value = rows)])
})
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex align-center ga-3">
      <VIcon icon="tabler-users" /> {{ $t('common.navigation.applicants') }}
      <VSpacer /><VBtn v-if="canCreate" color="primary" prepend-icon="tabler-plus" @click="openCreate">{{ $t('common.actions.add') }}</VBtn>
    </VCardTitle>
    <VCardText><VTextField v-model="search" :label="$t('educational.searchApplicant')" prepend-inner-icon="tabler-search" clearable @keyup.enter="load" /></VCardText>
    <ResponsiveDataTable :headers="headers" :items="filtered" :loading="loading" item-value="id">
      <template #item.actions="{ item }"><VBtn v-if="canEdit" icon="tabler-edit" variant="text" @click="openEdit(item)" /><VBtn v-if="canDelete" icon="tabler-trash" color="error" variant="text" @click="remove(item)" /></template>
    </ResponsiveDataTable>
  </VCard>
  <VDialog v-model="dialog" max-width="600"><VCard :title="editing ? $t('common.actions.edit') : $t('common.actions.add')"><VCardText><VTextField v-model="form.name" :label="$t('common.fields.name')" class="mb-3" /><VTextField v-model="form.contact" :label="$t('common.messages.contact')" class="mb-3" /><VTextField v-model="form.email" :label="$t('common.fields.email')" class="mb-3" /><VTextarea v-model="form.address" :label="$t('common.fields.address')" class="mb-3" /><VTextField v-model="form.tax_number" :label="$t('common.fields.taxNumber')" class="mb-3" /><VSelect v-model="form.marketer_id" :items="marketers.map(row => ({ title: row.name, value: row.id }))" :label="$t('educational.marketer')" clearable /></VCardText><VCardActions><VSpacer /><VBtn @click="dialog = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="primary" :disabled="!form.name.trim()" @click="save">{{ $t('common.actions.save') }}</VBtn></VCardActions></VCard></VDialog>
</template>
