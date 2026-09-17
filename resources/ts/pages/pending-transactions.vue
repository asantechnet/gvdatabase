<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import PendingTransactionApi, { type PendingTransaction, type PendingTransactionInput, type PendingTransactionOptions, type PendingTransactionStatus } from '@/api/pendingTransactions'
import { canAccess } from '@/utils/authorization'

const toast = useToast()
const loading = ref(false)
const dialog = ref(false)
const editing = ref<PendingTransaction | null>(null)
const rejectDialog = ref(false)
const selected = ref<PendingTransaction | null>(null)
const rejectionReason = ref('')
const rows = ref<PendingTransaction[]>([])
const options = ref<PendingTransactionOptions>({ currencies: [], customers: [], employees: [], applicants: [] })
const form = ref<PendingTransactionInput>({ transaction_type: 'income', reason: '', amount: 0, currency_id: null, customer_id: null, applicant_id: null, employee_id: null })

const canCreate = computed(() => canAccess('create_pending_transactions'))
const canApprove = computed(() => canAccess('approve_pending_transactions'))
const canPost = computed(() => canAccess('post_pending_transactions'))
const currencyOptions = computed(() => options.value.currencies.map(currency => ({ title: `${currency.code} — ${currency.name}`, value: currency.id })))
const customerOptions = computed(() => options.value.customers.map(customer => ({ title: customer.name, value: customer.id })))
const employeeOptions = computed(() => options.value.employees.map(employee => ({ title: employee.name, value: employee.id })))
const applicantOptions = computed(() => options.value.applicants.filter(applicant => !form.value.customer_id || applicant.customer_id === form.value.customer_id).map(applicant => ({ title: `Case #${applicant.id} — ${applicant.customer?.name ?? 'Customer'}`, value: applicant.id })))
const needsEmployee = computed(() => form.value.transaction_type === 'salary')

const statusColor = (status: PendingTransactionStatus) => ({ submitted: 'warning', under_review: 'info', approved: 'success', rejected: 'error', posted: 'primary', draft: 'secondary' }[status])
const money = (row: PendingTransaction) => `${row.currency?.symbol ?? ''}${Number(row.amount).toLocaleString()} ${row.currency?.code ?? ''}`.trim()

async function load() {
  loading.value = true
  try {
    const [transactions, formOptions] = await Promise.all([PendingTransactionApi.list(), PendingTransactionApi.options()])
    rows.value = transactions
    options.value = formOptions
  }
  catch {
    toast.error('Unable to load pending transactions.')
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
    toast.success(editing.value ? 'Transaction updated.' : 'Transaction submitted for approval.')
  }
  catch (error: unknown) {
    const message = (error as { response?: { data?: { message?: string } }; message?: string })?.response?.data?.message
      || (error as { message?: string })?.message
      || 'Unable to save the transaction. It may be locked or outside the edit window.'

    toast.error(message)
  }
}
async function transition(action: 'review' | 'approve' | 'post', row: PendingTransaction) { try { await PendingTransactionApi[action](row.id); await load(); toast.success(`Transaction ${action === 'post' ? 'posted' : `${action}ed`}.`) } catch { toast.error('This transition is not allowed.') } }
function openReject(row: PendingTransaction) { selected.value = row; rejectionReason.value = ''; rejectDialog.value = true }
async function reject() { if (!selected.value || !rejectionReason.value.trim()) return; try { await PendingTransactionApi.reject(selected.value.id, rejectionReason.value); rejectDialog.value = false; await load(); toast.success('Transaction rejected.') } catch { toast.error('Unable to reject the transaction.') } }

onMounted(load)
</script>

