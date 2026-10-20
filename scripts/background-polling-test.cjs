const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const { stripTypeScriptTypes } = require('node:module')

function compile(file) {
  let source = fs.readFileSync(file, 'utf8').replace(/^\uFEFF/, '')
  const names = [...source.matchAll(/^export (?:async )?(?:function|class|const) (\w+)/gm)].map(match => match[1])
  source = source.replace(/^import .+\r?\n/gm, '')
    .replaceAll('import.meta.env.VITE_API_BASE_URL', 'undefined')
  if (stripTypeScriptTypes) {
    return stripTypeScriptTypes(source, { mode: 'transform' })
      .replace(/^export default /gm, 'exports.default = ')
      .replace(/^export /gm, '')
      + names.map(name => `\nexports.${name} = ${name};`).join('')
  }
  const ts = require('typescript')
  return ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
  }).outputText
}

const flush = async () => { for (let i = 0; i < 20; i++) await Promise.resolve() }
const deferred = () => {
  let resolve, reject
  const promise = new Promise((yes, no) => { resolve = yes; reject = no })
  return { promise, resolve, reject }
}

function environment() {
  const state = { focused: true, token: 'user-A', warnings: [], sounds: 0 }
  const document = new EventTarget()
  document.visibilityState = 'visible'
  document.hasFocus = () => state.focused
  const window = new EventTarget()
  window.location = { href: '/applicants' }
  const navigator = { onLine: true }
  const cleanup = []
  let now = 0
  let nextId = 0
  const timers = new Map()
  const sandbox = {
    exports: {}, document, window, navigator, CustomEvent, Event, Map, Promise,
    console: { warn: (...args) => state.warnings.push(args) },
    getAccessToken: () => state.token,
    ref: value => ({ value }), onUnmounted: fn => cleanup.push(fn),
    setTimeout: (callback, delay) => {
      const id = ++nextId
      timers.set(id, { at: now + delay, callback })
      return id
    },
    clearTimeout: id => timers.delete(id),
    Audio: class {
      play() { state.sounds++; return Promise.resolve() }
    },
  }
  const load = (file, additions = {}) => {
    Object.assign(sandbox, additions)
    sandbox.exports = {}
    vm.runInNewContext(compile(file), sandbox, { filename: file })
    const result = sandbox.exports
    Object.assign(sandbox, result)
    return result
  }
  load('resources/ts/utils/backgroundActivity.ts')
  load('resources/ts/api/backgroundRead.ts')
  load('resources/ts/composables/useVisiblePolling.ts')
  load('resources/ts/composables/useForegroundBatch.ts')
  return {
    state, sandbox, document, window, navigator, timers, load,
    unmount: () => cleanup.forEach(fn => fn()),
    visibility(value) {
      document.visibilityState = value
      document.dispatchEvent(new Event('visibilitychange'))
    },
    focus(value) {
      state.focused = value
      window.dispatchEvent(new Event(value ? 'focus' : 'blur'))
    },
    online(value) {
      navigator.onLine = value
      window.dispatchEvent(new Event(value ? 'online' : 'offline'))
    },
    async tick(ms) {
      const end = now + ms
      for (;;) {
        const next = [...timers.entries()].sort((a, b) => a[1].at - b[1].at)[0]
        if (!next || next[1].at > end) break
        now = next[1].at
        timers.delete(next[0])
        next[1].callback()
        await flush()
      }
      now = end
      await flush()
    },
  }
}

const cases = []
const test = (name, run) => cases.push({ name, run })

for (const inactive of ['hidden', 'unfocused', 'offline']) {
  test(`${inactive} initial loads/manual runs/timers do not issue requests`, async () => {
    const f = environment()
    if (inactive === 'hidden') f.visibility('hidden')
    if (inactive === 'unfocused') f.focus(false)
    if (inactive === 'offline') f.online(false)
    let requests = 0
    const polling = f.sandbox.useVisiblePolling(async () => { requests++ }, 30000)
    polling.start(true)
    await polling.run()
    await f.tick(180000)
    assert.equal(requests, 0)
    f.visibility('visible')
    f.focus(true)
    f.online(true)
    await flush()
    assert.equal(requests, 1)
    await f.tick(30000)
    assert.equal(requests, 2)
    f.unmount()
  })
}

test('rapid focus/visibility/online events coalesce, and hidden intervals are not replayed', async () => {
  const f = environment()
  let requests = 0
  const pending = deferred()
  const polling = f.sandbox.useVisiblePolling(() => { requests++; return pending.promise }, 30000)
  polling.start(true)
  f.focus(false)
  f.visibility('hidden')
  await f.tick(300000)
  f.visibility('visible')
  f.focus(true)
  f.online(true)
  f.focus(true)
  await polling.run()
  assert.equal(requests, 1)
  pending.resolve()
  await flush()
  await f.tick(29999)
  assert.equal(requests, 1)
  await f.tick(1)
  assert.equal(requests, 2)
  f.unmount()
})

