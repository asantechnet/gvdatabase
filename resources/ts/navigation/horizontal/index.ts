import type { HorizontalNavItems } from '@layouts/types'
import contracts from './contracts'
import finance from './finance'
import home from './home'
import hr from './hr'
import innvoice from './innvoice'
import notes from './notes'
import settings from './settings'
import setup from './setup'
import visitor from './visitor'

export default [
  ...home,
  ...contracts,
  ...innvoice,
  ...finance,
  ...hr,
  ...visitor,
  ...notes,
  ...settings,
  ...setup,
] as HorizontalNavItems
