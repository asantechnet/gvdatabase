import contracts from './contracts'
import crm from './crm'
import finance from './finance'
import home from './home'
import hr from './hr'
import innvoice from './innvoice'
import settings from './settings'
import setup from './setup'
import tasks from './tasks'
import type { HorizontalNavItems } from '@layouts/types'

export default [
  ...home,
  ...contracts,
  ...crm,
  ...innvoice,
  ...finance,
  ...hr,
  ...tasks,
  ...settings,
  ...setup,
] as HorizontalNavItems
