<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { canAccess } from '@/utils/authorization'
import RecurringEventsApi, { type RecurringEvent, type RecurringEventInput, type RecurringEventType } from '@/api/recurringEvents'
import CurrencyApi, { type Currency } from '@/api/currency'
import CompanyApi, { type Company } from '@/api/company'
import ProjectApi, { type Project } from '@/api/project'
import BoardApi from '@/api/board'
import type { Board } from '@/api/kanban'

const loading = ref(false)
const route = useRoute()
const { t } = useI18n()
const saving = ref(false)
const catalogLoading = ref(false)
const events = ref<RecurringEvent[]>([])
const currencies = ref<Currency[]>([])
const companies = ref<Company[]>([])
const projects = ref<Project[]>([])
const boards = ref<Board[]>([])
const dialog = ref(false)
const detailDialog = ref(false)
const selected = ref<RecurringEvent | null>(null)
const today = new Date().toISOString().slice(0, 10)
const form = ref<RecurringEventInput>({ name: '', description: '', event_type: 'income', frequency: 'monthly', interval: 1, start_date: today, next_occurrence_date: today, end_date: null, payload: { type: 'Other', amount: 0, quantity: 1, company_id: null, project_id: null } })
const types: RecurringEventType[] = ['income', 'expense', 'task']

const visible = computed(() => events.value)
const headers = computed(() => [{ title: t('pages.recurringEvents.name'), key: 'name' }, { title: t('pages.recurringEvents.type'), key: 'event_type' }, { title: t('pages.recurringEvents.schedule'), key: 'schedule' }, { title: t('pages.recurringEvents.nextOccurrence'), key: 'next_occurrence_date' }, { title: t('pages.recurringEvents.timeRemaining'), key: 'remaining' }, { title: t('common.fields.status'), key: 'status' }, { title: '', key: 'actions', sortable: false }])
const availableTypes = computed(() => types.filter(type => canAccess(`create_recurring_${type}s`)))
const canViewBoards = computed(() => canAccess('view_all_boards') || canAccess('view_assigned_boards'))
const canViewCompanies = computed(() => canAccess('view_companies'))
const canViewProjects = computed(() => canAccess('view_projects'))
const incomeExpenseTypes = computed(() => ['Service', 'Product', 'Investment', 'Other'].map(value => ({ title: t(`pages.recurringEvents.recordTypes.${value.toLowerCase()}`), value })))
const frequencyOptions = computed(() => ['daily', 'weekly', 'monthly', 'yearly'].map(value => ({ title: t(`pages.recurringEvents.frequencies.${value}`), value })))
const currencyOptions = computed(() => currencies.value.filter(currency => currency.isActive))

const companyOptions = computed(() => companies.value
  .filter(company => company.is_active)
  .map(company => ({ title: company.trade_name || company.legal_name, value: company.id })))

const projectOptions = computed(() => projects.value
  .filter(project => project.status !== 'archived')
  .map(project => ({ title: project.customer ? `${project.name} - ${project.customer.name}` : project.name, value: project.id })))

