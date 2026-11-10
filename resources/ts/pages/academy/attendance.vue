<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import ConfirmDialog from '@/components/dialogs/ConfirmDialog.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpMonthPicker from '@/components/erp/ErpMonthPicker.vue'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import DataTableFilterMenu from '@/components/responsive-data-table/DataTableFilterMenu.vue'
import ResponsiveDataTable from '@/components/responsive-data-table/ResponsiveDataTable.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import { AcademyAttendanceApi, type AcademyAttendanceMonth, type AcademyAttendanceStatus, type AcademyAttendanceWriteRow } from '@/api/academyAttendance'
import { AcademyClassesApi, type AcademyClassRecord } from '@/api/academyClasses'
import { canAccess } from '@/utils/authorization'
import { formatActiveDate } from '@/utils/localization'
import { printDocument } from '@/utils/printDocument'
import AcademyAttendanceTemplate from '@/pages/templates/academy_attendance_template.vue'

type LedgerRow = { key: string; enrollment_id: number; student_number: string | null; name: string | null; contact: string | null; date: string; status: AcademyAttendanceStatus; check_in_time: string | null; check_out_time: string | null }
const { t } = useI18n(); const toast = useToast()
const now = new Date(); const currentMonth = now.toISOString().slice(0, 7)
const monthStart = `${currentMonth}-01`; const monthEnd = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth() + 1, 0)).toISOString().slice(0, 10)
const classes = ref<AcademyClassRecord[]>([]); const activeClassId = ref<number | null>(null); const data = ref<AcademyAttendanceMonth | null>(null)
const loading = ref(true); const failed = ref(false); const search = ref(''); const filterOpen = ref(false)
const dateFrom = ref(monthStart); const dateTo = ref(monthEnd); const draftFrom = ref(monthStart); const draftTo = ref(monthEnd)
const markOpen = ref(false); const markClassId = ref<number | null>(null); const markMonth = ref(currentMonth); const markData = ref<AcademyAttendanceMonth | null>(null); const markLoading = ref(false); const saving = ref(false)
const drafts = ref<AcademyAttendanceWriteRow[]>([]); const completeOpen = ref(false)
const canManage = computed(() => canAccess('manage_academy_attendance'))
const activeClass = computed(() => classes.value.find(row => row.id === activeClassId.value) ?? null)
const headers = computed(() => [{ title: t('academy.fields.student'), key: 'student' }, { title: t('academy.fields.date'), key: 'date' }, { title: t('academy.fields.status'), key: 'status' }, { title: t('academy.attendance.checkIn'), key: 'check_in_time' }, { title: t('academy.attendance.checkOut'), key: 'check_out_time' }])
const markHeaders = computed(() => [{ title: t('academy.fields.student'), key: 'student' }, { title: t('academy.fields.date'), key: 'date' }, { title: t('academy.fields.status'), key: 'status' }, { title: t('academy.attendance.checkIn'), key: 'check_in_time' }, { title: t('academy.attendance.checkOut'), key: 'check_out_time' }])
const statusOptions = computed(() => (['present', 'absent', 'leave'] as AcademyAttendanceStatus[]).map(value => ({ title: t(`academy.attendance.status.${value}`), value })))
const timeOptions = Array.from({ length: 96 }, (_, index) => { const hour = Math.floor(index / 4); const minute = (index % 4) * 15; const value = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`; const title = new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(new Date(2000, 0, 1, hour, minute)); return { title, value } })
const ledgerRows = computed<LedgerRow[]>(() => {
  const needle = search.value.trim().toLocaleLowerCase(); const result: LedgerRow[] = []
  for (const student of data.value?.rows ?? []) for (const [date, cell] of Object.entries(student.attendance)) if (date >= dateFrom.value && date <= dateTo.value && (!needle || [student.name, student.student_number, student.contact].some(v => String(v ?? '').toLocaleLowerCase().includes(needle)))) result.push({ key: `${student.enrollment_id}|${date}`, enrollment_id: student.enrollment_id, student_number: student.student_number, name: student.name, contact: student.contact, date, ...cell })
  return result.sort((a, b) => b.date.localeCompare(a.date) || String(a.name).localeCompare(String(b.name)))
})

async function loadClasses() { classes.value = await AcademyClassesApi.list(); activeClassId.value ||= classes.value[0]?.id ?? null }
async function load() { if (!activeClassId.value) { loading.value = false; return } loading.value = true; failed.value = false; try { data.value = await AcademyAttendanceApi.getMonth(activeClassId.value, currentMonth) } catch { failed.value = true } finally { loading.value = false } }
function applyFilter() { dateFrom.value = draftFrom.value || monthStart; dateTo.value = draftTo.value || monthEnd; filterOpen.value = false }
function resetFilter() { draftFrom.value = monthStart; draftTo.value = monthEnd; applyFilter() }
function inScope(row: AcademyAttendanceMonth['rows'][number], date: string, klass: AcademyClassRecord) { const weekday = new Date(`${date}T00:00:00Z`).getUTCDay() || 7; return (!row.started_on || date >= row.started_on) && (!row.ends_on || date <= row.ends_on) && (!klass.starts_on || date >= klass.starts_on) && (!klass.ends_on || date <= klass.ends_on) && klass.weekdays.includes(weekday) }
async function loadMark() { if (!markClassId.value || !markMonth.value) { markData.value = null; drafts.value = []; return } markLoading.value = true; try { const response = await AcademyAttendanceApi.getMonth(markClassId.value, markMonth.value); markData.value = response; drafts.value = response.rows.flatMap(student => response.dates.filter(date => inScope(student, date, response.class)).map(date => { const cell = student.attendance[date]; return { enrollment_id: student.enrollment_id, date, status: cell?.status ?? 'present', check_in_time: cell?.check_in_time ?? response.class.starts_at?.slice(0, 5) ?? null, check_out_time: cell?.check_out_time ?? response.class.ends_at?.slice(0, 5) ?? null } })) } finally { markLoading.value = false } }
function studentFor(enrollmentId: number) { return markData.value?.rows.find(row => row.enrollment_id === enrollmentId) }
function setStatus(row: AcademyAttendanceWriteRow, status: AcademyAttendanceStatus) { row.status = status; if (status !== 'present') { row.check_in_time = null; row.check_out_time = null } else { row.check_in_time ||= markData.value?.class.starts_at?.slice(0, 5) ?? null; row.check_out_time ||= markData.value?.class.ends_at?.slice(0, 5) ?? null } }
async function saveAttendance() { if (!drafts.value.length) return; saving.value = true; try { await AcademyAttendanceApi.save(drafts.value); toast.success(t('academy.attendance.saved')); markOpen.value = false; await load() } finally { saving.value = false } }
async function completeClass(confirmed: boolean) { if (!confirmed || !activeClassId.value) return; try { await AcademyClassesApi.complete(activeClassId.value); toast.success(t('academy.attendance.classCompleted')); await loadClasses() } catch { /* API notifier owns error. */ } }
async function printSheet() { await printDocument({ bodyClass: 'printing-academy-attendance', pageStyle: 'size: A4 landscape; margin: 8mm;', element: document.querySelector<HTMLElement>('.academy-attendance-print-host') }) }
watch(activeClassId, load); watch([markClassId, markMonth], loadMark)
onMounted(async () => { try { await loadClasses(); await load() } catch { failed.value = true; loading.value = false } })
</script>

<template>
  <div class="academy-page">
    <div class="academy-attendance-print-host" aria-hidden="true"><AcademyAttendanceTemplate v-if="data" :title="activeClass?.name ?? ''" :month="data.month" :dates="data.dates.filter(date => date >= dateFrom && date <= dateTo)" :rows="data.rows" /></div>
    <ErpPageHeader :title="t('academy.attendance.title')" section="academy" />
    <VCard>
      <VCardText><VRow align="center"><VCol cols="12" md="5"><ErpSearchField v-model="search" :placeholder="t('academy.attendance.title')" /></VCol><VCol cols="12" md="7" class="attendance-toolbar d-flex justify-end ga-2">
        <DataTableFilterMenu v-model="filterOpen" :active="dateFrom !== monthStart || dateTo !== monthEnd" :show-reset="false"><ErpDateRange v-model:from="draftFrom" v-model:to="draftTo" :show-apply="false" :inline="false" /><template #actions><VBtn variant="tonal" color="primary" @click="resetFilter">{{ t('common.messages.reset') }}</VBtn><VBtn color="primary" @click="applyFilter">{{ t('educational.apply') }}</VBtn></template></DataTableFilterMenu>
        <VBtn variant="tonal" :disabled="!ledgerRows.length" @click="printSheet"><VIcon icon="tabler-printer" /><VTooltip activator="parent">{{ t('common.actions.print') }}</VTooltip></VBtn>
        <VBtn v-if="canManage" variant="tonal" color="success" :disabled="!activeClassId" @click="completeOpen = true">{{ t('academy.attendance.completeClass') }}</VBtn>
        <VBtn v-if="canManage" color="primary" @click="markClassId = null; markMonth = currentMonth; markOpen = true"><VIcon icon="tabler-calendar-check" class="me-1" />{{ t('academy.attendance.markAttendance') }}</VBtn>
      </VCol></VRow></VCardText>
      <VTabs v-if="classes.length" v-model="activeClassId" class="px-4" show-arrows><VTab v-for="klass in classes" :key="klass.id" :value="klass.id">{{ klass.name }}</VTab></VTabs>
      <VAlert v-if="failed" type="error" variant="tonal" class="ma-4">{{ t('academy.attendance.loadFailed') }}</VAlert>
      <ResponsiveDataTable v-else :headers="headers" :items="ledgerRows" :loading="loading" :skeleton-rows="6" item-value="key" class="attendance-table" mobile-cards mobile-expand-all mobile-title-key="student" :mobile-fields="['date','status','check_in_time','check_out_time']" :sticky-key-column="false" :sticky-action-column="false" :show-scroll-affordance="false">
        <template #item.student="{ item }"><div><strong>{{ item.name || '—' }}</strong><div class="text-caption text-medium-emphasis">{{ item.student_number || item.contact || '—' }}</div></div></template><template #item.date="{ item }">{{ formatActiveDate(item.date) }}</template><template #item.status="{ item }"><VChip size="small" variant="tonal" :color="item.status === 'present' ? 'success' : item.status === 'absent' ? 'error' : 'info'">{{ t(`academy.attendance.status.${item.status}`) }}</VChip></template><template #item.check_in_time="{ item }">{{ item.check_in_time || '—' }}</template><template #item.check_out_time="{ item }">{{ item.check_out_time || '—' }}</template>
        <template #no-data><TableEmptyState icon="tabler-calendar-check" :title="t('academy.attendance.empty')" :description="t('academy.attendance.emptyDescription')" /></template>
      </ResponsiveDataTable>
    </VCard>

    <VDialog v-model="markOpen" max-width="1180" content-class="attendance-mark-dialog" persistent scrollable><VCard class="attendance-mark-card"><VForm class="app-form" @submit.prevent="saveAttendance">
      <VCardTitle class="attendance-dialog-header px-5 py-4"><div><div class="text-caption text-medium-emphasis">{{ t('academy.attendance.title') }}</div><div class="text-h5 font-weight-bold">{{ t('academy.attendance.markAttendance') }}</div></div><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="attendance-dialog-close" @click="markOpen = false" /></VCardTitle>
      <VCardText class="pa-5 pa-sm-6"><VRow><VCol cols="12" md="6"><ErpMonthPicker v-model="markMonth" :label="t('academy.fields.month')" :placeholder="currentMonth" /></VCol><VCol cols="12" md="6"><ErpField v-model="markClassId" kind="select" :items="classes" item-title="name" item-value="id" :label="t('academy.fields.class')" :placeholder="t('academy.fields.class')" clearable /></VCol></VRow>
        <TableEmptyState v-if="!markClassId || !markMonth" icon="tabler-calendar-search" :title="t('academy.attendance.selectTitle')" :description="t('academy.attendance.selectDescription')" />
        <ResponsiveDataTable v-else :headers="markHeaders" :items="drafts" :loading="markLoading" :skeleton-rows="6" :items-per-page="-1" class="attendance-mark-table mt-4" mobile-cards mobile-expand-all :sticky-key-column="false" :sticky-action-column="false" :show-scroll-affordance="false">
          <template #item.student="{ item }"><strong>{{ studentFor(item.enrollment_id)?.name || '—' }}</strong><div class="text-caption">{{ studentFor(item.enrollment_id)?.student_number || '—' }}</div></template><template #item.date="{ item }">{{ formatActiveDate(item.date) }}</template>
          <template #item.status="{ item }"><VBtnToggle :model-value="item.status" mandatory density="compact" divided @update:model-value="value => setStatus(item, value)"><VBtn v-for="option in statusOptions" :key="option.value" :value="option.value" size="small">{{ option.title }}</VBtn></VBtnToggle></template>
          <template #item.check_in_time="{ item }"><ErpField v-model="item.check_in_time" kind="select" :items="timeOptions" :label="t('academy.attendance.checkIn')" :disabled="item.status !== 'present'" /></template><template #item.check_out_time="{ item }"><ErpField v-model="item.check_out_time" kind="select" :items="timeOptions" :label="t('academy.attendance.checkOut')" :disabled="item.status !== 'present'" /></template>
          <template #no-data><TableEmptyState icon="tabler-users-minus" :title="t('academy.attendance.empty')" /></template>
        </ResponsiveDataTable>
      </VCardText><VCardActions class="attendance-dialog-footer px-5 py-4"><VSpacer /><VBtn class="attendance-cancel" @click="markOpen = false">{{ t('common.actions.cancel') }}</VBtn><VBtn color="primary" type="submit" :loading="saving" :disabled="!drafts.length">{{ t('common.actions.save') }}</VBtn></VCardActions>
    </VForm></VCard></VDialog>
    <ConfirmDialog v-model:is-dialog-visible="completeOpen" tone="success" icon="tabler-circle-check" :confirmation-question="t('academy.attendance.completeClass')" :confirmation-message="activeClass?.name ?? ''" :confirm-text="t('academy.attendance.completeClass')" :cancel-text="t('common.actions.cancel')" :confirm-title="t('academy.attendance.classCompleted')" confirm-msg="" :cancel-title="t('common.actions.cancel')" cancel-msg="" @confirm="completeClass" />
  </div>
</template>

<style scoped lang="scss">
.academy-attendance-print-host { display: none; }.attendance-table :deep(thead th), .attendance-mark-table :deep(thead th) { background: color-mix(in srgb, rgb(var(--v-theme-primary)) 8%, rgb(var(--v-theme-surface)) 92%); font-weight: 800; }.attendance-dialog-header,.attendance-dialog-footer{display:flex;align-items:center;background:color-mix(in srgb,rgb(var(--v-theme-surface)) 94%,rgb(var(--v-theme-on-surface)) 6%)}.attendance-dialog-header{border-block-end:1px solid rgba(var(--v-border-color),.45)}.attendance-dialog-footer{border-block-start:1px solid rgba(var(--v-border-color),.45)}.attendance-dialog-close{color:rgb(var(--v-theme-error))!important;border:1px solid rgba(var(--v-theme-error),.35)}.attendance-cancel{background:rgba(var(--v-theme-secondary),.12)!important;color:rgb(var(--v-theme-secondary))!important}.attendance-cancel:hover{background:rgb(var(--v-theme-secondary))!important;color:white!important}:global(.v-overlay:has(.attendance-mark-dialog) .v-overlay__scrim){backdrop-filter:blur(4px);opacity:.7!important}.attendance-mark-table :deep(.erp-field-label){display:none}.attendance-mark-table :deep(.erp-field){min-inline-size:9rem}@media(max-width:599px){.attendance-toolbar{justify-content:flex-start!important;flex-wrap:wrap}.attendance-toolbar .v-btn{flex:1}}
@media print { :global(body.printing-academy-attendance .academy-attendance-print-host){display:block!important}:global(body.printing-academy-attendance .academy-page>:not(.academy-attendance-print-host)){display:none!important} }
</style>
