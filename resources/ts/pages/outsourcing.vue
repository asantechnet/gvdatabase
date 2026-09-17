<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import CompanyApi, { type Company } from '@/api/company'
import OutsourcingApi, { type OutsourcingAccrual, type OutsourcingSettlement } from '@/api/outsourcing'
import { canAccess } from '@/utils/authorization'

const { t } = useI18n()
const tr = (key: string, values?: Record<string, unknown>) => t(`outsourcing.${key}`, values)
const toast = useToast()
const userData = useCookie<{ roles?: string[] } | null>('userData')
const today = new Date().toISOString().slice(0, 10)
const loading = ref(false)
const activeTab = ref<'accruals' | 'settlements'>('accruals')
const companies = ref<Company[]>([])
const accruals = ref<OutsourcingAccrual[]>([])
const settlements = ref<OutsourcingSettlement[]>([])
const companyFilter = ref<number | null>(null)
const statusFilter = ref<string | null>(null)
const settlementDialog = ref(false)
const adjustmentDialog = ref(false)
const paymentDialog = ref(false)
const selectedSettlement = ref<OutsourcingSettlement | null>(null)
const selectedAccrualIds = ref<number[]>([])
const paymentDate = ref(today)
const form = ref({ company_id: null as number | null, base_currency_id: null as number | null, period_start: today.slice(0, 8) + '01', period_end: today, notes: '' })
const adjustmentForm = ref({ company_id: null as number | null, base_currency_id: null as number | null, effective_date: today, amount: 0, reason: '' })

const isSuperAdmin = computed(() => userData.value?.roles?.includes('super_admin') ?? false)
const companyName = (company?: { legal_name?: string; trade_name?: string | null }) => company?.trade_name || company?.legal_name || '—'
const money = (amount: number, currency?: { symbol?: string; code?: string; decimal_places?: number }) => `${currency?.symbol ?? ''}${Number(amount || 0).toFixed(currency?.decimal_places ?? 2)} ${currency?.code ?? ''}`.trim()
const statusColor = (status: string) => ({ pending: 'warning', draft: 'info', approved: 'primary', paid: 'success', cancelled: 'secondary', void: 'secondary' }[status] ?? 'secondary')
const statusLabel = (status: string) => tr(`statuses.${status}`)

const filteredAccruals = computed(() => accruals.value.filter(row => !companyFilter.value || row.company_id === companyFilter.value))
const pendingCurrencies = computed(() => {
  const seen = new Set<number>()
  return accruals.value.filter(row => row.status === 'pending' && (!form.value.company_id || row.company_id === form.value.company_id))
    .filter(row => !seen.has(row.base_currency_id) && seen.add(row.base_currency_id))
    .map(row => ({ id: row.base_currency_id, title: `${row.base_currency.symbol} ${row.base_currency.code}` }))
})
const ledgerCurrencies = computed(() => [...new Map(accruals.value.map(row => [row.base_currency_id, { id: row.base_currency_id, title: `${row.base_currency.symbol} ${row.base_currency.code}` }])).values()])
const candidates = computed(() => accruals.value.filter(row => row.status === 'pending' && row.company_id === form.value.company_id && row.base_currency_id === form.value.base_currency_id && row.earned_at >= form.value.period_start && row.earned_at <= form.value.period_end))
const selectedCandidates = computed(() => candidates.value.filter(row => selectedAccrualIds.value.includes(row.id)))
const settlementTotal = computed(() => selectedCandidates.value.reduce((sum, row) => sum + Number(row.outsourcing_base_amount || 0), 0))
const settlementCurrency = computed(() => candidates.value[0]?.base_currency)
const pendingTotals = computed(() => {
  const totals = new Map<number, { currency: OutsourcingAccrual['base_currency']; amount: number; count: number }>()
  filteredAccruals.value.filter(row => row.status === 'pending').forEach(row => {
    const current = totals.get(row.base_currency_id) || { currency: row.base_currency, amount: 0, count: 0 }
    current.amount += Number(row.outsourcing_base_amount || 0)
    current.count += 1
    totals.set(row.base_currency_id, current)
  })
  return [...totals.values()]
})

const selectAll = () => { selectedAccrualIds.value = candidates.value.map(row => row.id) }
watch([() => form.value.company_id, () => form.value.base_currency_id, () => form.value.period_start, () => form.value.period_end], selectAll)

