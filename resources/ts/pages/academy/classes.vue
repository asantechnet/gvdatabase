<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import AcademyClassDialog from '@/components/academy/AcademyClassDialog.vue'
import ConfirmDialog from '@/components/dialogs/ConfirmDialog.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import PrintableListReport from '@/components/PrintableListReport.vue'
import DataTableFilterMenu from '@/components/responsive-data-table/DataTableFilterMenu.vue'
import ResponsiveDataTable from '@/components/responsive-data-table/ResponsiveDataTable.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import { AcademyClassesApi, type AcademyClassRecord, type AcademyOption } from '@/api/academyClasses'
import { AcademyTeachersApi, type AcademyTeacher } from '@/api/academyTeachers'
import EmployeeAvatar from '@/components/hr/EmployeeAvatar.vue'
import MasterDataApi from '@/api/educationalMasterData'
import { canAccess } from '@/utils/authorization'
import { formatActiveDate, formatActiveMoney } from '@/utils/localization'
import { printDocument } from '@/utils/printDocument'

type ClassStatusFilter = '' | 'active' | 'inactive' | 'completed'

const { t } = useI18n()
const toast = useToast()
const classes = ref<AcademyClassRecord[]>([])
const programs = ref<AcademyOption[]>([])
const teachers = ref<AcademyTeacher[]>([])
const loading = ref(true)
const failed = ref(false)
const search = ref('')
const filterMenuOpen = ref(false)
const filterProgramId = ref<number | null>(null)
const filterTeacherId = ref<number | null>(null)
const filterStatus = ref<ClassStatusFilter>('')
const filterDraft = reactive({
  programId: null as number | null,
  teacherId: null as number | null,
  status: '' as ClassStatusFilter,
})
const dialogOpen = ref(false)
const editing = ref<AcademyClassRecord | null>(null)
const detailsOpen = ref(false)
const detailsLoading = ref(false)
const selectedClass = ref<AcademyClassRecord | null>(null)
const roster = ref<NonNullable<AcademyClassRecord['roster']>>([])
const deleteDialogOpen = ref(false)
const completeDialogOpen = ref(false)
const deleteTarget = ref<AcademyClassRecord | null>(null)
const completeTarget = ref<AcademyClassRecord | null>(null)
const canManage = computed(() => canAccess('manage_academy_classes'))
const userData = useCookie<{ employee?: { id?: number } | null } | null>('userData')
const ownEmployeeId = computed(() => userData.value?.employee?.id ?? null)
const canComplete = (row: AcademyClassRecord) => canManage.value
  || (ownEmployeeId.value !== null && row.teachers.some(teacher => teacher.id === ownEmployeeId.value))
const weekdays = computed(() => ['', t('academy.fields.weekdaysShort.mon'), t('academy.fields.weekdaysShort.tue'), t('academy.fields.weekdaysShort.wed'), t('academy.fields.weekdaysShort.thu'), t('academy.fields.weekdaysShort.fri'), t('academy.fields.weekdaysShort.sat'), t('academy.fields.weekdaysShort.sun')])
const headers = computed(() => [
  { title: t('academy.fields.class'), key: 'name' },
  { title: t('academy.fields.program'), key: 'program' },
  { title: t('academy.fields.seats'), key: 'seats' },
  { title: t('academy.fields.teachers'), key: 'teachers' },
  { title: t('academy.fields.status'), key: 'status' },
  { title: t('common.fields.actions'), key: 'actions', sortable: false, align: 'end' },
])
const statusOptions = computed(() => [
  { title: t('common.statuses.active'), value: 'active' },
  { title: t('common.statuses.inactive'), value: 'inactive' },
  { title: t('academy.status.completed'), value: 'completed' },
])
const hasActiveFilters = computed(() => Boolean(filterProgramId.value || filterTeacherId.value || filterStatus.value))

const filteredClasses = computed(() => {
  const needle = search.value.trim().toLocaleLowerCase()

  return classes.value.filter(row => {
    const matchesSearch = !needle || [
      row.name,
      row.description,
      row.program?.name,
      ...row.teachers.map(teacher => teacher.name),
    ].filter(Boolean).some(value => String(value).toLocaleLowerCase().includes(needle))
    const matchesProgram = !filterProgramId.value || row.program?.id === filterProgramId.value
    const matchesTeacher = !filterTeacherId.value || row.teachers.some(teacher => teacher.id === filterTeacherId.value)
    const matchesStatus = !filterStatus.value
      || (filterStatus.value === 'completed' && Boolean(row.completed_at))
      || (filterStatus.value === 'active' && !row.completed_at && row.is_active)
      || (filterStatus.value === 'inactive' && !row.completed_at && !row.is_active)

    return matchesSearch && matchesProgram && matchesTeacher && matchesStatus
  })
})