test('failures retry only after the normal interval, never recursively', async () => {
  const f = environment()
  let requests = 0
  const polling = f.sandbox.useVisiblePolling(async () => { requests++; throw new Error('network') }, 30000)
  polling.start(true)
  await flush()
  assert.equal(requests, 1)
  await f.tick(29999)
  assert.equal(requests, 1)
  await f.tick(1)
  assert.equal(requests, 2)
  assert.equal(f.state.warnings.length, 2)
  f.unmount()
})

test('unmount during a request removes listeners/timers and blocks late startup', async () => {
  const f = environment()
  let requests = 0
  const pending = deferred()
  const polling = f.sandbox.useVisiblePolling(() => { requests++; return pending.promise }, 30000)
  polling.start(true)
  f.unmount()
  pending.resolve()
  await flush()
  polling.start(true)
  f.focus(false)
  f.focus(true)
  f.online(true)
  await f.tick(120000)
  assert.equal(requests, 1)
  assert.equal(f.timers.size, 0)
})

test('pagehide pauses and pageshow catches up once', async () => {
  const f = environment()
  let requests = 0
  f.sandbox.useVisiblePolling(async () => { requests++ }, 30000).start()
  f.window.dispatchEvent(new Event('pagehide'))
  await f.tick(120000)
  assert.equal(requests, 0)
  f.window.dispatchEvent(new Event('pageshow'))
  f.focus(true)
  await flush()
  assert.equal(requests, 1)
  f.unmount()
})

test('deferred batches deduplicate, wait for focus and cannot cross sessions or unmount', async () => {
  const f = environment()
  const batches = []
  const deliver = f.sandbox.useForegroundBatch(batch => batches.push(batch.map(item => item.id)))
  f.focus(false)
  deliver([{ id: 1 }, { id: 1 }, { id: 2 }], 'user-A')
  assert.equal(batches.length, 0)
  f.focus(true)
  deliver()
  deliver()
  assert.deepEqual(Array.from(batches[0]), [1, 2])
  assert.equal(batches.length, 1)
  f.focus(false)
  deliver([{ id: 3 }], 'user-A')
  f.state.token = 'user-B'
  f.focus(true)
  deliver([{ id: 4 }], 'user-A')
  deliver()
  assert.equal(batches.length, 1)
  f.unmount()
  deliver([{ id: 5 }], 'user-B')
  assert.equal(batches.length, 1)
})

for (const kind of ['notification', 'audit']) {
  test(`${kind} heartbeat finishes in flight without hidden delivery, then delivers once on return`, async () => {
    const f = environment()
    const first = deferred()
    let requests = 0
    const batches = []
    const fetch = () => { requests++; return requests === 1 ? first.promise : Promise.resolve([]) }
    const api = f.load(`resources/ts/composables/use${kind === 'audit' ? 'Audit' : 'Notification'}Heartbeat.ts`, {
      canAccess: () => true, getAuditHeartbeat: fetch, fetchNotificationHeartbeat: fetch,
    })
    const heartbeat = api[kind === 'audit' ? 'useAuditHeartbeat' : 'useNotificationHeartbeat'](batch => batches.push(batch))
    const inFlight = heartbeat.poll()
    f.focus(false)
    first.resolve([{ id: 8 }])
    await inFlight
    assert.equal(batches.length, 0)
    assert.equal(f.state.sounds, 0)
    await heartbeat.poll()
    assert.equal(requests, 1)
    f.focus(true)
    await heartbeat.poll()
    await heartbeat.poll()
    assert.equal(batches.length, 1)
    assert.equal(f.state.sounds, kind === 'notification' ? 1 : 0)
    f.unmount()
    await heartbeat.poll()
    assert.equal(requests, 3)
  })
}

function axiosFixture() {
  const f = environment()
  const events = []
  f.window.addEventListener('api-error', event => events.push(event.detail))
  const hooks = {}
  let renewals = 0
  let clears = 0
  let replay = 0
  let refresh = async () => ({ data: { data: { access_token: 'renewed' } } })
  const instance = config => {
    replay++
    return Promise.resolve().then(() => hooks.request(config))
  }
  instance.interceptors = {
    request: { use: (ok, fail) => { hooks.request = ok; hooks.requestError = fail } },
    response: { use: (ok, fail) => { hooks.response = ok; hooks.error = fail } },
  }
  class CanceledError extends Error {
    constructor(message, config) { super(message); this.config = config; this.__CANCEL__ = true }
  }
  f.load('resources/ts/api/axios.ts', {
    axios: { create: () => instance, CanceledError, isCancel: error => Boolean(error?.__CANCEL__),
      post: (...args) => { renewals++; return refresh(...args) } },
    clearAuthTokens: () => { clears++ }, getRefreshToken: () => 'refresh',
    isRememberedSession: () => true, storeAuthTokens() {}, currentLocale: () => 'en',
    validateApiRequest: () => ({}), permissionsForApiRequest: () => null, isApiRequestAllowed: () => true,
    clearFormErrors() {}, replaceFormErrors() {}, apiFieldErrors: () => ({}),
    apiErrorMessage: error => error.message || 'request failed',
  })
  const config = (background = true, method = 'get') => ({
    url: '/notifications/heartbeat', method,
    ...(background ? f.sandbox.backgroundReadConfig({ background: true }) : { headers: {} }),
  })
  const error = (request, status) => ({ config: request, message: 'ERR_NETWORK_CONNECTION',
    ...(status ? { response: { status, data: {} } } : {}) })
  return { ...f, hooks, config, error, events,
    setRefresh: callback => { refresh = callback },
    stats: () => ({ renewals, clears, replay }),
  }
}

