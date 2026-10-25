import { apiFieldErrors, humanizeApiError, type ApiError } from '@/utils/apiErrors'
import { fieldLabel } from '@/utils/apiValidation'
import { translate, translateOrFallback } from '@/utils/localization'

// Match the labels shown by the editors, including fields in repeated cards.
export const settingFieldLabels: Record<string, string> = {
  website_title: 'pages.settings.settings.websiteTitle',
  website_logo: 'pages.settings.settings.uploadNewLogo',
  company_signature: 'pages.settings.settings.authorizedSignature',
  website_address: 'pages.settings.settings.websiteAddress',
  phone: 'common.fields.phone',
  physical_address: 'pages.settings.settings.physicalAddress',
  short_company_name: 'pages.settings.settings.shortCompanyName',
  original_company_name: 'pages.settings.settings.originalCompanyName',
  timezone: 'pages.settings.settings.companyTimezone',
  company_email: 'pages.settings.settings.fromEmailAddress',
  email_delivery_enabled: 'pages.settings.settings.emailDeliveryEnabled',
  mail_host: 'pages.settings.settings.smtpHost',
  mail_port: 'pages.settings.settings.smtpPort',
  mail_encryption: 'pages.settings.settings.encryption',
  mail_username: 'pages.settings.settings.smtpUsername',
  mail_password: 'pages.settings.settings.smtpPassword',
  clear_mail_password: 'pages.settings.settings.removeSavedSmtpPassword',
  mail_from_name: 'pages.settings.settings.fromName',
  mail_reply_to: 'pages.settings.settings.replyToEmail',
  paid_leave_days_per_year: 'pages.settings.settings.paidLeaveDaysPerYear',
  business_licence: 'pages.settings.settings.businessLicence',
  office_open_time: 'pages.settings.settings.officeOpens',
  office_close_time: 'pages.settings.settings.officeCloses',
  default_break_minutes: 'pages.settings.settings.defaultBreakMinutes',
  automatic_attendance_enabled: 'pages.settings.settings.automaticAttendanceEnabled',
  primary_color: 'pages.settings.settings.primaryColor',
  secondary_color: 'pages.settings.settings.secondaryColor',
  light_background_color: 'settingsValidation.lightBackground',
  light_surface_color: 'settingsValidation.lightSurface',
  dark_background_color: 'settingsValidation.darkBackground',
  dark_surface_color: 'settingsValidation.darkSurface',
  navigation_clr: 'pages.settings.settings.headerNavbar',
  cards_clr: 'pages.settings.settings.cardsSurfaces',
  footer_clr: 'pages.settings.settings.footerBar',
  contract_letterhead: 'pages.settings.settings.contractLetterhead',
  contract_cover_design: 'pages.settings.settings.contractCoverDesign',
  receipt_letterhead: 'pages.settings.settings.receiptLetterhead',
  payslip_letterhead: 'pages.settings.settings.payslipLetterhead',
  receipt_letterhead_source: 'pages.settings.settings.letterheadSource',
  payslip_letterhead_source: 'pages.settings.settings.letterheadSource',
  safe_x: 'pages.settings.settings.printSafeAreaOffsetX',
  safe_y: 'pages.settings.settings.printSafeAreaOffsetY',
  safe_width: 'pages.settings.settings.printSafeAreaWidth',
  safe_height: 'pages.settings.settings.printSafeAreaHeight',
  letterhead: 'pages.settings.settings.contractLetterhead',
  cover_design: 'pages.settings.settings.contractCoverDesign',
  contract_type_id: 'pages.settings.settings.contractTypeScope',
  service: 'pages.settings.settings.serviceScopeOptional',
  name: 'common.fields.name',
  code: 'pages.settings.directorycrudpage.officeCode',
  email: 'common.fields.email',
  address: 'common.fields.address',
  status: 'common.fields.status',
  latitude: 'pages.settings.geofence.latitude',
  longitude: 'pages.settings.geofence.longitude',
  geofence_radius_meters: 'pages.settings.geofence.radiusMeters',
  geofence_enabled: 'common.ui.requireStaffToClockInFromThisOffice',
  category: 'pages.support.category',
  severity: 'pages.support.severity',
  subject: 'pages.support.subject',
  description: 'pages.support.description',
  attachments: 'pages.support.attachments',
}

export function settingFieldLabel(field: string): string {
  const nested = field.match(/^attachments\.(\d+)$/)
  if (nested) return `${settingFieldLabel('attachments')} #${Number(nested[1]) + 1}`
  const safe = field.match(/^(contract|receipt|payslip)_letterhead_safe_(x|y|width|height)$/)
  if (safe)
    return `${settingFieldLabel(`${safe[1]}_letterhead`)}: ${settingFieldLabel(`safe_${safe[2]}`)}`
  if (field.startsWith('remove_'))
    return `${translate('pages.settings.settings.removeFile')}: ${settingFieldLabel(field.slice(7))}`

  return settingFieldLabels[field] ? translateOrFallback(settingFieldLabels[field], fieldLabel(field)) : fieldLabel(field)
}

/** Normalize terse client failures and retain descriptive, localized server text. */
export function settingsFieldErrors(error: unknown): Record<string, string> {
  const apiError = error as ApiError
  const messages = apiFieldErrors(apiError)
  return Object.fromEntries(Object.entries(messages).map(([field, message]) => {
    const value = apiError.errors?.[field]
    const code = Array.isArray(value) ? value[0] : value
    if (code === 'validation.required' || message === translate('backend.validation.required') || /^required\.?$/i.test(message.trim()))
      message = translate('settingsValidation.required', { field: settingFieldLabel(field) })
    if (field === 'website_address' && (code === 'validation.url' || message === translate('backend.validation.url')))
      message = translate('settingsValidation.websiteURL', { field: settingFieldLabel(field) })
    const label = settingFieldLabel(field)
    return [field, message.toLocaleLowerCase().includes(label.toLocaleLowerCase()) ? message : `${label}: ${message}`]
  }))
}

export function settingsErrorMessage(error: unknown, fallback: string): string {
  const messages = Object.values(settingsFieldErrors(error))
  if (messages.length)
    return messages.slice(0, 3).join('\n') + (messages.length > 3 ? `\n${translate('settingsValidation.moreErrors', { count: messages.length - 3 })}` : '')

  const apiError = error as ApiError
  return humanizeApiError(apiError?.code, apiError?.message || fallback)
}

export function settingsUploadError(file: File, field: string): string | null {
  const logo = field === 'website_logo'
  const limit = ['website_logo', 'company_signature'].includes(field) ? 2 : 8
  const types = ['image/jpeg', 'image/png', 'image/webp', ...(logo ? ['image/gif'] : [])]
  if (!types.includes(file.type))
    return translate('settingsValidation.image', { field: settingFieldLabel(field), types: logo ? 'JPG, PNG, WEBP, GIF' : 'JPG, PNG, WEBP' })
  if (file.size > limit * 1024 * 1024)
    return translate('settingsValidation.fileSize', { field: settingFieldLabel(field), size: limit })

  return null
}
