<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AcademyEnrollmentDialog from '@/components/academy/AcademyEnrollmentDialog.vue'
import AcademySlipTemplate from '@/pages/templates/academy_slip_template.vue'
import AcademyStudentCard from '@/components/academy/AcademyStudentCard.vue'
import AcademyStudentDialog from '@/components/academy/AcademyStudentDialog.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import PrintableListReport from '@/components/PrintableListReport.vue'
import DataTableFilterMenu from '@/components/responsive-data-table/DataTableFilterMenu.vue'
import ResponsiveDataTableServer from '@/components/responsive-data-table/ResponsiveDataTableServer.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import AcademySlipApi, { type AcademySlipPayload } from '@/api/academySlip'
import { AcademyClassesApi, type AcademyClassRecord } from '@/api/academyClasses'
import { AcademyEnrollmentsApi, type AcademyEnrollmentRecord, type AcademyEnrollmentStatus } from '@/api/academyEnrollments'
import { AcademyTeachersApi, type AcademyTeacher } from '@/api/academyTeachers'
import EmployeeAvatar from '@/components/hr/EmployeeAvatar.vue'
import { canAccess } from '@/utils/authorization'
import { formatActiveDate, formatActiveMoney } from '@/utils/localization'

type EndingFilter = '' | 'ending'

const { t } = useI18n()
const classes = ref<AcademyClassRecord[]>([])
const teachers = ref<AcademyTeacher[]>([])
const enrollments = ref<AcademyEnrollmentRecord[]>([])
const loading = ref(true)
const failed = ref(false)
const classId = ref<number | null>(null)
const status = ref<AcademyEnrollmentStatus | ''>('')
const ending = ref<EndingFilter>('')
const teacherId = ref<number | null>(null)
const search = ref('')
const page = ref(1)
const perPage = ref(25)
const meta = ref({ current_page: 1, last_page: 1, per_page: 25, total: 0 })
const filterMenuOpen = ref(false)
const filterDraft = reactive({ classId: null as number | null, status: '' as AcademyEnrollmentStatus | '', ending: '' as EndingFilter, teacherId: null as number | null })
const detailOpen = ref(false)
const editing = ref<AcademyEnrollmentRecord | null>(null)
const cardOpen = ref(false)
const cardLoading = ref(false)
const cardEnrollments = ref<AcademyEnrollmentRecord[]>([])
const cardKey = ref<number | null>(null)
const addOpen = ref(false)
const reportRows = ref<string[][]>([])
const slipForPrint = ref<AcademySlipPayload | null>(null)
const printingSlip = ref(false)
const slipTemplate = ref<InstanceType<typeof AcademySlipTemplate> | null>(null)
const canManage = computed(() => canAccess('manage_academy_classes'))
const canPrintSlip = computed(() => canAccess('view_academy_contracts'))
const statusItems = computed(() => ['active', 'completed', 'withdrawn', 'cancelled'].map(value => ({ title: t(`academy.status.${value}`), value })))
const endingItems = computed(() => [{ title: t('academy.students.endingSoon'), value: 'ending' }])
const hasActiveFilters = computed(() => Boolean(classId.value || status.value || ending.value || teacherId.value))
const headers = computed(() => [
  { title: t('academy.fields.id'), key: 'student_number', sortable: false },
  { title: t('academy.fields.student'), key: 'student_name', sortable: false },
  { title: t('academy.fields.contact'), key: 'contact', sortable: false },
  { title: t('academy.fields.class'), key: 'class_name', sortable: false },
  { title: t('academy.fields.joiningDate'), key: 'joining_date', sortable: false },
  { title: t('academy.fields.teacher'), key: 'teachers', sortable: false },
  { title: t('academy.fields.status'), key: 'status', sortable: false },
  { title: t('academy.fields.fee'), key: 'fee', sortable: false },
  { title: t('academy.fields.balance'), key: 'balance', sortable: false },
  { title: t('common.fields.actions'), key: 'actions', sortable: false, align: 'end' },
])
const tableEnrollments = computed(() => enrollments.value.map(row => ({
  ...row,
  student_name: studentName(row),
  class_name: row.class?.name ?? '—',
  joining_date: row.started_on,
  contact: row.student?.contact ?? row.applicant?.contact ?? null,
})))

const studentName = (row: AcademyEnrollmentRecord) => row.student?.name ?? row.applicant?.name ?? '—'
const studentNumber = (row: AcademyEnrollmentRecord) => row.student?.student_number ?? row.student_number ?? '—'
const teacherNames = (row: AcademyEnrollmentRecord) => row.class?.teachers.map(teacher => teacher.name).join(', ') || '—'
const statusColor = (value: AcademyEnrollmentStatus) => ({ active: 'success', completed: 'info', withdrawn: 'warning', cancelled: 'error' }[value])
const fee = (row: AcademyEnrollmentRecord) => row.line ? formatActiveMoney(row.line.fee, row.line.currency) : formatActiveMoney(row.class?.fee ?? 0)
const balance = (row: AcademyEnrollmentRecord) => row.line ? formatActiveMoney(row.line.balance, row.line.currency) : '—'

