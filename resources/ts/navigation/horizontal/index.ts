import applicantPortal from './applicant-portal'
import applications from './applications'
import contracts from './contracts'
import crm from './crm'
import finance from './finance'
import home from './home'
import hr from './hr'
import settings from './settings'
import setup from './setup'
import tasks from './tasks'
import type { HorizontalNavItems } from '@layouts/types'

export default [
  ...home,
  ...applicantPortal,
  ...contracts,
  ...applications,
  ...crm,
  ...finance,
  ...hr,
  ...tasks,
  ...settings,
  ...setup,
] as HorizontalNavItems
