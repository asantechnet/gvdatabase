import type { App } from 'vue'

import { abilitiesPlugin } from '@casl/vue'
import { ability, type Rule } from './ability'
import { getStoredPermissions } from '@/utils/authStorage'
import { permissionRules } from '@/utils/authorization'

export default function (app: App) {
  const userAbilityRules = useCookie<Rule[] | null>('userAbilityRules')
  const storedPermissions = getStoredPermissions()

  // Permission names are stored in Web Storage. A super-admin's full rule
  // set can exceed a browser cookie's size limit; in that case the legacy
  // cookie is absent and must not overwrite the rules restored by the router.
  ability.update(storedPermissions.length ? permissionRules(storedPermissions) : userAbilityRules.value ?? [])

  app.use(abilitiesPlugin, ability, {
    useGlobalProperties: true,
  })
}
