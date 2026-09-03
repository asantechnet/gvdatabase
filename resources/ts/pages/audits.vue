<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import {
  getAuditLog,
  getAuditLogs,
  getAuditResources,
  reviewAuditLog,
  updateAuditResource,
  type AuditChange,
  type AuditLog,
  type AuditResource,
  type AuditState,
} from '@/api/auditLog'
import { formatLocalizedDate, formatLocalizedNumber, translateBackendValue } from '@/utils/localization'
import { canAccess } from '@/utils/authorization'

const { t } = useI18n()
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
const resourceKeys = ['attendances', 'boards', 'comments', 'companies', 'commission_accruals', 'commission_settlements', 'contract_policies', 'contract_templates', 'contract_types', 'contracts', 'currencies', 'customer_services', 'customers', 'daily_reports', 'employees', 'expenses', 'incomes', 'invoices', 'marketers', 'offices', 'leaves', 'payment_details', 'permissions', 'positions', 'salaries', 'services', 'settings', 'assets', 'subtasks', 'tags', 'task_attachments', 'tasks', 'users', 'visitors', 'leads', 'crm_interactions', 'worthy_employees', 'account_deactivations', 'audit_settings']
const resourceOptions = computed(() => [...resourceKeys].sort((a, b) => resourceName(a).localeCompare(resourceName(b))).map(value => ({ value, title: resourceName(value) })))
const eventOptions = computed(() => ['created', 'updated', 'deleted', 'restored', 'audit_scope_enabled', 'audit_scope_disabled'].map(value => ({ value, title: eventLabel(value) })))

