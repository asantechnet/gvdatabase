<script setup lang="ts">
import AttendanceApi, { type Attendance, type UnmarkedStaff } from '@/api/attendance'
import { printDocument } from '@/utils/printDocument'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { VAvatar, VBtn, VCard, VCardText, VChip, VCol, VIcon, VRow, VSelect } from 'vuetify/components'
import TimesheetTemplate from './templates/timesheet_template.vue'
import EmployeeApi, { type Employee } from '@/api/employee'
import OfficeApi, { type Office } from '@/api/office'
import SettingApi from '@/api/setting'

// Operational Date Range States (For Filter & Printing)
const startDate = ref<string>(new Date().toISOString().substring(0, 10))
const endDate = ref<string>(new Date().toISOString().substring(0, 10))

const getCurrentDate = () => {
  const date = new Date()

  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

// Attendance records are always added for the current date.
const logDate = ref<string>(getCurrentDate())

const selectedStaffId = ref<number | null>(null)
const checkInTime = ref<string>('')
const checkOutTime = ref<string>('')
const breakMinutes = ref<number>(0)
const employeeFilter = ref<number | null>(null)
const officeFilter = ref<number | null>(null)
const statusFilter = ref<Attendance['status'] | 'incomplete' | null>(null)
const employees = ref<Employee[]>([])
const offices = ref<Office[]>([])
const attendanceRecords = ref<Attendance[]>([])
const unmarkedStaff = ref<UnmarkedStaff[]>([])
const loading = ref<boolean>(false)
const error = ref<string | null>(null)
const { locale } = useI18n()

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
  } catch (err) {
    error.value = translate('pages.attendance.timesheet.timesheet.failedToFetchAttendanceRecords')
    console.error(err)
  } finally {
    loading.value = false
  }
}

// Fetch unmarked staff for the selected LOG date
const fetchUnmarkedStaff = async () => {
  try {
    loading.value = true
    const response = await AttendanceApi.getUnmarkedStaff(logDate.value)

    unmarkedStaff.value = response.map(staff => ({
      id: staff.id,
      name: staff.name,
      position: staff.position || staff.role || 'Unassigned'
    }))
  } catch (err) {
    error.value = translate('pages.attendance.timesheet.timesheet.failedToFetchUnmarkedStaff')
    console.error(err)
  } finally {
    loading.value = false
  }
}

// Log attendance for an employee on the dedicated logDate
const logAttendance = async (status: 'present' | 'absent') => {
  if (!selectedStaffId.value) return

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

    // Refresh data after logging
    await fetchAttendanceRecords()
    await fetchUnmarkedStaff()
    selectedStaffId.value = null
    const setting = (await SettingApi.getCompanyProfile()).data
    checkInTime.value = setting?.office_open_time || ''
    checkOutTime.value = setting?.office_close_time || ''
    breakMinutes.value = setting?.default_break_minutes || 0
  } catch (err) {
    error.value = translate('pages.attendance.timesheet.timesheet.failedToLogAttendance')
    console.error(err)
  } finally {
    loading.value = false
  }
}

// Delete an attendance record
const deleteRecord = async (id: number) => {
  try {
    loading.value = true
    await AttendanceApi.delete(id)

    // Refresh data after deletion
    await fetchAttendanceRecords()
    await fetchUnmarkedStaff()
  } catch (err) {
    error.value = translate('pages.attendance.timesheet.timesheet.failedToDeleteAttendanceRecord')
    console.error(err)
  } finally {
    loading.value = false
  }
}

