export interface ApiReadOptions { background?: boolean }

/** Opt-in: manual page reads and mutations keep their normal error feedback. */
export const backgroundReadConfig = (options?: ApiReadOptions) => options?.background
  ? {
      timeout: 10000,
      headers: {
        'X-Background-Request': 'true',
        'X-Suppress-Error-Notification': 'true',
      },
    }
  : {}
