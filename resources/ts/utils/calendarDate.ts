/** Canonical YYYY-MM-DD in the company's timezone, independent of browser timezone. */
export function calendarDate(timeZone: string, instant = new Date()): string {
  const parts = new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(instant)
  const value = (type: string) => parts.find(part => part.type === type)?.value ?? ''

  return `${value('year')}-${value('month')}-${value('day')}`
}
