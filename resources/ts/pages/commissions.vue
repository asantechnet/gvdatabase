<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import CommissionApi, { type CommissionAccrual, type CommissionSettlement, type CommissionSummary } from '@/api/commission'
import EmployeeApi, { type Employee } from '@/api/employee'
import { canAccess } from '@/utils/authorization'

const toast = useToast()
const loading = ref(false)
const activeTab = ref<'accruals' | 'settlements'>('accruals')
const accruals = ref<CommissionAccrual[]>([])
const settlements = ref<CommissionSettlement[]>([])
const summary = ref<CommissionSummary[]>([])
const employees = ref<Employee[]>([])
const employeeFilter = ref<number | null>(null)
const statusFilter = ref<string | null>(null)
const dialog = ref(false)
const adjustmentDialog = ref(false)
const paymentDialog = ref(false)
const selectedSettlement = ref<CommissionSettlement | null>(null)
const paymentDate = ref(new Date().toISOString().slice(0, 10))

const form = ref({
  employee_id: null as number | null,
  base_currency_id: null as number | null,
  period_start: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
  period_end: new Date().toISOString().slice(0, 10),
  notes: '',
})

const adjustmentForm = ref({ employee_id: null as number | null, base_currency_id: null as number | null, effective_date: new Date().toISOString().slice(0, 10), amount: 0, reason: '' })

const commissionEmployees = computed(() => employees.value.filter(employee => employee.employment_type === 'commission'))

const pendingCurrencies = computed(() => {
  const ids = new Set<number>()

  return accruals.value.filter(row => row.status === 'pending' && (!form.value.employee_id || row.employee_id === form.value.employee_id))
    .filter(row => !ids.has(row.base_currency_id) && ids.add(row.base_currency_id))
    .map(row => ({ id: row.base_currency_id, title: `${row.base_currency.symbol} ${row.base_currency.code}` }))
})

const summaryCards = computed(() => summary.value.filter(row => row.status !== 'void'))

const ledgerCurrencies = computed(() => {
  const rows = [...summary.value.map(row => row.currency), ...accruals.value.map(row => row.base_currency)]

  return [...new Map(rows.filter(Boolean).map(currency => [currency.id, { id: currency.id, title: `${currency.symbol} ${currency.code}` }])).values()]
})

const money = (amount: number, currency?: { symbol?: string; code?: string; decimal_places?: number }) =>
  `${currency?.symbol ?? ''}${Number(amount || 0).toFixed(currency?.decimal_places ?? 2)} ${currency?.code ?? ''}`.trim()

const statusColor = (status: string) => ({ pending: 'warning', draft: 'info', approved: 'primary', paid: 'success', cancelled: 'secondary', void: 'secondary' }[status] ?? 'secondary')

const load = async () => {
  loading.value = true
  try {
    const params = employeeFilter.value ? { employee_id: employeeFilter.value } : undefined

    const [accrualRows, settlementRows, summaryRows, employeeRows] = await Promise.all([
      CommissionApi.accruals(params),
      CommissionApi.settlements({ ...(params ?? {}), ...(statusFilter.value ? { status: statusFilter.value } : {}) }),
      CommissionApi.summary(params),
      EmployeeApi.getOptions(),
    ])

    accruals.value = accrualRows
    settlements.value = settlementRows
    summary.value = summaryRows
    employees.value = employeeRows
  }
  catch (error: any) {
    toast.error(error?.response?.data?.message || 'Unable to load commission records.')
  }
  finally {
    loading.value = false
  }
}

const openSettlement = () => {
  form.value.employee_id = employeeFilter.value
  form.value.base_currency_id = null
  form.value.notes = ''
  dialog.value = true
}

const createSettlement = async () => {
  if (!form.value.employee_id || !form.value.base_currency_id)
    return
  loading.value = true
  try {
    await CommissionApi.createSettlement({
      employee_id: form.value.employee_id,
      base_currency_id: form.value.base_currency_id,
      period_start: form.value.period_start,
      period_end: form.value.period_end,
      notes: form.value.notes || undefined,
    })
    dialog.value = false
    toast.success('Draft commission settlement created.')
    await load()
    activeTab.value = 'settlements'
  }
  catch (error: any) {
    toast.error(error?.response?.data?.message || 'Unable to create the settlement.')
  }
  finally { loading.value = false }
}

const createAdjustment = async () => {
  if (!adjustmentForm.value.employee_id || !adjustmentForm.value.base_currency_id || !adjustmentForm.value.amount || adjustmentForm.value.reason.trim().length < 5)
    return
  loading.value = true
  try {
    await CommissionApi.createAdjustment({ ...adjustmentForm.value, employee_id: adjustmentForm.value.employee_id, base_currency_id: adjustmentForm.value.base_currency_id })
    adjustmentDialog.value = false
    toast.success('Commission adjustment added to the pending ledger.')
    await load()
  }
  catch (error: any) { toast.error(error?.response?.data?.message || 'Unable to create adjustment.') }
  finally { loading.value = false }
}