test('dispatch guard silently cancels inactive background reads and allows manual writes', async () => {
  const f = axiosFixture()
  f.focus(false)
  let canceled
  try { await f.hooks.request(f.config()) } catch (error) { canceled = error }
  assert.equal(canceled.__CANCEL__, true)
  await assert.rejects(f.hooks.error(canceled))
  assert.equal(f.events.length, 0)
  const manual = f.config(false, 'post')
  assert.equal(await f.hooks.request(manual), manual)
})

test('background network/timeouts/HTTP/offline errors emit no toasts; manual failures still do', async () => {
  const f = axiosFixture()
  for (const status of [undefined, 403, 422, 500])
    await assert.rejects(f.hooks.error(f.error(f.config(), status)))
  f.online(false)
  await assert.rejects(f.hooks.error(f.error(f.config())))
  assert.equal(f.events.length, 0)
  f.online(true)
  await assert.rejects(f.hooks.error(f.error(f.config(false, 'post'), 422)))
  await assert.rejects(f.hooks.error(f.error(f.config(false, 'post'))))
  assert.equal(f.events.length, 2)
})

test('401 after blur never renews, and a renewal already in progress cannot replay while hidden', async () => {
  const f = axiosFixture()
  f.focus(false)
  await assert.rejects(f.hooks.error(f.error(f.config(), 401)))
  assert.equal(f.stats().renewals, 0)
  f.focus(true)
  const refresh = deferred()
  f.setRefresh(() => refresh.promise)
  const request = f.hooks.error(f.error(f.config(), 401))
  await flush()
  f.focus(false)
  refresh.resolve({ data: { data: { access_token: 'renewed' } } })
  await assert.rejects(request)
  assert.equal(f.stats().renewals, 1)
  assert.equal(f.stats().clears, 0)
  assert.equal(f.events.length, 0)
})

test('transient background token renewal failure preserves the form/session; terminal rejection clears auth', async () => {
  for (const status of [undefined, 429, 503, 401]) {
    const f = axiosFixture()
    f.setRefresh(async () => { throw status ? { response: { status } } : new Error('network') })
    await assert.rejects(f.hooks.error(f.error(f.config(), 401)))
    assert.equal(f.stats().clears, status === 401 ? 1 : 0)
    assert.equal(f.window.location.href, status === 401 ? '/login' : '/applicants')
    assert.equal(f.events.length, 0)
  }
})

test('background API contracts opt in while manual notification/count reads stay normal', async () => {
  const f = environment()
  const calls = []
  const api = { get: async (url, options) => {
    calls.push({ url, options })
    return { data: { data: url.includes('count') ? { count: 7 } : [] } }
  } }
  const notifications = f.load('resources/ts/api/notification.ts', { api })
  await notifications.fetchNotificationHeartbeat()
  await notifications.getNotifications({ limit: 20 }, { background: true })
  await notifications.getNotifications({ limit: 100 })
  const audits = f.load('resources/ts/api/auditLog.ts', { api })
  await audits.getAuditHeartbeat()
  assert.equal(await audits.getPendingAuditCount({ background: true }), 7)
  await audits.getPendingAuditCount()
  for (const index of [0, 1, 3, 4]) {
    assert.equal(calls[index].options.headers['X-Suppress-Error-Notification'], 'true')
    assert.equal(calls[index].options.headers['X-Background-Request'], 'true')
    assert.equal(calls[index].options.timeout, 10000)
  }
  assert.equal(calls[2].options.headers, undefined)
  assert.equal(calls[5].options.headers, undefined)
})

test('workday background status and version checks are quiet while manual status remains normal', async () => {
  const f = environment()
  const calls = []
  const api = { get: async (url, options) => {
    calls.push({ url, options })
    return { data: { data: url === '/version' ? { version: 'release-B' } : { state: 'active' } } }
  } }
  const workday = f.load('resources/ts/api/workday.ts', { api })
  await workday.default.status(true)
  await workday.default.status()
  const version = f.load('resources/ts/api/appVersion.ts', { api })
  await version.getAppVersion()
  for (const index of [0, 2]) {
    assert.equal(calls[index].options.headers['X-Background-Request'], 'true')
    assert.equal(calls[index].options.headers['X-Suppress-Error-Notification'], 'true')
    assert.equal(calls[index].options.timeout, 10000)
  }
  assert.equal(calls[1].options.headers, undefined)
})

async function main() {
  for (const { name, run } of cases) {
    await run()
    console.log(`PASS ${name}`)
  }
  console.log(`${cases.length} background polling regression tests passed.`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
