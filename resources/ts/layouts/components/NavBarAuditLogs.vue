<script setup lang="ts">
import { useToast } from 'vue-toastification'
import { getAuditLog, getAuditLogs, reviewAuditLog, type AuditEvent, type AuditLog } from '@/api/auditLog'

const toast = useToast()
const userData = useCookie<{ roles?: string[] | string } | null>('userData')
const dialogOpen = ref(false)
const loading = ref(false)
const reviewingId = ref<number | null>(null)
const logs = ref<AuditLog[]>([])
const eventFilter = ref<'all' | 'created' | 'updated' | 'deleted'>('all')
const hasNewAudits = ref(false)
const selectedAudit = ref<AuditLog | null>(null)
const auditDetailsOpen = ref(false)
const lastSeenKey = 'lastSeenAuditId'

const headers = [
  { title: translate('components.navigation.navbarauditlogs.action'), key: 'event', width: '105px' },
  { title: translate('components.navigation.navbarauditlogs.record'), key: 'record' },
  { title: translate('components.navigation.navbarauditlogs.changedColumns'), key: 'changed_columns', sortable: false },
  { title: translate('common.fields.employee'), key: 'employee' },
  { title: translate('components.navigation.navbarauditlogs.when'), key: 'created_at' },
  { title: translate('components.navigation.navbarauditlogs.review'), key: 'review_status' },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' },
] as const
const comparisonHeaders = [
  { title: translate('components.navigation.navbarauditlogs.field'), key: 'field', width: '38%' },
  { title: translate('components.navigation.navbarauditlogs.value'), key: 'value' },
] as const

const isSuperAdmin = computed(() => {
  let roles = userData.value?.roles

  for (let attempt = 0; attempt < 2 && typeof roles === 'string'; attempt++) {
    try { roles = JSON.parse(roles) }
    catch { roles = [] }
  }

  return Array.isArray(roles) && roles.includes('super_admin')
})

const eventColor = (event: AuditLog['event']) => ({ created: 'success', updated: 'info', deleted: 'error', restored: 'warning' }[event] ?? 'secondary')
const reviewColor = (status: AuditLog['review_status']) => ({ pending: 'warning', approved: 'success', discarded: 'error' }[status])
const formatDate = (date: string) => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(date))

type ComparisonRow = { field: string; value: string; state: 'record' | 'new' | 'changed' }

function formatValue(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—'
  if (typeof value === 'boolean') return value ? 'Yes' : 'No'
  if (Array.isArray(value)) return value.map(formatValue).join(', ')
  if (typeof value === 'object') return Object.entries(value as Record<string, unknown>).map(([key, item]) => `${key}: ${formatValue(item)}`).join(' · ')

  return String(value)
}

function comparisonRows(log: AuditLog, side: 'before' | 'after'): ComparisonRow[] {
  const values = side === 'before' ? log.old_values : log.new_values
  if (!values || Object.keys(values).length === 0) return [{ field: 'No saved values', value: '—', state: 'record' }]

  return Object.entries(values).map(([field, value]) => ({
    field,
    value: formatValue(value),
    state: log.changed_columns.includes(field) ? side === 'before' ? 'changed' : 'new' : 'record',
  }))
}

function updateNewAuditIndicator() {
  const newestId = Math.max(0, ...logs.value.map(log => log.id))
  hasNewAudits.value = newestId > Number(localStorage.getItem(lastSeenKey) ?? 0)
}

async function loadLogs(markSeen = false) {
  loading.value = true
  try {
    const event = eventFilter.value === 'all' ? undefined : eventFilter.value as AuditEvent
    logs.value = (await getAuditLogs({ per_page: 50, event })).data
    updateNewAuditIndicator()

    if (markSeen && logs.value.length) {
      localStorage.setItem(lastSeenKey, String(Math.max(...logs.value.map(log => log.id))))
      hasNewAudits.value = false
    }
  } catch (error: any) {
    if (error?.status !== 403) toast.error(error?.message || 'Could not load audit history.')
  } finally {
    loading.value = false
  }
}

function showAuditDetails(log: AuditLog) {
  selectedAudit.value = log
  auditDetailsOpen.value = true
}

async function openAuditDialog(auditLogId?: number) {
  dialogOpen.value = true
  await loadLogs(true)

  if (!auditLogId) return

  try {
    const log = logs.value.find(item => item.id === auditLogId) ?? await getAuditLog(auditLogId)
    showAuditDetails(log)
  } catch (error: any) {
    toast.error(error?.message || 'That audit entry is no longer available.')
  }
}