async function load() {
  loading.value = true
  failed.value = false
  try {
    const response = await AcademyEnrollmentsApi.list({
      class_id: classId.value ?? undefined,
      status: status.value || undefined,
      ending: ending.value === 'ending' || undefined,
      teacher_id: teacherId.value ?? undefined,
      search: search.value.trim() || undefined,
      page: page.value,
      per_page: perPage.value,
    })
    enrollments.value = response.data
    meta.value = response.meta
  }
  catch {
    failed.value = true
  }
  finally {
    loading.value = false
  }
}

async function loadOptions() {
  try {
    const [classRows, teacherRows] = await Promise.all([AcademyClassesApi.list(), AcademyTeachersApi.list()])
    classes.value = classRows
    teachers.value = teacherRows
  }
  catch { /* The API notifier provides the error feedback. */ }
}

function syncFilterDraft() {
  filterDraft.classId = classId.value
  filterDraft.status = status.value
  filterDraft.ending = ending.value
  filterDraft.teacherId = teacherId.value
}

function applyFilters() {
  classId.value = filterDraft.classId
  status.value = filterDraft.status
  ending.value = filterDraft.ending
  teacherId.value = filterDraft.teacherId
  page.value = 1
  filterMenuOpen.value = false
  void load()
}

function resetFilters() {
  classId.value = null
  status.value = ''
  ending.value = ''
  teacherId.value = null
  syncFilterDraft()
  page.value = 1
  filterMenuOpen.value = false
  void load()
}

function updateTableOptions(options: { page: number; itemsPerPage: number }) {
  if (page.value === options.page && perPage.value === options.itemsPerPage)
    return
  page.value = options.page
  perPage.value = options.itemsPerPage
  void load()
}

const debouncedSearch = useDebounceFn(() => {
  page.value = 1
  void load()
}, 300)

function openDetail(row: AcademyEnrollmentRecord) {
  editing.value = row
  detailOpen.value = true
}

async function openCard(row: AcademyEnrollmentRecord) {
  cardKey.value = row.student?.applicant_id ?? row.applicant?.id ?? row.student?.id ?? row.student_id
  cardEnrollments.value = []
  cardLoading.value = true
  cardOpen.value = true
  try {
    const response = await AcademyEnrollmentsApi.list({
      search: row.student?.student_number ?? row.student?.name ?? row.applicant?.name ?? undefined,
      per_page: 100,
    })
    cardEnrollments.value = response.data.filter(item =>
      (item.student?.applicant_id ?? item.applicant?.id ?? item.student?.id ?? item.student_id) === cardKey.value,
    )
  }
  catch { /* The API notifier provides the error feedback. */ }
  finally {
    cardLoading.value = false
  }
}

async function printSlip(row: AcademyEnrollmentRecord) {
  if (printingSlip.value)
    return
  printingSlip.value = true
  try {
    slipForPrint.value = await AcademySlipApi.getForEnrollment(row.id)
    await nextTick()
    await slipTemplate.value?.print()
  }
  finally {
    printingSlip.value = false
    slipForPrint.value = null
  }
}

async function printTable() {
  const rows: AcademyEnrollmentRecord[] = []
  let requestedPage = 1
  let lastPage = 1
  do {
    const response = await AcademyEnrollmentsApi.list({
      class_id: classId.value ?? undefined,
      status: status.value || undefined,
      ending: ending.value === 'ending' || undefined,
      teacher_id: teacherId.value ?? undefined,
      search: search.value.trim() || undefined,
      page: requestedPage,
      per_page: 100,
    })
    rows.push(...response.data)
    lastPage = response.meta.last_page
    requestedPage += 1
  } while (requestedPage <= lastPage)
  reportRows.value = rows.map(row => [studentNumber(row), studentName(row), row.student?.contact ?? row.applicant?.contact ?? '—', row.class?.name ?? '—', formatActiveDate(row.started_on), teacherNames(row), t(`academy.status.${row.status}`), fee(row), balance(row)])
  const { printDocument } = await import('@/utils/printDocument')
  await printDocument({ bodyClass: 'printing-table-academy-students', pageStyle: 'size: A4 landscape; margin: 12mm;' })
}

watch(filterMenuOpen, open => {
  if (open)
    syncFilterDraft()
})
onMounted(async () => {
  await Promise.all([loadOptions(), load()])
})
</script>

