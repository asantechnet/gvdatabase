<template>
  <div class="timesheet-wrapper print-document" :dir="$i18n.locale === 'en' ? 'ltr' : 'rtl'">
    <div class="actions no-print">
      <button @click="printReport" class="print-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 6 2 18 2 18 9"></polyline>
          <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
          <rect x="6" y="14" width="12" height="8"></rect>
        </svg>
        {{ $t('documents.timesheet_template.timesheetTemplate.printTimesheet') }}
      </button>
    </div>

    <div class="print-container">
      <div v-for="(page, index) in calendarPages" :key="index" class="a4-page">
        <div class="timesheet-page-header">
          <h2>{{ page.monthName }} {{ page.year }}</h2>
          <p class="subtitle">{{ $t('documents.timesheet_template.timesheetTemplate.monthlyAttendanceRecord') }}</p>
        </div>

        <div class="calendar-grid">
          <!-- Weekday Headers -->
          <div v-for="day in weekDays" :key="day" class="weekday-header">
            {{ day }}
          </div>

          <!-- Calendar Days -->
          <div v-for="cell in page.days" :key="cell.dateStr" class="calendar-cell"
            :class="{ 'out-of-month': !cell.isCurrentMonth, 'is-today': cell.isToday }">
            <div class="day-number">{{ cell.dayNumber }}</div>

            <div class="attendances-list">
              <div v-for="att in cell.attendances" :key="att.id" class="attendance-item">
                <div class="status-icon">
                  <!-- Green Checkmark -->
                  <svg v-if="att.status === 'present'" class="icon-present" xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"
                    stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>

                  <!-- Red Cross -->
                  <svg v-else-if="att.status === 'absent'" class="icon-absent" xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"
                    stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                  </svg>
                </div>
                <span class="staff-name">{{ att.name }}</span>
                <span v-if="att.status === 'present'" class="staff-hours">
                  {{ att.missing_punch_state !== 'none' ? translateBackendValue('incomplete') : formatDuration(att.net_worked_minutes) }}
                  <template v-if="att.overtime_minutes">{{ $t('documents.timesheet.overtimeInline', { duration: formatDuration(att.overtime_minutes) }) }}</template>
                </span>
              </div>
            </div>
          </div>
        </div>

        <footer class="page-footer">
          <p>{{ $t('documents.timesheet.totals', { worked: formatDuration(totalWorkedMinutes), overtime: formatDuration(totalOvertimeMinutes) }) }}</p>
          <p>{{ $t('documents.timesheet.generatedOn', { date: formatLocalizedDate(new Date()) }) }}</p>
        </footer>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

export interface Attendance {
  id: string | number
  staff_id: string | number
  name: string
  role: string
  date: string // Format: 'YYYY-MM-DD'
  status: 'present' | 'absent' | 'paid-off-day' | 'unpaid-off-day'
  timestamp: string
  net_worked_minutes: number | null
  overtime_minutes: number
  missing_punch_state: 'none' | 'check_in' | 'check_out' | 'both'
}

const props = defineProps<{
  attendances: Attendance[]
  startDate: string // Format: 'YYYY-MM-DD'
  endDate: string // Format: 'YYYY-MM-DD'
  totalWorkedMinutes: number
  totalOvertimeMinutes: number
}>()

const { locale } = useI18n()

const formatDuration = (minutes: number | null) => minutes === null
  ? '—'
  : `${new Intl.NumberFormat(locale.value, { style: 'unit', unit: 'hour', unitDisplay: 'narrow' }).format(Math.floor(minutes / 60))} ${new Intl.NumberFormat(locale.value, { style: 'unit', unit: 'minute', unitDisplay: 'narrow' }).format(minutes % 60)}`

const weekDays = computed(() => Array.from({ length: 7 }, (_, day) =>
  new Intl.DateTimeFormat(locale.value, { weekday: 'short', timeZone: 'UTC' }).format(new Date(Date.UTC(2023, 0, day + 1))),
))

// Helper to format date cleanly avoiding timezone shifting issues
const formatDateKey = (year: number, month: number, day: number): string => {
  const m = String(month + 1).padStart(2, '0')
  const d = String(day).padStart(2, '0')
  return `${year}-${m}-${d}`
}