const load = async () => {
  loading.value = true
  try {
    const [accrualRows, settlementRows, companyRows] = await Promise.all([
      OutsourcingApi.accruals(companyFilter.value ? { company_id: companyFilter.value } : undefined),
      OutsourcingApi.settlements({ ...(companyFilter.value ? { company_id: companyFilter.value } : {}), ...(statusFilter.value ? { status: statusFilter.value } : {}) }),
      CompanyApi.getAll(),
    ])
    accruals.value = accrualRows
    settlements.value = settlementRows
    companies.value = companyRows.filter(company => company.is_active)
  }
  catch (error: any) { toast.error(error?.response?.data?.message || tr('loadFailed')) }
  finally { loading.value = false }
}

const openSettlement = () => {
  const oldest = accruals.value.filter(row => row.status === 'pending' && (!companyFilter.value || row.company_id === companyFilter.value)).sort((a, b) => a.earned_at.localeCompare(b.earned_at))[0]
  form.value = { company_id: companyFilter.value, base_currency_id: null, period_start: oldest?.earned_at ?? today.slice(0, 8) + '01', period_end: today, notes: '' }
  selectedAccrualIds.value = []
  settlementDialog.value = true
}

const createSettlement = async () => {
  if (!form.value.company_id || !form.value.base_currency_id || !selectedAccrualIds.value.length || settlementTotal.value <= 0) return
  loading.value = true
  try {
    await OutsourcingApi.createSettlement({ ...form.value, company_id: form.value.company_id, base_currency_id: form.value.base_currency_id, accrual_ids: selectedAccrualIds.value, notes: form.value.notes || undefined })
    settlementDialog.value = false
    activeTab.value = 'settlements'
    toast.success(tr('settlementCreated'))
    await load()
  }
  catch (error: any) { toast.error(error?.response?.data?.message || tr('settlementCreateFailed')) }
  finally { loading.value = false }
}

const createAdjustment = async () => {
  if (!adjustmentForm.value.company_id || !adjustmentForm.value.base_currency_id || !adjustmentForm.value.amount || adjustmentForm.value.reason.trim().length < 5) return
  loading.value = true
  try {
    await OutsourcingApi.createAdjustment({ ...adjustmentForm.value, company_id: adjustmentForm.value.company_id, base_currency_id: adjustmentForm.value.base_currency_id })
    adjustmentDialog.value = false
    toast.success(tr('adjustmentAdded'))
    await load()
  }
  catch (error: any) { toast.error(error?.response?.data?.message || tr('adjustmentCreateFailed')) }
  finally { loading.value = false }
}
const approve = async (row: OutsourcingSettlement) => { try { await OutsourcingApi.approve(row.id); toast.success(tr('approvedSuccess', { number: row.settlement_number })); await load() } catch (error: any) { toast.error(error?.response?.data?.message || tr('approveFailed')) } }
const openPayment = (row: OutsourcingSettlement) => { selectedSettlement.value = row; paymentDate.value = today; paymentDialog.value = true }
const pay = async () => { if (!selectedSettlement.value) return; try { await OutsourcingApi.pay(selectedSettlement.value.id, paymentDate.value); paymentDialog.value = false; toast.success(tr('paidSuccess', { number: selectedSettlement.value.settlement_number })); await load() } catch (error: any) { toast.error(error?.response?.data?.message || tr('payFailed')) } }
const cancel = async (row: OutsourcingSettlement) => { try { await OutsourcingApi.cancel(row.id); toast.success(tr('cancelledSuccess', { number: row.settlement_number })); await load() } catch (error: any) { toast.error(error?.response?.data?.message || tr('cancelFailed')) } }

