<script setup lang="ts">
import { useRouter } from 'vue-router'
import { getPendingAuditCount } from '@/api/auditLog'
import { canAccess } from '@/utils/authorization'
import { translate } from '@/utils/localization'
import { useVisiblePolling } from '@/composables/useVisiblePolling'

const router = useRouter()
const $t = translate
const pendingCount = ref(0)
const userData = useCookie<any>('userData')
const isSuperAdmin = computed(() => userData.value?.roles?.includes('super_admin') ?? false)
const canViewAudit = computed(() => isSuperAdmin.value || canAccess('view_audit_logs'))

async function refreshCount() {
  if (!canViewAudit.value)
    return
  try { pendingCount.value = await getPendingAuditCount() }
  catch { pendingCount.value = 0 }
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

onMounted(() => {
  window.addEventListener('open-audit-log', handleAuditNotification)
  void refreshCount()
  startCountRefresh()
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
