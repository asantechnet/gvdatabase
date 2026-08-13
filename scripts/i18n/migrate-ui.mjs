import fs from 'node:fs'
import path from 'node:path'

const root = process.cwd()
const sourceRoots = [
  'resources/ts/pages',
  'resources/ts/components',
  'resources/ts/layouts',
  'resources/ts/views/apps/roles/RoleCards.vue',
  'resources/ts/views/pages/account-settings',
]
const localeDir = path.join(root, 'resources/ts/plugins/i18n/locales')

const walk = directory => fs.existsSync(directory)
  ? fs.statSync(directory).isFile() ? [directory] : fs.readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
      const target = path.join(directory, entry.name)
      return entry.isDirectory() ? walk(target) : [target]
    })
  : []

const files = sourceRoots.flatMap(directory => walk(path.join(root, directory)))
  .filter(file => file.endsWith('.vue'))

const actions = new Set(['add', 'approve', 'back', 'cancel', 'close', 'confirm', 'continue', 'create', 'delete', 'dismiss', 'download', 'edit', 'export', 'filter', 'grant', 'next', 'print', 'refresh', 'reject', 'remove', 'retry', 'save', 'search', 'start', 'submit', 'update', 'upload', 'view'])
const fields = new Set(['address', 'amount', 'category', 'company', 'currency', 'date', 'department', 'description', 'email', 'employee', 'end date', 'name', 'notes', 'office', 'password', 'phone', 'priority', 'reason', 'role', 'start date', 'status', 'title', 'type'])
const statuses = new Set(['active', 'approved', 'cancelled', 'completed', 'deactivated', 'eligible', 'expired', 'failed', 'granted', 'inactive', 'loading', 'overdue', 'paid', 'pending', 'present', 'rejected', 'running', 'success', 'suspended'])

const slug = value => {
  const words = value.replace(/&[a-z]+;/gi, ' ').replace(/[^\p{L}\p{N}]+/gu, ' ').trim().split(/\s+/)
  const result = words.map((word, index) => index ? word[0]?.toUpperCase() + word.slice(1) : word.toLowerCase()).join('').replace(/^\d/, match => `n${match}`)
  return result.slice(0, 70) || 'text'
}

