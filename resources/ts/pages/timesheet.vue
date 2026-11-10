<script setup lang="ts">
import { translate } from '@/utils/localization'
import { useToast } from 'vue-toastification'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import TimesheetTemplate from './templates/timesheet_template.vue'
import { printDocument } from '@/utils/printDocument'
import AttendanceApi, { type Attendance, type AttendanceOffDay, type DailyReport, type UnmarkedStaff } from '@/api/attendance'
import EmployeeApi, { type Employee } from '@/api/employee'
import OfficeApi, { type Office } from '@/api/office'
import SettingApi from '@/api/setting'
import { calendarDate } from '@/utils/calendarDate'
import { canAccess } from '@/utils/authorization'
import { apiErrorMessage, apiFieldErrors } from '@/utils/apiErrors'
import TableEmptyState from '@/components/TableEmptyState.vue'
import ResponsiveDataTable from '@/components/responsive-data-table/ResponsiveDataTable.vue'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'

// Operational Date Range States (For Filter & Printing)
const startDate = ref<string>(new Date().toISOString().substring(0, 10))
const endDate = ref<string>(new Date().toISOString().substring(0, 10))

const getCurrentDate = () => {
  const date = new Date()

  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

// Attendance records are always added for the current date.
const logDate = ref<string>(getCurrentDate())
const isFriday = (date: string) => new Date(`${date}T00:00:00Z`).getUTCDay() === 5

// Retained for the quick single-entry controls in the attendance panel.
const selectedStaffId = ref<number | null>(null)
const checkInTime = ref('')
const checkOutTime = ref('')
const breakMinutes = ref(0)

type AttendanceDraftStatus = 'present' | 'absent' | 'unmarked'
interface AttendanceDraft {
  staff: UnmarkedStaff
  status: AttendanceDraftStatus
  checkInTime: string
  checkOutTime: string
  breakMinutes: number
}

const employeeFilter = ref<number | null>(null)
const officeFilter = ref<number | null>(null)
const statusFilter = ref<Attendance['status'] | 'incomplete' | null>(null)
const search = ref('')
const filterMenuOpen = ref(false)
const employees = ref<Employee[]>([])
const offices = ref<Office[]>([])
const attendanceRecords = ref<Attendance[]>([])
const unmarkedStaff = ref<UnmarkedStaff[]>([])
const companyOffDay = ref<AttendanceOffDay | null>(null)
const isCompanyOffDay = computed(() => Boolean(companyOffDay.value?.is_off_day))
// Start in a loading state so the table never flashes an empty state before
// its first request begins.
const loading = ref<boolean>(true)
const hasLoadedAttendanceRecords = ref(false)
const rosterLoading = ref<boolean>(false)
const error = ref<string | null>(null)
const toast = useToast()
const employeeFor = (id: number) => employees.value.find(employee => employee.id === id)
const staffName = (id: number) => employeeFor(id)?.name ?? `#${id}`
const reportDialogOpen = ref(false)
const reportLoading = ref(false)
const selectedReport = ref<DailyReport | null>(null)
const reportError = ref<string | null>(null)
const { locale, t } = useI18n()

const timesheetLoadingHeaders = computed(() => [
  { title: t('common.fields.employee'), key: 'employee' },
  { title: t('common.fields.office'), key: 'office' },
  { title: t('common.fields.status'), key: 'status' },
  { title: t('common.messages.inTime'), key: 'check_in' },
  { title: t('common.messages.outTime'), key: 'check_out' },
  { title: t('pages.attendance.timesheet.timesheet.worked'), key: 'worked' },
  { title: t('pages.attendance.timesheet.timesheet.overtime'), key: 'overtime' },
  { title: t('pages.attendance.timesheet.timesheet.action'), key: 'actions', sortable: false, align: 'end' as const },
])

const attendanceRosterLoadingHeaders = computed(() => [
  { title: t('common.fields.employee'), key: 'employee' },
  { title: t('common.fields.status'), key: 'status' },
  { title: t('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

const attendanceDialogOpen = ref(false)
const savingAttendance = ref(false)
const attendanceDrafts = ref<AttendanceDraft[]>([])
const attendanceStaffSearch = ref('')
const officeOpenTime = ref('')
const officeCloseTime = ref('')
const officeBreakMinutes = ref(0)
const deletingRecord = ref<Attendance | null>(null)
const deleteDialogOpen = ref(false)
const deleting = ref(false)
const editingRecord = ref<Attendance | null>(null)
const editDialogOpen = ref(false)
const savingEdit = ref(false)
const editStatus = ref<'present' | 'absent'>('present')
const editCheckInTime = ref('')
const editCheckOutTime = ref('')
const editBreakMinutes = ref(0)

const correctionTarget = ref<Attendance | null>(null)
const correctionOpen = ref(false)
const correcting = ref(false)
const correctionDate = ref('')
const correctionTime = ref('')
const correctionError = ref('')
function openCorrection(record: Attendance) {
  correctionTarget.value = record
  correctionDate.value = record.date
  correctionTime.value = ''
  correctionError.value = ''
  correctionOpen.value = true
}
async function correctCheckout() {
  if (!correctionTarget.value || correcting.value) return
  correcting.value = true
  correctionError.value = ''
  try {
    await AttendanceApi.correctCheckout(correctionTarget.value.id, { date: correctionDate.value, time: correctionTime.value })
    correctionOpen.value = false
    await fetchAttendanceRecords()
    window.dispatchEvent(new Event('workday-status-changed'))
  }
  catch (error) {
    correctionError.value = Object.values(apiFieldErrors(error))[0] || apiErrorMessage(error as Parameters<typeof apiErrorMessage>[0])
  }
  finally { correcting.value = false }
}

// Fetch attendance records within the date range
const fetchAttendanceRecords = async () => {
  try {
    loading.value = true
    attendanceRecords.value = await AttendanceApi.getAll({
      start_date: startDate.value,
      end_date: endDate.value,
      employee_id: employeeFilter.value || undefined,
      office_id: officeFilter.value || undefined,
      status: statusFilter.value || undefined,
    })
  }
  catch (err) {
    error.value = translate('pages.attendance.timesheet.timesheet.failedToFetchAttendanceRecords')
    console.error(err)
  }
  finally {
    loading.value = false
    hasLoadedAttendanceRecords.value = true
  }
}

const openEditAttendance = (record: Attendance) => {
  editingRecord.value = record
  editStatus.value = record.status === 'absent' ? 'absent' : 'present'
  editCheckInTime.value = record.check_in_time ?? ''
  editCheckOutTime.value = record.check_out_time ?? ''
  editBreakMinutes.value = record.break_minutes ?? 0
  editDialogOpen.value = true
}

const saveAttendanceEdit = async () => {
  if (!editingRecord.value || savingEdit.value)
    return

  savingEdit.value = true
  try {
    await AttendanceApi.update(editingRecord.value.id, {
      status: editStatus.value,
      check_in_time: editStatus.value === 'present' ? editCheckInTime.value || null : null,
      check_out_time: editStatus.value === 'present' ? editCheckOutTime.value || null : null,
      break_minutes: editStatus.value === 'present' ? editBreakMinutes.value : 0,
    })
    editDialogOpen.value = false
    await fetchAttendanceRecords()
  }
  catch (err) {
    toast.error(apiErrorMessage(err, translate('pages.attendance.timesheet.timesheet.failedToLogAttendance')))
  }
  finally {
    savingEdit.value = false
  }
}

const loadCompanyOffDay = async () => {
  try {
    companyOffDay.value = await AttendanceApi.getOffDay(logDate.value)
  }
  catch {
    // Keep the logger usable when the off-day lookup is unavailable.
    companyOffDay.value = null
  }
}

// Fetch unmarked staff for the selected LOG date
const fetchUnmarkedStaff = async () => {
  if (isFriday(logDate.value) || isCompanyOffDay.value) {
    unmarkedStaff.value = []

    return
  }

  try {
    rosterLoading.value = true

    const response = await AttendanceApi.getUnmarkedStaff(logDate.value)

    unmarkedStaff.value = response.map(staff => ({
      id: staff.id,
      name: staff.name,
      position: staff.position || staff.role || translate('common.crmUi.unassigned'),
    }))
  }
  catch (err) {
    error.value = translate('pages.attendance.timesheet.timesheet.failedToFetchUnmarkedStaff')
    console.error(err)
  }
  finally {
    rosterLoading.value = false
  }
}

// Log attendance for an employee on the dedicated logDate
const logAttendance = async (status: 'present' | 'absent') => {
  if (!selectedStaffId.value)
    return
  if (isFriday(logDate.value)) {
    error.value = 'Friday is the Afghanistan weekend. Attendance cannot be recorded.'

    return
  }
  if (isCompanyOffDay.value) {
    const message = translate('pages.attendance.timesheet.timesheet.offDayNoAttendance')

    error.value = message
    toast.warning(message)

    return
  }

  try {
    loading.value = true
    await AttendanceApi.create({
      staff_id: selectedStaffId.value,
      date: logDate.value,
      status,
      check_in_time: status === 'present' ? checkInTime.value || null : null,
      check_out_time: status === 'present' ? checkOutTime.value || null : null,
      break_minutes: status === 'present' ? breakMinutes.value : null,
    })

    toast.success(`${staffName(selectedStaffId.value!)} · ${logDate.value} · ${status === 'present' ? translate('common.statuses.present') : translate('pages.attendance.timesheet.timesheet.absent')}`)

    // Refresh data after logging
    await fetchAttendanceRecords()
    await fetchUnmarkedStaff()
    selectedStaffId.value = null

    const setting = (await SettingApi.getCompanyProfile()).data

    checkInTime.value = setting?.office_open_time || ''
    checkOutTime.value = setting?.office_close_time || ''
    breakMinutes.value = setting?.default_break_minutes || 0
  }
  catch (err) {
    const message = apiErrorMessage(err, translate('pages.attendance.timesheet.timesheet.failedToLogAttendance'))

    error.value = message
    toast.error(`${staffName(selectedStaffId.value!)} · ${logDate.value} · ${message}`)
    console.error(err)
  }
  finally {
    loading.value = false
  }
}

const makeAttendanceDrafts = () => {
  attendanceDrafts.value = unmarkedStaff.value.map(staff => ({
    staff,
    status: 'present',
    checkInTime: officeOpenTime.value,
    checkOutTime: officeCloseTime.value,
    breakMinutes: officeBreakMinutes.value,
  }))
}

const openAttendanceDialog = async () => {
  error.value = null
  attendanceStaffSearch.value = ''
  await fetchUnmarkedStaff()
  makeAttendanceDrafts()
  attendanceDialogOpen.value = true
}

const attendanceFieldsDisabled = (draft: AttendanceDraft) => draft.status !== 'present'

const saveAllAttendance = async () => {
  const draftsToSave = attendanceDrafts.value.filter(draft => draft.status !== 'unmarked')
  if (!draftsToSave.length || savingAttendance.value || isFriday(logDate.value))
    return

  if (isCompanyOffDay.value) {
    const message = translate('pages.attendance.timesheet.timesheet.offDayNoAttendance')

    error.value = message
    toast.warning(message)

    return
  }

  savingAttendance.value = true
  error.value = null
  const results = await Promise.allSettled(draftsToSave.map(draft => AttendanceApi.create({
    staff_id: draft.staff.id,
    date: logDate.value,
    status: draft.status as 'present' | 'absent',
    check_in_time: draft.status === 'present' ? draft.checkInTime || null : null,
    check_out_time: draft.status === 'present' ? draft.checkOutTime || null : null,
    break_minutes: draft.status === 'present' ? draft.breakMinutes : null,
  })))
  const failed = results.filter(result => result.status === 'rejected')

  if (failed.length) {
    const reason = apiErrorMessage((failed[0] as PromiseRejectedResult).reason, translate('pages.attendance.timesheet.timesheet.failedToLogAttendance'))
    error.value = reason
    toast.error(reason)
  }

  const saved = results.length - failed.length
  if (saved)
    toast.success(t('pages.attendance.timesheet.timesheet.attendanceSaved', { count: saved }))

  await Promise.all([fetchAttendanceRecords(), fetchUnmarkedStaff()])
  makeAttendanceDrafts()
  if (!failed.length)
    attendanceDialogOpen.value = false
  savingAttendance.value = false
}

// Delete confirmation keeps a destructive action separate from the row icon.
const requestDelete = (record: Attendance) => {
  deletingRecord.value = record
  deleteDialogOpen.value = true
}

const deleteRecord = async () => {
  if (!deletingRecord.value || deleting.value)
    return

  try {
    deleting.value = true
    await AttendanceApi.delete(deletingRecord.value.id)

    toast.success(translate('common.actions.delete'))

    // Refresh data after deletion
    await fetchAttendanceRecords()
    await fetchUnmarkedStaff()
    deleteDialogOpen.value = false
    deletingRecord.value = null
  }
  catch (err) {
    const message = apiErrorMessage(err, translate('pages.attendance.timesheet.timesheet.failedToDeleteAttendanceRecord'))

    error.value = message
    toast.error(message)
    console.error(err)
  }
  finally {
    deleting.value = false
  }
}

// A report is deliberately requested only after its row action is clicked.
const viewDailyReport = async (attendance: Attendance) => {
  reportDialogOpen.value = true
  reportLoading.value = true
  selectedReport.value = null
  reportError.value = null

  try {
    selectedReport.value = await AttendanceApi.getDailyReport(attendance.id)
  }
  catch (err) {
    reportError.value = translate('pages.attendance.timesheet.timesheet.failedToFetchDailyReport')
    console.error(err)
  }
  finally {
    reportLoading.value = false
  }
}

// Trigger printable report engine
const printTimesheet = () => {
  void printDocument({
    bodyClass: 'printing-timesheet',
    element: document.querySelector<HTMLElement>('.timesheet-print-host'),
    pageStyle: 'size: A4 portrait; margin: 0;',
  })
}

// Computed properties
const availableStaff = computed(() => {
  return unmarkedStaff.value
})

const timeOptions = Array.from({ length: 96 }, (_, index) => {
  const hours = Math.floor(index / 4)
  const minutes = (index % 4) * 15
  const value = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`

  return { title: value, value }
})

const displayedRecords = computed(() => {
  const query = search.value.trim().toLocaleLowerCase()

  return attendanceRecords.value
    .filter(record => record.date >= startDate.value && record.date <= endDate.value)
    .filter(record => !query || [record.name, record.role, record.office, record.status, record.date]
      .filter(Boolean)
      .some(value => String(value).toLocaleLowerCase().includes(query)))
    .sort((a, b) => b.date.localeCompare(a.date) || b.timestamp.localeCompare(a.timestamp))
})

const visibleAttendanceDrafts = computed(() => {
  const query = attendanceStaffSearch.value.trim().toLocaleLowerCase()

  return attendanceDrafts.value.filter(draft => !query || [draft.staff.name, draft.staff.position]
    .filter(Boolean)
    .some(value => String(value).toLocaleLowerCase().includes(query)))
})

const hasActiveFilters = computed(() => Boolean(employeeFilter.value || officeFilter.value || statusFilter.value || startDate.value !== endDate.value))

const stats = computed(() => {
  const present = displayedRecords.value.filter(r => r.status === 'present').length
  const absent = displayedRecords.value.filter(r => r.status === 'absent').length
  const totalInView = displayedRecords.value.length

  const pendingOnLogDate = unmarkedStaff.value.length

  const workedMinutes = displayedRecords.value.reduce((sum, row) => sum + (row.net_worked_minutes ?? 0), 0)
  const overtimeMinutes = displayedRecords.value.reduce((sum, row) => sum + row.overtime_minutes, 0)
  return { present, absent, totalInView, pendingOnLogDate, workedMinutes, overtimeMinutes }
})

const formatDuration = (minutes: number | null) => {
  if (minutes === null)
    return '—'
  const hours = Math.floor(minutes / 60)
  const remainder = minutes % 60
  const hoursText = new Intl.NumberFormat(locale.value, { style: 'unit', unit: 'hour', unitDisplay: 'narrow' }).format(hours)
  const minutesText = new Intl.NumberFormat(locale.value, { style: 'unit', unit: 'minute', unitDisplay: 'narrow' }).format(remainder)

  return `${hoursText} ${minutesText}`
}

const formatTime = (instant: string | null, fallback: string | null, timezone: string) => {
  if (!instant)
    return fallback

  return new Intl.DateTimeFormat(locale.value, { hour: '2-digit', minute: '2-digit', timeZone: timezone }).format(new Date(instant))
}

const timeColors: Record<NonNullable<Attendance['check_in_state'] | Attendance['check_out_state']>, string> = {
  on_time: 'success',
  early: 'teal-darken-1',
  overtime: 'green-darken-2',
  late: 'error',
  early_departure: 'error',
}

const timeColor = (state: Attendance['check_in_state'] | Attendance['check_out_state']) => state ? timeColors[state] : 'secondary'

const statusColor = (status: Attendance['status']) => ({ 'present': 'success', 'absent': 'error', 'off_day': 'info' }[status])
const statusIcon = (status: Attendance['status']) => ({ 'present': 'tabler-circle-check', 'absent': 'tabler-circle-x', 'off_day': 'tabler-beach' }[status])

// Company holidays and fully allowance-covered leave are attendance ledger
// entries. The API rejects their deletion; only an actual worked portion of
// an approved leave can be removed while preserving the leave claim.
const canDeleteAttendance = (record: Attendance) => canAccess('delete_attendance')
  && record.status !== 'off_day'
  && !record.off_day_id
  && (!record.leave_request_id || Boolean(record.checked_in_at || record.checked_out_at || record.check_in_time || record.check_out_time))

const timeHints: Record<NonNullable<Attendance['check_in_state'] | Attendance['check_out_state']>, string> = {
  early: 'Early arrival',
  on_time: 'On time',
  late: 'Late arrival',
  early_departure: 'Left before office closing time',
  overtime: 'Left after office closing time',
}

const timeHint = (state: Attendance['check_in_state'] | Attendance['check_out_state']) => state ? timeHints[state] : 'Office hours are not configured'

const applyFilters = async () => {
  if (startDate.value > endDate.value)
    return

  filterMenuOpen.value = false
  await fetchAttendanceRecords()
}

const resetFilters = async () => {
  const today = logDate.value
  startDate.value = today
  endDate.value = today
  employeeFilter.value = null
  officeFilter.value = null
  statusFilter.value = null
  filterMenuOpen.value = false
  await fetchAttendanceRecords()
}

// Lifecycle hooks
onMounted(async () => {
  const [employeeResponse, officeResponse, settingResponse] = await Promise.all([EmployeeApi.getAll(), OfficeApi.getAll(), SettingApi.getCompanyProfile()])

  employees.value = employeeResponse.data.data
  offices.value = officeResponse
  const today = calendarDate(settingResponse.data?.timezone || 'UTC')
  startDate.value = today
  endDate.value = today
  logDate.value = today
  await loadCompanyOffDay()
  checkInTime.value = settingResponse.data?.office_open_time || ''
  checkOutTime.value = settingResponse.data?.office_close_time || ''
  breakMinutes.value = settingResponse.data?.default_break_minutes || 0
  officeOpenTime.value = settingResponse.data?.office_open_time || ''
  officeCloseTime.value = settingResponse.data?.office_close_time || ''
  officeBreakMinutes.value = settingResponse.data?.default_break_minutes || 0
  await Promise.all([fetchAttendanceRecords(), fetchUnmarkedStaff()])
})

// Watch range changes for attendance fetching
watch([startDate, endDate, employeeFilter, officeFilter, statusFilter], async () => {
  await fetchAttendanceRecords()
})

// Watch target logging date changes to get relevant unmarked staff
watch(logDate, async () => {
  await loadCompanyOffDay()
  await fetchUnmarkedStaff()
})
</script>

<template>
  <div class="erp-workspace attendance-container">
    <!-- PRINT ENGINE COMPONENT (Visible ONLY during print output) -->
    <div
      class="timesheet-print-host"
      aria-hidden="true"
    >
      <TimesheetTemplate
        :attendances="displayedRecords"
        :start-date="startDate"
        :end-date="endDate"
        :total-worked-minutes="stats.workedMinutes"
        :total-overtime-minutes="stats.overtimeMinutes"
      />
    </div>

    <!-- MAIN INTERACTIVE UI (Hidden during print output) -->
    <div class="timesheet-screen">
      <ErpPageHeader :title="$t('common.navigation.timesheet')" section="hr" />

      <VCard class="timesheet-table-shell">
        <VCardText class="timesheet-table-toolbar">
          <VRow align="center" class="table-toolbar">
            <VCol cols="12" md="6" lg="5">
              <ErpSearchField
                v-model="search"
                :placeholder="$t('common.actions.search')"
                @keyup.enter="applyFilters"
              />
            </VCol>
            <VCol cols="12" md="6" lg="7" class="table-toolbar-actions d-flex flex-nowrap justify-end gap-2">
              <DataTableFilterMenu v-model="filterMenuOpen" :active="hasActiveFilters" :show-reset="false">
                <ErpDateRange v-model:from="startDate" v-model:to="endDate" :show-apply="false" />
                <ErpField v-model="employeeFilter" kind="select" :items="employees" item-title="name" item-value="id" :label="$t('common.fields.employee')" clearable />
                <ErpField v-model="officeFilter" kind="select" :items="offices" item-title="name" item-value="id" :label="$t('common.fields.office')" clearable />
                <ErpField
                  v-model="statusFilter"
                  kind="select"
                  :items="[{ title: translate('common.statuses.present'), value: 'present' }, { title: translate('pages.attendance.timesheet.timesheet.absent'), value: 'absent' }, { title: translate('common.attendanceStatuses.offDay'), value: 'off_day' }, { title: translate('pages.attendance.timesheet.timesheet.incomplete'), value: 'incomplete' }]"
                  :label="$t('common.messages.filterByStatus')"
                  clearable
                />
                <template #actions>
                  <VBtn variant="tonal" color="primary" @click="resetFilters">{{ $t('common.messages.reset') }}</VBtn>
                  <VBtn color="primary" variant="flat" data-filter-apply @click="applyFilters">{{ $t('educational.apply') }}</VBtn>
                </template>
              </DataTableFilterMenu>
              <VBtn icon="tabler-printer" variant="tonal" class="flex-shrink-0" :aria-label="$t('common.actions.print')" @click="printTimesheet">
                <VIcon icon="tabler-printer" />
                <VTooltip activator="parent" location="top">{{ $t('common.actions.print') }}</VTooltip>
              </VBtn>
              <VBtn color="primary" class="flex-shrink-0" :disabled="loading" @click="openAttendanceDialog">
                <VIcon icon="tabler-user-check" class="me-1" />
                {{ $t('pages.attendance.timesheet.steps.logAttendance') }}
              </VBtn>
            </VCol>
          </VRow>
        </VCardText>

      <!-- TOP ROW: Range Metric Indicators -->
      <VRow v-if="false" class="timesheet-kpi-strip mb-6">
        <VCol
          cols="auto"
        >
          <VCard
            elevation="2"
            class="stats-card border-inline-start-success"
          >
            <VCardText class="stats-card__body">
              <div class="stats-card__copy">
                <h6 class="stats-card__label">
                  {{ $t('pages.attendance.timesheet.timesheet.totalPRESENTRANGE') }}
                </h6>
                <div class="stats-card__value text-success">
                  {{ stats.present }}
                </div>
              </div>
              <VAvatar
                color="success"
                variant="tonal"
                rounded
                size="42"
              >
                <VIcon
                  icon="tabler-square-check"
                  size="22"
                />
              </VAvatar>
            </VCardText>
          </VCard>
        </VCol>
        <VCol
          cols="auto"
        >
          <VCard
            elevation="2"
            class="stats-card border-inline-start-primary"
          >
            <VCardText class="stats-card__body">
              <div class="stats-card__copy">
                <h6 class="stats-card__label">
                  {{ $t('pages.attendance.timesheet.timesheet.totalWORKED') }}
                </h6>
                <div class="stats-card__value">
                  {{ formatDuration(stats.workedMinutes) }}
                </div>
              </div>
              <VAvatar color="primary" variant="tonal" rounded size="42">
                <VIcon icon="tabler-clock" size="22" />
              </VAvatar>
            </VCardText>
          </VCard>
        </VCol>
        <VCol
          cols="auto"
        >
          <VCard
            elevation="2"
            class="stats-card border-inline-start-success"
          >
            <VCardText class="stats-card__body">
              <div class="stats-card__copy">
                <h6 class="stats-card__label">
                  {{ $t('pages.attendance.timesheet.timesheet.overtime') }}
                </h6>
                <div class="stats-card__value text-success">
                  {{ formatDuration(stats.overtimeMinutes) }}
                </div>
              </div>
              <VAvatar color="success" variant="tonal" rounded size="42">
                <VIcon icon="tabler-clock-plus" size="22" />
              </VAvatar>
            </VCardText>
          </VCard>
        </VCol>
        <VCol
          cols="auto"
        >
          <VCard
            elevation="2"
            class="stats-card border-inline-start-error"
          >
            <VCardText class="stats-card__body">
              <div class="stats-card__copy">
                <h6 class="stats-card__label">
                  {{ $t('pages.attendance.timesheet.timesheet.totalABSENTRANGE') }}
                </h6>
                <div class="stats-card__value text-error">
                  {{ stats.absent }}
                </div>
              </div>
              <VAvatar
                color="error"
                variant="tonal"
                rounded
                size="42"
              >
                <VIcon
                  icon="tabler-square-x"
                  size="22"
                />
              </VAvatar>
            </VCardText>
          </VCard>
        </VCol>

      </VRow>

      <!-- MAIN GRID CONTAINER -->
      <VRow>
        <!-- LEFT COLUMN: Control Panels -->
        <VCol
          v-if="false"
          cols="12"
          lg="4"
        >
          <!-- 1. Interactive Roll Logger (Decoupled from start date filter) -->
          <VCard
            class="pa-5 mb-6"
            elevation="3"
          >
            <div class="d-flex align-center gap-3 mb-4">
              <VAvatar
                color="primary"
                variant="flat"
                size="36"
              >
                <VIcon
                  icon="tabler-user-plus"
                  size="20"
                  color="white"
                />
              </VAvatar>
              <h3 class="text-h5 font-weight-bold">
                {{ $t('pages.attendance.timesheet.steps.logAttendance') }}
              </h3>
            </div>
            <VAlert
              v-if="isFriday(logDate)"
              type="info"
              variant="tonal"
              class="mb-4"
            >
              {{ $t('common.ui.fridayIsTheAfghanistanWeekendAttendanceIsNotRequiredOrRecordedToday') }}
            </VAlert>
            <VAlert
              v-if="isCompanyOffDay && !isFriday(logDate)"
              type="info"
              variant="tonal"
              class="mb-4"
            >
              {{ $t('pages.attendance.timesheet.timesheet.offDayNoAttendance') }}
              <template v-if="companyOffDay?.reason">
                — {{ companyOffDay?.reason }}
              </template>
            </VAlert>

            <!-- Dropdown Staff Selection -->
            <VSelect
              v-model="selectedStaffId"
              :items="availableStaff"
              item-title="name"
              item-value="id"
              :label="$t('pages.attendance.timesheet.timesheet.selectEmployee')"
              :placeholder="$t('pages.attendance.timesheet.timesheet.searchOrPickAnOption')"
              variant="outlined"
              density="comfortable"
              class="mb-5"
              :loading="loading"
              :disabled="isFriday(logDate) || isCompanyOffDay"
              :no-data-text="stats.pendingOnLogDate === 0 ? '🎉 All staff filled for this date!' : 'Select an employee'"
            >
              <template #item="{ props, item }">
                <VListItem
                  v-bind="props"
                  :title="item.raw.name"
                  :subtitle="item.raw.position"
                >
                  <template #prepend>
                    <VAvatar
                      color="primary"
                      variant="tonal"
                      size="32"
                      class="text-body-2 font-weight-bold"
                    >
                      {{ item.raw.name.charAt(0) }}
                    </VAvatar>
                  </template>
                </VListItem>
              </template>
            </VSelect>

            <VRow class="mb-2">
              <VCol cols="6">
                <AppSelect
                  v-model="checkInTime"
                  :items="timeOptions"
                  :label="$t('common.messages.inTime')"
                  density="comfortable"
                  hide-details
                  clearable
                  :disabled="isFriday(logDate) || isCompanyOffDay"
                />
              </VCol>
              <VCol cols="6">
                <AppSelect
                  v-model="checkOutTime"
                  :items="timeOptions"
                  :label="$t('common.messages.outTime')"
                  density="comfortable"
                  hide-details
                  clearable
                  :disabled="isFriday(logDate) || isCompanyOffDay"
                />
              </VCol>
            </VRow>
            <VTextField
              v-model.number="breakMinutes"
              :label="$t('pages.attendance.timesheet.timesheet.breakMinutes')"
              type="number"
              min="0"
              max="720"
              class="mb-3"
              :disabled="isFriday(logDate) || isCompanyOffDay"
            />

            <!-- Logger Submission Buttons -->
            <VRow class="ma-0 ga-3">
              <VCol class="pa-0">
                <VBtn
                  color="success"
                  block
                  size="large"
                  variant="flat"
                  :disabled="!selectedStaffId || loading || isFriday(logDate) || isCompanyOffDay"
                  class="action-btn-success font-weight-bold"
                  @click="logAttendance('present')"
                >
                  <VIcon
                    icon="tabler-check"
                    class="mr-2"
                    size="18"
                  />
                  {{ $t('common.statuses.present') }}
                </VBtn>
              </VCol>

              <VCol class="pa-0">
                <VBtn
                  color="error"
                  block
                  size="large"
                  variant="flat"
                  :disabled="!selectedStaffId || loading || isFriday(logDate) || isCompanyOffDay"
                  class="action-btn-error font-weight-bold"
                  @click="logAttendance('absent')"
                >
                  <VIcon
                    icon="tabler-x"
                    class="mr-2"
                    size="18"
                  />
                  {{ $t('pages.attendance.timesheet.timesheet.absent') }}
                </VBtn>
              </VCol>
            </VRow>
          </VCard>

          <!-- 2. Range Configuration & Printing -->
          <VCard
            class="pa-5 mb-6"
            elevation="3"
          >
            <div class="d-flex align-center gap-3 mb-4">
              <VAvatar
                color="primary"
                variant="flat"
                size="36"
              >
                <VIcon
                  icon="tabler-calendar"
                  size="20"
                  color="white"
                />
              </VAvatar>
              <h3 class="text-h5 font-weight-bold">
                {{ $t('pages.attendance.timesheet.steps.printRangeFilter') }}
              </h3>
            </div>

            <VRow class="mb-4">
              <VCol
                cols="12"
                sm="6"
                lg="12"
                class="py-1"
              >
                <AppDateTimePicker
                  v-model="startDate"
                  :label="$t('common.fields.startDate')"
                  density="comfortable"
                  variant="outlined"
                  hide-details
                  :loading="loading"
                  :config="{ dateFormat: 'Y-m-d' }"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
                lg="12"
                class="py-1"
              >
                <AppDateTimePicker
                  v-model="endDate"
                  :label="$t('common.fields.endDate')"
                  density="comfortable"
                  variant="outlined"
                  hide-details
                  :loading="loading"
                  :config="{ dateFormat: 'Y-m-d' }"
                />
              </VCol>
            </VRow>
            <VSelect
              v-model="employeeFilter"
              :items="employees"
              item-title="name"
              item-value="id"
              :label="$t('common.fields.employee')"
              clearable
              class="mb-2"
            />
            <VSelect
              v-model="officeFilter"
              :items="offices"
              item-title="name"
              item-value="id"
              :label="$t('common.fields.office')"
              clearable
              class="mb-2"
            />
            <VSelect
              v-model="statusFilter"
              :items="[{ title: translate('common.statuses.present'), value: 'present' }, { title: translate('pages.attendance.timesheet.timesheet.absent'), value: 'absent' }, { title: translate('common.attendanceStatuses.offDay'), value: 'off_day' }, { title: translate('pages.attendance.timesheet.timesheet.incomplete'), value: 'incomplete' }]"
              :label="$t('common.fields.status')"
              clearable
              class="mb-2"
            />

            <VBtn
              color="primary"
              variant="elevated"
              size="large"
              block
              class="font-weight-bold shadow-primary mt-2"
              :loading="loading"
              @click="printTimesheet"
            >
              <VIcon
                icon="tabler-printer"
                class="mr-2"
                size="20"
              />
              {{ $t('pages.attendance.timesheet.timesheet.printTimesheetReport') }}
            </VBtn>
          </VCard>
        </VCol>

        <!-- RIGHT COLUMN: Screen Output View List -->
        <VCol
          cols="12"
          lg="12"
        >
          <div class="timesheet-ledger">
            <!-- Live Attendance Log Grid List -->
            <div class="attendance-table-wrapper">
              <ResponsiveDataTable
                v-if="loading || !hasLoadedAttendanceRecords"
                :headers="timesheetLoadingHeaders"
                :items="[]"
                :loading="true"
                :skeleton-rows="6"
                :sticky-key-column="false"
                :sticky-action-column="false"
                mobile-cards
                mobile-title-key="employee"
                :mobile-fields="['office', 'status']"
                class="timesheet-loading-table"
              />
              <table
                v-else
                class="custom-attendance-table timesheet-desktop-table w-100"
              >
                <thead>
                  <tr>
                    <th class="text-left py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">
                      {{ $t('common.fields.employee') }}
                    </th>
                    <th class="text-left py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">
                      {{ $t('common.fields.office') }}
                    </th>
                    <th class="text-center py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">
                      {{ $t('common.fields.status') }}
                    </th>
                    <th class="text-center py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">
                      {{ $t('common.messages.inTime') }}
                    </th>
                    <th class="text-center py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">
                      {{ $t('common.messages.outTime') }}
                    </th>
                    <th class="text-center py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">
                      {{ $t('pages.attendance.timesheet.timesheet.worked') }}
                    </th>
                    <th class="text-center py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">
                      {{ $t('pages.attendance.timesheet.timesheet.overtime') }}
                    </th>
                    <th class="text-right py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">
                      {{ $t('pages.attendance.timesheet.timesheet.action') }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="displayedRecords.length === 0">
                    <td colspan="8" class="pa-0">
                      <TableEmptyState
                        icon="tabler-notebook-off"
                        :title="$t('pages.attendance.timesheet.timesheet.noRecordsFoundInsideRange')"
                        :description="$t('pages.attendance.timesheet.timesheet.adjustYourRangeLimitsOrSelectTeamMembersToBuildDataPoints')"
                      />
                    </td>
                  </tr>
                  <tr
                    v-for="record in displayedRecords"
                    :key="`${record.id}-${record.date}`"
                    class="table-row-hover transition-all"
                    :class="{ 'incomplete-session': record.status === 'present' && record.missing_punch_state !== 'none', 'friday-weekend': isFriday(record.date) }"
                  >
                    <!-- Employee Name -->
                    <td class="py-3 px-4 font-weight-semibold text-body-1">
                      <div class="d-flex align-center">
                        <VAvatar
                          color="primary"
                          variant="tonal"
                          size="28"
                          class="mr-3 font-weight-bold text-caption"
                        >
                          {{ record.name.charAt(0) }}
                        </VAvatar>
                        <span>{{ record.name }}</span>
                      </div>
                    </td>

                    <td class="py-3 px-4 text-medium-emphasis text-body-2">
                      {{ record.office || '—' }}
                    </td>

                    <!-- Status Pill Badge -->
                    <td class="py-3 px-4 text-center">
                      <VChip
                        :color="record.is_off_day ? 'info' : statusColor(record.status)"
                        variant="tonal"
                        size="small"
                        class="font-weight-bold text-uppercase px-3"
                        style="min-width: 90px; justify-content: center;"
                      >
                        <VIcon
                          :icon="record.is_off_day ? 'tabler-calendar-off' : statusIcon(record.status)"
                          size="14"
                          class="mr-1"
                        />
                        {{ record.is_off_day ? $t('pages.attendance.timesheet.timesheet.offDay') : translateBackendValue(record.status) }}
                      </VChip>
                      <div
                        v-if="record.is_off_day && record.off_day_reason"
                        class="text-caption text-medium-emphasis mt-1"
                      >
                        {{ record.off_day_reason }}
                      </div>
                      <div
                        v-if="record.leave_days"
                        class="d-flex justify-center ga-1 mt-1"
                      >
                        <VChip
                          v-if="record.off_day_days"
                          size="x-small"
                          color="success"
                          variant="tonal"
                        >
                          {{ record.off_day_days }} {{ $t('common.attendanceStatuses.offDay') }}
                        </VChip>
                        <VChip
                          v-if="record.leave_days - record.off_day_days > 0"
                          size="x-small"
                          color="warning"
                          variant="tonal"
                        >
                          {{ record.leave_days - record.off_day_days }} {{ $t('common.attendanceStatuses.absent') }}
                        </VChip>
                      </div>
                    </td>

                    <!-- Office-hour state is conveyed by both color and a tooltip. -->
                    <td class="py-3 px-4 text-center text-body-2 font-weight-medium">
                      <VTooltip
                        :text="timeHint(record.check_in_state)"
                        location="top"
                      >
                        <template #activator="{ props }">
                          <span v-bind="props">
                            <VChip
                              v-if="record.check_in_time"
                              :color="timeColor(record.check_in_state)"
                              variant="tonal"
                              size="small"
                            >{{ formatTime(record.checked_in_at, record.check_in_time, record.timezone) }}</VChip>
                            <span
                              v-else
                              class="text-medium-emphasis"
                            >—</span>
                          </span>
                        </template>
                      </VTooltip>
                    </td>

                    <td class="py-3 px-4 text-center text-body-2 font-weight-medium">
                      <VTooltip
                        :text="timeHint(record.check_out_state)"
                        location="top"
                      >
                        <template #activator="{ props }">
                          <span v-bind="props">
                            <VChip
                              v-if="record.check_out_time"
                              :color="timeColor(record.check_out_state)"
                              variant="tonal"
                              size="small"
                            >{{ formatTime(record.checked_out_at, record.check_out_time, record.timezone) }}</VChip>
                            <span
                              v-else
                              class="text-medium-emphasis"
                            >—</span>
                          </span>
                        </template>
                      </VTooltip>
                    </td>
                    <td class="py-3 px-4 text-center font-weight-medium">
                      <VChip
                        v-if="record.missing_punch_state !== 'none'"
                        color="warning"
                        size="small"
                      >
                        {{ $t('pages.attendance.timesheet.timesheet.incomplete') }}
                      </VChip><span v-else>{{ formatDuration(record.net_worked_minutes) }}</span>
                    </td>
                    <td class="py-3 px-4 text-center text-success font-weight-medium">
                      {{ formatDuration(record.overtime_minutes) }}
                    </td>

                    <!-- Row Contextual Eraser -->
                    <td class="py-3 px-4 text-right">
                      <div class="attendance-row-actions">
                      <VBtn
                        v-if="record.daily_report_exists"
                        icon="tabler-file-text"
                        variant="text"
                        color="primary"
                        size="small"
                        density="comfortable"
                        @click="viewDailyReport(record)"
                      >
                        <VIcon icon="tabler-file-text" /><VTooltip
                          activator="parent"
                          location="top"
                        >
                          {{ $t('pages.attendance.timesheet.timesheet.viewDailyReport') }}
                        </VTooltip>
                      </VBtn>
                      <VBtn
                        v-if="!record.is_off_day && !record.leave_request_id && canAccess('record_attendance')"
                        icon="tabler-edit"
                        variant="text"
                        color="primary"
                        size="small"
                        density="comfortable"
                        :aria-label="$t('pages.attendance.timesheet.timesheet.editAttendance')"
                        @click="openEditAttendance(record)"
                      >
                        <VIcon icon="tabler-edit" /><VTooltip activator="parent" location="top">{{ $t('pages.attendance.timesheet.timesheet.editAttendance') }}</VTooltip>
                      </VBtn>
                      <VBtn
                        v-if="record.status === 'present' && !record.checked_out_at && !record.check_out_time && (record.checked_in_at || record.check_in_time) && canAccess('record_attendance')"
                        icon="tabler-clock-edit"
                        variant="text"
                        color="warning"
                        size="small"
                        :aria-label="$t('components.account.workday.correctCheckout')"
                        @click="openCorrection(record)"
                      />
                      <VBtn
                        v-if="canDeleteAttendance(record)"
                        icon="tabler-trash"
                        variant="text"
                        color="error"
                        size="small"
                        density="comfortable"
                        :loading="loading"
                        :aria-label="$t('pages.attendance.timesheet.timesheet.removeRecord')"
                        @click="requestDelete(record)"
                      >
                        <VIcon icon="tabler-trash" /><VTooltip
                          activator="parent"
                          location="top"
                        >
                          {{ $t('pages.attendance.timesheet.timesheet.removeRecord') }}
                        </VTooltip>
                      </VBtn>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
              <div v-if="!loading && hasLoadedAttendanceRecords" class="timesheet-mobile-cards">
                <TableEmptyState
                  v-if="displayedRecords.length === 0"
                  icon="tabler-notebook-off"
                  :title="$t('pages.attendance.timesheet.timesheet.noRecordsFoundInsideRange')"
                  :description="$t('pages.attendance.timesheet.timesheet.adjustYourRangeLimitsOrSelectTeamMembersToBuildDataPoints')"
                />
                <article v-for="record in displayedRecords" :key="`mobile-${record.id}-${record.date}`" class="timesheet-mobile-card">
                  <div class="timesheet-mobile-card__header">
                    <div>
                      <strong>{{ record.name }}</strong>
                      <span>{{ record.role || record.office || '—' }}</span>
                    </div>
                    <VChip :color="record.is_off_day ? 'info' : statusColor(record.status)" variant="tonal" size="small">
                      {{ record.is_off_day ? $t('pages.attendance.timesheet.timesheet.offDay') : translateBackendValue(record.status) }}
                    </VChip>
                  </div>
                  <div class="timesheet-mobile-card__fields">
                    <div><span>{{ $t('common.messages.inTime') }}</span><strong>{{ formatTime(record.checked_in_at, record.check_in_time, record.timezone) || '—' }}</strong></div>
                    <div><span>{{ $t('common.messages.outTime') }}</span><strong>{{ formatTime(record.checked_out_at, record.check_out_time, record.timezone) || '—' }}</strong></div>
                    <div><span>{{ $t('pages.attendance.timesheet.timesheet.worked') }}</span><strong>{{ record.missing_punch_state !== 'none' ? $t('pages.attendance.timesheet.timesheet.incomplete') : formatDuration(record.net_worked_minutes) }}</strong></div>
                    <div><span>{{ $t('pages.attendance.timesheet.timesheet.overtime') }}</span><strong class="text-success">{{ formatDuration(record.overtime_minutes) }}</strong></div>
                  </div>
                  <div class="timesheet-mobile-card__actions">
                    <VBtn v-if="record.daily_report_exists" icon="tabler-file-text" variant="text" color="primary" size="small" @click="viewDailyReport(record)" />
                    <VBtn v-if="!record.is_off_day && !record.leave_request_id && canAccess('record_attendance')" icon="tabler-edit" variant="text" color="primary" size="small" @click="openEditAttendance(record)" />
                    <VBtn v-if="record.status === 'present' && !record.checked_out_at && !record.check_out_time && (record.checked_in_at || record.check_in_time) && canAccess('record_attendance')" icon="tabler-clock-edit" variant="text" color="warning" size="small" @click="openCorrection(record)" />
                    <VBtn v-if="canDeleteAttendance(record)" icon="tabler-trash" variant="text" color="error" size="small" :aria-label="$t('pages.attendance.timesheet.timesheet.removeRecord')" @click="requestDelete(record)" />
                  </div>
                </article>
              </div>
            </div>
          </div>
        </VCol>
      </VRow>
      </VCard>
    </div>

    <VDialog v-model="attendanceDialogOpen" :persistent="savingAttendance" max-width="960" scrollable content-class="attendance-log-dialog">
      <VCard class="attendance-log-dialog__card">
        <VCardTitle class="attendance-log-dialog__header px-5 py-4">
          <div>
            <div class="attendance-log-dialog__eyebrow">{{ $t('pages.attendance.timesheet.steps.logAttendance') }}</div>
            <div class="attendance-log-dialog__title">{{ $t('pages.attendance.timesheet.unmarkedFor', { date: logDate }) }}</div>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="error" :aria-label="$t('common.actions.close')" :disabled="savingAttendance" @click="attendanceDialogOpen = false" />
        </VCardTitle>

        <VCardText class="attendance-log-dialog__body pa-5">
          <VAlert v-if="isFriday(logDate)" type="info" variant="tonal" class="mb-4">
            {{ $t('common.ui.fridayIsTheAfghanistanWeekendAttendanceIsNotRequiredOrRecordedToday') }}
          </VAlert>
          <VAlert v-else-if="isCompanyOffDay" type="info" variant="tonal" class="mb-4">
            {{ companyOffDay?.reason || $t('pages.attendance.timesheet.timesheet.offDay') }}
          </VAlert>
          <VAlert v-else-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert>
          <ErpSearchField
            v-if="attendanceDrafts.length"
            v-model="attendanceStaffSearch"
            class="mb-4"
            :placeholder="$t('pages.attendance.timesheet.timesheet.searchStaff')"
          />
          <ResponsiveDataTable
            v-if="rosterLoading"
            :headers="attendanceRosterLoadingHeaders"
            :items="[]"
            :loading="true"
            :skeleton-rows="4"
            :sticky-key-column="false"
            :sticky-action-column="false"
            mobile-cards
            mobile-title-key="employee"
            :mobile-fields="['status']"
          />
          <section v-else-if="!attendanceDrafts.length" class="attendance-complete-state">
            <div class="attendance-complete-state__icon"><VIcon icon="tabler-circle-check" size="30" /></div>
            <h3>{{ $t('pages.attendance.timesheet.timesheet.attendanceCompleteTitle') }}</h3>
            <p>{{ $t('pages.attendance.timesheet.timesheet.attendanceComplete') }}</p>
          </section>
          <TableEmptyState
            v-else-if="!visibleAttendanceDrafts.length"
            icon="tabler-users-group"
            :title="$t('common.messages.noMatchingRecords')"
            :description="$t('pages.attendance.timesheet.timesheet.noStaffMatchDescription')"
          />
          <div v-else class="attendance-draft-list">
            <article v-for="draft in visibleAttendanceDrafts" :key="draft.staff.id" class="attendance-draft">
              <div class="attendance-draft__top">
                <div class="attendance-draft__identity">
                  <VAvatar color="primary" variant="tonal" size="44" :image="employeeFor(draft.staff.id)?.avatar || undefined">
                    <span v-if="!employeeFor(draft.staff.id)?.avatar">{{ draft.staff.name.charAt(0) }}</span>
                  </VAvatar>
                  <div>
                    <strong>{{ draft.staff.name }}</strong>
                    <span>{{ draft.staff.position }}</span>
                  </div>
                </div>
                <div class="attendance-status-picker" role="radiogroup" :aria-label="$t('common.fields.status')">
                  <VBtn :class="['attendance-status-picker__button', 'attendance-status-picker__button--present', { 'is-selected': draft.status === 'present' }]" :aria-pressed="draft.status === 'present'" size="small" variant="text" @click="draft.status = 'present'">{{ $t('common.statuses.present') }}</VBtn>
                  <VBtn :class="['attendance-status-picker__button', 'attendance-status-picker__button--absent', { 'is-selected': draft.status === 'absent' }]" :aria-pressed="draft.status === 'absent'" size="small" variant="text" @click="draft.status = 'absent'">{{ $t('pages.attendance.timesheet.timesheet.absent') }}</VBtn>
                  <VBtn :class="['attendance-status-picker__button', 'attendance-status-picker__button--unmarked', { 'is-selected': draft.status === 'unmarked' }]" :aria-pressed="draft.status === 'unmarked'" size="small" variant="text" @click="draft.status = 'unmarked'">{{ $t('pages.attendance.timesheet.timesheet.unmarked') }}</VBtn>
                </div>
              </div>
              <VRow class="mt-1">
                <VCol cols="12" sm="4"><ErpField v-model="draft.checkInTime" kind="select" :items="timeOptions" :label="$t('common.messages.inTime')" :disabled="attendanceFieldsDisabled(draft)" clearable /></VCol>
                <VCol cols="12" sm="4"><ErpField v-model="draft.checkOutTime" kind="select" :items="timeOptions" :label="$t('common.messages.outTime')" :disabled="attendanceFieldsDisabled(draft)" clearable /></VCol>
                <VCol cols="12" sm="4"><ErpField v-model.number="draft.breakMinutes" :label="$t('pages.attendance.timesheet.timesheet.breakMinutes')" type="number" min="0" max="720" :disabled="attendanceFieldsDisabled(draft)" /></VCol>
              </VRow>
            </article>
          </div>
        </VCardText>

        <VCardActions class="attendance-log-dialog__footer px-5 py-4">
          <VBtn class="attendance-log-dialog__cancel" :disabled="savingAttendance" @click="attendanceDialogOpen = false">{{ $t('common.actions.cancel') }}</VBtn>
          <VBtn class="attendance-log-dialog__save" color="primary" variant="flat" :loading="savingAttendance" :disabled="isFriday(logDate) || !attendanceDrafts.some(draft => draft.status !== 'unmarked')" @click="saveAllAttendance">
            <VIcon icon="tabler-checks" class="me-1" />{{ $t('pages.attendance.timesheet.timesheet.saveAllAttendance') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="editDialogOpen" :persistent="savingEdit" max-width="620" content-class="attendance-edit-dialog">
      <VCard class="attendance-edit-dialog__card">
        <VCardTitle class="attendance-edit-dialog__header d-flex align-center justify-space-between px-5 py-4">
          <span>{{ $t('pages.attendance.timesheet.timesheet.editAttendance') }}</span>
          <VBtn class="attendance-edit-dialog__close" icon="tabler-x" variant="tonal" size="small" :disabled="savingEdit" @click="editDialogOpen = false" />
        </VCardTitle>
        <VCardText class="pt-5">
          <p class="text-body-2 text-medium-emphasis mb-5">{{ editingRecord?.name }} · {{ editingRecord?.date }}</p>
          <div class="attendance-status-picker justify-start mb-5" role="radiogroup" :aria-label="$t('common.fields.status')">
            <VBtn :class="['attendance-status-picker__button', 'attendance-status-picker__button--present', { 'is-selected': editStatus === 'present' }]" size="small" variant="text" @click="editStatus = 'present'">{{ $t('common.statuses.present') }}</VBtn>
            <VBtn :class="['attendance-status-picker__button', 'attendance-status-picker__button--absent', { 'is-selected': editStatus === 'absent' }]" size="small" variant="text" @click="editStatus = 'absent'">{{ $t('pages.attendance.timesheet.timesheet.absent') }}</VBtn>
          </div>
          <VRow>
            <VCol cols="12" sm="4"><ErpField v-model="editCheckInTime" kind="select" :items="timeOptions" :label="$t('common.messages.inTime')" :disabled="editStatus !== 'present'" clearable /></VCol>
            <VCol cols="12" sm="4"><ErpField v-model="editCheckOutTime" kind="select" :items="timeOptions" :label="$t('common.messages.outTime')" :disabled="editStatus !== 'present'" clearable /></VCol>
            <VCol cols="12" sm="4"><ErpField v-model.number="editBreakMinutes" :label="$t('pages.attendance.timesheet.timesheet.breakMinutes')" type="number" min="0" max="720" :disabled="editStatus !== 'present'" /></VCol>
          </VRow>
        </VCardText>
        <VCardActions class="attendance-edit-dialog__actions px-5 py-4">
          <VBtn class="attendance-log-dialog__cancel" :disabled="savingEdit" @click="editDialogOpen = false">{{ $t('common.actions.cancel') }}</VBtn>
          <VBtn color="primary" variant="flat" :loading="savingEdit" @click="saveAttendanceEdit">{{ $t('common.actions.save') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="deleteDialogOpen" :persistent="deleting" max-width="460">
      <VCard class="timesheet-action-dialog">
        <VCardText class="timesheet-action-dialog__content">
          <div class="timesheet-action-dialog__icon"><VIcon icon="tabler-trash" size="30" /></div>
          <h2 class="timesheet-action-dialog__title">{{ $t('common.messages.confirmDelete') }}</h2>
          <p class="timesheet-action-dialog__description">{{ $t('pages.attendance.timesheet.timesheet.deleteAttendanceRecord', { name: deletingRecord?.name }) }}</p>
          <p class="timesheet-action-dialog__hint">{{ $t('common.confirmations.thisActionCannotBeUndone') }}</p>
        </VCardText>
        <VCardActions class="timesheet-action-dialog__actions">
          <VBtn class="timesheet-action-dialog__cancel" :disabled="deleting" @click="deleteDialogOpen = false">{{ $t('common.actions.cancel') }}</VBtn>
          <VBtn class="timesheet-action-dialog__delete" :loading="deleting" @click="deleteRecord">{{ $t('common.actions.delete') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="correctionOpen" :persistent="correcting" max-width="560">
      <VCard>
        <VCardTitle>{{ $t('components.account.workday.correctCheckout') }}</VCardTitle>
        <VCardText>
          <p>{{ correctionTarget?.name }} — {{ correctionTarget?.date }}</p>
          <p class="text-body-2">{{ $t('components.account.workday.correctionDescription', { timezone: correctionTarget?.timezone }) }}</p>
          <VAlert v-if="correctionError" type="error" variant="tonal" class="mb-4">{{ correctionError }}</VAlert>
          <VTextField v-model="correctionDate" type="date" :label="$t('components.account.workday.checkoutDate')" class="mb-3" />
          <VTextField v-model="correctionTime" type="time" :label="$t('components.account.workday.checkoutTime')" />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn :disabled="correcting" @click="correctionOpen = false">{{ $t('common.actions.cancel') }}</VBtn>
          <VBtn color="primary" :loading="correcting" :disabled="!correctionDate || !correctionTime" @click="correctCheckout">{{ $t('common.actions.save') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="reportDialogOpen"
      max-width="720"
    >
      <VCard>
        <VCardText class="pa-6">
          <div class="d-flex align-center justify-space-between mb-5">
            <div>
              <h3 class="text-h5 font-weight-bold">
                {{ $t('pages.attendance.timesheet.timesheet.dailyReport') }}
              </h3>
              <p
                v-if="selectedReport"
                class="text-body-2 text-medium-emphasis mb-0"
              >
                {{ formatLocalizedDate(`${selectedReport.reportDate}T00:00:00`) }}
              </p>
            </div>
            <VBtn
              icon="tabler-x"
              variant="text"
              @click="reportDialogOpen = false"
            />
          </div>

          <div
            v-if="reportLoading"
            class="text-center py-8"
          >
            {{ $t('common.messages.loading') }}
          </div>
          <VAlert
            v-else-if="reportError"
            type="error"
            variant="tonal"
          >
            {{ reportError }}
          </VAlert>
          <div
            v-else-if="selectedReport"
            class="d-flex flex-column ga-4"
          >
            <div>
              <div class="text-subtitle-2 font-weight-bold mb-1">
                {{ $t('pages.attendance.timesheet.timesheet.summary') }}
              </div><div class="text-body-1 white-space-pre-wrap">
                {{ selectedReport.summary }}
              </div>
            </div>
            <div v-if="selectedReport.achievements">
              <div class="text-subtitle-2 font-weight-bold mb-1">
                {{ $t('pages.attendance.timesheet.timesheet.achievements') }}
              </div><div class="white-space-pre-wrap">
                {{ selectedReport.achievements }}
              </div>
            </div>
            <div v-if="selectedReport.challenges">
              <div class="text-subtitle-2 font-weight-bold mb-1">
                {{ $t('pages.attendance.timesheet.timesheet.challenges') }}
              </div><div class="white-space-pre-wrap">
                {{ selectedReport.challenges }}
              </div>
            </div>
            <div v-if="selectedReport.nextDayPlan">
              <div class="text-subtitle-2 font-weight-bold mb-1">
                {{ $t('pages.attendance.timesheet.timesheet.nextDayPlan') }}
              </div><div class="white-space-pre-wrap">
                {{ selectedReport.nextDayPlan }}
              </div>
            </div>
            <div v-if="selectedReport.supportNeeded">
              <div class="text-subtitle-2 font-weight-bold mb-1">
                {{ $t('pages.attendance.timesheet.timesheet.supportNeeded') }}
              </div><div class="white-space-pre-wrap">
                {{ selectedReport.supportNeeded }}
              </div>
            </div>
          </div>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>

.attendance-container { max-inline-size: 100%; }

.timesheet-table-shell {
  overflow: hidden;
  border-radius: .85rem !important;
  box-shadow: 0 3px 14px rgba(var(--v-theme-on-surface), .035) !important;
}

/* Keep the body neutral and readable in both themes. Semantic colour belongs
   to the small status chips, not to an entire attendance row. */
.timesheet-table-shell :deep(tbody > tr:nth-child(odd) > td) {
  background: rgba(var(--v-theme-on-surface), .018);
}

.timesheet-table-shell :deep(tbody > tr:nth-child(even) > td) {
  background: rgba(var(--v-theme-on-surface), .045);
}

.timesheet-table-shell :deep(tbody > tr:hover > td) {
  background: rgba(var(--v-theme-primary), .07);
}

.timesheet-table-toolbar {
  padding: 1.25rem 1.5rem !important;
}

.table-toolbar-actions {
  min-inline-size: 0;
}

.attendance-log-dialog__card {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  max-block-size: min(90dvh, 920px);
  overflow: hidden;
}

.attendance-log-dialog__header,
.attendance-log-dialog__footer {
  z-index: 1;
  display: flex;
  align-items: center;
  background: color-mix(in srgb, rgb(var(--v-theme-surface)) 93%, rgb(var(--v-theme-on-surface)) 7%);
}

.attendance-log-dialog__header {
  position: sticky;
  inset-block-start: 0;
  border-block-end: 1px solid rgba(var(--v-border-color), .5);
}

.attendance-log-dialog__footer {
  position: sticky;
  inset-block-end: 0;
  border-block-start: 1px solid rgba(var(--v-border-color), .5);
  justify-content: flex-start;
  gap: .75rem;
}

.attendance-log-dialog__cancel,
.attendance-log-dialog__save {
  min-block-size: 2.75rem;
  flex: 0 0 auto;
  inline-size: auto;
  font-weight: 700;
}

.attendance-log-dialog__cancel {
  border: 1px solid rgba(var(--v-theme-secondary), .5) !important;
  background: rgba(var(--v-theme-secondary), .13) !important;
  color: rgb(var(--v-theme-secondary)) !important;
}

.attendance-log-dialog__cancel:hover:not(:disabled) {
  background: rgb(var(--v-theme-secondary)) !important;
  color: rgb(var(--v-theme-on-secondary)) !important;
}

.attendance-log-dialog__eyebrow {
  color: rgb(var(--v-theme-primary));
  font-size: .72rem;
  font-weight: 750;
  letter-spacing: .055em;
  text-transform: uppercase;
}

.attendance-log-dialog__title {
  margin-block-start: .15rem;
  color: rgb(var(--v-theme-on-surface));
  font-size: 1.18rem;
  font-weight: 700;
}

.attendance-log-dialog__body {
  min-block-size: 0;
  background: rgba(var(--v-theme-on-surface), .018);
  overflow-y: auto;
}

.attendance-draft-list {
  display: grid;
  gap: 1.725rem;
}

.attendance-draft {
  padding: 1rem;
  border: 1px solid rgba(var(--v-border-color), .48);
  border-radius: .85rem;
  background: rgb(var(--v-theme-surface));
  transition: border-color .2s ease, box-shadow .2s ease;
}

.attendance-draft:focus-within {
  border-color: rgba(var(--v-theme-primary), .65);
  box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), .1);
}

.attendance-draft__top,
.attendance-draft__identity,
.attendance-status-picker {
  display: flex;
  align-items: center;
}

.attendance-draft__top {
  justify-content: space-between;
  gap: 1rem;
}

.attendance-draft__identity {
  min-inline-size: 0;
  gap: .75rem;
}

.attendance-draft__identity > div {
  display: grid;
  min-inline-size: 0;
  gap: .1rem;
}

.attendance-draft__identity strong {
  overflow: hidden;
  color: rgb(var(--v-theme-on-surface));
  font-size: .94rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.attendance-draft__identity span {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: .78rem;
}

.attendance-status-picker {
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: .4rem;
}

.attendance-status-picker__button {
  border: 1px solid rgba(var(--v-border-color), .54) !important;
  background: transparent !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-weight: 700;
  text-transform: none;
}

.attendance-status-picker__button--present.is-selected {
  background: rgba(var(--v-theme-success), .1) !important;
  color: rgb(var(--v-theme-success)) !important;
}

.attendance-status-picker__button--absent.is-selected {
  background: rgba(var(--v-theme-error), .1) !important;
  color: rgb(var(--v-theme-error)) !important;
}

.attendance-status-picker__button--unmarked.is-selected {
  background: rgba(var(--v-theme-secondary), .1) !important;
  color: rgb(var(--v-theme-secondary)) !important;
}

.attendance-status-picker__button.is-selected {
  border-color: currentColor !important;
  box-shadow: none !important;
}

.attendance-edit-dialog__card { overflow: hidden; }
.attendance-edit-dialog__header,
.attendance-edit-dialog__actions {
  background: color-mix(in srgb, rgb(var(--v-theme-surface)) 93%, rgb(var(--v-theme-on-surface)) 7%);
}
.attendance-edit-dialog__header { border-block-end: 1px solid rgba(var(--v-border-color), .5); }
.attendance-edit-dialog__actions { display: flex; justify-content: flex-start; gap: .75rem; border-block-start: 1px solid rgba(var(--v-border-color), .5); }
.attendance-edit-dialog__actions :deep(.v-btn) { min-block-size: 2.75rem; }
.attendance-edit-dialog__close { background: rgba(var(--v-theme-error), .13) !important; color: rgb(var(--v-theme-error)) !important; }

.attendance-complete-state { display: grid; justify-items: center; max-inline-size: 34rem; margin: 1.25rem auto; padding: 3.5rem 1.5rem; text-align: center; }
.attendance-complete-state__icon { display: grid; inline-size: 4rem; block-size: 4rem; margin-block-end: 1rem; border-radius: 50%; background: rgba(var(--v-theme-success), .13); color: rgb(var(--v-theme-success)); place-items: center; }
.attendance-complete-state h3 { margin: 0; color: rgb(var(--v-theme-on-surface)); font-size: 1.1rem; }
.attendance-complete-state p { margin: .55rem 0 0; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); line-height: 1.6; }

.attendance-row-actions { display: inline-flex; align-items: center; justify-content: flex-end; gap: .15rem; white-space: nowrap; }

.timesheet-action-dialog { overflow: hidden; border: 1px solid rgba(var(--v-border-color), .2) !important; border-radius: 16px !important; background: rgb(var(--v-theme-surface)) !important; box-shadow: 0 24px 48px rgba(0, 0, 0, .18) !important; text-align: center; }
.timesheet-action-dialog__content { display: flex; flex-direction: column; align-items: center; padding: 2rem 2rem 1.25rem !important; }
.timesheet-action-dialog__icon { display: grid; inline-size: 4.25rem; block-size: 4.25rem; margin-block-end: 1.25rem; border-radius: 50%; background: rgba(var(--v-theme-error), .14); color: rgb(var(--v-theme-error)); place-items: center; }
.timesheet-action-dialog__title { margin: 0; font-size: 1.2rem; font-weight: 700; line-height: 1.45; }
.timesheet-action-dialog__description { max-inline-size: 24rem; margin: .65rem 0 0; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); line-height: 1.65; }
.timesheet-action-dialog__hint { margin: .45rem 0 0; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .8rem; }
.timesheet-action-dialog__actions { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; padding: 0 2rem 2rem !important; }
.timesheet-action-dialog__cancel, .timesheet-action-dialog__delete { min-block-size: 2.85rem; border-radius: 10px !important; font-weight: 700; }
.timesheet-action-dialog__cancel { border: 1px solid rgba(var(--v-theme-secondary), .52) !important; background: rgba(var(--v-theme-secondary), .13) !important; color: rgb(var(--v-theme-secondary)) !important; }
.timesheet-action-dialog__delete { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }

:global(.v-overlay:has(.attendance-log-dialog) .v-overlay__scrim),
:global(.v-overlay:has(.attendance-edit-dialog) .v-overlay__scrim),
:global(.v-overlay:has(.timesheet-action-dialog) .v-overlay__scrim) {
  background: rgba(20, 25, 35, .32) !important;
  backdrop-filter: blur(6px);
  opacity: 1 !important;
}

@media (max-width: 700px) {
  .attendance-log-dialog__card { max-block-size: calc(100dvh - 1rem); }
  .attendance-log-dialog__header, .attendance-log-dialog__footer { padding-inline: 1rem !important; }
  .attendance-draft { padding: .85rem; }
  .attendance-draft-list { gap: 1.475rem; }
  .attendance-draft__top { align-items: flex-start; flex-direction: column; }
  .attendance-status-picker { justify-content: flex-start; }
  .timesheet-table-toolbar { padding-inline: 1rem !important; }
  .timesheet-action-dialog__content, .timesheet-action-dialog__actions { padding-inline: 1.25rem !important; }
}


/* Accent Card Aesthetics */
.border-inline-start-success {
  border-inline-start: 4px solid rgb(var(--v-theme-success)) !important;
}

.border-inline-start-primary {
  border-inline-start: 4px solid rgb(var(--v-theme-primary)) !important;
}

.border-inline-start-error {
  border-inline-start: 4px solid rgb(var(--v-theme-error)) !important;
}

.border-inline-start-warning {
  border-inline-start: 4px solid rgb(var(--v-theme-warning)) !important;
}

.stats-card {
  block-size: 100%;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0.75rem;
  background: rgba(var(--v-theme-surface), 0.7);
}

.stats-card__body {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  min-block-size: 88px;
  padding: 1rem 1.25rem !important;
}

.stats-card__copy {
  min-inline-size: 0;
}

.stats-card__label {
  margin-block-end: 0.35rem;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.25;
}

.stats-card__value {
  color: rgb(var(--v-theme-on-surface));
  font-size: 1.45rem;
  font-weight: 700;
  line-height: 1.1;
  white-space: nowrap;
}

.timesheet-kpi-strip {
  flex-wrap: nowrap;
  overflow-x: auto;
  overscroll-behavior-inline: contain;
  padding-block-end: 0.25rem;
  scroll-snap-type: inline proximity;
  scrollbar-width: none;
}

.timesheet-kpi-strip::-webkit-scrollbar {
  display: none;
}

.timesheet-kpi-strip > .v-col {
  flex: 0 0 min(76vw, 15rem) !important;
  inline-size: min(76vw, 15rem) !important;
  max-inline-size: min(76vw, 15rem) !important;
  scroll-snap-align: start;
}

@media (min-width: 1280px) {
  .timesheet-kpi-strip > .v-col {
    flex: 1 1 0 !important;
    inline-size: auto !important;
    max-inline-size: none !important;
  }
}

/* Micro interactions */
.action-btn-success,
.action-btn-error {
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Keep action labels readable against the lighter semantic fills in dark mode. */
.action-btn-success :deep(.v-btn__content),
.action-btn-success :deep(.v-icon) {
  color: #103326 !important;
}

.action-btn-error :deep(.v-btn__content),
.action-btn-error :deep(.v-icon) {
  color: #3a1012 !important;
}

.action-btn-success:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(40, 199, 111, 0.35);
}

.action-btn-error:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(234, 84, 85, 0.35);
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(115, 103, 240, 0.4) !important;
}

/* Custom Vuexy Style Clean Border Tables */
.attendance-table-wrapper {
  overflow-x: auto;
  border-radius: 6px;
  scrollbar-width: none;
}

.attendance-table-wrapper::-webkit-scrollbar {
  display: none;
}

.timesheet-mobile-cards { display: none; }

.custom-attendance-table {
  border-collapse: separate;
  border-spacing: 0;
  min-inline-size: 68rem;
}

.custom-attendance-table th {
  background-color: rgba(var(--v-theme-on-surface), 0.04);
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.72rem !important;
  letter-spacing: 0.8px;
}

.custom-attendance-table td,
.custom-attendance-table th {
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  min-inline-size: 8.5rem;
}

.custom-attendance-table th:first-child,
.custom-attendance-table td:first-child {
  min-inline-size: 15rem;
}

.custom-attendance-table th:last-child,
.custom-attendance-table td:last-child {
  min-inline-size: 4.75rem;
  inline-size: 4.75rem;
}

.custom-attendance-table th:nth-child(4),
.custom-attendance-table td:nth-child(4),
.custom-attendance-table th:nth-child(5),
.custom-attendance-table td:nth-child(5) {
  min-inline-size: 7.5rem;
  inline-size: 7.5rem;
}

.custom-attendance-table th:nth-child(6),
.custom-attendance-table td:nth-child(6),
.custom-attendance-table th:nth-child(7),
.custom-attendance-table td:nth-child(7) {
  min-inline-size: 7rem;
  inline-size: 7rem;
}

.incomplete-session { background: rgba(var(--v-theme-warning), 0.1); }

.table-row-hover:hover {
  background-color: rgba(var(--v-theme-on-surface), .035);
}

.border-dashed {
  border: 2px dashed rgba(var(--v-theme-on-surface), 0.15) !important;
}

.grey-bg {
  background-color: rgba(var(--v-theme-on-surface), 0.01);
}

.transition-all {
  transition: all 0.2s ease;
}

@media (max-width: 959px) {
  .table-row-hover:hover { background-color: transparent; }
}

@media (max-width: 959px) {
  .attendance-table-wrapper { overflow: visible; }
  .timesheet-desktop-table { display: none; }
  .timesheet-mobile-cards { display: grid; gap: .75rem; padding: .75rem; }
  .timesheet-mobile-card { padding: 1rem; border: 1px solid rgba(var(--v-border-color), .48); border-radius: .85rem; background: rgb(var(--v-theme-surface)); }
  .timesheet-mobile-card__header { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; }
  .timesheet-mobile-card__header > div { display: grid; min-inline-size: 0; gap: .15rem; }
  .timesheet-mobile-card__header strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .timesheet-mobile-card__header span,
  .timesheet-mobile-card__fields span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .76rem; }
  .timesheet-mobile-card__fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .8rem; margin-block-start: 1rem; }
  .timesheet-mobile-card__fields > div { display: grid; gap: .15rem; min-inline-size: 0; }
  .timesheet-mobile-card__fields strong { overflow: hidden; font-size: .88rem; text-overflow: ellipsis; white-space: nowrap; }
  .timesheet-mobile-card__actions { display: flex; justify-content: flex-end; gap: .15rem; margin-block-start: .75rem; padding-block-start: .65rem; border-block-start: 1px solid rgba(var(--v-border-color), .42); }
}
</style>

<style>
/* ==========================================================================
   BULLETPROOF PRINT OVERRIDES FOR VUEXY & VUETIFY 3
   ========================================================================== */
@media print {

  body.printing-timesheet .timesheet-screen {
    display: none !important;
  }

  body.printing-timesheet .timesheet-print-host {
    display: block !important;
    width: 100% !important;
  }

  /* 1. Nuke navigation elements (Carefully avoiding component <header> tags) */
  body.printing-timesheet .layout-navbar,
  body.printing-timesheet .layout-menu,
  body.printing-timesheet .menu-vertical,
  body.printing-timesheet .layout-horizontal-nav,
  body.printing-timesheet .layout-vertical-nav,
  body.printing-timesheet .vertical-nav-wrapper,
  body.printing-timesheet .layout-overlay,
  body.printing-timesheet .v-app-bar,
  body.printing-timesheet .v-navigation-drawer,
  body.printing-timesheet .v-bottom-navigation,
  body.printing-timesheet .v-footer,
  body.printing-timesheet .layout-footer,
  body.printing-timesheet #app>header,
  body.printing-timesheet #app>nav,
  body.printing-timesheet .layout-page>header {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
    opacity: 0 !important;
    visibility: hidden !important;
  }

  /* 2. Strip Vuetify's internal layout spacing variables */
  body.printing-timesheet .v-application,
  body.printing-timesheet .v-layout,
  body.printing-timesheet .v-main {
    --v-layout-left: 0px !important;
    --v-layout-right: 0px !important;
    --v-layout-top: 0px !important;
    --v-layout-bottom: 0px !important;
  }

  /* 3. Reset all padding and margins on structural wrappers */
  body.printing-timesheet .v-main,
  body.printing-timesheet .layout-page,
  body.printing-timesheet .layout-page-content,
  body.printing-timesheet .page-content-container,
  body.printing-timesheet .layout-content-wrapper,
  body.printing-timesheet .layout-wrapper,
  body.printing-timesheet .app-content-wrapper,
  body.printing-timesheet .v-application__wrap,
  body.printing-timesheet .attendance-container {
    padding: 0 !important;
    margin: 0 !important;
    min-height: auto !important;
    width: 100% !important;
    max-width: 100% !important;
  }

  body.printing-timesheet .layout-content-wrapper,
  body.printing-timesheet .layout-page-content {
    display: block !important;
    flex: none !important;
    min-height: 0 !important;
    min-block-size: 0 !important;
  }

  /* 4. Force pure white background and reset overflow */
  body.printing-timesheet,
  body.printing-timesheet .v-application,
  body.printing-timesheet .layout-wrapper {
    background-color: #ffffff !important;
    background: #ffffff !important;
    overflow: visible !important;
    height: auto !important;
    min-height: 0 !important;
    min-block-size: 0 !important;
  }
}

.timesheet-print-host {
  display: none;
}
</style>
