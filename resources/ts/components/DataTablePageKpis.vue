<script setup lang="ts">
import { computed } from 'vue'

type TableRow = Record<string, unknown>
type KpiMetric = { label: string, value: string | number, icon: string, tone: 'primary' | 'status' | 'attention' | 'insight' }

const props = withDefaults(defineProps<{
  items: TableRow[]
  title: string
  description: string
  icon?: string
  statusKey?: string
}>(), {
  icon: 'tabler-table',
  statusKey: 'status',
})

const total = computed(() => props.items.length)
const activeStatuses = ['active', 'paid', 'approved', 'completed', 'present', 'verified', 'good', 'new']
const attentionStatuses = ['inactive', 'pending', 'overdue', 'rejected', 'cancelled', 'damaged', 'poor', 'fair', 'unpaid', 'paused']
const healthy = computed(() => props.items.filter(item => item[props.statusKey] === true || activeStatuses.includes(String(item[props.statusKey] ?? '').toLowerCase())).length)
const needsAttention = computed(() => props.items.filter(item => item[props.statusKey] === false || attentionStatuses.includes(String(item[props.statusKey] ?? '').toLowerCase())).length)
const amountKey = computed(() => ['amount', 'total', 'fee', 'price', 'salary', 'net_salary'].find(key => props.items.some(item => Number.isFinite(Number(item[key])))))
const totalAmount = computed(() => amountKey.value ? props.items.reduce((sum, item) => sum + (Number(item[amountKey.value!]) || 0), 0) : 0)
const groupKey = computed(() => ['office_id', 'company_id', 'customer_id', 'employee_id', 'staff_id', 'type', 'category', 'condition', 'currency_id'].find(key => props.items.some(item => item[key] !== null && item[key] !== undefined)))
const groups = computed(() => groupKey.value ? new Set(props.items.map(item => item[groupKey.value!]).filter(value => value !== null && value !== undefined && value !== '')).size : 0)
const groupLabel = computed(() => ({ office_id: 'Offices represented', company_id: 'Companies represented', customer_id: 'Customers represented', employee_id: 'Employees represented', staff_id: 'Staff represented', type: 'Types represented', category: 'Categories represented', condition: 'Conditions represented', currency_id: 'Currencies represented' }[groupKey.value || ''] || 'Records covered'))
const formatAmount = (amount: number) => new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(amount)
const sum = (...keys: string[]) => props.items.reduce((total, item) => total + (Number(keys.map(key => item[key]).find(value => Number.isFinite(Number(value)))) || 0), 0)
const count = (predicate: (item: TableRow) => boolean) => props.items.filter(predicate).length
const unique = (key: string) => new Set(props.items.map(item => item[key]).filter(Boolean)).size