// Group attendances by date for O(1) lookups during rendering
const attendancesByDate = computed(() => {
  const map = new Map<string, Attendance[]>()
  for (const att of props.attendances) {
    if (!map.has(att.date)) {
      map.set(att.date, [])
    }
    map.get(att.date)?.push(att)
  }
  return map
})

// Generate the months and exact 42-day grid layouts
const calendarPages = computed(() => {
  const start = new Date(props.startDate)
  const end = new Date(props.endDate)

  // Normalize to the 1st of the month to safely iterate
  let currentMonth = new Date(start.getFullYear(), start.getMonth(), 1)
  const endMonth = new Date(end.getFullYear(), end.getMonth(), 1)

  const pages = []
  const todayKey = formatDateKey(new Date().getFullYear(), new Date().getMonth(), new Date().getDate())

  while (currentMonth <= endMonth) {
    const year = currentMonth.getFullYear()
    const month = currentMonth.getMonth()
const monthName = formatLocalizedDate(currentMonth, { month: 'long' })

    // Calendar math
    const firstDayOfMonth = new Date(year, month, 1)
    const lastDayOfMonth = new Date(year, month + 1, 0)

    const startingDayOfWeek = firstDayOfMonth.getDay() // 0 = Sun, 6 = Sat
    const daysInMonth = lastDayOfMonth.getDate()
    const daysInPrevMonth = new Date(year, month, 0).getDate()

    const days = []

    // 1. Padding: Previous Month Days
    for (let i = startingDayOfWeek - 1; i >= 0; i--) {
      const dayNum = daysInPrevMonth - i
      const prevMonth = month === 0 ? 11 : month - 1
      const prevYear = month === 0 ? year - 1 : year
      const dateStr = formatDateKey(prevYear, prevMonth, dayNum)

      days.push({
        dayNumber: dayNum,
        dateStr,
        isCurrentMonth: false,
        isToday: dateStr === todayKey,
        attendances: attendancesByDate.value.get(dateStr) || []
      })
    }

    // 2. Current Month Days
    for (let i = 1; i <= daysInMonth; i++) {
      const dateStr = formatDateKey(year, month, i)
      days.push({
        dayNumber: i,
        dateStr,
        isCurrentMonth: true,
        isToday: dateStr === todayKey,
        attendances: attendancesByDate.value.get(dateStr) || []
      })
    }

    // 3. Padding: Next Month Days (Fill up to 42 cells for exactly 6 rows)
    const remainingCells = 42 - days.length
    for (let i = 1; i <= remainingCells; i++) {
      const nextMonth = month === 11 ? 0 : month + 1
      const nextYear = month === 11 ? year + 1 : year
      const dateStr = formatDateKey(nextYear, nextMonth, i)

      days.push({
        dayNumber: i,
        dateStr,
        isCurrentMonth: false,
        isToday: dateStr === todayKey,
        attendances: attendancesByDate.value.get(dateStr) || []
      })
    }

    pages.push({
      year,
      month,
      monthName,
      days
    })

    // Advance loop
    currentMonth.setMonth(currentMonth.getMonth() + 1)
  }

  return pages
})

const printReport = () => {
  window.print()
}
</script>

<style scoped>
/* ==========================================
   CSS Variables & Screen Layout
========================================== */
.timesheet-wrapper {
  --primary-color: #0f172a;
  --text-main: #334155;
  --text-muted: #94a3b8;
  --border-light: #e2e8f0;
  --bg-page: #ffffff;
  --bg-body: #f1f5f9;
  --bg-header: #f8fafc;
  --bg-out-of-month: #f8fafc;
  --color-present: #10b981;
  --color-absent: #ef4444;

  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  color: var(--text-main);
  background: var(--bg-body);
  min-height: 100vh;
  padding: 2rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2rem;
}

.actions {
  width: 100%;
  max-width: 210mm;
  display: flex;
  justify-content: flex-end;
}

