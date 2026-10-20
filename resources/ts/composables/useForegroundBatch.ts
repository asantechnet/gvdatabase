import { onUnmounted } from 'vue'
import { getAccessToken } from '@/utils/authStorage'
import { isBackgroundActive } from '@/utils/backgroundActivity'

/** Keep successful late heartbeat responses until the user returns to the app. */
export function useForegroundBatch<T extends { id: number }>(onBatch: (batch: T[]) => void) {
  const queued = new Map<number, T>()
  let owner: string | null | undefined
  let disposed = false

  onUnmounted(() => {
    disposed = true
    queued.clear()
  })

  return (batch: T[] = [], requestOwner = getAccessToken()) => {
    if (disposed)
      return

    const account = getAccessToken()
    if (owner !== account) {
      queued.clear()
      owner = account
    }
    if (requestOwner !== account)
      return

    batch.forEach(item => queued.set(item.id, item))
    if (!isBackgroundActive() || !queued.size)
      return

    const items = [...queued.values()]
    queued.clear()
    onBatch(items)
  }
}
