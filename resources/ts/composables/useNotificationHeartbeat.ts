import { onUnmounted, ref } from 'vue'
import { type AppNotification, fetchNotificationHeartbeat } from '@/api/notification'
import { canAccess } from '@/utils/authorization'

/**
 * Real-time notification delivery.
 *
 * The backend keeps a per-user `unread_notifications` flag that is raised the
 * moment a notification is delivered. This composable polls the small heartbeat
 * endpoint every 30s; the endpoint returns the queued notifications only while
 * the flag is set and then clears it, so a batch is delivered exactly once. The
 * existing on-launch inbox load and 60s inbox refresh are untouched — heartbeat
 * items are merged into the same list.
 */

const HEARTBEAT_INTERVAL = 30_000
const DING_SOURCE = '/sounds/notification-ding.mp3'

let dingAudio: HTMLAudioElement | null = null
let launchDingPlayed = false

/** Plays the notification chime once. Autoplay policies may reject it. */
export const playNotificationDing = () => {
  if (typeof window === 'undefined')
    return

  try {
    dingAudio ??= new Audio(DING_SOURCE)
    dingAudio.currentTime = 0
    void dingAudio.play().catch(() => { /* Blocked before user interaction. */ })
  }
  catch {
    // Audio is unavailable; notifications still appear without a chime.
  }
}

/**
 * App-launch chime: fires at most once per session, and only when the user
 * already had unread notifications when the app opened (never once per item).
 */
export const playLaunchDingOnce = (hasUnread: boolean) => {
  if (launchDingPlayed || !hasUnread)
    return

  launchDingPlayed = true
  playNotificationDing()
}

export function useNotificationHeartbeat(onBatch: (notifications: AppNotification[]) => void) {
  const isPolling = ref(false)
  let timer: ReturnType<typeof setInterval> | undefined

  const poll = async () => {
    if (isPolling.value || !canAccess('view_own_notifications'))
      return

    isPolling.value = true
    try {
      const batch = await fetchNotificationHeartbeat()

      // One chime per batch, regardless of how many notifications it carries.
      if (batch.length) {
        playNotificationDing()
        onBatch(batch)
      }
    }
    catch {
      // A transient poll failure is retried on the next tick.
    }
    finally {
      isPolling.value = false
    }
  }

  const start = () => {
    if (timer || !canAccess('view_own_notifications'))
      return

    timer = setInterval(poll, HEARTBEAT_INTERVAL)
  }

  const stop = () => {
    if (timer) {
      clearInterval(timer)
      timer = undefined
    }
  }

  onUnmounted(stop)

  return { start, stop, poll, isPolling }
}
