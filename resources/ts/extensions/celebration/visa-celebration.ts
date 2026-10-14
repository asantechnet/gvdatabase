/**
 * visa-celebration.ts — the visa-approval animation, as a typed module.
 *
 * Only the animation logic lives here: it builds the timeline, runs it on
 * canvas-confetti, and reports what happened. No DOM, no markup, no copy.
 * Your portal owns the screen; this owns the confetti.
 *
 *   - `intent` picks the show. 'visa-approved' is the full fireworks + poofs
 *     arc (2.4s, 196 particles on a desktop, 119 on a phone); smaller milestones
 *     get shorter, calmer shows.
 *   - `applicant` / `visa` / `study` / `locale` feed the deterministic
 *     variation (same applicant, same show — different applicants, different
 *     firework positions) and come back in the result as a ready-to-render
 *     `persona`, so the same call can drive both the confetti and the card.
 *   - Remembers that this applicant already saw this milestone, so the portal
 *     can celebrate on every visit without ever replaying it.
 *
 * Requires canvas-confetti to be loaded first (this project already has it):
 *
 *   import confetti from 'canvas-confetti';
 *   import { setConfettiLibrary, play } from './visa-celebration';
 *   setConfettiLibrary(confetti);           // or drop the <script> tag and skip this
 *
 *   await play({
 *     intent: 'visa-approved',
 *     applicant: { id: 'APP-10482', fullName: 'Amina Rahimi', shortName: 'Amina' },
 *     visa: { type: 'F-1', number: 'F1-2026-884201', decisionDate: '2026-10-12',
 *             validFrom: '2026-11-01', validUntil: '2031-10-31', country: 'DE' },
 *     study: { program: 'MSc Computer Science', institution: 'TU Berlin' },
 *     locale: 'en-GB',
 *   }).then(result => { if (result.played) revealCongratulations(result.persona); });
 *
 * React / Next.js (client component):
 *
 *   useEffect(() => {
 *     let alive = true;
 *     play({ intent: 'visa-approved', applicant, visa, locale }).then(r => {
 *       if (alive && r.played) setCelebrating(true);
 *     });
 *     return () => { alive = false; stop(); };   // stop() is safe to call always
 *   }, []);
 */

/* ------------------------------------------------------------------ types */

/** What happened to the applicant — this decides which show plays. */
export type CelebrationIntent =
  | 'visa-approved'
  | 'visa-renewed'
  | 'visa-issued'
  | 'documents-ready'
  | 'application-received'
  | 'none'

/** The three tuned shapes of the show. */
export type CelebrationPreset = 'full' | 'light' | 'poofs'

export type CelebrationReason =
  | 'celebrated'
  | 'already-celebrated'
  | 'already-playing'
  | 'intent-none'
  | 'reduced-motion'
  | 'library-missing'
  | 'no-window'
  | 'stopped'
  | 'error'

/** Where the "already celebrated" flag is kept. `memory` never persists. */
export type CelebrationStorage = 'local' | 'session' | 'memory'

export type DateLike = string | Date

export interface CelebrationApplicant {

  /** Stable id — the replay guard is keyed by this. */
  id?: string
  fullName?: string

  /** First name, if you want the card to say "Amina" instead of the full name. */
  shortName?: string
}

export interface CelebrationVisa {

  /** 'F-1', 'J-1', 'M-1', … */
  type?: string
  number?: string
  decisionDate?: DateLike
  validFrom?: DateLike
  validUntil?: DateLike

  /** Country of issue or nationality, whatever your portal shows. */
  country?: string
}

export interface CelebrationStudy {
  program?: string
  institution?: string
}

export interface VisaCelebrationInput {
  intent: CelebrationIntent
  applicant?: CelebrationApplicant
  visa?: CelebrationVisa
  study?: CelebrationStudy

  /** BCP-47 tag; used to format the dates in the returned persona. */
  locale?: string

  /**
   * Variation key for the randomised firework positions. Defaults to
   * applicant.id → visa.number → applicant.fullName. Pass your own if you want
   * the same show across applicants, or a different one per device.
   */
  seed?: string

  /* behaviour — all optional, defaults shown */
  /** Celebrate a milestone only once (default true). The intent is part of the key. */
  once?: boolean
  storage?: CelebrationStorage // 'local'
  /** One short buzz, only after the user has interacted with the page (default true). */
  haptics?: boolean

