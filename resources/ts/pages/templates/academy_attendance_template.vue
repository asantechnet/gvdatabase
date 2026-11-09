<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBranding } from '@/composables/useBranding'
import { formatActiveDate } from '@/utils/localization'
import type { AcademyAttendanceRow, AcademyTeacherTimesheetRow } from '@/api/academyAttendance'

const props = defineProps<{
  title: string
  month: string
  dates: string[]
  rows: AcademyAttendanceRow[]
  teacherRows?: AcademyTeacherTimesheetRow[]
  teacherName?: string
  mode: 'students' | 'teachers'
}>()

const { t, locale } = useI18n()
const { logoUrl, originalCompanyName } = useBranding()
const dayNumbers = computed(() => props.dates.map(date => Number(date.slice(8, 10))))
const statusLabel = (status: string | undefined) => status ? t(`academy.attendance.status.${status}`) : ''
const legendStatuses = ['present', 'absent', 'late', 'excused']
const monthLabel = computed(() => {
  const [year, monthNumber] = props.month.split('-').map(Number)
  if (!year || !monthNumber)
    return props.month

  return new Intl.DateTimeFormat(locale.value, { month: 'long', year: 'numeric', timeZone: 'UTC' })
    .format(new Date(Date.UTC(year, monthNumber - 1, 1)))
})
</script>

<template>
  <div class="academy-attendance-document" :dir="$i18n.locale === 'en' ? 'ltr' : 'rtl'">
    <div class="print-container">
      <header class="academy-print-header">
        <div class="d-flex align-center ga-3">
          <img v-if="logoUrl" :src="logoUrl" :alt="originalCompanyName">
          <div>
            <strong>{{ originalCompanyName }}</strong>
            <div class="text-caption">{{ t('academy.attendance.printSubtitle') }}</div>
          </div>
        </div>
        <div class="text-end">
          <h2>{{ title }}</h2>
          <div class="text-caption">{{ monthLabel }}</div>
        </div>
      </header>

      <table v-if="mode === 'students'" class="academy-print-table">
        <thead>
          <tr>
            <th class="name-cell">{{ t('academy.fields.student') }}</th>
            <th v-for="day in dayNumbers" :key="day">{{ day }}</th>
            <th>{{ t('academy.attendance.totalRecorded') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.enrollment_id">
            <td class="name-cell">{{ row.name }}<div class="student-number">{{ row.student_number }}</div></td>
            <td v-for="date in dates" :key="date" class="attendance-cell">
              <span v-if="row.attendance[date]" :class="`status-${row.attendance[date].status}`">{{ statusLabel(row.attendance[date].status).charAt(0) }}</span>
            </td>
            <td>{{ Object.keys(row.attendance).length }}</td>
          </tr>
        </tbody>
      </table>

      <table v-else class="academy-print-table">
        <thead>
          <tr>
            <th>{{ t('academy.fields.teacher') }}</th>
            <th>{{ t('academy.fields.class') }}</th>
            <th>{{ t('academy.fields.date') }}</th>
            <th>{{ t('academy.fields.status') }}</th>
            <th>{{ t('academy.fields.hours') }}</th>
            <th>{{ t('academy.fields.note') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in teacherRows" :key="row.id">
            <td>{{ teacherName || row.teacher_id }}</td>
            <td>{{ row.class_name ?? '—' }}</td>
            <td>{{ formatActiveDate(row.date) }}</td>
            <td>{{ statusLabel(row.status) }}</td>
            <td>{{ row.starts_at ?? '—' }} — {{ row.ends_at ?? '—' }} ({{ row.minutes ?? 0 }}m)</td>
            <td>{{ row.note ?? '—' }}</td>
          </tr>
        </tbody>
      </table>

      <footer class="academy-print-footer">
        <div class="academy-print-legend">
          <span class="academy-print-legend__title">{{ t('academy.attendance.legend') }}</span>
          <span v-for="status in legendStatuses" :key="status" class="academy-print-legend__item">
            <i class="academy-print-legend__swatch" :class="`status-${status}`" />
            {{ statusLabel(status) }}
          </span>
        </div>
        <div class="academy-print-meta">
          <span>{{ t('academy.attendance.printFooter') }}</span>
          <span>{{ formatActiveDate(new Date()) }}</span>
        </div>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.academy-attendance-document { color: #111; background: #fff; }
.print-container { inline-size: 100%; }
.academy-print-header { display: flex; align-items: center; justify-content: space-between; border-block-end: 2px solid #80027f; padding-block-end: 8px; margin-block-end: 12px; }
.academy-print-header img { max-inline-size: 38mm; max-block-size: 14mm; object-fit: contain; }
.academy-print-header h2 { margin: 0; font-size: 16pt; }
.academy-print-table { inline-size: 100%; border-collapse: collapse; table-layout: fixed; font-size: 7pt; }
.academy-print-table th, .academy-print-table td { border: 1px solid #cbd5e1; padding: 3px 4px; text-align: center; overflow: hidden; }
.academy-print-table th { background: #f1f5f9; font-weight: 700; }
.academy-print-table .name-cell { inline-size: 34mm; text-align: start; }
.academy-print-table .student-number { font-size: 6pt; color: #64748b; }
.attendance-cell { block-size: 6mm; }
.status-present { color: #047857; font-weight: 800; }
.status-absent { color: #b91c1c; font-weight: 800; }
.status-late { color: #b45309; font-weight: 800; }
.status-excused { color: #1d4ed8; font-weight: 800; }
.academy-print-footer { display: flex; justify-content: space-between; align-items: flex-start; gap: 6mm; margin-block-start: 8px; font-size: 8pt; color: #475569; }
.academy-print-legend { display: flex; flex-wrap: wrap; align-items: center; gap: 2mm 4mm; max-inline-size: 70%; }
.academy-print-legend__title { font-weight: 700; color: #111; }
.academy-print-legend__item { display: inline-flex; align-items: center; gap: 1.2mm; white-space: nowrap; }
.academy-print-legend__swatch { display: inline-block; inline-size: 2.4mm; block-size: 2.4mm; border-radius: 50%; border: .2mm solid currentColor; }
.academy-print-legend__swatch.status-present { background: #047857; }
.academy-print-legend__swatch.status-absent { background: #b91c1c; }
.academy-print-legend__swatch.status-late { background: #b45309; }
.academy-print-legend__swatch.status-excused { background: #1d4ed8; }
.academy-print-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; white-space: nowrap; }
</style>