onMounted(load)
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-5">
      <div><h2 class="text-h4 font-weight-bold">{{ tr('title') }}</h2><p class="text-medium-emphasis mb-0">{{ tr('subtitle') }}</p></div>
      <div class="d-flex ga-2">
        <VBtn v-if="canAccess('manage_outsourcing_settlements')" variant="tonal" prepend-icon="tabler-adjustments" @click="adjustmentForm.company_id = companyFilter; adjustmentDialog = true">{{ tr('addAdjustment') }}</VBtn>
        <VBtn v-if="canAccess('manage_outsourcing_settlements')" prepend-icon="tabler-plus" @click="openSettlement">{{ tr('newSettlement') }}</VBtn>
      </div>
    </div>
    <VRow class="mb-3"><VCol v-for="row in pendingTotals" :key="row.currency.id" cols="12" sm="6" lg="3"><VCard><VCardText><div class="text-medium-emphasis mb-2">{{ tr('pendingPayable', { count: row.count }) }}</div><div class="text-h5 font-weight-bold">{{ money(row.amount, row.currency) }}</div></VCardText></VCard></VCol></VRow>
    <VCard>
      <VCardText><VRow align="center"><VCol cols="12" md="4"><VSelect v-model="companyFilter" :items="companies" :item-title="companyName" item-value="id" :label="tr('company')" clearable hide-details @update:model-value="load" /></VCol><VCol cols="12" md="4"><VSelect v-if="activeTab === 'settlements'" v-model="statusFilter" :items="['draft', 'approved', 'paid', 'cancelled'].map(value => ({ title: statusLabel(value), value }))" :label="tr('settlementStatus')" clearable hide-details @update:model-value="load" /></VCol><VCol cols="12" md="4" class="text-md-end"><VBtn variant="text" prepend-icon="tabler-refresh" :loading="loading" @click="load">{{ tr('refresh') }}</VBtn></VCol></VRow>
        <VTabs v-model="activeTab" class="mt-4"><VTab value="accruals">{{ tr('accruals') }}</VTab><VTab value="settlements">{{ tr('settlements') }}</VTab></VTabs>
      </VCardText>
      <VWindow v-model="activeTab">
        <VWindowItem value="accruals"><ResponsiveDataTable :loading="loading" :items="filteredAccruals" :headers="[{ title: tr('date'), key: 'earned_at' }, { title: tr('company'), key: 'company' }, { title: tr('service'), key: 'service' }, { title: tr('invoice'), key: 'invoice' }, { title: tr('eligibleRevenue'), key: 'eligible_base_amount' }, { title: tr('rate'), key: 'percentage' }, { title: tr('amount'), key: 'outsourcing_base_amount' }, { title: tr('status'), key: 'status' }]">
          <template #item.company="{ item }">{{ companyName(item.company) }}</template><template #item.service="{ item }">{{ item.service?.type || tr('manualAdjustment') }}</template><template #item.invoice="{ item }">{{ item.invoice?.invoice_number || '—' }}</template><template #item.eligible_base_amount="{ item }">{{ money(item.eligible_base_amount, item.base_currency) }}</template><template #item.percentage="{ item }">{{ item.percentage }}%</template><template #item.outsourcing_base_amount="{ item }"><strong>{{ money(item.outsourcing_base_amount, item.base_currency) }}</strong></template><template #item.status="{ item }"><VChip :color="statusColor(item.status)" size="small">{{ statusLabel(item.status) }}</VChip></template>
        </ResponsiveDataTable></VWindowItem>
        <VWindowItem value="settlements"><ResponsiveDataTable :loading="loading" :items="settlements" :headers="[{ title: tr('settlement'), key: 'settlement_number' }, { title: tr('company'), key: 'company' }, { title: tr('period'), key: 'period' }, { title: tr('accruals'), key: 'accruals_count' }, { title: tr('amount'), key: 'gross_base_amount' }, { title: tr('status'), key: 'status' }, { title: tr('paymentDate'), key: 'payment_date' }, { title: tr('actions'), key: 'actions', sortable: false }]">
          <template #item.company="{ item }">{{ companyName(item.company) }}</template><template #item.period="{ item }">{{ item.period_start }} — {{ item.period_end }}</template><template #item.gross_base_amount="{ item }"><strong>{{ money(item.gross_base_amount, item.base_currency) }}</strong></template><template #item.status="{ item }"><VChip :color="statusColor(item.status)" size="small">{{ statusLabel(item.status) }}</VChip></template><template #item.actions="{ item }"><div class="d-flex ga-1"><VBtn v-if="item.status === 'draft' && canAccess('approve_outsourcing')" size="small" variant="tonal" @click="approve(item)">{{ tr('approve') }}</VBtn><VBtn v-if="(item.status === 'approved' || (isSuperAdmin && item.status === 'draft')) && canAccess('pay_outsourcing')" size="small" color="success" variant="tonal" @click="openPayment(item)">{{ tr('pay') }}</VBtn><VBtn v-if="['draft', 'approved'].includes(item.status) && canAccess('manage_outsourcing_settlements')" size="small" color="error" variant="text" @click="cancel(item)">{{ tr('cancel') }}</VBtn></div></template>
        </ResponsiveDataTable></VWindowItem>
      </VWindow>
    </VCard>

    <VDialog v-model="settlementDialog" max-width="900"><VCard :title="tr('createSettlement')"><VCardText><VRow><VCol cols="12"><VSelect v-model="form.company_id" :items="companies" :item-title="companyName" item-value="id" :label="tr('company')" /></VCol><VCol cols="12"><VSelect v-model="form.base_currency_id" :items="pendingCurrencies" item-title="title" item-value="id" :label="tr('accrualCurrency')" /></VCol><VCol cols="12" sm="6"><AppDateTimePicker v-model="form.period_start" :label="tr('periodStart')" :config="{ dateFormat: 'Y-m-d' }" class="w-100" /></VCol><VCol cols="12" sm="6"><AppDateTimePicker v-model="form.period_end" :label="tr('periodEnd')" :config="{ dateFormat: 'Y-m-d' }" class="w-100" /></VCol><VCol cols="12"><VTextarea v-model="form.notes" :label="tr('notes')" rows="2" /></VCol><VCol cols="12"><VAlert :type="settlementTotal > 0 ? 'info' : 'warning'" variant="tonal">{{ tr('settlementPreview', { count: selectedCandidates.length, amount: money(settlementTotal, settlementCurrency) }) }}</VAlert></VCol><VCol cols="12"><div class="d-flex align-center justify-space-between mb-2"><span class="text-subtitle-2">{{ tr('selectAccruals') }}</span><div><VBtn size="small" variant="text" @click="selectAll">{{ tr('selectAll') }}</VBtn><VBtn size="small" variant="text" @click="selectedAccrualIds = []">{{ tr('clearSelection') }}</VBtn></div></div><ResponsiveDataTable v-model="selectedAccrualIds" :items="candidates" :headers="[{ title: tr('date'), key: 'earned_at' }, { title: tr('invoice'), key: 'invoice' }, { title: tr('service'), key: 'service' }, { title: tr('amount'), key: 'outsourcing_base_amount' }]" item-value="id" show-select density="compact"><template #no-data>{{ tr('noMatchingAccruals') }}</template><template #item.invoice="{ item }">{{ item.invoice?.invoice_number || '—' }}</template><template #item.service="{ item }">{{ item.service?.type || tr('manualAdjustment') }}</template><template #item.outsourcing_base_amount="{ item }">{{ money(item.outsourcing_base_amount, item.base_currency) }}</template></ResponsiveDataTable></VCol></VRow></VCardText><VCardActions><VSpacer /><VBtn variant="text" @click="settlementDialog = false">{{ tr('cancel') }}</VBtn><VBtn :loading="loading" :disabled="!selectedCandidates.length || settlementTotal <= 0" @click="createSettlement">{{ isSuperAdmin ? tr('createSettlement') : tr('createDraft') }}</VBtn></VCardActions></VCard></VDialog>
    <VDialog v-model="paymentDialog" max-width="480"><VCard :title="tr('paySettlement')"><VCardText><VAlert type="info" variant="tonal" class="mb-4">{{ tr('paymentWarning') }}</VAlert><AppDateTimePicker v-model="paymentDate" :label="tr('paymentDate')" :config="{ dateFormat: 'Y-m-d' }" class="w-100" /></VCardText><VCardActions><VSpacer /><VBtn variant="text" @click="paymentDialog = false">{{ tr('cancel') }}</VBtn><VBtn color="success" @click="pay">{{ tr('recordPayment') }}</VBtn></VCardActions></VCard></VDialog>
    <VDialog v-model="adjustmentDialog" max-width="600"><VCard :title="tr('addAdjustment')"><VCardText><VAlert type="warning" variant="tonal" class="mb-4">{{ tr('adjustmentWarning') }}</VAlert><VRow><VCol cols="12"><VSelect v-model="adjustmentForm.company_id" :items="companies" :item-title="companyName" item-value="id" :label="tr('company')" /></VCol><VCol cols="12" sm="6"><VLabel class="mb-1">{{ tr('currency') }}</VLabel><VSelect v-model="adjustmentForm.base_currency_id" :items="ledgerCurrencies" item-title="title" item-value="id" hide-details /></VCol><VCol cols="12" sm="6"><AppDateTimePicker v-model="adjustmentForm.effective_date" :label="tr('effectiveDate')" :config="{ dateFormat: 'Y-m-d' }" class="w-100" /></VCol><VCol cols="12"><VTextField v-model.number="adjustmentForm.amount" type="number" step="0.01" :label="tr('signedAmount')" :hint="tr('signedAmountHint')" persistent-hint /></VCol><VCol cols="12"><VTextarea v-model="adjustmentForm.reason" :label="tr('reason')" rows="3" /></VCol></VRow></VCardText><VCardActions><VSpacer /><VBtn variant="text" @click="adjustmentDialog = false">{{ tr('cancel') }}</VBtn><VBtn :loading="loading" @click="createAdjustment">{{ tr('addToLedger') }}</VBtn></VCardActions></VCard></VDialog>
  </div>
</template>
