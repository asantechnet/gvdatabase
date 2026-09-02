<script setup lang="ts">
import { computed } from 'vue'

type TableRow = Record<string, unknown>
type KpiMetric = { label: string, value: string | number, icon: string, tone: 'primary' | 'status' | 'attention' | 'insight' }
type BaseCurrency = { code?: string | null, symbol?: string | null, decimalPlaces?: number | null }

const props = withDefaults(defineProps<{
  items: TableRow[]
  title: string
  description: string
  icon?: string
  statusKey?: string
  baseCurrency?: BaseCurrency | null
}>(), {
  icon: 'tabler-table',
  statusKey: 'status',
})

const { t } = useI18n()
const pageTranslationKeys: Record<string, string> = {
  'Visitors': 'visitors',
  'Asset inventory': 'assetInventory',
  'Staff salaries': 'staffSalaries',
  'Staff directory': 'staffDirectory',
  'Users': 'users',
  'Positions': 'positions',
  'Payment details': 'paymentDetails',
  'Leave': 'paidTimeOff',
  'Invoice options': 'invoiceOptions',
  'HR options': 'hrOptions',
  'Finance options': 'financeOptions',
  'Contract options': 'contractOptions',
  'Services': 'services',
  'Invoices': 'invoices',
  'Companies': 'companies',
  'Cancelled contracts': 'cancelledContracts',
  'Customer directory': 'customerDirectory',
  'Contracts': 'contracts',
  'Policy library': 'policyLibrary',
}
const pageTranslationKey = computed(() => pageTranslationKeys[props.title] ?? null)
const displayTitle = computed(() => pageTranslationKey.value
  ? t(`components.dataTablePageKpis.pages.${pageTranslationKey.value}.title`)
  : props.title)
const displayDescription = computed(() => pageTranslationKey.value
  ? t(`components.dataTablePageKpis.pages.${pageTranslationKey.value}.description`)
  : props.description)
