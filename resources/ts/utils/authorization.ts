import { type Rule, ability } from '@/plugins/casl/ability'
import { useCookie } from '@core/composable/useCookie'
import { clearStoredPermissions, storePermissions } from '@/utils/authStorage'

const impliedPermissions: Record<string, string[]> = {
  view_all_boards: ['view_assigned_boards'],
  view_all_tasks: ['view_assigned_tasks'],
  edit_all_tasks: ['edit_assigned_tasks'],
  delete_all_tasks: ['delete_assigned_tasks'],
  move_all_tasks: ['move_assigned_tasks'],
  edit_all_subtasks: ['edit_own_subtasks'],
  delete_all_subtasks: ['delete_own_subtasks'],
  edit_all_comments: ['edit_own_comments'],
  delete_all_comments: ['delete_own_comments'],
}

export const permissionRules = (permissions: string[]): Rule[] => [...new Set(
  permissions.flatMap(permission => [permission, ...(impliedPermissions[permission] ?? [])]),
)].map(permission => ({
  action: 'read',
  subject: permission,
}))

export const applyPermissions = (permissions: string[]) => {
  const rules = permissionRules(permissions)

  ability.update(rules)
  storePermissions(permissions)
}

export const clearPermissions = () => {
  ability.update([])
  clearStoredPermissions()
  useCookie<Rule[] | null>('userAbilityRules').value = null
}

export const canAccess = (permission: string) => ability.can('read', permission)