.print-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.25rem;
  background-color: var(--primary-color);
  color: white;
  border: none;
  border-radius: 6px;
  font-weight: 500;
  font-size: 0.875rem;
  cursor: pointer;
  transition: opacity 0.2s;
}

.print-btn:hover {
  opacity: 0.9;
}

.print-container {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

/* ==========================================
   A4 Page Styling
========================================== */
.a4-page {
  background: var(--bg-page);
  width: 210mm;
  height: 297mm;
  padding: 15mm 20mm;
  box-sizing: border-box;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  border-radius: 4px;
}

.timesheet-page-header {
  margin-bottom: 1rem;
  padding-bottom: 0.75rem;
  text-align: center;
  border-bottom: 2px solid var(--primary-color);
}

.timesheet-page-header h2 {
  margin: 0;
  font-size: 1.75rem;
  color: var(--primary-color);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.timesheet-page-header .subtitle {
  margin: 0.25rem 0 0 0;
  color: var(--text-muted);
  font-size: 0.875rem;
}

.page-footer {
  margin-top: auto;
  padding-top: 1rem;
  font-size: 0.75rem;
  color: var(--text-muted);
  text-align: end;
  border-top: 1px solid var(--border-light);
}

/* ==========================================
   Calendar Grid System
========================================== */
.calendar-grid {
  flex: 1;
  display: grid;
  width: 100%;
  margin-inline: auto;
  box-sizing: border-box;
  grid-template-columns: repeat(7, 1fr);
  grid-template-rows: auto repeat(6, 1fr);
  /* 1 row for header, 6 rows for weeks */
  border: 1px solid var(--border-light);
  border-radius: 8px;
  overflow: hidden;
  background: var(--border-light);
  /* Acts as border color for gaps */
  gap: 1px;
  /* Creates clean 1px borders between all cells */
}

.weekday-header {
  background: var(--primary-color);
  text-align: center;
  padding: 0.75rem 0;
  font-weight: 600;
  font-size: 0.875rem;
  color: #ffffff;
  letter-spacing: 0.03em;
}

.calendar-cell {
  background: var(--bg-page);
  padding: 0.35rem;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.calendar-cell.out-of-month {
  background: var(--bg-out-of-month);
}

.calendar-cell.out-of-month .day-number {
  color: var(--text-muted);
}

.day-number {
  font-size: 0.875rem;
  font-weight: 500;
  margin-bottom: 0.25rem;
  align-self: flex-end;
}

.calendar-cell.is-today .day-number {
  background: var(--primary-color);
  color: white;
  width: 22px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  margin-top: -2px;
  margin-inline-end: -2px;
}

/* ==========================================
   Attendance Items
========================================== */
.attendances-list {
  display: flex;
  flex-direction: column;
  gap: 3px;
  overflow: hidden;
}

.attendance-item {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.65rem;
  /* Small font to fit multiple staff on A4 */
  background: #f8fafc;
  padding: 2px 4px;
  border-radius: 3px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.status-icon {
  width: 12px;
  height: 12px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.icon-present {
  stroke: var(--color-present);
}

.icon-absent {
  stroke: var(--color-absent);
}

.staff-name {
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
}

[dir='rtl'] .calendar-grid {
  direction: rtl;
}

[dir='rtl'] .attendance-item {
  flex-direction: row-reverse;
}

/* ==========================================
   Print CSS Configuration
========================================== */
@media print {
  .no-print {
    display: none !important;
  }

  .timesheet-wrapper {
    background: none;
    padding: 0;
    margin: 0;
    gap: 0;
    display: block;
  }

  .print-container {
    gap: 0;
    width: 100%;
  }

  .a4-page {
    box-shadow: none;
    border-radius: 0;
    margin: 0 auto;
    width: 210mm;
    max-width: 210mm;
    break-after: auto;
    page-break-after: auto;
    break-inside: avoid;
    page-break-inside: avoid;
    -webkit-print-color-adjust: exact;
    /* Force colors/backgrounds to print */
    print-color-adjust: exact;
  }

  /* A forced break belongs only between pages, never after the final page. */
  .a4-page:not(:last-child) {
    break-after: page;
    page-break-after: always;
  }
}
</style>