const shouldTranslate = value => {
  const text = value.trim()
  return /[A-Za-z]{2}/.test(text)
    && !/^(?:&#\d+;|&#x[\da-f]+;|&[a-z]+;)[A-Za-z0-9+_-]{0,3}$/i.test(text)
    && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(text)
    && !/^(?:https?:\/\/|www\.)/i.test(text)
    && !/^[A-Z0-9._/-]+$/.test(text)
}

const sourceFor = file => {
  const relative = path.relative(root, file).replaceAll('\\', '/')
  if (relative.includes('/templates/')) return ['documents', relative.match(/templates\/([^/.]+)/)?.[1] ?? 'contract']
  if (relative.includes('/task-manager/')) return ['components', 'taskManager']
  if (relative.includes('/workday/') || relative.includes('/account-settings/')) return ['components', 'account']
  if (relative.includes('/components/setup/') || relative.includes('/pages/setup/')) return ['pages', 'settings']
  if (relative.includes('/layouts/') || relative.includes('/navigation/')) return ['components', 'navigation']
  if (relative.includes('/components/dialogs/')) return ['components', 'dialogs']
  const name = path.basename(file, '.vue').toLowerCase()
  const group = /login|password|verify|auth/.test(name) ? 'auth'
    : /home|dashboard/.test(name) ? 'dashboard'
      : /user|role|position|marketer|office|setting/.test(name) ? 'settings'
        : /time|attendance/.test(name) ? 'attendance'
          : /salary|payroll/.test(name) ? 'payroll'
            : /bonus|worthy/.test(name) ? 'bonuses'
              : /contract|customer|service|visitor/.test(name) ? 'contracts'
                : /invoice|income|expense|stock|currenc|finance/.test(name) ? 'finance'
                  : /report/.test(name) ? 'reports'
                    : /task|kanban|calendar|gantt|todo/.test(name) ? 'tasks' : 'settings'
  return ['pages', group, slug(name)]
}

const candidates = []
for (const file of files) {
  const contents = fs.readFileSync(file, 'utf8')
  const template = contents.match(/<template\b[^>]*>([\s\S]*)<\/template>/)?.[1] ?? ''
  const attrPattern = /\s(?:label|placeholder|title|hint|aria-label|text|empty-text|no-data-text|confirm-text|cancel-text)="([^"<>]*[A-Za-z][^"<>]*)"/g
  for (const match of template.matchAll(attrPattern)) if (shouldTranslate(match[1])) candidates.push({ file, value: match[1].trim() })
  const textPattern = />([^<>]+)</g
  for (const match of template.matchAll(textPattern)) {
    const value = match[1].replace(/\s+/g, ' ').trim()
    if (shouldTranslate(value) && !value.includes('{{') && !value.includes('}}') && !value.startsWith('<!--')) candidates.push({ file, value })
  }
  const propertyPattern = /\b(?:title|label|placeholder|hint|message|description|subtitle|heading)\s*:\s*(['"])([^'"]*[A-Za-z][^'"]*)\1/g
  for (const match of contents.matchAll(propertyPattern)) {
    if (!/^(?:common|pages|components|documents|backend|notifications)\./.test(match[2])) candidates.push({ file, value: match[2].trim() })
  }
  const actionPattern = /(?:toast\.(?:success|error|info|warning)|alert|confirm)\(\s*(['"])([^'"]*[A-Za-z][^'"]*)\1/g
  for (const match of contents.matchAll(actionPattern)) candidates.push({ file, value: match[2].trim() })
  const assignmentPattern = /\.value(?:\.\w+)?\s*=\s*(['"])((?:Failed|Unable|Please|Select|Choose |Enter|No |Invalid|Unknown|This |Your |Password |Email |Name |Visitor |Father |Contact |Company |Service |Marketer |Address |Government |Position |Salary |Education |Contract |Join |Date:|Reason:|Paid-|Suggested |Selected |Performance |Add |Contribution |Notification |Profile |Account |If verification|If an account|New password)[^'"]*)\1/g
  for (const match of contents.matchAll(assignmentPattern)) candidates.push({ file, value: match[2].trim() })
  const returnPattern = /\breturn\s+(['"])((?:Select |Enter |Amount |Quantity |Exchange rate |No position)[^'"]*)\1/g
  for (const match of contents.matchAll(returnPattern)) candidates.push({ file, value: match[2].trim() })
}

const frequencies = new Map()
for (const { value } of candidates) frequencies.set(value, (frequencies.get(value) ?? 0) + 1)

const base = {
  common: {
    actions: {}, fields: {}, statuses: {}, messages: {
      notAvailable: 'Not available', noRecordsFound: 'No records found', loading: 'Loading…',
    }, confirmations: {}, navigation: {}, tables: {}, dateTime: {}, currencies: {}, accessibility: {},
    languages: { english: 'English', dari: 'دری', pashto: 'پښتو' },
  },
  pages: { auth: {}, dashboard: {}, settings: {}, users: {}, attendance: {}, payroll: {}, bonuses: {}, contracts: {}, finance: {}, reports: {}, tasks: {} },
  components: { taskManager: {}, account: {}, setup: {}, navigation: {}, dialogs: {} },
  documents: { invoice: {}, contract: {}, payslip: {}, timesheet: {} },
  backend: { values: {}, errors: {}, validation: {} },
  notifications: {},
  $vuetify: {
    badge: 'Badge', noDataText: 'No data available', close: 'Close', open: 'Open', loading: 'Loading',
    dataFooter: { itemsPerPageText: 'Items per page:', itemsPerPageAll: 'All', pageText: '{0}-{1} of {2}', firstPage: 'First page', prevPage: 'Previous page', nextPage: 'Next page', lastPage: 'Last page' },
    pagination: { ariaLabel: { root: 'Pagination', previous: 'Previous', first: 'First', last: 'Last', next: 'Next', currentPage: 'Current page', page: 'Page {0}' } },
    input: { clear: 'Clear', appendAction: 'Appended action', prependAction: 'Prepended action', counterSize: 'Input length', otp: 'One-time password' },
    fileInput: { counterSize: 'Files selected' },
  },
}

const merge = (target, source) => {
  for (const [key, value] of Object.entries(source ?? {})) {
    if (value && typeof value === 'object' && !Array.isArray(value)) merge(target[key] ??= {}, value)
    else target[key] = value
  }
}

const set = (object, parts, value) => {
  let cursor = object
  for (const part of parts.slice(0, -1)) cursor = cursor[part] ??= {}
  let key = parts.at(-1)
  if (cursor[key] !== undefined && cursor[key] !== value) {
    let suffix = 2
    while (cursor[`${key}${suffix}`] !== undefined && cursor[`${key}${suffix}`] !== value) suffix++
    key = `${key}${suffix}`
  }
  cursor[key] = value
  return [...parts.slice(0, -1), key].join('.')
}

const keys = new Map()
const keyFor = (file, value) => {
  const identity = `${file}\0${value}`
  if (keys.has(identity)) return keys.get(identity)
  const lower = value.replace(/[.:!?]+$/, '').toLowerCase()
  let parts
  if (actions.has(lower)) parts = ['common', 'actions', slug(lower)]
  else if (fields.has(lower)) parts = ['common', 'fields', slug(lower)]
  else if (statuses.has(lower)) parts = ['common', 'statuses', slug(lower)]
  else if (/^are you sure|^do you want|cannot be undone/i.test(value)) parts = ['common', 'confirmations', slug(value)]
  else if ((frequencies.get(value) ?? 0) > 1) parts = ['common', 'messages', slug(value)]
  else parts = [...sourceFor(file), slug(path.basename(file, '.vue')), slug(value)]
  const key = set(base, parts, value)
  keys.set(identity, key)
  return key
}

const humanize = key => key
  .replace(/\d+$/, '')
  .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
  .replace(/^./, letter => letter.toUpperCase())

// Recover previously migrated keys when this script is rerun. This makes the
// migration idempotent and protects hand-edited English source messages.
for (const file of files) {
  const contents = fs.readFileSync(file, 'utf8')
  for (const match of contents.matchAll(/(?:\$t|translate|\bt)\('([^']+)'/g)) {
    const parts = match[1].split('.')
    if (parts[0] === '$vuetify') continue
    const existing = parts.reduce((cursor, part) => cursor?.[part], base)
    if (existing === undefined) set(base, parts, humanize(parts.at(-1)))
  }
}

base.common.languages = { english: 'English', dari: 'دری', pashto: 'پښتو' }
base.common.navigation = {
  home: 'Home', finance: 'Finance', income: 'Income', expenses: 'Expenses', stock: 'Stock', reports: 'Reports',
  hr: 'Human resources', staffList: 'Staff list', timesheet: 'Timesheet', staffSalary: 'Staff salary', bonusList: 'Bonus list',
  invoice: 'Invoice', options: 'Options', contract: 'Contracts', policies: 'Policies', services: 'Services', currencies: 'Currencies',
  hrm: 'HR management', allContracts: 'All contracts', allCustomers: 'All customers', cancelledContracts: 'Cancelled contracts',
  setup: 'Setup', users: 'Users', roles: 'Roles', positions: 'Positions', marketers: 'Marketers', offices: 'Offices',
  settings: 'Settings', tasks: 'Tasks', visitor: 'Visitors',
}
base.common.messages.notAvailable = 'Not available'
base.common.messages.noRecordsFound = 'No records found'
base.common.messages.loading = 'Loading…'
base.common.messages.unableToSaveChanges = 'Unable to save your changes.'
base.common.messages.editWindowExpired = 'Editing is available for one hour after creation.'
base.components.taskManager.comments = { ...(base.components.taskManager.comments ?? {}), editComment: 'Edit comment' }
base.components.taskManager.todos = { ...(base.components.taskManager.todos ?? {}), editActionItem: 'Edit action item' }
base.components.account.workday = {
  summaryRequired: 'Please summarize your day.', endMyDay: 'End my day', startedAt: 'Started at {time}',
  dayCompleted: 'Day completed', attendanceUnavailable: "Workday unavailable for today's attendance",
  startMyDay: 'Start my day', startTitle: 'Ready to start your day?',
  startDescription: "Start your workday to record today's attendance and working time.",
  reportTitle: 'End-of-day report', reportDescription: 'Share a concise account of your day. Submitting this report will end your workday.',
  summary: 'Day summary', achievements: 'Achievements', challenges: 'Challenges', nextDayPlan: 'Plan for the next workday',
  supportNeeded: 'Support needed', submitAndEnd: 'Submit report and end my day',
}
base.pages.settings.geofence = {
  title: 'Office geofence',
  description: 'Set the office point and the boundary used for automatic attendance.',
  enableAutomaticAttendance: 'Enable automatic attendance',
  mapLabel: 'Office geofence map',
  mapHint: 'Click the map or drag the purple marker to move the office. Drag the orange handle to resize the boundary.',
  currentLocation: 'Current location',
  latitude: 'Latitude',
  longitude: 'Longitude',
  radiusMeters: 'Office radius (meters)',
  useCurrentLocation: 'Use current location',
  coordinatesRequired: 'Select office coordinates before enabling automatic attendance.',
  notSupported: 'Location services are not supported by this browser.',
  locationFailed: 'Your current location could not be read. Check the browser location permission and try again.',
}
base.components.account.geofence = {
  checkedIn: 'Automatically checked in at {office}.',
  checkedOut: 'Automatically checked out after leaving {office}. Submit your daily report from the profile menu.',
  inaccurate: 'Automatic attendance is waiting for a more accurate location (currently ±{accuracy} meters).',
  permissionRequired: 'Allow location access to use automatic office attendance.',
}
base.components.account.deactivation = { ...(base.components.account.deactivation ?? {}), pendingSince: 'Your account deactivation request from {date} is awaiting approval.' }
base.documents.payslip.amountInWordsFallback = '{amount} {currency} only'
base.documents.timesheet.generatedOn = 'Generated on {date}'
Object.assign(base.common.statuses, {
  active: 'Active', inactive: 'Inactive', pending: 'Pending', approved: 'Approved', rejected: 'Rejected',
  completed: 'Completed', cancelled: 'Cancelled', eligible: 'Eligible', granted: 'Granted', expired: 'Expired',
  present: 'Present', absent: 'Absent', paidLeave: 'Paid leave', complete: 'Complete', incomplete: 'Incomplete',
  low: 'Low', medium: 'Medium', high: 'High', urgent: 'Urgent', overdue: 'Overdue', inProgress: 'In progress',
  notStarted: 'Not started', running: 'Running', failed: 'Failed', success: 'Success', suspended: 'Suspended',
  new: 'New', good: 'Good', fair: 'Fair', poor: 'Poor', damaged: 'Damaged', unknown: 'Unknown', none: 'None',
  yes: 'Yes', no: 'No', paid: 'Paid', unpaid: 'Unpaid', partial: 'Partially paid', voided: 'Voided',
  service: 'Service', product: 'Product', investment: 'Investment', other: 'Other', employment: 'Employment',
  partnership: 'Partnership', lease: 'Lease', nda: 'NDA', performance: 'Performance', spotBonus: 'Spot bonus',
  all: 'All', software: 'Software', salaries: 'Salaries', marketing: 'Marketing', supplies: 'Supplies', infrastructure: 'Infrastructure',
  projectCompletion: 'Project completion', retention: 'Retention', topPerformer: 'Top performer',
  projectMilestone: 'Project milestone', clientPraise: 'Client praise', workAnniversary: 'Work anniversary',
  managerNomination: 'Manager nomination', automaticTaskNomination: 'Automatic task nomination', manualNomination: 'Manual nomination',
  nearingCompletion: 'Nearing completion',
})
base.pages.bonuses.bonuslist = {
  ...(base.pages.bonuses.bonuslist ?? {}), suggestedAmount: 'Suggested: {amount}',
  grantConfirmation: 'You are about to grant a {type} bonus to {employee} for {month}.',
}
base.pages.settings.notifications = { ...(base.pages.settings.notifications ?? {}), unreadCount: '{count} unread' }
base.pages.finance.currencies = {
  ...(base.pages.finance.currencies ?? {}), activated: 'Currency activated', deactivated: 'Currency deactivated',
  rateToBase: 'Rate to base ({currency})', updatedAt: 'Updated: {date}', decimalPlaces: '{count} decimal places',
}
base.pages.settings.settings.assetHint = '{hint} · PNG, JPG, or WEBP up to 8 MB'
base.documents.contract.licence = 'Licence: {licence}'
base.documents.payslip.totalNetPayable = 'TOTAL NET PAYABLE ({currency})'
base.documents.payslip.workedDays = '{worked} / {total} days'
base.pages.attendance.timesheet = { ...(base.pages.attendance.timesheet ?? {}), unmarkedFor: 'Unmarked for {date}' }
base.pages.attendance.timesheet.filteredRange = 'Filtered view: {start} to {end}'
base.pages.attendance.timesheet.steps = { logAttendance: '1. Log attendance', printRangeFilter: '2. Print range filter' }
base.pages.finance.currencies.baseRate = '1.0000 (Base)'
base.components.dialogs.bonusRules = { currentVersion: 'Saving creates a new immutable rule version. Current version: {version}', priorityWeight: '{priority} weight' }
base.components.dialogs.addeditaddressdialog = {
  ...(base.components.dialogs.addeditaddressdialog ?? {}), addAddress: 'Add new address', editAddress: 'Edit address',
}
base.components.dialogs.addeditpermissiondialog = {
  ...(base.components.dialogs.addeditpermissiondialog ?? {}), addPermission: 'Add permission', editPermission: 'Edit permission',
  addDescription: 'Add a permission for your requirements.', editDescription: 'Edit the permission for your requirements.',
}
base.components.setup.directory = { activeCount: '{count} active', assignmentCount: '{count} assignments' }
Object.assign(base.common.actions, { addItem: 'Add {item}', editItem: 'Edit {item}', deleteItem: 'Delete {item}', adding: 'adding', editing: 'editing' })
Object.assign(base.common.fields, { marketer: 'Marketer', itemName: '{item} name' })
Object.assign(base.common.messages, { noNotes: 'No notes', noAddress: 'No address', noMatchingRecords: 'No records match the current filters.' })
base.pages.settings.directory = {
  marketers: 'Marketers', offices: 'Offices',
  marketersDescription: 'Manage the people responsible for customer and visitor relationships.',
  officesDescription: 'Manage business locations used by inventory and operations.',
  deleteConfirmation: 'Delete {name}? Assigned directories must be reassigned first.',
}
Object.assign(base.common.messages, {
  quantityValue: 'Quantity: {value}', rateValue: 'Rate: {value}', currencyQuantity: '{currency} · Quantity: {quantity}',
  quantityExchangeRate: 'Quantity: {quantity} · Exchange rate: {rate}',
})
base.pages.contracts.allCustomers = { ...(base.pages.contracts.allCustomers ?? {}), attachServicesTo: 'Attach services to {customer}' }
base.pages.bonuses.bonuslist = { ...(base.pages.bonuses.bonuslist ?? {}), calculationSummary: 'Rule version {version} · score {score}' }
base.pages.finance.currencies = { ...(base.pages.finance.currencies ?? {}), symbolExample: 'For example, A$' }
base.pages.contracts.services = { ...(base.pages.contracts.services ?? {}), durationMonthsValue: '{count} month | {count} months' }
base.pages.settings.positions = { ...(base.pages.settings.positions ?? {}), deleteConfirmation: 'Delete {position}? Positions assigned to staff or users cannot be deleted.' }
base.pages.settings.users = { ...(base.pages.settings.users ?? {}), deleteConfirmation: 'Delete {user}? This action can be restored only through the database.' }
base.pages.users.staffList = { ...(base.pages.users.staffList ?? {}), leaveAllowance: 'Allowance: {value}', leaveUsed: 'Used: {value}', leaveRemaining: 'Remaining: {value}' }
base.documents.contract.telephone = 'Tel: {phone}'
Object.assign(base.documents.timesheet, { overtimeInline: ' · OT {duration}', totals: 'Total worked: {worked} · Overtime: {overtime}' })
base.components.taskManager.calendarview = { ...(base.components.taskManager.calendarview ?? {}), itemProgress: '{completed} / {total} items' }
base.components.taskManager.todomatrixview = { ...(base.components.taskManager.todomatrixview ?? {}), completedProgress: '{completed} / {total} completed' }
base.components.taskManager.tasksettingsview = { ...(base.components.taskManager.tasksettingsview ?? {}), deleteConfirmation: 'Delete “{label}”? It will be removed from tasks that currently use it.' }
base.components.account.accountdeactivationrequests = {
  ...(base.components.account.accountdeactivationrequests ?? {}), approvalWarning: '{user} will be signed out everywhere immediately after approval.',
  confirmDecision: 'Confirm {decision}', approved: 'Account deactivation approved.', rejected: 'Account deactivation rejected.',
}
base.components.dialogs.addeditpermissiondialog.warningText = 'By {action} the permission name, you might break the system permissions functionality.'
Object.assign(base.components.navigation.navbarauditlogs ??= {}, {
  changeApproved: 'Change approved and audit record removed.', changeDiscarded: 'Change discarded, reverted, and audit record removed.',
})
Object.assign(base.components.taskManager.tasksettingsview, { labelUpdated: 'Task label updated.', labelCreated: 'Task label created.' })
Object.assign(base.pages.settings.positions, { updated: 'Position updated.', created: 'Position created.' })
Object.assign(base.pages.settings.users, { updated: 'User updated.', invitationSent: 'Invitation sent. The user will create a password after verifying their email.' })
base.pages.auth.createPassword = { ...(base.pages.auth.createPassword ?? {}), invalidInvitation: 'This invitation link is incomplete or expired.' }
base.pages.auth.resetPassword = { ...(base.pages.auth.resetPassword ?? {}), missingToken: 'This reset link is missing its security token.' }
base.pages.auth.verifyEmail = {
  ...(base.pages.auth.verifyEmail ?? {}), invalidLink: 'This verification link is invalid or has expired.',
  verified: 'Your email address is verified. You can now sign in.', requestNew: 'Request a new verification link below.',
}
Object.assign(base.pages.users.staffList, { dateRequired: 'Date: Required.', reasonRequired: 'Reason: Required.' })
base.components.dialogs.twofactorauthdialog = {
  ...(base.components.dialogs.twofactorauthdialog ?? {}), authenticatorDescription: 'Get a code from an authenticator app.',
  smsDescription: 'We will send a code by SMS when you use the backup sign-in method.',
}
base.documents.invoice.defaultPaymentTerms = 'Unless otherwise agreed in writing, invoices are payable within thirty (30) days of the invoice date.'
base.documents.contract.defaultFooter = 'Thank you for doing business with us!'
base.components.taskManager.event = {
  ...(base.components.taskManager.event ?? {}), priority: '{priority} priority', dateRange: '{start} → {end}', dependencyCount: '{count} dependencies',
}
base.components.taskManager.todos = { ...(base.components.taskManager.todos ?? {}), dueDate: 'Due: {date}' }
base.components.navigation.footer = { copyright: '© {year} {company}. All rights reserved.' }
base.pages.reports.reportTitle = '{type} report'
base.pages.reports.currencyBasis = 'Historical record snapshots; base values use the rate preserved on each row.'
Object.assign(base.pages.dashboard, { healthy: 'Healthy', needsAttention: 'Needs attention' })
base.pages.dashboard.metrics = {
  newIncome: 'New income this month', noPreviousIncome: 'No income recorded last month',
  incomeComparison: '{change}% compared with last month', taskCompletionRate: '{rate}% task completion rate',
  nearingCompletion: '{count} nearing completion', attendanceSummary: '{present} present out of {marked} marked',
  averageProgress: '{progress}% average progress', attendanceRate: 'Attendance rate', taskCompletionRateTitle: 'Task completion rate',
  netCashFlow: 'Net cash flow', averageTaskProgress: 'Average task progress', unableToLoad: 'Unable to load dashboard data',
}
Object.assign(base.common.fields, {
  educationLevel: 'Education level', fatherName: 'Father name', joinDate: 'Join date', contractDuration: 'Contract duration',
  exchangeRate: 'Exchange rate', amountPaid: 'Amount paid', salaryMonth: 'Salary month', workedDays: 'Worked days', totalDays: 'Total days',
  performanceScore: 'Performance score', bonusType: 'Bonus type', bonusAmount: 'Bonus amount', invoiceType: 'Invoice type', customer: 'Customer',
  contractTemplate: 'Contract template', paymentInstructions: 'Payment instructions', authenticatorCode: 'Authenticator code', currentPassword: 'Current password',
  passwordConfirmation: 'Password confirmation', serialNumber: 'Serial number', filePath: 'File path', link: 'Link', staffMember: 'Staff member',
  board: 'Board', assignees: 'Assignees', tags: 'Tags', code: 'Code', symbol: 'Symbol', rateAgainstBase: 'Rate against base', decimalPlaces: 'Decimal places',
})
base.backend.validation = {
  required: 'Required.', email: 'Enter a valid email address.', date: 'Choose a valid date.', numeric: 'Enter a number.',
  integer: 'Enter a whole number.', option: 'Choose a valid option.', accepted: 'Must be accepted.', array: 'Choose at least one item.',
  phone: 'Enter a valid phone number.', month: 'Use YYYY-MM.', min: 'Use at least {value}.', max: 'Use no more than {value}.',
  gt: 'Enter a value above {value}.', between: 'Use a value from {min} to {max}.', digits: 'Use exactly {value} digits.',
  afterOrEqual: 'Choose {field} or later.', invalid: 'Enter a valid value.', confirmed: 'Password confirmation must match password.',
}
Object.assign(base.backend.values, {
  general: 'General', owner: 'Owner', canEdit: 'Can edit', canComment: 'Can comment', canView: 'Can view',
})
Object.assign(base.common.statuses, { partialPayment: 'Partial payment', downloaded: 'Downloaded', draft: 'Draft', sent: 'Sent', pastDue: 'Past due' })
base.notifications = {
  audit: {
    created: { title: 'Record created', message: 'A {table} record #{row} was created.' },
    updated: { title: 'Record updated', message: '{table} record #{row} changed: {columns}.' },
    deleted: { title: 'Record deleted', message: '{table} record #{row} was deleted.' },
    restored: { title: 'Record restored', message: '{table} record #{row} was restored.' },
  },
  account_deactivation: {
    requested: { title: 'Deactivation review requested', message: '{requester} requested account deactivation.' },
    cancelled: { title: 'Deactivation request cancelled', message: '{requester} cancelled the pending request.' },
    approved: { title: 'Account deactivation approved', message: '{reviewer} approved your account deactivation request.' },
    rejected: { title: 'Account deactivation rejected', message: '{reviewer} rejected your account deactivation request.' },
    reactivated: { title: 'Account reactivated', message: '{reviewer} reactivated your account.' },
  },
  bonus: { review: { title: 'Bonus nomination ready for review', message: 'An automatic task-based bonus nomination is ready for review.' } },
  types: {
    info: { title: 'Information', message: '' }, success: { title: 'Success', message: '' },
    warning: { title: 'Warning', message: '' }, error: { title: 'Error', message: '' },
    task: { title: 'Task update', message: '' }, contract: { title: 'Contract update', message: '' },
    finance: { title: 'Finance update', message: '' }, system: { title: 'System notification', message: '' },
  },
}

const reportTypes = { financial: 'Financial', payroll: 'HR and payroll', attendance: 'Attendance', operations: 'Operations' }
const reportSections = {
  invoices: 'Invoices', income: 'Income', expenses: 'Expenses', net_cash_flow: 'Net cash flow', salary_payments: 'Salary payments',
  bonuses: 'Bonuses', attendance: 'Attendance', contracts: 'Contracts', customers: 'Customers', visitors: 'Visitors', tasks: 'Tasks',
}
const reportColumns = [
  'number', 'date', 'customer', 'status', 'amount', 'currency', 'rate_to_base', 'base_amount', 'paid_base', 'outstanding_base',
  'reference', 'type', 'description', 'quantity', 'income_base', 'expense_base', 'net_base', 'payslip', 'payment_date',
  'salary_month', 'employee', 'office', 'bonus', 'deductions', 'net_amount', 'deductions_base', 'month', 'reason',
  'performance_score', 'achievement_details', 'source', 'check_in_utc', 'check_out_utc', 'gross_minutes', 'break_minutes',
  'worked_minutes', 'required_minutes', 'late_minutes', 'early_minutes', 'overtime_minutes', 'punch_state', 'overnight',
  'title', 'counterparty', 'name', 'company', 'contact', 'marketer', 'service', 'board', 'start_date', 'due_date', 'priority',
  'progress', 'employees', 'count', 'by_currency', 'records', 'present', 'absent', 'paid_leave', 'incomplete', 'completed',
  'overdue', 'average_progress', 'invoiced_base',
]
base.pages.reports.types ??= {}
base.pages.reports.sections ??= {}
base.pages.reports.columns ??= {}
for (const [key, value] of Object.entries(reportTypes)) base.pages.reports.types[key] = value
for (const [key, value] of Object.entries(reportSections)) base.pages.reports.sections[key] = value
for (const key of reportColumns) base.pages.reports.columns[slug(key)] = key.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase())

for (const { file, value } of candidates) keyFor(file, value)

for (const file of walk(path.join(root, 'resources/ts/navigation/horizontal')).filter(file => file.endsWith('.ts'))) {
  const contents = fs.readFileSync(file, 'utf8')
  const updated = contents.replace(/title:\s*(['"])([^'"]*[A-Za-z][^'"]*)\1/g, (full, quote, value) => {
    if (value.startsWith('common.navigation.')) return full
    const key = set(base, ['common', 'navigation', slug(value)], value)
    return `title: ${quote}${key}${quote}`
  })
  if (updated !== contents) fs.writeFileSync(file, updated)
}

const apiErrorSource = fs.readFileSync(path.join(root, 'resources/ts/utils/apiErrors.ts'), 'utf8')
for (const match of apiErrorSource.matchAll(/^\s*(?:'([^']+)'|([A-Za-z_][\w]*)):\s*'((?:\\'|[^'])*)',/gm)) {
  const code = match[1] ?? match[2]
  set(base, ['backend', 'errors', ...code.split('.')], match[3].replaceAll("\\'", "'"))
}

for (const file of files) {
  const contents = fs.readFileSync(file, 'utf8')
  const match = contents.match(/(<template\b[^>]*>)([\s\S]*)(<\/template>)/)
  if (!match) continue
  let template = match[2]
  template = template.replace(/\s(label|placeholder|title|hint|aria-label|text|empty-text|no-data-text|confirm-text|cancel-text)="([^"<>]*[A-Za-z][^"<>]*)"/g, (full, attr, raw) => {
    const value = raw.trim()
    if (!shouldTranslate(value)) return full
    return ` :${attr}="$t('${keyFor(file, value)}')"`
  })
  template = template.replace(/>([^<>]+)</g, (full, raw) => {
    const value = raw.replace(/\s+/g, ' ').trim()
    if (!shouldTranslate(value) || value.includes('{{') || value.includes('}}') || value.startsWith('<!--')) return full
    const leading = raw.match(/^\s*/)?.[0] ?? ''
    const trailing = raw.match(/\s*$/)?.[0] ?? ''
    return `>${leading}{{ $t('${keyFor(file, value)}') }}${trailing}<`
  })
  const updated = contents.slice(0, match.index) + match[1] + template + match[3] + contents.slice(match.index + match[0].length)
  let localized = updated
  localized = localized.replace(/\b(title|label|placeholder|hint|message|description|subtitle|heading)\s*:\s*(['"])([^'"]*[A-Za-z][^'"]*)\2/g, (full, property, quote, value) => {
    if (/^(?:common|pages|components|documents|backend|notifications)\./.test(value)) return full
    return `${property}: translate('${keyFor(file, value.trim())}')`
  })
  localized = localized.replace(/(toast\.(?:success|error|info|warning)|alert|confirm)\(\s*(['"])([^'"]*[A-Za-z][^'"]*)\2/g, (full, call, quote, value) => `${call}(translate('${keyFor(file, value.trim())}')`)
  localized = localized.replace(/(\.value(?:\.\w+)?)\s*=\s*(['"])((?:Failed|Unable|Please|Select|Choose |Enter|No |Invalid|Unknown|This |Your |Password |Email |Name |Visitor |Father |Contact |Company |Service |Marketer |Address |Government |Position |Salary |Education |Contract |Join |Date:|Reason:|Paid-|Suggested |Selected |Performance |Add |Contribution |Notification |Profile |Account |If verification|If an account|New password)[^'"]*)\2/g, (full, target, quote, value) => `${target} = translate('${keyFor(file, value.trim())}')`)
  localized = localized.replace(/\breturn\s+(['"])((?:Select |Enter |Amount |Quantity |Exchange rate |No position)[^'"]*)\1/g, (full, quote, value) => `return translate('${keyFor(file, value.trim())}')`)
  localized = localized
    .replaceAll('padding-left', 'padding-inline-start').replaceAll('padding-right', 'padding-inline-end')
    .replaceAll('margin-left', 'margin-inline-start').replaceAll('margin-right', 'margin-inline-end')
    .replaceAll('border-left', 'border-inline-start').replaceAll('border-right', 'border-inline-end')
  if (localized !== contents) fs.writeFileSync(file, localized)
}

fs.mkdirSync(localeDir, { recursive: true })
const preserveTranslations = (english, existing) => Object.fromEntries(Object.entries(english).map(([key, value]) => [
  key,
  value && typeof value === 'object' && !Array.isArray(value)
    ? preserveTranslations(value, existing?.[key])
    : (typeof existing?.[key] === 'string' ? existing[key] : value),
]))
const readLocale = locale => {
  const target = path.join(localeDir, `${locale}.json`)
  return fs.existsSync(target) ? JSON.parse(fs.readFileSync(target, 'utf8')) : {}
}
const previousEnglish = readLocale('en')
const previousDari = readLocale('fa-AF')
const previousPashto = readLocale('ps-AF')
const preserveEnglishCopy = (generated, english, dari, pashto) => Object.fromEntries(Object.entries(generated).map(([key, value]) => [
  key,
  value && typeof value === 'object' && !Array.isArray(value)
    ? preserveEnglishCopy(value, english?.[key], dari?.[key], pashto?.[key])
    : (typeof dari?.[key] === 'string' && dari[key] === pashto?.[key] ? dari[key] : (typeof english?.[key] === 'string' ? english[key] : value)),
]))
const englishBase = preserveEnglishCopy(base, previousEnglish, previousDari, previousPashto)
fs.writeFileSync(path.join(localeDir, 'en.json'), `${JSON.stringify(englishBase, null, 2)}\n`)
for (const locale of ['fa-AF', 'ps-AF']) {
  const target = path.join(localeDir, `${locale}.json`)
  const existing = readLocale(locale)
  fs.writeFileSync(target, `${JSON.stringify(preserveTranslations(englishBase, existing), null, 2)}\n`)
}

console.log(`Migrated ${files.length} Vue files and generated ${keys.size} localized UI entries.`)
