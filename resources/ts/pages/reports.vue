<script setup lang="ts">
import ReportApi, { type GeneratedReport, type ReportCatalog, type ReportFilters, type ReportSection, type ReportType } from '@/api/report'
import { canAccess } from '@/utils/authorization'
import { apiErrorMessage } from '@/utils/apiErrors'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTheme } from 'vuetify'

interface ReportProfile {
  key: ReportType
  icon: string
  color: string
}

interface Metric {
  label: string
  value: string
  detail: string
  icon: string
  color: string
}

const profiles: Record<ReportType, ReportProfile> = {
  financial: { key: 'financial', icon: 'tabler-chart-line', color: 'success' },
  payroll: { key: 'payroll', icon: 'tabler-cash-banknote', color: 'primary' },
  attendance: { key: 'attendance', icon: 'tabler-clock-check', color: 'info' },
  operations: { key: 'operations', icon: 'tabler-briefcase', color: 'warning' },
}

const { locale } = useI18n()
const { global } = useTheme()
const rt = (key: string, values?: Record<string, unknown>) => translate(`pages.reports.studio.${key}`, values)
const profileText = (profile: ReportProfile, field: 'label' | 'description' | 'question') => rt(`profiles.${profile.key}.${field}`)
const chartTextColor = computed(() => global.current.value.dark ? '#d4d2dc' : '#6d6b77')
const now = new Date()
const dateKey = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1)
const type = ref<ReportType>('financial')
const catalog = ref<ReportCatalog | null>(null)
const report = ref<GeneratedReport | null>(null)
const loading = ref(false)
const exporting = ref(false)
const error = ref('')
const datePreset = ref('month')
const filtersOpen = ref(false)
const filters = reactive<ReportFilters>({ start_date: dateKey(startOfMonth), end_date: dateKey(now) })
const lastAppliedFilters = ref('')
const activeSectionKey = ref('')
const tableSearch = ref('')
const page = ref(1)
const rowsPerPage = ref(10)
const sortColumn = ref('')
const sortDirection = ref<'asc' | 'desc'>('asc')

const availableTypes = computed(() => catalog.value?.types.map(item => item.value) ?? [])
const currentProfile = computed(() => profiles[type.value])
const direction = computed(() => ['fa-AF', 'ps-AF'].includes(locale.value) ? 'rtl' : 'ltr')
const generatedAt = computed(() => report.value
  ? new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short', timeZone: report.value.meta.timezone }).format(new Date(report.value.meta.generated_at))
  : '')
const canExport = computed(() => canAccess('export_reports') && (type.value !== 'financial' || canAccess('export_financial_reports')))
const hasUnappliedChanges = computed(() => lastAppliedFilters.value !== JSON.stringify({ type: type.value, ...filters }))
const baseCurrency = computed(() => catalog.value?.currencies.find(currency => currency.is_base)?.code ?? 'USD')

const resetContextFilters = () => {
  filters.employee_id = null; filters.office_id = null; filters.customer_id = null; filters.currency_id = null
  filters.board_id = null; filters.status = null; filters.contract_type = null; filters.attendance_state = null
}

const applyPreset = (preset: string) => {
  const end = new Date()
  let start = new Date(end.getFullYear(), end.getMonth(), 1)
  if (preset === '30days') start = new Date(end.getFullYear(), end.getMonth(), end.getDate() - 29)
  if (preset === 'quarter') start = new Date(end.getFullYear(), Math.floor(end.getMonth() / 3) * 3, 1)
  if (preset === 'year') start = new Date(end.getFullYear(), 0, 1)
  if (preset === 'all') start = new Date(2020, 0, 1)
  filters.start_date = dateKey(start)
  filters.end_date = dateKey(end)
  datePreset.value = preset
}

const generate = async () => {
  loading.value = true
  error.value = ''
  try {
    report.value = await ReportApi.generate(type.value, filters)
    lastAppliedFilters.value = JSON.stringify({ type: type.value, ...filters })
    const firstMeaningful = report.value.sections.find(section => section.key !== 'net_cash_flow') ?? report.value.sections[0]
    activeSectionKey.value = firstMeaningful?.key ?? ''
    tableSearch.value = ''; page.value = 1; sortColumn.value = ''; sortDirection.value = 'asc'
  }
  catch (caught) {
    report.value = null
    error.value = apiErrorMessage(caught, rt('errors.generate'))
  }
  finally { loading.value = false }
}

const changeType = async (nextType: ReportType) => {
  if (type.value === nextType && report.value) return
  type.value = nextType
  resetContextFilters()
  await generate()
}

const exportCsv = async () => {
  exporting.value = true; error.value = ''
  try {
    const blob = await ReportApi.export(type.value, filters)
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url; link.download = `${type.value}-report-${filters.start_date}-${filters.end_date}.csv`; link.click()
    URL.revokeObjectURL(url)
  }
  catch (caught) { error.value = apiErrorMessage(caught, rt('errors.export')) }
  finally { exporting.value = false }
}

const openPrintReport = async () => {
  exporting.value = true; error.value = ''
  try {
    const blob = await ReportApi.export(type.value, filters, 'print')
    const url = URL.createObjectURL(blob)
    window.open(url, '_blank', 'noopener,noreferrer')
    window.setTimeout(() => URL.revokeObjectURL(url), 60_000)
  }
  catch (caught) { error.value = apiErrorMessage(caught, rt('errors.print')) }
  finally { exporting.value = false }
}