  /** Overrides the palette the intent chose. */
  colors?: string[]

  /** 0.3 – 1.6 multiplier on every particle count (default 1). */
  intensity?: number

  /** 0.6 – 1.6 multiplier on the timeline (default 1). */
  speed?: number
  zIndex?: number

  /** Skip the confetti for prefers-reduced-motion users (default true). */
  respectReducedMotion?: boolean
}

/**
 * The applicant/visa fields, formatted and ready to render. Every field is
 * optional and explicitly `| undefined`, so this compiles under both plain
 * `strict` and `exactOptionalPropertyTypes`.
 */
export interface CelebrationPersona {
  applicantId?: string | undefined
  fullName?: string | undefined
  shortName?: string | undefined
  visaType?: string | undefined
  visaNumber?: string | undefined
  country?: string | undefined
  program?: string | undefined
  institution?: string | undefined
  locale?: string | undefined
  decisionDate?: string | undefined
  validFrom?: string | undefined
  validUntil?: string | undefined
}

export interface CelebrationResult {
  intent: CelebrationIntent
  played: boolean
  reason: CelebrationReason
  preset?: CelebrationPreset

  /** Particles fired on this viewport. */
  particles?: number

  /** Length of the scripted show in ms. */
  duration?: number
  seed?: string
  persona: CelebrationPersona

  /** The storage entry that was written, when the replay guard ran. */
  celebratedKey?: string | undefined
}

export interface CelebrationStep {
  at: number
  label: string
  particleCount: number
  mobileParticleCount: number
}

export interface CelebrationTimeline {
  intent: CelebrationIntent
  preset: CelebrationPreset
  note: string
  duration: number
  particles: number
  mobileParticles: number
  steps: CelebrationStep[]
}

/** Minimal structural type for canvas-confetti — no @types dependency needed. */
export interface ConfettiOptions {
  particleCount?: number
  angle?: number
  spread?: number
  startVelocity?: number
  decay?: number
  gravity?: number
  drift?: number
  ticks?: number
  zIndex?: number
  scalar?: number
  flat?: boolean
  colors?: string[]
  origin?: { x: number; y: number }
  disableForReducedMotion?: boolean
}

export interface ConfettiLibrary {
  (options?: ConfettiOptions): Promise<unknown> | null
  reset?: () => void
}

/* ------------------------------------------------------------------ config */

interface IntentConfig {
  preset: CelebrationPreset
  intensity: number
  colors: readonly string[]
  haptics: boolean
  note: string
}

const GOLD = ['#F5B301', '#FFD54A', '#FFE9A8', '#3FB6F0', '#FFFFFF'] as const
const BLUE = ['#2F6EF7', '#7FB2FF', '#E8F0FF', '#FFFFFF'] as const
const GREEN = ['#12A05A', '#5FD39A', '#E7F7EE', '#FFFFFF'] as const
const VIOLET = ['#8E7CFF', '#C4B8FF', '#FFFFFF'] as const

const INTENTS: Record<CelebrationIntent, IntentConfig | null> = {
  // the milestone the applicant has been waiting for: full fireworks + poofs
  'visa-approved': { preset: 'full', intensity: 1, colors: GOLD, haptics: true, note: 'Fireworks + poofs + cannons + finale' },
  'visa-renewed': { preset: 'full', intensity: 0.85, colors: GOLD, haptics: true, note: 'The full show, slightly lighter' },
  'visa-issued': { preset: 'light', intensity: 1, colors: BLUE, haptics: true, note: 'Poof, three fireworks, finale' },
  'documents-ready': { preset: 'poofs', intensity: 1, colors: GREEN, haptics: true, note: 'Soft poofs only' },
  'application-received': { preset: 'poofs', intensity: 0.6, colors: VIOLET, haptics: false, note: 'One small welcome poof and a finale' },

  // a reminder or a status page — nothing to celebrate
  'none': null,
}

const MIN_INTENSITY = 0.3
const MAX_INTENSITY = 1.6
const MIN_SPEED = 0.6
const MAX_SPEED = 1.6

/** Gap between the last burst and the "show is over" result. */
const TAIL_MS = 620
const STORAGE_PREFIX = 'visa-celebration:'

/** Narrow-viewport particle factor; mirrored by describe(). */
const PHONE_SCALE = 0.6