const approve = async (row: CommissionSettlement) => {
  try {
    await CommissionApi.approve(row.id)
    toast.success(`${row.settlement_number} approved.`)
    await load()
  }
  catch (error: any) { toast.error(error?.response?.data?.message || 'Unable to approve settlement.') }
}

const openPayment = (row: CommissionSettlement) => {
  selectedSettlement.value = row
  paymentDate.value = new Date().toISOString().slice(0, 10)
  paymentDialog.value = true
}

const pay = async () => {
  if (!selectedSettlement.value)
    return
  try {
    await CommissionApi.pay(selectedSettlement.value.id, paymentDate.value)
    paymentDialog.value = false
    toast.success(`${selectedSettlement.value.settlement_number} paid and posted to expenses.`)
    await load()
  }
  catch (error: any) { toast.error(error?.response?.data?.message || 'Unable to pay settlement.') }
}

const cancel = async (row: CommissionSettlement) => {
  try {
    await CommissionApi.cancel(row.id)
    toast.success(`${row.settlement_number} cancelled; its accruals are pending again.`)
    await load()
  }
  catch (error: any) { toast.error(error?.response?.data?.message || 'Unable to cancel settlement.') }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-5">
      <div>
        <h2 class="text-h4 font-weight-bold">
          Commission ledger
        </h2>
        <p class="text-medium-emphasis mb-0">
          Review invoice-payment accruals, approve settlements, and post paid commissions to expenses.
        </p>
      </div>
      <div class="d-flex ga-2">
        <VBtn
          v-if="canAccess('manage_commission_settlements')"
          variant="tonal"
          prepend-icon="tabler-adjustments"
          @click="adjustmentForm.employee_id = employeeFilter; adjustmentDialog = true"
        >
          Add adjustment
        </VBtn>
        <VBtn
          v-if="canAccess('manage_commission_settlements')"
          prepend-icon="tabler-plus"
          @click="openSettlement"
        >
          New settlement
        </VBtn>
      </div>
    </div>

    <VRow class="mb-3">
      <VCol
        v-for="row in summaryCards"
        :key="`${row.base_currency_id}-${row.status}`"
        cols="12"
        sm="6"
        lg="3"
      >
        <VCard>
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-capitalize text-medium-emphasis">{{ row.status }}</span><VChip
                :color="statusColor(row.status)"
                size="small"
              >
                {{ row.count }} accruals
              </VChip>
            </div>
            <div class="text-h5 font-weight-bold">
              {{ money(row.amount, row.currency) }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard>
      <VCardText>
        <VRow align="center">
          <VCol
            cols="12"
            md="4"
          >
            <VSelect
              v-model="employeeFilter"
              :items="commissionEmployees"
              item-title="name"
              item-value="id"
              label="Employee"
              clearable
              hide-details
              @update:model-value="load"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <VSelect
              v-if="activeTab === 'settlements'"
              v-model="statusFilter"
              :items="['draft', 'approved', 'paid', 'cancelled']"
              label="Settlement status"
              clearable
              hide-details
              @update:model-value="load"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
            class="text-md-end"
          >
            <VBtn
              variant="text"
              prepend-icon="tabler-refresh"
              :loading="loading"
              @click="load"
            >
              Refresh
            </VBtn>
          </VCol>
        </VRow>
        <VTabs
          v-model="activeTab"
          class="mt-4"
        >
          <VTab value="accruals">
            Accruals
          </VTab><VTab value="settlements">
            Settlements
          </VTab>
        </VTabs>
      </VCardText>

      <VWindow v-model="activeTab">
        <VWindowItem value="accruals">
          <ResponsiveDataTable
            :loading="loading"
            :items="accruals"
            :headers="[
              { title: 'Date', key: 'earned_at' }, { title: 'Type', key: 'entry_type' }, { title: 'Employee', key: 'employee' },
              { title: 'Invoice', key: 'invoice_number' }, { title: 'Service', key: 'service' },
              { title: 'Eligible revenue', key: 'eligible_base_amount' }, { title: 'Rate', key: 'percentage' },
              { title: 'Commission', key: 'commission_base_amount' }, { title: 'Status', key: 'status' },
            ]"
          >
            <template #item.eligible_base_amount="{ item }">
              {{ money(item.eligible_base_amount, item.base_currency) }}
            </template>
            <template #item.entry_type="{ item }">
              <VChip
                size="small"
                variant="tonal"
              >
                {{ item.entry_type }}
              </VChip><div
                v-if="item.adjustment_reason"
                class="text-caption text-medium-emphasis"
              >
                {{ item.adjustment_reason }}
              </div>
            </template>
            <template #item.percentage="{ item }">
              {{ item.percentage }}%
            </template>
            <template #item.commission_base_amount="{ item }">
              <strong>{{ money(item.commission_base_amount, item.base_currency) }}</strong>
            </template>
            <template #item.status="{ item }">
              <VChip
                :color="statusColor(item.status)"
                size="small"
                class="text-capitalize"
              >
                {{ item.status }}
              </VChip>
            </template>
          </ResponsiveDataTable>
        </VWindowItem>

        <VWindowItem value="settlements">
          <ResponsiveDataTable
            :loading="loading"
            :items="settlements"
            :headers="[
              { title: 'Settlement', key: 'settlement_number' }, { title: 'Employee', key: 'employee' },
              { title: 'Period', key: 'period' }, { title: 'Accruals', key: 'accrual_count' },
              { title: 'Amount', key: 'gross_base_amount' }, { title: 'Status', key: 'status' },
              { title: 'Payment date', key: 'payment_date' }, { title: 'Actions', key: 'actions', sortable: false },
            ]"
          >
            <template #item.period="{ item }">
              {{ item.period_start }} — {{ item.period_end }}
            </template>
            <template #item.gross_base_amount="{ item }">
              <strong>{{ money(item.gross_base_amount, item.base_currency) }}</strong>
            </template>
            <template #item.status="{ item }">
              <VChip
                :color="statusColor(item.status)"
                size="small"
                class="text-capitalize"
              >
                {{ item.status }}
              </VChip>
            </template>
            <template #item.actions="{ item }">
              <div class="d-flex ga-1">
                <VBtn
                  v-if="item.status === 'draft' && canAccess('approve_commissions')"
                  size="small"
                  variant="tonal"
                  @click="approve(item)"
                >
                  Approve
                </VBtn>
                <VBtn
                  v-if="item.status === 'approved' && canAccess('pay_commissions')"
                  size="small"
                  color="success"
                  variant="tonal"
                  @click="openPayment(item)"
                >
                  Pay
                </VBtn>
                <VBtn
                  v-if="['draft', 'approved'].includes(item.status) && canAccess('manage_commission_settlements')"
                  size="small"
                  color="error"
                  variant="text"
                  @click="cancel(item)"
                >
                  Cancel
                </VBtn>
              </div>
            </template>
          </ResponsiveDataTable>
        </VWindowItem>
      </VWindow>
    </VCard>

    <VDialog
      v-model="dialog"
      max-width="650"
    >
      <VCard title="Create commission settlement">
        <VCardText>
          <VRow>
            <VCol cols="12">
              <VSelect
                v-model="form.employee_id"
                :items="commissionEmployees"
                item-title="name"
                item-value="id"
                label="Commission employee"
              />
            </VCol>
            <VCol cols="12">
              <VSelect
                v-model="form.base_currency_id"
                :items="pendingCurrencies"
                item-title="title"
                item-value="id"
                label="Accrual currency"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.period_start"
                type="date"
                label="Period start"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="form.period_end"
                type="date"
                label="Period end"
              />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="form.notes"
                label="Notes"
                rows="2"
              />
            </VCol>
          </VRow>
        </VCardText><VCardActions>
          <VSpacer /><VBtn
            variant="text"
            @click="dialog = false"
          >
            Cancel
          </VBtn><VBtn
            :loading="loading"
            @click="createSettlement"
          >
            Create draft
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="paymentDialog"
      max-width="480"
    >
      <VCard title="Pay commission settlement">
        <VCardText>
          <VAlert
            type="info"
            variant="tonal"
            class="mb-4"
          >
            Payment creates a protected commission expense and permanently closes the included accruals.
          </VAlert>
          <VTextField
            v-model="paymentDate"
            type="date"
            label="Payment date"
          />
        </VCardText><VCardActions>
          <VSpacer /><VBtn
            variant="text"
            @click="paymentDialog = false"
          >
            Cancel
          </VBtn><VBtn
            color="success"
            @click="pay"
          >
            Record payment
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="adjustmentDialog"
      max-width="600"
    >
      <VCard title="Add commission adjustment">
        <VCardText>
          <VAlert
            type="warning"
            variant="tonal"
            class="mb-4"
          >
            Use a negative amount for a clawback or correction, and a positive amount for an approved manual earning. The reason is retained in the audit ledger.
          </VAlert>
          <VRow>
            <VCol cols="12">
              <VSelect
                v-model="adjustmentForm.employee_id"
                :items="commissionEmployees"
                item-title="name"
                item-value="id"
                label="Commission employee"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <VSelect
                v-model="adjustmentForm.base_currency_id"
                :items="ledgerCurrencies"
                item-title="title"
                item-value="id"
                label="Currency"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="adjustmentForm.effective_date"
                type="date"
                label="Effective date"
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model.number="adjustmentForm.amount"
                type="number"
                step="0.01"
                label="Signed amount"
                hint="Negative for clawback; positive for additional earning"
                persistent-hint
              />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="adjustmentForm.reason"
                label="Reason"
                rows="3"
              />
            </VCol>
          </VRow>
        </VCardText><VCardActions>
          <VSpacer /><VBtn
            variant="text"
            @click="adjustmentDialog = false"
          >
            Cancel
          </VBtn><VBtn
            :loading="loading"
            @click="createAdjustment"
          >
            Add to ledger
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
