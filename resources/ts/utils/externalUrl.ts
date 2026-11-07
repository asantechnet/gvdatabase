/**
 * Reduce a user-typed portal address to one canonical web link.
 *
 * Portal addresses are deliberately stored exactly as typed, so they may
 * arrive with or without a scheme, protocol-relative, or padded with spaces.
 * Only http(s) links are passed through; any other value is treated as a bare
 * web address. That keeps stored values such as `javascript:` from becoming
 * executable hrefs while still accepting every ordinary portal shape.
 */
export function externalUrl(value?: string | null): string | null {
  const trimmed = value?.trim()

  if (!trimmed)
    return null

  if (/^https?:\/\//i.test(trimmed))
    return trimmed

  if (trimmed.startsWith('//'))
    return `https:${trimmed}`

  return `https://${trimmed.replace(/^\/+/, '')}`
}
