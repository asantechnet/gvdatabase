<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import LeaveRequestApi, { type LeaveRequest, type LeaveRequestStatus } from '@/api/leaveRequests'
import PrintableListReport from '@/components/PrintableListReport.vue'
import ResponsiveDataTableServer from '@/components/responsive-data-table/ResponsiveDataTableServer.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import { printDocument } from '@/utils/printDocument'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'

const toast = useToast()
const { t: $t } = useI18n()
const year = ref(new Date().getFullYear())
const rows = ref<LeaveRequest[]>([])
const selected = ref<number[]>([])
const statusFilter = ref<LeaveRequestStatus | null>(null)
const loading = ref(true)
const search = ref('')
const filterMenuOpen = ref(false)
const draftYear = ref(year.value)
const draftStatus = ref<LeaveRequestStatus | null>(null)
const overrideDialog = ref(false)
const approvalNote = ref('')
const allowance = ref(0)
// The ledger asks the server for one page at a time.
const page = ref(1)
const perPage = ref(25)
const total = ref(0)
// Filled from every row of the year right before printing.
const reportRows = ref<string[][]>([])
// The allowance is resolved per employee by the API; the company default is
// only a fallback for a row whose employee has no personal value.
const allowances = ref<Record<number, number>>({})
// Approved off-day days of the whole year per employee: an approval is checked
// against the employee's real usage, not only the visible page.
const usage = ref<Record<number, number>>({})
const detailFields = computed(() => [
  { key: 'employee.name', label: $t('common.fields.employee') },
  { key: 'date', label: $t('common.fields.date') },
  { key: 'duration_days', label: $t('pages.leaveRequests.duration') },
  { key: 'treatment', label: $t('pages.leaveRequests.treatment') },
  { key: 'off_day_days', label: $t('common.attendanceStatuses.offDay') },
  { key: 'absent_days', label: $t('common.attendanceStatuses.absent') },
  { key: 'reason', label: $t('common.fields.reason') },
  { key: 'approvedBy.name', label: $t('common.ui.approvedBy') },
  { key: 'approval_note', label: $t('common.leaveApproval.justification') },
  { key: 'allowance', label: $t('pages.leaveRequests.allowance') },
  { key: 'used', label: $t('pages.leaveRequests.paidDaysGranted') },
  { key: 'remaining', label: $t('pages.leaveRequests.daysRemaining') },
])

const statusOptions = computed(() => [
  { title: $t('common.statuses.pending'), value: 'pending' },
  { title: $t('common.attendanceStatuses.offDay'), value: 'off_day' },
  { title: $t('pages.leaveRequests.partial'), value: 'partial' },
  { title: $t('common.attendanceStatuses.absent'), value: 'absent' },
])

const isSelectable = (row: LeaveRequest) => !row.is_granted

const selectedRows = computed(() => {
  const ids = new Set(selected.value)

  return rows.value.filter(row => ids.has(row.id) && !row.is_granted)
})

const allowanceRisks = computed(() => {
  const selectedByEmployee = new Map<number, { name: string; requested: number }>()

  selectedRows.value.forEach(row => {
    const current = selectedByEmployee.get(row.employee_id) ?? { name: row.employee?.name ?? $t('common.leaveApproval.employeeFallback'), requested: 0 }

    current.requested += Number(row.duration_days)
    selectedByEmployee.set(row.employee_id, current)
  })

  return [...selectedByEmployee.entries()].map(([employeeId, selection]) => {
    const used = Number(usage.value[employeeId] ?? 0)
    const employeeAllowance = allowances.value[employeeId] ?? allowance.value

    return { employeeId, ...selection, used, remaining: Math.max(0, employeeAllowance - used), excess: Math.max(0, used + selection.requested - employeeAllowance) }
  }).filter(item => item.excess > 0)
})

const selectionExceedsAllowance = computed(() => allowanceRisks.value.length > 0)
const visibleRows = computed(() => {
  const query = search.value.trim().toLocaleLowerCase()
  if (!query)
    return rows.value

  return rows.value.filter(row => [row.employee?.name, row.employee?.position, row.date, row.reason, treatmentLabel(row)]
    .filter(Boolean)
    .some(value => String(value).toLocaleLowerCase().includes(query)))
})
const hasActiveFilters = computed(() => Boolean(statusFilter.value || year.value !== new Date().getFullYear()))
const detailItem = (row: LeaveRequest) => {
  const employeeAllowance = Number(allowances.value[row.employee_id] ?? allowance.value)
  const used = Number(usage.value[row.employee_id] ?? 0)

  return { ...row, allowance: employeeAllowance, used, remaining: Math.max(0, employeeAllowance - used) }
}

async function load() {
  loading.value = true
  try {
    const result = await LeaveRequestApi.listPage({
      year: year.value,
      status: statusFilter.value,
      page: page.value,
      perPage: perPage.value,
    })

    rows.value = result.rows
    total.value = result.total
    allowance.value = result.allowance
    allowances.value = result.allowances ?? {}
    usage.value = result.usage ?? {}
  }
  catch { /* API failure feedback belongs to the global notifier (Task-018). */ }
  finally { loading.value = false }
}

