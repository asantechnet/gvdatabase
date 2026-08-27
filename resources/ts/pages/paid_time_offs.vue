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

async function load() {
  loading.value = true
  try { rows.value = await PaidTimeOffApi.getAllRequests(year.value) }
  catch (error) { toast.error(apiErrorMessage(error, translate('pages.paidTimeOff.unableToLoad'))) }
  finally { loading.value = false }
}

async function grantSelected() {
  if (!selected.value.length) return
  loading.value = true
  try {
    const result = await PaidTimeOffApi.grant(selected.value)
    toast.success(translate('pages.paidTimeOff.requestsGranted', { count: selected.value.length }))
    if (result.exceeded) toast.warning(translate('pages.paidTimeOff.allowanceExceeded', { count: result.exceeded }))
    selected.value = []
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
    <DataTablePageKpis :items="filteredRows" title="Paid time off" description="Review leave requests and make outstanding approvals easy to find." icon="tabler-beach" />
    <VCard class="pto-screen">
      <VCardTitle class="d-flex flex-wrap align-center ga-3 pa-5"><VIcon icon="tabler-beach" />{{ $t('pages.paidTimeOff.title') }}<VSpacer /><DataTableFilterMenu :active="Boolean(statusFilter || year !== new Date().getFullYear())" @reset="statusFilter = null; year = new Date().getFullYear()"><VSelect v-model="statusFilter" :items="statusOptions" :label="$t('common.fields.status')" clearable density="compact" hide-details /><VTextField v-model.number="year" type="number" density="compact" hide-details /></DataTableFilterMenu><VBtn prepend-icon="tabler-printer" variant="tonal" @click="printReport">{{ $t('common.actions.print') }}</VBtn><VBtn prepend-icon="tabler-checks" :disabled="!selected.length" :loading="loading" @click="grantSelected">{{ $t('pages.paidTimeOff.grantSelected', { count: selected.length }) }}</VBtn></VCardTitle>
      <ResponsiveDataTable v-model="selected" show-select item-value="id" :item-selectable="isSelectable" :items="filteredRows" :loading="loading" :headers="[{title:$t('common.fields.employee'),key:'employee.name'},{title:$t('common.messages.position'),key:'employee.position'},{title:$t('common.fields.date'),key:'date'},{title:$t('common.fields.reason'),key:'reason'},{title:$t('common.fields.status'),key:'status'},{title:$t('common.messages.actions'),key:'actions',sortable:false,align:'end'}]">
        <template #item.status="{ item }"><VChip :color="!item.is_granted ? 'warning' : item.is_paid ? 'success' : 'error'" size="small">{{ !item.is_granted ? $t('common.statuses.pending') : item.is_paid ? $t('common.statuses.paid') : $t('common.statuses.unpaid') }}</VChip></template>
        <template #item.actions="{ item }"><FullDetailsButton :item="item" :title="item.employee?.name" /></template>
      </ResponsiveDataTable>
    </VCard>
    <section class="pto-print"><header><h1>Paid-off Days Report</h1><p>Calendar year {{ year }}</p></header><table><thead><tr><th>Employee</th><th>Position</th><th>Date</th><th>Reason</th><th>Classification</th></tr></thead><tbody><tr v-for="row in rows" :key="row.id"><td>{{ row.employee?.name }}</td><td>{{ row.employee?.position || '—' }}</td><td>{{ row.date }}</td><td>{{ row.reason }}</td><td>{{ !row.is_granted ? 'Pending' : row.is_paid ? 'Paid' : 'Unpaid' }}</td></tr></tbody></table><footer>Generated {{ formatLocalizedDate(new Date(), 'long') }}</footer></section>
  </div>
</template>

<style scoped>.pto-print{display:none}.pto-print header{border-bottom:3px solid #7367f0;margin-bottom:18px}.pto-print table{width:100%;border-collapse:collapse}.pto-print th{background:#29245c;color:#fff;text-align:start}.pto-print th,.pto-print td{padding:10px;border-bottom:1px solid #ddd}.pto-print tbody tr:nth-child(even){background:#f6f5ff}.pto-print footer{margin-top:24px;color:#666;text-align:end}</style>
<style>@media print{body.printing-paid-time-off .pto-screen{display:none!important}body.printing-paid-time-off .pto-print{display:block!important}body.printing-paid-time-off .layout-navbar,body.printing-paid-time-off .layout-menu,body.printing-paid-time-off .layout-footer{display:none!important}body.printing-paid-time-off .v-main,body.printing-paid-time-off .layout-page-content{padding:0!important;margin:0!important;min-height:0!important}}</style>
