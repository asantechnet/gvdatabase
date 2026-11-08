<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useAbility } from '@casl/vue'
import OffDayApi, { type OffDay, type OffDayParams, type OffDayPayload, type OffDayStatus } from '@/api/offDays'
import ResponsiveDataTableServer from '@/components/responsive-data-table/ResponsiveDataTableServer.vue'
import { apiErrorMessage } from '@/utils/apiErrors'
import { formatLocalizedDate } from '@/utils/localization'
import { printDocument } from '@/utils/printDocument'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'

const { t } = useI18n()
const toast = useToast()
const ability = useAbility()

const year = ref(new Date().getFullYear())
const statusFilter = ref<OffDayStatus | null>(null)
const rows = ref<OffDay[]>([])
// The ledger asks the server for one page at a time.
const page = ref(1)
const perPage = ref(25)
const total = ref(0)
// Filled from every matching row right before printing.
const reportRows = ref<string[][]>([])
const loading = ref(true)
const search = ref('')
const filterMenuOpen = ref(false)
const draftYear = ref(year.value)
const draftStatus = ref<OffDayStatus | null>(null)
const saving = ref(false)
const dialog = ref(false)
const deleteDialog = ref(false)
const selected = ref<OffDay | null>(null)
const form = ref<OffDayPayload>({ start_date: todayIso(), end_date: todayIso(), reason: '' })
const error = ref('')

const canManage = computed(() => ability.can('read', 'manage_off_days'))
const canApprove = computed(() => ability.can('read', 'approve_off_days'))

const statusOptions = computed(() => [
  { title: t('common.statuses.all'), value: null },
  { title: t('common.statuses.pending'), value: 'pending' },
  { title: t('common.statuses.approved'), value: 'approved' },
  { title: t('common.statuses.rejected'), value: 'rejected' },
])

const statusColor = (status: OffDayStatus) => ({ pending: 'warning', approved: 'success', rejected: 'error' }[status])
const visibleRows = computed(() => {
  const query = search.value.trim().toLocaleLowerCase()
  if (!query)
    return rows.value

  return rows.value.filter(row => [row.start_date, row.end_date, row.reason, row.status, row.approvedBy?.name]
    .filter(Boolean).some(value => String(value).toLocaleLowerCase().includes(query)))
})
const hasActiveFilters = computed(() => Boolean(statusFilter.value || year.value !== new Date().getFullYear()))

function todayIso() {
  return new Date().toISOString().slice(0, 10)
}

const filterParams = (): OffDayParams => ({ year: year.value, status: statusFilter.value ?? undefined })

async function load() {
  loading.value = true
  try {
    const result = await OffDayApi.listPage({ ...filterParams(), page: page.value, perPage: perPage.value })

    rows.value = result.rows
    total.value = result.total
  }
  catch (e) {
  }
  finally {
    loading.value = false
  }
}

function openCreate() {
  selected.value = null
  form.value = { start_date: todayIso(), end_date: todayIso(), reason: '' }
  error.value = ''
  dialog.value = true
}

function openEdit(row: OffDay) {
  selected.value = row
  form.value = { start_date: row.start_date, end_date: row.end_date, reason: row.reason }
  error.value = ''
  dialog.value = true
}

async function save() {
  if (!form.value.start_date || !form.value.end_date || !form.value.reason.trim()) {
    error.value = t('pages.offDays.startDateRequired')
    return
  }

  if (form.value.end_date < form.value.start_date) {
    error.value = t('pages.offDays.endDateAfterStart')
    return
  }

  saving.value = true
  error.value = ''
  try {
    if (selected.value)
      await OffDayApi.update(selected.value.id, { ...form.value, reason: form.value.reason.trim() })
    else
      await OffDayApi.create({ ...form.value, reason: form.value.reason.trim() })

    toast.success(t('pages.offDays.saved'))
    dialog.value = false
    await load()
  }
  catch (e) {
    error.value = apiErrorMessage(e, t('pages.offDays.unableToSave'))
  }
  finally {
    saving.value = false
  }
}

async function approve(row: OffDay) {
  try {
    await OffDayApi.approve(row.id)
    toast.success(t('pages.offDays.approvedMessage'))
    await load()
  }
  catch (e) {
  }
}

async function reject(row: OffDay) {
  try {
    await OffDayApi.reject(row.id)
    toast.success(t('pages.offDays.rejectedMessage'))
    await load()
  }
  catch (e) {
  }
}

function openDelete(row: OffDay) {
  selected.value = row
  deleteDialog.value = true
}