function updateTableOptions(options: { page: number; itemsPerPage: number }) {
  if (page.value === options.page && perPage.value === options.itemsPerPage)
    return

  page.value = options.page
  perPage.value = options.itemsPerPage
  load()
}

async function applyFilters() {
  year.value = draftYear.value
  statusFilter.value = draftStatus.value
  page.value = 1
  filterMenuOpen.value = false
  await load()
}

async function resetFilters() {
  draftYear.value = new Date().getFullYear()
  draftStatus.value = null
  year.value = draftYear.value
  statusFilter.value = null
  page.value = 1
  filterMenuOpen.value = false
  await load()
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
  catch { /* API failure feedback belongs to the global notifier (Task-018). */ }
  finally { loading.value = false }
}

function treatmentLabel(row: LeaveRequest) {
  if (!row.is_granted)
    return $t('common.statuses.pending')
  if (row.treatment === 'partial')
    return $t('pages.leaveRequests.partialWithAbsence', { off: row.off_day_days, absent: row.absent_days })

  return row.treatment === 'off_day' ? $t('common.attendanceStatuses.offDay') : $t('common.attendanceStatuses.absent')
}

// Print is global: the report is filled from every matching row first.
async function printReport() {
  try {
    reportRows.value = (await LeaveRequestApi.getAllRequests(year.value, statusFilter.value)).data.map(row => [
      row.employee?.name ?? '—',
      row.employee?.position ?? '—',
      row.date,
      `${row.duration_days}`,
      row.reason,
      treatmentLabel(row),
    ])
    await printDocument({ bodyClass: 'printing-table-leave-requests', pageStyle: 'size: A4 landscape; margin: 12mm;' })
  }
  catch { /* API failure feedback belongs to the global notifier (Task-018). */ }
}
watch(rows, currentRows => {
  const pendingIds = new Set(currentRows.filter(isSelectable).map(row => row.id))

  selected.value = selected.value.filter(id => pendingIds.has(id))
})
onMounted(load)
</script>

<template>
  <div class="leave-requests-page">
    <ErpPageHeader :title="$t('pages.leaveRequests.title')" section="hr" />
    <VCard class="leave-requests-screen">
      <VCardText class="leave-requests-toolbar">
        <VRow align="center" class="table-toolbar">
          <VCol cols="12" md="6" lg="5"><ErpSearchField v-model="search" :placeholder="$t('pages.leaveRequests.searchRequests')" /></VCol>
          <VCol cols="12" md="6" lg="7" class="d-flex align-center justify-md-end ga-2 flex-wrap">
            <DataTableFilterMenu v-model="filterMenuOpen" :active="hasActiveFilters" @reset="resetFilters">
              <ErpField v-model="draftStatus" kind="select" :items="statusOptions" :label="$t('common.fields.status')" clearable />
              <ErpField v-model.number="draftYear" type="number" :label="$t('pages.leaveRequests.calendarYear', { year: '' })" min="2000" max="3000" />
              <template #actions><VBtn variant="tonal" color="primary" @click="resetFilters">{{ $t('common.messages.reset') }}</VBtn><VBtn color="primary" variant="flat" @click="applyFilters">{{ $t('educational.apply') }}</VBtn></template>
            </DataTableFilterMenu>
            <VBtn icon="tabler-printer" variant="tonal" :aria-label="$t('common.actions.print')" @click="printReport"><VIcon icon="tabler-printer" /><VTooltip activator="parent">{{ $t('common.actions.print') }}</VTooltip></VBtn>
            <VBtn
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
        </VBtn></VCol>
        </VRow>
      </VCardText>
      <ResponsiveDataTableServer
        class="leave-requests-table"
        v-model="selected"
        show-select
        item-value="id"
        :item-selectable="isSelectable"
        :items="visibleRows"
        :items-length="total"
        :page="page"
        :items-per-page="perPage"
        :items-per-page-options="[10, 25, 50, 100]"
        :loading="loading"
        :skeleton-rows="6"
        :show-scroll-affordance="false"
        mobile-cards
        mobile-expand-all
        mobile-title-key="employee.name"
        mobile-action-key="actions"
        :mobile-fields="['date', 'duration_days', 'treatment']"
        :sticky-key-column="false"
        :sticky-action-column="false"
        @update:options="updateTableOptions"
        :headers="[
          { title: $t('common.fields.employee'), key: 'employee.name' },
          { title: $t('common.fields.date'), key: 'date' },
          { title: $t('pages.leaveRequests.duration'), key: 'duration_days' },
          { title: $t('pages.leaveRequests.treatment'), key: 'treatment' },
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
            :item="detailItem(item)"
            :title="item.employee?.name"
            :fields="detailFields"
            mobile-action
            mobile-icon-only
          />
        </template>
        <template #no-data>
          <TableEmptyState icon="tabler-beach" :title="$t('pages.leaveRequests.noRequestsTitle')" :description="$t('pages.leaveRequests.noRequests')" />
        </template>
      </ResponsiveDataTableServer>
    </VCard>
    <PrintableListReport
      :title="$t('pages.leaveRequests.reportTitle')"
      :subtitle="String(year)"
      :columns="[$t('common.fields.employee'), $t('common.messages.position'), $t('common.fields.date'), $t('pages.leaveRequests.duration'), $t('common.fields.reason'), $t('pages.leaveRequests.treatment')]"
      :rows="reportRows"
    />
    <VDialog v-model="overrideDialog" :persistent="loading" max-width="620" content-class="leave-approval-dialog">
      <VCard class="leave-approval-dialog__card">
        <VCardTitle class="leave-approval-dialog__header d-flex align-center justify-space-between px-5 py-4">
          <span>{{ $t('common.leaveApproval.exceptionTitle') }}</span>
          <VBtn class="leave-approval-dialog__close" icon="tabler-x" variant="tonal" color="error" size="small" :aria-label="$t('common.actions.close')" :disabled="loading" @click="overrideDialog = false" />
        </VCardTitle>
        <VCardText class="pa-5">
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
          </div><ErpField
            v-model="approvalNote"
            :label="$t('common.leaveApproval.justification')"
            kind="textarea"
            rows="4"
            autofocus
          />
        </VCardText><VCardActions class="leave-approval-dialog__footer px-5 py-4">
          <VBtn
            color="primary"
            variant="flat"
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