const DEFAULTS = {
  once: true,
  storage: 'local' as CelebrationStorage,
  haptics: true,
  intensity: 1,
  speed: 1,
  zIndex: 100,
  respectReducedMotion: true,
}

/* ------------------------------------------------------------- timeline data */

interface RawStep {
  label: string
  at: number
  options: {
    particleCount: number
    spread: number
    startVelocity: number
    decay: number
    gravity: number
    ticks: number
    drift?: number
    angle?: number
    origin: { x: number | [number, number]; y: number | [number, number] }
  }
}

const PARTS: Record<string, RawStep[]> = {
  opening: [
    {
      label: 'Opening poof',
      at: 0,
      options: {
        particleCount: 54,
        spread: 360,
        startVelocity: 26,
        decay: 0.92,
        gravity: 0.95,
        ticks: 110,
        origin: { x: 0.5, y: 0.62 },
      },
    },
  ],
  sky: [
    {
      label: 'Firework left',
      at: 150,
      options: {
        particleCount: 26,
        spread: 360,
        startVelocity: 34,
        decay: 0.91,
        gravity: 0.85,
        ticks: 80,
        origin: { x: [0.16, 0.34], y: [0.16, 0.34] },
      },
    },
    {
      label: 'Firework right',
      at: 520,
      options: {
        particleCount: 26,
        spread: 360,
        startVelocity: 34,
        decay: 0.91,
        gravity: 0.85,
        ticks: 80,
        origin: { x: [0.66, 0.84], y: [0.16, 0.36] },
      },
    },
    {
      label: 'Firework centre',
      at: 900,
      options: {
        particleCount: 22,
        spread: 360,
        startVelocity: 30,
        decay: 0.91,
        gravity: 0.85,
        ticks: 80,
        origin: { x: [0.42, 0.58], y: [0.2, 0.34] },
      },
    },
  ],
  poofVolley: [240, 480, 720, 960].map((at, i): RawStep => ({
    label: `Poof ${i + 1}`,
    at,
    options: {
      particleCount: 18,
      spread: 360,
      startVelocity: 30,
      decay: 0.93,
      gravity: 0.9,
      ticks: 95,
      origin: { x: [0.15, 0.85], y: [-0.05, 0.28] },
    },
  })),
  cannons: [
    {
      label: 'Left cannon',
      at: 1280,
      options: {
        particleCount: 11,
        angle: 55,
        spread: 50,
        startVelocity: 42,
        decay: 0.9,
        gravity: 0.9,
        ticks: 90,
        origin: { x: 0, y: 0.95 },
      },
    },
    {
      label: 'Right cannon',
      at: 1280,
      options: {
        particleCount: 11,
        angle: 125,
        spread: 50,
        startVelocity: 42,
        decay: 0.9,
        gravity: 0.9,
        ticks: 90,
        origin: { x: 1, y: 0.95 },
      },
    },
  ],
  finale: [
    {
      label: 'Finale pop',
      at: 1620,
      options: {
        particleCount: 30,
        spread: 130,
        startVelocity: 46,
        decay: 0.93,
        gravity: 0.95,
        ticks: 100,
        origin: { x: 0.5, y: 0.48 },
      },
    },
    {
      label: 'Finale sparkle',
      at: 1780,
      options: {
        particleCount: 16,
        spread: 200,
        startVelocity: 34,
        decay: 0.93,
        gravity: 0.9,
        ticks: 100,
        origin: { x: [0.42, 0.58], y: 0.42 },
      },
    },
  ],
}

const PRESETS: Record<CelebrationPreset, { parts: string[]; scale: number }> = {
  full: { parts: ['opening', 'sky', 'cannons', 'finale'], scale: 1 },
  light: { parts: ['opening', 'sky', 'finale'], scale: 0.7 },
  poofs: { parts: ['opening', 'poofVolley', 'finale'], scale: 0.85 },
}

/* --------------------------------------------------------------- utilities */

function clamp(value: number, min: number, max: number): number {
  return Number.isFinite(value) ? Math.min(max, Math.max(min, value)) : min
}

function canUseWindow(): boolean {
  return typeof window !== 'undefined' && typeof document !== 'undefined'
}

