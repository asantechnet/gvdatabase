<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import PendingTransactionApi, { type PendingTransaction, type PendingTransactionInput, type PendingTransactionOptions, type PendingTransactionStatus, type PendingTransactionType } from '@/api/pendingTransactions'
import { canAccess } from '@/utils/authorization'
import { translateBackendValue } from '@/utils/localization'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ReceiptPrinter from '@/components/income/ReceiptPrinter.vue'
import ResponsiveDataTableServer from '@/components/responsive-data-table/ResponsiveDataTableServer.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'

const toast = useToast()
const { t, d, n } = useI18n()
const userData = useCookie<{ id?: number } | null>('userData')
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
const perPage = ref(25)
const total = ref(0)
const options = ref<PendingTransactionOptions>({ currencies: [], employees: [], applicants: [] })
const searchQuery = ref('')
const filterStatus = ref<PendingTransactionStatus | null>(null)
const filterType = ref<PendingTransactionType | null>(null)
const form = ref<PendingTransactionInput>({ transaction_type: 'income', reason: '', amount: 0, currency_id: null, applicant_id: null, employee_id: null })

const canCreate = computed(() => canAccess('create_pending_transactions'))
const canApprove = computed(() => canAccess('approve_pending_transactions'))
const canPost = computed(() => canAccess('post_pending_transactions'))
const currentUserId = computed(() => Number(userData.value?.id ?? 0))
const activeFilterCount = computed(() =>
  Number(Boolean(searchQuery.value.trim())) + Number(Boolean(filterStatus.value)) + Number(Boolean(filterType.value)),
)
const statusOptions = computed(() => ([
  'submitted', 'under_review', 'approved', 'rejected', 'posted', 'draft',
] as PendingTransactionStatus[]).map(value => ({ title: translateBackendValue(value), value })))
const typeOptions = computed(() => (['income', 'expense', 'salary'] as PendingTransactionType[])
  .map(value => ({ title: translateBackendValue(value), value })))
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

let searchTimer: ReturnType<typeof setTimeout> | null = null

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

// The server owns this rule; showing the action to its own requester only
// produces a guaranteed failure. The API is still authoritative.
const isOwnRequest = (row: PendingTransaction) => Number(row.requested_by) === currentUserId.value
const showsSelfApprovalHint = (row: PendingTransaction) => isOwnRequest(row)
  && ((canApprove.value && ['submitted', 'under_review', 'approved'].includes(row.approval_status))
    || (canPost.value && row.approval_status === 'approved'))

async function load() {
  loading.value = true
  try {
    const transactions = await PendingTransactionApi.list(page.value, perPage.value, {
      search: searchQuery.value.trim() || undefined,
      status: filterStatus.value,
      transaction_type: filterType.value,
    })

    rows.value = transactions.data
    page.value = transactions.current_page
    perPage.value = transactions.per_page
    total.value = transactions.total
  }
  catch {
    // ApiErrorNotifier owns the single API failure message.
    rows.value = []
    total.value = 0
  }
  finally { loading.value = false }
}

async function loadOptions() {
  if (!canCreate.value)
    return

  try {
    options.value = await PendingTransactionApi.options()
  }
  catch {
    // The notifier reports it; the list can still be used read-only.
  }
}

function updateTableOptions(next: { page: number; itemsPerPage: number }) {
  const sizeChanged = next.itemsPerPage !== perPage.value
  if (!sizeChanged && next.page === page.value)
    return

  perPage.value = next.itemsPerPage
  page.value = sizeChanged ? 1 : next.page
  void load()
}

// A filter change always starts from the first page.
function reload() {
  page.value = 1
  void load()
}

function queueReload() {
  if (searchTimer)
    clearTimeout(searchTimer)
  searchTimer = setTimeout(reload, 300)
}

function resetFilters() {
  if (searchTimer)
    clearTimeout(searchTimer)
  searchQuery.value = ''
  filterStatus.value = null
  filterType.value = null
  reload()
}

function openCreate() {
  editing.value = null
  form.value = { transaction_type: 'income', reason: '', amount: 0, currency_id: options.value.currencies.find(currency => currency.is_base)?.id ?? null, applicant_id: null, employee_id: null }
  dialog.value = true
}

function openEdit(row: PendingTransaction) {
  editing.value = row
  form.value = { transaction_type: row.transaction_type, reason: row.reason, amount: Number(row.amount), currency_id: row.currency_id, applicant_id: row.applicant_id, employee_id: row.employee_id }
  dialog.value = true
}