<style scoped>
.leave-requests-page { max-inline-size: 100%; }
.leave-requests-screen { overflow: hidden; border-radius: .85rem !important; box-shadow: 0 3px 14px rgba(var(--v-theme-on-surface), .035) !important; }
.leave-requests-toolbar { padding: 1.25rem 1.5rem !important; }
.leave-requests-screen :deep(.v-data-table__thead > tr > th) { background: rgba(var(--v-theme-on-surface), .045) !important; }
.leave-requests-screen :deep(.v-data-table__tbody > tr:hover > td) { background: rgba(var(--v-theme-on-surface), .035) !important; }
.leave-requests-table :deep(.v-table__wrapper) { overflow-x: auto !important; overflow-y: hidden; scrollbar-width: none; }
.leave-requests-table :deep(.v-table__wrapper::-webkit-scrollbar) { display: none; }
.leave-requests-table :deep(.v-table__wrapper > table) { inline-size: max-content !important; min-inline-size: 132rem; table-layout: auto !important; }
/* Keep the request register readable even when the select/action columns are
   omitted by permissions. These widths therefore deliberately do not depend
   on column position. */
.leave-requests-table :deep(.v-table__wrapper > table > thead > tr > th),
.leave-requests-table :deep(.v-table__wrapper > table > tbody > tr > td) { min-inline-size: 18rem !important; padding-inline: 1.25rem !important; white-space: nowrap !important; }
.leave-requests-table :deep(.v-table__wrapper > table > thead > tr > th:nth-child(1)),
.leave-requests-table :deep(.v-table__wrapper > table > tbody > tr > td:nth-child(1)) { min-inline-size: 22rem !important; }
.leave-requests-table :deep(.v-table__wrapper > table > thead > tr > th:nth-child(2)),
.leave-requests-table :deep(.v-table__wrapper > table > tbody > tr > td:nth-child(2)) { min-inline-size: 18rem !important; }
.leave-requests-table :deep(.v-table__wrapper > table > thead > tr > th:nth-child(5)),
.leave-requests-table :deep(.v-table__wrapper > table > tbody > tr > td:nth-child(5)) { min-inline-size: 26rem !important; }
.leave-approval-dialog__card { overflow: hidden; }
.leave-approval-dialog__header,
.leave-approval-dialog__footer { background: color-mix(in srgb, rgb(var(--v-theme-surface)) 93%, rgb(var(--v-theme-on-surface)) 7%); }
.leave-approval-dialog__header { background: color-mix(in srgb, rgb(var(--v-theme-surface)) 91%, rgb(var(--v-theme-primary)) 9%); }
.leave-approval-dialog__header { border-block-end: 1px solid rgba(var(--v-border-color), .5); font-weight: 750; }
.leave-approval-dialog__close { inline-size: 36px; block-size: 36px; border: 1px solid rgba(var(--v-theme-error), .38) !important; border-radius: 999px !important; background: rgba(var(--v-theme-error), .13) !important; color: rgb(var(--v-theme-error)) !important; }
.leave-approval-dialog__close:hover:not(:disabled) { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }
.leave-approval-dialog__footer { display: flex; justify-content: flex-end; gap: .75rem; border-block-start: 1px solid rgba(var(--v-border-color), .5); }
:global(.v-overlay:has(.leave-approval-dialog) .v-overlay__scrim) { background: rgba(20, 25, 35, .32) !important; backdrop-filter: blur(6px); opacity: 1 !important; }
@media (max-width: 959px) { .leave-requests-toolbar { padding-inline: 1rem !important; } .leave-requests-screen :deep(.v-data-table__tbody > tr:hover > td) { background: transparent !important; } }
</style>
