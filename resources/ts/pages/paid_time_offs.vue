<script setup lang="ts">
import PaidTimeOffApi, { type PaidTimeOff } from '@/api/paidTimeOff'
import { apiErrorMessage } from '@/utils/apiErrors'
import { formatLocalizedDate } from '@/utils/localization'
import { printDocument } from '@/utils/printDocument'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'

const toast = useToast()
const { t: $t } = useI18n()
const year = ref(new Date().getFullYear())
const rows = ref<PaidTimeOff[]>([])
const selected = ref<number[]>([])
const statusFilter = ref<'pending' | 'paid' | 'unpaid' | null>(null)
const loading = ref(false)
const overrideDialog = ref(false)
const approvalNote = ref('')
const allowance = ref(0)

const statusOptions = computed(() => [
  { title: $t('common.statuses.pending'), value: 'pending' },
  { title: $t('common.statuses.paid'), value: 'paid' },
  { title: $t('common.statuses.unpaid'), value: 'unpaid' },
])
const filteredRows = computed(() => rows.value.filter(row => {
  if (!statusFilter.value) return true
  if (statusFilter.value === 'pending') return !row.is_granted
  return row.is_granted && row.is_paid === (statusFilter.value === 'paid')
}))
const isSelectable = (row: PaidTimeOff) => !row.is_granted
const selectedRows = computed(() => {
  const ids = new Set(selected.value)
  return rows.value.filter(row => ids.has(row.id) && !row.is_granted)
})
const allowanceRisks = computed(() => {
  const paidByEmployee = new Map<number, number>()
  const selectedByEmployee = new Map<number, { name: string; requested: number }>()

  rows.value.forEach(row => {
    if (row.is_granted)
      paidByEmployee.set(row.employee_id, (paidByEmployee.get(row.employee_id) ?? 0) + Number(row.paid_days ?? 0))
  })
  selectedRows.value.forEach(row => {
    const current = selectedByEmployee.get(row.employee_id) ?? { name: row.employee?.name ?? $t('common.leaveApproval.employeeFallback'), requested: 0 }
    current.requested += Number(row.duration_days)
    selectedByEmployee.set(row.employee_id, current)
  })

  return [...selectedByEmployee.entries()].map(([employeeId, selection]) => {
    const used = paidByEmployee.get(employeeId) ?? 0
    return { employeeId, ...selection, used, remaining: Math.max(0, allowance.value - used), excess: Math.max(0, used + selection.requested - allowance.value) }
  }).filter(item => item.excess > 0)
})
const selectionExceedsAllowance = computed(() => allowanceRisks.value.length > 0)

async function load() {
  loading.value = true
  try {
    const result = await PaidTimeOffApi.getAllRequests(year.value)
    rows.value = result.data
    allowance.value = result.allowance
  }
  catch (error) { toast.error(apiErrorMessage(error, translate('pages.paidTimeOff.unableToLoad'))) }
  finally { loading.value = false }
}

async function grantSelected(forcePaid = false) {
  if (!selected.value.length) return
  loading.value = true
  try {
    const result = await PaidTimeOffApi.grant(selected.value, forcePaid, approvalNote.value || undefined)
    toast.success(translate('pages.paidTimeOff.requestsGranted', { count: selected.value.length }))
    if (result.exceeded) toast.warning(translate('pages.paidTimeOff.allowanceExceeded', { count: result.exceeded }))
    selected.value = []
    overrideDialog.value = false
    approvalNote.value = ''
    await load()
  } catch (error) { toast.error(apiErrorMessage(error, translate('pages.paidTimeOff.unableToGrant'))) }
  finally { loading.value = false }
}

function printReport() { void printDocument({ bodyClass: 'printing-paid-time-off', pageStyle: 'size: A4 landscape; margin: 12mm;' }) }
watch(year, load)
watch(rows, currentRows => {
  const pendingIds = new Set(currentRows.filter(isSelectable).map(row => row.id))
  selected.value = selected.value.filter(id => pendingIds.has(id))
})
onMounted(load)
</script>