async function save() {
  try {
    const wasEditing = Boolean(editing.value)
    if (wasEditing)
      await PendingTransactionApi.update(editing.value!.id, form.value)
    else
      await PendingTransactionApi.create(form.value)

    dialog.value = false
    await load()
    toast.success(t(wasEditing ? 'educational.pendingUi.saved' : 'educational.pendingUi.submitted'))
  }
  catch {
    // Keep the dialog open. ApiErrorNotifier owns the single failure message.
  }
}

async function transition(action: 'review' | 'approve' | 'post', row: PendingTransaction) {
  try {
    await PendingTransactionApi[action](row.id)
    await load()
    toast.success(t('educational.pendingUi.transitioned', {
      status: translateBackendValue(action === 'review' ? 'under_review' : action === 'approve' ? 'approved' : 'posted'),
    }))
  }
  catch {
    // ApiErrorNotifier owns the single failure message.
  }
}

function openReject(row: PendingTransaction) {
  selected.value = row
  rejectionReason.value = ''
  rejectDialog.value = true
}

function openLoanReceipt(row: PendingTransaction) {
  receiptLoanId.value = row.id
  receiptOpen.value = true
}

async function reject() {
  if (!selected.value || !rejectionReason.value.trim())
    return

  try {
    await PendingTransactionApi.reject(selected.value.id, rejectionReason.value)
    rejectDialog.value = false
    await load()
    toast.success(t('educational.pendingUi.rejected'))
  }
  catch {
    // ApiErrorNotifier owns the single failure message.
  }
}

onMounted(() => {
  void loadOptions()
  void load()
})

onBeforeUnmount(() => {
  if (searchTimer)
    clearTimeout(searchTimer)
})
</script>