const humanize = (value: string) => {
  const isResolved = (translated: string, key: string) => translated !== key && !translated.toLowerCase().startsWith('[missing:')
  const sectionLabel = translate(`pages.reports.sections.${value}`)
  if (isResolved(sectionLabel, `pages.reports.sections.${value}`)) return sectionLabel
  if (value.startsWith('reports.')) return translate(`pages.${value}`)
  const translationKey = value.replace(/_([a-z])/g, (_, letter: string) => letter.toUpperCase())
  const translated = translate(`pages.reports.columns.${translationKey}`)
  return isResolved(translated, `pages.reports.columns.${translationKey}`)
    ? translated
    : value.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase())
}

const section = (key: string) => report.value?.sections.find(item => item.key === key)
const numericTotal = (reportSection: ReportSection | undefined, key: string) => {
  const value = reportSection?.totals[key]
  if (typeof value === 'number') return value
  if (value && typeof value === 'object') return Object.values(value as Record<string, unknown>).reduce<number>((sum, item) => sum + (Number(item) || 0), 0)
  return Number(value) || 0
}
const compactNumber = (value: number) => new Intl.NumberFormat(locale.value, { notation: 'compact', maximumFractionDigits: 1 }).format(value)
const number = (value: number, digits = 0) => new Intl.NumberFormat(locale.value, { maximumFractionDigits: digits }).format(value)
const currency = (value: number, code = baseCurrency.value, compact = false) => {
  try { return new Intl.NumberFormat(locale.value, { style: 'currency', currency: code, notation: compact ? 'compact' : 'standard', maximumFractionDigits: compact ? 1 : 2 }).format(value) }
  catch { return `${code} ${number(value, 2)}` }
}
const duration = (minutes: number) => minutes < 60 ? rt('common.minutesShort', { value: number(minutes) }) : rt('common.hoursShort', { value: number(minutes / 60, 1) })

const metrics = computed<Metric[]>(() => {
  if (!report.value) return []
  if (type.value === 'financial') {
    const billed = numericTotal(section('invoices'), 'base_amount_by_base')
    const outstanding = numericTotal(section('invoices'), 'outstanding_by_base')
    const income = numericTotal(section('income'), 'base_amount_by_base')
    const expenses = numericTotal(section('expenses'), 'base_amount_by_base')
    return [
      { label: rt('metrics.billedValue'), value: currency(billed, baseCurrency.value, true), detail: rt('metrics.invoicesInPeriod', { count: number(numericTotal(section('invoices'), 'count')) }), icon: 'tabler-file-invoice', color: 'primary' },
      { label: rt('metrics.cashCollected'), value: currency(income, baseCurrency.value, true), detail: billed > 0 ? rt('metrics.ofBilledValue', { rate: number((income / billed) * 100, 1) }) : rt('metrics.noBilledValue'), icon: 'tabler-cash', color: 'success' },
      { label: rt('metrics.outstanding'), value: currency(outstanding, baseCurrency.value, true), detail: outstanding > 0 ? rt('metrics.collectionFollowup') : rt('metrics.noOpenBalance'), icon: 'tabler-hourglass', color: outstanding > 0 ? 'warning' : 'success' },
      { label: rt('metrics.netCashFlow'), value: currency(income - expenses, baseCurrency.value, true), detail: rt('metrics.spent', { amount: currency(expenses, baseCurrency.value, true) }), icon: 'tabler-chart-arrows-vertical', color: income - expenses >= 0 ? 'success' : 'error' },
    ]
  }
  if (type.value === 'payroll') {
    const payroll = numericTotal(section('salary_payments'), 'base_amount_by_base')
    const deductions = numericTotal(section('salary_payments'), 'deductions_by_base')
    const bonuses = numericTotal(section('bonuses'), 'base_amount_by_base')
    return [
      { label: rt('metrics.payrollCost'), value: currency(payroll, baseCurrency.value, true), detail: rt('metrics.payments', { count: number(numericTotal(section('salary_payments'), 'count')) }), icon: 'tabler-cash-banknote', color: 'primary' },
      { label: rt('metrics.bonuses'), value: currency(bonuses, baseCurrency.value, true), detail: rt('metrics.nominations', { count: number(numericTotal(section('bonuses'), 'count')) }), icon: 'tabler-award', color: 'success' },
      { label: rt('metrics.deductions'), value: currency(deductions, baseCurrency.value, true), detail: rt('metrics.acrossSalaryPayments'), icon: 'tabler-receipt-tax', color: 'warning' },
      { label: rt('metrics.headcount'), value: number(numericTotal(section('employee_roster'), 'company_headcount')), detail: rt('metrics.joinedInPeriod', { count: number(numericTotal(section('employee_roster'), 'joined_in_period')) }), icon: 'tabler-users-group', color: 'info' },
    ]
  }
  if (type.value === 'attendance') {
    const records = numericTotal(section('attendance'), 'records')
    const present = numericTotal(section('attendance'), 'present')
    return [
      { label: rt('metrics.attendanceRate'), value: `${records ? number((present / records) * 100, 1) : 0}%`, detail: rt('metrics.presentRecords', { count: number(present) }), icon: 'tabler-user-check', color: 'success' },
      { label: rt('metrics.workedTime'), value: duration(numericTotal(section('attendance'), 'worked_minutes')), detail: rt('metrics.netWorkingTime'), icon: 'tabler-clock-hour-4', color: 'primary' },
      { label: rt('metrics.overtime'), value: duration(numericTotal(section('attendance'), 'overtime_minutes')), detail: rt('metrics.beyondSchedules'), icon: 'tabler-clock-plus', color: 'info' },
      { label: rt('metrics.incompleteRecords'), value: number(numericTotal(section('attendance'), 'incomplete')), detail: rt('metrics.missingPunch'), icon: 'tabler-alert-triangle', color: numericTotal(section('attendance'), 'incomplete') ? 'warning' : 'success' },
    ]
  }
  return [
    { label: rt('metrics.newCustomers'), value: number(numericTotal(section('customers'), 'count')), detail: rt('metrics.addedInPeriod'), icon: 'tabler-user-plus', color: 'primary' },
    { label: rt('metrics.visitors'), value: number(numericTotal(section('visitors'), 'count')), detail: rt('metrics.commercialInterest'), icon: 'tabler-walk', color: 'info' },
    { label: rt('metrics.serviceCommitments'), value: number(numericTotal(section('customer_services'), 'commitments')), detail: rt('metrics.activeCount', { count: number(numericTotal(section('customer_services'), 'active')) }), icon: 'tabler-heart-handshake', color: 'success' },
    { label: rt('metrics.deliveryRisk'), value: number(numericTotal(section('tasks'), 'overdue')), detail: rt('metrics.tasksInReport', { count: number(numericTotal(section('tasks'), 'count')) }), icon: 'tabler-calendar-exclamation', color: numericTotal(section('tasks'), 'overdue') ? 'error' : 'success' },
  ]
})

