import { ref } from 'vue'
import {
  type WhatsappAudience,
  type WhatsappPlaceholderField,
  type WhatsappPlaceholderGroup,
  type WhatsappTemplate,
  getWhatsappPlaceholders,
  getWhatsappTemplates,
} from '@/api/whatsappTemplates'

/**
 * WhatsApp templates, fetched once per session and shared.
 *
 * The quick-contact menu renders inside every table row, so a per-row request
 * would mean one template fetch per record on screen. A module-level cache keeps
 * the menu to a single request, and the management page invalidates it after a
 * create/update/delete so the next menu opens with the new list.
 */

let templatesPromise: Promise<WhatsappTemplate[]> | null = null
let placeholdersPromise: Promise<WhatsappPlaceholderGroup[]> | null = null

const forgetOnFailure = <T>(promise: Promise<T>, clear: () => void): Promise<T> => promise.catch((error) => {
  // Drop the failed promise so the next caller retries instead of replaying it.
  clear()
  throw error
})

const loadTemplates = (): Promise<WhatsappTemplate[]> =>
  (templatesPromise ??= forgetOnFailure(getWhatsappTemplates(), () => {
    templatesPromise = null
  }))

const loadPlaceholderGroups = (): Promise<WhatsappPlaceholderGroup[]> =>
  (placeholdersPromise ??= forgetOnFailure(getWhatsappPlaceholders(), () => {
    placeholdersPromise = null
  }))

/** Forget the cached lists; call after any template change. */
export const invalidateWhatsappTemplates = (): void => {
  templatesPromise = null
  placeholdersPromise = null
}

/** The placeholder catalogue for one audience. */
export const whatsappPlaceholderFields = async (audience: WhatsappAudience): Promise<WhatsappPlaceholderField[]> => {
  try {
    const groups = await loadPlaceholderGroups()

    return groups.find(group => group.audience === audience)?.fields ?? []
  }
  catch {
    return []
  }
}

/** Active templates for one audience — the entries a quick-contact menu offers. */
export const activeWhatsappTemplates = async (audience: WhatsappAudience): Promise<WhatsappTemplate[]> => {
  try {
    const templates = await loadTemplates()

    return templates.filter(template => template.audience === audience && template.active)
  }
  catch {
    // The menu degrades to the plain "open the chat" action.
    return []
  }
}

/**
 * Management-page view of the templates and the placeholder catalogue. Unlike
 * the menu helpers above this surfaces errors, because the page must be able to
 * tell the user that loading failed.
 */
export const useWhatsappTemplates = () => {
  const templates = ref<WhatsappTemplate[]>([])
  const placeholderGroups = ref<WhatsappPlaceholderGroup[]>([])
  const loading = ref(false)
  const error = ref(false)

  const fieldsFor = (audience: WhatsappAudience): WhatsappPlaceholderField[] =>
    placeholderGroups.value.find(group => group.audience === audience)?.fields ?? []

  const load = async () => {
    loading.value = true
    error.value = false
    try {
      // Both calls are cached, so switching subpages does not refetch.
      const [all, groups] = await Promise.all([loadTemplates(), loadPlaceholderGroups()])

      templates.value = all
      placeholderGroups.value = groups
    }
    catch {
      error.value = true
      templates.value = []
      placeholderGroups.value = []
    }
    finally {
      loading.value = false
    }
  }

  return { templates, fieldsFor, loading, error, load }
}
