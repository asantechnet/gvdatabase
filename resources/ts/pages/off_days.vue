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
const loading = ref(false)
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

watch([year, statusFilter], () => {
  page.value = 1
  load()
})
onMounted(load)
</script>

<template>
  <div>
    <DataTablePageKpis
      :items="rows"
      :title="t('pages.offDays.companyTitle')"
      :description="t('pages.offDays.subtitle')"
      icon="tabler-calendar-off"
    />

    <VCard>
      <VCardTitle class="d-flex flex-wrap align-center ga-3">
        <VIcon icon="tabler-calendar-off" />
        {{ t('pages.offDays.companyTitle') }}
        <VSpacer />
        <DataTableFilterMenu
          :active="Boolean(statusFilter || year !== new Date().getFullYear())"
          @reset="statusFilter = null; year = new Date().getFullYear()"
        >
          <VSelect
            v-model="statusFilter"
            :items="statusOptions"
            :label="t('common.fields.status')"
            density="compact"
            hide-details
          />
          <VTextField
            v-model.number="year"
            type="number"
            min="2000"
            max="3000"
            density="compact"
            hide-details
          />
        </DataTableFilterMenu>
        <VBtn
          prepend-icon="tabler-printer"
          variant="tonal"
          @click="printReport"
        >
          {{ t('common.actions.print') }}
        </VBtn>
        <VBtn
          v-if="canManage"
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          {{ t('pages.offDays.add') }}
        </VBtn>
      </VCardTitle>

      <VCardText>
        <VAlert
          type="info"
          variant="tonal"
          density="comfortable"
          class="mb-4"
        >
          {{ t('pages.offDays.affectsNote') }}
        </VAlert>

        <ResponsiveDataTableServer
          :items="rows"
          :items-length="total"
          :page="page"
          :items-per-page="perPage"
          :items-per-page-options="[10, 25, 50, 100]"
          :loading="loading"
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
        </ResponsiveDataTableServer>
      </VCardText>
    </VCard>

    <PrintableListReport
      :title="t('pages.offDays.reportTitle')"
      :subtitle="String(year)"
      :columns="[t('pages.offDays.startDate'), t('pages.offDays.endDate'), t('pages.offDays.days'), t('pages.offDays.reason'), t('common.fields.status'), t('pages.offDays.approvedBy')]"
      :rows="reportRows"
    />

    <VDialog
      v-model="dialog"
      max-width="640"
    >
      <VCard :title="selected ? t('pages.offDays.edit') : t('pages.offDays.add')">
        <VCardText>
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
              <VTextarea
                v-model="form.reason"
                :label="t('pages.offDays.reason')"
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
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="dialog = false"
          >
            {{ t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
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
