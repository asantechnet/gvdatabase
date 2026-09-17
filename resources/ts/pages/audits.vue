<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import {
  type AuditChange,
  type AuditLog,
  type AuditResource,
  type AuditState,
  getAuditLog,
  getAuditLogs,
  getAuditResources,
  bulkReviewAuditLogs,
  reviewAuditLog,
  updateAuditResource,
} from '@/api/auditLog'
import { formatLocalizedDate, formatLocalizedNumber, translateBackendValue } from '@/utils/localization'
import { canAccess } from '@/utils/authorization'

const { t, locale } = useI18n()
const $t = t
const toast = useToast()
const route = useRoute()
const router = useRouter()

const section = ref<'records' | 'resources'>(route.query.section === 'resources' ? 'resources' : 'records')
const logs = ref<AuditLog[]>([])
const resources = ref<AuditResource[]>([])
const loading = ref(false)
const resourceLoading = ref(false)
const reviewing = ref(false)
const togglingId = ref<number | null>(null)
const selectedAudit = ref<AuditLog | null>(null)
const detailsOpen = ref(false)
const confirmDecision = ref<'approved' | 'discarded' | null>(null)
const bulkDecision = ref<'approved' | 'discarded' | null>(null)
const bulkReviewing = ref(false)
const selectedAuditIds = ref<number[]>([])

const auditPage = ref(Number(route.query.page) || 1)
const auditPerPage = ref(Number(route.query.per_page) || 25)
const auditTotal = ref(0)
const auditState = ref<'all' | AuditState>((route.query.state as 'all' | AuditState) || 'pending')
const eventFilter = ref(String(route.query.event || 'all'))
const resourceFilter = ref(String(route.query.resource || 'all'))
const auditSearch = ref(String(route.query.search || ''))

const resourcePage = ref(1)
const resourcePerPage = ref(25)
const resourceTotal = ref(0)
const resourceState = ref<'all' | 'enabled' | 'disabled'>('all')
const resourceSearch = ref('')

const canReview = computed(() => canAccess('review_audit_logs'))
const canManageScope = computed(() => canAccess('manage_audit_scope'))
// This mirrors the actual auditable registry plus the two audited workflows
// that are recorded outside model events. Avoid listing tables that merely
// exist in the database but are not configured as auditable.
const resourceKeys = ['attendances', 'boards', 'comments', 'companies', 'commission_accruals', 'commission_settlements', 'contract_policies', 'contract_templates', 'contract_types', 'contracts', 'currencies', 'customer_services', 'customers', 'daily_reports', 'employees', 'expenses', 'incomes', 'invoices', 'marketers', 'offices', 'leaves', 'payment_details', 'permissions', 'positions', 'salaries', 'services', 'settings', 'assets', 'subtasks', 'tags', 'task_attachments', 'tasks', 'users', 'visitors', 'worthy_employees', 'account_deactivations', 'audit_settings']
const resourcePurposes: Record<string, { group: string; description: string }> = {
  invoices: { group: 'Financial records', description: 'Issued invoices, their values, and payment status.' }, incomes: { group: 'Financial records', description: 'Recorded money received by the company.' }, expenses: { group: 'Financial records', description: 'Recorded business spending.' }, payment_details: { group: 'Financial records', description: 'Payment method and payment-detail records.' }, currencies: { group: 'Financial records', description: 'Currency definitions and exchange rates.' }, salaries: { group: 'Financial records', description: 'Payroll records and their amounts.' }, commission_accruals: { group: 'Financial records', description: 'Commission amounts earned before settlement.' }, commission_settlements: { group: 'Financial records', description: 'Approved and paid commission settlements.' },
  customers: { group: 'Customers & contracts', description: 'Customer identity and contact records.' }, customer_services: { group: 'Customers & contracts', description: 'Services committed to individual customers.' }, companies: { group: 'Customers & contracts', description: 'Counterparty company records used in agreements.' }, contracts: { group: 'Customers & contracts', description: 'Customer and company agreements.' }, contract_templates: { group: 'Customers & contracts', description: 'Reusable contract documents.' }, contract_policies: { group: 'Customers & contracts', description: 'Reusable policy content for contracts.' }, contract_types: { group: 'Customers & contracts', description: 'Categories used to classify contracts.' }, visitors: { group: 'Customers & contracts', description: 'New prospective contacts.' }, leads: { group: 'Customers & contracts', description: 'Qualified prospects being followed up.' }, crm_interactions: { group: 'Customers & contracts', description: 'Notes and interactions with prospects and customers.' },
  employees: { group: 'People & attendance', description: 'Employee work profiles.' }, users: { group: 'People & attendance', description: 'System user accounts and access-relevant changes.' }, attendances: { group: 'People & attendance', description: 'Daily check-in, check-out, and working-time records.' }, leaves: { group: 'People & attendance', description: 'Paid and unpaid leave records.' }, daily_reports: { group: 'People & attendance', description: 'End-of-day work reports.' }, positions: { group: 'People & attendance', description: 'Job positions used by staff profiles.' }, marketers: { group: 'People & attendance', description: 'Marketing-assignment records.' }, worthy_employees: { group: 'People & attendance', description: 'Bonus nominations and their decisions.' }, account_deactivations: { group: 'People & attendance', description: 'Account deactivation and reactivation workflow.' },
  tasks: { group: 'Work management', description: 'Tasks and their progress.' }, subtasks: { group: 'Work management', description: 'Subtasks owned within larger work items.' }, boards: { group: 'Work management', description: 'Task boards and their structure.' }, comments: { group: 'Work management', description: 'Comments attached to work items.' }, task_attachments: { group: 'Work management', description: 'Files attached to tasks.' }, tags: { group: 'Work management', description: 'Task-label catalogue changes.' },
  assets: { group: 'Operations & setup', description: 'Company assets, assignments, and condition.' }, offices: { group: 'Operations & setup', description: 'Office records, including attendance-related configuration.' }, services: { group: 'Operations & setup', description: 'Service catalogue definitions.' }, settings: { group: 'Operations & setup', description: 'Company-wide operational and communication settings.' }, permissions: { group: 'Operations & setup', description: 'Permission definitions that control system access.' }, audit_settings: { group: 'Operations & setup', description: 'Changes to which business areas are audited.' },
}
const resourceLabels: Record<string, string> = {
  commission_accruals: 'Commission accruals', commission_settlements: 'Commission settlements', leaves: 'Leave', leads: 'Leads', crm_interactions: 'CRM interactions', account_deactivations: 'Account deactivations', audit_settings: 'Audit settings',
}
const resourceOptions = computed(() => [...resourceKeys].sort((a, b) => resourceName(a).localeCompare(resourceName(b))).map(value => ({ value, title: resourceName(value) })))
const eventOptions = computed(() => ['created', 'updated', 'deleted', 'restored', 'audit_scope_enabled', 'audit_scope_disabled'].map(value => ({ value, title: eventLabel(value) })))
const hideOnMobile = {
  headerProps: { class: 'd-none d-md-table-cell' },
  cellProps: { class: 'd-none d-md-table-cell' },
}