const dateFieldFor = (key: string) => ({ invoices: 'date', income: 'date', expenses: 'date', salary_payments: 'payment_date', bonuses: 'month', attendance: 'date', contracts: 'date', customers: 'date', visitors: 'date', tasks: 'due_date' }[key] ?? 'date')
const groupRowsByDate = (reportSection: ReportSection | undefined, valueField?: string, transform: (value: number) => number = value => value) => {
  const groups: Record<string, number> = {}
  for (const row of reportSection?.rows ?? []) {
    const rawDate = row[dateFieldFor(reportSection?.key ?? '')]
    if (!rawDate) continue
    const key = String(rawDate).slice(0, 10)
    groups[key] = (groups[key] ?? 0) + transform(valueField ? Number(row[valueField] ?? 0) : 1)
  }
  return groups
}
const chartModel = computed(() => {
  const seriesMaps: Array<{ name: string; color: string; values: Record<string, number> }> = []
  if (type.value === 'financial') seriesMaps.push(
    { name: rt('charts.billed'), color: '#7367f0', values: groupRowsByDate(section('invoices'), 'base_amount') },
    { name: rt('charts.collected'), color: '#28c76f', values: groupRowsByDate(section('income'), 'base_amount') },
    { name: rt('charts.spent'), color: '#ff9f43', values: groupRowsByDate(section('expenses'), 'base_amount') },
  )
  if (type.value === 'payroll') seriesMaps.push(
    { name: rt('charts.payroll'), color: '#7367f0', values: groupRowsByDate(section('salary_payments'), 'base_amount') },
    { name: rt('charts.bonuses'), color: '#28c76f', values: groupRowsByDate(section('bonuses'), 'base_amount') },
  )
  if (type.value === 'attendance') seriesMaps.push(
    { name: rt('charts.workedHours'), color: '#00bad1', values: groupRowsByDate(section('attendance'), 'worked_minutes', value => value / 60) },
    { name: rt('charts.requiredHours'), color: '#7367f0', values: groupRowsByDate(section('attendance'), 'required_minutes', value => value / 60) },
  )
  if (type.value === 'operations') seriesMaps.push(
    { name: rt('charts.visitors'), color: '#00bad1', values: groupRowsByDate(section('visitors')) },
    { name: rt('charts.customers'), color: '#7367f0', values: groupRowsByDate(section('customers')) },
    { name: rt('charts.contracts'), color: '#28c76f', values: groupRowsByDate(section('contracts')) },
    { name: rt('charts.taskDeadlines'), color: '#ff9f43', values: groupRowsByDate(section('tasks')) },
  )
  const keys = [...new Set(seriesMaps.flatMap(item => Object.keys(item.values)))].sort()
  return {
    labels: keys.map(key => new Intl.DateTimeFormat(locale.value, { month: 'short', day: 'numeric' }).format(new Date(`${key}T00:00:00`))),
    series: seriesMaps.map(item => ({ name: item.name, data: keys.map(key => item.values[key] ?? 0) })),
    colors: seriesMaps.map(item => item.color),
  }
})
const timelineOptions = computed(() => ({
  chart: { type: 'area', toolbar: { show: false }, parentHeightOffset: 0, zoom: { enabled: false } },
  colors: chartModel.value.colors, stroke: { curve: 'smooth', width: 2.5 }, dataLabels: { enabled: false },
  fill: { type: 'gradient', gradient: { opacityFrom: .28, opacityTo: .025, stops: [0, 95] } },
  grid: { borderColor: 'rgba(var(--v-theme-on-surface), .10)', strokeDashArray: 5 },
  xaxis: { categories: chartModel.value.labels, axisBorder: { show: false }, axisTicks: { show: false }, crosshairs: { show: false }, tooltip: { enabled: false }, labels: { style: { colors: chartTextColor.value } } },
  yaxis: { labels: { style: { colors: chartTextColor.value }, formatter: (value: number) => type.value === 'financial' || type.value === 'payroll' ? compactNumber(value) : number(value, 1) } },
  legend: { position: 'top', horizontalAlign: 'right', markers: { radius: 12 }, labels: { colors: chartTextColor.value } },
  tooltip: { shared: true, theme: global.current.value.dark ? 'dark' : 'light', y: { formatter: (value: number) => type.value === 'financial' || type.value === 'payroll' ? currency(value) : number(value, 1) } },
  noData: { text: rt('charts.noTrendData'), style: { color: chartTextColor.value } },
}))