function remaining(date: string) {
  const days = Math.ceil((new Date(`${date}T00:00:00`).getTime() - new Date(`${today}T00:00:00`).getTime()) / 86400000)

  return days < 0 ? t('pages.recurringEvents.overdueBy', { count: Math.abs(days) }) : days === 0 ? t('pages.recurringEvents.dueToday') : days === 1 ? t('pages.recurringEvents.dueTomorrow') : t('pages.recurringEvents.inDays', { count: days })
}
function schedule(interval: number, frequency: string) { return interval === 1 ? t(`pages.recurringEvents.frequencyLabels.${frequency}`) : t('pages.recurringEvents.everyFrequency', { count: interval, frequency: t(`pages.recurringEvents.frequencies.${frequency}`) }) }
function reset() { form.value = { name: '', description: '', event_type: availableTypes.value[0] ?? 'income', frequency: 'monthly', interval: 1, start_date: today, next_occurrence_date: today, end_date: null, payload: { type: 'Other', amount: null, quantity: 1, currency_id: null, company_id: null, project_id: null } }; selected.value = null }
async function load() {
  loading.value = true; try { events.value = await RecurringEventsApi.all() }
  finally { loading.value = false }
}
async function openDetail(item: RecurringEvent) { selected.value = await RecurringEventsApi.get(item.id); detailDialog.value = true }
async function loadCatalogs() {
  if (catalogLoading.value)
    return; catalogLoading.value = true; try {
    const requests: Promise<void>[] = []; if (canAccess('view_currencies'))
      requests.push(CurrencyApi.getAll().then(response => { currencies.value = response.data })); if (canViewBoards.value)
      requests.push(BoardApi.getAll().then(response => { boards.value = response.data.data })); if (canViewCompanies.value)
      requests.push(CompanyApi.getAll().then(records => { companies.value = records })); if (canViewProjects.value)
      requests.push(ProjectApi.getAll().then(records => { projects.value = records })); await Promise.all(requests)
  }
  finally { catalogLoading.value = false }
}
async function openCreate() { reset(); dialog.value = true; await loadCatalogs() }
function setType(type: RecurringEventType) { form.value.event_type = type; form.value.payload = type === 'task' ? { title: '', board_id: null, priority: 'medium' } : { type: 'Other', amount: 0, quantity: 1, currency_id: null, company_id: null, project_id: null } }
const canSave = computed(() => form.value.name.trim() && (form.value.event_type === 'task' ? Boolean(form.value.payload.title && form.value.payload.board_id) : Boolean(form.value.payload.type && Number(form.value.payload.amount) > 0 && form.value.payload.currency_id)))
async function save() {
  if (!canSave.value)
    return; saving.value = true; try { await RecurringEventsApi.create(form.value); dialog.value = false; await load() }
  finally { saving.value = false }
}
async function toggle(item: RecurringEvent) { item.status === 'active' ? await RecurringEventsApi.pause(item.id) : await RecurringEventsApi.resume(item.id); await load() }
async function remove(item: RecurringEvent) { if (confirm(`Delete ${item.name}?`)) { await RecurringEventsApi.remove(item.id); await load() } }
async function execute(occurrenceId: number) {
  await RecurringEventsApi.execute(occurrenceId); if (selected.value)
    await openDetail(selected.value); await load()
}
async function skip(occurrenceId: number) {
  await RecurringEventsApi.skip(occurrenceId); if (selected.value)
    await openDetail(selected.value); await load()
}
onMounted(async () => {
  await load()

  const id = Number(route.params.id)
  const event = events.value.find(item => item.id === id)
  if (event)
    await openDetail(event)
})
</script>