const metrics = computed<KpiMetric[]>(() => {
  const page = props.title.toLowerCase()
  const base: KpiMetric = { label: 'Records shown', value: total.value, icon: props.icon, tone: 'primary' }

  if (page.includes('income')) return [base,
    { label: 'Recorded value', value: formatAmount(sum('base_amount', 'amount')), icon: 'tabler-cash', tone: 'status' },
    { label: 'Invoice-linked', value: count(item => Boolean(item.invoice_id)), icon: 'tabler-file-invoice', tone: 'insight' },
    { label: 'Income types', value: unique('type'), icon: 'tabler-category', tone: 'attention' },
  ]
  if (page.includes('expense')) return [base,
    { label: 'Recorded value', value: formatAmount(sum('base_amount', 'amount')), icon: 'tabler-receipt-2', tone: 'attention' },
    { label: 'Customer-linked', value: count(item => Boolean(item.customer_id)), icon: 'tabler-user-check', tone: 'insight' },
    { label: 'Expense types', value: unique('type'), icon: 'tabler-category', tone: 'status' },
  ]
  if (page.includes('invoice')) return [base,
    { label: 'Invoice value', value: formatAmount(sum('total_amount', 'amount')), icon: 'tabler-file-invoice', tone: 'primary' },
    { label: 'Collected', value: formatAmount(sum('paid_amount')), icon: 'tabler-circle-check', tone: 'status' },
    { label: 'Outstanding', value: formatAmount(sum('balance')), icon: 'tabler-alert-circle', tone: 'attention' },
  ]
  if (page.includes('stock')) return [base,
    { label: 'Units in stock', value: formatAmount(sum('quantity')), icon: 'tabler-packages', tone: 'status' },
    { label: 'Inventory value', value: formatAmount(props.items.reduce((value, item) => value + (Number(item.quantity) || 0) * (Number(item.price) || 0), 0)), icon: 'tabler-cash', tone: 'insight' },
    { label: 'Needs review', value: count(item => ['poor', 'damaged'].includes(String(item.condition))), icon: 'tabler-tool', tone: 'attention' },
  ]
  if (page.includes('salary')) return [base,
    { label: 'Net payroll', value: formatAmount(sum('netAmount', 'totalAmount', 'amountPaid')), icon: 'tabler-wallet', tone: 'status' },
    { label: 'Worked days', value: formatAmount(sum('workedDays')), icon: 'tabler-calendar-check', tone: 'insight' },
    { label: 'Absent days', value: formatAmount(sum('absentDays')), icon: 'tabler-calendar-x', tone: 'attention' },
  ]
  if (page.includes('customer')) return [base,
    { label: 'Companies', value: unique('company'), icon: 'tabler-building', tone: 'insight' },
    { label: 'Active services', value: props.items.reduce((total, item) => total + ((item.services as TableRow[] | undefined)?.filter(service => service.status === 'active').length || 0), 0), icon: 'tabler-plug-connected', tone: 'status' },
    { label: 'Paused services', value: props.items.reduce((total, item) => total + ((item.services as TableRow[] | undefined)?.filter(service => service.status === 'paused').length || 0), 0), icon: 'tabler-player-pause', tone: 'attention' },
  ]
  if (page.includes('paid time')) return [base,
    { label: 'Awaiting decision', value: count(item => !item.is_granted), icon: 'tabler-hourglass', tone: 'attention' },
    { label: 'Paid leave', value: count(item => item.is_paid === true), icon: 'tabler-circle-check', tone: 'status' },
    { label: 'Team members', value: unique('employee_id'), icon: 'tabler-users', tone: 'insight' },
  ]
  if (page.includes('contract')) return [base,
    { label: 'Active contracts', value: count(item => item.status === 'active'), icon: 'tabler-file-check', tone: 'status' },
    { label: 'Expiring / ended', value: count(item => ['expired', 'terminated', 'cancelled'].includes(String(item.status))), icon: 'tabler-file-alert', tone: 'attention' },
    { label: 'Counterparties', value: unique('company_id') || unique('counterparty'), icon: 'tabler-building-community', tone: 'insight' },
  ]
  if (page.includes('visitor')) return [base,
    { label: 'Companies represented', value: unique('company'), icon: 'tabler-building', tone: 'insight' },
    { label: 'Services requested', value: unique('service'), icon: 'tabler-briefcase', tone: 'status' },
    { label: 'Marketing sources', value: unique('marketer'), icon: 'tabler-speakerphone', tone: 'attention' },
  ]
  if (page.includes('payment detail')) return [base,
    { label: 'Payment methods', value: unique('method'), icon: 'tabler-credit-card', tone: 'status' },
    { label: 'Instructions set', value: count(item => Boolean(item.instruction)), icon: 'tabler-notes', tone: 'insight' },
    { label: 'Recently added', value: count(item => String(item.created_at || '').slice(0, 7) === new Date().toISOString().slice(0, 7)), icon: 'tabler-calendar-plus', tone: 'attention' },
  ]
  if (page.includes('recurring')) return [base,
    { label: 'Active automations', value: count(item => item.status === 'active'), icon: 'tabler-player-play', tone: 'status' },
    { label: 'Paused automations', value: count(item => item.status === 'paused'), icon: 'tabler-player-pause', tone: 'attention' },
    { label: 'Event types', value: unique('event_type'), icon: 'tabler-repeat', tone: 'insight' },
  ]
  if (page === 'services') return [base,
    { label: 'Active services', value: count(item => item.status === 'active'), icon: 'tabler-circle-check', tone: 'status' },
    { label: 'Inactive services', value: count(item => item.status === 'inactive'), icon: 'tabler-player-pause', tone: 'attention' },
    { label: 'Average duration', value: `${Math.round(sum('duration') / Math.max(total.value, 1))} months`, icon: 'tabler-clock', tone: 'insight' },
  ]
  if (page.includes('staff directory')) return [base,
    { label: 'Positions represented', value: unique('position_id') || unique('position'), icon: 'tabler-briefcase', tone: 'insight' },
    { label: 'Offices represented', value: unique('office_id'), icon: 'tabler-building', tone: 'status' },
    { label: 'Without an office', value: count(item => !item.office_id), icon: 'tabler-map-pin-off', tone: 'attention' },
  ]
  if (page === 'companies') return [base,
    { label: 'Active companies', value: count(item => item.status === 'active'), icon: 'tabler-building-check', tone: 'status' },
    { label: 'With a representative', value: count(item => Boolean(item.representative_name)), icon: 'tabler-user-check', tone: 'insight' },
    { label: 'Profile incomplete', value: count(item => !item.email && !item.phone), icon: 'tabler-alert-circle', tone: 'attention' },
  ]
  return [base,
    { label: 'Active / complete', value: healthy.value, icon: 'tabler-circle-check', tone: 'status' },
    { label: 'Need attention', value: needsAttention.value, icon: 'tabler-alert-circle', tone: 'attention' },
    { label: amountKey.value ? `Total ${amountKey.value.replace('_', ' ')}` : groupLabel.value, value: amountKey.value ? formatAmount(totalAmount.value) : groups.value, icon: amountKey.value ? 'tabler-cash' : 'tabler-category', tone: 'insight' },
  ]
})
</script>

<template>
  <section class="data-table-page-summary mb-6" :aria-label="`${title} overview`">
    <div class="data-table-page-summary__heading">
      <div class="d-flex align-center ga-3">
        <VAvatar :icon="icon" color="primary" variant="tonal" rounded="lg" />
        <div>
          <h1 class="text-h5 font-weight-bold">{{ title }}</h1>
          <p class="text-body-2 text-medium-emphasis mb-0">{{ description }}</p>
        </div>
      </div>
      <span class="data-table-page-summary__hint"><VIcon icon="tabler-arrows-left-right" size="16" /> Scroll tables sideways on small screens</span>
    </div>

    <VRow class="mt-1">
      <VCol v-for="metric in metrics" :key="metric.label" cols="6" sm="3"><VCard :class="`data-table-kpi data-table-kpi--${metric.tone}`" elevation="0"><VCardText><VIcon class="data-table-kpi__background-icon" :icon="metric.icon" /><div class="data-table-kpi__content"><div class="data-table-kpi__value">{{ metric.value }}</div><div class="data-table-kpi__label">{{ metric.label }}</div></div></VCardText></VCard></VCol>
    </VRow>
  </section>
</template>