const breakdown = computed(() => {
  let rows: Array<Record<string, unknown>> = []
  let field = 'status'
  if (type.value === 'financial') rows = section('invoices')?.rows ?? []
  if (type.value === 'payroll') { rows = section('salary_payments')?.rows ?? []; field = 'office' }
  if (type.value === 'attendance') rows = section('attendance')?.rows ?? []
  if (type.value === 'operations') return (report.value?.sections ?? []).filter(item => item.key !== 'net_cash_flow').map(item => ({ label: humanize(item.key), value: item.rows.length })).filter(item => item.value)
  const grouped = rows.reduce<Record<string, number>>((result, row) => {
    const key = String(row[field] || rt('common.unspecified'))
    result[key] = (result[key] ?? 0) + 1
    return result
  }, {})
  return Object.entries(grouped).map(([label, value]) => ({ label: translateBackendValue(label), value }))
})
const breakdownOptions = computed(() => ({
  chart: { type: 'donut' }, labels: breakdown.value.map(item => item.label),
  colors: ['#7367f0', '#28c76f', '#ff9f43', '#00bad1', '#ea5455', '#82868b', '#a66dd4'],
  stroke: { width: 0 }, dataLabels: { enabled: false }, legend: { position: 'bottom', fontSize: '12px', markers: { radius: 12 }, labels: { colors: chartTextColor.value } },
  plotOptions: { pie: { donut: { size: '72%', labels: { show: true, name: { color: chartTextColor.value }, value: { color: chartTextColor.value }, total: { show: true, color: chartTextColor.value, label: rt('charts.records'), formatter: () => number(breakdown.value.reduce((sum, item) => sum + item.value, 0)) } } } } },
  tooltip: { theme: global.current.value.dark ? 'dark' : 'light' }, noData: { text: rt('charts.noBreakdown'), style: { color: chartTextColor.value } },
}))

const insights = computed(() => {
  if (!report.value) return []
  if (type.value === 'financial') {
    const billed = numericTotal(section('invoices'), 'base_amount_by_base'), collected = numericTotal(section('income'), 'base_amount_by_base'), spent = numericTotal(section('expenses'), 'base_amount_by_base'), outstanding = numericTotal(section('invoices'), 'outstanding_by_base')
    return [
      { title: billed ? rt('insights.collectionCoverage', { rate: number((collected / billed) * 100, 1) }) : rt('insights.noBilledValue'), body: billed ? rt('insights.outstandingBody', { amount: currency(outstanding) }) : rt('insights.createInvoices'), icon: 'tabler-percentage', color: collected / (billed || 1) >= .7 ? 'success' : 'warning' },
      { title: collected - spent >= 0 ? rt('insights.positiveCash') : rt('insights.negativeCash'), body: rt('insights.cashPositionBody', { amount: currency(collected - spent) }), icon: 'tabler-chart-line', color: collected - spent >= 0 ? 'success' : 'error' },
    ]
  }
  if (type.value === 'payroll') return [
    { title: rt('insights.leavePending', { count: number(numericTotal(section('paid_time_off'), 'pending')) }), body: rt('insights.leavePendingBody'), icon: 'tabler-calendar-user', color: numericTotal(section('paid_time_off'), 'pending') ? 'warning' : 'success' },
    { title: rt('insights.peopleJoined', { count: number(numericTotal(section('employee_roster'), 'joined_in_period')) }), body: rt('insights.headcountBody', { count: number(numericTotal(section('employee_roster'), 'company_headcount')) }), icon: 'tabler-user-plus', color: 'info' },
  ]
  if (type.value === 'attendance') return [
    { title: rt('insights.incompleteSessions', { count: number(numericTotal(section('attendance'), 'incomplete')) }), body: rt('insights.incompleteSessionsBody'), icon: 'tabler-clock-exclamation', color: numericTotal(section('attendance'), 'incomplete') ? 'warning' : 'success' },
    { title: rt('insights.supportRequests', { count: number(numericTotal(section('daily_reports'), 'support_requested')) }), body: rt('insights.supportRequestsBody'), icon: 'tabler-help-hexagon', color: numericTotal(section('daily_reports'), 'support_requested') ? 'info' : 'success' },
  ]
  const visitors = numericTotal(section('visitors'), 'count'), customers = numericTotal(section('customers'), 'count'), tasks = numericTotal(section('tasks'), 'count'), overdue = numericTotal(section('tasks'), 'overdue')
  return [
    { title: visitors ? rt('insights.conversionRatio', { rate: number((customers / visitors) * 100, 1) }) : rt('insights.noVisitorActivity'), body: rt('insights.conversionBody', { visitors: number(visitors), customers: number(customers) }), icon: 'tabler-funnel', color: 'info' },
    { title: tasks ? rt('insights.overdueRatio', { rate: number((overdue / tasks) * 100, 1) }) : rt('insights.noTaskCommitments'), body: rt('insights.overdueBody', { overdue: number(overdue), tasks: number(tasks) }), icon: 'tabler-target-arrow', color: overdue ? 'warning' : 'success' },
  ]
})

