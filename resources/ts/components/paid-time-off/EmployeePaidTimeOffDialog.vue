<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { apiErrorMessage } from '@/utils/apiErrors'
import LeaveRequestApi, { type LeaveRequest } from '@/api/leaveRequests'
import ErpField from '@/components/erp/ErpField.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'

const props = defineProps<{
  modelValue: boolean
  employee: { id: number; name: string } | null
}>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const toast = useToast()
const { t: translate } = useI18n()
const year = ref(new Date().getFullYear())
const rows = ref<LeaveRequest[]>([])
const allowance = ref(0)
const used = ref(0)
const remaining = ref(0)
const pending = ref(0)
const loading = ref(false)
const editingId = ref<number | null>(null)
const form = ref({ start_date: new Date().toISOString().slice(0, 10), end_date: new Date().toISOString().slice(0, 10), duration_days: 1 as 0.5 | 1, reason: '' })
const visible = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })

// Friday is the configured weekly day off and cannot be requested again.
const requestedDays = computed(() => {
  const start = new Date(`${form.value.start_date}T00:00:00`)
  const end = new Date(`${form.value.end_date}T00:00:00`)

  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start)
    return 0

  let days = 0
  for (const day = new Date(start); day <= end; day.setDate(day.getDate() + 1)) {
    if (day.getDay() !== 5)
      days++
  }

  return days
})

function dockDatePickerCalendar(_: unknown, __: unknown, instance: any) {
  requestAnimationFrame(() => {
    const calendar = instance?.calendarContainer as HTMLElement | undefined

    if (!calendar)
      return

    calendar.style.transform = 'translateY(0) scale(0.86)'
    calendar.style.transformOrigin = 'top left'
    calendar.style.zIndex = '2500'
  })
}

const resetForm = () => {
  editingId.value = null
  form.value = {
    start_date: `${year.value}-01-01`,
    end_date: `${year.value}-01-01`,
    duration_days: 1,
    reason: '',
  }
}

async function load() {
  if (!props.employee)
    return

  loading.value = true
  try {
    const ledger = await LeaveRequestApi.getAll(props.employee.id, year.value)

    rows.value = ledger.data
    allowance.value = ledger.allowance
    used.value = ledger.used
    remaining.value = ledger.remaining
    pending.value = ledger.pending
  }
  catch (error) {
    toast.error(apiErrorMessage(error, translate('pages.leaveRequests.unableToLoad')))
  }
  finally {
    loading.value = false
  }
}

async function submit() {
  if (!props.employee || !form.value.reason.trim() || requestedDays.value < 1)
    return

  loading.value = true
  try {
    if (editingId.value) {
      await LeaveRequestApi.update(props.employee.id, editingId.value, {
        date: form.value.start_date,
        duration_days: form.value.duration_days,
        reason: form.value.reason.trim(),
      })
    }
    else {
      await LeaveRequestApi.createRange(props.employee.id, {
        ...form.value,
        reason: form.value.reason.trim(),
        duration_days: requestedDays.value === 1 ? form.value.duration_days : 1,
      })
    }
    resetForm()
    await load()
  }
  catch (error) {
    toast.error(apiErrorMessage(error, translate('pages.leaveRequests.unableToRequest')))
  }
  finally {
    loading.value = false
  }
}

function edit(row: LeaveRequest) {
  editingId.value = row.id
  form.value = {
    start_date: row.date,
    end_date: row.date,
    duration_days: row.duration_days,
    reason: row.reason,
  }
}

async function remove(row: LeaveRequest) {
  if (!props.employee)
    return

  loading.value = true
  try {
    await LeaveRequestApi.delete(props.employee.id, row.id)
    if (editingId.value === row.id)
      resetForm()
    await load()
  }
  catch (error) {
    toast.error(apiErrorMessage(error, translate('pages.leaveRequests.unableToRequest')))
  }
  finally {
    loading.value = false
  }
}

watch(() => props.modelValue, open => {
  if (open) {
    year.value = new Date().getFullYear()
    resetForm()
    void load()
  }
})
watch(year, () => {
  if (props.modelValue) {
    resetForm()
    void load()
  }
})
</script>

