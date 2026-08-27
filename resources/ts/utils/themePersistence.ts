import { contrastingTextColor } from '@/utils/themeContrast'

export const themeColorFields = ['primary_color', 'secondary_color', 'tertiary_color'] as const

export type ThemeColorField = typeof themeColorFields[number]
export type ThemeColorPreferences = Partial<Record<ThemeColorField, string>>

const storageKeys: Record<ThemeColorField, string> = {
  primary_color: 'theme-primary-color',
  secondary_color: 'theme-secondary-color',
  tertiary_color: 'theme-tertiary-color',
}

const isThemeColor = (value: unknown): value is string =>
  typeof value === 'string' && /^#[\da-f]{6}$/i.test(value)

export function readStoredThemeColors(): ThemeColorPreferences {
  if (typeof localStorage === 'undefined')
    return {}

  try {
    return themeColorFields.reduce<ThemeColorPreferences>((colors, field) => {
      const value = localStorage.getItem(storageKeys[field])
      if (isThemeColor(value))
        colors[field] = value

      return colors
    }, {})
  }
  catch {
    return {}
  }
}

export function persistThemeColors(colors: Partial<Record<ThemeColorField, string | null | undefined>>) {
  if (typeof localStorage === 'undefined')
    return

  try {
    themeColorFields.forEach(field => {
      const value = colors[field]
      if (isThemeColor(value))
        localStorage.setItem(storageKeys[field], value)
    })
  }
  catch {
    // Theme persistence is optional when browser storage is unavailable.
  }
}

export function applyThemeColorCssVariables(colors: ThemeColorPreferences) {
  if (typeof document === 'undefined')
    return

  // Vuetify reads theme colors from its reactive theme object. This helper is
  // intentionally retained as a no-op for API callers and initial boot.
}
