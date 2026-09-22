<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import PendingTransactionApi, { type PendingTransaction, type PendingTransactionInput, type PendingTransactionOptions, type PendingTransactionStatus } from '@/api/pendingTransactions'
import { canAccess } from '@/utils/authorization'
import { translateBackendValue } from '@/utils/localization'

const toast = useToast()
const { t, d, n } = useI18n()
const loading = ref(false)
const dialog = ref(false)
const editing = ref<PendingTransaction | null>(null)
const rejectDialog = ref(false)
const selected = ref<PendingTransaction | null>(null)
const rejectionReason = ref('')
const rows = ref<PendingTransaction[]>([])
const page = ref(1)
const lastPage = ref(1)
const perPage = ref(25)
const total = ref(0)
const options = ref<PendingTransactionOptions>({ currencies: [], customers: [], employees: [], applicants: [] })
const form = ref<PendingTransactionInput>({ transaction_type: 'income', reason: '', amount: 0, currency_id: null, customer_id: null, applicant_id: null, employee_id: null })

const canCreate = computed(() => canAccess('create_pending_transactions'))
const canApprove = computed(() => canAccess('approve_pending_transactions'))
const canPost = computed(() => canAccess('post_pending_transactions'))
const currencyOptions = computed(() => options.value.currencies.map(currency => ({ title: `${currency.code} — ${currency.name}`, value: currency.id })))
const customerOptions = computed(() => options.value.customers.map(customer => ({ title: customer.name, value: customer.id })))
const employeeOptions = computed(() => options.value.employees.map(employee => ({ title: employee.name, value: employee.id })))
const applicantOptions = computed(() => options.value.applicants.filter(applicant => !form.value.customer_id || applicant.customer_id === form.value.customer_id).map(applicant => ({ title: t('educational.caseOption', { id: applicant.id, customer: applicant.customer?.name ?? t('educational.customer') }), value: applicant.id })))
const needsEmployee = computed(() => form.value.transaction_type === 'salary')

const statusColor = (status: PendingTransactionStatus) => ({ submitted: 'warning', under_review: 'info', approved: 'success', rejected: 'error', posted: 'primary', draft: 'secondary' }[status])
const money = (row: PendingTransaction) => `${row.currency?.symbol ?? ''}${n(Number(row.amount), 'decimal')} ${row.currency?.code ?? ''}`.trim()

async function load() {
  loading.value = true
  try {
    const [transactions, formOptions] = await Promise.all([PendingTransactionApi.list(page.value, perPage.value), PendingTransactionApi.options()])
    rows.value = transactions.data
    page.value = transactions.current_page
    lastPage.value = transactions.last_page
    perPage.value = transactions.per_page
    total.value = transactions.total
    options.value = formOptions
  }
  catch {
    toast.error(t('educational.loadReportFailed'))
  }
  finally { loading.value = false }
}

function openCreate() { editing.value = null; form.value = { transaction_type: 'income', reason: '', amount: 0, currency_id: options.value.currencies.find(currency => currency.is_base)?.id ?? null, customer_id: null, applicant_id: null, employee_id: null }; dialog.value = true }
function openEdit(row: PendingTransaction) { editing.value = row; form.value = { transaction_type: row.transaction_type, reason: row.reason, amount: Number(row.amount), currency_id: row.currency_id, customer_id: row.customer_id, applicant_id: row.applicant_id, employee_id: row.employee_id }; dialog.value = true }
async function save() {
  try {
    if (editing.value)
      await PendingTransactionApi.update(editing.value.id, form.value)
    else
      await PendingTransactionApi.create(form.value)

    dialog.value = false
    await load()
    toast.success(editing.value ? t('common.actions.update') : t('common.actions.submit'))
  }
  catch (error: unknown) {
    const message = (error as { response?: { data?: { message?: string } }; message?: string })?.response?.data?.message
      || (error as { message?: string })?.message
      || t('educational.pendingUi.saveFailed')

    toast.error(message)
  }
}
async function transition(action: 'review' | 'approve' | 'post', row: PendingTransaction) { try { await PendingTransactionApi[action](row.id); await load(); toast.success(t('educational.pendingUi.transitioned', { status: translateBackendValue(action === 'review' ? 'under_review' : action === 'approve' ? 'approved' : 'posted') })) } catch { toast.error(t('educational.messages.transitionNotAllowed')) } }
function openReject(row: PendingTransaction) { selected.value = row; rejectionReason.value = ''; rejectDialog.value = true }
async function reject() { if (!selected.value || !rejectionReason.value.trim()) return; try { await PendingTransactionApi.reject(selected.value.id, rejectionReason.value); rejectDialog.value = false; await load(); toast.success(t('educational.pendingUi.rejected')) } catch { toast.error(t('educational.pendingUi.rejectFailed')) } }

onMounted(load)
</script>

