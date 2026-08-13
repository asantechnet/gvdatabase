<script setup lang="ts">
import PaidTimeOffApi, { type PaidTimeOff } from '@/api/paidTimeOff'
import { apiErrorMessage } from '@/utils/apiErrors'
import { computed, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const toast = useToast()
const year = ref(new Date().getFullYear())
const rows = ref<PaidTimeOff[]>([])
const allowance = ref(0)
const used = ref(0)
const remaining = ref(0)
const pending = ref(0)
const loading = ref(false)
const form = ref({ start_date: new Date().toISOString().slice(0, 10), end_date: new Date().toISOString().slice(0, 10), reason: '' })
const visible = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })

const requestedDays = computed(() => {
  const start = new Date(`${form.value.start_date}T00:00:00`)
  const end = new Date(`${form.value.end_date}T00:00:00`)
  return Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start ? 0 : Math.floor((end.getTime() - start.getTime()) / 86400000) + 1
})

async function load() {
  loading.value = true
  try {
    const ledger = await PaidTimeOffApi.mine(year.value)
    rows.value = ledger.data; allowance.value = ledger.allowance; used.value = ledger.used; remaining.value = ledger.remaining; pending.value = ledger.pending
  } catch (error) { toast.error(apiErrorMessage(error, 'Unable to load off days.')) }
  finally { loading.value = false }
}

async function submit() {
  if (!form.value.reason.trim() || requestedDays.value < 1 || requestedDays.value > remaining.value) return
  loading.value = true
  try {
    await PaidTimeOffApi.requestRange({ ...form.value, reason: form.value.reason.trim() })
    toast.success('Off-day request submitted.')
    form.value.reason = ''
    await load()
  } catch (error) { toast.error(apiErrorMessage(error, 'Unable to request off days.')) }
  finally { loading.value = false }
}

watch(() => props.modelValue, open => { if (open) void load() })
watch(year, () => { if (props.modelValue) void load() })
</script>

<template>
  <VDialog v-model="visible" max-width="850">
    <VCard>
      <VCardTitle class="d-flex align-center pa-5"><VIcon icon="tabler-beach" class="me-2" />My paid-off days<VSpacer /><VTextField v-model.number="year" type="number" density="compact" hide-details style="max-width:110px" /></VCardTitle>
      <VDivider />
      <VCardText class="pa-5">
        <div class="d-flex flex-wrap ga-2 mb-5"><VChip color="primary">Limit: {{ allowance }}</VChip><VChip color="success">Granted paid: {{ used }}</VChip><VChip color="warning">Pending: {{ pending }}</VChip><VChip color="info">Left: {{ remaining }}</VChip></div>
        <VRow class="align-end">
          <VCol cols="12" sm="3"><AppDateTimePicker v-model="form.start_date" label="From" :config="{ dateFormat: 'Y-m-d' }" /></VCol>
          <VCol cols="12" sm="3"><AppDateTimePicker v-model="form.end_date" label="To" :config="{ dateFormat: 'Y-m-d' }" /></VCol>
          <VCol cols="12" sm="4"><VTextField v-model="form.reason" label="Reason" maxlength="1000" /></VCol>
          <VCol cols="12" sm="2"><VBtn block :disabled="requestedDays < 1 || requestedDays > remaining || !form.reason.trim()" :loading="loading" @click="submit">Request {{ requestedDays || '' }}</VBtn></VCol>
        </VRow>
        <VAlert v-if="requestedDays > remaining" type="warning" variant="tonal" class="mb-4">This range exceeds your {{ remaining }} remaining day(s).</VAlert>
        <VDataTable :items="rows" :headers="[{title:'Date',key:'date'},{title:'Reason',key:'reason'},{title:'Status',key:'status'}]" :loading="loading">
          <template #item.status="{ item }"><VChip :color="!item.is_granted ? 'warning' : item.is_paid ? 'success' : 'error'" size="small">{{ !item.is_granted ? 'Pending' : item.is_paid ? 'Paid' : 'Unpaid' }}</VChip></template>
        </VDataTable>
      </VCardText>
      <VCardActions class="pa-5 pt-0"><VSpacer /><VBtn variant="tonal" @click="visible=false">Close</VBtn></VCardActions>
    </VCard>
  </VDialog>
</template>
