<script setup lang="ts">
import PaidTimeOffApi, { type PaidTimeOff } from '@/api/paidTimeOff'
import { apiErrorMessage } from '@/utils/apiErrors'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const toast = useToast()
const { t: translate } = useI18n()
const year = ref(new Date().getFullYear())
const rows = ref<PaidTimeOff[]>([])
const allowance = ref(0)
const used = ref(0)
const remaining = ref(0)
const pending = ref(0)
const loading = ref(false)
const form = ref({ start_date: new Date().toISOString().slice(0, 10), end_date: new Date().toISOString().slice(0, 10), duration_days: 1 as 0.5 | 1, reason: '' })
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
  } catch (error) { toast.error(apiErrorMessage(error, translate('pages.paidTimeOff.unableToLoad'))) }
  finally { loading.value = false }
}

async function submit() {
  if (!form.value.reason.trim() || requestedDays.value < 1) return
  loading.value = true
  try {
    await PaidTimeOffApi.requestRange({ ...form.value, reason: form.value.reason.trim() })
    toast.success(translate('pages.paidTimeOff.requestSubmitted'))
    form.value.reason = ''
    await load()
  } catch (error) { toast.error(apiErrorMessage(error, translate('pages.paidTimeOff.unableToRequest'))) }
  finally { loading.value = false }
}

watch(() => props.modelValue, open => { if (open) void load() })
watch(year, () => { if (props.modelValue) void load() })
</script>

