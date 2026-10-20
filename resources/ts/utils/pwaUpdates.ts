/** Update lifecycle shared by the PWA plugin and regression tests. */
interface UpdateOptions {
  version: string
  registration: ServiceWorkerRegistration
  container: ServiceWorkerContainer
  ready: (ready: boolean) => void
  applying: (applying: boolean) => void
  offlineReady: () => void
  failed: (error: unknown) => void
  purge: () => Promise<void>
  reload: () => void
}

const MESSAGE_TIMEOUT = 3000
const ACTIVATION_TIMEOUT = 30000

export function workerVersion(worker: ServiceWorker): Promise<string | null> {
  return new Promise(resolve => {
    const channel = new MessageChannel()
    const finish = (version: string | null) => {
      clearTimeout(timer)
      channel.port1.close()
      channel.port2.close()
      resolve(version)
    }
    const timer = setTimeout(() => finish(null), MESSAGE_TIMEOUT)

    channel.port1.onmessage = event => {
      const version = event.data?.version

      finish(typeof version === 'string' && version.length > 0 ? version : null)
    }

    try {
      worker.postMessage({ type: 'GET_VERSION' }, [channel.port2])
    }
    catch {
      finish(null)
    }
  })
}

export class PwaUpdates {
  private serverVersion: string | null = null
  private candidate: ServiceWorker | null = null
  private inspection = 0
  private checking: Promise<void> | null = null
  private applying = false
  private reloaded = false
  private watched = new WeakSet<ServiceWorker>()
  private unidentified = new WeakSet<ServiceWorker>()

  constructor(private options: UpdateOptions) {
    const { registration, container } = options

    registration.addEventListener('updatefound', () => this.watchInstalling())
    container.addEventListener('controllerchange', () => { void this.inspect() })
    this.watchInstalling()
    if (registration.active?.state === 'activated')
      options.offlineReady()
  }

  private watchInstalling() {
    const worker = this.options.registration.installing
    if (!worker || this.watched.has(worker))
      return

    this.watched.add(worker)
    worker.addEventListener('statechange', () => {
      if (worker.state === 'installed' || worker.state === 'redundant' || worker.state === 'activated')
        void this.inspect()

      if (worker.state === 'activated')
        this.options.offlineReady()
    })
  }

  private currentCandidate() {
    const { registration, container } = this.options

    return registration.waiting
      ?? (registration.active === container.controller ? container.controller : null)
  }

  /** A version mismatch is evidence to check, never evidence of readiness. */
  async inspect() {
    const inspection = ++this.inspection
    this.candidate = null
    const worker = this.currentCandidate()
    const target = this.serverVersion
    const version = worker && target && target !== this.options.version
      ? await workerVersion(worker)
      : null

    if (inspection !== this.inspection)
      return

    if (worker && target && target !== this.options.version && !version && !this.unidentified.has(worker)) {
      this.unidentified.add(worker)
      this.options.failed(new Error('PWA worker did not identify its release; update prompt suppressed.'))
    }

    const ready = Boolean(worker && version === target && target === this.serverVersion && target !== this.options.version
      && target && worker === this.currentCandidate()
      && (worker.state === 'installed' || worker.state === 'activated'))

    this.candidate = ready ? worker : null
    this.options.ready(ready)
  }

  check(version?: string): Promise<void> {
    if (version)
      this.serverVersion = version

    if (this.checking)
      return this.checking

    this.checking = (async () => {
      try {
        await this.options.registration.update()
        this.watchInstalling()
      }
      catch (error) {
        this.options.failed(error)
      }

      await this.inspect()
    })().finally(() => { this.checking = null })

    return this.checking
  }

  /** A click is local consent; another tab's activation never reloads us. */
  async apply(): Promise<void> {
    if (this.applying || this.reloaded)
      return

    this.applying = true
    this.options.applying(true)

    try {
      await this.inspect()
      const worker = this.candidate
      if (!worker || worker !== this.currentCandidate())
        return

      const { container } = this.options
      if (container.controller !== worker) {
        await new Promise<void>((resolve, reject) => {
          const cleanup = () => {
            clearTimeout(timer)
            container.removeEventListener('controllerchange', changed)
            worker.removeEventListener('statechange', changed)
          }
          const changed = () => {
            if (container.controller === worker && worker.state === 'activated') {
              cleanup()
              resolve()
            }
          }
          const timer = setTimeout(() => {
            cleanup()
            reject(new Error('PWA activation timed out; update can be retried.'))
          }, ACTIVATION_TIMEOUT)

          container.addEventListener('controllerchange', changed)
          worker.addEventListener('statechange', changed)
          try {
            worker.postMessage({ type: 'SKIP_WAITING' })
            changed()
          }
          catch (error) {
            cleanup()
            reject(error)
          }
        })
      }

      await this.options.purge()
      this.reloaded = true
      this.options.reload()
    }
    catch (error) {
      this.options.failed(error)
    }
    finally {
      this.applying = false
      this.options.applying(false)
      if (!this.reloaded)
        await this.inspect()
    }
  }
}
