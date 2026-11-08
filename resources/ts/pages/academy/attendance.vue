<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import type { AcademyClassRecord, AcademyOption } from '@/api/academyClasses'
import { AcademyClassesApi } from '@/api/academyClasses'
import { AcademyAttendanceApi, type AcademyAttendanceMonth, type AcademyAttendanceStatus, type AcademyTeacherMonth, type AcademyTeacherWriteRow } from '@/api/academyAttendance'
import MasterDataApi from '@/api/educationalMasterData'
import { canAccess } from '@/utils/authorization'
import { printDocument } from '@/utils/printDocument'
import AcademyAttendanceTemplate from '@/pages/templates/academy_attendance_template.vue'

const { t } = useI18n()
const toast = useToast()
const tab = ref<'students' | 'teachers'>('students')
const classes = ref<AcademyClassRecord[]>([])
const teachers = ref<AcademyOption[]>([])
const classId = ref<number | null>(null)
const teacherId = ref<number | null>(null)
const month = ref(new Date().toISOString().slice(0, 7))
const studentMonth = ref<AcademyAttendanceMonth | null>(null)
const teacherMonth = ref<AcademyTeacherMonth | null>(null)
const changes = ref<Record<string, AcademyAttendanceStatus>>({})
const loading = ref(false)
const failed = ref(false)
const canManage = computed(() => canAccess('manage_academy_attendance'))
const canPrint = computed(() => tab.value === 'students'
  ? (studentMonth.value?.rows.length ?? 0) > 0
  : (teacherMonth.value?.rows.length ?? 0) > 0)
const statuses: Array<AcademyAttendanceStatus | ''> = ['', 'present', 'absent', 'late', 'excused']
const teacherDialog = ref(false)
const teacherRow = ref<AcademyTeacherWriteRow>({
  teacher_id: 0,
  class_id: 0,
  date: new Date().toISOString().slice(0, 10),
  status: 'present',
  starts_at: '',
  ends_at: '',
  minutes: null,
  note: '',
})

const classTitle = computed(() => studentMonth.value?.class.name ?? '')
const teacherTitle = computed(() => teacherMonth.value?.teacher.name ?? '')

async function loadOptions() {
  try {
    const [classRows, teacherRows] = await Promise.all([AcademyClassesApi.list(), MasterDataApi.list('teachers')])
    classes.value = classRows
    teachers.value = teacherRows
    classId.value ||= classRows[0]?.id ?? null
    teacherId.value ||= teacherRows[0]?.id ?? null
  }
  catch { /* Global notifier owns the API error. */ }
}

async function loadStudent() {
  if (!classId.value)
    return
  loading.value = true
  failed.value = false
  try {
    studentMonth.value = await AcademyAttendanceApi.getMonth(classId.value, month.value)
    changes.value = {}
  }
  catch {
    failed.value = true
  }
  finally {
    loading.value = false
  }
}

async function loadTeacher() {
  if (!teacherId.value)
    return
  loading.value = true
  failed.value = false
  try {
    teacherMonth.value = await AcademyAttendanceApi.getTeacherMonth(teacherId.value, month.value)
  }
  catch {
    failed.value = true
  }
  finally {
    loading.value = false
  }
}

function inScope(row: AcademyAttendanceMonth['rows'][number], date: string): boolean {
  const classRow = studentMonth.value?.class
  if (!classRow)
    return false
  const within = (!row.started_on || date >= row.started_on)
    && (!row.ends_on || date <= row.ends_on)
    && (!classRow.starts_on || date >= classRow.starts_on)
    && (!classRow.ends_on || date <= classRow.ends_on)
  const weekday = new Date(`${date}T00:00:00Z`).getUTCDay() || 7
  return within && classRow.weekdays.includes(weekday)
}

function statusFor(row: AcademyAttendanceMonth['rows'][number], date: string): AcademyAttendanceStatus | '' {
  return changes.value[`${row.enrollment_id}|${date}`] ?? row.attendance[date]?.status ?? ''
}

function cycle(row: AcademyAttendanceMonth['rows'][number], date: string) {
  if (!canManage.value || !inScope(row, date))
    return
  const current = statusFor(row, date)
  const next = statuses[(statuses.indexOf(current) + 1) % statuses.length]
  if (!next)
    delete changes.value[`${row.enrollment_id}|${date}`]
  else
    changes.value[`${row.enrollment_id}|${date}`] = next
}

async function save() {
  const rows = Object.entries(changes.value).map(([key, status]) => {
    const [enrollmentId, date] = key.split('|')

    return { enrollment_id: Number(enrollmentId), date, status }
  })
  if (rows.length === 0)
    return
  try {
    await AcademyAttendanceApi.save(rows)
    toast.success(t('academy.attendance.saved'))
    await loadStudent()
  }
  catch { /* Global notifier owns the API error. */ }
}