function handleAuditNotification(event: Event) {
  const auditLogId = Number((event as CustomEvent<{ auditLogId?: number }>).detail?.auditLogId)
  if (Number.isInteger(auditLogId) && auditLogId > 0)
    void openAuditDialog(auditLogId)
}

async function review(log: AuditLog, decision: 'approved' | 'discarded') {
  reviewingId.value = log.id
  try {
    await reviewAuditLog(log.id, decision)
    logs.value = logs.value.filter(item => item.id !== log.id)
    toast.success(translate(decision === 'approved'
      ? 'components.navigation.navbarauditlogs.changeApproved'
      : 'components.navigation.navbarauditlogs.changeDiscarded'))
    if (decision === 'discarded') await loadLogs()
  } catch (error: any) {
    toast.error(error?.message || 'Could not review this change.')
  } finally {
    reviewingId.value = null
  }
}

watch(eventFilter, () => loadLogs())
watch(isSuperAdmin, active => { if (active) loadLogs() }, { immediate: true })

let refreshTimer: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  window.addEventListener('open-audit-log', handleAuditNotification)
  refreshTimer = setInterval(() => { if (isSuperAdmin.value) loadLogs() }, 60_000)
})
onUnmounted(() => {
  window.removeEventListener('open-audit-log', handleAuditNotification)
  if (refreshTimer) clearInterval(refreshTimer)
})
</script>

