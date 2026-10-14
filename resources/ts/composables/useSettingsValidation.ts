import { nextTick, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { settingsErrorMessage, settingsFieldErrors } from '@/api/settingValidation'

/** Local errors survive section changes and bind directly to Vuetify controls. */
export function useSettingsValidation() {
  const errors = ref<Record<string, string>>({})
  const toast = useToast()
  const clear = (field?: string) => {
    if (!field) errors.value = {}
    else {
      const remaining = { ...errors.value }
      for (const key of Object.keys(remaining)) {
        if (key === field || key.startsWith(`${field}.`)) delete remaining[key]
      }
      errors.value = remaining
    }
  }
  const inputProps = (field: string) => ({
    id: field,
    name: field,
    'data-settings-field': field,
    'error-messages': Object.entries(errors.value).filter(([key]) => key === field || key.startsWith(`${field}.`)).map(([, message]) => message),
    'onUpdate:modelValue': () => clear(field),
  })
  async function capture(error: unknown, fallback: string, reveal?: (field: string) => void) {
    const received = settingsFieldErrors(error)
    errors.value = { ...errors.value, ...received }
    const first = Object.keys(received)[0]
    if (first) reveal?.(first)
    toast.error(settingsErrorMessage(error, fallback))
    await nextTick()
    if (first && typeof document !== 'undefined') {
      const element = Array.from(document.querySelectorAll<HTMLElement>('[data-settings-field]'))
        .find(candidate => [first, first.split('.')[0], first.replace(/^remove_/, '')].includes(candidate.dataset.settingsField ?? '') && candidate.getClientRects().length > 0)
      element?.scrollIntoView({ behavior: 'smooth', block: 'center' })
      const input = element?.matches('input, textarea, select') ? element : element?.querySelector<HTMLElement>('input, textarea, select, button')
      input?.focus({ preventScroll: true })
    }
  }
  return { errors, clear, inputProps, capture }
}