<template>
  <div>
    <ErpPageHeader
      :title="$t('common.navigation.paymentRequests')"
      section="dashboard"
      :breadcrumbs="false"
    >
      <template #actions>
        <VBtn v-if="canCreate" prepend-icon="tabler-plus" color="primary" @click="openCreate">
          {{ $t('educational.paymentRequestsNew') }}
        </VBtn>
      </template>
    </ErpPageHeader>
    <p class="text-body-2 text-medium-emphasis mb-6">
      {{ $t('educational.paymentRequestsSubtitle') }}
    </p>

    <VCard class="mb-4" elevation="0">
      <VCardText class="d-flex flex-wrap align-center ga-3">
        <VTextField
          v-model="searchQuery"
          class="flex-grow-1"
          style="min-inline-size: 220px"
          prepend-inner-icon="tabler-search"
          :placeholder="$t('educational.pendingUi.searchReason')"
          density="compact"
          clearable
          hide-details
          @update:model-value="queueReload"
        />
        <VSelect
          v-model="filterStatus"
          :items="statusOptions"
          :label="$t('educational.status')"
          density="compact"
          clearable
          hide-details
          style="max-inline-size: 210px"
          @update:model-value="reload"
        />
        <VSelect
          v-model="filterType"
          :items="typeOptions"
          :label="$t('educational.transactionType')"
          density="compact"
          clearable
          hide-details
          style="max-inline-size: 210px"
          @update:model-value="reload"
        />
        <VChip v-if="activeFilterCount" size="small" color="primary" variant="tonal">
          {{ $t('educational.activeFilters', { count: activeFilterCount }) }}
        </VChip>
        <VBtn
          v-if="activeFilterCount"
          variant="text"
          size="small"
          prepend-icon="tabler-filter-off"
          @click="resetFilters"
        >
          {{ $t('educational.clear') }}
        </VBtn>
      </VCardText>
    </VCard>

    <VCard>
      <ResponsiveDataTableServer
        :loading="loading"
        :items="rows"
        :items-length="total"
        :page="page"
        :items-per-page="perPage"
        :items-per-page-options="[10, 25, 50, 100]"
        :page-sizes="[10, 25, 50, 100]"
        pagination
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
        @update:options="updateTableOptions"
      >
        <template #loading>
          <TableLoadingSkeleton :columns="6" :label="$t('common.messages.loading')" />
        </template>
        <template #no-data>
          <TableEmptyState
            icon="tabler-receipt-off"
            :description="$t(activeFilterCount ? 'educational.adjustFilters' : 'educational.noTransactions')"
          />
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
            <VTooltip v-if="canPrint(item)" location="top" :text="$t(printLabelKey(item))">
              <template #activator="{ props: tooltipProps }">
                <VBtn v-bind="tooltipProps" icon size="small" variant="text" color="primary" :aria-label="$t(printLabelKey(item))" @click="openLoanReceipt(item)">
                  <VIcon icon="tabler-printer" size="18" />
                </VBtn>
              </template>
            </VTooltip>
            <VTooltip v-if="canCreate && ['draft', 'submitted'].includes(item.approval_status)" location="top" :text="$t('educational.pendingUi.actions.edit')">
              <template #activator="{ props: tooltipProps }">
                <VBtn v-bind="tooltipProps" icon size="small" variant="text" :aria-label="$t('educational.pendingUi.actions.edit')" @click="openEdit(item)">
                  <VIcon icon="tabler-pencil" size="18" />
                </VBtn>
              </template>
            </VTooltip>
            <VTooltip v-if="canApprove && !isOwnRequest(item) && item.approval_status === 'submitted'" location="top" :text="$t('educational.pendingUi.actions.review')">
              <template #activator="{ props: tooltipProps }">
                <VBtn v-bind="tooltipProps" icon size="small" variant="text" color="info" :aria-label="$t('educational.pendingUi.actions.review')" @click="transition('review', item)">
                  <VIcon icon="tabler-eye" size="18" />
                </VBtn>
              </template>
            </VTooltip>
            <VTooltip v-if="canApprove && !isOwnRequest(item) && ['submitted', 'under_review'].includes(item.approval_status)" location="top" :text="$t('educational.pendingUi.actions.approve')">
              <template #activator="{ props: tooltipProps }">
                <VBtn v-bind="tooltipProps" icon size="small" variant="text" color="success" :aria-label="$t('educational.pendingUi.actions.approve')" @click="transition('approve', item)">
                  <VIcon icon="tabler-check" size="18" />
                </VBtn>
              </template>
            </VTooltip>
            <VTooltip v-if="canApprove && !isOwnRequest(item) && ['submitted', 'under_review'].includes(item.approval_status)" location="top" :text="$t('educational.pendingUi.actions.reject')">
              <template #activator="{ props: tooltipProps }">
                <VBtn v-bind="tooltipProps" icon size="small" variant="text" color="error" :aria-label="$t('educational.pendingUi.actions.reject')" @click="openReject(item)">
                  <VIcon icon="tabler-x" size="18" />
                </VBtn>
              </template>
            </VTooltip>
            <VTooltip v-if="canPost && !isOwnRequest(item) && item.approval_status === 'approved'" location="top" :text="$t('educational.pendingUi.actions.post')">
              <template #activator="{ props: tooltipProps }">
                <VBtn v-bind="tooltipProps" icon size="small" variant="text" color="primary" :aria-label="$t('educational.pendingUi.actions.post')" @click="transition('post', item)">
                  <VIcon icon="tabler-send" size="18" />
                </VBtn>
              </template>
            </VTooltip>
            <VTooltip v-if="showsSelfApprovalHint(item)" location="top" :text="$t('educational.pendingUi.selfApproval')">
              <template #activator="{ props: tooltipProps }">
                <VIcon v-bind="tooltipProps" icon="tabler-lock" size="18" class="text-medium-emphasis" :aria-label="$t('educational.pendingUi.selfApproval')" />
              </template>
            </VTooltip>
          </div>
        </template>
      </ResponsiveDataTableServer>
    </VCard>
  </div>

  <VDialog v-model="dialog" max-width="600">
    <VCard :title="$t(editing ? 'educational.editPending' : 'educational.newPending')">
      <VCardText>
        <VSelect v-model="form.transaction_type" :items="['income', 'expense'].map(value => ({ value, title: translateBackendValue(value) }))" :label="$t('educational.transactionType')" class="mb-3" :disabled="!!editing" />
        <VTextarea v-model="form.reason" :label="$t('educational.reason')" class="mb-3" />
        <VTextField v-model.number="form.amount" type="number" min="0.000001" :label="$t('educational.amount')" class="mb-3" />
        <VSelect v-model="form.currency_id" :items="currencyOptions" :label="$t('common.fields.currency')" class="mb-3" :disabled="!!editing" />
        <VSelect v-model="form.applicant_id" :items="applicantOptions" :label="$t('educational.applicantOptional')" clearable class="mb-3" :disabled="!!editing" />
        <VSelect v-if="showsEmployeeField" v-model="form.employee_id" :items="employeeOptions" :label="$t('educational.employeeOptional')" clearable :disabled="!!editing" />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="dialog = false">{{ $t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :disabled="!form.reason || form.amount <= 0 || !form.currency_id" @click="save">
          {{ $t(editing ? 'common.actions.update' : 'common.actions.submit') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <VDialog v-model="rejectDialog" max-width="500">
    <VCard :title="$t('educational.rejectTransaction')">
      <VCardText><VTextarea v-model="rejectionReason" :label="$t('educational.rejectionReason')" autofocus /></VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="rejectDialog = false">{{ $t('common.actions.cancel') }}</VBtn>
        <VBtn color="error" :disabled="!rejectionReason.trim()" @click="reject">{{ $t('common.actions.reject') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <ReceiptPrinter v-model="receiptOpen" :record-id="receiptLoanId" :loader="PendingTransactionApi.receipt" document="loan" />
</template>