async function load() {
  loading.value = true
  failed.value = false
  try {
    const [classRows, programRows, teacherRows] = await Promise.all([
      AcademyClassesApi.list(),
      MasterDataApi.list('programs'),
      AcademyTeachersApi.list(),
    ])
    classes.value = classRows
    programs.value = programRows
    teachers.value = teacherRows
  }
  catch {
    // Keep the rows already on screen; only an empty first load is a hard failure.
    failed.value = !classes.value.length
  }
  finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  dialogOpen.value = true
}

function openEdit(row: AcademyClassRecord) {
  editing.value = row
  dialogOpen.value = true
}

function syncFilterDraft() {
  filterDraft.programId = filterProgramId.value
  filterDraft.teacherId = filterTeacherId.value
  filterDraft.status = filterStatus.value
}

function applyFilters() {
  filterProgramId.value = filterDraft.programId
  filterTeacherId.value = filterDraft.teacherId
  filterStatus.value = filterDraft.status
  filterMenuOpen.value = false
}

function resetFilters() {
  filterProgramId.value = null
  filterTeacherId.value = null
  filterStatus.value = ''
  syncFilterDraft()
  filterMenuOpen.value = false
}

function requestDelete(row: AcademyClassRecord) {
  deleteTarget.value = row
  deleteDialogOpen.value = true
}

async function confirmDelete(confirmed: boolean) {
  if (!confirmed || !deleteTarget.value)
    return

  try {
    await AcademyClassesApi.remove(deleteTarget.value.id)
    toast.success(t('academy.classes.removed'))
    await load()
  }
  catch { /* The API notifier presents business-rule failures. */ }
  finally {
    deleteTarget.value = null
  }
}

function requestComplete(row: AcademyClassRecord) {
  completeTarget.value = row
  completeDialogOpen.value = true
}

async function confirmComplete(confirmed: boolean) {
  if (!confirmed || !completeTarget.value)
    return

  try {
    await AcademyClassesApi.complete(completeTarget.value.id)
    toast.success(t('academy.classes.completed'))
    await load()
  }
  catch { /* The API notifier presents business-rule failures. */ }
  finally {
    completeTarget.value = null
  }
}

async function viewDetails(row: AcademyClassRecord) {
  selectedClass.value = row
  roster.value = []
  detailsOpen.value = true
  detailsLoading.value = true
  try {
    const detail = await AcademyClassesApi.get(row.id)
    selectedClass.value = detail
    roster.value = detail.roster ?? []
  }
  catch { /* The API notifier presents request failures. */ }
  finally {
    detailsLoading.value = false
  }
}

const seats = (row: AcademyClassRecord) => `${row.seats_taken ?? 0} / ${row.capacity ?? '∞'}`
const schedule = (row: AcademyClassRecord) => row.weekdays.map(day => weekdays.value[day]).filter(Boolean).join(', ') || '—'
const teachersText = (row: AcademyClassRecord) => row.teachers.map(teacher => teacher.name).join(', ') || '—'
const statusLabel = (row: AcademyClassRecord) => t(row.completed_at ? 'academy.status.completed' : row.is_active ? 'common.statuses.active' : 'common.statuses.inactive')
const statusColor = (row: AcademyClassRecord) => row.completed_at ? 'info' : row.is_active ? 'success' : 'secondary'
const printRows = computed(() => filteredClasses.value.map(row => [
  row.name,
  row.program?.name ?? '—',
  seats(row),
  teachersText(row),
  statusLabel(row),
]))

async function printTable() {
  await printDocument({ bodyClass: 'printing-table-academy-classes', pageStyle: 'size: A4 landscape; margin: 12mm;' })
}

watch(filterMenuOpen, open => {
  if (open)
    syncFilterDraft()
})

onMounted(load)
</script>

