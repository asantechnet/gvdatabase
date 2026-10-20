import { onUnmounted, ref } from 'vue'
import { type AuditLog, getAuditHeartbeat } from '@/api/auditLog'
import { canAccess } from '@/utils/authorization'
import { useVisiblePolling } from '@/composables/useVisiblePolling'
import { useForegroundBatch } from '@/composables/useForegroundBatch'
import { isBackgroundActive } from '@/utils/backgroundActivity'
import { getAccessToken } from '@/utils/authStorage'

/**
 * Real-time audit delivery.
 *
 * Mirrors the notification heartbeat: the backend raises a per-user
 * `unread_audit_logs` flag when an audit row is written, and this poller drains
 * it every 30s. The endpoint returns the rows after the user's cursor and then
 * clears the flag, so a change is announced exactly once.
 */

const HEARTBEAT_INTERVAL = 30_000

export function useAuditHeartbeat(onBatch: (audits: AuditLog[]) => void) {
  const isPolling = ref(false)
  let disposed = false
  onUnmounted(() => { disposed = true })
  const deliver = useForegroundBatch<AuditLog>(onBatch)

  const canPoll = () => canAccess('view_audit_logs') || canAccess('view_team_audit_logs')

  const poll = async () => {
    if (disposed || isPolling.value || !isBackgroundActive() || !canPoll())
      return

    deliver()
    isPolling.value = true
    try {
      const account = getAccessToken()
      const batch = await getAuditHeartbeat()
      deliver(batch, account)
    }
    catch {
      // Retry on a later tick. Transport failure does not prove whether the
      // server already committed delivery; never recursively retry this read.
    }
    finally {
      isPolling.value = false
    }
  }

  // Visibility-aware polling: no work while the tab is hidden, one catch-up
  // poll when it becomes visible again.
  const polling = useVisiblePolling(poll, HEARTBEAT_INTERVAL)

  return {
    start: () => {
      if (canPoll())
        polling.start()
    },
    stop: () => polling.stop(),
    poll,
    isPolling,
  }
}
