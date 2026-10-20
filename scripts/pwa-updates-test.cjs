const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const { stripTypeScriptTypes } = require('node:module')

function compile(source) {
  const exports = [...source.matchAll(/^export (?:async )?(?:function|class|const) (\w+)/gm)].map(match => match[1])
  source = source.replace(/^import .+\r?\n/gm, '')
  // Node 22.13+ can run this suite even when platform-specific dependencies
  // are unavailable. Older supported build environments use installed TS.
  if (stripTypeScriptTypes) {
    return stripTypeScriptTypes(source, { mode: 'transform', disableExperimentalWarning: true })
      .replace(/^export /gm, '')
      + exports.map(name => `\nexports.${name} = ${name};`).join('')
  }
  const ts = require('typescript')

  return ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
  }).outputText
}

// Exercise the production lifecycle with deterministic workers, channels and clocks.
function runtime() {
  let now = 0
  let sequence = 0
  const timers = new Map()
  const sandbox = {
    exports: {}, console,
    setTimeout: (callback, delay) => {
      const id = ++sequence
      timers.set(id, { at: now + delay, callback })
      return id
    },
    clearTimeout: id => timers.delete(id),
    MessageChannel: class {
      constructor() {
        this.port1 = { onmessage: null, close() {} }
        this.port2 = {
          close() {},
          postMessage: data => queueMicrotask(() => this.port1.onmessage?.({ data })),
        }
      }
    },
  }
  const code = compile(fs.readFileSync('resources/ts/utils/pwaUpdates.ts', 'utf8'))
  vm.runInNewContext(code, sandbox)
  return {
    ...sandbox.exports,
    tick(ms) {
      now += ms
      for (const [id, timer] of timers) {
        if (timer.at <= now) {
          timers.delete(id)
          timer.callback()
        }
      }
    },
  }
}

class Worker extends EventTarget {
  constructor(version, state = 'installed') {
    super()
    this.version = version
    this.state = state
    this.messages = []
  }

  postMessage(message, ports) {
    if (this.fail) throw new Error('Worker messaging failed')
    this.messages.push(message.type)
    if (message.type === 'GET_VERSION' && this.version)
      ports[0].postMessage({ version: this.version })
  }

  transition(state) {
    this.state = state
    this.dispatchEvent(new Event('statechange'))
  }
}

function fixture({ waiting = null, installing = null, firstInstall = false } = {}) {
  const runtimeApi = runtime()
  const registration = new EventTarget()
  const container = new EventTarget()
  const state = { ready: false, applying: false, reloads: 0, purges: 0, offline: 0, failures: [], checks: 0 }
  container.controller = firstInstall ? null : new Worker('A', 'activated')
  Object.assign(registration, {
    active: container.controller, waiting, installing,
    update: async () => { state.checks++ },
  })
  const updates = new runtimeApi.PwaUpdates({
    version: 'A', registration, container,
    ready: value => { state.ready = value },
    applying: value => { state.applying = value },
    offlineReady: () => { state.offline++ },
    failed: error => state.failures.push(error),
    purge: async () => { state.purges++ },
    reload: () => { state.reloads++ },
  })
  return {
    ...runtimeApi, updates, registration, container, state,
    activate(worker) {
      registration.waiting = null
      registration.installing = null
      registration.active = worker
      worker.transition('activated')
      container.controller = worker
      container.dispatchEvent(new Event('controllerchange'))
    },
  }
}

const flush = async () => { for (let i = 0; i < 12; i++) await Promise.resolve() }
const cases = []
const test = (name, run) => cases.push({ name, run })

test('unchanged server version and same-version waiting worker never prompt', async () => {
  const f = fixture({ waiting: new Worker('A') })
  await f.updates.check('A')
  await f.updates.check('A')
  await f.updates.apply()
  assert.equal(f.state.ready, false)
  assert.equal(f.state.reloads, 0)
  assert.equal(f.state.checks, 2)
})

test('API mismatch alone and installation in progress never prompt', async () => {
  const worker = new Worker('B', 'installing')
  const f = fixture({ installing: worker })
  await f.updates.check('B')
  assert.equal(f.state.ready, false)
  f.registration.installing = null
  f.registration.waiting = worker
  worker.transition('installed')
  await flush()
  assert.equal(f.state.ready, true)
})

test('stale waiting worker must match the server release', async () => {
  const f = fixture({ waiting: new Worker('B') })
  await f.updates.check('C')
  assert.equal(f.state.ready, false)
  f.registration.waiting = new Worker('C')
  await f.updates.check('C')
  assert.equal(f.state.ready, true)
  await f.updates.check('A')
  assert.equal(f.state.ready, false)
})