function openTeacherRow() {
  teacherRow.value = {
    teacher_id: teacherId.value ?? 0,
    class_id: classId.value ?? classes.value[0]?.id ?? 0,
    date: new Date().toISOString().slice(0, 10),
    status: 'present',
    starts_at: '',
    ends_at: '',
    minutes: null,
    note: '',
  }
  teacherDialog.value = true
}

async function saveTeacherRow() {
  try {
    await AcademyAttendanceApi.saveTeacher([teacherRow.value])
    teacherDialog.value = false
    toast.success(t('academy.attendance.saved'))
    await loadTeacher()
  }
  catch { /* Global notifier owns the API error. */ }
}

function printSheet() {
  if (tab.value === 'students' && !studentMonth.value)
    return
  if (tab.value === 'teachers' && !teacherMonth.value)
    return
  void printDocument({
    bodyClass: 'printing-academy-attendance',
    pageStyle: 'size: A4 landscape; margin: 8mm;',
    element: document.querySelector<HTMLElement>('.academy-attendance-print-host'),
  })
}

watch([classId, month], () => {
  if (tab.value === 'students')
    void loadStudent()
})
watch(teacherId, () => {
  if (tab.value === 'teachers')
    void loadTeacher()
})
watch(tab, value => {
  if (value === 'students')
    void loadStudent()
  else
    void loadTeacher()
})
onMounted(async () => {
  await loadOptions()
  await loadStudent()
})
</script>

