<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import LeaveRequestApi, { type LeaveRequest } from '@/api/leaveRequests'
import { apiErrorMessage } from '@/utils/apiErrors'
import PrintableListReport from '@/components/PrintableListReport.vue'
import { printDocument } from '@/utils/printDocument'

const toast = useToast()
const { t: $t } = useI18n()
const year = ref(new Date().getFullYear())
const rows = ref<LeaveRequest[]>([])
const selected = ref<number[]>([])
const statusFilter = ref<'pending' | 'off_day' | 'partial' | 'absent' | null>(null)
const loading = ref(false)
const overrideDialog = ref(false)
const approvalNote = ref('')
const allowance = ref(0)
// The allowance is resolved per employee by the API; the company default is
// only a fallback for a row whose employee has no personal value.
const allowances = ref<Record<number, number>>({})

const statusOptions = computed(() => [
  { title: $t('common.statuses.pending'), value: 'pending' },
  { title: $t('common.attendanceStatuses.offDay'), value: 'off_day' },
  { title: $t('pages.leaveRequests.partial'), value: 'partial' },
  { title: $t('common.attendanceStatuses.absent'), value: 'absent' },
])

const filteredRows = computed(() => rows.value.filter(row => {
  if (!statusFilter.value)
    return true
  if (statusFilter.value === 'pending')
    return !row.is_granted

  return row.is_granted && row.treatment === statusFilter.value
}))

const isSelectable = (row: LeaveRequest) => !row.is_granted

const selectedRows = computed(() => {
  const ids = new Set(selected.value)

  return rows.value.filter(row => ids.has(row.id) && !row.is_granted)
})

const allowanceRisks = computed(() => {
  const usedByEmployee = new Map<number, number>()
  const selectedByEmployee = new Map<number, { name: string; requested: number }>()

  rows.value.forEach(row => {
    if (row.is_granted)
      usedByEmployee.set(row.employee_id, (usedByEmployee.get(row.employee_id) ?? 0) + Number(row.off_day_days ?? 0))
  })
  selectedRows.value.forEach(row => {
    const current = selectedByEmployee.get(row.employee_id) ?? { name: row.employee?.name ?? $t('common.leaveApproval.employeeFallback'), requested: 0 }

    current.requested += Number(row.duration_days)
    selectedByEmployee.set(row.employee_id, current)
  })

  return [...selectedByEmployee.entries()].map(([employeeId, selection]) => {
    const used = usedByEmployee.get(employeeId) ?? 0
    const employeeAllowance = allowances.value[employeeId] ?? allowance.value

    return { employeeId, ...selection, used, remaining: Math.max(0, employeeAllowance - used), excess: Math.max(0, used + selection.requested - employeeAllowance) }
  }).filter(item => item.excess > 0)
})

const selectionExceedsAllowance = computed(() => allowanceRisks.value.length > 0)

async function load() {
  loading.value = true
  try {
    const result = await LeaveRequestApi.getAllRequests(year.value)

    rows.value = result.data
    allowance.value = result.allowance
    allowances.value = result.allowances ?? {}
  }
  catch (error) { toast.error(apiErrorMessage(error, $t('pages.leaveRequests.unableToLoad'))) }
  finally { loading.value = false }
}

async function grantSelected(forceOffDay = false) {
  if (!selected.value.length)
    return
  loading.value = true
  try {
    const result = await LeaveRequestApi.grant(selected.value, forceOffDay, approvalNote.value || undefined)

    toast.success($t('pages.leaveRequests.requestsGranted', { count: selected.value.length }))
    if (result.exceeded)
      toast.warning($t('pages.leaveRequests.allowanceExceeded', { count: result.exceeded }))
    selected.value = []
    overrideDialog.value = false
    approvalNote.value = ''
    await load()
  }
  catch (error) { toast.error(apiErrorMessage(error, $t('pages.leaveRequests.unableToGrant'))) }
  finally { loading.value = false }
}

function treatmentLabel(row: LeaveRequest) {
  if (!row.is_granted)
    return $t('common.statuses.pending')
  if (row.treatment === 'partial')
    return $t('pages.leaveRequests.partialWithAbsence', { off: row.off_day_days, absent: row.absent_days })

  return row.treatment === 'off_day' ? $t('common.attendanceStatuses.offDay') : $t('common.attendanceStatuses.absent')
}

function printReport() { void printDocument({ bodyClass: 'printing-table-leave-requests', pageStyle: 'size: A4 landscape; margin: 12mm;' }) }
watch(year, load)
watch(rows, currentRows => {
  const pendingIds = new Set(currentRows.filter(isSelectable).map(row => row.id))

  selected.value = selected.value.filter(id => pendingIds.has(id))
})
onMounted(load)
</script>

