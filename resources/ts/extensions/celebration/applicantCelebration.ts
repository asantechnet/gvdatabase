import confetti from 'canvas-confetti'
import { type CelebrationResult, play, setConfettiLibrary, stop } from './visa-celebration'

/**
 * The terminal stage that finishes a case successfully: `ApplicantStage::COMPLETED_KEY`
 * in `app/Models/ApplicantStage.php`. A case that is cancelled, rejected or withdrawn
 * also ends at a terminal stage, but it is not a completion and must not be celebrated.
 */
export const APPLICATION_COMPLETED_STAGE_KEY = 'completed'

/**
 * Above the app shell (vertical nav 1003, Vuetify overlays 2000) and above the
 * Kanban drag layer (3000), but still below the toastification container (9999).
 */
const CONFETTI_Z_INDEX = 3000

export interface ApplicantCompletionStage {
  key: string
}

export interface ApplicationCompletionCelebration {

  /** Case id — seeds the show so one applicant always gets the same firework positions. */
  caseId?: number | string | null

  /** Applicant name, carried into the returned persona. */
  fullName?: string | null

  /** `case.current_stage` exactly as `/applicant-portal` returns it. */
  stage?: ApplicantCompletionStage | null

  /** BCP-47 locale used to format the returned persona's dates. */
  locale?: string
}

/** True once the case has reached the stage that completes it. */
export function isApplicationCompleted(stage?: ApplicantCompletionStage | null): boolean {
  return stage?.key === APPLICATION_COMPLETED_STAGE_KEY
}

/**
 * Plays the visa-approval show for an applicant whose case has reached the
 * completion stage, once per launch of the applicant portal.
 *
 * The replay guard is deliberately off: the portal celebrates on every launch
 * while the case sits at the completion stage, and this call is made once per
 * page launch. Pass `once: true` through `play()` if the show should instead be
 * remembered and seen only once. Resolves with `null` when there is nothing to
 * celebrate, and never rejects, so callers can fire it and forget it.
 */
export function celebrateApplicationCompletion(input: ApplicationCompletionCelebration): Promise<CelebrationResult | null> {
  if (!isApplicationCompleted(input.stage))
    return Promise.resolve(null)

  setConfettiLibrary(confetti)

  return play({
    intent: 'visa-approved',
    applicant: {
      id: input.caseId === null || input.caseId === undefined ? undefined : String(input.caseId),
      fullName: input.fullName ?? undefined,
    },
    locale: input.locale,
    once: false,
    zIndex: CONFETTI_Z_INDEX,
  })
}

/** Cuts the show short when the portal is left mid-animation. Safe to call always. */
export function stopApplicationCelebration(): void {
  stop()
}