const total = computed(() => props.items.length)
const activeStatuses = ['active', 'paid', 'approved', 'completed', 'present', 'verified', 'good', 'new']
const attentionStatuses = ['inactive', 'pending', 'overdue', 'rejected', 'cancelled', 'damaged', 'poor', 'fair', 'unpaid', 'paused']
const healthy = computed(() => props.items.filter(item => item[props.statusKey] === true || activeStatuses.includes(String(item[props.statusKey] ?? '').toLowerCase())).length)
const needsAttention = computed(() => props.items.filter(item => item[props.statusKey] === false || attentionStatuses.includes(String(item[props.statusKey] ?? '').toLowerCase())).length)
const amountKey = computed(() => ['amount', 'total', 'fee', 'price', 'salary', 'net_salary'].find(key => props.items.some(item => Number.isFinite(Number(item[key])))))
const totalAmount = computed(() => amountKey.value ? props.items.reduce((sum, item) => sum + (Number(item[amountKey.value!]) || 0), 0) : 0)
const groupKey = computed(() => ['office_id', 'company_id', 'customer_id', 'employee_id', 'staff_id', 'type', 'category', 'condition', 'currency_id'].find(key => props.items.some(item => item[key] !== null && item[key] !== undefined)))
const groups = computed(() => groupKey.value ? new Set(props.items.map(item => item[groupKey.value!]).filter(value => value !== null && value !== undefined && value !== '')).size : 0)
const groupLabel = computed(() => ({
  office_id: t('components.dataTablePageKpis.officesRepresented'),
  company_id: t('components.dataTablePageKpis.companiesRepresented'),
  customer_id: t('components.dataTablePageKpis.customersRepresented'),
  employee_id: t('components.dataTablePageKpis.employeesRepresented'),
  staff_id: t('components.dataTablePageKpis.staffRepresented'),
  type: t('components.dataTablePageKpis.typesRepresented'),
  category: t('components.dataTablePageKpis.categoriesRepresented'),
  condition: t('components.dataTablePageKpis.conditionsRepresented'),
  currency_id: t('components.dataTablePageKpis.currenciesRepresented'),
}[groupKey.value || ''] || t('components.dataTablePageKpis.recordsCovered')))
const formatAmount = (amount: number) => new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(amount)
const sum = (...keys: string[]) => props.items.reduce((total, item) => total + (Number(keys.map(key => item[key]).find(value => Number.isFinite(Number(value)))) || 0), 0)
const resolvedBaseCurrency = computed<BaseCurrency | null>(() => {
  // Prefer the live currency catalogue, but use the invoice's base-currency
  // snapshot whenever it is the only source with display details available.
  if (props.baseCurrency?.symbol || props.baseCurrency?.code)
    return props.baseCurrency

  const invoiceBase = props.items.find(item => item.currentBaseCurrency && typeof item.currentBaseCurrency === 'object')?.currentBaseCurrency

  return (invoiceBase as BaseCurrency | undefined) ?? props.baseCurrency ?? null
})
const formatCurrentBaseMoney = (amount: number) => {
  const currency = resolvedBaseCurrency.value
  const decimals = currency?.decimalPlaces ?? 2
  const formatted = new Intl.NumberFormat(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(amount)
  return `${currency?.symbol ?? ''}${formatted}${currency?.code ? ` ${currency.code}` : ''}`.trim()
}
const count = (predicate: (item: TableRow) => boolean) => props.items.filter(predicate).length
const unique = (key: string) => new Set(props.items.map(item => item[key]).filter(Boolean)).size

const metrics = computed<KpiMetric[]>(() => {
  const page = props.title.toLowerCase()
  const base: KpiMetric = { label: t('components.dataTablePageKpis.recordsShown'), value: total.value, icon: props.icon, tone: 'primary' }

  if (page.includes('income')) return [base,
    { label: t('components.dataTablePageKpis.recordedValueCurrentBase'), value: formatCurrentBaseMoney(sum('currentBaseAmount', 'base_amount', 'amount')), icon: 'tabler-cash', tone: 'status' },
    { label: t('components.dataTablePageKpis.invoiceLinked'), value: count(item => Boolean(item.invoice_id)), icon: 'tabler-file-invoice', tone: 'insight' },
    { label: t('components.dataTablePageKpis.incomeTypes'), value: unique('type'), icon: 'tabler-category', tone: 'attention' },
  ]
  if (page.includes('expense')) return [base,
    { label: t('components.dataTablePageKpis.recordedValueCurrentBase'), value: formatCurrentBaseMoney(sum('currentBaseAmount', 'base_amount', 'amount')), icon: 'tabler-receipt-2', tone: 'attention' },
    { label: t('components.dataTablePageKpis.customerLinked'), value: count(item => Boolean(item.customer_id)), icon: 'tabler-user-check', tone: 'insight' },
    { label: t('components.dataTablePageKpis.expenseTypes'), value: unique('type'), icon: 'tabler-category', tone: 'status' },
  ]
  if (page.includes('invoice')) return [base,
    { label: t('components.dataTablePageKpis.invoiceValueCurrentBase'), value: formatCurrentBaseMoney(sum('currentBaseAmount', 'total_amount', 'amount')), icon: 'tabler-file-invoice', tone: 'primary' },
    { label: t('components.dataTablePageKpis.collectedCurrentBase'), value: formatCurrentBaseMoney(sum('currentBasePaidAmount', 'paid_amount')), icon: 'tabler-circle-check', tone: 'status' },
    { label: t('components.dataTablePageKpis.outstandingCurrentBase'), value: formatCurrentBaseMoney(sum('currentBaseBalance', 'balance')), icon: 'tabler-alert-circle', tone: 'attention' },
  ]
  if (page.includes('asset')) return [base,
    { label: t('components.dataTablePageKpis.unitsInAsset'), value: formatAmount(sum('quantity')), icon: 'tabler-packages', tone: 'status' },
    { label: t('components.dataTablePageKpis.inventoryValue'), value: formatAmount(props.items.reduce((value, item) => value + (Number(item.quantity) || 0) * (Number(item.price) || 0), 0)), icon: 'tabler-cash', tone: 'insight' },
    { label: t('components.dataTablePageKpis.needsReview'), value: count(item => ['poor', 'damaged'].includes(String(item.condition))), icon: 'tabler-tool', tone: 'attention' },
  ]
  if (page.includes('salary')) return [base,
    { label: t('components.dataTablePageKpis.netPayrollCurrentBase'), value: formatCurrentBaseMoney(sum('currentBaseNetAmount', 'netAmount', 'totalAmount', 'amountPaid')), icon: 'tabler-wallet', tone: 'status' },
    { label: t('components.dataTablePageKpis.workedDays'), value: formatAmount(sum('workedDays')), icon: 'tabler-calendar-check', tone: 'insight' },
    { label: t('components.dataTablePageKpis.absentDays'), value: formatAmount(sum('absentDays')), icon: 'tabler-calendar-x', tone: 'attention' },
  ]
  if (page.includes('position')) return [base,
    { label: t('components.dataTablePageKpis.baseSalaries'), value: formatCurrentBaseMoney(sum('salary')), icon: 'tabler-wallet', tone: 'status' },
    { label: t('components.dataTablePageKpis.assignedStaff'), value: formatAmount(sum('employee_count')), icon: 'tabler-users', tone: 'insight' },
    { label: t('components.dataTablePageKpis.activePositions'), value: count(item => item.status === 'active'), icon: 'tabler-briefcase', tone: 'attention' },
  ]
  if (page.includes('customer')) return [base,
    { label: t('components.dataTablePageKpis.companies'), value: unique('company'), icon: 'tabler-building', tone: 'insight' },
    { label: t('components.dataTablePageKpis.activeServices'), value: props.items.reduce((total, item) => total + ((item.services as TableRow[] | undefined)?.filter(service => service.status === 'active').length || 0), 0), icon: 'tabler-plug-connected', tone: 'status' },
    { label: t('components.dataTablePageKpis.pausedServices'), value: props.items.reduce((total, item) => total + ((item.services as TableRow[] | undefined)?.filter(service => service.status === 'paused').length || 0), 0), icon: 'tabler-player-pause', tone: 'attention' },
  ]
  if (page.includes('leave')) return [base,
    { label: t('components.dataTablePageKpis.awaitingDecision'), value: count(item => !item.is_granted), icon: 'tabler-hourglass', tone: 'attention' },
    { label: t('components.dataTablePageKpis.paidLeave'), value: count(item => item.is_paid === true), icon: 'tabler-circle-check', tone: 'status' },
    { label: t('components.dataTablePageKpis.teamMembers'), value: unique('employee_id'), icon: 'tabler-users', tone: 'insight' },
  ]
  if (page.includes('contract')) return [base,
    { label: t('components.dataTablePageKpis.activeContracts'), value: count(item => item.status === 'active'), icon: 'tabler-file-check', tone: 'status' },
    { label: t('components.dataTablePageKpis.expiringEnded'), value: count(item => ['expired', 'terminated', 'cancelled'].includes(String(item.status))), icon: 'tabler-file-alert', tone: 'attention' },
    { label: t('components.dataTablePageKpis.counterparties'), value: unique('company_id') || unique('counterparty'), icon: 'tabler-building-community', tone: 'insight' },
  ]
  if (page.includes('visitor')) return [base,
    { label: t('components.dataTablePageKpis.companiesRepresented'), value: unique('company'), icon: 'tabler-building', tone: 'insight' },
    { label: t('components.dataTablePageKpis.servicesRequested'), value: unique('service'), icon: 'tabler-briefcase', tone: 'status' },
    { label: t('components.dataTablePageKpis.marketingSources'), value: unique('marketer'), icon: 'tabler-speakerphone', tone: 'attention' },
  ]
  if (page.includes('payment detail')) return [base,
    { label: t('components.dataTablePageKpis.paymentMethods'), value: unique('method'), icon: 'tabler-credit-card', tone: 'status' },
    { label: t('components.dataTablePageKpis.instructionsSet'), value: count(item => Boolean(item.instruction)), icon: 'tabler-notes', tone: 'insight' },
    { label: t('components.dataTablePageKpis.recentlyAdded'), value: count(item => String(item.created_at || '').slice(0, 7) === new Date().toISOString().slice(0, 7)), icon: 'tabler-calendar-plus', tone: 'attention' },
  ]
  if (page.includes('recurring')) return [base,
    { label: t('components.dataTablePageKpis.activeAutomations'), value: count(item => item.status === 'active'), icon: 'tabler-player-play', tone: 'status' },
    { label: t('components.dataTablePageKpis.pausedAutomations'), value: count(item => item.status === 'paused'), icon: 'tabler-player-pause', tone: 'attention' },
    { label: t('components.dataTablePageKpis.eventTypes'), value: unique('event_type'), icon: 'tabler-repeat', tone: 'insight' },
  ]
  if (page === 'services') return [base,
    { label: t('components.dataTablePageKpis.activeServices'), value: count(item => item.status === 'active'), icon: 'tabler-circle-check', tone: 'status' },
    { label: t('components.dataTablePageKpis.inactiveServices'), value: count(item => item.status === 'inactive'), icon: 'tabler-player-pause', tone: 'attention' },
    { label: t('components.dataTablePageKpis.averageDuration'), value: t('components.dataTablePageKpis.months', { count: Math.round(sum('duration') / Math.max(total.value, 1)) }), icon: 'tabler-clock', tone: 'insight' },
  ]
  if (page.includes('staff directory')) return [base,
    { label: t('components.dataTablePageKpis.positionsRepresented'), value: unique('position_id') || unique('position'), icon: 'tabler-briefcase', tone: 'insight' },
    { label: t('components.dataTablePageKpis.officesRepresented'), value: unique('office_id'), icon: 'tabler-building', tone: 'status' },
    { label: t('components.dataTablePageKpis.withoutOffice'), value: count(item => !item.office_id), icon: 'tabler-map-pin-off', tone: 'attention' },
  ]
  if (page === 'companies') return [base,
    { label: t('components.dataTablePageKpis.activeCompanies'), value: count(item => item.status === 'active'), icon: 'tabler-building-check', tone: 'status' },
    { label: t('components.dataTablePageKpis.withRepresentative'), value: count(item => Boolean(item.representative_name)), icon: 'tabler-user-check', tone: 'insight' },
    { label: t('components.dataTablePageKpis.profileIncomplete'), value: count(item => !item.email && !item.phone), icon: 'tabler-alert-circle', tone: 'attention' },
  ]
  return [base,
    { label: t('components.dataTablePageKpis.activeComplete'), value: healthy.value, icon: 'tabler-circle-check', tone: 'status' },
    { label: t('components.dataTablePageKpis.needAttention'), value: needsAttention.value, icon: 'tabler-alert-circle', tone: 'attention' },
    { label: amountKey.value ? t('components.dataTablePageKpis.total', { key: amountKey.value.replace('_', ' ') }) : groupLabel.value, value: amountKey.value ? formatAmount(totalAmount.value) : groups.value, icon: amountKey.value ? 'tabler-cash' : 'tabler-category', tone: 'insight' },
  ]
})
</script>

<template>
  <section class="data-table-page-summary mb-6" :aria-label="$t('components.dataTablePageKpis.overview', { title: displayTitle })">
    <div class="data-table-page-summary__heading">
      <div class="d-flex align-center ga-3">
        <VAvatar :icon="icon" color="primary" variant="tonal" rounded="lg" />
        <div>
          <h1 class="text-h5 font-weight-bold">{{ displayTitle }}</h1>
          <p class="text-body-2 text-medium-emphasis mb-0">{{ displayDescription }}</p>
        </div>
      </div>
      <span class="data-table-page-summary__hint"><VIcon icon="tabler-arrows-left-right" size="16" /> {{ $t('components.dataTablePageKpis.scrollHint') }}</span>
    </div>

    <VRow class="mt-1">
      <VCol v-for="metric in metrics" :key="metric.label" cols="6" sm="3"><VCard :class="`data-table-kpi data-table-kpi--${metric.tone}`" elevation="0"><VCardText><VIcon class="data-table-kpi__background-icon" :icon="metric.icon" /><div class="data-table-kpi__content"><div class="data-table-kpi__value">{{ metric.value }}</div><div class="data-table-kpi__label">{{ metric.label }}</div></div></VCardText></VCard></VCol>
    </VRow>
  </section>
</template>