const activeSection = computed(() => report.value?.sections.find(item => item.key === activeSectionKey.value) ?? report.value?.sections[0] ?? null)
const filteredRows = computed(() => {
  const query = tableSearch.value.trim().toLocaleLowerCase()
  let rows = [...(activeSection.value?.rows ?? [])]
  if (query) rows = rows.filter(row => Object.values(row).some(value => String(value ?? '').toLocaleLowerCase().includes(query)))
  if (sortColumn.value) rows.sort((left, right) => {
    const a = left[sortColumn.value], b = right[sortColumn.value]
    const result = typeof a === 'number' && typeof b === 'number' ? a - b : String(a ?? '').localeCompare(String(b ?? ''), locale.value)
    return sortDirection.value === 'asc' ? result : -result
  })
  return rows
})
const pageCount = computed(() => Math.max(1, Math.ceil(filteredRows.value.length / rowsPerPage.value)))
const paginatedRows = computed(() => filteredRows.value.slice((page.value - 1) * rowsPerPage.value, page.value * rowsPerPage.value))
const selectSection = (key: string) => { activeSectionKey.value = key; tableSearch.value = ''; page.value = 1; sortColumn.value = ''; sortDirection.value = 'asc' }
const sortBy = (column: string) => {
  if (sortColumn.value === column) sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  else { sortColumn.value = column; sortDirection.value = 'asc' }
  page.value = 1
}
const isMoneyColumn = (column: string) => /(^|_)(amount|bonus|deductions|fee|price|value|base)$/.test(column)
const formatCell = (row: Record<string, unknown>, column: string) => {
  const value = row[column]
  if (value === null || value === undefined || value === '') return rt('common.notAvailable')
  if (typeof value === 'boolean') return value ? rt('common.yes') : rt('common.no')
  if (column.endsWith('_utc')) return new Intl.DateTimeFormat(locale.value, { dateStyle: 'short', timeStyle: 'short', timeZone: report.value?.meta.timezone }).format(new Date(String(value)))
  if (column.endsWith('_minutes') && typeof value === 'number') return duration(value)
  if (typeof value === 'number' && isMoneyColumn(column)) return currency(value, String(column.includes('base') ? row.base_currency || baseCurrency.value : row.currency || baseCurrency.value))
  if (typeof value === 'number') return number(value, 2)
  if (typeof value === 'object') return Object.entries(value as Record<string, unknown>).map(([key, item]) => `${key}: ${number(Number(item), 2)}`).join(' · ')
  return String(value)
}
const totalEntries = computed(() => Object.entries(activeSection.value?.totals ?? {}))
const filteredChipCount = computed(() => Object.entries(filters).filter(([key, value]) => !['start_date', 'end_date'].includes(key) && value !== null && value !== undefined && value !== '').length)

onMounted(async () => {
  loading.value = true
  try {
    catalog.value = await ReportApi.catalog()
    if (catalog.value.types.length) type.value = catalog.value.types[0].value
    await generate()
  }
  catch (caught) { error.value = apiErrorMessage(caught, rt('errors.catalog')) }
  finally { loading.value = false }
})
</script>