test('legacy worker without identity times out without a false prompt', async () => {
  const f = fixture({ waiting: new Worker(null) })
  const checked = f.updates.check('B')
  await flush()
  f.tick(3000)
  await checked
  assert.equal(f.state.ready, false)
})

test('first install claims the page without update prompt or reload', async () => {
  const worker = new Worker('A', 'installing')
  const f = fixture({ installing: worker, firstInstall: true })
  await f.updates.check('A')
  worker.transition('installed')
  assert.equal(f.state.offline, 0)
  f.activate(worker)
  await flush()
  assert.equal(f.state.offline, 1)
  assert.equal(f.state.ready, false)
  assert.equal(f.state.reloads, 0)
})

test('slow activation waits for control; repeated clicks reload only once', async () => {
  const worker = new Worker('B')
  const f = fixture({ waiting: worker })
  await f.updates.check('B')
  const applying = f.updates.apply()
  await flush()
  await f.updates.apply()
  f.tick(1500)
  await flush()
  assert.equal(f.state.reloads, 0)
  assert.equal(f.state.purges, 0)
  assert.equal(worker.messages.filter(type => type === 'SKIP_WAITING').length, 1)
  f.activate(worker)
  await applying
  assert.equal(f.state.reloads, 1)
  assert.equal(f.state.purges, 1)
  await f.updates.apply()
  assert.equal(f.state.reloads, 1)
})

test('another tab activating does not reload this tab; local click can reload', async () => {
  const worker = new Worker('B')
  const f = fixture({ waiting: worker })
  await f.updates.check('B')
  f.activate(worker)
  await flush()
  assert.equal(f.state.reloads, 0)
  assert.equal(f.state.ready, true)
  await f.updates.apply()
  assert.equal(f.state.reloads, 1)
  assert.equal(worker.messages.includes('SKIP_WAITING'), false)
})

test('activation timeout never forces reload and remains retryable', async () => {
  const worker = new Worker('B')
  const f = fixture({ waiting: worker })
  await f.updates.check('B')
  const applying = f.updates.apply()
  await flush()
  f.tick(30000)
  await applying
  assert.equal(f.state.reloads, 0)
  assert.equal(f.state.applying, false)
  assert.equal(f.state.ready, true)
  assert.equal(f.state.failures.length, 1)
  const retry = f.updates.apply()
  await flush()
  f.activate(worker)
  await retry
  assert.equal(f.state.reloads, 1)
})

test('failed worker messaging and network update do not reload or fabricate readiness', async () => {
  const worker = new Worker('B')
  worker.fail = true
  const f = fixture({ waiting: worker })
  f.registration.update = async () => { throw new Error('Network failed') }
  await f.updates.check('B')
  await f.updates.apply()
  assert.equal(f.state.ready, false)
  assert.equal(f.state.reloads, 0)
  assert.equal(f.state.failures.length, 2)
  worker.fail = false
  await f.updates.check('B')
  assert.equal(f.state.ready, true)
})

test('concurrent checks coalesce and use the latest supplied server version', async () => {
  const f = fixture({ waiting: new Worker('C') })
  let finish
  f.registration.update = () => new Promise(resolve => { finish = resolve })
  const first = f.updates.check('B')
  const second = f.updates.check('C')
  assert.equal(first, second)
  finish()
  await second
  assert.equal(f.state.ready, true)
})

test('a server version change during identity messaging cannot publish a stale prompt', async () => {
  const worker = new Worker('B')
  let respond
  worker.postMessage = (_message, ports) => { respond = () => ports[0].postMessage({ version: 'B' }) }
  const f = fixture({ waiting: worker })
  const first = f.updates.check('B')
  await flush()
  const second = f.updates.check('A')
  respond()
  await Promise.all([first, second])
  assert.equal(f.state.ready, false)
  assert.equal(f.state.reloads, 0)
})

test('worker superseded after prompt cannot be applied from a stale toast', async () => {
  const f = fixture({ waiting: new Worker('B') })
  await f.updates.check('B')
  f.registration.waiting = new Worker('C')
  await f.updates.apply()
  assert.equal(f.state.ready, false)
  assert.equal(f.state.reloads, 0)
})

test('a rollback is a valid different identity, not a numeric version comparison', async () => {
  const f = fixture({ waiting: new Worker('9.4.0+old') })
  await f.updates.check('9.4.0+old')
  assert.equal(f.state.ready, true)
})

test('control during activation waits until the worker finishes activating', async () => {
  const worker = new Worker('B')
  const f = fixture({ waiting: worker })
  await f.updates.check('B')
  const applying = f.updates.apply()
  await flush()
  worker.transition('activating')
  f.registration.waiting = null
  f.registration.active = worker
  f.container.controller = worker
  f.container.dispatchEvent(new Event('controllerchange'))
  await flush()
  assert.equal(f.state.reloads, 0)
  worker.transition('activated')
  await applying
  assert.equal(f.state.reloads, 1)
})

