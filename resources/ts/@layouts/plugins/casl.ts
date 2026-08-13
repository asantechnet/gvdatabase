import { ability } from '@/plugins/casl/ability'
import type { Actions } from '@/plugins/casl/ability'
import type { RouteLocationNormalized } from 'vue-router'
import type { NavGroup } from '@layouts/types'

/**
 * Returns ability result if ACL is configured or else just return true
 * We should allow passing string | undefined to can because for admin ability we omit defining action & subject
 *
 * Useful if you don't know if ACL is configured or not
 * Used in @core files to handle absence of ACL without errors
 *
 * @param {string} action CASL Actions // https://casl.js.org/v4/en/guide/intro#basics
 * @param {string} subject CASL Subject // https://casl.js.org/v4/en/guide/intro#basics
 */
export const can = (action: string | undefined, subject: string | undefined) => {
  const vm = getCurrentInstance()

  if (!vm)
    return false

  // ℹ️ Items that don't declare an action/subject are not ACL-constrained and must always be visible.
  // Without this guard, `$can(undefined, undefined)` returns false when the ability has no rules (e.g. not logged in),
  // which silently hides every nav link.
  if (!action && !subject)
    return true

  const localCan = vm.proxy && '$can' in vm.proxy

  return localCan ? vm.proxy?.$can(action as Actions, subject as string) : true
}

/**
 * Check if user can view item based on it's ability
 * Based on item's action and subject & Hide group if all of it's children are hidden
 * @param {object} item navigation object item
 */
export const canViewNavMenuGroup = (item: NavGroup) => {
  const hasAnyVisibleChild = item.children.some(i => can(i.action, i.subject))

  // If subject and action is defined in item => Return based on children visibility (Hide group if no child is visible)
  // Else check for ability using provided subject and action along with checking if has any visible child
  if (!(item.action && item.subject))
    return hasAnyVisibleChild

  return can(item.action, item.subject) && hasAnyVisibleChild
}

export const canNavigate = (to: RouteLocationNormalized) => {
  // Get the most specific route (last one in the matched array)
  const targetRoute = to.matched[to.matched.length - 1]

  const anyPermissions = targetRoute?.meta?.anyPermissions
  if (Array.isArray(anyPermissions))
    return anyPermissions.some(permission => ability.can('read', permission as string))

  if (!targetRoute?.meta?.action && !targetRoute?.meta?.subject)
    return true

  // If the target route has specific permissions, check those first
  if (targetRoute?.meta?.action && targetRoute?.meta?.subject)
    return ability.can(targetRoute.meta.action as Actions, targetRoute.meta.subject as string)

  // If no specific permissions, fall back to checking if any parent route allows access
  return to.matched.some(route => ability.can(route.meta.action as Actions, route.meta.subject as string))
}