<template>
  <main class="reporting-studio pa-4 pa-md-6">
    <header class="d-flex flex-wrap align-end justify-space-between ga-4 mb-6 no-print">
      <div><div class="eyebrow mb-2">{{ rt('header.eyebrow') }}</div><h1 class="text-h3 font-weight-bold tracking-tight mb-1">{{ rt('header.title') }}</h1><p class="text-body-1 text-medium-emphasis mb-0">{{ rt('header.subtitle') }}</p></div>
      <div class="d-flex ga-2"><VBtn v-if="canExport" variant="outlined" prepend-icon="tabler-file-spreadsheet" :loading="exporting" :disabled="!report" @click="exportCsv">{{ rt('header.exportCsv') }}</VBtn><VBtn v-if="canExport" color="primary" prepend-icon="tabler-printer" :loading="exporting" :disabled="!report" @click="openPrintReport">{{ rt('header.printReport') }}</VBtn></div>
    </header>

    <VAlert v-if="error" type="error" variant="tonal" closable class="mb-5 no-print" @click:close="error = ''">{{ error }}</VAlert>

    <section class="report-library no-print mb-5">
      <button v-for="reportType in availableTypes" :key="reportType" class="report-type-card" :class="{ active: type === reportType }" type="button" @click="changeType(reportType)">
        <VAvatar :color="profiles[reportType].color" :variant="type === reportType ? 'flat' : 'tonal'" rounded="lg" size="42"><VIcon :icon="profiles[reportType].icon" size="22" /></VAvatar>
        <span class="min-w-0"><strong>{{ profileText(profiles[reportType], 'label') }}</strong><small>{{ profileText(profiles[reportType], 'description') }}</small></span>
        <VIcon v-if="type === reportType" icon="tabler-circle-check-filled" color="primary" size="19" />
      </button>
    </section>

    <VCard class="filter-workbench no-print mb-6">
      <VCardText class="pa-4 pa-md-5">
        <div class="d-flex flex-wrap align-center justify-space-between ga-3">
          <div><p class="eyebrow mb-1">{{ rt('filters.analysisWindow') }}</p><p class="text-body-2 font-weight-medium mb-0">{{ profileText(currentProfile, 'question') }}</p></div>
          <div class="d-flex align-center ga-2 flex-wrap">
            <VBtnToggle v-model="datePreset" mandatory density="compact" color="primary" variant="outlined" divided class="date-preset-toggle">
              <VBtn value="month" @click="applyPreset('month')">{{ rt('filters.thisMonth') }}</VBtn><VBtn value="30days" @click="applyPreset('30days')">{{ rt('filters.thirtyDays') }}</VBtn><VBtn value="quarter" @click="applyPreset('quarter')">{{ rt('filters.quarter') }}</VBtn><VBtn value="year" @click="applyPreset('year')">{{ rt('filters.year') }}</VBtn><VBtn value="all" class="d-none d-md-flex" @click="applyPreset('all')">{{ rt('filters.allTime') }}</VBtn>
            </VBtnToggle>
            <VBtn variant="text" prepend-icon="tabler-adjustments-horizontal" @click="filtersOpen = !filtersOpen">{{ rt('filters.filters') }} <VBadge v-if="filteredChipCount" inline color="primary" :content="filteredChipCount" /></VBtn>
          </div>
        </div>
        <VExpandTransition><div v-show="filtersOpen"><VDivider class="my-4" /><VRow class="align-end" dense>
          <VCol cols="12" sm="6" md="3"><AppDateTimePicker v-model="filters.start_date" :label="rt('filters.startDate')" :config="{ dateFormat: 'Y-m-d' }" @update:model-value="datePreset = 'custom'" /></VCol>
          <VCol cols="12" sm="6" md="3"><AppDateTimePicker v-model="filters.end_date" :label="rt('filters.endDate')" :config="{ dateFormat: 'Y-m-d' }" @update:model-value="datePreset = 'custom'" /></VCol>
          <VCol v-if="type === 'payroll' || type === 'attendance'" cols="12" md="3"><VSelect v-model="filters.employee_id" :items="catalog?.employees ?? []" item-title="name" item-value="id" :label="rt('filters.employee')" clearable /></VCol>
          <VCol v-if="type === 'payroll' || type === 'attendance'" cols="12" md="3"><VSelect v-model="filters.office_id" :items="catalog?.offices ?? []" item-title="name" item-value="id" :label="rt('filters.office')" clearable /></VCol>
          <VCol v-if="type === 'financial'" cols="12" md="3"><VSelect v-model="filters.customer_id" :items="catalog?.customers ?? []" item-title="name" item-value="id" :label="rt('filters.customer')" clearable /></VCol>
          <VCol v-if="type === 'financial' || type === 'payroll'" cols="12" md="3"><VSelect v-model="filters.currency_id" :items="catalog?.currencies ?? []" item-title="code" item-value="id" :label="rt('filters.currency')" clearable /></VCol>
          <VCol v-if="type === 'operations'" cols="12" md="3"><VSelect v-model="filters.board_id" :items="catalog?.boards ?? []" item-title="title" item-value="id" :label="rt('filters.taskBoard')" clearable /></VCol>
          <VCol v-if="type === 'operations'" cols="12" md="3"><VSelect v-model="filters.contract_type" :items="catalog?.contract_types ?? []" :label="rt('filters.contractType')" clearable /></VCol>
          <VCol v-if="type === 'attendance'" cols="12" md="3"><VSelect v-model="filters.attendance_state" :items="['present', 'absent', 'paid-leave', 'unpaid-leave', 'complete', 'incomplete']" :item-title="translateBackendValue" :label="rt('filters.attendanceState')" clearable /></VCol>
          <VCol v-if="type === 'financial' || type === 'payroll'" cols="12" md="3"><VCombobox v-model="filters.status" :items="type === 'financial' ? ['Issued', 'Draft', 'Paid', 'Cancelled'] : ['granted', 'pending']" :label="rt('filters.status')" clearable /></VCol>
        </VRow></div></VExpandTransition>
        <div class="d-flex flex-wrap align-center justify-space-between ga-3 mt-4">
          <div class="text-caption text-medium-emphasis"><VIcon icon="tabler-calendar" size="16" class="me-1" />{{ filters.start_date }} — {{ filters.end_date }} <span v-if="catalog">· {{ catalog.timezone }}</span></div>
          <VBtn color="primary" prepend-icon="tabler-player-play" :loading="loading" :variant="hasUnappliedChanges ? 'flat' : 'tonal'" @click="generate">{{ rt('filters.runAnalysis') }}</VBtn>
        </div>
      </VCardText>
    </VCard>

    <div v-if="loading && !report" class="text-center py-16"><VProgressCircular indeterminate color="primary" size="48" /></div>

    <article v-if="report" class="report-result" :dir="direction">
      <section class="report-context d-flex flex-wrap align-center justify-space-between ga-4 mb-5">
        <div class="d-flex align-center ga-3"><VAvatar :color="currentProfile.color" variant="tonal" rounded="lg" size="48"><VIcon :icon="currentProfile.icon" /></VAvatar><div><p class="eyebrow mb-1">{{ rt('result.generatedAnalysis') }}</p><h2 class="text-h5 font-weight-bold mb-0">{{ profileText(currentProfile, 'label') }}</h2></div></div>
        <div class="d-flex flex-wrap ga-4 text-caption text-medium-emphasis"><span><VIcon icon="tabler-calendar-event" size="16" /> {{ report.filters.start_date }} — {{ report.filters.end_date }}</span><span><VIcon icon="tabler-database" size="16" /> {{ rt('result.detailedRows', { count: number(report.sections.reduce((sum, item) => sum + item.rows.length, 0)) }) }}</span><span><VIcon icon="tabler-clock" size="16" /> {{ generatedAt }}</span></div>
      </section>

      <VRow g="4" class="mb-1"><VCol v-for="metric in metrics" :key="metric.label" cols="12" sm="6" lg="3"><VCard class="metric-card h-100 pa-5"><div class="d-flex justify-space-between ga-3"><div class="min-w-0"><p class="metric-label mb-2">{{ metric.label }}</p><h3 class="text-h5 font-weight-bold text-truncate mb-1">{{ metric.value }}</h3><p class="text-caption text-medium-emphasis text-truncate mb-0">{{ metric.detail }}</p></div><VAvatar :color="metric.color" variant="tonal" rounded="lg" size="40"><VIcon :icon="metric.icon" size="21" /></VAvatar></div></VCard></VCol></VRow>

      <VRow g="4" class="mt-1">
        <VCol cols="12" lg="8"><VCard class="h-100"><VCardItem class="px-5 pt-5 pb-0"><VCardTitle class="font-weight-bold">{{ rt('result.performanceOverTime') }}</VCardTitle><VCardSubtitle>{{ rt('result.performanceSubtitle') }}</VCardSubtitle></VCardItem><VCardText class="pt-2"><VueApexCharts type="area" height="320" :options="timelineOptions" :series="chartModel.series" /></VCardText></VCard></VCol>
        <VCol cols="12" lg="4"><VCard class="h-100"><VCardItem class="px-5 pt-5 pb-0"><VCardTitle class="font-weight-bold">{{ rt('result.composition') }}</VCardTitle><VCardSubtitle>{{ type === 'operations' ? rt('result.operationsComposition') : rt('result.resultComposition') }}</VCardSubtitle></VCardItem><VCardText><VueApexCharts type="donut" height="295" :options="breakdownOptions" :series="breakdown.map(item => item.value)" /></VCardText></VCard></VCol>
      </VRow>

      <section class="insight-grid mt-5 mb-5"><div v-for="insight in insights" :key="insight.title" class="insight-card"><VAvatar :color="insight.color" variant="tonal" size="38"><VIcon :icon="insight.icon" size="20" /></VAvatar><div><strong>{{ insight.title }}</strong><p>{{ insight.body }}</p></div></div><div class="insight-card"><VAvatar color="primary" variant="tonal" size="38"><VIcon icon="tabler-shield-check" size="20" /></VAvatar><div><strong>{{ rt('result.traceableEvidence') }}</strong><p>{{ rt('result.traceableEvidenceBody') }}</p></div></div></section>

      <VCard class="detail-workspace">
        <VCardItem class="px-5 pt-5 pb-3"><div class="d-flex flex-wrap align-center justify-space-between ga-3"><div><VCardTitle class="font-weight-bold pa-0">{{ rt('table.supportingRecords') }}</VCardTitle><VCardSubtitle class="pa-0">{{ rt('table.supportingRecordsSubtitle') }}</VCardSubtitle></div><VTextField v-model="tableSearch" density="compact" prepend-inner-icon="tabler-search" :placeholder="rt('table.searchSection')" clearable hide-details class="table-search" @update:model-value="page = 1" /></div></VCardItem>
        <div class="section-nav px-5 pb-3"><button v-for="item in report.sections" :key="item.key" type="button" :class="{ active: activeSection?.key === item.key }" @click="selectSection(item.key)"><span>{{ humanize(item.key) }}</span><small>{{ item.rows.length }}</small></button></div>
        <div v-if="activeSection" class="section-summary px-5 py-3"><span v-for="([key, value]) in totalEntries" :key="key"><small>{{ humanize(key) }}</small><strong>{{ typeof value === 'object' ? Object.entries(value as Record<string, unknown>).map(([code, amount]) => `${code} ${number(Number(amount), 2)}`).join(' · ') : formatCell({ [key]: value }, key) }}</strong></span></div>
        <div class="table-scroll"><VTable class="report-table"><thead><tr><th v-for="column in activeSection?.columns ?? []" :key="column" @click="sortBy(column)"><span>{{ humanize(column) }}</span><VIcon v-if="sortColumn === column" :icon="sortDirection === 'asc' ? 'tabler-chevron-up' : 'tabler-chevron-down'" size="15" /></th></tr></thead><tbody><tr v-for="(row, rowIndex) in paginatedRows" :key="rowIndex"><td v-for="column in activeSection?.columns ?? []" :key="column"><VChip v-if="/status|state|priority|condition|classification/.test(column) && row[column]" size="x-small" variant="tonal" :color="['overdue', 'absent', 'damaged', 'poor', 'incomplete', 'unpaid'].includes(String(row[column]).toLowerCase()) ? 'error' : ['pending', 'fair', 'urgent'].includes(String(row[column]).toLowerCase()) ? 'warning' : 'primary'">{{ translateBackendValue(row[column]) }}</VChip><div v-else-if="column === 'progress'" class="d-flex align-center ga-2 progress-cell"><VProgressLinear :model-value="Number(row[column])" height="6" color="primary" rounded /><span>{{ number(Number(row[column])) }}%</span></div><span v-else class="cell-value">{{ formatCell(row, column) }}</span></td></tr><tr v-if="!paginatedRows.length"><td :colspan="activeSection?.columns.length || 1" class="text-center py-10 text-medium-emphasis"><VIcon icon="tabler-database-off" size="28" class="mb-2" /><div>{{ rt('table.noMatchingRecords') }}</div></td></tr></tbody></VTable></div>
        <div class="table-footer d-flex flex-wrap align-center justify-space-between ga-3 px-5 py-3"><div class="d-flex align-center ga-2 text-caption text-medium-emphasis"><span>{{ rt('table.rowsPerPage') }}</span><VSelect v-model="rowsPerPage" :items="[10, 25, 50, 100]" density="compact" hide-details class="rows-select" @update:model-value="page = 1" /><span>{{ rt('table.matchingRows', { count: number(filteredRows.length) }) }}</span></div><VPagination v-model="page" :length="pageCount" :total-visible="5" density="compact" /></div>
      </VCard>

      <footer class="report-footnote d-flex flex-wrap justify-space-between ga-3 mt-5 px-1"><span>{{ translate(report.meta.currency_basis) }}</span><span>{{ report.meta.company.name || report.meta.company.short_name }} · {{ report.meta.timezone }}</span></footer>
    </article>
  </main>
