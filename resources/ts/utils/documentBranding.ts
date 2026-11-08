import type { CSSProperties } from 'vue'

/** Keep printed documents aligned with the Settings → Appearance defaults. */
export const DEFAULT_DOCUMENT_PRIMARY = '#80027F'
export const DEFAULT_DOCUMENT_SECONDARY = '#F47E26'

const normalizeHex = (value: string | null | undefined, fallback: string): string => {
  const candidate = String(value ?? '').trim()

  if (/^#[0-9a-f]{6}$/i.test(candidate))
    return candidate

  if (/^#[0-9a-f]{3}$/i.test(candidate)) {
    const [red, green, blue] = candidate.slice(1).split('')

    return `#${red}${red}${green}${green}${blue}${blue}`
  }

  return fallback
}

function rgba(hex: string, alpha: number): string {
  const value = hex.slice(1)
  const red = Number.parseInt(value.slice(0, 2), 16)
  const green = Number.parseInt(value.slice(2, 4), 16)
  const blue = Number.parseInt(value.slice(4, 6), 16)

  return `rgba(${red}, ${green}, ${blue}, ${alpha})`
}

/**
 * CSS custom properties consumed by the five non-contract printable documents.
 * Derived tints keep the brand colour readable on a white sheet instead of
 * relying on a fixed accent palette.
 */
export function documentBrandStyle(primary?: string | null, secondary?: string | null): CSSProperties {
  const primaryHex = normalizeHex(primary, DEFAULT_DOCUMENT_PRIMARY)
  const secondaryHex = normalizeHex(secondary, DEFAULT_DOCUMENT_SECONDARY)

  return {
    '--doc-primary': primaryHex,
    '--doc-secondary': secondaryHex,
    '--doc-primary-soft': rgba(primaryHex, 0.08),
    '--doc-primary-tint': rgba(primaryHex, 0.18),
    '--doc-secondary-soft': rgba(secondaryHex, 0.12),
    '--doc-secondary-line': rgba(secondaryHex, 0.5),
  } as CSSProperties
}