<template>
  <VCard>
    <VCardItem title="Pending transactions" subtitle="Requests are not official finance records until an authorized user approves and posts them.">
      <template #append><VBtn v-if="canCreate" prepend-icon="tabler-plus" color="primary" @click="openCreate">New request</VBtn></template>
    </VCardItem>
    <VProgressLinear v-if="loading" indeterminate />
    <VTable><thead><tr><th>Type</th><th>Reason</th><th>Customer / employee</th><th>Amount</th><th>Status</th><th>Requested</th><th /></tr></thead><tbody>
      <tr v-for="row in rows" :key="row.id"><td class="text-capitalize">{{ row.transaction_type }}</td><td>{{ row.reason }}<div v-if="row.applicant_id"><VChip size="x-small" color="info" :to="{ name: 'applicant-case', params: { id: row.applicant_id } }">Case #{{ row.applicant_id }}</VChip></div></td><td>{{ row.customer?.name || row.employee?.name || '—' }}</td><td>{{ money(row) }}</td><td><VChip size="small" :color="statusColor(row.approval_status)">{{ row.approval_status.replace('_', ' ') }}</VChip><div v-if="row.rejection_reason" class="text-caption text-error">{{ row.rejection_reason }}</div></td><td>{{ row.requested_at ? new Date(row.requested_at).toLocaleString() : '—' }}</td><td class="text-end"><VBtn v-if="canCreate && ['draft', 'submitted'].includes(row.approval_status)" size="small" variant="text" @click="openEdit(row)">Edit</VBtn><VBtn v-if="canApprove && row.approval_status === 'submitted'" size="small" variant="text" @click="transition('review', row)">Review</VBtn><VBtn v-if="canApprove && ['submitted', 'under_review'].includes(row.approval_status)" size="small" color="success" variant="text" @click="transition('approve', row)">Approve</VBtn><VBtn v-if="canApprove && ['submitted', 'under_review'].includes(row.approval_status)" size="small" color="error" variant="text" @click="openReject(row)">Reject</VBtn><VBtn v-if="canPost && row.approval_status === 'approved'" size="small" color="primary" variant="text" @click="transition('post', row)">Post</VBtn></td></tr>
      <tr v-if="!loading && !rows.length"><td colspan="7" class="text-center text-medium-emphasis py-8">No pending transactions.</td></tr>
    </tbody></VTable>
  </VCard>
  <VDialog v-model="dialog" max-width="600"><VCard :title="editing ? 'Edit pending transaction' : 'New pending transaction'"><VCardText><VSelect v-model="form.transaction_type" :items="['income', 'expense', 'salary']" label="Transaction type" class="mb-3" :disabled="!!editing"/><VTextarea v-model="form.reason" label="Reason" class="mb-3"/><VTextField v-model.number="form.amount" type="number" min="0.000001" label="Amount" class="mb-3"/><VSelect v-model="form.currency_id" :items="currencyOptions" label="Currency" class="mb-3" :disabled="!!editing"/><VSelect v-model="form.customer_id" :items="customerOptions" label="Customer (optional)" clearable class="mb-3" :disabled="!!editing"/><VSelect v-model="form.applicant_id" :items="applicantOptions" label="Applicant / case (optional)" clearable class="mb-3" :disabled="!!editing"/><VSelect v-model="form.employee_id" :items="employeeOptions" :label="needsEmployee ? 'Employee' : 'Employee (optional)'" :rules="[value => !needsEmployee || !!value || 'An employee is required for salary']" clearable :disabled="!!editing"/></VCardText><VCardActions><VSpacer/><VBtn @click="dialog = false">Cancel</VBtn><VBtn color="primary" :disabled="!form.reason || form.amount <= 0 || !form.currency_id || (needsEmployee && !form.employee_id)" @click="save">{{ editing ? 'Save changes' : 'Submit' }}</VBtn></VCardActions></VCard></VDialog>
  <VDialog v-model="rejectDialog" max-width="500"><VCard title="Reject transaction"><VCardText><VTextarea v-model="rejectionReason" label="Reason for rejection" autofocus/></VCardText><VCardActions><VSpacer/><VBtn @click="rejectDialog = false">Cancel</VBtn><VBtn color="error" :disabled="!rejectionReason.trim()" @click="reject">Reject</VBtn></VCardActions></VCard></VDialog>
</template>
