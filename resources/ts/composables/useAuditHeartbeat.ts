import { ref } from 'vue'
import { type AuditLog, getAuditHeartbeat } from '@/api/auditLog'
import { canAccess } from '@/utils/authorization'
import { useVisiblePolling } from '@/composables/useVisiblePolling'

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

  const canPoll = () => canAccess('view_audit_logs') || canAccess('view_team_audit_logs')

  const poll = async () => {
    if (isPolling.value || !canPoll())
      return

    isPolling.value = true
    try {
      const batch = await getAuditHeartbeat()
      if (batch.length)
        onBatch(batch)
    }
    catch {
      // A transient poll failure is retried on the next tick; the flag stays
      // raised server-side so nothing is lost.
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