/** xmur3 string hash → mulberry32 PRNG. Deterministic per applicant. */
function makeRandom(seed: string): () => number {
  let h = 1779033703 ^ seed.length
  for (let i = 0; i < seed.length; i++) {
    h = Math.imul(h ^ seed.charCodeAt(i), 3432918353)
    h = (h << 13) | (h >>> 19)
  }
  let a = (h ^= h >>> 16) >>> 0

  return function random(): number {
    a |= 0
    a = (a + 0x6D2B79F5) | 0
    let t = Math.imul(a ^ (a >>> 15), 1 | a)
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t

    return ((t ^ (t >>> 14)) >>> 0) / 4294967296
  }
}

function pick(value: number | [number, number], random: () => number): number {
  return Array.isArray(value) ? value[0] + random() * (value[1] - value[0]) : value
}

/** How busy the show is allowed to be on this screen. */
function deviceScale(): number {
  if (!canUseWindow())
    return 1
  const width = window.innerWidth || 1024
  if (width < 600)
    return PHONE_SCALE
  if (width < 900)
    return 0.8

  return 1
}

function prefersReducedMotion(): boolean {
  return canUseWindow() && typeof window.matchMedia === 'function'
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

function userHasInteracted(): boolean {
  if (typeof navigator === 'undefined')
    return false
  const activation = (navigator as Navigator & { userActivation?: { hasBeenActive?: boolean } }).userActivation

  return !activation || activation.hasBeenActive !== false
}

function formatDate(value: DateLike | undefined, locale?: string): string | undefined {
  if (value === undefined || value === null || value === '')
    return undefined
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime()))
    return undefined
  try {
    return new Intl.DateTimeFormat(locale || undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(date)
  }
  catch {
    return date.toISOString().slice(0, 10)
  }
}

function buildPersona(input: VisaCelebrationInput): CelebrationPersona {
  const applicant = input.applicant ?? {}
  const visa = input.visa ?? {}
  const study = input.study ?? {}

  const shortName = applicant.shortName
    ?? (applicant.fullName ? applicant.fullName.trim().split(/\s+/)[0] : undefined)

  return {
    applicantId: applicant.id,
    fullName: applicant.fullName,
    shortName,
    visaType: visa.type,
    visaNumber: visa.number,
    country: visa.country,
    program: study.program,
    institution: study.institution,
    locale: input.locale,
    decisionDate: formatDate(visa.decisionDate, input.locale),
    validFrom: formatDate(visa.validFrom, input.locale),
    validUntil: formatDate(visa.validUntil, input.locale),
  }
}

/* -------------------------------------------------------- library injection */

let injected: ConfettiLibrary | null = null

/**
 * Optional. Only needed when canvas-confetti is imported rather than loaded from
 * a script tag; when the global `confetti` exists it is used automatically.
 */
export function setConfettiLibrary(library: ConfettiLibrary | null): void {
  injected = library
}

function getConfetti(): ConfettiLibrary | null {
  if (injected)
    return injected
  if (typeof globalThis === 'undefined')
    return null
  const candidate = (globalThis as { confetti?: ConfettiLibrary }).confetti

  return typeof candidate === 'function' ? candidate : null
}

/* ----------------------------------------------------------- replay guard */

const memoryFlags = new Set<string>()

type IdentityInput = Pick<VisaCelebrationInput, 'applicant' | 'visa'>

function identityOf(input: IdentityInput): string | undefined {
  const applicant = input.applicant ?? {}
  const visa = input.visa ?? {}

  const raw = applicant.id || visa.number
    || (applicant.fullName ? applicant.fullName.trim().toLowerCase().replace(/\s+/g, '-') : '')

  return raw || undefined
}

/**
 * One celebration per milestone per applicant: 'visa-approved' and a later
 *  'documents-ready' each get their own show.
 */
export function celebrationKey(input: Pick<VisaCelebrationInput, 'intent' | 'applicant' | 'visa'>): string | null {
  const identity = identityOf(input)

  return identity ? `${STORAGE_PREFIX}${input.intent}:${identity}` : null
}

function storageArea(kind: CelebrationStorage): Storage | null {
  if (!canUseWindow())
    return null
  try {
    if (kind === 'session')
      return window.sessionStorage
    if (kind === 'local')
      return window.localStorage
  }
  catch {
    /* private mode can throw on access */
  }

  return null
}

