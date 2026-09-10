import type { PlaceholderGroup } from '@/api/contractPlaceholders'

/**
 * Placeholder serialization bridge.
 *
 * In the editor a placeholder is a dedicated <span data-placeholder-key>
 * node showing a friendly "[ Label ]" chip. On save the HTML is serialized
 * to stable machine keys ("{{ employee.full_name }}"), which is what the
 * backend resolver understands and what is persisted. On load the reverse
 * mapping restores editable chips.
 */

export const PLACEHOLDER_SPAN_PATTERN = /<span[^>]*data-placeholder-key="([^"]+)"[^>]*>[\s\S]*?<\/span>/gi

/** Convert editor chips into resolvable {{ key }} tokens. */
export const serializePlaceholders = (html: string): string =>
  html.replace(PLACEHOLDER_SPAN_PATTERN, (_match, key: string) => `{{ ${key} }}`)

/** Build a flat lookup of machine key → human label from API groups. */
export const placeholderLabels = (groups: PlaceholderGroup[]): Record<string, string> => {
  const labels: Record<string, string> = {}
  for (const group of groups) {
    for (const field of group.fields)
      labels[field.key] = `${group.label} · ${field.label}`
  }

  return labels
}

/** Convert persisted {{ key }} tokens back into editable chips for the editor. */
export const hydratePlaceholders = (
  html: string,
  groups: PlaceholderGroup[],
  knownKeys?: ReadonlySet<string>,
): string => {
  const labels = placeholderLabels(groups)

  return html.replace(/\{\{\s*([\w.]+)\s*\}\}/g, (match, rawKey: string) => {
    const key = rawKey.toLowerCase()
    if (knownKeys && !knownKeys.has(key))

      // Unknown or out-of-context placeholders stay visible as raw tokens
      // so the author notices them instead of silently losing content.
      return match

    const escapedLabel = (labels[key] || key).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

    return `<span data-placeholder-key="${key}" class="contract-placeholder">${escapedLabel}</span>`
  })
}

/** Extract every placeholder token present in an HTML string. */
export const extractPlaceholderKeys = (html: string): string[] => {
  const keys = new Set<string>()
  for (const match of html.matchAll(/\{\{\s*([\w.]+)\s*\}\}/g)) keys.add(match[1].toLowerCase())
  for (const match of html.matchAll(PLACEHOLDER_SPAN_PATTERN)) keys.add(match[1].toLowerCase())

  return [...keys]
}

interface LegacyDeltaOp {
  insert?: unknown
  attributes?: Record<string, unknown>
}

interface LegacyQuillDelta {
  ops?: LegacyDeltaOp[]
}

/**
 * Legacy Quill Delta → minimal HTML. Contract content historically stored
 * either HTML strings or JSON-stringified Quill Deltas; both must remain
 * usable now that TipTap (which parses HTML natively) is the editor.
 */
export const legacyContentToHtml = (content: unknown): string => {
  if (!content)
    return ''
  if (typeof content === 'object') {
    try {
      return deltaToHtml(content as LegacyQuillDelta)
    }
    catch {
      return ''
    }
  }
  if (typeof content !== 'string')
    return ''

  const trimmed = content.trim()
  if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
    try {
      const parsed = JSON.parse(trimmed) as LegacyQuillDelta
      if (parsed?.ops?.length)
        return deltaToHtml(parsed)
    }
    catch {
      // Plain HTML that merely starts with "{" is impossible; fall through.
    }
  }

  return content
}

const escapeHtml = (value: string): string =>
  value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

const deltaToHtml = (delta: LegacyQuillDelta): string => {
  if (!delta?.ops)
    return ''

  let html = ''
  let listOpen: 'ul' | 'ol' | null = null

  const closeList = () => {
    if (listOpen) {
      html += `</${listOpen}>`
      listOpen = null
    }
  }

  for (const op of delta.ops) {
    if (typeof op.insert !== 'string')
      continue

    const attrs = op.attributes ?? {}
    const isListItem = attrs.list === 'bullet' || attrs.list === 'ordered'

    if (!isListItem && listOpen)
      closeList()

    let text = escapeHtml(op.insert)

    if (attrs.bold)
      text = `<strong>${text}</strong>`
    if (attrs.italic)
      text = `<em>${text}</em>`
    if (attrs.underline)
      text = `<u>${text}</u>`
    if (attrs.strike)
      text = `<s>${text}</s>`
    if (typeof attrs.link === 'string')
      text = `<a href="${escapeHtml(attrs.link)}" target="_blank" rel="noopener">${text}</a>`

    if (isListItem) {
      const tag: 'ul' | 'ol' = attrs.list === 'ordered' ? 'ol' : 'ul'
      if (listOpen !== tag) {
        closeList()
        html += `<${tag}><li>${text}</li>`
        listOpen = tag
      }
      else {
        html += `<li>${text}</li>`
      }
      continue
    }

    const header = typeof attrs.header === 'number' ? attrs.header : null
    if (header) {
      html += `<h${header}>${text.trim()}</h${header}>`
      continue
    }

    if (attrs.blockquote) {
      html += `<blockquote>${text.trim()}</blockquote>`
      continue
    }

    if (op.insert.includes('\n')) {
      for (const segment of op.insert.split('\n')) html += segment ? `<p>${segment}</p>` : '<br>'
      continue
    }

    html += text
  }

  closeList()

  return html
}