<template>
  <div class="academy-page">
    <ErpPageHeader :title="t('academy.classes.title')" section="academy" />

    <VCard>
      <VCardText>
        <VRow align="center" class="academy-classes-toolbar">
          <VCol cols="12" md="6" lg="5">
            <ErpSearchField v-model="search" :placeholder="t('academy.classes.title')" />
          </VCol>
          <VCol cols="12" md="6" lg="7" class="academy-classes-toolbar__actions d-flex flex-wrap justify-end ga-2">
            <DataTableFilterMenu v-model="filterMenuOpen" :active="hasActiveFilters" :show-reset="false">
              <ErpField v-model="filterDraft.programId" kind="select" :items="programs" item-title="name" item-value="id" :label="t('academy.fields.program')" clearable />
              <ErpField v-model="filterDraft.teacherId" kind="select" :items="teachers" item-title="name" item-value="id" :label="t('academy.fields.teacher')" clearable />
              <ErpField v-model="filterDraft.status" kind="select" :items="statusOptions" :label="t('academy.fields.status')" clearable />
              <template #actions>
                <VBtn class="academy-classes-filter-reset" variant="flat" @click="resetFilters">{{ t('common.messages.reset') }}</VBtn>
                <VBtn color="primary" variant="flat" class="academy-classes-filter-apply" @click="applyFilters">{{ t('educational.apply') }}</VBtn>
              </template>
            </DataTableFilterMenu>
            <VBtn variant="tonal" class="academy-classes-toolbar__icon" :disabled="loading" :aria-label="t('common.actions.print')" @click="printTable"><VIcon icon="tabler-printer" /><VTooltip activator="parent">{{ t('common.actions.print') }}</VTooltip></VBtn>
            <VBtn v-if="canManage" color="primary" :disabled="loading" @click="openCreate"><VIcon icon="tabler-plus" class="me-1" />{{ t('academy.classes.create') }}</VBtn>
          </VCol>
        </VRow>
      </VCardText>

      <VAlert v-if="failed" type="error" variant="tonal" class="ma-4 mt-0">{{ t('academy.classes.loadFailed') }}</VAlert>
      <ResponsiveDataTable
        :headers="headers"
        :items="filteredClasses"
        :loading="loading"
        :skeleton-rows="5"
        item-value="id"
        class="academy-classes-table"
        mobile-cards
        mobile-expand-all
        mobile-actions-as-menu
        mobile-title-key="name"
        mobile-action-key="actions"
        :mobile-fields="['program', 'seats', 'teachers', 'status']"
        :sticky-key-column="false"
        :sticky-action-column="false"
        :show-scroll-affordance="false"
      >
        <template #item.name="{ item }"><div class="academy-classes-table__name">{{ item.name }}</div><div class="text-caption text-medium-emphasis">{{ formatActiveMoney(item.fee) }}</div></template>
        <template #item.program="{ item }">{{ item.program?.name ?? '—' }}</template>
        <template #item.seats="{ item }">{{ seats(item) }}</template>
        <template #item.teachers="{ item }"><div class="academy-classes-table__teachers d-flex flex-wrap ga-2"><span v-for="teacher in item.teachers" :key="teacher.id" class="d-inline-flex align-center ga-1"><EmployeeAvatar :name="teacher.name" :src="teacher.avatar" size="24" />{{ teacher.name }}</span><span v-if="!item.teachers.length">—</span></div></template>
        <template #item.status="{ item }"><VChip :color="statusColor(item)" size="small" variant="tonal">{{ statusLabel(item) }}</VChip></template>
        <template #item.actions="{ item }">
          <div class="d-flex justify-end" @click.stop>
            <VMenu location="bottom end">
              <template #activator="{ props: menuProps }"><VBtn v-bind="menuProps" icon="tabler-dots-vertical" variant="tonal" size="small" class="academy-classes-table__actions-trigger" :aria-label="t('common.messages.actions')" /></template>
              <VList density="compact" min-width="220">
                <VListItem prepend-icon="tabler-list-details" :title="t('common.actions.viewFullDetails')" @click="viewDetails(item)" />
                <VListItem v-if="canComplete(item) && !item.completed_at" prepend-icon="tabler-circle-check" base-color="success" :title="t('academy.classes.complete')" @click="requestComplete(item)" />
                <template v-if="canManage">
                  <VListItem prepend-icon="tabler-pencil" :title="t('common.actions.edit')" @click="openEdit(item)" />
                  <VListItem prepend-icon="tabler-trash" class="academy-classes-table__delete-action" :title="t('common.actions.delete')" @click="requestDelete(item)" />
                </template>
              </VList>
            </VMenu>
          </div>
        </template>
        <template #no-data>
          <div class="academy-classes-empty-state">
            <TableEmptyState icon="tabler-school" :min-height="0" :title="t('academy.classes.emptyTitle')" :description="t('academy.classes.emptyDescription')" />
            <VBtn v-if="canManage" color="primary" variant="tonal" class="academy-classes-empty-state__action" @click="openCreate"><VIcon icon="tabler-plus" class="me-1" />{{ t('academy.classes.create') }}</VBtn>
          </div>
        </template>
      </ResponsiveDataTable>
    </VCard>

    <PrintableListReport :title="t('academy.classes.title')" :columns="headers.slice(0, -1).map(header => header.title)" :rows="printRows" />
    <AcademyClassDialog v-model="dialogOpen" :record="editing" :programs="programs" :teachers="teachers" @saved="load" />

    <VDialog v-model="detailsOpen" max-width="980" content-class="academy-class-details-dialog" scrollable>
      <VCard class="academy-class-details-card">
        <VCardTitle class="academy-class-details__header px-5 py-4"><div><div class="academy-class-details__eyebrow">{{ t('common.actions.viewFullDetails') }}</div><div class="text-h5 font-weight-bold">{{ selectedClass?.name }}</div></div><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="academy-class-details__close" :aria-label="t('common.actions.close')" @click="detailsOpen = false" /></VCardTitle>
        <VCardText class="academy-class-details__body pa-5 pa-sm-6">
          <div v-if="detailsLoading" class="academy-class-details__loading" aria-busy="true">
            <div class="academy-class-details__grid">
              <VSkeletonLoader v-for="index in 6" :key="index" type="list-item-two-line" class="academy-class-details__skeleton-tile" />
            </div>
            <VDivider class="my-6" />
            <VSkeletonLoader type="heading, table-tbody" />
          </div>
          <template v-else-if="selectedClass">
            <div class="academy-class-details__grid">
              <div class="academy-class-details__tile"><VIcon icon="tabler-calendar-week" class="academy-class-details__icon" /><div><span>{{ t('academy.fields.schedule') }}</span><strong>{{ schedule(selectedClass) }}</strong><small>{{ selectedClass.starts_at ?? '—' }} — {{ selectedClass.ends_at ?? '—' }}</small></div></div>
              <div class="academy-class-details__tile"><VIcon icon="tabler-calendar-event" class="academy-class-details__icon" /><div><span>{{ t('academy.fields.term') }}</span><strong>{{ formatActiveDate(selectedClass.starts_on) }} — {{ formatActiveDate(selectedClass.ends_on) }}</strong></div></div>
              <div class="academy-class-details__tile"><VIcon icon="tabler-users-group" class="academy-class-details__icon" /><div><span>{{ t('academy.fields.seats') }}</span><strong>{{ seats(selectedClass) }}</strong></div></div>
              <div class="academy-class-details__tile"><VIcon icon="tabler-cash" class="academy-class-details__icon" /><div><span>{{ t('academy.fields.fee') }}</span><strong>{{ formatActiveMoney(selectedClass.fee) }}</strong></div></div>
              <div class="academy-class-details__tile academy-class-details__tile--wide"><VIcon icon="tabler-user-star" class="academy-class-details__icon" /><div><span>{{ t('academy.fields.teachers') }}</span><div class="d-flex flex-wrap ga-2 mt-1"><span v-for="teacher in selectedClass.teachers" :key="teacher.id" class="d-inline-flex align-center ga-1"><EmployeeAvatar :name="teacher.name" :src="teacher.avatar" size="24" /><strong>{{ teacher.name }}</strong></span><strong v-if="!selectedClass.teachers.length">—</strong></div></div></div>
              <div class="academy-class-details__tile academy-class-details__tile--wide"><VIcon icon="tabler-notes" class="academy-class-details__icon" /><div><span>{{ t('academy.fields.description') }}</span><strong>{{ selectedClass.description || '—' }}</strong></div></div>
            </div>
            <VDivider class="my-6" />
            <div class="text-subtitle-1 font-weight-bold mb-3">{{ t('academy.classes.rosterTitle') }}</div>
            <TableEmptyState v-if="roster.length === 0" icon="tabler-user-off" :min-height="160" :title="t('academy.classes.emptyRoster')" />
            <div v-else class="academy-class-details__roster">
              <article v-for="row in roster" :key="row.id" class="academy-class-details__roster-item">
                <VAvatar color="primary" variant="tonal" size="40"><span class="text-body-2 font-weight-bold">{{ (row.student?.name ?? row.applicant?.name ?? '—').slice(0, 1) }}</span></VAvatar>
                <div class="academy-class-details__roster-person"><strong>{{ row.student?.name ?? row.applicant?.name ?? '—' }}</strong><span>{{ row.student?.student_number ?? row.student_number ?? '—' }} · {{ row.student?.contact ?? row.applicant?.contact ?? '—' }}</span></div>
                <div class="academy-class-details__roster-meta"><span>{{ t('academy.fields.joiningDate') }}</span><strong>{{ formatActiveDate(row.started_on) }}</strong></div>
                <VChip size="small" :color="row.status === 'active' ? 'success' : 'secondary'" variant="tonal">{{ t(`academy.status.${row.status}`) }}</VChip>
                <div class="academy-class-details__roster-balance"><span>{{ t('academy.fields.balance') }}</span><strong>{{ row.line ? formatActiveMoney(row.line.balance, row.line.currency) : '—' }}</strong></div>
              </article>
            </div>
          </template>
        </VCardText>
      </VCard>
    </VDialog>

    <ConfirmDialog v-model:is-dialog-visible="deleteDialogOpen" tone="error" icon="tabler-trash" :confirmation-question="t('common.messages.confirmDelete')" :confirmation-message="t('academy.classes.removeConfirm', { name: deleteTarget?.name ?? '' })" :confirm-text="t('common.actions.delete')" :cancel-text="t('common.actions.cancel')" :confirm-title="t('common.actions.delete')" confirm-msg="" :cancel-title="t('common.actions.cancel')" cancel-msg="" @confirm="confirmDelete" />
    <ConfirmDialog v-model:is-dialog-visible="completeDialogOpen" tone="success" icon="tabler-circle-check" :confirmation-question="t('academy.classes.complete')" :confirmation-message="t('academy.classes.completeConfirm', { name: completeTarget?.name ?? '' })" :confirm-text="t('academy.classes.complete')" :cancel-text="t('common.actions.cancel')" :confirm-title="t('academy.classes.completed')" confirm-msg="" :cancel-title="t('common.actions.cancel')" cancel-msg="" @confirm="confirmComplete" />
  </div>