// Trigger printable report engine
const printTimesheet = () => {
  void printDocument({
    bodyClass: 'printing-timesheet',
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
  return attendanceRecords.value
    .filter(record => record.date >= startDate.value && record.date <= endDate.value)
    .sort((a, b) => b.date.localeCompare(a.date) || b.timestamp.localeCompare(a.timestamp))
})

const stats = computed(() => {
  const present = displayedRecords.value.filter(r => r.status === 'present').length
  const absent = displayedRecords.value.filter(r => r.status === 'absent').length
  const totalInView = displayedRecords.value.length

  const pendingOnLogDate = unmarkedStaff.value.length

  const workedMinutes = displayedRecords.value.reduce((sum, row) => sum + (row.net_worked_minutes ?? 0), 0)
  const overtimeMinutes = displayedRecords.value.reduce((sum, row) => sum + row.overtime_minutes, 0)
  const incomplete = displayedRecords.value.filter(row => row.status === 'present' && row.missing_punch_state !== 'none').length
  return { present, absent, totalInView, pendingOnLogDate, workedMinutes, overtimeMinutes, incomplete }
})

const formatDuration = (minutes: number | null) => {
  if (minutes === null) return '—'
  const hours = Math.floor(minutes / 60)
  const remainder = minutes % 60
  const hoursText = new Intl.NumberFormat(locale.value, { style: 'unit', unit: 'hour', unitDisplay: 'narrow' }).format(hours)
  const minutesText = new Intl.NumberFormat(locale.value, { style: 'unit', unit: 'minute', unitDisplay: 'narrow' }).format(remainder)
  return `${hoursText} ${minutesText}`
}

const formatTime = (instant: string | null, fallback: string | null, timezone: string) => {
  if (!instant) return fallback
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

const statusColor = (status: Attendance['status']) => ({ present: 'success', absent: 'error', 'paid-off-day': 'info', 'unpaid-off-day': 'warning' }[status])
const statusIcon = (status: Attendance['status']) => ({ present: 'tabler-circle-check', absent: 'tabler-circle-x', 'paid-off-day': 'tabler-beach', 'unpaid-off-day': 'tabler-calendar-dollar' }[status])

const timeHints: Record<NonNullable<Attendance['check_in_state'] | Attendance['check_out_state']>, string> = {
  early: 'Early arrival',
  on_time: 'On time',
  late: 'Late arrival',
  early_departure: 'Left before office closing time',
  overtime: 'Left after office closing time',
}
const timeHint = (state: Attendance['check_in_state'] | Attendance['check_out_state']) => state ? timeHints[state] : 'Office hours are not configured'

// Lifecycle hooks
onMounted(async () => {
  const [employeeResponse, officeResponse, settingResponse] = await Promise.all([EmployeeApi.getAll(), OfficeApi.getAll(), SettingApi.getCompanyProfile()])
  employees.value = employeeResponse.data.data
  offices.value = officeResponse
  checkInTime.value = settingResponse.data?.office_open_time || ''
  checkOutTime.value = settingResponse.data?.office_close_time || ''
  breakMinutes.value = settingResponse.data?.default_break_minutes || 0
  await Promise.all([fetchAttendanceRecords(), fetchUnmarkedStaff()])
})

// Watch range changes for attendance fetching
watch([startDate, endDate, employeeFilter, officeFilter, statusFilter], async () => {
  await fetchAttendanceRecords()
})

// Watch target logging date changes to get relevant unmarked staff
watch(logDate, async () => {
  await fetchUnmarkedStaff()
})
</script>

<template>
  <div class="attendance-container pa-4 pa-sm-6">

    <!-- PRINT ENGINE COMPONENT (Visible ONLY during print output) -->
    <div class="timesheet-print-host" aria-hidden="true">
      <TimesheetTemplate :attendances="displayedRecords" :start-date="startDate" :end-date="endDate"
        :total-worked-minutes="stats.workedMinutes" :total-overtime-minutes="stats.overtimeMinutes" />
    </div>

    <!-- MAIN INTERACTIVE UI (Hidden during print output) -->
    <div class="timesheet-screen">

      <!-- TOP ROW: Range Metric Indicators -->
      <VRow class="mb-6">
        <VCol cols="12" sm="4">
          <VCard elevation="2" class="stats-card border-inline-start-success">
            <VCardText class="d-flex align-center justify-space-between py-4">
              <div>
                <h6 class="text-xs font-weight-medium text-medium-emphasis mb-1">{{ $t('pages.attendance.timesheet.timesheet.totalPRESENTRANGE') }}</h6>
                <h4 class="text-h4 font-weight-bold text-success">{{ stats.present }}</h4>
              </div>
              <VAvatar color="success" variant="tonal" rounded size="42">
                <VIcon icon="tabler-square-check" size="22" />
              </VAvatar>
            </VCardText>
          </VCard>
        </VCol>
        <VCol cols="12" sm="4"><VCard elevation="2" class="stats-card"><VCardText><div class="text-caption">{{ $t('pages.attendance.timesheet.timesheet.totalWORKED') }}</div><div class="text-h5 font-weight-bold">{{ formatDuration(stats.workedMinutes) }}</div></VCardText></VCard></VCol>
        <VCol cols="12" sm="4"><VCard elevation="2" class="stats-card"><VCardText><div class="text-caption">{{ $t('pages.attendance.timesheet.timesheet.overtime') }}</div><div class="text-h5 font-weight-bold text-success">{{ formatDuration(stats.overtimeMinutes) }}</div></VCardText></VCard></VCol>
        <VCol cols="12" sm="4"><VCard elevation="2" class="stats-card"><VCardText><div class="text-caption">{{ $t('pages.attendance.timesheet.timesheet.incompleteSESSIONS') }}</div><div class="text-h5 font-weight-bold text-warning">{{ stats.incomplete }}</div></VCardText></VCard></VCol>

        <VCol cols="12" sm="4">
          <VCard elevation="2" class="stats-card border-inline-start-error">
            <VCardText class="d-flex align-center justify-space-between py-4">
              <div>
                <h6 class="text-xs font-weight-medium text-medium-emphasis mb-1">{{ $t('pages.attendance.timesheet.timesheet.totalABSENTRANGE') }}</h6>
                <h4 class="text-h4 font-weight-bold text-error">{{ stats.absent }}</h4>
              </div>
              <VAvatar color="error" variant="tonal" rounded size="42">
                <VIcon icon="tabler-square-x" size="22" />
              </VAvatar>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="4">
          <VCard elevation="2" class="stats-card border-inline-start-warning">
            <VCardText class="d-flex align-center justify-space-between py-4">
              <div>
        <h6 class="text-xs font-weight-medium text-medium-emphasis mb-1">{{ $t('pages.attendance.timesheet.unmarkedFor', { date: formatLocalizedDate(`${logDate}T00:00:00`) }) }}</h6>
                <h4 class="text-h4 font-weight-bold text-warning">{{ stats.pendingOnLogDate }}</h4>
              </div>
              <VAvatar color="warning" variant="tonal" rounded size="42">
                <VIcon icon="tabler-clock-hour-4" size="22" />
              </VAvatar>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- MAIN GRID CONTAINER -->
      <VRow>
        <!-- LEFT COLUMN: Control Panels -->
        <VCol cols="12" lg="4">

          <!-- 1. Interactive Roll Logger (Decoupled from start date filter) -->
          <VCard class="pa-5 mb-6" elevation="3">
            <div class="d-flex align-center mb-4">
              <VAvatar color="primary" variant="flat" size="36" class="mr-3">
                <VIcon icon="tabler-user-plus" size="20" color="white" />
              </VAvatar>
              <h3 class="text-h5 font-weight-bold">{{ $t('pages.attendance.timesheet.steps.logAttendance') }}</h3>
            </div>

            <!-- Dropdown Staff Selection -->
            <VSelect v-model="selectedStaffId" :items="availableStaff" item-title="name" item-value="id"
              :label="$t('pages.attendance.timesheet.timesheet.selectEmployee')" :placeholder="$t('pages.attendance.timesheet.timesheet.searchOrPickAnOption')" variant="outlined" density="comfortable"
              class="mb-5" :loading="loading"
              :no-data-text="stats.pendingOnLogDate === 0 ? '🎉 All staff filled for this date!' : 'Select an employee'">
              <template #item="{ props, item }">
                <VListItem v-bind="props" :title="item.raw.name" :subtitle="item.raw.position">
                  <template #prepend>
                    <VAvatar color="primary" variant="tonal" size="32" class="text-body-2 font-weight-bold">
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
                />
              </VCol>
            </VRow>
            <VTextField v-model.number="breakMinutes" :label="$t('pages.attendance.timesheet.timesheet.breakMinutes')" type="number" min="0" max="720" class="mb-3" />

            <!-- Logger Submission Buttons -->
            <VRow class="ma-0 ga-3">
              <VCol class="pa-0">
                <VBtn color="success" block size="large" variant="flat" :disabled="!selectedStaffId || loading"
                  @click="logAttendance('present')" class="action-btn-success font-weight-bold">
                  <VIcon icon="tabler-check" class="mr-2" size="18" />
                  {{ $t('common.statuses.present') }}
                </VBtn>
              </VCol>

              <VCol class="pa-0">
                <VBtn color="error" block size="large" variant="flat" :disabled="!selectedStaffId || loading"
                  @click="logAttendance('absent')" class="action-btn-error font-weight-bold">
                  <VIcon icon="tabler-x" class="mr-2" size="18" />
                  {{ $t('pages.attendance.timesheet.timesheet.absent') }}
                </VBtn>
              </VCol>
            </VRow>
          </VCard>

          <!-- 2. Range Configuration & Printing -->
          <VCard class="pa-5 mb-6" elevation="3">
            <div class="d-flex align-center mb-4">
              <VAvatar color="primary" variant="flat" size="36" class="mr-3">
                <VIcon icon="tabler-calendar" size="20" color="white" />
              </VAvatar>
              <h3 class="text-h5 font-weight-bold">{{ $t('pages.attendance.timesheet.steps.printRangeFilter') }}</h3>
            </div>

            <VRow class="mb-4">
              <VCol cols="12" sm="6" lg="12" class="py-1">
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
              <VCol cols="12" sm="6" lg="12" class="py-1">
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
            <VSelect v-model="employeeFilter" :items="employees" item-title="name" item-value="id" :label="$t('common.fields.employee')" clearable class="mb-2" />
            <VSelect v-model="officeFilter" :items="offices" item-title="name" item-value="id" :label="$t('common.fields.office')" clearable class="mb-2" />
            <VSelect v-model="statusFilter" :items="[{title: translate('common.statuses.present'),value:'present'},{title: translate('pages.attendance.timesheet.timesheet.absent'),value:'absent'},{title: translate('pages.attendance.timesheet.timesheet.paidLeave'),value:'paid-off-day'},{title:'Unpaid off-day',value:'unpaid-off-day'},{title: translate('pages.attendance.timesheet.timesheet.incomplete'),value:'incomplete'}]" :label="$t('common.fields.status')" clearable class="mb-2" />

            <VBtn color="primary" variant="elevated" size="large" block @click="printTimesheet"
              class="font-weight-bold shadow-primary mt-2" :loading="loading">
              <VIcon icon="tabler-printer" class="mr-2" size="20" />
              {{ $t('pages.attendance.timesheet.timesheet.printTimesheetReport') }}
            </VBtn>
          </VCard>
        </VCol>

        <!-- RIGHT COLUMN: Screen Output View List -->
        <VCol cols="12" lg="8">
          <VCard class="pa-5 pa-sm-6" elevation="3">

            <!-- Heading Descriptor Info -->
            <div class="mb-5 border-bottom pb-4">
              <h3 class="text-h4 font-weight-bold mb-1">{{ $t('pages.attendance.timesheet.timesheet.attendanceRollLedger') }}</h3>
              <p class="text-body-2 text-medium-emphasis mb-0">
                {{ $t('pages.attendance.timesheet.filteredRange', { start: formatLocalizedDate(`${startDate}T00:00:00`), end: formatLocalizedDate(`${endDate}T00:00:00`) }) }}
              </p>
            </div>

            <!-- Live Attendance Log Grid List -->
            <div class="attendance-table-wrapper">
              <div v-if="displayedRecords.length === 0" class="text-center py-12 border-dashed rounded-lg grey-bg">
                <VIcon icon="tabler-notebook-off" size="48" class="text-disabled mb-3" />
                <h4 class="text-h5 font-weight-medium text-medium-emphasis mb-1">{{ $t('pages.attendance.timesheet.timesheet.noRecordsFoundInsideRange') }}</h4>
                <p class="text-body-2 text-disabled mb-0">{{ $t('pages.attendance.timesheet.timesheet.adjustYourRangeLimitsOrSelectTeamMembersToBuildDataPoints') }}
                </p>
              </div>

              <table v-else class="custom-attendance-table w-100">
                <thead>
                  <tr>
                    <th class="text-left py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">{{ $t('common.fields.date') }}
                    </th>
                    <th class="text-left py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">
                      {{ $t('common.fields.employee') }}</th>
                    <th class="text-left py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">{{ $t('common.fields.role') }}
                    </th>
                    <th class="text-left py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">{{ $t('common.fields.office') }}</th>
                    <th class="text-center py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">
                      {{ $t('common.fields.status') }}</th>
                    <th class="text-center py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">
                      {{ $t('common.messages.inTime') }}
                    </th>
                    <th class="text-center py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">
                      {{ $t('common.messages.outTime') }}
                    </th>
                    <th class="text-center py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">{{ $t('pages.attendance.timesheet.timesheet.worked') }}</th>
                    <th class="text-center py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">{{ $t('pages.attendance.timesheet.timesheet.overtime') }}</th>
                    <th class="text-right py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">
                      {{ $t('pages.attendance.timesheet.timesheet.action') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="record in displayedRecords" :key="`${record.id}-${record.date}`"
                    class="table-row-hover transition-all" :class="{ 'incomplete-session': record.status === 'present' && record.missing_punch_state !== 'none' }">
                    <!-- Date Marked -->
                    <td class="py-3 px-4 font-weight-bold text-body-2 text-primary">
                      {{ formatLocalizedDate(`${record.date}T00:00:00`) }}
                    </td>

                    <!-- Employee Name -->
                    <td class="py-3 px-4 font-weight-semibold text-body-1">
                      <div class="d-flex align-center">
                        <VAvatar color="primary" variant="tonal" size="28" class="mr-3 font-weight-bold text-caption">
                          {{ record.name.charAt(0) }}
                        </VAvatar>
                        <span>{{ record.name }}</span>
                      </div>
                    </td>

                    <!-- Job Assignment -->
                    <td class="py-3 px-4 text-medium-emphasis text-body-2">
                      {{ record.role }}
                    </td>
                    <td class="py-3 px-4 text-medium-emphasis text-body-2">{{ record.office || '—' }}</td>

                    <!-- Status Pill Badge -->
                    <td class="py-3 px-4 text-center">
                      <VChip :color="statusColor(record.status)" variant="flat" size="small"
                        class="font-weight-bold text-uppercase px-3" style="min-width: 90px; justify-content: center;">
                        <VIcon :icon="statusIcon(record.status)" size="14"
                          class="mr-1" />
                        {{ translateBackendValue(record.status) }}
                      </VChip>
                    </td>

                    <!-- Office-hour state is conveyed by both color and a tooltip. -->
                    <td class="py-3 px-4 text-center text-body-2 font-weight-medium" :title="timeHint(record.check_in_state)">
                      <VChip v-if="record.check_in_time" :color="timeColor(record.check_in_state)" variant="tonal" size="small">{{ formatTime(record.checked_in_at, record.check_in_time, record.timezone) }}</VChip>
                      <span v-else class="text-medium-emphasis">—</span>
                    </td>

                    <td class="py-3 px-4 text-center text-body-2 font-weight-medium" :title="timeHint(record.check_out_state)">
                      <VChip v-if="record.check_out_time" :color="timeColor(record.check_out_state)" variant="tonal" size="small">{{ formatTime(record.checked_out_at, record.check_out_time, record.timezone) }}</VChip>
                      <span v-else class="text-medium-emphasis">—</span>
                    </td>
                    <td class="py-3 px-4 text-center font-weight-medium"><VChip v-if="record.missing_punch_state !== 'none'" color="warning" size="small">{{ $t('pages.attendance.timesheet.timesheet.incomplete') }}</VChip><span v-else>{{ formatDuration(record.net_worked_minutes) }}</span></td>
                    <td class="py-3 px-4 text-center text-success font-weight-medium">{{ formatDuration(record.overtime_minutes) }}</td>

                    <!-- Row Contextual Eraser -->
                    <td class="py-3 px-4 text-right">
                      <VBtn icon="tabler-trash" variant="text" color="error" size="small" density="comfortable"
                        @click="deleteRecord(record.id)" :title="$t('pages.attendance.timesheet.timesheet.removeRecord')" :loading="loading" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </div>
</template>

<style scoped>
.attendance-container {
  max-width: 1600px;
  margin: 0 auto;
}

/* Accent Card Aesthetics */
.border-inline-start-success {
  border-inline-start: 4px solid rgb(var(--v-theme-success)) !important;
}

.border-inline-start-error {
  border-inline-start: 4px solid rgb(var(--v-theme-error)) !important;
}

.border-inline-start-warning {
  border-inline-start: 4px solid rgb(var(--v-theme-warning)) !important;
}

/* Micro interactions */
.action-btn-success,
.action-btn-error {
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
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
}

.custom-attendance-table {
  border-collapse: separate;
  border-spacing: 0;
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
}

.incomplete-session { background: rgba(var(--v-theme-warning), 0.1); }

.table-row-hover:hover {
  background-color: rgba(var(--v-theme-primary), 0.03);
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
