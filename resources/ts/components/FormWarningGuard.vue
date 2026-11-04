<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { confirmActionWarning, isActionWarningDismissed } from '@/composables/useActionWarning'

const { t } = useI18n()
const router = useRouter()
const dirtyForms = new Set<HTMLFormElement>()
const hasDirtyForms = ref(false)
const replayingButtons = new WeakSet<HTMLElement>()
let removeNavigationGuard: (() => void) | undefined

const eligibleForm = (target: EventTarget | null) => {
  const element = target instanceof Element ? target : null
  const form = element?.closest('form') as HTMLFormElement | null

  return form && (form.classList.contains('app-form') || form.classList.contains('contract-template-form') || form.closest('.app-form-dialog'))
    ? form
    : null
}

const updateDirtyState = () => {
  for (const form of dirtyForms) {
    if (!form.isConnected)
      dirtyForms.delete(form)
  }
  hasDirtyForms.value = dirtyForms.size > 0
}

const markDirty = (event: Event) => {
  const form = eligibleForm(event.target)

  if (!form)
    return
  dirtyForms.add(form)
  updateDirtyState()
}

const clearDirtyForm = (event: Event) => {
  const form = eligibleForm(event.target)

  if (form)
    dirtyForms.delete(form)
  updateDirtyState()
}

const normalized = (value?: string | null) => value?.replace(/\s+/g, ' ').trim().toLocaleLowerCase() || ''

const cancellationLabels = () => new Set([
  normalized(t('common.actions.cancel')),
  normalized(t('common.actions.close')),
  normalized(t('pages.settings.policies.policies.reset')),
])

const isCancellationButton = (button: HTMLElement) => {
  if (button.hasAttribute('data-skip-form-warning'))
    return false
  if (button.hasAttribute('data-confirm-form-cancel'))
    return true
  if (button instanceof HTMLButtonElement && button.type === 'reset')
    return true

  const labels = cancellationLabels()
  const label = normalized(button.getAttribute('aria-label') || button.getAttribute('title') || button.textContent)

  return labels.has(label)
}

const formsInActionScope = (button: HTMLElement) => {
  const ownForm = button.closest('form') as HTMLFormElement | null
  if (ownForm && dirtyForms.has(ownForm))
    return [ownForm]

  const scope = button.closest('.v-dialog, .v-navigation-drawer, .app-form-dialog, .v-card, main')

  return [...dirtyForms].filter(form => form.isConnected && scope?.contains(form))
}

const discardWarning = () => confirmActionWarning({
  key: 'discard-form-changes',
  title: t('common.actionWarnings.discardTitle'),
  message: t('common.actionWarnings.discardMessage'),
  confirmText: t('common.actionWarnings.discardConfirm'),
  cancelText: t('common.actionWarnings.keepEditing'),
  icon: 'tabler-file-alert',
  tone: 'primary',
})

const replayCancellation = async (button: HTMLElement, forms: HTMLFormElement[]) => {
  if (!await discardWarning())
    return

  forms.forEach(form => dirtyForms.delete(form))
  updateDirtyState()
  replayingButtons.add(button)
  button.click()
}

const handleClick = (event: MouseEvent) => {
  const target = event.target instanceof Element ? event.target : null
  const scrim = target?.closest('.v-overlay__scrim')

  if (scrim) {
    const overlay = scrim.closest('.v-overlay')
    const dialog = overlay?.querySelector<HTMLElement>('.v-dialog')
    if (dialog && !dialog.querySelector('[data-form-warning-dialog]')) {
      const hasDirtyForm = [...dirtyForms].some(form => form.isConnected && dialog.contains(form))
      const cancelButton = [...dialog.querySelectorAll<HTMLElement>('button, [role="button"]')].find(isCancellationButton)

      if (hasDirtyForm && cancelButton) {
        event.preventDefault()
        event.stopImmediatePropagation()
        cancelButton.click()

        return
      }
    }
  }

  const button = target?.closest('button, [role="button"]') as HTMLElement | null

  if (!button || replayingButtons.delete(button) || button.closest('[data-form-warning-dialog]') || !isCancellationButton(button))
    return

  const forms = formsInActionScope(button)
  if (!forms.length)
    return

  event.preventDefault()
  event.stopImmediatePropagation()
  replayCancellation(button, forms).catch(() => {})
}

const handleEscape = (event: KeyboardEvent) => {
  if (event.key !== 'Escape' || document.querySelector('[data-form-warning-dialog]'))
    return

  const dialogs = [...document.querySelectorAll<HTMLElement>('.v-dialog')].reverse()
  const dialog = dialogs.find(candidate => [...dirtyForms].some(form => candidate.contains(form)))
  if (!dialog)
    return

  const cancelButton = [...dialog.querySelectorAll<HTMLElement>('button, [role="button"]')].find(isCancellationButton)
  if (!cancelButton)
    return

  event.preventDefault()
  event.stopImmediatePropagation()
  cancelButton.click()
}

const handleBeforeUnload = (event: BeforeUnloadEvent) => {
  updateDirtyState()
  if (!hasDirtyForms.value || isActionWarningDismissed('discard-form-changes'))
    return

  event.preventDefault()
  event.returnValue = ''
}

onMounted(() => {
  document.addEventListener('input', markDirty, true)
  document.addEventListener('change', markDirty, true)
  document.addEventListener('submit', clearDirtyForm, true)
  document.addEventListener('app-form-pristine', clearDirtyForm, true)
  document.addEventListener('click', handleClick, true)
  document.addEventListener('keydown', handleEscape, true)
  window.addEventListener('beforeunload', handleBeforeUnload)

  removeNavigationGuard = router.beforeEach(async () => {
    updateDirtyState()
    if (!hasDirtyForms.value)
      return true
    if (!await discardWarning())
      return false

    dirtyForms.clear()
    updateDirtyState()

    return true
  })
})

onBeforeUnmount(() => {
  document.removeEventListener('input', markDirty, true)
  document.removeEventListener('change', markDirty, true)
  document.removeEventListener('submit', clearDirtyForm, true)
  document.removeEventListener('app-form-pristine', clearDirtyForm, true)
  document.removeEventListener('click', handleClick, true)
  document.removeEventListener('keydown', handleEscape, true)
  window.removeEventListener('beforeunload', handleBeforeUnload)
  removeNavigationGuard?.()
})
</script>

<template>
  <span
    class="d-none"
    aria-hidden="true"
  />
</template>
