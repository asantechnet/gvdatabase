import assert from 'node:assert/strict'
import fs from 'node:fs'
import ts from 'typescript'

// Execute the real API module with controlled HTTP/auth/browser dependencies.
// No browser launch or app/database credentials are needed.
const source = fs.readFileSync('resources/ts/api/workday.ts', 'utf8')
const compiled = ts.transpileModule(source, {
  compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
}).outputText
let token = 'employee-one'
let active = false
let enabled = true
let positionCallback
let mode = 'success'
let gpsCalls = 0
const calls = []
const status = () => ({ eligible: true, state: active ? 'active' : 'not_started', attendance: null, report: null, timezone: 'Asia/Kabul' })
const http = {
  async get(url) { calls.push({ method: 'get', url }); return { data: { data: status() } } },
  async post(url, data) { calls.push({ method: 'post', url, data }); return { data: { data: status() } } },
}
const configuration = { async configuration() { return { eligible: true, office: { enabled } } } }
const module = { exports: {} }
new Function('require', 'module', 'exports', compiled)(name => {
  if (name === './axios') return { default: http }
  if (name === './attendanceGeofence') return { default: configuration }
  if (name === '@/utils/authStorage') return { getAccessToken: () => token }
  throw new Error(`Unexpected dependency ${name}`)
}, module, module.exports)
const { default: api, WorkdayLocationError } = module.exports
const position = { timestamp: Date.now(), coords: { latitude: 34.5553, longitude: 69.2075, accuracy: 8 } }
Object.defineProperty(globalThis, 'isSecureContext', { configurable: true, value: true, writable: true })
Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { geolocation: {
  getCurrentPosition(success, failure, options) {
    gpsCalls++
    assert.equal(options.maximumAge, 0)
    assert.equal(options.timeout, 15000)
    if (mode === 'deferred') positionCallback = success
    else if (mode === 'failure') failure({ code: 1 })
    else success(position)
  },
} } })

// Nothing runs at import/login time.
assert.equal(calls.length, 0)
assert.equal(gpsCalls, 0)
await api.start()
assert.deepEqual(calls.filter(call => call.method === 'post')[0], {
  method: 'post', url: '/workday/start', data: {
    latitude: position.coords.latitude, longitude: position.coords.longitude,
    accuracy_meters: 8, observed_at: position.timestamp,
  },
})
assert.equal(calls[0].url, '/workday/status') // browser cookie precedes mutation
let count = calls.filter(call => call.method === 'post').length
mode = 'failure'
await assert.rejects(api.start(), error => error instanceof WorkdayLocationError && error.reason === 'unavailable')
assert.equal(calls.filter(call => call.method === 'post').length, count)
mode = 'deferred'
const pending = api.start()
await new Promise(resolve => setImmediate(resolve))
token = 'employee-two'
positionCallback(position)
await assert.rejects(pending, error => error.reason === 'session_changed')
assert.equal(calls.filter(call => call.method === 'post').length, count)
mode = 'success'
globalThis.isSecureContext = false
await assert.rejects(api.start(), error => error.reason === 'unsupported')
assert.equal(calls.filter(call => call.method === 'post').length, count)
enabled = false
let previousGps = gpsCalls
await api.start()
assert.equal(gpsCalls, previousGps) // optional geofence requires no location permission
assert.deepEqual(calls.at(-1).data, {})
enabled = true
active = true
previousGps = gpsCalls
await api.start()
assert.equal(gpsCalls, previousGps) // repeat click can recover an already recorded check-in
assert.equal(calls.at(-1).url, '/workday/start')
await api.end({ summary: 'Done', attendanceId: 17 })
assert.deepEqual(calls.at(-1).data, { summary: 'Done', attendanceId: 17 })
assert(!fs.readFileSync('resources/ts/layouts/default.vue', 'utf8').includes('AutomaticAttendanceTracker'))
const authModule = { exports: {} }
new Function('require', 'module', 'exports', ts.transpileModule(fs.readFileSync('resources/ts/utils/apiAuthorization.ts', 'utf8'), {
  compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
}).outputText)(() => ({ canAccess: () => false }), authModule, authModule.exports)
assert.equal(authModule.exports.isApiRequestAllowed('GET', '/my-work-history/17/daily-report'), true)
assert.equal(authModule.exports.isApiRequestAllowed('POST', '/attendances/17/checkout-correction'), false)
const calendarModule = { exports: {} }
new Function('module', 'exports', ts.transpileModule(fs.readFileSync('resources/ts/utils/calendarDate.ts', 'utf8'), {
  compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
}).outputText)(calendarModule, calendarModule.exports)
assert.equal(calendarModule.exports.calendarDate('Asia/Kabul', new Date('2026-08-31T20:00:00Z')), '2026-09-01')
assert.equal(calendarModule.exports.calendarDate('America/New_York', new Date('2026-09-01T02:00:00Z')), '2026-08-31')
console.log('Attendance client regression checks passed: explicit actions, optional location, GPS errors, account switch, repeat start, report session.')
