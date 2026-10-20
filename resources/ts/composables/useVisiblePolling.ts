import { onUnmounted } from 'vue'
import { isBackgroundActive } from '@/utils/backgroundActivity'

/** One foreground request at a time; the next interval starts on completion. */
export function useVisiblePolling(callback: () => void | Promise<void>, intervalMs: number) {
  let timer: ReturnType<typeof setTimeout> | undefined
  let started = false
  let disposed = false
  let pending = false
  let active = false
  let suspended = false

  const eligible = () => !suspended && isBackgroundActive()
  const clear = () => {
    if (timer !== undefined)
      clearTimeout(timer)
    timer = undefined
  }
  const schedule = () => {
    clear()
    if (started && eligible())
      timer = setTimeout(() => { void run() }, intervalMs)
  }
  const run = async () => {
    if (!started || disposed || pending || !eligible())
      return

    clear()
    pending = true
    try {
      await callback()
    }
    catch (error) {
      // Polls never retry recursively or create UI errors at scheduler level.
      console.warn('[Polling] Background refresh failed; retrying on the next interval.', error)
    }
    finally {
      pending = false
      schedule()
    }
  }
  const reconcile = () => {
    const next = eligible()
    const resumed = next && !active
    active = next
    if (!next)
      clear()
    else if (resumed)
      void run()
  }
  const pause = () => {
    active = false
    clear()
  }
  const hide = () => {
    suspended = true
    pause()
  }
  const show = () => {
    suspended = false
    reconcile()
  }
  const start = (immediate = false) => {
    if (started || disposed || typeof document === 'undefined')
      return

    started = true
    suspended = false
    active = eligible()
    document.addEventListener('visibilitychange', reconcile)
    window.addEventListener('focus', reconcile)
    window.addEventListener('blur', pause)
    window.addEventListener('online', reconcile)
    window.addEventListener('offline', reconcile)
    window.addEventListener('pagehide', hide)
    window.addEventListener('pageshow', show)
    if (immediate)
      void run()
    else
      schedule()
  }
  const stop = () => {
    started = false
    active = false
    clear()
    if (typeof document === 'undefined')
      return

    document.removeEventListener('visibilitychange', reconcile)
    window.removeEventListener('focus', reconcile)
    window.removeEventListener('blur', pause)
    window.removeEventListener('online', reconcile)
    window.removeEventListener('offline', reconcile)
    window.removeEventListener('pagehide', hide)
    window.removeEventListener('pageshow', show)
  }

  onUnmounted(() => {
    disposed = true
    stop()
  })

  return { start, stop, run }
}
