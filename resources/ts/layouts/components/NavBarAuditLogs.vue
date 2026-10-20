<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { getPendingAuditCount } from '@/api/auditLog'
import { canAccess } from '@/utils/authorization'
import { translate } from '@/utils/localization'
import { useVisiblePolling } from '@/composables/useVisiblePolling'
import { useAuditHeartbeat } from '@/composables/useAuditHeartbeat'
import { isBackgroundActive } from '@/utils/backgroundActivity'

const router = useRouter()
const toast = useToast()
const $t = translate
const pendingCount = ref(0)
const userData = useCookie<any>('userData')
const isSuperAdmin = computed(() => userData.value?.roles?.includes('super_admin') ?? false)
const canViewAudit = computed(() => isSuperAdmin.value || canAccess('view_audit_logs'))

async function refreshCount() {
  if (!isBackgroundActive() || !canViewAudit.value)
    return
  try { pendingCount.value = await getPendingAuditCount({ background: true }) }
  catch { /* Preserve the last successful count during background failures. */ }
}

function openAudits() {
  void router.push({ name: 'audits', query: { state: 'pending' } })
}

function handleAuditNotification(event: Event) {
  const auditLogId = Number((event as CustomEvent<{ auditLogId?: number }>).detail?.auditLogId)
  if (Number.isInteger(auditLogId) && auditLogId > 0)
    void router.push({ name: 'audits', query: { audit: String(auditLogId) } })
}

const { start: startCountRefresh } = useVisiblePolling(refreshCount, 60_000)

// Real-time channel: a raised flag delivers the new audit rows, the badge is
// refreshed from the authoritative pending count and the user gets one notice
// per batch (never per row).
const { start: startHeartbeat } = useAuditHeartbeat(batch => {
  pendingCount.value += batch.filter(audit => audit.audit_state === 'pending').length
  toast.info($t('pages.audits.realtimeBody', { count: batch.length }), { timeout: 5_000 })
})

onMounted(() => {
  window.addEventListener('open-audit-log', handleAuditNotification)
  startCountRefresh(true)
  startHeartbeat()
})
onUnmounted(() => {
  window.removeEventListener('open-audit-log', handleAuditNotification)
})
</script>

<template>
  <VBadge
    v-if="canViewAudit"
    :content="pendingCount > 99 ? '99+' : pendingCount"
    :model-value="pendingCount > 0"
    color="error"
    location="top end"
  >
    <IconBtn
      :aria-label="$t('pages.audits.openPage')"
      @click="openAudits"
    >
      <VIcon icon="tabler-history" />
      <VTooltip
        activator="parent"
        location="bottom"
      >
        {{ $t('pages.audits.openPage') }}
      </VTooltip>
    </IconBtn>
  </VBadge>
</template>

<style scoped lang="scss">
:deep(.v-badge__badge) {
  min-inline-size: 30px;
  padding-inline: 4px;
  white-space: nowrap;
}
</style>
