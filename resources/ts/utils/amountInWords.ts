/**
 * Active-locale amount spelling for printed documents.
 *
 * The app supports English, Dari (fa-AF) and Pashto (ps-AF). Currency values
 * are handed to this utility as numbers plus the currency snapshot stored on
 * the document, so printing follows the language currently selected in the UI.
 */
export interface AmountWordsCurrency {
  code?: string | null
  decimalPlaces?: number | null
}

type Language = 'en' | 'fa' | 'ps'

interface CurrencyWords {
  major: readonly [singular: string, plural: string]
  minor: readonly [singular: string, plural: string] | null
}

const EN_ONES = [
  '', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
  'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
  'seventeen', 'eighteen', 'nineteen',
] as const

const EN_TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'] as const

const FA_ONES = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'] as const
const FA_TEENS = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'] as const
const FA_TENS = ['', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'] as const
const FA_HUNDREDS = ['', 'یکصد', 'دوصد', 'سهصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'] as const

const PS_ONES = ['', 'یو', 'دوه', 'درې', 'څلور', 'پنځه', 'شپږ', 'اووه', 'اته', 'نهه'] as const
const PS_TEENS = ['لس', 'یوولس', 'دوولس', 'دیارلس', 'څوارلس', 'پنځلس', 'شپاړس', 'اووهلس', 'اتلس', 'نولس'] as const
const PS_TENS = ['', '', 'شل', 'دېرش', 'څلوېښت', 'پنځوس', 'شپېته', 'اویا', 'اتیا', 'نوي'] as const

const ZERO: Record<Language, string> = { en: 'zero', fa: 'صفر', ps: 'صفر' }
const ONLY: Record<Language, string> = { en: 'only', fa: 'فقط', ps: 'یوازې' }
const AND: Record<Language, string> = { en: ' and ', fa: ' و ', ps: ' او ' }
const MINUS: Record<Language, string> = { en: 'minus ', fa: 'منفی ', ps: 'منفي ' }
const FRACTION: Record<Language, string> = { en: 'hundredths', fa: 'صدم', ps: 'سلنې' }

const SCALES: Record<Language, readonly string[]> = {
  en: ['', 'thousand', 'million', 'billion', 'trillion'],
  fa: ['', 'هزار', 'میلیون', 'میلیارد', 'تریلیون'],
  ps: ['', 'زره', 'میلیون', 'میلیارد', 'تریلیون'],
}

const CURRENCY_WORDS: Record<Language, Record<string, CurrencyWords>> = {
  en: {
    AFN: { major: ['Afghan afghani', 'Afghan afghanis'], minor: ['pul', 'pul'] },
    USD: { major: ['US dollar', 'US dollars'], minor: ['cent', 'cents'] },
    EUR: { major: ['euro', 'euros'], minor: ['cent', 'cents'] },
    GBP: { major: ['British pound', 'British pounds'], minor: ['penny', 'pence'] },
    PKR: { major: ['Pakistani rupee', 'Pakistani rupees'], minor: ['paisa', 'paise'] },
    IRR: { major: ['Iranian rial', 'Iranian rials'], minor: ['dinar', 'dinars'] },
    AED: { major: ['UAE dirham', 'UAE dirhams'], minor: ['fils', 'fils'] },
    SAR: { major: ['Saudi riyal', 'Saudi riyals'], minor: ['halala', 'halalas'] },
    INR: { major: ['Indian rupee', 'Indian rupees'], minor: ['paisa', 'paise'] },
    JPY: { major: ['Japanese yen', 'Japanese yen'], minor: ['sen', 'sen'] },
  },
  fa: {
    AFN: { major: ['افغانی', 'افغانی'], minor: ['پول', 'پول'] },
    USD: { major: ['دالر امریکایی', 'دالر امریکایی'], minor: ['سنت', 'سنت'] },
    EUR: { major: ['یورو', 'یورو'], minor: ['سنت', 'سنت'] },
    GBP: { major: ['پوند بریتانیا', 'پوند بریتانیا'], minor: ['پنی', 'پنی'] },
    PKR: { major: ['کلدار پاکستانی', 'کلدار پاکستانی'], minor: ['پیسې', 'پیسې'] },
    IRR: { major: ['ریال ایرانی', 'ریال ایرانی'], minor: ['دینار', 'دینار'] },
    AED: { major: ['درهم امارات', 'درهم امارات'], minor: ['فلس', 'فلس'] },
    SAR: { major: ['ریال سعودی', 'ریال سعودی'], minor: ['هلله', 'هلله'] },
    INR: { major: ['روپیه هندی', 'روپیه هندی'], minor: ['پیسې', 'پیسې'] },
    JPY: { major: ['ین جاپانی', 'ین جاپانی'], minor: ['سن', 'سن'] },
  },
  ps: {
    AFN: { major: ['افغانی', 'افغانی'], minor: ['پول', 'پول'] },
    USD: { major: ['امریکايي ډالر', 'امریکايي ډالر'], minor: ['سنت', 'سنت'] },
    EUR: { major: ['یورو', 'یورو'], minor: ['سنت', 'سنت'] },
    GBP: { major: ['برتانوي پونډ', 'برتانوي پونډ'], minor: ['پنس', 'پنس'] },
    PKR: { major: ['پاکستاني کلدار', 'پاکستاني کلدار'], minor: ['پیسې', 'پیسې'] },
    IRR: { major: ['ایراني ریال', 'ایراني ریال'], minor: ['دینار', 'دینار'] },
    AED: { major: ['اماراتي درهم', 'اماراتي درهم'], minor: ['فلس', 'فلس'] },
    SAR: { major: ['سعودي ریال', 'سعودي ریال'], minor: ['هلله', 'هلله'] },
    INR: { major: ['هندي روپۍ', 'هندي روپۍ'], minor: ['پیسې', 'پیسې'] },
    JPY: { major: ['جاپاني ین', 'جاپاني ین'], minor: ['سین', 'سین'] },
  },
}

function languageFor(locale: string): Language {
  if (locale === 'fa-AF')
    return 'fa'
  if (locale === 'ps-AF')
    return 'ps'
  return 'en'
}

function enUnder100(value: number): string {
  if (value < 20)
    return EN_ONES[value] ?? ''

  const tens = Math.floor(value / 10)
  const unit = value % 10

  return [EN_TENS[tens], unit ? EN_ONES[unit] : ''].filter(Boolean).join('-')
}

function enUnder1000(value: number): string {
  const hundreds = Math.floor(value / 100)
  const rest = value % 100
  const parts: string[] = []

  if (hundreds)
    parts.push(`${EN_ONES[hundreds]} hundred`)
  if (rest)
    parts.push(enUnder100(rest))

  return parts.join(' ')
}

function faUnder1000(value: number): string {
  const hundreds = Math.floor(value / 100)
  const rest = value % 100
  const parts: string[] = []

  if (hundreds)
    parts.push(FA_HUNDREDS[hundreds])

  if (rest < 10) {
    if (rest)
      parts.push(FA_ONES[rest])
  }
  else if (rest < 20) {
    parts.push(FA_TEENS[rest - 10])
  }
  else {
    const tens = Math.floor(rest / 10)
    const unit = rest % 10

    parts.push(FA_TENS[tens])
    if (unit)
      parts.push(FA_ONES[unit])
  }

  return parts.join(' و ')
}

function psUnder100(value: number): string {
  if (value < 10)
    return PS_ONES[value] ?? ''
  if (value < 20)
    return PS_TEENS[value - 10]
  if (value < 30)
    return `${PS_ONES[value - 20]}ویشت`

  const tens = Math.floor(value / 10)
  const unit = value % 10

  return unit ? `${PS_ONES[unit]} ${PS_TENS[tens]}` : PS_TENS[tens]
}

function psUnder1000(value: number): string {
  const hundreds = Math.floor(value / 100)
  const rest = value % 100
  const parts: string[] = []

  if (hundreds === 1)
    parts.push('سل')
  else if (hundreds)
    parts.push(`${PS_ONES[hundreds]} سوه`)

  if (rest)
    parts.push(psUnder100(rest))

  return parts.join(' او ')
}

function under1000(value: number, language: Language): string {
  if (language === 'fa')
    return faUnder1000(value)
  if (language === 'ps')
    return psUnder1000(value)

  return enUnder1000(value)
}

function integerWords(value: number, language: Language): string {
  if (value === 0)
    return ZERO[language]

  const groups: number[] = []
  let remaining = value

  while (remaining > 0) {
    groups.push(remaining % 1000)
    remaining = Math.floor(remaining / 1000)
  }

  if (groups.length > SCALES[language].length)
    return String(value)

  const parts: string[] = []

  for (let index = groups.length - 1; index >= 0; index -= 1) {
    const group = groups[index]

    if (!group)
      continue

    const words = under1000(group, language)
    parts.push(index > 0 ? `${words} ${SCALES[language][index]}` : words)
  }

  return parts.join(language === 'en' ? ' ' : language === 'fa' ? ' و ' : ' او ')
}

function fallbackCurrency(code: string, language: Language): CurrencyWords {
  const label = code || (language === 'en' ? 'currency' : language === 'fa' ? 'پول' : 'پیسې')

  return { major: [label, label], minor: null }
}

export function formatAmountInWords(
  value: number | string | null | undefined,
  locale: string,
  currency?: AmountWordsCurrency | null,
): string {
  const language = languageFor(locale)
  const numeric = Number(value)
  const absolute = Number.isFinite(numeric) ? Math.abs(numeric) : 0
  const decimals = Number(currency?.decimalPlaces ?? 2)
  // Printed documents use two fractional digits for every currency that has
  // fractional units; whole-number currencies (e.g. JPY) omit the minor unit.
  const factor = decimals <= 0 ? 1 : 100
  const total = Math.round(absolute * factor)
  const major = Math.floor(total / factor)
  const minor = total % factor
  const code = String(currency?.code ?? '').trim().toUpperCase()
  const words = CURRENCY_WORDS[language][code] ?? fallbackCurrency(code, language)
  const majorLabel = words.major[major === 1 ? 0 : 1]
  const parts = [`${integerWords(major, language)} ${majorLabel}`.trim()]

  if (minor > 0) {
    const minorLabel = words.minor?.[minor === 1 ? 0 : 1] ?? FRACTION[language]

    parts.push(`${integerWords(minor, language)} ${minorLabel}`)
  }

  const phrase = parts.join(AND[language])
  const signed = numeric < 0 ? `${MINUS[language]}${phrase}` : phrase

  return `${signed} ${ONLY[language]}`
}