<template>
  <VDialog v-model="visible" max-width="960" scrollable>
    <VCard class="paid-time-off-dialog">
      <VCardTitle class="pto-header pa-5 pa-sm-6">
        <div class="d-flex align-center ga-4">
          <VAvatar color="primary" variant="tonal" rounded="lg" size="48">
            <VIcon icon="tabler-beach" size="26" />
          </VAvatar>
          <div>
            <div class="text-h6 font-weight-bold">{{ translate('pages.paidTimeOff.myTitle') }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ translate('pages.paidTimeOff.title') }}</div>
          </div>
          <VSpacer />
          <VTextField v-model.number="year" type="number" density="comfortable" hide-details variant="outlined" class="pto-year" />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6">
        <section class="mb-6">
          <div class="d-flex align-center justify-space-between mb-3">
            <div class="text-subtitle-1 font-weight-bold">Leave balance</div>
            <div class="text-caption text-medium-emphasis">{{ year }}</div>
          </div>
          <VRow dense>
            <VCol cols="6" sm="3"><VSheet border rounded="lg" class="pto-stat pa-3"><VIcon icon="tabler-calendar" color="primary" size="20" /><div><div class="text-h6 font-weight-bold">{{ allowance }}</div><div class="text-caption text-medium-emphasis">Allowance</div></div></VSheet></VCol>
            <VCol cols="6" sm="3"><VSheet border rounded="lg" class="pto-stat pa-3"><VIcon icon="tabler-circle-check" color="success" size="20" /><div><div class="text-h6 font-weight-bold">{{ used }}</div><div class="text-caption text-medium-emphasis">Paid days granted</div></div></VSheet></VCol>
            <VCol cols="6" sm="3"><VSheet border rounded="lg" class="pto-stat pa-3"><VIcon icon="tabler-clock-hour-4" color="warning" size="20" /><div><div class="text-h6 font-weight-bold">{{ pending }}</div><div class="text-caption text-medium-emphasis">Pending requests</div></div></VSheet></VCol>
            <VCol cols="6" sm="3"><VSheet border rounded="lg" class="pto-stat pa-3"><VIcon icon="tabler-sparkles" color="info" size="20" /><div><div class="text-h6 font-weight-bold">{{ remaining }}</div><div class="text-caption text-medium-emphasis">Days remaining</div></div></VSheet></VCol>
          </VRow>
        </section>

        <VSheet border rounded="lg" class="pto-request pa-4 pa-sm-5 mb-6">
          <div class="d-flex align-center ga-3 mb-4">
            <VAvatar color="primary" variant="tonal" size="36"><VIcon icon="tabler-plus" /></VAvatar>
            <div><div class="text-subtitle-1 font-weight-bold">Request time off</div><div class="text-caption text-medium-emphasis">Choose your dates and tell us why you need leave.</div></div>
          </div>
          <VRow class="align-end">
            <VCol cols="12" sm="6" md="3"><AppDateTimePicker v-model="form.start_date" :label="translate('pages.paidTimeOff.from')" :config="{ dateFormat: 'Y-m-d' }" /></VCol>
            <VCol cols="12" sm="6" md="3"><AppDateTimePicker v-model="form.end_date" :label="translate('pages.paidTimeOff.to')" :config="{ dateFormat: 'Y-m-d' }" /></VCol>
            <VCol cols="12" md="3"><VTextField v-model="form.reason" :label="translate('common.fields.reason')" maxlength="1000" /></VCol>
            <VCol v-if="requestedDays === 1" cols="12" md="2"><VSelect v-model="form.duration_days" :items="[{ title: 'Full day', value: 1 }, { title: 'Half day', value: 0.5 }]" label="Duration" /></VCol>
            <VCol cols="12" md="3"><VBtn block size="large" :disabled="requestedDays < 1 || requestedDays > remaining || !form.reason.trim()" :loading="loading" @click="submit"><VIcon icon="tabler-send" start />{{ translate('pages.paidTimeOff.requestDays', { count: requestedDays || '' }) }}</VBtn></VCol>
          </VRow>
          <VAlert v-if="requestedDays > remaining" type="warning" variant="tonal" density="comfortable" class="mt-2"><VIcon icon="tabler-alert-triangle" start />{{ translate('pages.paidTimeOff.rangeExceeds', { count: remaining }) }}</VAlert>
        </VSheet>

        <section>
          <div class="d-flex align-center justify-space-between mb-3"><div><div class="text-subtitle-1 font-weight-bold">Request history</div><div class="text-caption text-medium-emphasis">Track the status of your submitted requests.</div></div><VChip size="small" variant="tonal">{{ rows.length }}</VChip></div>
          <VSheet border rounded="lg" class="overflow-hidden">
            <ResponsiveDataTable :items="rows" :headers="[{title:translate('common.fields.date'),key:'date'},{title:translate('common.fields.reason'),key:'reason'},{title:translate('common.fields.status'),key:'status'},{title:translate('common.messages.actions'),key:'actions',sortable:false,align:'end'}]" :loading="loading">
              <template #item.status="{ item }"><VChip :color="!item.is_granted ? 'warning' : item.is_paid ? 'success' : 'error'" size="small" label>{{ !item.is_granted ? translate('common.statuses.pending') : item.is_paid ? translate('common.statuses.paid') : translate('common.statuses.unpaid') }}</VChip></template>
              <template #item.actions="{ item }"><FullDetailsButton :item="item" /></template>
            </ResponsiveDataTable>
          </VSheet>
        </section>
      </VCardText>
      <VCardActions class="pto-footer pa-4 pa-sm-5"><VSpacer /><VBtn variant="tonal" color="secondary" @click="visible=false">{{ translate('common.actions.close') }}</VBtn></VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped lang="scss">
.paid-time-off-dialog {
  overflow: hidden;
}

.pto-header {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary), 0.08), rgb(var(--v-theme-surface)) 58%);
}

.pto-year {
  max-inline-size: 116px;
}

.pto-stat {
  display: flex;
  align-items: center;
  gap: 10px;
  min-block-size: 74px;
  background: rgb(var(--v-theme-surface));
}

.pto-request {
  background: rgb(var(--v-theme-primary), 0.025);
}

.pto-footer {
  border-block-start: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
}

@media (max-width: 599px) {
  .pto-header :deep(.v-text-field) {
    max-inline-size: 88px;
  }
}
</style>