<template>
  <div class="leave-requests-page pa-4">
    <DataTablePageKpis
      :items="filteredRows"
      :title="$t('pages.leaveRequests.title')"
      :description="$t('pages.leaveRequests.description')"
      icon="tabler-beach"
    />
    <VCard class="leave-requests-screen">
      <VCardTitle class="d-flex flex-wrap align-center ga-3 pa-5">
        <VIcon icon="tabler-beach" />{{ $t('pages.leaveRequests.title') }}<VSpacer /><DataTableFilterMenu
          :active="Boolean(statusFilter || year !== new Date().getFullYear())"
          @reset="statusFilter = null; year = new Date().getFullYear()"
        >
          <VSelect
            v-model="statusFilter"
            :items="statusOptions"
            :label="$t('common.fields.status')"
            clearable
            density="compact"
            hide-details
          /><VTextField
            v-model.number="year"
            type="number"
            density="compact"
            hide-details
          />
        </DataTableFilterMenu><VBtn
          prepend-icon="tabler-printer"
          variant="tonal"
          @click="printReport"
        >
          {{ $t('common.actions.print') }}
        </VBtn><VBtn
          v-if="selectionExceedsAllowance"
          color="warning"
          variant="flat"
          prepend-icon="tabler-shield-check"
          :loading="loading"
          @click="overrideDialog = true"
        >
          {{ $t('common.leaveApproval.offDayException', { count: selected.length }) }}
        </VBtn><VBtn
          v-else
          prepend-icon="tabler-checks"
          :disabled="!selected.length"
          :loading="loading"
          @click="grantSelected"
        >
          {{ $t('pages.leaveRequests.grantSelected', { count: selected.length }) }}
        </VBtn>
      </VCardTitle>
      <ResponsiveDataTable
        v-model="selected"
        show-select
        item-value="id"
        :item-selectable="isSelectable"
        :items="filteredRows"
        :loading="loading"
        :headers="[
          { title: $t('common.fields.employee'), key: 'employee.name' },
          { title: $t('common.fields.date'), key: 'date' },
          { title: $t('pages.leaveRequests.duration'), key: 'duration_days' },
          { title: $t('pages.leaveRequests.treatment'), key: 'treatment' },
          { title: $t('common.fields.reason'), key: 'reason' },
          { title: $t('common.ui.approvedBy'), key: 'approvedBy.name' },
          { title: $t('common.messages.actions'), key: 'actions', sortable: false, align: 'end' },
        ]"
      >
        <template #item.duration_days="{ item }">
          {{ item.duration_days === 0.5 ? $t('pages.leaveRequests.halfDay') : $t('pages.leaveRequests.fullDay') }}
        </template>
        <template #item.treatment="{ item }">
          <VChip
            :color="!item.is_granted ? 'warning' : item.treatment === 'off_day' ? 'success' : item.treatment === 'partial' ? 'info' : 'error'"
            size="small"
          >
            {{ treatmentLabel(item) }}
          </VChip>
        </template>
        <template #item.approvedBy.name="{ item }">
          <span v-if="item.approvedBy">{{ item.approvedBy.name }}</span><span
            v-else
            class="text-medium-emphasis"
          >{{ $t('common.statuses.pending') }}</span>
        </template>
        <template #item.actions="{ item }">
          <FullDetailsButton
            :item="item"
            :title="item.employee?.name"
          />
        </template>
      </ResponsiveDataTable>
    </VCard>
    <PrintableListReport
      :title="$t('pages.leaveRequests.reportTitle')"
      :subtitle="String(year)"
      :columns="[$t('common.fields.employee'), $t('common.messages.position'), $t('common.fields.date'), $t('pages.leaveRequests.duration'), $t('common.fields.reason'), $t('pages.leaveRequests.treatment')]"
      :rows="filteredRows.map(row => [row.employee?.name, row.employee?.position, row.date, row.duration_days, row.reason, treatmentLabel(row)])"
    />
    <VDialog
      v-model="overrideDialog"
      max-width="620"
    >
      <VCard :title="$t('common.leaveApproval.exceptionTitle')">
        <VCardText>
          <VAlert
            color="warning"
            variant="tonal"
            class="mb-4"
          >
            {{ $t('common.leaveApproval.exceptionHint') }}
          </VAlert><div class="d-flex flex-column ga-2 mb-5">
            <VCard
              v-for="risk in allowanceRisks"
              :key="risk.employeeId"
              border
              variant="flat"
            >
              <VCardText class="d-flex flex-wrap align-center ga-3 py-3">
                <VAvatar
                  color="warning"
                  variant="tonal"
                  icon="tabler-user-exclamation"
                /><div>
                  <div class="font-weight-bold">
                    {{ risk.name }}
                  </div><div class="text-caption text-medium-emphasis">
                    {{ $t('common.leaveApproval.usageSummary', { used: risk.used, remaining: risk.remaining, selected: risk.requested }) }}
                  </div>
                </div><VSpacer /><VChip
                  color="warning"
                  variant="tonal"
                >
                  {{ $t('common.leaveApproval.overLimit', { count: risk.excess }) }}
                </VChip>
              </VCardText>
            </VCard>
          </div><VTextarea
            v-model="approvalNote"
            :label="$t('common.leaveApproval.justification')"
            rows="4"
            autofocus
          />
        </VCardText><VCardActions>
          <VSpacer /><VBtn
            variant="text"
            @click="overrideDialog = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn><VBtn
            color="warning"
            :loading="loading"
            :disabled="!approvalNote.trim()"
            @click="grantSelected(true)"
          >
            {{ $t('common.leaveApproval.approveOffDay') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
