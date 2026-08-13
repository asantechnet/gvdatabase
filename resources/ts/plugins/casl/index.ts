import type { App } from 'vue'

import { abilitiesPlugin } from '@casl/vue'
import { ability, type Rule } from './ability'

export default function (app: App) {
  const userAbilityRules = useCookie<Rule[] | null>('userAbilityRules')
  ability.update(userAbilityRules.value ?? [])

  app.use(abilitiesPlugin, ability, {
    useGlobalProperties: true,
  })
}
