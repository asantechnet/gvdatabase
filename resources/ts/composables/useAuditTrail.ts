import { computed, ref, toValue, watch, type MaybeRefOrGetter } from 'vue'
import { getAuditRowTrails, type AuditTrailRow } from '@/api/auditTrail'
import { useBranding } from '@/composables/useBranding'
import { auditResourceOfRow } from '@/utils/auditResources'
import { canAccess } from '@/utils/authorization'

interface AuditTrailOptions {
  /** Explicit registry key for raw tables; otherwise the Axios row tag wins. */
  resource?: MaybeRefOrGetter<string | null | undefined>
  /** Page-level opt-out. The global Settings → Operations switch still gates it. */
  enabled?: MaybeRefOrGetter<boolean>
}

const firstTaggedResource = (items: Record<string, any>[]): string | null => {
  for (const item of items) {
    const resource = auditResourceOfRow(item)
    if (resource)
      return resource
  }

  return null
}

/**
 * Batch audit state for a loaded table page.
 *
 * At most one request is issued per page/filter state, stale responses are
 * ignored, and every failure degrades to "no rail" without a toast. The global
 * Settings → Operations switch and `view_audit_logs` both gate the read.
 */
export function useAuditTrail(
  items: MaybeRefOrGetter<Record<string, any>[]>,
  options: AuditTrailOptions = {},
) {
  const { auditColoringEnabled, loadBranding } = useBranding()
  const trails = ref<Record<number, AuditTrailRow>>({})
  const isLoading = ref(false)
  let requestSequence = 0

  // The public branding payload carries the display preference, so every
  // signed-in user can gate the feature without the settings permission.
  void loadBranding()

  const resource = computed(() => toValue(options.resource) ?? firstTaggedResource(toValue(items)))
  const enabled = computed(() => toValue(options.enabled) ?? true)
  const allowed = computed(() => canAccess('view_audit_logs'))

  const ids = computed(() => [...new Set(
    toValue(items)
      .map(item => Number(item?.id))
      .filter(id => Number.isInteger(id) && id > 0),
  )].slice(0, 100))

  const signature = computed(() => `${resource.value ?? ''}:${ids.value.join(',')}`)

  const load = async () => {
    const sequence = ++requestSequence

    if (!auditColoringEnabled.value || !enabled.value || !allowed.value || !resource.value || !ids.value.length) {
      trails.value = {}
      isLoading.value = false

      return
    }

    isLoading.value = true

    try {
      const rows = await getAuditRowTrails(resource.value, ids.value)

      if (sequence === requestSequence)
        trails.value = Object.fromEntries(rows.map(row => [Number(row.id), row]))
    }
    catch {
      // A missing permission, disabled audit scope or offline hiccup must not
      // block the register or produce an API-error toast.
      if (sequence === requestSequence)
        trails.value = {}
    }
    finally {
      if (sequence === requestSequence)
        isLoading.value = false
    }
  }

  watch([signature, auditColoringEnabled, enabled, allowed], load, { immediate: true })

  const trailFor = (rowOrId: Record<string, any> | number | null | undefined): AuditTrailRow | null => {
    const id = typeof rowOrId === 'number' ? rowOrId : Number(rowOrId?.id)

    return Number.isInteger(id) ? trails.value[id] ?? null : null
  }

  const activeTrails = computed(() => ids.value
    .map(id => trails.value[id])
    .filter((trail): trail is AuditTrailRow => Boolean(trail)))

  return {
    /** Whether the feature is enabled company-wide. */
    enabled: auditColoringEnabled,
    /** Whether this caller may read and deep-link audits. */
    allowed,
    isLoading,
    trails,
    trailFor,
    activeTrails,
    hasTrails: computed(() => activeTrails.value.length > 0),
    legendVisible: computed(() => auditColoringEnabled.value && allowed.value && activeTrails.value.length > 0),
  }
}