<template>
  <div class="pto-page pa-4">
    <DataTablePageKpis :items="filteredRows" title="Leave" description="Review full and half-day requests, allowance usage, and authorized paid exceptions." icon="tabler-beach" />
    <VCard class="pto-screen">
      <VCardTitle class="d-flex flex-wrap align-center ga-3 pa-5"><VIcon icon="tabler-beach" />{{ $t('pages.paidTimeOff.title') }}<VSpacer /><DataTableFilterMenu :active="Boolean(statusFilter || year !== new Date().getFullYear())" @reset="statusFilter = null; year = new Date().getFullYear()"><VSelect v-model="statusFilter" :items="statusOptions" :label="$t('common.fields.status')" clearable density="compact" hide-details /><VTextField v-model.number="year" type="number" density="compact" hide-details /></DataTableFilterMenu><VBtn prepend-icon="tabler-printer" variant="tonal" @click="printReport">{{ $t('common.actions.print') }}</VBtn><VBtn v-if="selectionExceedsAllowance" color="warning" variant="flat" prepend-icon="tabler-shield-check" :loading="loading" @click="overrideDialog = true">{{ $t('common.leaveApproval.paidException', { count: selected.length }) }}</VBtn><VBtn v-else prepend-icon="tabler-checks" :disabled="!selected.length" :loading="loading" @click="grantSelected">{{ $t('pages.paidTimeOff.grantSelected', { count: selected.length }) }}</VBtn></VCardTitle>
      <ResponsiveDataTable v-model="selected" show-select item-value="id" :item-selectable="isSelectable" :items="filteredRows" :loading="loading" :headers="[{title:$t('common.fields.employee'),key:'employee.name'},{title:$t('common.fields.date'),key:'date'},{title:'Days',key:'duration_days'},{title:$t('common.fields.reason'),key:'reason'},{title:$t('common.fields.status'),key:'status'},{title:'Approved by',key:'approvedBy.name'},{title:$t('common.messages.actions'),key:'actions',sortable:false,align:'end'}]">
        <template #item.status="{ item }"><VChip :color="!item.is_granted ? 'warning' : item.is_paid ? 'success' : 'error'" size="small">{{ !item.is_granted ? $t('common.statuses.pending') : item.is_paid ? $t('common.statuses.paid') : $t('common.statuses.unpaid') }}</VChip></template>
        <template #item.approvedBy.name="{ item }"><span v-if="item.approvedBy">{{ item.approvedBy.name }}</span><span v-else class="text-medium-emphasis">Pending</span></template>
        <template #item.actions="{ item }"><FullDetailsButton :item="item" :title="item.employee?.name" /></template>
      </ResponsiveDataTable>
    </VCard>
    <section class="pto-print"><header><h1>Paid-off Days Report</h1><p>Calendar year {{ year }}</p></header><table><thead><tr><th>Employee</th><th>Position</th><th>Date</th><th>Reason</th><th>Classification</th></tr></thead><tbody><tr v-for="row in rows" :key="row.id"><td>{{ row.employee?.name }}</td><td>{{ row.employee?.position || '—' }}</td><td>{{ row.date }}</td><td>{{ row.reason }}</td><td>{{ !row.is_granted ? 'Pending' : row.is_paid ? 'Paid' : 'Unpaid' }}</td></tr></tbody></table><footer>Generated {{ formatLocalizedDate(new Date(), 'long') }}</footer></section>
    <VDialog v-model="overrideDialog" max-width="620"><VCard :title="$t('common.leaveApproval.exceptionTitle')"><VCardText><VAlert color="warning" variant="tonal" class="mb-4">{{ $t('common.leaveApproval.exceptionHint') }}</VAlert><div class="d-flex flex-column ga-2 mb-5"><VCard v-for="risk in allowanceRisks" :key="risk.employeeId" border variant="flat"><VCardText class="d-flex flex-wrap align-center ga-3 py-3"><VAvatar color="warning" variant="tonal" icon="tabler-user-exclamation" /><div><div class="font-weight-bold">{{ risk.name }}</div><div class="text-caption text-medium-emphasis">{{ $t('common.leaveApproval.usageSummary', { used: risk.used, remaining: risk.remaining, selected: risk.requested }) }}</div></div><VSpacer /><VChip color="warning" variant="tonal">{{ $t('common.leaveApproval.overLimit', { count: risk.excess }) }}</VChip></VCardText></VCard></div><VTextarea v-model="approvalNote" :label="$t('common.leaveApproval.justification')" rows="4" autofocus /></VCardText><VCardActions><VSpacer /><VBtn variant="text" @click="overrideDialog = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="warning" :loading="loading" :disabled="!approvalNote.trim()" @click="grantSelected(true)">{{ $t('common.leaveApproval.approvePaid') }}</VBtn></VCardActions></VCard></VDialog>
  </div>
</template>

<style scoped>.pto-print{display:none}.pto-print header{border-bottom:3px solid #7367f0;margin-bottom:18px}.pto-print table{width:100%;border-collapse:collapse}.pto-print th{background:#29245c;color:#fff;text-align:start}.pto-print th,.pto-print td{padding:10px;border-bottom:1px solid #ddd}.pto-print tbody tr:nth-child(even){background:#f6f5ff}.pto-print footer{margin-top:24px;color:#666;text-align:end}</style>
<style>@media print{body.printing-paid-time-off .pto-screen{display:none!important}body.printing-paid-time-off .pto-print{display:block!important}body.printing-paid-time-off .layout-navbar,body.printing-paid-time-off .layout-menu,body.printing-paid-time-off .layout-footer{display:none!important}body.printing-paid-time-off .v-main,body.printing-paid-time-off .layout-page-content{padding:0!important;margin:0!important;min-height:0!important}}</style>