const auditHeaders = computed(() => [
  { title: t('pages.audits.columns.action'), key: 'event', width: '120px', ...hideOnMobile },
  { title: t('pages.audits.columns.record'), key: 'record' },
  { title: t('pages.audits.columns.change'), key: 'changes', sortable: false, ...hideOnMobile },
  { title: t('pages.audits.columns.changedBy'), key: 'changed_by', sortable: false, ...hideOnMobile },
  { title: t('pages.audits.columns.when'), key: 'created_at', ...hideOnMobile },
  { title: t('pages.audits.columns.auditedBy'), key: 'audited_by', sortable: false, ...hideOnMobile },
  { title: t('pages.audits.columns.status'), key: 'audit_state', sortable: false, ...hideOnMobile },
  { title: t('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

const resourceHeaders = computed(() => [
  { title: t('pages.audits.columns.resource'), key: 'resource_key' },
  { title: 'What is monitored', key: 'purpose', sortable: false, ...hideOnMobile },
  { title: t('pages.audits.columns.auditEnabled'), key: 'enabled', sortable: false },
  { title: t('pages.audits.columns.lastChangedBy'), key: 'updated_by', sortable: false, ...hideOnMobile },
  { title: t('pages.audits.columns.lastChanged'), key: 'updated_at', sortable: false, ...hideOnMobile },
])

const selectedAudits = computed(() => logs.value.filter(log => selectedAuditIds.value.includes(log.id)))
const canUndoSelection = computed(() => {
  const selectedIds = new Set(selectedAuditIds.value)

  return selectedAudits.value.length > 0 && selectedAudits.value.every(log =>
    log.can_revert || (log.revert_blocked_by_id !== null && selectedIds.has(log.revert_blocked_by_id)))
})
const undoUnavailableReason = computed(() => {
  const nonRevertible = selectedAudits.value.find(log => !['updated', 'deleted'].includes(log.event))
  if (nonRevertible)
    return t('pages.audits.bulkUndo.event')

  return t('pages.audits.bulkUndo.newer')
})

function localized(key: string, fallbackKey: string): string {
  const value = String(t(key))

  return !value || value.startsWith('[missing:') || value === key ? String(t(fallbackKey)) : value
}

function resourceName(key: string): string {
  const translated = String(t(`pages.audits.resources.${key}`))

  return translated && !translated.startsWith('[missing:') && translated !== `pages.audits.resources.${key}`
    ? translated
    : resourceLabels[key] ?? key.replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase())
}

function resourcePurpose(key: string): { group: string; description: string } {
  return resourcePurposes[key] ?? { group: 'Other business records', description: 'Operational records retained for traceability.' }
}

function eventLabel(event: string): string {
  return localized(`pages.audits.events.${event}`, 'pages.audits.events.other')
}

function fieldLabel(field: string): string {
  const auditLabel = String(t(`pages.audits.fields.${field}`))
  if (auditLabel && !auditLabel.startsWith('[missing:') && auditLabel !== `pages.audits.fields.${field}`)
    return auditLabel
  const commonLabel = String(t(`common.fields.${field}`))

  return commonLabel && !commonLabel.startsWith('[missing:') && commonLabel !== `common.fields.${field}`
    ? commonLabel
    : String(t('pages.audits.otherInformation'))
}

function recordLabel(log: AuditLog): string {
  const rawLabel = log.resource_key === 'audit_settings' ? resourceName(log.subject_label) : log.subject_label
  const label = rawLabel.replace(/\b\d{4}-\d{2}-\d{2}\b/g, value => formatLocalizedDate(value, 'short'))

  return label ? `${resourceName(log.resource_key)} · ${label}` : resourceName(log.resource_key)
}

function formatValue(value: unknown, field = ''): string {
  if (value === null || value === undefined || value === '')
    return String(t('common.messages.notAvailable'))
  if (typeof value === 'boolean')
    return String(t(value ? 'common.messages.yes' : 'common.messages.no'))
  if (typeof value === 'number')
    return formatLocalizedNumber(value)
  if (Array.isArray(value))
    return value.map(item => formatValue(item, field)).join(` ${t('pages.audits.valueSeparator')} `)
  if (typeof value === 'object')
    return Object.values(value as Record<string, unknown>).map(item => formatValue(item, field)).join(` ${t('pages.audits.valueSeparator')} `)
  if (/(date|At|Until|Month)$/.test(field) && !Number.isNaN(new Date(String(value)).getTime()))
    return formatLocalizedDate(String(value), 'long')

  return translateBackendValue(value)
}

function shortChanges(log: AuditLog): string {
  if (log.event === 'created')
    return String(t('pages.audits.noCreateColumns'))
  if (!log.changes.length)
    return String(t('pages.audits.noUserFacingChanges'))
  const first = log.changes[0]
  const text = `${fieldLabel(first.field)}: ${formatValue(first.before, first.field)} → ${formatValue(first.after, first.field)}`

  return log.changes.length > 1 ? `${text} ${t('pages.audits.moreChanges', { count: log.changes.length - 1 })}` : text
}

function stateColor(log: AuditLog): string {
  if (log.audit_state === 'pending')
    return 'warning'

  return log.resolution === 'discarded' ? 'error' : 'success'
}

function stateLabel(log: AuditLog): string {
  if (log.audit_state === 'pending')
    return String(t('pages.audits.states.pending'))

  return String(t(`pages.audits.states.${log.resolution}`))
}

function compactAuditDate(value: string): string {
  return ['fa-AF', 'ps-AF'].includes(String(locale.value))
    ? formatLocalizedDate(value, { year: '2-digit', month: '2-digit', day: '2-digit' })
    : formatLocalizedDate(value, 'short')
}

async function loadAudits() {
  loading.value = true
  try {
    const result = await getAuditLogs({
      page: auditPage.value,
      per_page: auditPerPage.value,
      audit_state: auditState.value === 'all' ? undefined : auditState.value,
      event: eventFilter.value === 'all' ? undefined : eventFilter.value,
      resource: resourceFilter.value === 'all' ? undefined : resourceFilter.value,
      search: auditSearch.value || undefined,
    })

    logs.value = result.data
    selectedAuditIds.value = selectedAuditIds.value.filter(id => logs.value.some(log => log.id === id && log.audit_state === 'pending'))
    auditTotal.value = result.total
  }
  catch (error: any) {
    toast.error(error?.message || t('pages.audits.errors.load'))
  }
  finally {
    loading.value = false
  }
}

async function loadResources() {
  resourceLoading.value = true
  try {
    const localizedMatch = resourceSearch.value
      ? resourceKeys.find(key => resourceName(key).toLocaleLowerCase().includes(resourceSearch.value.toLocaleLowerCase()))
      : undefined

    const result = await getAuditResources({
      page: resourcePage.value,
      per_page: resourcePerPage.value,
      state: resourceState.value === 'all' ? undefined : resourceState.value,
      search: localizedMatch || resourceSearch.value || undefined,
    })

    resources.value = result.data
    resourceTotal.value = result.total
  }
  catch (error: any) {
    toast.error(error?.message || t('pages.audits.errors.loadResources'))
  }
  finally {
    resourceLoading.value = false
  }
}

async function showDetails(log: AuditLog) {
  selectedAudit.value = log
  detailsOpen.value = true
  await router.replace({ query: { ...route.query, audit: String(log.id) } })
}

async function closeDetails() {
  detailsOpen.value = false
  selectedAudit.value = null

  const query = { ...route.query }

  delete query.audit
  await router.replace({ query })
}

async function applyDecision() {
  if (!selectedAudit.value || !confirmDecision.value)
    return
  reviewing.value = true
  try {
    selectedAudit.value = await reviewAuditLog(selectedAudit.value.id, confirmDecision.value)
    toast.success(t(confirmDecision.value === 'approved' ? 'pages.audits.messages.kept' : 'pages.audits.messages.reverted'))
    confirmDecision.value = null
    await loadAudits()
  }
  catch (error: any) {
    toast.error(error?.message || t('pages.audits.errors.review'))
  }
  finally {
    reviewing.value = false
  }
}

async function applyBulkDecision() {
  if (!bulkDecision.value || !selectedAuditIds.value.length)
    return

  bulkReviewing.value = true
  try {
    const count = await bulkReviewAuditLogs(selectedAuditIds.value, bulkDecision.value)
    toast.success(t(bulkDecision.value === 'approved' ? 'pages.audits.bulk.keptSuccess' : 'pages.audits.bulk.undoneSuccess', { count }))
    selectedAuditIds.value = []
    bulkDecision.value = null
    await loadAudits()
  }
  catch (error: any) {
    toast.error(t('pages.audits.bulk.reviewError'))
  }
  finally {
    bulkReviewing.value = false
  }
}

async function toggleResource(item: AuditResource, enabled: boolean) {
  togglingId.value = item.id
  try {
    const updated = await updateAuditResource(item.id, enabled)
    const index = resources.value.findIndex(resource => resource.id === item.id)
    if (index >= 0)
      resources.value[index] = updated
    toast.success(t(enabled ? 'pages.audits.messages.enabled' : 'pages.audits.messages.disabled', { resource: resourceName(item.resource_key) }))
    if (resourceState.value !== 'all')
      await loadResources()
  }
  catch (error: any) {
    toast.error(error?.message || t('pages.audits.errors.scope'))
  }
  finally {
    togglingId.value = null
  }
}

function resetAuditPage() {
  if (auditPage.value !== 1)
    auditPage.value = 1
  else void loadAudits()
}

function resetResourcePage() {
  if (resourcePage.value !== 1)
    resourcePage.value = 1
  else void loadResources()
}

let auditSearchTimer: ReturnType<typeof setTimeout> | undefined
watch(auditSearch, () => {
  if (auditSearchTimer)
    clearTimeout(auditSearchTimer)
  auditSearchTimer = setTimeout(resetAuditPage, 300)
})
watch([auditState, eventFilter, resourceFilter], resetAuditPage)
watch([auditState, eventFilter, resourceFilter, auditPage, auditPerPage], () => { selectedAuditIds.value = [] })
watch([auditPage, auditPerPage], loadAudits)

let resourceSearchTimer: ReturnType<typeof setTimeout> | undefined
watch(resourceSearch, () => {
  if (resourceSearchTimer)
    clearTimeout(resourceSearchTimer)
  resourceSearchTimer = setTimeout(resetResourcePage, 300)
})
watch(resourceState, resetResourcePage)
watch([resourcePage, resourcePerPage], loadResources)
watch(section, async value => {
  await router.replace({ query: { ...route.query, section: value === 'records' ? undefined : value } })
  if (value === 'resources' && !resources.value.length)
    await loadResources()
})

onMounted(async () => {
  await loadAudits()
  if (section.value === 'resources')
    await loadResources()
  const auditId = Number(route.query.audit)
  if (Number.isInteger(auditId) && auditId > 0) {
    try { await showDetails(logs.value.find(log => log.id === auditId) ?? await getAuditLog(auditId)) }
    catch (error: any) { toast.error(error?.message || t('pages.audits.errors.notFound')) }
  }
})

onUnmounted(() => {
  if (auditSearchTimer)
    clearTimeout(auditSearchTimer)
  if (resourceSearchTimer)
    clearTimeout(resourceSearchTimer)
})
</script>

<template>
  <div class="audit-page">
    <div class="d-flex flex-wrap align-center ga-3 mb-5">
      <div>
        <h1 class="text-h4 mb-1">
          {{ $t('pages.audits.title') }}
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          {{ $t('pages.audits.subtitle') }}
        </p>
      </div>
    </div>

    <VTabs
      v-model="section"
      class="mb-4"
      color="primary"
    >
      <VTab
        value="records"
        prepend-icon="tabler-list-check"
      >
        {{ $t('pages.audits.sections.records') }}
      </VTab>
      <VTab
        value="resources"
        prepend-icon="tabler-adjustments-check"
      >
        {{ $t('pages.audits.sections.resources') }}
      </VTab>
    </VTabs>

    <VWindow v-model="section">
      <VWindowItem value="records">
        <VCard :loading="loading">
          <VCardText class="audit-filters">
            <VBtnToggle
              v-model="auditState"
              mandatory
              density="compact"
              color="primary"
              divided
              class="audit-filter-toggle"
            >
              <VBtn value="pending">
                {{ $t('pages.audits.filters.notAudited') }}
              </VBtn>
              <VBtn value="audited">
                {{ $t('pages.audits.filters.audited') }}
              </VBtn>
              <VBtn value="all">
                {{ $t('pages.audits.filters.all') }}
              </VBtn>
            </VBtnToggle>
            <VTextField
              v-model="auditSearch"
              :label="$t('pages.audits.filters.search')"
              prepend-inner-icon="tabler-search"
              density="compact"
              hide-details
              clearable
            />
            <VSelect
              v-model="resourceFilter"
              :label="$t('pages.audits.filters.resource')"
              :items="[{ value: 'all', title: $t('pages.audits.filters.allResources') }, ...resourceOptions]"
              density="compact"
              hide-details
            />
            <VSelect
              v-model="eventFilter"
              :label="$t('pages.audits.filters.action')"
              :items="[{ value: 'all', title: $t('pages.audits.filters.allActions') }, ...eventOptions]"
              density="compact"
              hide-details
            />
          </VCardText>
          <VDivider />

          <VExpandTransition>
            <div
              v-if="selectedAuditIds.length"
              class="audit-selection-toolbar"
              role="region"
              :aria-label="$t('pages.audits.bulk.selected', { count: selectedAuditIds.length })"
            >
              <div class="audit-selection-toolbar__summary">
                <VIcon icon="tabler-checkbox" color="primary" />
                <strong>{{ $t('pages.audits.bulk.selected', { count: selectedAuditIds.length }) }}</strong>
                <span>{{ $t('pages.audits.bulk.reviewHint') }}</span>
              </div>
              <div class="audit-selection-toolbar__actions">
                <VBtn
                  color="success"
                  prepend-icon="tabler-check"
                  :disabled="!canReview"
                  @click="bulkDecision = 'approved'"
                >
                  {{ $t('pages.audits.bulk.keep') }}
                </VBtn>
                <VTooltip location="top" :disabled="canUndoSelection">
                  <template #activator="{ props }">
                    <span v-bind="props" class="audit-selection-toolbar__undo-wrap">
                      <VBtn
                        color="error"
                        variant="tonal"
                        prepend-icon="tabler-arrow-back-up"
                        :disabled="!canUndoSelection"
                        @click="bulkDecision = 'discarded'"
                      >
                        {{ $t('pages.audits.bulk.undo') }}
                      </VBtn>
                    </span>
                  </template>
                  {{ undoUnavailableReason }}
                </VTooltip>
                <IconBtn :aria-label="$t('pages.audits.bulk.clear')" @click="selectedAuditIds = []">
                  <VIcon icon="tabler-x" />
                  <VTooltip activator="parent">{{ $t('pages.audits.bulk.clear') }}</VTooltip>
                </IconBtn>
              </div>
            </div>
          </VExpandTransition>

          <ResponsiveDataTableServer
            v-model="selectedAuditIds"
            v-model:page="auditPage"
            v-model:items-per-page="auditPerPage"
            :headers="auditHeaders"
            :items="logs"
            :items-length="auditTotal"
            :loading="loading"
            item-value="id"
            show-select
            mobile-cards
            mobile-title-key="record"
            mobile-action-key="actions"
            :item-selectable="(log: AuditLog) => canReview && log.audit_state === 'pending'"
          >
            <template #item.event="{ item }">
              <VChip
                size="small"
                variant="tonal"
                color="primary"
              >
                {{ eventLabel(item.event) }}
              </VChip>
            </template>
            <template #item.record="{ item }">
              <div class="audit-record">
                <strong>{{ recordLabel(item) }}</strong>
                <div class="audit-record__mobile-meta d-md-none">
                  <VChip size="x-small" variant="tonal" color="primary">
                    {{ eventLabel(item.event) }}
                  </VChip>
                  <VChip size="x-small" :color="stateColor(item)" variant="tonal">
                    {{ stateLabel(item) }}
                  </VChip>
                  <span>{{ item.changed_by?.name ?? $t('pages.audits.system') }}</span>
                  <span>{{ compactAuditDate(item.created_at) }}</span>
                </div>
              </div>
            </template>
            <template #item.changes="{ item }">
              <span class="audit-change-summary">{{ shortChanges(item) }}</span>
            </template>
            <template #item.changed_by="{ item }">
              {{ item.changed_by?.name ?? $t('pages.audits.system') }}
            </template>
            <template #item.created_at="{ item }">
              {{ compactAuditDate(item.created_at) }}
            </template>
            <template #item.audited_by="{ item }">
              {{ item.audited_by?.name ?? $t('pages.audits.notAuditedYet') }}
            </template>
            <template #item.audit_state="{ item }">
              <VChip
                size="small"
                :color="stateColor(item)"
                variant="tonal"
              >
                {{ stateLabel(item) }}
              </VChip>
            </template>
            <template #item.actions="{ item }">
              <IconBtn
                :aria-label="$t('pages.audits.actions.view')"
                @click="showDetails(item)"
              >
                <VIcon icon="tabler-eye" />
              </IconBtn>
            </template>
            <template #no-data>
              <div class="pa-10 text-center text-medium-emphasis">
                {{ $t('pages.audits.empty.records') }}
              </div>
            </template>
          </ResponsiveDataTableServer>
        </VCard>
      </VWindowItem>

      <VWindowItem value="resources">
        <VAlert
          type="info"
          variant="tonal"
          class="mb-4"
        >
          {{ $t('pages.audits.scopeNotice') }}
        </VAlert>
        <VAlert
          type="info"
          variant="tonal"
          class="mb-4"
          :title="$t('pages.audits.scopeGuide.title')"
        >
          {{ $t('pages.audits.scopeGuide.description') }}
        </VAlert>
        <VCard :loading="resourceLoading">
          <VCardText class="audit-filters audit-resource-filters">
            <VBtnToggle
              v-model="resourceState"
              mandatory
              density="compact"
              color="primary"
              divided
              class="audit-filter-toggle"
            >
              <VBtn value="all">
                {{ $t('pages.audits.filters.all') }}
              </VBtn>
              <VBtn value="enabled">
                {{ $t('pages.audits.filters.enabled') }}
              </VBtn>
              <VBtn value="disabled">
                {{ $t('pages.audits.filters.disabled') }}
              </VBtn>
            </VBtnToggle>
            <VTextField
              v-model="resourceSearch"
              :label="$t('pages.audits.filters.searchResources')"
              prepend-inner-icon="tabler-search"
              density="compact"
              hide-details
              clearable
            />
          </VCardText>
          <VDivider />

          <ResponsiveDataTableServer
            v-model:page="resourcePage"
            v-model:items-per-page="resourcePerPage"
            :headers="resourceHeaders"
            :items="resources"
            :items-length="resourceTotal"
            :loading="resourceLoading"
            item-value="id"
            mobile-cards
            mobile-title-key="resource_key"
          >
            <template #item.resource_key="{ item }">
              <div class="audit-resource-name">
                <strong>{{ resourceName(item.resource_key) }}</strong>
                <VChip
                  size="x-small"
                  variant="tonal"
                >
                  {{ resourcePurpose(item.resource_key).group }}
                </VChip>
                <span class="audit-resource-name__mobile-purpose d-md-none">{{ resourcePurpose(item.resource_key).description }}</span>
              </div>
            </template>
            <template #item.purpose="{ item }">
              <span class="audit-resource-purpose">{{ resourcePurpose(item.resource_key).description }}</span>
            </template>
            <template #item.enabled="{ item }">
              <VSwitch
                :model-value="item.enabled"
                :loading="togglingId === item.id"
                :disabled="!canManageScope || togglingId === item.id"
                color="success"
                hide-details
                :aria-label="$t('pages.audits.actions.toggleResource', { resource: resourceName(item.resource_key) })"
                @update:model-value="toggleResource(item, Boolean($event))"
              />
            </template>
            <template #item.updated_by="{ item }">
              {{ item.updated_by?.name ?? $t('pages.audits.system') }}
            </template>
            <template #item.updated_at="{ item }">
              {{ item.updated_at ? compactAuditDate(item.updated_at) : $t('common.messages.notAvailable') }}
            </template>
            <template #no-data>
              <div class="pa-10 text-center text-medium-emphasis">
                {{ $t('pages.audits.empty.resources') }}
              </div>
            </template>
          </ResponsiveDataTableServer>
        </VCard>
      </VWindowItem>
    </VWindow>

    <VDialog
      :model-value="detailsOpen"
      max-width="900"
      @update:model-value="value => { if (!value) closeDetails() }"
    >
      <VCard v-if="selectedAudit">
        <VCardItem
          :title="recordLabel(selectedAudit)"
          :subtitle="`${eventLabel(selectedAudit.event)} · ${formatLocalizedDate(selectedAudit.created_at, 'long')}`"
        >
          <template #append>
            <IconBtn
              :aria-label="$t('pages.audits.actions.close')"
              @click="closeDetails"
            >
              <VIcon icon="tabler-x" />
            </IconBtn>
          </template>
        </VCardItem>
        <VDivider />
        <VCardText>
          <div class="audit-accountability mb-5">
            <div><span>{{ $t('pages.audits.columns.changedBy') }}</span><strong>{{ selectedAudit.changed_by?.name ?? $t('pages.audits.system') }}</strong></div>
            <div><span>{{ $t('pages.audits.columns.auditedBy') }}</span><strong>{{ selectedAudit.audited_by?.name ?? $t('pages.audits.notAuditedYet') }}</strong></div>
            <div>
              <span>{{ $t('pages.audits.columns.status') }}</span><VChip
                size="small"
                :color="stateColor(selectedAudit)"
                variant="tonal"
              >
                {{ stateLabel(selectedAudit) }}
              </VChip>
            </div>
          </div>

          <VAlert
            v-if="selectedAudit.event === 'created'"
            type="success"
            variant="tonal"
          >
            {{ $t('pages.audits.createExplanation') }}
          </VAlert>
          <VAlert
            v-else-if="!selectedAudit.changes.length"
            type="info"
            variant="tonal"
          >
            {{ $t('pages.audits.noUserFacingChanges') }}
          </VAlert>
          <VTable
            v-else
            class="audit-comparison"
            density="comfortable"
          >
            <thead><tr><th>{{ $t('pages.audits.columns.field') }}</th><th>{{ $t('pages.audits.columns.before') }}</th><th>{{ $t('pages.audits.columns.after') }}</th></tr></thead>
            <tbody>
              <tr
                v-for="change in selectedAudit.changes"
                :key="change.field"
              >
                <td><strong>{{ fieldLabel(change.field) }}</strong></td><td class="text-error">
                  {{ formatValue(change.before, change.field) }}
                </td><td class="text-success">
                  {{ formatValue(change.after, change.field) }}
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>
        <template v-if="selectedAudit.audit_state === 'pending' && canReview">
          <VDivider />
          <VCardActions class="justify-end pa-4">
            <VBtn
              v-if="selectedAudit.can_revert"
              color="error"
              variant="tonal"
              prepend-icon="tabler-arrow-back-up"
              @click="confirmDecision = 'discarded'"
            >
              {{ $t('pages.audits.actions.undo') }}
            </VBtn>
            <VBtn
              color="success"
              prepend-icon="tabler-check"
              @click="confirmDecision = 'approved'"
            >
              {{ $t('pages.audits.actions.keep') }}
            </VBtn>
          </VCardActions>
        </template>
      </VCard>
    </VDialog>

    <VDialog
      :model-value="Boolean(confirmDecision)"
      max-width="460"
      @update:model-value="value => { if (!value) confirmDecision = null }"
    >
      <VCard>
        <VCardItem :title="$t(confirmDecision === 'discarded' ? 'pages.audits.confirm.undoTitle' : 'pages.audits.confirm.keepTitle')" />
        <VCardText>{{ $t(confirmDecision === 'discarded' ? 'pages.audits.confirm.undoText' : 'pages.audits.confirm.keepText') }}</VCardText>
        <VCardActions class="justify-end">
          <VBtn
            variant="text"
            @click="confirmDecision = null"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            :color="confirmDecision === 'discarded' ? 'error' : 'success'"
            :loading="reviewing"
            @click="applyDecision"
          >
            {{ $t(confirmDecision === 'discarded' ? 'pages.audits.actions.undo' : 'pages.audits.actions.keep') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
    <VDialog
      :model-value="Boolean(bulkDecision)"
      max-width="520"
      @update:model-value="value => { if (!value) bulkDecision = null }"
    >
      <VCard>
        <VCardItem :title="$t(bulkDecision === 'discarded' ? 'pages.audits.bulk.undoTitle' : 'pages.audits.bulk.keepTitle')" />
        <VCardText>
          <template v-if="bulkDecision === 'discarded'">
            {{ $t('pages.audits.bulk.undoText', { count: selectedAuditIds.length }) }}
          </template>
          <template v-else>
            {{ $t('pages.audits.bulk.keepText', { count: selectedAuditIds.length }) }}
          </template>
        </VCardText>
        <VCardActions class="justify-end">
          <VBtn
            variant="text"
            :disabled="bulkReviewing"
            @click="bulkDecision = null"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            :color="bulkDecision === 'discarded' ? 'error' : 'success'"
            :loading="bulkReviewing"
            @click="applyBulkDecision"
          >
            {{ $t(bulkDecision === 'discarded' ? 'pages.audits.bulk.undo' : 'pages.audits.bulk.keep') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.audit-page { max-inline-size: 1500px; margin-inline: auto; }
.audit-filters { display: grid; grid-template-columns: auto minmax(220px, 1fr) minmax(180px, 0.45fr) minmax(170px, 0.4fr); gap: 0.75rem; align-items: center; }
.audit-resource-filters { grid-template-columns: auto minmax(240px, 1fr); }
.audit-filter-toggle { display: inline-flex; inline-size: max-content; max-inline-size: 100%; overflow-x: auto; }
.audit-filter-toggle :deep(.v-btn) { flex: 0 0 auto; min-inline-size: max-content; padding-inline: 1rem; white-space: nowrap; }
.audit-selection-toolbar { align-items: center; background: rgba(var(--v-theme-primary), 0.08); border-block-end: 1px solid rgba(var(--v-theme-primary), 0.18); display: flex; flex-wrap: wrap; gap: 0.75rem 1rem; justify-content: space-between; padding: 0.75rem 1rem; }
.audit-selection-toolbar__summary, .audit-selection-toolbar__actions { align-items: center; display: flex; flex-wrap: wrap; gap: 0.6rem; }
.audit-selection-toolbar__summary > span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: 0.8125rem; }
.audit-selection-toolbar__undo-wrap { display: inline-flex; }
.audit-record { display: flex; flex-direction: column; gap: 0.4rem; min-inline-size: 0; white-space: normal; }
.audit-record__mobile-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem 0.5rem; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: 0.75rem; }
.audit-record__mobile-meta > span { flex-basis: 100%; min-inline-size: 0; overflow-wrap: anywhere; }
.audit-resource-name__mobile-purpose { flex-basis: 100%; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: 0.8125rem; line-height: 1.4; }
.audit-resource-name { display: flex; flex-wrap: wrap; align-items: center; gap: 0.45rem; }
.audit-resource-purpose { display: block; max-inline-size: 34rem; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); white-space: normal; }
.audit-change-summary { display: block; min-inline-size: 220px; max-inline-size: 440px; white-space: normal; color: rgb(var(--v-theme-on-surface)); }
.audit-accountability { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
.audit-accountability > div { display: flex; flex-direction: column; gap: 0.35rem; padding: 0.85rem; border-radius: 0.6rem; background: rgba(var(--v-theme-on-surface), 0.04); }
.audit-accountability span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: 0.75rem; }
.audit-comparison td { max-inline-size: 360px; white-space: normal; overflow-wrap: anywhere; }
@media (max-width: 960px) {
  .audit-filters, .audit-resource-filters { grid-template-columns: 1fr 1fr; }
  .audit-filter-toggle { grid-column: 1 / -1; }
}
@media (max-width: 600px) {
  .audit-filters, .audit-resource-filters, .audit-accountability { grid-template-columns: 1fr; }
  .audit-filter-toggle { inline-size: 100%; }
  .audit-filter-toggle :deep(.v-btn) { flex-grow: 1; }
  .audit-page :deep(.v-data-table__td) { padding-inline: 0.75rem; }
  .audit-page :deep(.v-data-table__td:first-child), .audit-page :deep(.v-data-table__th:first-child) { padding-inline-end: 0.25rem; }
  .audit-page :deep(.v-data-table__td:last-child), .audit-page :deep(.v-data-table__th:last-child) { padding-inline-start: 0.25rem; }
  .audit-page :deep(.v-data-table-footer) { flex-wrap: wrap; justify-content: center; gap: 0.5rem; padding-inline: 0.5rem; }
  .audit-page :deep(.responsive-data-table--mobile-cards .v-data-table__thead) { display: none; }
  .audit-page :deep(.responsive-data-table--mobile-cards) { inline-size: min(100%, calc(100vw - 2rem)); max-inline-size: calc(100vw - 2rem); margin-inline: auto; overflow: clip; }
  .audit-page :deep(.responsive-data-table--mobile-cards .v-data-table__wrapper) { inline-size: 100%; max-inline-size: 100%; overflow: clip; }
  .audit-page :deep(.responsive-data-table--mobile-cards table) { inline-size: 100% !important; min-inline-size: 0 !important; max-inline-size: 100% !important; table-layout: fixed; }
  .audit-page :deep(.responsive-data-table--mobile-cards .responsive-data-table__mobile-row > td) { inline-size: 100%; max-inline-size: 100%; padding: 0.75rem; }
  .audit-page :deep(.responsive-data-table--mobile-cards .responsive-data-table__card), .audit-page :deep(.responsive-data-table--mobile-cards .responsive-data-table__card-toggle), .audit-page :deep(.responsive-data-table--mobile-cards .v-card-item__content), .audit-page :deep(.responsive-data-table--mobile-cards .responsive-data-table__card-title) { inline-size: 100%; min-inline-size: 0; max-inline-size: 100%; }
  .audit-page :deep(.responsive-data-table--mobile-cards .responsive-data-table__card-title) { overflow-wrap: anywhere; white-space: normal; }
  .audit-page :deep(.responsive-data-table--mobile-cards .v-chip) { align-self: flex-start; display: inline-flex !important; flex: 0 0 auto !important; inline-size: fit-content !important; width: fit-content !important; max-inline-size: 100%; }
}
</style>