</template>

<style scoped>
.reporting-studio { max-width: 1680px; margin-inline: auto; }.tracking-tight { letter-spacing: -.035em !important; }.eyebrow,.metric-label { color: rgba(var(--v-theme-on-surface),.56); font-size:.69rem; font-weight:700; letter-spacing:.105em; text-transform:uppercase; }.report-library { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }.report-type-card { display:flex; align-items:center; gap:12px; min-width:0; padding:14px; color:inherit; text-align:start; border:1px solid rgba(var(--v-theme-on-surface),.08); border-radius:12px; background:rgb(var(--v-theme-surface)); transition:.18s ease; }.report-type-card:hover { border-color:rgba(var(--v-theme-primary),.35); transform:translateY(-1px); }.report-type-card.active { border-color:rgba(var(--v-theme-primary),.65); box-shadow:0 0 0 2px rgba(var(--v-theme-primary),.08); }.report-type-card span { display:flex; flex:1; flex-direction:column; }.report-type-card strong { font-size:.86rem; }.report-type-card small { overflow:hidden; margin-top:2px; color:rgba(var(--v-theme-on-surface),.55); font-size:.7rem; text-overflow:ellipsis; white-space:nowrap; }.filter-workbench,.metric-card,.detail-workspace { border:1px solid rgba(var(--v-theme-on-surface),.065); box-shadow:0 3px 16px rgba(20,24,40,.035)!important; }.date-preset-toggle { display:inline-flex; flex:0 0 auto; max-width:100%; overflow-x:auto; overflow-y:hidden; scrollbar-width:none; }.date-preset-toggle::-webkit-scrollbar { display:none; }.date-preset-toggle :deep(.v-btn) { flex:0 0 auto!important; min-width:max-content!important; padding-inline:14px!important; white-space:nowrap; }.date-preset-toggle :deep(.v-btn__content) { display:block; overflow:visible; line-height:1.2; white-space:nowrap; }.report-context span { display:inline-flex; align-items:center; gap:5px; }.insight-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }.insight-card { display:flex; align-items:flex-start; gap:12px; padding:16px; border:1px solid rgba(var(--v-theme-on-surface),.075); border-radius:12px; background:rgba(var(--v-theme-on-surface),.022); }.insight-card strong { font-size:.84rem; }.insight-card p { margin:3px 0 0; color:rgba(var(--v-theme-on-surface),.58); font-size:.75rem; line-height:1.45; }.table-search { max-width:280px; }.section-nav { display:flex; overflow-x:auto; gap:7px; scrollbar-width:none; }.section-nav button { display:flex; align-items:center; gap:8px; flex:0 0 auto; padding:7px 11px; color:rgba(var(--v-theme-on-surface),.67); border:1px solid rgba(var(--v-theme-on-surface),.09); border-radius:8px; background:transparent; font-size:.76rem; font-weight:600; }.section-nav button.active { color:rgb(var(--v-theme-primary)); border-color:rgba(var(--v-theme-primary),.34); background:rgba(var(--v-theme-primary),.08); }.section-nav small { display:grid; place-items:center; min-width:19px; height:19px; padding-inline:4px; border-radius:10px; background:rgba(var(--v-theme-on-surface),.08); font-size:.65rem; }.section-summary { display:flex; overflow-x:auto; gap:28px; border-block:1px solid rgba(var(--v-theme-on-surface),.07); background:rgba(var(--v-theme-on-surface),.018); }.section-summary span { display:flex; flex:0 0 auto; flex-direction:column; }.section-summary small { color:rgba(var(--v-theme-on-surface),.52); font-size:.65rem; text-transform:uppercase; }.section-summary strong { margin-top:2px; font-size:.78rem; }.table-scroll { overflow-x:auto; }.report-table { min-width:780px; }.report-table :deep(th) { height:46px!important; cursor:pointer; color:rgba(var(--v-theme-on-surface),.55)!important; background:rgba(var(--v-theme-on-surface),.025)!important; font-size:.67rem!important; font-weight:700!important; letter-spacing:.07em; text-transform:uppercase; white-space:nowrap; }.report-table :deep(th span) { vertical-align:middle; }.report-table :deep(td) { height:56px!important; font-size:.78rem; }.report-table :deep(tr:hover td) { background:rgba(var(--v-theme-primary),.022); }.cell-value { display:block; overflow:hidden; max-width:260px; text-overflow:ellipsis; white-space:nowrap; }.progress-cell { min-width:130px; }.rows-select { width:72px; }.table-footer { border-top:1px solid rgba(var(--v-theme-on-surface),.07); }.report-footnote { color:rgba(var(--v-theme-on-surface),.5); font-size:.7rem; }.min-w-0 { min-width:0; }
@media(max-width:1199px){.report-library{grid-template-columns:repeat(2,minmax(0,1fr));}.insight-grid{grid-template-columns:1fr 1fr}.insight-card:last-child{grid-column:1/-1}}@media(max-width:599px){.report-library,.insight-grid{grid-template-columns:1fr}.insight-card:last-child{grid-column:auto}.report-type-card small{white-space:normal}.table-search{max-width:none;width:100%}.table-footer{align-items:center!important;flex-direction:column}.date-preset-toggle{display:flex;width:100%;padding-block-end:2px}.date-preset-toggle :deep(.v-btn){min-height:38px;padding-inline:12px!important}}
</style>
