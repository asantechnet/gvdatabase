import type { Contract } from '@/api/contracts'
import { legacyContentToHtml } from '@/utils/contractPlaceholders'
import { formatLocalizedDate, translate, translateBackendValue } from '@/utils/localization'

export interface OfficialCompanyDetails {
  brandName: string
  logo?: string
  address?: string
  phone?: string
  email?: string
  website?: string
  signatureUrl?: string
  letterheadUrl?: string
  letterheadSafeX?: number
  letterheadSafeY?: number
  letterheadSafeWidth?: number
  letterheadSafeHeight?: number
}

export interface OfficialPrintField {
  label: string
  value: string
}

export interface OfficialContractPrintData {
  title: string
  content: string
  direction: 'ltr' | 'rtl'
  metadata: OfficialPrintField[]
  applicantFields: OfficialPrintField[]
  applicantSignatureLabel: string
}

type RecordValue = Record<string, unknown>

const record = (value: unknown): RecordValue =>
  value && typeof value === 'object' && !Array.isArray(value) ? value as RecordValue : {}

const value = (...values: unknown[]): string | null => {
  for (const item of values) {
    if (typeof item === 'string' || typeof item === 'number') {
      const normalized = String(item).trim()
      if (normalized)
        return normalized
    }
  }

  return null
}

const named = (item: unknown): string | null => value(record(item).name, item)

const list = (item: unknown): string | null => {
  if (!Array.isArray(item))
    return value(item)

  const items = item.map(entry => named(entry)).filter((entry): entry is string => Boolean(entry))

  return items.length ? items.join(', ') : null
}

const field = (labelKey: string, ...values: unknown[]): OfficialPrintField[] => {
  const normalized = value(...values)

  return normalized ? [{ label: translate(`pages.contracts.shared.officialPrint.${labelKey}`), value: normalized }] : []
}

/**
 * Build only the fields relevant to the issued applicant service. Newer
 * contracts contain the human-readable `print_details` snapshot; older
 * contracts safely fall back to the relations returned by the API.
 */
export const buildOfficialContractPrintData = (contract: Contract, activeLocale: string): OfficialContractPrintData => {
  const snapshot = record(contract.snapshot_data)
  const entity = record(snapshot.entity)
  const frozen = record(entity.print_details)
  const frozenApplicant = record(entity.applicant)
  const applicant = record(contract.applicant)
  const details = record(frozen.details)
  const liveDetails = record(applicant.details)
  const service = value(contract.service, entity.service, applicant.service)

  const title = value(contract.contract, contract.contract_template?.name)
    ?? translate('pages.contracts.allContracts.allContracts.untitledContract')

  const languageCode = value(record(snapshot.template_version).language_code)
  const isRtlLanguage = /^(?:ar|fa|ps|ur)(?:[-_]|$)/i.test(languageCode ?? activeLocale)
  const applicantName = value(frozen.name, frozenApplicant.name, applicant.name, contract.counterparty)

  const serviceLabel = service
    ? translate(`educational.builtInServices.${service === 'test_offer' ? 'testOffer' : service}`)
    : contract.type

  const metadata = [
    ...field('referenceNumber', contract.contract_number),
    ...field('issueDate', contract.start_date ? formatLocalizedDate(contract.start_date) : null),
    ...field('service', serviceLabel),
    ...field('status', contract.status ? translateBackendValue(contract.status) : null),
  ]

  // Other agreements are intentionally free-form. They have no applicant
  // section and must never be populated with company/counterparty fields.
  if (!service) {
    return {
      title,
      content: legacyContentToHtml(contract.content || contract.contract_template?.content || '') || '',
      direction: isRtlLanguage ? 'rtl' : 'ltr',
      metadata,
      applicantFields: [],
      applicantSignatureLabel: translate('pages.contracts.shared.officialPrint.counterpartySignature'),
    }
  }

  const common = [
    ...field('applicantName', applicantName),
    ...field('fatherName', frozen.father_name, frozenApplicant.father_name, applicant.father_name),
    ...field('phone', frozen.phone, frozen.contact, frozenApplicant.contact, applicant.contact),
    ...field('email', frozen.email, frozenApplicant.email, applicant.email),
  ]

  const frozenDetails = record(frozen.details)

  const detailsValue = (key: string, liveKey = key) => value(
    frozen[key],
    frozenDetails[key],
    details[key],
    named(liveDetails[liveKey]),
    liveDetails[liveKey],
  )

  let applicantFields = common

  if (service === 'private') {
    applicantFields = [
      ...common,
      ...field('passportId', detailsValue('passport_id')),
      ...field('destinationCountry', detailsValue('country')),
      ...field('university', detailsValue('university')),
      ...field('educationLevel', detailsValue('education_level')),
      ...field('semester', detailsValue('semester')),
      ...field('educationScore', detailsValue('grade_score')),
    ]
  }
  else if (service === 'scholarship') {
    applicantFields = [
      ...common,
      ...field('passportId', detailsValue('passport_id')),
      ...field('destinationCountry', detailsValue('country')),
      ...field('educationLevel', detailsValue('education_level')),
      ...field('semester', detailsValue('semester')),
      ...field('scholarship', detailsValue('scholarship')),
      ...field('subjects', value(frozen.subjects, list(applicant.favorite_subjects))),
    ]
  }
  else if (service === 'test_offer') {
    applicantFields = [
      ...common,
      ...field('test', detailsValue('test')),
      ...field('skills', value(frozen.skills, list(applicant.skills))),
    ]
  }
  else if (service === 'academy') {
    applicantFields = [
      ...common,
      ...field('studentId', detailsValue('student_id')),
      ...field('program', detailsValue('program')),
      ...field('academyClass', detailsValue('academy_class')),
      ...field('time', detailsValue('time')),
      ...field('duration', detailsValue('duration')),
      ...field('teachers', value(frozen.teachers, list(applicant.teachers))),
    ]
  }

  return {
    title,
    content: legacyContentToHtml(contract.content || contract.contract_template?.content || '') || '',
    direction: isRtlLanguage ? 'rtl' : 'ltr',
    metadata,
    applicantFields,
    applicantSignatureLabel: translate('pages.contracts.shared.officialPrint.applicantSignature'),
  }
}

export interface OfficialPrintMargins {
  top: number
  right: number
  bottom: number
  left: number
}

const clamp = (number: number, minimum: number, maximum: number) => Math.min(Math.max(number, minimum), maximum)

/** Convert a configured letterhead safe zone into physical A4 print margins. */
export const officialPrintMargins = (company: OfficialCompanyDetails, withLetterhead: boolean): OfficialPrintMargins => {
  if (!withLetterhead)
    return { top: 36, right: 18, bottom: 28, left: 18 }

  const x = clamp(Number(company.letterheadSafeX ?? 8.5), 0, 95)
  const y = clamp(Number(company.letterheadSafeY ?? 15), 0, 95)
  const width = clamp(Number(company.letterheadSafeWidth ?? 83), 5, 100 - x)
  const height = clamp(Number(company.letterheadSafeHeight ?? 70), 5, 100 - y)

  return {
    top: y * 2.97,
    right: (100 - x - width) * 2.1,
    bottom: (100 - y - height) * 2.97,
    left: x * 2.1,
  }
}
