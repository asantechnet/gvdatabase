import type { WhatsappAudience, WhatsappPlaceholderField } from '@/api/whatsappTemplates'

/**
 * WhatsApp message templates: audiences, formatting and rendering.
 *
 * One module owns the parts the page editor and the quick-contact menus must
 * agree on, so a template composed in the editor renders identically when it is
 * sent from a data table.
 *
 * Everything here is plain text on purpose. WhatsApp does not render HTML or
 * rich text: it supports line breaks, links, emoji and four inline markers
 * (`*bold*`, `_italic_`, `~strikethrough~`, ```monospace```). Keeping the
 * message a string — rather than HTML — is what makes "only content WhatsApp
 * displays without trouble" true by construction.
 */

/** WhatsApp's own text-body limit; the API validates the same number. */
export const WHATSAPP_MESSAGE_LIMIT = 4096

export const WHATSAPP_AUDIENCES: WhatsappAudience[] = ['visitor', 'applicant', 'employee']

/**
 * Subpage metadata per audience. Label keys and icons are written as literals
 * so the i18n audit and the icon bundler both see them; the audience value
 * decides which record type a template belongs to and who may read it.
 */
export const WHATSAPP_AUDIENCE_TABS: Record<WhatsappAudience, {
  labelKey: string
  icon: string
  /** Reading the audience's templates follows the record type's view permission. */
  permission: string
  /** Creating, editing and deleting them follows the record type's edit permission. */
  managePermission: string
}> = {
  visitor: { labelKey: 'pages.whatsapp.audiences.visitor', icon: 'tabler-user-search', permission: 'view_visitors', managePermission: 'edit_visitors' },
  applicant: { labelKey: 'pages.whatsapp.audiences.applicant', icon: 'tabler-users', permission: 'view_applicants', managePermission: 'edit_applicants' },
  employee: { labelKey: 'pages.whatsapp.audiences.employee', icon: 'tabler-users-group', permission: 'view_employees', managePermission: 'edit_employees' },
}

/** The inline markers the editor toolbar can apply, in display order. */
export const WHATSAPP_FORMAT_TOKENS: Array<{ key: string; marker: string; labelKey: string; icon: string }> = [
  { key: 'bold', marker: '*', labelKey: 'pages.whatsapp.editor.bold', icon: 'tabler-bold' },
  { key: 'italic', marker: '_', labelKey: 'pages.whatsapp.editor.italic', icon: 'tabler-italic' },
  { key: 'strikethrough', marker: '~', labelKey: 'pages.whatsapp.editor.strikethrough', icon: 'tabler-strikethrough' },
  { key: 'monospace', marker: '```', labelKey: 'pages.whatsapp.editor.monospace', icon: 'tabler-code' },
]

const PLACEHOLDER_PATTERN = /\{\{\s*([A-Za-z0-9_.]+)\s*\}\}/g

/** The digits WhatsApp's `wa.me` link expects. */
export const whatsappPhoneDigits = (phone: string | null | undefined): string => (phone ?? '').replace(/\D/g, '')

/**
 * The `wa.me` deep link. An empty message keeps the plain "open the chat"
 * link the quick-contact menu has always produced.
 */
export const buildWhatsappUrl = (phone: string | null | undefined, message?: string | null): string => {
  const digits = whatsappPhoneDigits(phone)
  const text = (message ?? '').trim()

  return text ? `https://wa.me/${digits}?text=${encodeURIComponent(text)}` : `https://wa.me/${digits}`
}

const valueAtPath = (record: unknown, path: string): string => {
  const value = path.split('.').reduce<unknown>((cursor, key) => (
    cursor && typeof cursor === 'object' ? (cursor as Record<string, unknown>)[key] : undefined
  ), record)

  return value === null || value === undefined ? '' : String(value)
}

/**
 * Fill a template's `{{token}}` placeholders from the record the message is
 * sent to. A token the audience does not declare resolves to nothing rather
 * than being sent as literal braces.
 */
export const renderWhatsappMessage = (
  message: string,
  fields: WhatsappPlaceholderField[],
  record: unknown,
): string => message.replace(PLACEHOLDER_PATTERN, (_match, token: string) => {
  const field = fields.find(item => item.token === token)

  return field ? valueAtPath(record, field.source) : ''
})

/** Every token a message uses, in order of first appearance. */
export const whatsappTemplateTokens = (message: string): string[] =>
  [...new Set([...message.matchAll(PLACEHOLDER_PATTERN)].map(match => match[1]))]

/** A sample-value record so the editor preview shows the message as it will read. */
export const whatsappSampleRecord = (fields: WhatsappPlaceholderField[]): Record<string, unknown> => {
  const record: Record<string, unknown> = {}

  for (const field of fields) {
    const segments = field.source.split('.')
    const last = segments.pop() as string
    const target = segments.reduce<Record<string, unknown>>((cursor, key) => {
      const next = (cursor[key] ??= {})

      return next as Record<string, unknown>
    }, record)

    target[last] = field.sample
  }

  return record
}

/** Toggle a selection's wrapping with one WhatsApp marker, or wrap the caret. */
export const toggleWhatsappWrapping = (
  value: string,
  start: number,
  end: number,
  marker: string,
): { value: string; start: number; end: number } => {
  const inner = value.slice(start, end)

  // Pressing the same button again unwraps, so the toolbar never needs a
  // separate "remove formatting" action.
  if (inner !== ''
    && value.slice(start - marker.length, start) === marker
    && value.slice(end, end + marker.length) === marker) {
    return {
      value: `${value.slice(0, start - marker.length)}${inner}${value.slice(end + marker.length)}`,
      start: start - marker.length,
      end: end - marker.length,
    }
  }

  return {
    value: `${value.slice(0, start)}${marker}${inner}${marker}${value.slice(end)}`,
    start: start + marker.length,
    end: end + marker.length,
  }
}

const escapeHtml = (value: string): string =>
  value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')

/** Longest marker first, so ```monospace``` is not eaten by the italic rule. */
const INLINE_MARKERS: Array<{ pattern: RegExp; tag: string }> = [
  { pattern: /```([^`\n]+)```/g, tag: 'code' },
  { pattern: /\*([^*\n]+)\*/g, tag: 'strong' },
  { pattern: /_([^_\n]+)_/g, tag: 'em' },
  { pattern: /~([^~\n]+)~/g, tag: 's' },
]

/**
 * Render a message the way WhatsApp displays it.
 *
 * The whole string is escaped before any marker is applied, and a marker whose
 * content starts or ends with whitespace is left alone — that is exactly the
 * rule WhatsApp itself applies, so the preview never claims formatting the
 * recipient will not see.
 */
export const whatsappPreviewHtml = (message: string): string =>
  INLINE_MARKERS
    .reduce(
      (html, { pattern, tag }) => html.replace(pattern, (match, inner: string) => (
        /^\s|\s$/.test(inner) ? match : `<${tag}>${inner}</${tag}>`
      )),
      escapeHtml(message),
    )
    .replace(/\n/g, '<br>')

/** Split the comma-separated labels field into the chip list the API stores. */
export const parseWhatsappLabels = (raw: string | string[]): string[] => (
  Array.isArray(raw) ? raw : raw.split(',')
)
  .map(label => label.trim())
  .filter((label, index, all) => label !== '' && all.indexOf(label) === index)
  .slice(0, 20)