const auditHeaders = computed(() => [
  { title: t('pages.audits.columns.action'), key: 'event', width: '120px' },
  { title: t('pages.audits.columns.record'), key: 'record' },
  { title: t('pages.audits.columns.change'), key: 'changes', sortable: false },
  { title: t('pages.audits.columns.changedBy'), key: 'changed_by', sortable: false },
  { title: t('pages.audits.columns.when'), key: 'created_at' },
  { title: t('pages.audits.columns.auditedBy'), key: 'audited_by', sortable: false },
  { title: t('pages.audits.columns.status'), key: 'audit_state', sortable: false },
  { title: t('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
])

const resourceHeaders = computed(() => [
  { title: t('pages.audits.columns.resource'), key: 'resource_key' },
  { title: t('pages.audits.columns.auditEnabled'), key: 'enabled', sortable: false },
  { title: t('pages.audits.columns.lastChangedBy'), key: 'updated_by', sortable: false },
  { title: t('pages.audits.columns.lastChanged'), key: 'updated_at', sortable: false },
])

function localized(key: string, fallbackKey: string): string {
  const value = String(t(key))
  return !value || value.startsWith('[missing:') || value === key ? String(t(fallbackKey)) : value
}

function resourceName(key: string): string {
  return localized(`pages.audits.resources.${key}`, 'pages.audits.unknownRecord')
}

function eventLabel(event: string): string {
  return localized(`pages.audits.events.${event}`, 'pages.audits.events.other')
}

function fieldLabel(field: string): string {
  const auditLabel = String(t(`pages.audits.fields.${field}`))
  if (auditLabel && !auditLabel.startsWith('[missing:') && auditLabel !== `pages.audits.fields.${field}`) return auditLabel
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
  if (value === null || value === undefined || value === '') return String(t('common.messages.notAvailable'))
  if (typeof value === 'boolean') return String(t(value ? 'common.messages.yes' : 'common.messages.no'))
  if (typeof value === 'number') return formatLocalizedNumber(value)
  if (Array.isArray(value)) return value.map(item => formatValue(item, field)).join(` ${t('pages.audits.valueSeparator')} `)
  if (typeof value === 'object') return Object.values(value as Record<string, unknown>).map(item => formatValue(item, field)).join(` ${t('pages.audits.valueSeparator')} `)
  if (/(date|At|Until|Month)$/.test(field) && !Number.isNaN(new Date(String(value)).getTime())) return formatLocalizedDate(String(value), 'long')
  return translateBackendValue(value)
}

function shortChanges(log: AuditLog): string {
  if (log.event === 'created') return String(t('pages.audits.noCreateColumns'))
  if (!log.changes.length) return String(t('pages.audits.noUserFacingChanges'))
  const first = log.changes[0]
  const text = `${fieldLabel(first.field)}: ${formatValue(first.before, first.field)} → ${formatValue(first.after, first.field)}`
  return log.changes.length > 1 ? `${text} ${t('pages.audits.moreChanges', { count: log.changes.length - 1 })}` : text
}

function stateColor(log: AuditLog): string {
  if (log.audit_state === 'pending') return 'warning'
  return log.resolution === 'discarded' ? 'error' : 'success'
}

function stateLabel(log: AuditLog): string {
  if (log.audit_state === 'pending') return String(t('pages.audits.states.pending'))
  return String(t(`pages.audits.states.${log.resolution}`))
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
    auditTotal.value = result.total
  } catch (error: any) {
    toast.error(error?.message || t('pages.audits.errors.load'))
  } finally {
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
  } catch (error: any) {
    toast.error(error?.message || t('pages.audits.errors.loadResources'))
  } finally {
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
  if (!selectedAudit.value || !confirmDecision.value) return
  reviewing.value = true
  try {
    selectedAudit.value = await reviewAuditLog(selectedAudit.value.id, confirmDecision.value)
    toast.success(t(confirmDecision.value === 'approved' ? 'pages.audits.messages.kept' : 'pages.audits.messages.reverted'))
    confirmDecision.value = null
    await loadAudits()
  } catch (error: any) {
    toast.error(error?.message || t('pages.audits.errors.review'))
  } finally {
    reviewing.value = false
  }
}

async function toggleResource(item: AuditResource, enabled: boolean) {
  togglingId.value = item.id
  try {
    const updated = await updateAuditResource(item.id, enabled)
    const index = resources.value.findIndex(resource => resource.id === item.id)
    if (index >= 0) resources.value[index] = updated
    toast.success(t(enabled ? 'pages.audits.messages.enabled' : 'pages.audits.messages.disabled', { resource: resourceName(item.resource_key) }))
    if (resourceState.value !== 'all') await loadResources()
  } catch (error: any) {
    toast.error(error?.message || t('pages.audits.errors.scope'))
  } finally {
    togglingId.value = null
  }
}

function resetAuditPage() {
  if (auditPage.value !== 1) auditPage.value = 1
  else void loadAudits()
}

function resetResourcePage() {
  if (resourcePage.value !== 1) resourcePage.value = 1
  else void loadResources()
}

let auditSearchTimer: ReturnType<typeof setTimeout> | undefined
watch(auditSearch, () => {
  if (auditSearchTimer) clearTimeout(auditSearchTimer)
  auditSearchTimer = setTimeout(resetAuditPage, 300)
})
watch([auditState, eventFilter, resourceFilter], resetAuditPage)
watch([auditPage, auditPerPage], loadAudits)

let resourceSearchTimer: ReturnType<typeof setTimeout> | undefined
watch(resourceSearch, () => {
  if (resourceSearchTimer) clearTimeout(resourceSearchTimer)
  resourceSearchTimer = setTimeout(resetResourcePage, 300)
})
watch(resourceState, resetResourcePage)
watch([resourcePage, resourcePerPage], loadResources)
watch(section, async value => {
  await router.replace({ query: { ...route.query, section: value === 'records' ? undefined : value } })
  if (value === 'resources' && !resources.value.length) await loadResources()
})

onMounted(async () => {
  await loadAudits()
  if (section.value === 'resources') await loadResources()
  const auditId = Number(route.query.audit)
  if (Number.isInteger(auditId) && auditId > 0) {
    try { await showDetails(logs.value.find(log => log.id === auditId) ?? await getAuditLog(auditId)) }
    catch (error: any) { toast.error(error?.message || t('pages.audits.errors.notFound')) }
  }
})

onUnmounted(() => {
  if (auditSearchTimer) clearTimeout(auditSearchTimer)
  if (resourceSearchTimer) clearTimeout(resourceSearchTimer)
})
</script>

<template>
  <div class="audit-page">
    <div class="d-flex flex-wrap align-center ga-3 mb-5">
      <div>
        <h1 class="text-h4 mb-1">{{ $t('pages.audits.title') }}</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">{{ $t('pages.audits.subtitle') }}</p>
      </div>
    </div>

    <VTabs v-model="section" class="mb-4" color="primary">
      <VTab value="records" prepend-icon="tabler-list-check">{{ $t('pages.audits.sections.records') }}</VTab>
      <VTab value="resources" prepend-icon="tabler-adjustments-check">{{ $t('pages.audits.sections.resources') }}</VTab>
    </VTabs>

    <VWindow v-model="section">
      <VWindowItem value="records">
        <VCard :loading="loading">
          <VCardText class="audit-filters">
            <VBtnToggle v-model="auditState" mandatory density="compact" color="primary" divided class="audit-filter-toggle">
              <VBtn value="pending">{{ $t('pages.audits.filters.notAudited') }}</VBtn>
              <VBtn value="audited">{{ $t('pages.audits.filters.audited') }}</VBtn>
              <VBtn value="all">{{ $t('pages.audits.filters.all') }}</VBtn>
            </VBtnToggle>
            <VTextField v-model="auditSearch" :label="$t('pages.audits.filters.search')" prepend-inner-icon="tabler-search" density="compact" hide-details clearable />
            <VSelect v-model="resourceFilter" :label="$t('pages.audits.filters.resource')" :items="[{ value: 'all', title: $t('pages.audits.filters.allResources') }, ...resourceOptions]" density="compact" hide-details />
            <VSelect v-model="eventFilter" :label="$t('pages.audits.filters.action')" :items="[{ value: 'all', title: $t('pages.audits.filters.allActions') }, ...eventOptions]" density="compact" hide-details />
          </VCardText>
          <VDivider />

          <ResponsiveDataTableServer
            v-model:page="auditPage"
            v-model:items-per-page="auditPerPage"
            :headers="auditHeaders"
            :items="logs"
            :items-length="auditTotal"
            :loading="loading"
            item-value="id"
          >
            <template #item.event="{ item }"><VChip size="small" variant="tonal" color="primary">{{ eventLabel(item.event) }}</VChip></template>
            <template #item.record="{ item }"><strong class="audit-record">{{ recordLabel(item) }}</strong></template>
            <template #item.changes="{ item }"><span class="audit-change-summary">{{ shortChanges(item) }}</span></template>
            <template #item.changed_by="{ item }">{{ item.changed_by?.name ?? $t('pages.audits.system') }}</template>
            <template #item.created_at="{ item }">{{ formatLocalizedDate(item.created_at, 'long') }}</template>
            <template #item.audited_by="{ item }">{{ item.audited_by?.name ?? $t('pages.audits.notAuditedYet') }}</template>
            <template #item.audit_state="{ item }"><VChip size="small" :color="stateColor(item)" variant="tonal">{{ stateLabel(item) }}</VChip></template>
            <template #item.actions="{ item }">
              <IconBtn :aria-label="$t('pages.audits.actions.view')" @click="showDetails(item)"><VIcon icon="tabler-eye" /></IconBtn>
            </template>
            <template #no-data><div class="pa-10 text-center text-medium-emphasis">{{ $t('pages.audits.empty.records') }}</div></template>
          </ResponsiveDataTableServer>
        </VCard>
      </VWindowItem>

      <VWindowItem value="resources">
        <VAlert type="info" variant="tonal" class="mb-4">{{ $t('pages.audits.scopeNotice') }}</VAlert>
        <VCard :loading="resourceLoading">
          <VCardText class="audit-filters audit-resource-filters">
            <VBtnToggle v-model="resourceState" mandatory density="compact" color="primary" divided class="audit-filter-toggle">
              <VBtn value="all">{{ $t('pages.audits.filters.all') }}</VBtn>
              <VBtn value="enabled">{{ $t('pages.audits.filters.enabled') }}</VBtn>
              <VBtn value="disabled">{{ $t('pages.audits.filters.disabled') }}</VBtn>
            </VBtnToggle>
            <VTextField v-model="resourceSearch" :label="$t('pages.audits.filters.searchResources')" prepend-inner-icon="tabler-search" density="compact" hide-details clearable />
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
          >
            <template #item.resource_key="{ item }"><strong>{{ resourceName(item.resource_key) }}</strong></template>
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
            <template #item.updated_by="{ item }">{{ item.updated_by?.name ?? $t('pages.audits.system') }}</template>
            <template #item.updated_at="{ item }">{{ item.updated_at ? formatLocalizedDate(item.updated_at, 'long') : $t('common.messages.notAvailable') }}</template>
            <template #no-data><div class="pa-10 text-center text-medium-emphasis">{{ $t('pages.audits.empty.resources') }}</div></template>
          </ResponsiveDataTableServer>
        </VCard>
      </VWindowItem>
    </VWindow>

    <VDialog :model-value="detailsOpen" max-width="900" @update:model-value="value => { if (!value) closeDetails() }">
      <VCard v-if="selectedAudit">
        <VCardItem :title="recordLabel(selectedAudit)" :subtitle="`${eventLabel(selectedAudit.event)} · ${formatLocalizedDate(selectedAudit.created_at, 'long')}`">
          <template #append><IconBtn :aria-label="$t('pages.audits.actions.close')" @click="closeDetails"><VIcon icon="tabler-x" /></IconBtn></template>
        </VCardItem>
        <VDivider />
        <VCardText>
          <div class="audit-accountability mb-5">
            <div><span>{{ $t('pages.audits.columns.changedBy') }}</span><strong>{{ selectedAudit.changed_by?.name ?? $t('pages.audits.system') }}</strong></div>
            <div><span>{{ $t('pages.audits.columns.auditedBy') }}</span><strong>{{ selectedAudit.audited_by?.name ?? $t('pages.audits.notAuditedYet') }}</strong></div>
            <div><span>{{ $t('pages.audits.columns.status') }}</span><VChip size="small" :color="stateColor(selectedAudit)" variant="tonal">{{ stateLabel(selectedAudit) }}</VChip></div>
          </div>

          <VAlert v-if="selectedAudit.event === 'created'" type="success" variant="tonal">{{ $t('pages.audits.createExplanation') }}</VAlert>
          <VAlert v-else-if="!selectedAudit.changes.length" type="info" variant="tonal">{{ $t('pages.audits.noUserFacingChanges') }}</VAlert>
          <VTable v-else class="audit-comparison" density="comfortable">
            <thead><tr><th>{{ $t('pages.audits.columns.field') }}</th><th>{{ $t('pages.audits.columns.before') }}</th><th>{{ $t('pages.audits.columns.after') }}</th></tr></thead>
            <tbody><tr v-for="change in selectedAudit.changes" :key="change.field"><td><strong>{{ fieldLabel(change.field) }}</strong></td><td class="text-error">{{ formatValue(change.before, change.field) }}</td><td class="text-success">{{ formatValue(change.after, change.field) }}</td></tr></tbody>
          </VTable>
        </VCardText>
        <template v-if="selectedAudit.audit_state === 'pending' && canReview">
          <VDivider />
          <VCardActions class="justify-end pa-4">
            <VBtn v-if="selectedAudit.can_revert" color="error" variant="tonal" prepend-icon="tabler-arrow-back-up" @click="confirmDecision = 'discarded'">{{ $t('pages.audits.actions.undo') }}</VBtn>
            <VBtn color="success" prepend-icon="tabler-check" @click="confirmDecision = 'approved'">{{ $t('pages.audits.actions.keep') }}</VBtn>
          </VCardActions>
        </template>
      </VCard>
    </VDialog>

    <VDialog :model-value="Boolean(confirmDecision)" max-width="460" @update:model-value="value => { if (!value) confirmDecision = null }">
      <VCard>
        <VCardItem :title="$t(confirmDecision === 'discarded' ? 'pages.audits.confirm.undoTitle' : 'pages.audits.confirm.keepTitle')" />
        <VCardText>{{ $t(confirmDecision === 'discarded' ? 'pages.audits.confirm.undoText' : 'pages.audits.confirm.keepText') }}</VCardText>
        <VCardActions class="justify-end">
          <VBtn variant="text" @click="confirmDecision = null">{{ $t('common.actions.cancel') }}</VBtn>
          <VBtn :color="confirmDecision === 'discarded' ? 'error' : 'success'" :loading="reviewing" @click="applyDecision">{{ $t(confirmDecision === 'discarded' ? 'pages.audits.actions.undo' : 'pages.audits.actions.keep') }}</VBtn>
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
.audit-record { white-space: normal; }
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
}
</style>
