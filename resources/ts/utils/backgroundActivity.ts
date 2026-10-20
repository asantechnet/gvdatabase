/** Background reads are useful only while the user is in this online app. */
export const isBackgroundActive = () =>
  typeof document !== 'undefined'
  && document.visibilityState === 'visible'
  && document.hasFocus()
  && (typeof navigator === 'undefined' || navigator.onLine !== false)