function readFlag(key: string, kind: CelebrationStorage): boolean {
  const area = storageArea(kind)
  if (!area)
    return memoryFlags.has(key)
  try {
    return area.getItem(key) === '1'
  }
  catch {
    return memoryFlags.has(key)
  }
}

function writeFlag(key: string, kind: CelebrationStorage): void {
  const area = storageArea(kind)

  memoryFlags.add(key)
  if (!area)
    return
  try {
    area.setItem(key, '1')
  }
  catch {
    /* quota / blocked: the in-memory flag still stops a repeat this session */
  }
}

/** True when this applicant has already seen this milestone's show. */
export function isCelebrated(input: Pick<VisaCelebrationInput, 'intent' | 'applicant' | 'visa' | 'storage'>): boolean {
  const key = celebrationKey(input)

  return key ? readFlag(key, input.storage ?? DEFAULTS.storage) : false
}

/** Clears the flag — for a "watch it again" button or a support tool. */
export function forget(input: Pick<VisaCelebrationInput, 'intent' | 'applicant' | 'visa' | 'storage'>): void {
  const key = celebrationKey(input)
  if (!key)
    return
  memoryFlags.delete(key)

  const kind = input.storage ?? DEFAULTS.storage
  const area = storageArea(kind)
  if (!area)
    return
  try {
    area.removeItem(key)
  }
  catch {
    /* ignore */
  }
}

/* ----------------------------------------------------------------- timeline */

interface BuiltStep {
  at: number
  label: string
  options: ConfettiOptions
}

function buildTimeline(
  presetName: CelebrationPreset,
  context: { intensity: number; colors: string[]; zIndex: number; scalar: number; random: () => number },
): BuiltStep[] {
  const preset = PRESETS[presetName]
  const steps: BuiltStep[] = []

  preset.parts.forEach(partName => {
    (PARTS[partName] ?? []).forEach(step => {
      const raw = step.options

      steps.push({
        at: step.at,
        label: step.label,
        options: {
          particleCount: Math.max(1, Math.round(raw.particleCount * context.intensity)),
          spread: raw.spread,
          startVelocity: raw.startVelocity,
          decay: raw.decay,
          gravity: raw.gravity,
          ticks: raw.ticks,
          drift: raw.drift ?? 0,
          angle: raw.angle ?? 90,
          scalar: context.scalar,
          colors: context.colors,
          zIndex: context.zIndex,
          origin: { x: pick(raw.origin.x, context.random), y: pick(raw.origin.y, context.random) },
          disableForReducedMotion: true,
        },
      })
    })
  })

  return steps.sort((a, b) => a.at - b.at)
}

/**
 * The timings and particle counts for an intent, without randomness — useful for
 * docs, QA and for checking the numbers your portal tells users about.
 * Returns null for `intent: 'none'`.
 */
export function describe(intent: CelebrationIntent): CelebrationTimeline | null {
  const config = INTENTS[intent]
  if (!config)
    return null
  const preset = PRESETS[config.preset]
  const steps: CelebrationStep[] = []

  preset.parts.forEach(partName => {
    (PARTS[partName] ?? []).forEach(step => {
      const scaled = step.options.particleCount * config.intensity * preset.scale

      steps.push({
        at: step.at,
        label: step.label,
        particleCount: Math.max(1, Math.round(scaled)),
        mobileParticleCount: Math.max(1, Math.round(scaled * PHONE_SCALE)),
      })
    })
  })

  steps.sort((a, b) => a.at - b.at)

  const last = steps.reduce((latest, step) => Math.max(latest, step.at), 0)

  return {
    intent,
    preset: config.preset,
    note: config.note,
    duration: last + TAIL_MS,
    particles: steps.reduce((total, step) => total + step.particleCount, 0),
    mobileParticles: steps.reduce((total, step) => total + step.mobileParticleCount, 0),
    steps,
  }
}

/* --------------------------------------------------------------------- run */

interface RunningShow {
  timers: ReturnType<typeof setTimeout>[]
  settle: (result: CelebrationResult) => void
}

let running: RunningShow | null = null

/**
 * Play the celebration for one applicant and one intent.
 *
 * Resolves — it never rejects — so a celebration can be fired and forgotten
 * inside a page that must not break if anything goes wrong.
 */