</template>

<style scoped lang="scss">
.academy-classes-toolbar__actions { align-items: center; }
.academy-classes-toolbar__icon { flex: none; min-inline-size: 2.25rem !important; inline-size: 2.25rem !important; padding-inline: 0 !important; border-radius: 999px !important; font-size: .75rem !important; }
.academy-classes-table :deep(.v-table__wrapper > table) { min-inline-size: 780px; }
.academy-classes-table :deep(.v-table__wrapper > table > thead > tr > th) { background: color-mix(in srgb, rgb(var(--v-theme-primary)) 8%, rgb(var(--v-theme-surface)) 92%) !important; color: rgb(var(--v-theme-on-surface)) !important; font-size: .75rem; font-weight: 800; letter-spacing: .035em; text-transform: uppercase; }
.academy-classes-table :deep(.v-table__wrapper > table > thead > tr > th), .academy-classes-table :deep(.v-table__wrapper > table > tbody > tr > td) { white-space: nowrap; }
.academy-classes-table :deep(.v-table__wrapper > table > thead > tr > th:nth-child(1)), .academy-classes-table :deep(.v-table__wrapper > table > tbody > tr > td:nth-child(1)) { min-inline-size: 12rem; }
.academy-classes-table :deep(.v-table__wrapper > table > thead > tr > th:nth-child(4)), .academy-classes-table :deep(.v-table__wrapper > table > tbody > tr > td:nth-child(4)) { min-inline-size: 14rem; }
.academy-classes-table__name { font-weight: 700; }
.academy-classes-table__teachers { display: block; max-inline-size: 17rem; overflow: hidden; text-overflow: ellipsis; }
.academy-classes-table__actions-trigger { border-radius: 999px !important; background: rgba(var(--v-theme-on-surface), .04) !important; color: rgba(var(--v-theme-on-surface), .72) !important; }
.academy-classes-table__actions-trigger:hover { background: rgba(var(--v-theme-on-surface), .08) !important; }
.academy-classes-table__delete-action { color: rgb(var(--v-theme-error)) !important; }
.academy-classes-table__delete-action:hover { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }
.academy-classes-filter-reset { background: rgba(var(--v-theme-primary), .12) !important; color: rgb(var(--v-theme-primary)) !important; }
.academy-classes-filter-reset:hover, .academy-classes-filter-apply, .academy-classes-filter-apply:hover { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
.academy-classes-empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; min-block-size: 17rem; padding: 1.25rem 1.25rem 2rem; }
.academy-classes-empty-state :deep(.table-empty-state) { min-block-size: 0 !important; padding-block: 1rem; }
.academy-classes-empty-state__action { margin-block-start: -.25rem; background: rgba(var(--v-theme-primary), .12) !important; color: rgb(var(--v-theme-primary)) !important; }
.academy-class-details-card { overflow: hidden; }
.academy-class-details__header { align-items: center; display: flex; border-block-end: 1px solid rgba(var(--v-theme-on-surface), .07); background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%); }
.academy-class-details__body { background: rgb(var(--v-theme-surface)); }
.academy-class-details__eyebrow { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .8125rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
.academy-class-details__close { border: 1px solid rgba(var(--v-theme-error), .38) !important; border-radius: 999px !important; color: rgb(var(--v-theme-error)) !important; }
.academy-class-details__close:hover { background: rgba(var(--v-theme-error), .18) !important; }
.academy-class-details__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .875rem; }
.academy-class-details__tile { align-items: flex-start; display: flex; gap: .875rem; min-block-size: 5.75rem; padding: 1.125rem; border: 1px solid rgba(var(--v-border-color), .42); border-radius: 12px; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 96%, rgb(var(--v-theme-on-surface)) 4%); }
.academy-class-details__tile--wide { grid-column: 1 / -1; }
.academy-class-details__tile > div { display: grid; gap: .25rem; min-inline-size: 0; }
.academy-class-details__tile span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .8125rem; font-weight: 700; }
.academy-class-details__tile strong { overflow-wrap: anywhere; color: rgb(var(--v-theme-on-surface)); font-size: 1rem; line-height: 1.45; }
.academy-class-details__tile small { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .8125rem; }
.academy-class-details__icon { flex: none; margin-block-start: .15rem; color: rgb(var(--v-theme-primary)); }
.academy-class-details__skeleton-tile { min-block-size: 5.75rem; border: 1px solid rgba(var(--v-border-color), .36); border-radius: 12px; }
.academy-class-details__roster { display: grid; gap: .75rem; }
.academy-class-details__roster-item { display: grid; grid-template-columns: auto minmax(10rem, 1.6fr) minmax(7rem, .85fr) auto minmax(7rem, .8fr); align-items: center; gap: 1rem; padding: .875rem 1rem; border: 1px solid rgba(var(--v-border-color), .42); border-radius: 12px; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 96%, rgb(var(--v-theme-on-surface)) 4%); }
.academy-class-details__roster-person, .academy-class-details__roster-meta, .academy-class-details__roster-balance { display: grid; gap: .2rem; min-inline-size: 0; }
.academy-class-details__roster-person strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.academy-class-details__roster-person span, .academy-class-details__roster-meta span, .academy-class-details__roster-balance span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .75rem; }
.academy-class-details__roster-balance { text-align: end; }
:global(.v-overlay:has(.academy-class-details-dialog) .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .24) !important; backdrop-filter: blur(4px); opacity: 1 !important; }
@media (max-width: 959px) { .academy-classes-table.responsive-data-table--mobile-cards :deep(.v-table__wrapper > table > tbody > tr > td) { white-space: normal; } .academy-classes-table.responsive-data-table--mobile-cards :deep(.responsive-data-table__field-value > .d-flex) { justify-content: flex-end; } }
@media (max-width: 799px) { .academy-class-details__roster-item { grid-template-columns: auto minmax(0, 1fr) auto; gap: .75rem; } .academy-class-details__roster-meta { grid-column: 2; } .academy-class-details__roster-balance { grid-column: 3; grid-row: 2; } }
@media (max-width: 599px) { .academy-classes-toolbar__actions { justify-content: space-between !important; } .academy-class-details__grid { grid-template-columns: 1fr; } .academy-class-details__tile--wide { grid-column: auto; } .academy-class-details__header, .academy-class-details__body { padding-inline: 1rem !important; } .academy-class-details__roster-item { grid-template-columns: auto minmax(0, 1fr); } .academy-class-details__roster-item > .v-chip { justify-self: end; } .academy-class-details__roster-meta { grid-column: 2; } .academy-class-details__roster-balance { grid-column: 2; grid-row: 3; text-align: start; } }
</style>