<template>
  <VDialog v-model="visible" max-width="960" scrollable class="paid-time-off-overlay">
    <VCard class="paid-time-off-dialog">
      <VCardTitle class="pto-header pa-5 pa-sm-6">
        <div class="d-flex align-center ga-4">
          <VAvatar color="primary" variant="tonal" rounded="lg" size="48">
            <VIcon icon="tabler-beach" size="26" />
          </VAvatar>
          <div>
            <div class="text-h6 font-weight-bold">{{ translate('pages.settings.staffList.staffList.paidOffDays') }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ props.employee?.name }}</div>
          </div>
          <VSpacer />
          <ErpField v-model.number="year" type="number" :label="translate('pages.leaveRequests.calendarYear', { year })" :placeholder="String(year)" min="2000" max="3000" class="pto-year" />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6">
        <section class="mb-6">
          <div class="d-flex align-center justify-space-between mb-3">
            <div class="text-subtitle-1 font-weight-bold">{{ translate('pages.leaveRequests.balance') }}</div>
            <div class="text-caption text-medium-emphasis">{{ year }}</div>
          </div>
          <VRow dense>
            <VCol cols="6" sm="3"><VSheet border rounded="lg" class="pto-stat pa-3"><VIcon icon="tabler-calendar" color="primary" size="20" /><div><div class="text-h6 font-weight-bold">{{ allowance }}</div><div class="text-caption text-medium-emphasis">{{ translate('pages.leaveRequests.allowance') }}</div></div></VSheet></VCol>
            <VCol cols="6" sm="3"><VSheet border rounded="lg" class="pto-stat pa-3"><VIcon icon="tabler-circle-check" color="success" size="20" /><div><div class="text-h6 font-weight-bold">{{ used }}</div><div class="text-caption text-medium-emphasis">{{ translate('pages.leaveRequests.paidDaysGranted') }}</div></div></VSheet></VCol>
            <VCol cols="6" sm="3"><VSheet border rounded="lg" class="pto-stat pa-3"><VIcon icon="tabler-clock-hour-4" color="warning" size="20" /><div><div class="text-h6 font-weight-bold">{{ pending }}</div><div class="text-caption text-medium-emphasis">{{ translate('pages.leaveRequests.pendingRequests') }}</div></div></VSheet></VCol>
            <VCol cols="6" sm="3"><VSheet border rounded="lg" class="pto-stat pa-3"><VIcon icon="tabler-sparkles" color="info" size="20" /><div><div class="text-h6 font-weight-bold">{{ remaining }}</div><div class="text-caption text-medium-emphasis">{{ translate('pages.leaveRequests.daysRemaining') }}</div></div></VSheet></VCol>
          </VRow>
        </section>

        <VSheet border rounded="lg" class="pto-request pa-4 pa-sm-5 mb-6">
          <div class="d-flex align-center ga-3 mb-4">
            <VAvatar color="primary" variant="tonal" size="36"><VIcon :icon="editingId ? 'tabler-edit' : 'tabler-plus'" /></VAvatar>
            <div>
              <div class="text-subtitle-1 font-weight-bold">{{ editingId ? translate('common.actions.edit') : translate('pages.leaveRequests.requestTimeOff') }}</div>
              <div class="text-caption text-medium-emphasis">{{ translate('pages.leaveRequests.requestHint') }}</div>
            </div>
          </div>
          <VRow class="align-end">
            <VCol cols="12" sm="6" md="3"><div class="erp-field"><label class="erp-field-label">{{ translate('pages.leaveRequests.from') }}</label><AppDateTimePicker v-model="form.start_date" class="erp-field-control" :placeholder="translate('pages.leaveRequests.from')" persistent-placeholder :config="{ dateFormat: 'Y-m-d', static: false, maxDate: form.end_date || undefined, onOpen: dockDatePickerCalendar }" /></div></VCol>
            <VCol cols="12" sm="6" md="3"><div class="erp-field"><label class="erp-field-label">{{ translate('pages.leaveRequests.to') }}</label><AppDateTimePicker v-model="form.end_date" class="erp-field-control" :placeholder="translate('pages.leaveRequests.to')" persistent-placeholder :disabled="Boolean(editingId)" :config="{ dateFormat: 'Y-m-d', static: false, minDate: form.start_date || undefined, onOpen: dockDatePickerCalendar }" /></div></VCol>
            <VCol cols="12" md="3"><ErpField v-model="form.reason" :label="translate('common.fields.reason')" :placeholder="translate('common.fields.reason')" maxlength="1000" /></VCol>
            <VCol cols="12" md="3"><ErpField v-model="form.duration_days" kind="select" :items="[{ title: translate('pages.leaveRequests.fullDay'), value: 1 }, { title: translate('pages.leaveRequests.halfDay'), value: 0.5 }]" :label="translate('pages.leaveRequests.duration')" :placeholder="translate('pages.leaveRequests.duration')" :disabled="requestedDays !== 1" /></VCol>
            <VCol cols="12" class="d-flex justify-end ga-2">
              <VBtn v-if="editingId" variant="tonal" color="secondary" size="small" :disabled="loading" @click="resetForm">{{ translate('common.actions.cancel') }}</VBtn>
              <VBtn size="small" :disabled="requestedDays < 1 || requestedDays > remaining || !form.reason.trim()" :loading="loading" @click="submit"><VIcon :icon="editingId ? 'tabler-check' : 'tabler-send'" start />{{ editingId ? translate('common.actions.save') : translate('pages.leaveRequests.requestDays', { count: requestedDays || '' }) }}</VBtn>
            </VCol>
          </VRow>
        </VSheet>

        <section>
          <div class="d-flex align-center justify-space-between mb-3"><div><div class="text-subtitle-1 font-weight-bold">{{ translate('pages.leaveRequests.historyTitle') }}</div><div class="text-caption text-medium-emphasis">{{ translate('pages.leaveRequests.historySubtitle') }}</div></div><VChip size="small" variant="tonal">{{ rows.length }}</VChip></div>
          <VSheet border rounded="lg" class="overflow-hidden">
            <ResponsiveDataTable
              :items="rows"
              :headers="[{ title: translate('common.fields.date'), key: 'date' }, { title: translate('common.fields.reason'), key: 'reason' }, { title: translate('common.fields.status'), key: 'status' }, { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' }]"
              :loading="loading"
              item-value="id"
              mobile-cards
              mobile-expand-all
              mobile-title-key="date"
              mobile-action-key="actions"
              :mobile-fields="['reason', 'status']"
              :skeleton-rows="5"
              :show-scroll-affordance="false"
            >
              <template #item.status="{ item }"><VChip :color="!item.is_granted ? 'warning' : item.treatment === 'off_day' ? 'success' : item.treatment === 'partial' ? 'info' : 'error'" size="small" label>{{ !item.is_granted ? translate('common.statuses.pending') : item.treatment === 'partial' ? translate('pages.leaveRequests.partial') : item.treatment === 'off_day' ? translate('common.attendanceStatuses.offDay') : translate('common.attendanceStatuses.absent') }}</VChip></template>
              <template #item.actions="{ item }"><div class="d-flex justify-end"><FullDetailsButton :item="item" /><VBtn icon="tabler-edit" size="small" variant="text" :aria-label="translate('common.actions.edit')" @click="edit(item)" /><VBtn icon="tabler-trash" size="small" variant="text" color="error" :aria-label="translate('common.actions.delete')" @click="remove(item)" /></div></template>
              <template #no-data><TableEmptyState icon="tabler-beach" :title="translate('pages.leaveRequests.historyTitle')" :description="translate('pages.leaveRequests.noRequests')" :min-height="240" /></template>
            </ResponsiveDataTable>
          </VSheet>
        </section>
      </VCardText>
      <VCardActions class="pto-footer pa-4 pa-sm-5"><VSpacer /><VBtn variant="tonal" color="secondary" @click="visible = false">{{ translate('common.actions.close') }}</VBtn></VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped lang="scss">
.paid-time-off-dialog { overflow: hidden; }
:global(.paid-time-off-overlay .v-overlay__scrim),
:global(.v-overlay:has(.paid-time-off-dialog) .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), 0.26) !important; backdrop-filter: blur(7px); opacity: 1 !important; }
.pto-header { background: linear-gradient(135deg, rgb(var(--v-theme-primary) / .08), rgb(var(--v-theme-surface)) 58%); }
.pto-year { max-inline-size: 156px; }
.pto-stat { display: flex; align-items: center; gap: 10px; min-block-size: 74px; background: rgb(var(--v-theme-surface)); }
.pto-request { background: rgb(var(--v-theme-primary), 0.025); }
.pto-footer { background: rgb(var(--v-theme-primary) / 0.04); border-block-start: thin solid rgba(var(--v-border-color), var(--v-border-opacity)); }
@media (max-width: 599px) { .pto-header { align-items: flex-start; } .pto-year { max-inline-size: 132px; } }
</style>
