const normalizeHex = (color: string) => {
  const raw = color.trim().replace('#', '').slice(0, 6)
  if (/^[0-9a-f]{3}$/i.test(raw))
    return raw.split('').map(value => value + value).join('')

  return /^[0-9a-f]{6}$/i.test(raw) ? raw : null
}

export const contrastingTextColor = (color: string) => {
  const hex = normalizeHex(color)
  if (!hex)
    return '#111827'
  const [red, green, blue] = [0, 2, 4].map(index => Number.parseInt(hex.slice(index, index + 2), 16) / 255)

  const luminance = [red, green, blue]
    .map(value => value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4)
    .reduce((total, value, index) => total + value * [0.2126, 0.7152, 0.0722][index], 0)

  return luminance > 0.36 ? '#111827' : '#ffffff'
}

export const applyAccessibleThemeColors = (theme: { colors: Record<string, string> }, colors: { navigation: string; footer: string; surface: string }) => {
  const navigationText = contrastingTextColor(colors.navigation)
  const footerText = contrastingTextColor(colors.footer)
  const surfaceText = contrastingTextColor(colors.surface)

  theme.colors.header = colors.navigation
  theme.colors.footer = colors.footer
  theme.colors.surface = colors.surface
  theme.colors['on-surface'] = surfaceText
  document.documentElement.style.setProperty('--app-navigation-color', colors.navigation)
  document.documentElement.style.setProperty('--app-navigation-on-color', navigationText)
  document.documentElement.style.setProperty('--app-footer-color', colors.footer)
  document.documentElement.style.setProperty('--app-footer-on-color', footerText)
}
