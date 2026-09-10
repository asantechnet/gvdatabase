import { reactive } from 'vue'
import { fieldLabel } from './apiValidation'

export type FieldErrors = Record<string, string>

const state = reactive<{ errors: FieldErrors }>({ errors: {} })

const normalizeField = (field: string) => field
  .replace(/\[(\d+)\]/g, '.$1')
  .replace(/^data\./, '')

export const formErrors = state

export const replaceFormErrors = (errors: FieldErrors = {}) => {
  state.errors = Object.fromEntries(
    Object.entries(errors).map(([field, message]) => [normalizeField(field), message]),
  )
}

export const clearFormErrors = () => replaceFormErrors()

export const clearFieldError = (field?: string) => {
  if (!field)
    return

  const normalized = normalizeField(field)

  state.errors = Object.fromEntries(
    Object.entries(state.errors).filter(([key]) => key !== normalized && !key.startsWith(`${normalized}.`)),
  )
}

export const fieldErrorMessages = (field?: string): string[] => {
  if (!field)
    return []

  const normalized = normalizeField(field)
  const direct = state.errors[normalized]
  if (direct)
    return [direct]

  const nested = Object.entries(state.errors).find(([key]) => key.startsWith(`${normalized}.`))

  return nested ? [nested[1]] : []
}

export const mergeFieldErrorMessages = (field: string | undefined, existing: unknown): string[] => {
  const current = Array.isArray(existing) ? existing : existing ? [String(existing)] : []

  return [...new Set([...fieldErrorMessages(field), ...current])]
}

const normalizedText = (value: string) => value.trim().replace(/\s+/g, ' ').toLocaleLowerCase()

/**
 * Compatibility bridge for forms that still use Vuetify controls directly.
 * Shared App* controls consume the reactive store; this decorates legacy
 * controls by their name/id or visible label so every API form gets an inline
 * error while it is migrated to the shared wrappers.
 */
export const displayInlineFormErrors = async (): Promise<number> => {
  if (typeof document === 'undefined')
    return 0

  await Promise.resolve()
  document.querySelectorAll<HTMLElement>('[data-api-field-error]').forEach(element => element.remove())

  let matched = 0
  for (const [field, message] of Object.entries(state.errors)) {
    const leaf = field.split('.').filter(part => !/^\d+$/.test(part)).at(-1) ?? field
    const expectedLabels = [fieldLabel(field), fieldLabel(leaf)].map(normalizedText)
    const controls = Array.from(document.querySelectorAll<HTMLElement>('.v-input'))

    const control = controls.find(candidate => {
      const input = candidate.querySelector<HTMLElement>('input, textarea, select')
      if ([input?.getAttribute('name'), input?.id].includes(field))
        return true

      const labelScope = candidate.closest<HTMLElement>('.app-text-field, .app-select, .app-autocomplete, .app-textarea, .app-picker-field') ?? candidate
      const labels = Array.from(labelScope.querySelectorAll('label')).map(label => normalizedText(label.textContent ?? ''))

      return labels.some(label => expectedLabels.some(expected => expected && (label === expected || label.includes(expected))))
    })

    if (!control)
      continue
    matched++
    control.classList.add('v-input--error')
    control.querySelector('.v-field')?.classList.add('v-field--error')
    control.querySelector<HTMLElement>('input, textarea, select')?.setAttribute('aria-invalid', 'true')

    const details = control.querySelector<HTMLElement>('.v-input__details') ?? control
    const errorElement = document.createElement('div')

    errorElement.dataset.apiFieldError = field
    errorElement.className = 'v-messages text-error mt-1'
    errorElement.setAttribute('role', 'alert')
    errorElement.textContent = message
    details.appendChild(errorElement)

    const clear = () => {
      clearFieldError(field)
      errorElement.remove()
      control.classList.remove('v-input--error')
      control.querySelector('.v-field')?.classList.remove('v-field--error')
      control.querySelector<HTMLElement>('input, textarea, select')?.removeAttribute('aria-invalid')
    }

    control.addEventListener('input', clear, { once: true })
    control.addEventListener('change', clear, { once: true })
  }

  document.querySelector<HTMLElement>('[data-api-field-error]')?.scrollIntoView({ behavior: 'smooth', block: 'center' })

  return matched
}
