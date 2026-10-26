<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import PendingTransactionApi, { type PendingTransaction, type PendingTransactionInput, type PendingTransactionOptions, type PendingTransactionStatus } from '@/api/pendingTransactions'
import { canAccess } from '@/utils/authorization'
import { translateBackendValue } from '@/utils/localization'
import ReceiptPrinter from '@/components/income/ReceiptPrinter.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'

const toast = useToast()
const { t, d, n } = useI18n()
const loading = ref(false)
const dialog = ref(false)
const editing = ref<PendingTransaction | null>(null)
const rejectDialog = ref(false)
const selected = ref<PendingTransaction | null>(null)
const receiptOpen = ref(false)
const receiptLoanId = ref<number | null>(null)
const rejectionReason = ref('')
const rows = ref<PendingTransaction[]>([])
const page = ref(1)
const lastPage = ref(1)
const perPage = ref(25)
const total = ref(0)
const options = ref<PendingTransactionOptions>({ currencies: [], employees: [], applicants: [] })
const form = ref<PendingTransactionInput>({ transaction_type: 'income', reason: '', amount: 0, currency_id: null, applicant_id: null, employee_id: null })

const canCreate = computed(() => canAccess('create_pending_transactions'))
const canApprove = computed(() => canAccess('approve_pending_transactions'))
const canPost = computed(() => canAccess('post_pending_transactions'))
const currencyOptions = computed(() => options.value.currencies.map(currency => ({ title: `${currency.code} — ${currency.name}`, value: currency.id })))
const employeeOptions = computed(() => options.value.employees.map(employee => ({ title: employee.name, value: employee.id })))
const applicantOptions = computed(() => options.value.applicants.map(applicant => ({ title: applicant.name, value: applicant.id })))
// Payroll runs through the salary module, so a payment request is either money
// coming in (income) or an office/case cost (expense). An expense may still name
// an employee as its payee; any other direction clears that field.
const showsEmployeeField = computed(() => form.value.transaction_type === 'expense')
watch(showsEmployeeField, visible => {
  if (!visible)
    form.value.employee_id = null
})

const statusColor = (status: PendingTransactionStatus) => ({ submitted: 'warning', under_review: 'info', approved: 'success', rejected: 'error', posted: 'primary', draft: 'secondary' }[status])
// Direction decides both the accent and the printable document: money coming in
// prints an income receipt, money going out (expense) prints a payment voucher.
const isOutgoing = (row: PendingTransaction) => row.transaction_type !== 'income'
const directionColor = (row: PendingTransaction) => (isOutgoing(row) ? 'error' : 'success')
const directionIcon = (row: PendingTransaction) => (isOutgoing(row) ? 'tabler-arrow-up-right' : 'tabler-arrow-down-left')
const printLabelKey = (row: PendingTransaction) => (isOutgoing(row) ? 'documents.paymentVoucher.print' : 'documents.loan.print')
// The finance office approves a request before it can be printed.
const canPrint = (row: PendingTransaction) => ['approved', 'posted'].includes(row.approval_status)
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

function openCreate() { editing.value = null; form.value = { transaction_type: 'income', reason: '', amount: 0, currency_id: options.value.currencies.find(currency => currency.is_base)?.id ?? null, applicant_id: null, employee_id: null }; dialog.value = true }
function openEdit(row: PendingTransaction) { editing.value = row; form.value = { transaction_type: row.transaction_type, reason: row.reason, amount: Number(row.amount), currency_id: row.currency_id, applicant_id: row.applicant_id, employee_id: row.employee_id }; dialog.value = true }
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
function openLoanReceipt(row: PendingTransaction) { receiptLoanId.value = row.id; receiptOpen.value = true }
async function reject() { if (!selected.value || !rejectionReason.value.trim()) return; try { await PendingTransactionApi.reject(selected.value.id, rejectionReason.value); rejectDialog.value = false; await load(); toast.success(t('educational.pendingUi.rejected')) } catch { toast.error(t('educational.pendingUi.rejectFailed')) } }

onMounted(load)
</script>

