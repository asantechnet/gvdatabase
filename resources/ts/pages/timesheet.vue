<script setup lang="ts">
import AttendanceApi, { type Attendance, type UnmarkedStaff } from '@/api/attendance'
import { printDocument } from '@/utils/printDocument'
import { computed, onMounted, ref } from 'vue'
import { VAvatar, VBtn, VCard, VCardText, VChip, VCol, VIcon, VRow, VSelect, VTextField } from 'vuetify/components'
import TimesheetTemplate from './templates/timesheet_template.vue'

// Operational Date Range States (For Filter & Printing)
const startDate = ref<string>(new Date().toISOString().substring(0, 10))
const endDate = ref<string>(new Date().toISOString().substring(0, 10))

// Dedicated Date State for Logging Attendance (Fixes the coupling quirk)
const logDate = ref<string>(new Date().toISOString().substring(0, 10))

const selectedStaffId = ref<number | null>(null)
const attendanceRecords = ref<Attendance[]>([])
const unmarkedStaff = ref<UnmarkedStaff[]>([])
const loading = ref<boolean>(false)
const error = ref<string | null>(null)

// Fetch attendance records within the date range
const fetchAttendanceRecords = async () => {
  try {
    loading.value = true
    const response = await AttendanceApi.getAll({
      start_date: startDate.value,
      end_date: endDate.value
    })
    attendanceRecords.value = response.data.data
  } catch (err) {
    error.value = 'Failed to fetch attendance records'
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

    unmarkedStaff.value = response.data.data.map(staff => ({
      id: staff.id,
      name: staff.name,
      position: staff.role
    }))
  } catch (err) {
    error.value = 'Failed to fetch unmarked staff'
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
      status: status
    })

    // Refresh data after logging
    await fetchAttendanceRecords()
    await fetchUnmarkedStaff()
    selectedStaffId.value = null
  } catch (err) {
    error.value = 'Failed to log attendance'
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
    error.value = 'Failed to delete attendance record'
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

  return { present, absent, totalInView, pendingOnLogDate }
})

// Lifecycle hooks
onMounted(async () => {
  await fetchAttendanceRecords()
  await fetchUnmarkedStaff()
})