async function remove() {
  if (!selected.value)
    return

  try {
    await OffDayApi.remove(selected.value.id)
    toast.success(t('pages.offDays.removed'))
    deleteDialog.value = false
    await load()
  }
  catch (e) {
  }
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

// Print is global: the report is filled from every matching row first.
async function printReport() {
  try {
    reportRows.value = (await OffDayApi.list(filterParams())).map(row => [
      row.start_date,
      row.end_date,
      String(row.days),
      row.reason,
      t(`common.statuses.${row.status}`),
      row.approvedBy?.name ?? '—',
    ])
    await printDocument({ bodyClass: 'printing-table-off-days', pageStyle: 'size: A4 landscape; margin: 12mm;' })
  }
  catch (e) {
  }
}

onMounted(load)
</script>

<template>
  <div class="off-days-page">
    <ErpPageHeader :title="t('pages.offDays.companyTitle')" section="hr" />

    <VCard class="off-days-screen">
      <VCardText class="off-days-toolbar">
        <VRow align="center">
          <VCol cols="12" md="6" lg="5"><ErpSearchField v-model="search" :placeholder="t('pages.offDays.search')" /></VCol>
          <VCol cols="12" md="6" lg="7" class="d-flex align-center justify-md-end ga-2 flex-wrap">
        <DataTableFilterMenu v-model="filterMenuOpen" :active="hasActiveFilters" @reset="resetFilters">
          <ErpField v-model="draftStatus" kind="select" :items="statusOptions" :label="t('common.fields.status')" clearable />
          <ErpField v-model.number="draftYear" type="number" :label="t('pages.offDays.year')" min="2000" max="3000" />
          <template #actions><VBtn variant="tonal" color="primary" @click="resetFilters">{{ t('common.messages.reset') }}</VBtn><VBtn color="primary" variant="flat" @click="applyFilters">{{ t('educational.apply') }}</VBtn></template>
        </DataTableFilterMenu>
        <VBtn icon="tabler-printer" variant="tonal" :aria-label="t('common.actions.print')" @click="printReport"><VIcon icon="tabler-printer" /><VTooltip activator="parent">{{ t('common.actions.print') }}</VTooltip></VBtn>
        <VBtn
          v-if="canManage"
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          {{ t('pages.offDays.add') }}
        </VBtn></VCol>
        </VRow>
      </VCardText>
        <ResponsiveDataTableServer
          :items="visibleRows"
          :items-length="total"
          :page="page"
          :items-per-page="perPage"
          :items-per-page-options="[10, 25, 50, 100]"
          :loading="loading"
          :skeleton-rows="6"
          mobile-cards
          mobile-expand-all
          mobile-title-key="start_date"
          mobile-action-key="actions"
          :mobile-fields="['end_date', 'days', 'status']"
          :sticky-key-column="false"
          :sticky-action-column="false"
          @update:options="updateTableOptions"
          :headers="[
            { title: t('pages.offDays.startDate'), key: 'start_date' },
            { title: t('pages.offDays.endDate'), key: 'end_date' },
            { title: t('pages.offDays.days'), key: 'days' },
            { title: t('pages.offDays.reason'), key: 'reason' },
            { title: t('common.fields.status'), key: 'status' },
            { title: t('pages.offDays.approvedBy'), key: 'approvedBy.name' },
            { title: t('common.messages.actions'), key: 'actions', sortable: false, align: 'end' },
          ]"
        >
          <template #item.start_date="{ item }">
            {{ formatLocalizedDate(item.start_date) }}
          </template>
          <template #item.end_date="{ item }">
            {{ formatLocalizedDate(item.end_date) }}
          </template>
          <template #item.status="{ item }">
            <VChip
              :color="statusColor(item.status)"
              size="small"
              label
            >
              {{ t(`common.statuses.${item.status}`) }}
            </VChip>
          </template>
          <template #item.approvedBy.name="{ item }">
            <span v-if="item.approvedBy">{{ item.approvedBy.name }}</span>
            <span v-else class="text-medium-emphasis">—</span>
          </template>
          <template #item.actions="{ item }">
            <div class="d-flex justify-end ga-1">
              <VBtn
                v-if="item.status === 'pending' && canApprove"
                icon="tabler-check"
                size="small"
                variant="text"
                color="success"
                :aria-label="t('pages.offDays.approve')"
                @click="approve(item)"
              >
                <VIcon icon="tabler-check" />
                <VTooltip activator="parent" location="top">
                  {{ t('pages.offDays.approve') }}
                </VTooltip>
              </VBtn>
              <VBtn
                v-if="item.status === 'pending' && canApprove"
                icon="tabler-x"
                size="small"
                variant="text"
                color="error"
                :aria-label="t('pages.offDays.reject')"
                @click="reject(item)"
              >
                <VIcon icon="tabler-x" />
                <VTooltip activator="parent" location="top">
                  {{ t('pages.offDays.reject') }}
                </VTooltip>
              </VBtn>
              <VBtn
                v-if="canManage && (item.status === 'pending' || canApprove)"
                icon="tabler-edit"
                size="small"
                variant="text"
                :aria-label="t('common.actions.edit')"
                @click="openEdit(item)"
              >
                <VIcon icon="tabler-edit" />
                <VTooltip activator="parent" location="top">
                  {{ t('common.actions.edit') }}
                </VTooltip>
              </VBtn>
              <VBtn
                v-if="canManage && (item.status === 'pending' || canApprove)"
                icon="tabler-trash"
                size="small"
                variant="text"
                color="error"
                :aria-label="t('pages.offDays.remove')"
                @click="openDelete(item)"
              >
                <VIcon icon="tabler-trash" />
                <VTooltip activator="parent" location="top">
                  {{ t('pages.offDays.remove') }}
                </VTooltip>
              </VBtn>
            </div>
          </template>
          <template #no-data><TableEmptyState icon="tabler-calendar-off" :title="t('pages.offDays.noRecordsTitle')" :description="t('pages.offDays.noRecords')" /></template>
        </ResponsiveDataTableServer>
    </VCard>

    <PrintableListReport
      :title="t('pages.offDays.reportTitle')"
      :subtitle="String(year)"
      :columns="[t('pages.offDays.startDate'), t('pages.offDays.endDate'), t('pages.offDays.days'), t('pages.offDays.reason'), t('common.fields.status'), t('pages.offDays.approvedBy')]"
      :rows="reportRows"
    />

    <VDialog v-model="dialog" :persistent="saving" max-width="640" content-class="off-day-form-dialog">
      <VCard class="off-day-form-dialog__card">
        <VCardTitle class="off-day-form-dialog__header d-flex align-center justify-space-between px-5 py-4">
          <span>{{ selected ? t('pages.offDays.edit') : t('pages.offDays.add') }}</span>
          <VBtn icon="tabler-x" variant="tonal" color="error" size="small" :disabled="saving" @click="dialog = false" />
        </VCardTitle>
        <VCardText class="pa-5">
          <VAlert
            v-if="error"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ error }}
          </VAlert>
          <VRow>
            <VCol cols="12" sm="6">
              <AppDateTimePicker
                v-model="form.start_date"
                :label="t('pages.offDays.startDate')"
                :config="{ dateFormat: 'Y-m-d' }"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <AppDateTimePicker
                v-model="form.end_date"
                :label="t('pages.offDays.endDate')"
                :config="{ dateFormat: 'Y-m-d' }"
              />
            </VCol>
            <VCol cols="12">
              <ErpField
                v-model="form.reason"
                :label="t('pages.offDays.reason')"
                kind="textarea"
                rows="3"
                maxlength="1000"
                counter
                auto-grow
              />
            </VCol>
          </VRow>
          <VAlert
            v-if="!canApprove"
            type="info"
            variant="tonal"
            density="comfortable"
            class="mt-2"
          >
            {{ t('pages.offDays.subtitle') }}
          </VAlert>
        </VCardText>
        <VCardActions class="off-day-form-dialog__footer px-5 py-4">
          <VBtn class="off-day-form-dialog__cancel"
            @click="dialog = false"
          >
            {{ t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            variant="flat"
            :disabled="saving"
            @click="save"
          >
            {{ t('common.actions.save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="deleteDialog"
      max-width="460"
    >
      <VCard :title="t('pages.offDays.remove')">
        <VCardText>{{ t('pages.offDays.removeConfirm') }}</VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="deleteDialog = false"
          >
            {{ t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="error"
            @click="remove"
          >
            {{ t('pages.offDays.remove') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.off-days-page { max-inline-size: 100%; }
.off-days-screen { overflow: hidden; border-radius: .85rem !important; box-shadow: 0 3px 14px rgba(var(--v-theme-on-surface), .035) !important; }
.off-days-toolbar { padding: 1.25rem 1.5rem !important; }
.off-days-screen :deep(.v-data-table__thead > tr > th) { background: rgba(var(--v-theme-on-surface), .045) !important; }
.off-days-screen :deep(.v-data-table__tbody > tr:hover > td) { background: rgba(var(--v-theme-on-surface), .035) !important; }
.off-day-form-dialog__card { overflow: hidden; }
.off-day-form-dialog__header,
.off-day-form-dialog__footer { background: color-mix(in srgb, rgb(var(--v-theme-surface)) 93%, rgb(var(--v-theme-on-surface)) 7%); }
.off-day-form-dialog__header { border-block-end: 1px solid rgba(var(--v-border-color), .5); }
.off-day-form-dialog__footer { display: flex; justify-content: flex-start; gap: .75rem; border-block-start: 1px solid rgba(var(--v-border-color), .5); }
.off-day-form-dialog__cancel { border: 1px solid rgba(var(--v-theme-secondary), .5) !important; background: rgba(var(--v-theme-secondary), .13) !important; color: rgb(var(--v-theme-secondary)) !important; }
.off-day-form-dialog__cancel:hover { background: rgb(var(--v-theme-secondary)) !important; color: rgb(var(--v-theme-on-secondary)) !important; }
:global(.v-overlay:has(.off-day-form-dialog) .v-overlay__scrim) { background: rgba(20, 25, 35, .32) !important; backdrop-filter: blur(6px); opacity: 1 !important; }
@media (max-width: 959px) { .off-days-toolbar { padding-inline: 1rem !important; } .off-days-screen :deep(.v-data-table__tbody > tr:hover > td) { background: transparent !important; } }
</style>