<template>
  <DataTablePageKpis
    :items="visible"
    :title="$t('pages.recurringEvents.title')"
    :description="$t('pages.recurringEvents.description')"
    icon="tabler-repeat"
  />
  <VCard>
    <VCardItem
      :title="$t('pages.recurringEvents.title')"
      :subtitle="$t('pages.recurringEvents.subtitle')"
    >
      <template #append>
        <VBtn
          v-if="canAccess('create_recurring_events') && availableTypes.length"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          {{ $t('pages.recurringEvents.create') }}
        </VBtn>
      </template>
    </VCardItem>
    <ResponsiveDataTable
      :headers="headers"
      :items="visible"
      :loading="loading"
      class="text-no-wrap"
    >
      <template #item.name="{ item }">
        <a
          href="#"
          class="text-primary font-weight-medium"
          @click.prevent="openDetail(item)"
        >{{ item.name }}</a><div class="text-caption text-medium-emphasis">
          {{ item.description }}
        </div>
      </template>
      <template #item.event_type="{ item }">
        <VChip
          size="small"
          :color="item.event_type === 'income' ? 'success' : item.event_type === 'expense' ? 'error' : 'primary'"
        >
          {{ $t(`pages.recurringEvents.eventTypes.${item.event_type}`) }}
        </VChip>
      </template>
      <template #item.schedule="{ item }">
        {{ schedule(item.interval, item.frequency) }}
      </template>
      <template #item.remaining="{ item }">
        <span :class="{ 'text-error': item.next_occurrence_date < today }">{{ remaining(item.next_occurrence_date) }}</span>
      </template>
      <template #item.status="{ item }">
        <VChip
          size="small"
          :color="item.status === 'active' ? 'success' : 'warning'"
        >
          {{ $t(`pages.recurringEvents.eventStatuses.${item.status}`) }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <VBtn
          icon="tabler-eye"
          variant="text"
          size="small"
          @click="openDetail(item)"
        /><VBtn
          v-if="canAccess('manage_recurring_events')"
          :icon="item.status === 'active' ? 'tabler-player-pause' : 'tabler-player-play'"
          variant="text"
          size="small"
          @click="toggle(item)"
        /><VBtn
          v-if="canAccess('delete_recurring_events')"
          icon="tabler-trash"
          color="error"
          variant="text"
          size="small"
          @click="remove(item)"
        />
      </template>
    </ResponsiveDataTable>
  </VCard>

  <VDialog
    v-model="dialog"
    max-width="720"
  >
    <VForm @submit.prevent="save">
      <VCard class="recurring-event-form">
        <VCardTitle>{{ $t('pages.recurringEvents.create') }}</VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12">
              <div
                class="event-type-picker"
                role="radiogroup"
                :aria-label="$t('pages.recurringEvents.eventType')"
              >
                <VBtn
                  v-for="type in availableTypes"
                  :key="type"
                  :color="form.event_type === type ? 'primary' : undefined"
                  :variant="form.event_type === type ? 'flat' : 'text'"
                  class="text-capitalize"
                  @click="setType(type)"
                >
                  {{ $t(`pages.recurringEvents.eventTypes.${type}`) }}
                </VBtn>
              </div>
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.name"
                :label="$t('common.fields.name')"
                :placeholder="$t('pages.recurringEvents.namePlaceholder')"
                required
              />
            </VCol><VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="form.frequency"
                :items="frequencyOptions"
                :label="$t('pages.recurringEvents.repeat')"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppDateTimePicker
                v-model="form.start_date"
                :label="$t('common.fields.startDate')"
                :config="{ dateFormat: 'Y-m-d' }"
              />
            </VCol><VCol
              cols="12"
              md="4"
            >
              <AppDateTimePicker
                v-model="form.next_occurrence_date"
                :label="$t('pages.recurringEvents.firstOccurrence')"
                :config="{ dateFormat: 'Y-m-d', minDate: form.start_date }"
              />
            </VCol><VCol
              cols="12"
              md="4"
            >
              <AppDateTimePicker
                v-model="form.end_date"
                :label="$t('common.fields.endDate')"
                :config="{ dateFormat: 'Y-m-d', minDate: form.start_date }"
                clearable
              />
            </VCol>
            <template v-if="form.event_type !== 'task'">
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="form.payload.type"
                  :items="incomeExpenseTypes"
                  :label="$t('common.fields.type')"
                />
              </VCol><VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="form.payload.amount"
                  type="number"
                  min="0.01"
                  step="0.01"
                  :label="$t('common.fields.amount')"
                />
              </VCol><VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="form.payload.company_id"
                  :items="companyOptions"
                  label="Company (optional)"
                  :loading="catalogLoading"
                  :disabled="!canViewCompanies"
                  clearable
                />
              </VCol><VCol
                v-if="form.event_type === 'expense'"
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="form.payload.project_id"
                  :items="projectOptions"
                  label="Project (optional)"
                  :loading="catalogLoading"
                  :disabled="!canViewProjects"
                  clearable
                />
              </VCol><VCol cols="12">
                <VSelect
                  v-model="form.payload.currency_id"
                  :items="currencyOptions"
                  :item-title="currency => `${currency.code} · ${currency.name}`"
                  item-value="id"
                  :label="$t('common.fields.currency')"
                  :loading="catalogLoading"
                  :disabled="!canAccess('view_currencies')"
                  :hint="!canAccess('view_currencies') ? $t('pages.recurringEvents.currencyPermissionRequired') : undefined"
                  persistent-hint
                  required
                />
              </VCol>
            </template>
            <template v-else>
              <VCol cols="12">
                <VTextField
                  v-model="form.payload.title"
                  :label="$t('pages.recurringEvents.taskTitle')"
                  required
                />
              </VCol><VCol cols="12">
                <VSelect
                  v-model="form.payload.board_id"
                  :items="boards"
                  item-title="title"
                  item-value="id"
                  :label="$t('pages.recurringEvents.board')"
                  :loading="catalogLoading"
                  :disabled="!canViewBoards"
                  :hint="!canViewBoards ? $t('pages.recurringEvents.boardPermissionRequired') : undefined"
                  persistent-hint
                  required
                />
              </VCol>
            </template>
            <VCol cols="12">
              <VTextarea
                v-model="form.description"
                :label="$t('common.fields.description')"
                :placeholder="$t('pages.recurringEvents.descriptionPlaceholder')"
                rows="3"
                auto-grow
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="px-6 pb-5">
          <VSpacer /><VBtn
            variant="text"
            :disabled="saving"
            @click="dialog = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn><VBtn
            color="primary"
            type="submit"
            :loading="saving"
            :disabled="!canSave"
          >
            {{ $t('common.actions.save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VForm>
  </VDialog>
  <VDialog
    v-model="detailDialog"
    max-width="820"
  >
    <VCard v-if="selected">
      <VCardTitle>{{ selected.name }}</VCardTitle><VCardText>
        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <div class="text-caption">
              {{ $t('pages.recurringEvents.nextOccurrence').toUpperCase() }}
            </div><div class="text-h6">
              {{ selected.next_occurrence_date }}
            </div><div>{{ remaining(selected.next_occurrence_date) }}</div>
          </VCol><VCol
            cols="12"
            md="6"
          >
            <div class="text-caption">
              {{ $t('pages.recurringEvents.schedule').toUpperCase() }}
            </div><div>{{ schedule(selected.interval, selected.frequency) }}</div><div class="mt-2">
              {{ selected.description }}
            </div>
          </VCol>
        </VRow><VDivider class="my-5" /><div class="text-h6 mb-3">
          {{ $t('pages.recurringEvents.occurrenceHistory') }}
        </div><VTable>
          <thead><tr><th>{{ $t('common.fields.date') }}</th><th>{{ $t('common.fields.status') }}</th><th>{{ $t('pages.recurringEvents.result') }}</th><th /></tr></thead><tbody>
            <tr
              v-for="item in selected.occurrences"
              :key="item.id"
            >
              <td>{{ item.occurrence_date }}</td><td>
                <VChip size="x-small">
                  {{ $t(`pages.recurringEvents.statuses.${item.status}`) }}
                </VChip>
              </td><td>{{ item.result_id ? `#${item.result_id}` : '—' }}</td><td>
                <VBtn
                  v-if="item.status === 'notified' && canAccess('execute_recurring_events')"
                  size="small"
                  @click="execute(item.id)"
                >
                  {{ $t('pages.recurringEvents.execute') }}
                </VBtn><VBtn
                  v-if="item.status === 'notified' && canAccess('manage_recurring_events')"
                  size="small"
                  variant="text"
                  @click="skip(item.id)"
                >
                  {{ $t('pages.recurringEvents.skip') }}
                </VBtn>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.event-type-picker {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.25rem;
  padding: 0.25rem;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0.5rem;
}

.event-type-picker :deep(.v-btn) {
  min-inline-size: 0;
  border-radius: 0.375rem;
}
</style>