export function play(input: VisaCelebrationInput): Promise<CelebrationResult> {
  const persona = buildPersona(input)
  const config = INTENTS[input.intent]

  if (!config)
    return Promise.resolve({ intent: input.intent, played: false, reason: 'intent-none', persona })

  const once = input.once ?? DEFAULTS.once
  const storage = input.storage ?? DEFAULTS.storage
  const key = celebrationKey(input)

  if (running)
    return Promise.resolve({ intent: input.intent, played: false, reason: 'already-playing', persona, preset: config.preset })

  if (once && key && readFlag(key, storage))
    return Promise.resolve({ intent: input.intent, played: false, reason: 'already-celebrated', persona, preset: config.preset })

  const library = getConfetti()
  if (!library) {
    console.error('[visa-celebration] canvas-confetti was not found. Load it (or call setConfettiLibrary) first.')

    return Promise.resolve({ intent: input.intent, played: false, reason: 'library-missing', persona, preset: config.preset })
  }

  const respectMotion = input.respectReducedMotion ?? DEFAULTS.respectReducedMotion
  if (respectMotion && prefersReducedMotion()) {
    // No confetti, but the milestone still counts as seen so it stays quiet.
    if (once && key)
      writeFlag(key, storage)

    return Promise.resolve({ intent: input.intent, played: false, reason: 'reduced-motion', persona, preset: config.preset })
  }

  const identity = identityOf(input)
  const seed = input.seed || identity || String(Date.now())

  const intensity = clamp(input.intensity ?? DEFAULTS.intensity, MIN_INTENSITY, MAX_INTENSITY)
    * config.intensity // the intent's own weight
    * PRESETS[config.preset].scale // the preset's weight — keeps the runtime equal to describe()
    * deviceScale()

  const speed = clamp(input.speed ?? DEFAULTS.speed, MIN_SPEED, MAX_SPEED)
  const colors = input.colors && input.colors.length ? input.colors : [...config.colors]
  const zIndex = input.zIndex ?? DEFAULTS.zIndex
  const scale = deviceScale()

  let steps: BuiltStep[]
  try {
    steps = buildTimeline(config.preset, {
      intensity,
      colors,
      zIndex,
      scalar: scale < 1 ? 0.85 : 0.95,
      random: makeRandom(seed),
    })
    library.reset?.()
  }
  catch (error) {
    console.error('[visa-celebration] could not start the show.', error)

    return Promise.resolve({ intent: input.intent, played: false, reason: 'error', persona, preset: config.preset, seed })
  }

  const particles = steps.reduce((total, step) => total + (step.options.particleCount ?? 0), 0)
  const lastAt = steps.reduce((latest, step) => Math.max(latest, step.at), 0)
  const duration = Math.round((lastAt + TAIL_MS) * speed)
  const haptics = input.haptics ?? DEFAULTS.haptics

  if (haptics && config.haptics && userHasInteracted()
    && typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
    // Gated on user activation: browsers block vibration before any interaction.
    try {
      navigator.vibrate([16, 45, 22])
    }
    catch {
      // haptics are a bonus, never a failure
    }
  }

  return new Promise<CelebrationResult>(resolve => {
    const timers = steps.map(step => setTimeout(() => {
      try {
        library(step.options)
      }
      catch (error) {
        console.error('[visa-celebration]', error)
      }
    }, Math.round(step.at * speed)))

    timers.push(setTimeout(() => {
      running = null
      if (once && key)
        writeFlag(key, storage)
      resolve({
        intent: input.intent,
        played: true,
        reason: 'celebrated',
        preset: config.preset,
        particles,
        duration,
        seed,
        persona,
        celebratedKey: once && key ? key : undefined,
      })
    }, duration))

    running = {
      timers,
      settle: result => resolve(result),
    }
  })
}

/** Cuts the show short (unmount, navigation, applicant dismissed it). Safe anytime. */
export function stop(): boolean {
  if (!running) {
    getConfetti()?.reset?.()

    return false
  }
  const current = running

  running = null
  current.timers.forEach(clearTimeout)
  getConfetti()?.reset?.()
  current.settle({ intent: 'none', played: false, reason: 'stopped', persona: {} })

  return true
}

export const intents = Object.keys(INTENTS) as CelebrationIntent[]
export const presets: CelebrationPreset[] = ['full', 'light', 'poofs']
export const defaults = DEFAULTS
