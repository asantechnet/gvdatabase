import { onUnmounted } from 'vue'

/**
 * Background polling that respects the Page Visibility API.
 *
 * Interval ticks only invoke the callback while `document.visibilityState` is
 * `visible`, so hidden tabs issue no HTTP requests (browsers still throttle,
 * but do not stop, timers in background tabs). When the tab becomes visible
 * again the callback runs once immediately so a poller can catch up without
 * waiting for the next interval.
 *
 * The composable never performs an initial call: call sites keep their own
 * on-mounted load before calling `start()`. Overlap safety stays with each
 * call site (`isPolling`, `requestPending` guards); repeated rapid visibility
 * flips therefore cannot produce concurrent requests.
 */
export function useVisiblePolling(callback: () => void | Promise<void>, intervalMs: number) {
  let timer: ReturnType<typeof setInterval> | undefined
  let started = false

  // Callbacks own their error handling; a failed tick is retried next time.
  const invoke = () => void callback()

  const isVisible = () => typeof document === 'undefined' || document.visibilityState === 'visible'

  const tick = () => {
    if (isVisible())
      invoke()
  }

  const handleVisibilityChange = () => {
    if (isVisible())
      invoke()
  }

  const start = () => {
    // SSR has no document and no timers to manage.
    if (started || typeof document === 'undefined')
      return

    started = true
    timer = setInterval(tick, intervalMs)
    document.addEventListener('visibilitychange', handleVisibilityChange)
  }

  const stop = () => {
    if (!started)
      return

    started = false

    if (timer) {
      clearInterval(timer)
      timer = undefined
    }

    if (typeof document !== 'undefined')
      document.removeEventListener('visibilitychange', handleVisibilityChange)
  }

  onUnmounted(stop)

  return { start, stop }
}