<template>
  <div class="academy-page pa-4 pa-sm-6">
    <div class="academy-attendance-print-host" aria-hidden="true">
      <AcademyAttendanceTemplate
        v-if="studentMonth && tab === 'students'"
        :title="`${classTitle} — ${t('academy.attendance.studentsTab')}`"
        :month="studentMonth.month"
        :dates="studentMonth.dates"
        :rows="studentMonth.rows"
        mode="students"
      />
      <AcademyAttendanceTemplate
        v-else-if="teacherMonth && tab === 'teachers'"
        :title="`${teacherTitle} — ${t('academy.attendance.teachersTab')}`"
        :month="teacherMonth.month"
        :dates="teacherMonth.dates"
        :rows="[]"
        :teacher-rows="teacherMonth.rows"
        mode="teachers"
      />
    </div>

    <ErpPageHeader :title="t('academy.attendance.title')" :description="t('academy.attendance.description')" section="academy">
      <template #actions>
        <VBtn variant="tonal" prepend-icon="tabler-printer" :disabled="!canPrint" @click="printSheet">{{ t('academy.attendance.print') }}</VBtn>
        <VBtn v-if="canManage && tab === 'students'" color="primary" :disabled="Object.keys(changes).length === 0" @click="save">
          {{ t('common.actions.save') }}
        </VBtn>
        <VBtn v-if="canManage && tab === 'teachers'" color="primary" prepend-icon="tabler-plus" @click="openTeacherRow">
          {{ t('academy.attendance.addTeacherRow') }}
        </VBtn>
      </template>
    </ErpPageHeader>

    <VCard>
      <VCardText class="d-flex flex-wrap ga-3 align-center">
        <VTabs v-model="tab" density="compact">
          <VTab value="students">{{ t('academy.attendance.studentsTab') }}</VTab>
          <VTab value="teachers">{{ t('academy.attendance.teachersTab') }}</VTab>
        </VTabs>
        <VSpacer />
        <VTextField v-model="month" type="month" :label="t('academy.fields.month')" density="compact" hide-details style="max-width: 180px" />
        <VSelect v-if="tab === 'students'" v-model="classId" :items="classes" item-title="name" item-value="id" :label="t('academy.fields.class')" density="compact" hide-details style="min-width: 200px" />
        <VSelect v-else v-model="teacherId" :items="teachers" item-title="name" item-value="id" :label="t('academy.fields.teacher')" density="compact" hide-details style="min-width: 200px" />
      </VCardText>

      <VCardText>
        <VProgressLinear v-if="loading" indeterminate />
        <VAlert v-else-if="failed" type="error" variant="tonal">{{ t('academy.attendance.loadFailed') }}</VAlert>
        <VAlert v-else-if="tab === 'students' && studentMonth?.rows.length === 0" type="info" variant="tonal">{{ t('academy.attendance.empty') }}</VAlert>
        <div v-else-if="tab === 'students' && studentMonth" class="attendance-scroll">
          <table class="attendance-grid">
            <thead>
              <tr>
                <th class="student-col">{{ t('academy.fields.student') }}</th>
                <th v-for="date in studentMonth.dates" :key="date">{{ Number(date.slice(8, 10)) }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in studentMonth.rows" :key="row.enrollment_id">
                <td class="student-col">{{ row.name }}<div class="text-caption">{{ row.student_number }}</div></td>
                <td v-for="date in studentMonth.dates" :key="date">
                  <button
                    type="button"
                    class="attendance-cell"
                    :class="{ 'out-of-scope': !inScope(row, date), [`status-${statusFor(row, date)}`]: statusFor(row, date) }"
                    :disabled="!canManage || !inScope(row, date)"
                    :aria-label="`${row.name} ${date} ${statusFor(row, date) || t('academy.attendance.emptyCell')}`"
                    @click="cycle(row, date)"
                  >
                    {{ statusFor(row, date) ? t(`academy.attendance.status.${statusFor(row, date)}`).charAt(0) : '' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <VAlert v-else-if="tab === 'teachers' && teacherMonth?.rows.length === 0" type="info" variant="tonal">{{ t('academy.attendance.empty') }}</VAlert>
        <VTable v-else-if="tab === 'teachers' && teacherMonth" density="compact">
          <thead><tr><th>{{ t('academy.fields.class') }}</th><th>{{ t('academy.fields.date') }}</th><th>{{ t('academy.fields.status') }}</th><th>{{ t('academy.fields.hours') }}</th><th>{{ t('academy.fields.note') }}</th></tr></thead>
          <tbody>
            <tr v-for="row in teacherMonth.rows" :key="row.id">
              <td>{{ row.class_name ?? '—' }}</td>
              <td>{{ row.date }}</td>
              <td>{{ t(`academy.attendance.status.${row.status}`) }}</td>
              <td>{{ row.starts_at ?? '—' }} — {{ row.ends_at ?? '—' }} ({{ row.minutes ?? 0 }}m)</td>
              <td>{{ row.note ?? '—' }}</td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
    </VCard>

    <VDialog v-model="teacherDialog" max-width="560">
      <VCard>
        <VCardTitle>{{ t('academy.attendance.addTeacherRow') }}</VCardTitle>
        <VCardText>
          <VSelect v-model="teacherRow.teacher_id" :items="teachers" item-title="name" item-value="id" :label="t('academy.fields.teacher')" class="mb-3" />
          <VSelect v-model="teacherRow.class_id" :items="classes" item-title="name" item-value="id" :label="t('academy.fields.class')" class="mb-3" />
          <VTextField v-model="teacherRow.date" type="date" :label="t('academy.fields.date')" class="mb-3" />
          <VSelect v-model="teacherRow.status" :items="['present', 'absent'].map(value => ({ title: t(`academy.attendance.status.${value}`), value }))" :label="t('academy.fields.status')" class="mb-3" />
          <VRow>
            <VCol cols="6"><VTextField v-model="teacherRow.starts_at" type="time" :label="t('academy.fields.startsAt')" /></VCol>
            <VCol cols="6"><VTextField v-model="teacherRow.ends_at" type="time" :label="t('academy.fields.endsAt')" /></VCol>
          </VRow>
          <VTextField v-model.number="teacherRow.minutes" type="number" min="0" max="1440" :label="t('academy.fields.minutes')" class="mb-3" />
          <VTextarea v-model="teacherRow.note" :label="t('academy.fields.note')" rows="2" />
        </VCardText>
        <VCardActions><VSpacer /><VBtn @click="teacherDialog = false">{{ t('common.actions.cancel') }}</VBtn><VBtn color="primary" @click="saveTeacherRow">{{ t('common.actions.save') }}</VBtn></VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.academy-attendance-print-host { display: none; }
@media print {
  :global(body.printing-academy-attendance .academy-attendance-print-host) { display: block !important; }
  :global(body.printing-academy-attendance .academy-page > :not(.academy-attendance-print-host)) { display: none !important; }
}
.attendance-scroll { overflow-x: auto; }
.attendance-grid { border-collapse: collapse; min-inline-size: max-content; font-size: 12px; }
.attendance-grid th, .attendance-grid td { border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); padding: 2px; text-align: center; }
.attendance-grid .student-col { position: sticky; inset-inline-start: 0; background: rgb(var(--v-theme-surface)); text-align: start; min-inline-size: 170px; z-index: 1; }
.attendance-grid th:not(.student-col) { min-inline-size: 24px; writing-mode: vertical-rl; }
.attendance-cell { inline-size: 22px; block-size: 22px; border: 0; border-radius: 4px; background: transparent; cursor: pointer; font-weight: 700; }
.attendance-cell.out-of-scope { opacity: .25; cursor: not-allowed; }
.status-present { background: rgba(16, 185, 129, .18); color: #047857; }
.status-absent { background: rgba(239, 68, 68, .18); color: #b91c1c; }
.status-late { background: rgba(245, 158, 11, .2); color: #b45309; }
.status-excused { background: rgba(59, 130, 246, .18); color: #1d4ed8; }
</style>
