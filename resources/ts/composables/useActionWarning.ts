import { reactive } from 'vue'

export type ActionWarningKey = 'discard-form-changes' | 'move-kanban-card' | `activate-template-version-${number}`

export interface ActionWarningRequest {
  key: ActionWarningKey
  title: string
  message: string
  confirmText: string
  cancelText: string
  icon?: string
}

interface QueuedWarning {
  request: ActionWarningRequest
  resolve: (confirmed: boolean) => void
}

const STORAGE_KEY = 'asantech.dismissed-action-warnings.v1'
const queue: QueuedWarning[] = []

export const actionWarningState = reactive({
  visible: false,
  rememberChoice: false,
  request: null as ActionWarningRequest | null,
})

const readDismissedWarnings = (): ActionWarningKey[] => {
  try {
    const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]')

    return Array.isArray(stored) ? stored : []
  }
  catch {
    return []
  }
}

export const isActionWarningDismissed = (key: ActionWarningKey) => readDismissedWarnings().includes(key)

const dismissPermanently = (key: ActionWarningKey) => {
  try {
    const dismissed = new Set(readDismissedWarnings())

    dismissed.add(key)
    localStorage.setItem(STORAGE_KEY, JSON.stringify([...dismissed]))
  }
  catch {
    // Storage may be unavailable in a locked-down browser. The action should
    // still continue; only the preference cannot be persisted.
  }
}

const showNext = () => {
  const next = queue[0]

  actionWarningState.request = next?.request ?? null
  actionWarningState.rememberChoice = false
  actionWarningState.visible = Boolean(next)
}

export const confirmActionWarning = (request: ActionWarningRequest): Promise<boolean> => {
  if (isActionWarningDismissed(request.key))
    return Promise.resolve(true)

  return new Promise(resolve => {
    queue.push({ request, resolve })
    if (queue.length === 1)
      showNext()
  })
}

export const resolveActionWarning = (confirmed: boolean) => {
  const current = queue.shift()

  if (!current)
    return

  if (confirmed && actionWarningState.rememberChoice)
    dismissPermanently(current.request.key)

  current.resolve(confirmed)
  showNext()
}
