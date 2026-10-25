import { contrastingTextColor } from '@/utils/themeContrast'
import { semanticThemeColors } from '@/utils/semanticThemeColors'

// These values deliberately match resources/ts/plugins/vuetify/theme.ts.
// They are the complete set of company-adjustable Vuexy core tokens.
export const themeColorDefaults = {
  primary_color: '#0F766E',
  secondary_color: '#475569',
  light_background_color: '#F5F7F4',
  light_surface_color: '#FFFEFA',
  dark_background_color: '#111313',
  dark_surface_color: '#1A1B1B',
} as const

export const themeColorFields = Object.keys(themeColorDefaults) as (keyof typeof themeColorDefaults)[]

export type ThemeColorField = typeof themeColorFields[number]
export type ThemeColorPreferences = Partial<Record<ThemeColorField, string>>

export interface MutableVuetifyTheme {
  dark: boolean
  colors: Record<string, string>
}

const storageKeys: Record<ThemeColorField, string> = {
  primary_color: 'theme-primary-color',
  secondary_color: 'theme-secondary-color',
  light_background_color: 'theme-light-background-color',
  light_surface_color: 'theme-light-surface-color',
  dark_background_color: 'theme-dark-background-color',
  dark_surface_color: 'theme-dark-surface-color',
}

export const isThemeColor = (value: unknown): value is string =>
  typeof value === 'string' && /^#[\da-f]{6}$/i.test(value)

const darkenHexColor = (color: string, amount = 0.1) => {
  const hex = color.slice(1)
  const channels = [0, 2, 4].map(index => Math.max(0, Math.round(Number.parseInt(hex.slice(index, index + 2), 16) * (1 - amount))))

  return `#${channels.map(channel => channel.toString(16).padStart(2, '0')).join('')}`.toUpperCase()
}

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
      else if (value === null)
        localStorage.removeItem(storageKeys[field])
    })

    // The static loading screen renders before Vue has mounted.
    const primary = colors.primary_color
    if (isThemeColor(primary))
      localStorage.setItem('vuexy-initial-loader-color', primary)
  }
  catch {
    // Browser storage is an optional fast-path; API values are still applied.
  }
}

/** Apply a complete or partial palette to one Vuetify light/dark definition. */
export function applyThemePalette(theme: MutableVuetifyTheme, palette: ThemeColorPreferences) {
  const editableTokens: Record<'primary_color' | 'secondary_color', string> = {
    primary_color: 'primary',
    secondary_color: 'secondary',
  }

  Object.entries(editableTokens).forEach(([field, token]) => {
    const color = palette[field as keyof typeof editableTokens]
    if (!isThemeColor(color))
      return
    theme.colors[token] = color
    theme.colors[`on-${token}`] = contrastingTextColor(color)
    theme.colors[`${token}-darken-1`] = darkenHexColor(color)
  })

  // Status colors deliberately stay fixed and are reapplied even if a user
  // still has obsolete company preferences in an old API response or storage.
  Object.entries(semanticThemeColors).forEach(([token, color]) => {
    theme.colors[token] = color
    theme.colors[`on-${token}`] = contrastingTextColor(color)
    theme.colors[`${token}-darken-1`] = darkenHexColor(color)
  })

  const backgroundField = theme.dark ? 'dark_background_color' : 'light_background_color'
  const surfaceField = theme.dark ? 'dark_surface_color' : 'light_surface_color'
  const expectedText = theme.dark ? '#ffffff' : '#111827'

  const resolveModeSurface = (color: string | undefined, fallback: string) =>
    isThemeColor(color) && contrastingTextColor(color) === expectedText ? color : fallback

  // A light surface with white foreground text (or the inverse in dark mode)
  // makes every inherited Vuetify label unreadable. Ignore stale or invalid
  // mode colors and retain the accessible application defaults instead.
  const background = resolveModeSurface(palette[backgroundField], themeColorDefaults[backgroundField])
  const surface = resolveModeSurface(palette[surfaceField], themeColorDefaults[surfaceField])

  theme.colors.background = background
  theme.colors['on-background'] = contrastingTextColor(background)
  theme.colors.surface = surface
  theme.colors['on-surface'] = contrastingTextColor(surface)

  // Keep both skin variants synchronized. Branding is loaded asynchronously,
  // after Vuexy has cached these values, so updating only background/surface
  // leaves the active skin pointing at stale colors until the next refresh.
  theme.colors['skin-default-background'] = background
  theme.colors['skin-default-surface'] = surface
  theme.colors['skin-bordered-background'] = surface
  theme.colors['skin-bordered-surface'] = surface
}

/**
 * Return a freshly allocated theme definition with the palette applied.
 * Vuetify regenerates its CSS variables reliably when a theme entry is
 * replaced; deep mutations made after mount can otherwise leave old variables
 * painted until a reload.
 */
export function themeWithPalette<T extends MutableVuetifyTheme>(theme: T, palette: ThemeColorPreferences): T {
  const updatedTheme = {
    ...theme,
    colors: { ...theme.colors },
  } as T

  applyThemePalette(updatedTheme, palette)

  return updatedTheme
}