<template>
  <VCard>
    <VCardItem :title="$t('common.navigation.paymentRequests')" :subtitle="$t('educational.paymentRequestsSubtitle')">
      <template #append><VBtn v-if="canCreate" prepend-icon="tabler-plus" color="primary" @click="openCreate">{{ $t('educational.paymentRequestsNew') }}</VBtn></template>
    </VCardItem>
    <ResponsiveDataTable
      :loading="loading"
      :items="rows"
      :headers="[
        { title: $t('educational.transactionType'), key: 'transaction_type' },
        { title: $t('educational.pendingUi.customerEmployee'), key: 'customer_employee' },
        { title: $t('educational.amount'), key: 'amount' },
        { title: $t('educational.status'), key: 'approval_status' },
        { title: $t('educational.requested'), key: 'requested_at' },
        { title: $t('common.messages.actions'), key: 'actions', sortable: false },
      ]"
      item-value="id"
      mobile-cards
      mobile-title-key="transaction_type"
      mobile-action-key="actions"
    >
      <template #loading>
        <TableLoadingSkeleton :columns="6" :label="$t('common.messages.loading')" />
      </template>
      <template #no-data>
        <TableEmptyState icon="tabler-receipt-off" :description="$t('educational.noTransactions')" />
      </template>
      <template #item.transaction_type="{ item }">
        <VChip size="small" variant="tonal" :color="directionColor(item)">
          <VIcon :icon="directionIcon(item)" size="14" start />{{ translateBackendValue(item.transaction_type) }}
        </VChip>
      </template>
      <template #item.customer_employee="{ item }">
        {{ item.applicant?.name || item.employee?.name || '—' }}
      </template>
      <template #item.amount="{ item }">
        {{ money(item) }}
      </template>
      <template #item.approval_status="{ item }">
        <VChip size="small" :color="statusColor(item.approval_status)">{{ translateBackendValue(item.approval_status) }}</VChip>
        <div v-if="item.rejection_reason" class="text-caption text-error">{{ item.rejection_reason }}</div>
      </template>
      <template #item.requested_at="{ item }">
        {{ item.requested_at ? d(new Date(item.requested_at), 'long') : '—' }}
      </template>
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <FullDetailsButton :item="item" />
          <VTooltip v-if="canPrint(item)" location="top" :text="$t(printLabelKey(item))"><template #activator="{ props: tooltipProps }"><VBtn v-bind="tooltipProps" icon size="small" variant="text" color="primary" :aria-label="$t(printLabelKey(item))" @click="openLoanReceipt(item)"><VIcon icon="tabler-printer" size="18" /></VBtn></template></VTooltip><VTooltip v-if="canCreate && ['draft', 'submitted'].includes(item.approval_status)" location="top" :text="$t('educational.pendingUi.actions.edit')"><template #activator="{ props: tooltipProps }"><VBtn v-bind="tooltipProps" icon size="small" variant="text" :aria-label="$t('educational.pendingUi.actions.edit')" @click="openEdit(item)"><VIcon icon="tabler-pencil" size="18" /></VBtn></template></VTooltip><VTooltip v-if="canApprove && item.approval_status === 'submitted'" location="top" :text="$t('educational.pendingUi.actions.review')"><template #activator="{ props: tooltipProps }"><VBtn v-bind="tooltipProps" icon size="small" variant="text" color="info" :aria-label="$t('educational.pendingUi.actions.review')" @click="transition('review', item)"><VIcon icon="tabler-eye" size="18" /></VBtn></template></VTooltip><VTooltip v-if="canApprove && ['submitted', 'under_review'].includes(item.approval_status)" location="top" :text="$t('educational.pendingUi.actions.approve')"><template #activator="{ props: tooltipProps }"><VBtn v-bind="tooltipProps" icon size="small" variant="text" color="success" :aria-label="$t('educational.pendingUi.actions.approve')" @click="transition('approve', item)"><VIcon icon="tabler-check" size="18" /></VBtn></template></VTooltip><VTooltip v-if="canApprove && ['submitted', 'under_review'].includes(item.approval_status)" location="top" :text="$t('educational.pendingUi.actions.reject')"><template #activator="{ props: tooltipProps }"><VBtn v-bind="tooltipProps" icon size="small" variant="text" color="error" :aria-label="$t('educational.pendingUi.actions.reject')" @click="openReject(item)"><VIcon icon="tabler-x" size="18" /></VBtn></template></VTooltip><VTooltip v-if="canPost && item.approval_status === 'approved'" location="top" :text="$t('educational.pendingUi.actions.post')"><template #activator="{ props: tooltipProps }"><VBtn v-bind="tooltipProps" icon size="small" variant="text" color="primary" :aria-label="$t('educational.pendingUi.actions.post')" @click="transition('post', item)"><VIcon icon="tabler-send" size="18" /></VBtn></template></VTooltip>
        </div>
      </template>
    </ResponsiveDataTable>
    <VCardText class="d-flex align-center gap-4"><VPagination v-model="page" :length="Math.max(lastPage, 1)" size="small" @update:model-value="load"/><VSelect v-model="perPage" :items="[10, 25, 50, 100]" :label="$t('educational.perPage')" style="max-inline-size: 140px" @update:model-value="page = 1; load()"/><span class="text-caption text-medium-emphasis">{{ $t('educational.resultCount', { count: total }) }}</span></VCardText>
  </VCard>
  <VDialog v-model="dialog" max-width="600"><VCard :title="$t(editing ? 'educational.editPending' : 'educational.newPending')"><VCardText><VSelect v-model="form.transaction_type" :items="['income', 'expense'].map(value => ({ value, title: translateBackendValue(value) }))" :label="$t('educational.transactionType')" class="mb-3" :disabled="!!editing"/><VTextarea v-model="form.reason" :label="$t('educational.reason')" class="mb-3"/><VTextField v-model.number="form.amount" type="number" min="0.000001" :label="$t('educational.amount')" class="mb-3"/><VSelect v-model="form.currency_id" :items="currencyOptions" :label="$t('common.fields.currency')" class="mb-3" :disabled="!!editing"/><VSelect v-model="form.applicant_id" :items="applicantOptions" :label="$t('educational.applicantOptional')" clearable class="mb-3" :disabled="!!editing"/><VSelect v-if="showsEmployeeField" v-model="form.employee_id" :items="employeeOptions" :label="$t('educational.employeeOptional')" clearable :disabled="!!editing"/></VCardText><VCardActions><VSpacer/><VBtn @click="dialog = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="primary" :disabled="!form.reason || form.amount <= 0 || !form.currency_id" @click="save">{{ $t(editing ? 'common.actions.update' : 'common.actions.submit') }}</VBtn></VCardActions></VCard></VDialog>
  <VDialog v-model="rejectDialog" max-width="500"><VCard :title="$t('educational.rejectTransaction')"><VCardText><VTextarea v-model="rejectionReason" :label="$t('educational.rejectionReason')" autofocus/></VCardText><VCardActions><VSpacer/><VBtn @click="rejectDialog = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="error" :disabled="!rejectionReason.trim()" @click="reject">{{ $t('common.actions.reject') }}</VBtn></VCardActions></VCard></VDialog>
  <ReceiptPrinter v-model="receiptOpen" :record-id="receiptLoanId" :loader="PendingTransactionApi.receipt" document="loan" />
</template>