<template>
  <VCard>
    <VCardItem :title="$t('common.navigation.loans')" :subtitle="$t('educational.pendingSubtitle')">
      <template #append><VBtn v-if="canCreate" prepend-icon="tabler-plus" color="primary" @click="openCreate">{{ $t('educational.newRequest') }}</VBtn></template>
    </VCardItem>
    <VProgressLinear v-if="loading" indeterminate />
    <VTable><thead><tr><th>{{ $t('educational.transactionType') }}</th><th>{{ $t('educational.reason') }}</th><th>{{ $t('educational.pendingUi.customerEmployee') }}</th><th>{{ $t('educational.amount') }}</th><th>{{ $t('educational.status') }}</th><th>{{ $t('educational.requested') }}</th><th /></tr></thead><tbody>
      <tr v-for="row in rows" :key="row.id"><td class="text-capitalize">{{ translateBackendValue(row.transaction_type) }}</td><td>{{ row.reason }}<div v-if="row.applicant_id"><VChip size="x-small" color="info" :to="{ name: 'applicant-case', params: { id: row.applicant_id } }">{{ $t('educational.caseNumber', { id: row.applicant_id }) }}</VChip></div></td><td>{{ row.customer?.name || row.employee?.name || '—' }}</td><td>{{ money(row) }}</td><td><VChip size="small" :color="statusColor(row.approval_status)">{{ translateBackendValue(row.approval_status) }}</VChip><div v-if="row.rejection_reason" class="text-caption text-error">{{ row.rejection_reason }}</div></td><td>{{ row.requested_at ? d(new Date(row.requested_at), 'long') : '—' }}</td><td class="text-end"><VBtn v-if="canCreate && ['draft', 'submitted'].includes(row.approval_status)" size="small" variant="text" @click="openEdit(row)">{{ $t('common.actions.edit') }}</VBtn><VBtn v-if="canApprove && row.approval_status === 'submitted'" size="small" variant="text" @click="transition('review', row)">{{ $t('educational.review') }}</VBtn><VBtn v-if="canApprove && ['submitted', 'under_review'].includes(row.approval_status)" size="small" color="success" variant="text" @click="transition('approve', row)">{{ $t('common.actions.approve') }}</VBtn><VBtn v-if="canApprove && ['submitted', 'under_review'].includes(row.approval_status)" size="small" color="error" variant="text" @click="openReject(row)">{{ $t('common.actions.reject') }}</VBtn><VBtn v-if="canPost && row.approval_status === 'approved'" size="small" color="primary" variant="text" @click="transition('post', row)">{{ $t('educational.post') }}</VBtn></td></tr>
      <tr v-if="!loading && !rows.length"><td colspan="7" class="text-center text-medium-emphasis py-8">{{ $t('educational.noTransactions') }}</td></tr>
    </tbody></VTable>
    <VCardText class="d-flex align-center gap-4"><VPagination v-model="page" :length="Math.max(lastPage, 1)" size="small" @update:model-value="load"/><VSelect v-model="perPage" :items="[10, 25, 50, 100]" :label="$t('educational.perPage')" style="max-inline-size: 140px" @update:model-value="page = 1; load()"/><span class="text-caption text-medium-emphasis">{{ $t('educational.resultCount', { count: total }) }}</span></VCardText>
  </VCard>
  <VDialog v-model="dialog" max-width="600"><VCard :title="$t(editing ? 'educational.editPending' : 'educational.newPending')"><VCardText><VSelect v-model="form.transaction_type" :items="['income', 'expense', 'salary'].map(value => ({ value, title: translateBackendValue(value) }))" :label="$t('educational.transactionType')" class="mb-3" :disabled="!!editing"/><VTextarea v-model="form.reason" :label="$t('educational.reason')" class="mb-3"/><VTextField v-model.number="form.amount" type="number" min="0.000001" :label="$t('educational.amount')" class="mb-3"/><VSelect v-model="form.currency_id" :items="currencyOptions" :label="$t('common.fields.currency')" class="mb-3" :disabled="!!editing"/><VSelect v-model="form.customer_id" :items="customerOptions" :label="$t('educational.customerOptional')" clearable class="mb-3" :disabled="!!editing"/><VSelect v-model="form.applicant_id" :items="applicantOptions" :label="$t('educational.applicantOptional')" clearable class="mb-3" :disabled="!!editing"/><VSelect v-model="form.employee_id" :items="employeeOptions" :label="$t(needsEmployee ? 'educational.employee' : 'educational.employeeOptional')" :rules="[value => !needsEmployee || !!value || $t('educational.pendingUi.employeeRequired')]" clearable :disabled="!!editing"/></VCardText><VCardActions><VSpacer/><VBtn @click="dialog = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="primary" :disabled="!form.reason || form.amount <= 0 || !form.currency_id || (needsEmployee && !form.employee_id)" @click="save">{{ $t(editing ? 'common.actions.update' : 'common.actions.submit') }}</VBtn></VCardActions></VCard></VDialog>
  <VDialog v-model="rejectDialog" max-width="500"><VCard :title="$t('educational.rejectTransaction')"><VCardText><VTextarea v-model="rejectionReason" :label="$t('educational.rejectionReason')" autofocus/></VCardText><VCardActions><VSpacer/><VBtn @click="rejectDialog = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="error" :disabled="!rejectionReason.trim()" @click="reject">{{ $t('common.actions.reject') }}</VBtn></VCardActions></VCard></VDialog>
</template>
