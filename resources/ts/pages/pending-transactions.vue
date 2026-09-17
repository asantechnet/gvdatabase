<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import CurrencyApi, { type Currency } from '@/api/currency'
import CustomerApi, { type Customer } from '@/api/customer'
import EmployeeApi, { type Employee } from '@/api/employee'
import PendingTransactionApi, { type PendingTransaction, type PendingTransactionInput, type PendingTransactionStatus } from '@/api/pendingTransactions'
import { canAccess } from '@/utils/authorization'

const toast = useToast()
const loading = ref(false)
const dialog = ref(false)
const rejectDialog = ref(false)
const selected = ref<PendingTransaction | null>(null)
const rejectionReason = ref('')
const rows = ref<PendingTransaction[]>([])
const currencies = ref<Currency[]>([])
const customers = ref<Customer[]>([])
const employees = ref<Employee[]>([])
const form = ref<PendingTransactionInput>({ transaction_type: 'income', reason: '', amount: 0, currency_id: null, customer_id: null, applicant_id: null, employee_id: null })

const canCreate = computed(() => canAccess('create_pending_transactions'))
const canApprove = computed(() => canAccess('approve_pending_transactions'))
const canPost = computed(() => canAccess('post_pending_transactions'))
const currencyOptions = computed(() => currencies.value.filter(currency => currency.isActive).map(currency => ({ title: `${currency.code} — ${currency.name}`, value: currency.id })))
const customerOptions = computed(() => customers.value.map(customer => ({ title: customer.name, value: customer.id })))
const employeeOptions = computed(() => employees.value.map(employee => ({ title: employee.name, value: employee.id })))
const needsEmployee = computed(() => form.value.transaction_type === 'salary')

const statusColor = (status: PendingTransactionStatus) => ({ submitted: 'warning', under_review: 'info', approved: 'success', rejected: 'error', posted: 'primary', draft: 'secondary' }[status])
const money = (row: PendingTransaction) => `${row.currency?.symbol ?? ''}${Number(row.amount).toLocaleString()} ${row.currency?.code ?? ''}`.trim()

async function load() {
  loading.value = true
  try {
    const [transactions, currencyResponse, customerResponse, employeeList] = await Promise.all([PendingTransactionApi.list(), CurrencyApi.getAll(), CustomerApi.getAll(), EmployeeApi.getOptions()])
    rows.value = transactions
    currencies.value = currencyResponse.data
    customers.value = customerResponse.data
    employees.value = employeeList
  }
  catch {
    toast.error('Unable to load pending transactions.')
  }
  finally { loading.value = false }
}

function openCreate() { form.value = { transaction_type: 'income', reason: '', amount: 0, currency_id: currencies.value.find(currency => currency.isBase)?.id ?? null, customer_id: null, applicant_id: null, employee_id: null }; dialog.value = true }
async function create() { try { await PendingTransactionApi.create(form.value); dialog.value = false; await load(); toast.success('Transaction submitted for approval.') } catch { toast.error('Unable to submit the transaction.') } }
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
      <tr v-for="row in rows" :key="row.id"><td class="text-capitalize">{{ row.transaction_type }}</td><td>{{ row.reason }}</td><td>{{ row.customer?.name || row.employee?.name || '—' }}</td><td>{{ money(row) }}</td><td><VChip size="small" :color="statusColor(row.approval_status)">{{ row.approval_status.replace('_', ' ') }}</VChip><div v-if="row.rejection_reason" class="text-caption text-error">{{ row.rejection_reason }}</div></td><td>{{ row.requested_at ? new Date(row.requested_at).toLocaleString() : '—' }}</td><td class="text-end"><VBtn v-if="canApprove && row.approval_status === 'submitted'" size="small" variant="text" @click="transition('review', row)">Review</VBtn><VBtn v-if="canApprove && ['submitted', 'under_review'].includes(row.approval_status)" size="small" color="success" variant="text" @click="transition('approve', row)">Approve</VBtn><VBtn v-if="canApprove && ['submitted', 'under_review'].includes(row.approval_status)" size="small" color="error" variant="text" @click="openReject(row)">Reject</VBtn><VBtn v-if="canPost && row.approval_status === 'approved'" size="small" color="primary" variant="text" @click="transition('post', row)">Post</VBtn></td></tr>
      <tr v-if="!loading && !rows.length"><td colspan="7" class="text-center text-medium-emphasis py-8">No pending transactions.</td></tr>
    </tbody></VTable>
  </VCard>
  <VDialog v-model="dialog" max-width="600"><VCard title="New pending transaction"><VCardText><VSelect v-model="form.transaction_type" :items="['income', 'expense', 'salary']" label="Transaction type" class="mb-3"/><VTextarea v-model="form.reason" label="Reason" class="mb-3"/><VTextField v-model.number="form.amount" type="number" min="0.000001" label="Amount" class="mb-3"/><VSelect v-model="form.currency_id" :items="currencyOptions" label="Currency" class="mb-3"/><VSelect v-model="form.customer_id" :items="customerOptions" label="Customer (optional)" clearable class="mb-3"/><VSelect v-model="form.employee_id" :items="employeeOptions" :label="needsEmployee ? 'Employee' : 'Employee (optional)'" :rules="[value => !needsEmployee || !!value || 'An employee is required for salary']" clearable/></VCardText><VCardActions><VSpacer/><VBtn @click="dialog = false">Cancel</VBtn><VBtn color="primary" :disabled="!form.reason || form.amount <= 0 || !form.currency_id || (needsEmployee && !form.employee_id)" @click="create">Submit</VBtn></VCardActions></VCard></VDialog>
  <VDialog v-model="rejectDialog" max-width="500"><VCard title="Reject transaction"><VCardText><VTextarea v-model="rejectionReason" label="Reason for rejection" autofocus/></VCardText><VCardActions><VSpacer/><VBtn @click="rejectDialog = false">Cancel</VBtn><VBtn color="error" :disabled="!rejectionReason.trim()" @click="reject">Reject</VBtn></VCardActions></VCard></VDialog>
</template>