test('the real worker replies with its version and claims only during activation', async () => {
  const self = new EventTarget()
  const pending = []
  const deleted = []
  let claims = 0
  let skips = 0
  Object.assign(self, {
    location: new URL('https://example.test/sw.js'), __WB_MANIFEST: [],
    clients: { claim: async () => { claims++ } },
    skipWaiting: async () => { skips++ },
  })
  const strategies = class {}
  const sandbox = {
    exports: {}, self, __APP_VERSION__: 'B', Response,
    precacheAndRoute() {}, cleanupOutdatedCaches() {}, registerRoute() {}, setCatchHandler() {},
    NetworkOnly: strategies, NetworkFirst: strategies, StaleWhileRevalidate: strategies,
    CacheableResponsePlugin: strategies, ExpirationPlugin: strategies,
    caches: { keys: async () => ['api-A', 'api-B', 'pages-A', 'assets-B', 'workbox-precache-v2'],
      delete: async name => { deleted.push(name) } },
  }
  vm.runInNewContext(compile(fs.readFileSync('resources/ts/service-worker/sw.ts', 'utf8')), sandbox)
  const dispatch = (type, data, ports = []) => {
    const event = new Event(type)
    Object.assign(event, { data, ports, waitUntil(promise) {
      assert.equal(this, event, 'waitUntil must preserve its event receiver')
      pending.push(promise)
    } })
    self.dispatchEvent(event)
  }
  let reply
  dispatch('message', { type: 'GET_VERSION' }, [{ postMessage: value => { reply = value.version } }])
  assert.equal(reply, 'B')
  dispatch('message', { type: 'SKIP_WAITING' })
  await Promise.all(pending)
  assert.equal(skips, 1)
  assert.equal(claims, 0)
  dispatch('activate')
  await Promise.all(pending)
  assert.equal(claims, 1)
  assert.deepEqual(deleted.sort(), ['api-A', 'pages-A'])
  dispatch('message', { type: 'PURGE_API_CACHE' })
  await Promise.all(pending)
  assert.ok(deleted.includes('api-B'))
})

test('version composable checks on mount and repeated visible polls, coalesces and skips offline', async () => {
  let mounted
  let poll
  let calls = 0
  let complete
  const versions = []
  const sandbox = {
    exports: {}, console, __APP_VERSION__: 'A', navigator: { onLine: true },
    ref: value => ({ value }), onMounted: callback => { mounted = callback },
    getAppVersion: () => { calls++; return new Promise(resolve => { complete = resolve }) },
    checkPwaUpdate: async version => { versions.push(version) },
    purgeApiCache: async () => { throw new Error('Mismatch must not purge before acceptance') },
    isBackgroundActive: () => sandbox.navigator.onLine,
    localStorage: { getItem: () => 'A', setItem() {} },
    useVisiblePolling: (callback, interval) => {
      assert.equal(interval, 15 * 60 * 1000)
      poll = callback
      return { start(immediate) { if (immediate) void callback() } }
    },
  }
  vm.runInNewContext(compile(fs.readFileSync('resources/ts/composables/useAppVersion.ts', 'utf8')), sandbox)
  sandbox.exports.useAppVersion()
  mounted()
  await poll()
  assert.equal(calls, 1)
  complete({ version: 'B' })
  await flush()
  const next = poll()
  complete({ version: 'B' })
  await next
  assert.deepEqual(versions, ['B', 'B'])
  sandbox.navigator.onLine = false
  await poll()
  assert.equal(calls, 2)
})

test('version API rejects malformed responses and unwraps the actual API contract', async () => {
  let payload = { version: 'B', built_at: null }
  const sandbox = { exports: {}, backgroundReadConfig: () => ({ timeout: 10000 }), api: { get: async (url, options) => {
    assert.equal(url, '/version')
    assert.equal(options.timeout, 10000)
    return { data: { data: payload } }
  } } }
  vm.runInNewContext(compile(fs.readFileSync('resources/ts/api/appVersion.ts', 'utf8')), sandbox)
  assert.equal((await sandbox.exports.getAppVersion()).version, 'B')
  for (const invalid of [null, {}, { version: 12 }, { version: ' ' }]) {
    payload = invalid
    await assert.rejects(sandbox.exports.getAppVersion(), /Invalid deployed app version/)
  }
})

async function main() {
  for (const { name, run } of cases) {
    await run()
    console.log(`PASS ${name}`)
  }
  console.log(`${cases.length} PWA update lifecycle tests passed.`)
}

main().catch(error => { console.error(error); process.exitCode = 1 })