<template>
  <template v-if="isSuperAdmin">
    <VBadge :color="hasNewAudits ? 'error' : 'success'" dot bordered location="top end" offset-x="4" offset-y="4">
      <IconBtn :aria-label="$t('components.navigation.navbarauditlogs.openAuditHistory')" @click="openAuditDialog()">
        <VIcon icon="tabler-history" />
        <VTooltip activator="parent" location="bottom">{{ $t('common.messages.auditHistory') }}</VTooltip>
      </IconBtn>
    </VBadge>

    <VDialog v-model="dialogOpen" max-width="1200" width="96vw">
      <VCard :loading="loading">
        <VCardItem :title="$t('common.messages.auditHistory')" subtitle="Review saved changes, including edits and deletions.">
          <template #append>
            <div class="d-flex align-center gap-2">
              <VBtnToggle v-model="eventFilter" mandatory density="compact" color="primary" divided>
                <VBtn value="all">{{ $t('components.navigation.navbarauditlogs.all') }}</VBtn>
                <VBtn value="created">{{ $t('components.navigation.navbarauditlogs.creates') }}</VBtn>
                <VBtn value="updated">{{ $t('components.navigation.navbarauditlogs.edits') }}</VBtn>
                <VBtn value="deleted">{{ $t('components.navigation.navbarauditlogs.deletes') }}</VBtn>
              </VBtnToggle>
              <IconBtn :aria-label="$t('components.navigation.navbarauditlogs.refreshAuditHistory')" :loading="loading" @click="loadLogs">
                <VIcon icon="tabler-refresh" />
              </IconBtn>
            </div>
          </template>
        </VCardItem>

        <VDataTable :headers="headers" :items="logs" item-value="id" :loading="loading" :items-per-page="10" density="compact" class="audit-table">
          <template #item.event="{ item }">
            <VChip size="small" :color="eventColor(item.event)" variant="tonal" class="text-capitalize">{{ item.event }}</VChip>
          </template>
          <template #item.record="{ item }"><strong>{{ item.table_name }}</strong> #{{ item.auditable_id }}</template>
          <template #item.changed_columns="{ item }">
            <VChip v-for="column in item.changed_columns" :key="column" class="me-1 my-1" size="x-small" color="primary" variant="tonal">{{ column }}</VChip>
          </template>
          <template #item.employee="{ item }">{{ item.employee?.name ?? item.user?.name ?? 'System' }}</template>
          <template #item.created_at="{ item }">{{ formatDate(item.created_at) }}</template>
          <template #item.review_status="{ item }">
            <VChip size="small" :color="reviewColor(item.review_status)" variant="tonal" class="text-capitalize">{{ item.review_status }}</VChip>
          </template>
          <template #item.actions="{ item }">
            <div class="d-flex justify-end align-center gap-1">
              <VDialog max-width="920" width="94vw">
                <template #activator="{ props }"><IconBtn v-bind="props" :aria-label="$t('components.navigation.navbarauditlogs.viewSavedValues')"><VIcon icon="tabler-eye" /></IconBtn></template>
                <template #default="{ isActive }">
                  <VCard>
                    <VCardItem :title="`${item.table_name} #${item.auditable_id}`" :subtitle="`${item.event} by ${item.employee?.name ?? item.user?.name ?? 'System'}`">
                      <template #append><IconBtn @click="isActive.value = false"><VIcon icon="tabler-x" /></IconBtn></template>
                    </VCardItem>
                    <VCardText class="audit-values">
                      <div>
                        <div class="text-subtitle-2 mb-2 text-error">{{ $t('common.messages.previousValues') }}</div>
                        <VDataTable :headers="comparisonHeaders" :items="comparisonRows(item, 'before')" :items-per-page="-1" hide-default-footer density="compact" class="comparison-table">
                          <template #item.field="{ item: row }"><span :class="`comparison-${row.state}`">{{ row.field }}</span></template>
                          <template #item.value="{ item: row }"><span :class="`comparison-${row.state}`">{{ row.value }}</span></template>
                        </VDataTable>
                      </div>
                      <div>
                        <div class="text-subtitle-2 mb-2 text-success">{{ $t('common.messages.currentValues') }}</div>
                        <VDataTable :headers="comparisonHeaders" :items="comparisonRows(item, 'after')" :items-per-page="-1" hide-default-footer density="compact" class="comparison-table">
                          <template #item.field="{ item: row }"><span :class="`comparison-${row.state}`">{{ row.field }}</span></template>
                          <template #item.value="{ item: row }"><span :class="`comparison-${row.state}`">{{ row.value }}</span></template>
                        </VDataTable>
                      </div>
                    </VCardText>
                  </VCard>
                </template>
              </VDialog>
              <template v-if="item.review_status === 'pending'">
                <IconBtn :aria-label="$t('components.navigation.navbarauditlogs.approveChange')" color="success" :loading="reviewingId === item.id" @click="review(item, 'approved')"><VIcon icon="tabler-check" /></IconBtn>
                <IconBtn v-if="['updated', 'deleted'].includes(item.event)" :aria-label="$t('components.navigation.navbarauditlogs.discardChange')" color="error" :loading="reviewingId === item.id" @click="review(item, 'discarded')"><VIcon icon="tabler-x" /></IconBtn>
              </template>
            </div>
          </template>
          <template #no-data><div class="py-8 text-center text-medium-emphasis">{{ $t('components.navigation.navbarauditlogs.noAuditRecordsMatchThisFilter') }}</div></template>
        </VDataTable>

        <VDialog v-model="auditDetailsOpen" max-width="920" width="94vw">
          <VCard v-if="selectedAudit">
            <VCardItem
              :title="`${selectedAudit.table_name} #${selectedAudit.auditable_id}`"
              :subtitle="`${selectedAudit.event} by ${selectedAudit.employee?.name ?? selectedAudit.user?.name ?? 'System'}`"
            >
              <template #append><IconBtn :aria-label="$t('components.navigation.navbarauditlogs.closeAuditDetails')" @click="auditDetailsOpen = false"><VIcon icon="tabler-x" /></IconBtn></template>
            </VCardItem>
            <VCardText class="audit-values">
              <div>
                <div class="text-subtitle-2 mb-2 text-error">{{ $t('common.messages.previousValues') }}</div>
                <VDataTable :headers="comparisonHeaders" :items="comparisonRows(selectedAudit, 'before')" :items-per-page="-1" hide-default-footer density="compact" class="comparison-table">
                  <template #item.field="{ item }"><span :class="`comparison-${item.state}`">{{ item.field }}</span></template>
                  <template #item.value="{ item }"><span :class="`comparison-${item.state}`">{{ item.value }}</span></template>
                </VDataTable>
              </div>
              <div>
                <div class="text-subtitle-2 mb-2 text-success">{{ $t('common.messages.currentValues') }}</div>
                <VDataTable :headers="comparisonHeaders" :items="comparisonRows(selectedAudit, 'after')" :items-per-page="-1" hide-default-footer density="compact" class="comparison-table">
                  <template #item.field="{ item }"><span :class="`comparison-${item.state}`">{{ item.field }}</span></template>
                  <template #item.value="{ item }"><span :class="`comparison-${item.state}`">{{ item.value }}</span></template>
                </VDataTable>
              </div>
            </VCardText>
          </VCard>
        </VDialog>
      </VCard>
    </VDialog>

  </template>
</template>

<style scoped>
.audit-values { display: grid; gap: 1rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.comparison-record { color: rgb(var(--v-theme-primary)); }
.comparison-new { color: rgb(var(--v-theme-success)); font-weight: 700; }
.comparison-changed { color: rgb(var(--v-theme-error)); font-weight: 700; }
@media (max-width: 600px) { .audit-values { grid-template-columns: 1fr; } }
</style>