<template>
  <div class="academy-page">
    <ErpPageHeader :title="t('academy.students.title')" section="academy" />

    <VCard>
      <VCardText>
        <VRow align="center" class="academy-students-toolbar">
          <VCol cols="12" md="6" lg="5"><ErpSearchField v-model="search" :placeholder="t('academy.students.title')" @input="debouncedSearch" /></VCol>
          <VCol cols="12" md="6" lg="7" class="academy-students-toolbar__actions d-flex flex-wrap justify-end ga-2">
            <DataTableFilterMenu v-model="filterMenuOpen" :active="hasActiveFilters" :show-reset="false">
              <ErpField v-model="filterDraft.classId" kind="select" :items="classes" item-title="name" item-value="id" :label="t('academy.fields.class')" clearable />
              <ErpField v-model="filterDraft.teacherId" kind="select" :items="teachers" item-title="name" item-value="id" :label="t('academy.fields.teacher')" clearable />
              <ErpField v-model="filterDraft.status" kind="select" :items="statusItems" :label="t('academy.fields.status')" clearable />
              <ErpField v-model="filterDraft.ending" kind="select" :items="endingItems" :label="t('academy.students.endingSoon')" clearable />
              <template #actions><VBtn class="academy-students-filter-reset" variant="flat" @click="resetFilters">{{ t('common.messages.reset') }}</VBtn><VBtn color="primary" variant="flat" class="academy-students-filter-apply" @click="applyFilters">{{ t('educational.apply') }}</VBtn></template>
            </DataTableFilterMenu>
            <VBtn variant="tonal" class="academy-students-toolbar__icon" :disabled="loading" :aria-label="t('common.actions.print')" @click="printTable"><VIcon icon="tabler-printer" /><VTooltip activator="parent">{{ t('common.actions.print') }}</VTooltip></VBtn>
            <VBtn v-if="canManage" color="primary" :disabled="loading" @click="addOpen = true"><VIcon icon="tabler-plus" class="me-1" />{{ t('academy.students.add') }}</VBtn>
          </VCol>
        </VRow>
      </VCardText>

      <VAlert v-if="failed" type="error" variant="tonal" class="ma-4 mt-0">{{ t('academy.students.loadFailed') }}</VAlert>
      <ResponsiveDataTableServer
        v-else
        :headers="headers"
        :items="tableEnrollments"
        :items-length="meta.total"
        :page="page"
        :items-per-page="perPage"
        :items-per-page-options="[10, 25, 50, 100]"
        item-value="id"
        class="academy-students-table"
        mobile-cards
        mobile-expand-all
        mobile-actions-as-menu
        mobile-title-key="student_name"
        mobile-action-key="actions"
        :mobile-fields="['student_number', 'contact', 'class_name', 'joining_date', 'teachers', 'status', 'fee', 'balance']"
        :sticky-key-column="false"
        :sticky-action-column="false"
        :show-scroll-affordance="false"
        :skeleton-rows="5"
        :loading="loading"
        pagination
        @update:options="updateTableOptions"
      >
        <template #item.student_number="{ item }"><span class="academy-students-table__id">{{ studentNumber(item) }}</span></template>
        <template #item.student_name="{ item }"><button class="academy-students-table__student d-inline-flex align-center ga-2" type="button" @click="openCard(item)"><EmployeeAvatar :name="studentName(item)" size="28" />{{ studentName(item) }}</button></template>
        <template #item.contact="{ item }"><span dir="ltr">{{ item.student?.contact ?? item.applicant?.contact ?? '—' }}</span></template>
        <template #item.class_name="{ item }">{{ item.class?.name ?? '—' }}</template>
        <template #item.joining_date="{ item }">{{ formatActiveDate(item.started_on) }}</template>
        <template #item.teachers="{ item }"><div class="academy-students-table__teachers d-flex flex-wrap ga-2"><span v-for="teacher in item.class?.teachers ?? []" :key="teacher.id" class="d-inline-flex align-center ga-1"><EmployeeAvatar :name="teacher.name" :src="teacher.avatar" size="24" />{{ teacher.name }}</span><span v-if="!(item.class?.teachers?.length)">—</span></div></template>
        <template #item.status="{ item }"><VChip size="small" :color="statusColor(item.status)" variant="tonal">{{ t(`academy.status.${item.status}`) }}</VChip></template>
        <template #item.fee="{ item }">{{ fee(item) }}</template>
        <template #item.balance="{ item }">{{ balance(item) }}</template>
        <template #item.actions="{ item }"><div class="d-flex justify-end" @click.stop><VMenu location="bottom end"><template #activator="{ props: menuProps }"><VBtn v-bind="menuProps" icon="tabler-dots-vertical" variant="tonal" size="small" class="academy-students-table__actions-trigger" :aria-label="t('common.messages.actions')" /></template><VList density="compact" min-width="220"><VListItem prepend-icon="tabler-address-book" :title="t('common.actions.viewFullDetails')" @click="openCard(item)" /><VListItem prepend-icon="tabler-list-details" :title="t('academy.students.detailTitle')" @click="openDetail(item)" /><VListItem v-if="canPrintSlip" prepend-icon="tabler-printer" :title="t('documents.academySlip.print')" :disabled="printingSlip" @click="printSlip(item)" /></VList></VMenu></div></template>
        <template #no-data><div class="academy-students-empty-state"><TableEmptyState icon="tabler-users-group" :min-height="0" :title="t('academy.students.emptyTitle')" :description="t('academy.students.emptyDescription')" /><VBtn v-if="canManage" color="primary" variant="tonal" class="academy-students-empty-state__action" @click="addOpen = true"><VIcon icon="tabler-plus" class="me-1" />{{ t('academy.students.add') }}</VBtn></div></template>
      </ResponsiveDataTableServer>
    </VCard>

    <PrintableListReport :title="t('academy.students.title')" :columns="headers.slice(0, -1).map(header => header.title)" :rows="reportRows" />
    <AcademyEnrollmentDialog v-model="detailOpen" :record="editing" :can-manage="canManage" @saved="load" />
    <AcademyStudentCard v-model="cardOpen" :enrollments="cardEnrollments" :loading="cardLoading" />
    <AcademyStudentDialog v-model="addOpen" :classes="classes" @saved="load" />
    <div v-if="slipForPrint" class="academy-students-slip-source"><AcademySlipTemplate ref="slipTemplate" :slip="slipForPrint" :copies="2" paper-size="A4 2x A5" /></div>
  </div>
