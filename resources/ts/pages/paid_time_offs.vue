<script setup lang="ts">
import PaidTimeOffApi, { type PaidTimeOff } from '@/api/paidTimeOff'
import { apiErrorMessage } from '@/utils/apiErrors'
import { printDocument } from '@/utils/printDocument'
import { onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'

const toast = useToast()
const year = ref(new Date().getFullYear())
const rows = ref<PaidTimeOff[]>([])
const selected = ref<number[]>([])
const loading = ref(false)

async function load() {
  loading.value = true
  try { rows.value = await PaidTimeOffApi.getAllRequests(year.value) }
  catch (error) { toast.error(apiErrorMessage(error, 'Unable to load paid-off days.')) }
  finally { loading.value = false }
}

async function grantSelected() {
  if (!selected.value.length) return
  loading.value = true
  try {
    const result = await PaidTimeOffApi.grant(selected.value)
    toast.success(`${selected.value.length} request(s) granted.`)
    if (result.exceeded) toast.warning(`${result.exceeded} day(s) exceeded the paid allowance and were recorded as unpaid off-days.`)
    selected.value = []
    await load()
  } catch (error) { toast.error(apiErrorMessage(error, 'Unable to grant selected requests.')) }
  finally { loading.value = false }
}

function printReport() { void printDocument({ bodyClass: 'printing-paid-time-off', pageStyle: 'size: A4 landscape; margin: 12mm;' }) }
watch(year, load)
onMounted(load)
</script>

<template>
  <div class="pto-page pa-4">
    <VCard class="pto-screen">
      <VCardTitle class="d-flex flex-wrap align-center ga-3 pa-5"><VIcon icon="tabler-beach" />Paid-off days<VSpacer /><VTextField v-model.number="year" type="number" density="compact" hide-details style="max-width:110px" /><VBtn prepend-icon="tabler-printer" variant="tonal" @click="printReport">Print</VBtn><VBtn prepend-icon="tabler-checks" :disabled="!selected.length" :loading="loading" @click="grantSelected">Grant selected ({{ selected.length }})</VBtn></VCardTitle>
      <VDataTable v-model="selected" show-select item-value="id" :items="rows" :loading="loading" :headers="[{title:'Employee',key:'employee.name'},{title:'Position',key:'employee.position'},{title:'Date',key:'date'},{title:'Reason',key:'reason'},{title:'Status',key:'status'}]">
        <template #item.status="{ item }"><VChip :color="!item.is_granted ? 'warning' : item.is_paid ? 'success' : 'error'" size="small">{{ !item.is_granted ? 'Pending' : item.is_paid ? 'Paid' : 'Unpaid' }}</VChip></template>
      </VDataTable>
    </VCard>
    <section class="pto-print"><header><h1>Paid-off Days Report</h1><p>Calendar year {{ year }}</p></header><table><thead><tr><th>Employee</th><th>Position</th><th>Date</th><th>Reason</th><th>Classification</th></tr></thead><tbody><tr v-for="row in rows" :key="row.id"><td>{{ row.employee?.name }}</td><td>{{ row.employee?.position || '—' }}</td><td>{{ row.date }}</td><td>{{ row.reason }}</td><td>{{ !row.is_granted ? 'Pending' : row.is_paid ? 'Paid' : 'Unpaid' }}</td></tr></tbody></table><footer>Generated {{ new Date().toLocaleString() }}</footer></section>
  </div>
</template>

<style scoped>.pto-print{display:none}.pto-print header{border-bottom:3px solid #7367f0;margin-bottom:18px}.pto-print table{width:100%;border-collapse:collapse}.pto-print th{background:#29245c;color:#fff;text-align:start}.pto-print th,.pto-print td{padding:10px;border-bottom:1px solid #ddd}.pto-print tbody tr:nth-child(even){background:#f6f5ff}.pto-print footer{margin-top:24px;color:#666;text-align:end}</style>
<style>@media print{body.printing-paid-time-off .pto-screen{display:none!important}body.printing-paid-time-off .pto-print{display:block!important}body.printing-paid-time-off .layout-navbar,body.printing-paid-time-off .layout-menu,body.printing-paid-time-off .layout-footer{display:none!important}body.printing-paid-time-off .v-main,body.printing-paid-time-off .layout-page-content{padding:0!important;margin:0!important;min-height:0!important}}</style>
