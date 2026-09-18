import applicants from './applicants'
import contracts from './contracts'
import crm from './crm'
import finance from './finance'
import home from './home'
import hr from './hr'
import innvoice from './innvoice'
import services from './services'
import settings from './settings'
import setup from './setup'
import tasks from './tasks'
import type { HorizontalNavItems } from '@layouts/types'

export default [
  ...home,
  ...applicants,
  ...contracts,
  ...crm,
  ...innvoice,
  ...services,
  ...finance,
  ...hr,
  ...tasks,
  ...settings,
  ...setup,
] as HorizontalNavItems