</template>

<style scoped lang="scss">
.academy-students-toolbar__actions { align-items: center; }
.academy-students-toolbar__icon { flex: none; min-inline-size: 2.25rem !important; inline-size: 2.25rem !important; padding-inline: 0 !important; border-radius: 999px !important; font-size: .75rem !important; }
.academy-students-table :deep(.v-table__wrapper > table) { min-inline-size: 1060px; }
.academy-students-table :deep(.v-table__wrapper > table > thead > tr > th) { background: color-mix(in srgb, rgb(var(--v-theme-primary)) 8%, rgb(var(--v-theme-surface)) 92%) !important; color: rgb(var(--v-theme-on-surface)) !important; font-size: .75rem; font-weight: 800; letter-spacing: .035em; text-transform: uppercase; }
.academy-students-table :deep(.v-table__wrapper > table > thead > tr > th), .academy-students-table :deep(.v-table__wrapper > table > tbody > tr > td) { white-space: nowrap; }
.academy-students-table__id { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .8125rem; font-variant-numeric: tabular-nums; }
.academy-students-table__student { padding: 0; border: 0; background: none; color: rgb(var(--v-theme-primary)); cursor: pointer; font: inherit; font-weight: 700; text-align: start; }
.academy-students-table__student:focus-visible { outline: 2px solid rgb(var(--v-theme-primary)); outline-offset: 2px; }
.academy-students-table__teachers { max-inline-size: 13rem; overflow: hidden; }
.academy-students-table__actions-trigger { border-radius: 999px !important; background: rgba(var(--v-theme-on-surface), .04) !important; color: rgba(var(--v-theme-on-surface), .72) !important; }
.academy-students-table__actions-trigger:hover { background: rgba(var(--v-theme-on-surface), .08) !important; }
.academy-students-filter-reset { background: rgba(var(--v-theme-primary), .12) !important; color: rgb(var(--v-theme-primary)) !important; }
.academy-students-filter-reset:hover, .academy-students-filter-apply, .academy-students-filter-apply:hover { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
.academy-students-empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; min-block-size: 17rem; padding: 1.25rem 1.25rem 2rem; }
.academy-students-empty-state :deep(.table-empty-state) { min-block-size: 0 !important; padding-block: 1rem; }
.academy-students-empty-state__action { margin-block-start: -.25rem; background: rgba(var(--v-theme-primary), .12) !important; color: rgb(var(--v-theme-primary)) !important; }
.academy-students-slip-source { display: none; }
@media (max-width: 959px) { .academy-students-table.responsive-data-table--mobile-cards :deep(.v-table__wrapper > table > tbody > tr > td) { white-space: normal; } .academy-students-table.responsive-data-table--mobile-cards :deep(.responsive-data-table__field-value > .d-flex) { justify-content: flex-end; } }
@media (max-width: 599px) { .academy-students-toolbar__actions { justify-content: space-between !important; } }
</style>