// Watch range changes for attendance fetching
watch([startDate, endDate], async () => {
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
      <TimesheetTemplate :attendances="attendanceRecords" :start-date="startDate" :end-date="endDate" />
    </div>

    <!-- MAIN INTERACTIVE UI (Hidden during print output) -->
    <div class="timesheet-screen">

      <!-- TOP ROW: Range Metric Indicators -->
      <VRow class="mb-6">
        <VCol cols="12" sm="4">
          <VCard elevation="2" class="stats-card border-left-success">
            <VCardText class="d-flex align-center justify-space-between py-4">
              <div>
                <h6 class="text-xs font-weight-medium text-medium-emphasis mb-1">TOTAL PRESENT (RANGE)</h6>
                <h4 class="text-h4 font-weight-bold text-success">{{ stats.present }}</h4>
              </div>
              <VAvatar color="success" variant="tonal" rounded size="42">
                <VIcon icon="tabler-square-check" size="22" />
              </VAvatar>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="4">
          <VCard elevation="2" class="stats-card border-left-error">
            <VCardText class="d-flex align-center justify-space-between py-4">
              <div>
                <h6 class="text-xs font-weight-medium text-medium-emphasis mb-1">TOTAL ABSENT (RANGE)</h6>
                <h4 class="text-h4 font-weight-bold text-error">{{ stats.absent }}</h4>
              </div>
              <VAvatar color="error" variant="tonal" rounded size="42">
                <VIcon icon="tabler-square-x" size="22" />
              </VAvatar>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="4">
          <VCard elevation="2" class="stats-card border-left-warning">
            <VCardText class="d-flex align-center justify-space-between py-4">
              <div>
                <h6 class="text-xs font-weight-medium text-medium-emphasis mb-1">UNMARKED FOR {{ logDate }}</h6>
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
              <h3 class="text-h5 font-weight-bold">1. Log Attendance</h3>
            </div>

            <!-- Target Log Date Selector -->
            <VTextField v-model="logDate" label="Attendance Date" type="date" density="comfortable" variant="outlined"
              class="mb-4" :loading="loading" />

            <!-- Dropdown Staff Selection -->
            <VSelect v-model="selectedStaffId" :items="availableStaff" item-title="name" item-value="id"
              label="Select Employee" placeholder="Search or pick an option..." variant="outlined" density="comfortable"
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

            <!-- Logger Submission Buttons -->
            <VRow class="ma-0 ga-3">
              <VCol class="pa-0">
                <VBtn color="success" block size="large" variant="flat" :disabled="!selectedStaffId || loading"
                  @click="logAttendance('present')" class="action-btn-success font-weight-bold">
                  <VIcon icon="tabler-check" class="mr-2" size="18" />
                  Present
                </VBtn>
              </VCol>

              <VCol class="pa-0">
                <VBtn color="error" block size="large" variant="flat" :disabled="!selectedStaffId || loading"
                  @click="logAttendance('absent')" class="action-btn-error font-weight-bold">
                  <VIcon icon="tabler-x" class="mr-2" size="18" />
                  Absent
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
              <h3 class="text-h5 font-weight-bold">2. Print Range Filter</h3>
            </div>

            <VRow class="mb-4">
              <VCol cols="12" sm="6" lg="12" class="py-1">
                <VTextField v-model="startDate" label="Start Date" type="date" density="comfortable" variant="outlined"
                  hide-details :loading="loading" />
              </VCol>
              <VCol cols="12" sm="6" lg="12" class="py-1">
                <VTextField v-model="endDate" label="End Date" type="date" density="comfortable" variant="outlined"
                  hide-details :loading="loading" />
              </VCol>
            </VRow>

            <VBtn color="primary" variant="elevated" size="large" block @click="printTimesheet"
              class="font-weight-bold shadow-primary mt-2" :loading="loading">
              <VIcon icon="tabler-printer" class="mr-2" size="20" />
              Print Timesheet Report
            </VBtn>
          </VCard>
        </VCol>

        <!-- RIGHT COLUMN: Screen Output View List -->
        <VCol cols="12" lg="8">
          <VCard class="pa-5 pa-sm-6" elevation="3">

            <!-- Heading Descriptor Info -->
            <div class="mb-5 border-bottom pb-4">
              <h3 class="text-h4 font-weight-bold mb-1">Attendance Roll Ledger</h3>
              <p class="text-body-2 text-medium-emphasis mb-0">
                Filtered View Frame: From <span class="text-primary font-weight-semibold">{{ startDate }}</span> to
                <span class="text-primary font-weight-semibold">{{ endDate }}</span>
              </p>
            </div>

            <!-- Live Attendance Log Grid List -->
            <div class="attendance-table-wrapper">
              <div v-if="displayedRecords.length === 0" class="text-center py-12 border-dashed rounded-lg grey-bg">
                <VIcon icon="tabler-notebook-off" size="48" class="text-disabled mb-3" />
                <h4 class="text-h5 font-weight-medium text-medium-emphasis mb-1">No Records Found inside Range</h4>
                <p class="text-body-2 text-disabled mb-0">Adjust your range limits or select team members to build data
                  points.
                </p>
              </div>

              <table v-else class="custom-attendance-table w-100">
                <thead>
                  <tr>
                    <th class="text-left py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">Date
                    </th>
                    <th class="text-left py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">
                      Employee</th>
                    <th class="text-left py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">Role
                    </th>
                    <th class="text-center py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">
                      Status</th>
                    <th class="text-center py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">
                      Logged At
                    </th>
                    <th class="text-right py-3 px-4 font-weight-bold text-subtitle-2 text-uppercase tracking-wider">
                      Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="record in displayedRecords" :key="`${record.id}-${record.date}`"
                    class="table-row-hover transition-all">
                    <!-- Date Marked -->
                    <td class="py-3 px-4 font-weight-bold text-body-2 text-primary">
                      {{ record.date }}
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

                    <!-- Status Pill Badge -->
                    <td class="py-3 px-4 text-center">
                      <VChip :color="record.status === 'present' ? 'success' : 'error'" variant="flat" size="small"
                        class="font-weight-bold text-uppercase px-3" style="min-width: 90px; justify-content: center;">
                        <VIcon :icon="record.status === 'present' ? 'tabler-circle-check' : 'tabler-circle-x'" size="14"
                          class="mr-1" />
                        {{ record.status }}
                      </VChip>
                    </td>

                    <!-- Clock Entry Timestamp -->
                    <td class="py-3 px-4 text-center text-body-2 text-medium-emphasis">
                      {{ record.timestamp }}
                    </td>

                    <!-- Row Contextual Eraser -->
                    <td class="py-3 px-4 text-right">
                      <VBtn icon="tabler-trash" variant="text" color="error" size="small" density="comfortable"
                        @click="deleteRecord(record.id)" title="Remove Record" :loading="loading" />
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
.border-left-success {
  border-left: 4px solid rgb(var(--v-theme-success)) !important;
}

.border-left-error {
  border-left: 4px solid rgb(var(--v-theme-error)) !important;
}

.border-left-warning {
  border-left: 4px solid rgb(var(--v-theme-warning)) !important;
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
