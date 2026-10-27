import type { Contract } from '@/api/contracts'
import { formatLocalizedDate, formatLocalizedNumber, translate, translateBackendValue } from '@/utils/localization'
import { contractTypeSlug } from '@/utils/contractPartyDetails'

/**
 * Contract print header.
 *
 * The optional printed contract header surfaces the key contract + counterparty
 * facts on the first page. It is assembled at print time from the frozen
 * snapshot when one exists, so a reprint of an issued contract stays stable,
 * with document fields supplying values outside the frozen snapshot.
 */

type RecordValue = Record<string, unknown>

export interface ContractHeaderRow {
  label: string
  value: string
}

export interface ContractHeaderSection {
  title: string
  rows: ContractHeaderRow[]
}

export interface ContractHeaderMeta {
  label: string
  value: string
}

export interface ContractPrintHeader {
  title: string
  date: string | null
  endDate: string | null
  meta: ContractHeaderMeta[]
  sections: ContractHeaderSection[]
}

const asRecord = (value: unknown): RecordValue =>
  value && typeof value === 'object' && !Array.isArray(value) ? value as RecordValue : {}

const firstText = (...values: unknown[]): string | null => {
  for (const value of values) {
    if (typeof value === 'string' || typeof value === 'number') {
      const normalized = String(value).trim()
      if (normalized)
        return normalized
    }
  }

  return null
}

const rows = (items: Array<[string, string | null]>): ContractHeaderRow[] =>
  items.flatMap(([label, value]) => value ? [{ label, value }] : [])

const formatAmount = (value: unknown): string | null => {
  const amount = Number(value)
  if (!Number.isFinite(amount) || amount <= 0)
    return null

  return formatLocalizedNumber(amount, 'decimal')
}

/** Group service fees per currency so multi-currency contracts stay readable. */
const amountPayable = (contract: Contract): string | null => {
  const entity = asRecord(asRecord(contract.snapshot_data).entity)
  const lines = Array.isArray(entity.services) ? entity.services : contract.services ?? []
  const totals = new Map<string, number>()

  for (const line of lines) {
    const fee = Number(line.fee ?? 0) - Number(line.discount ?? 0)
    if (!Number.isFinite(fee) || fee <= 0)
      continue

    const currency = line.currency as { code?: string | null; symbol?: string | null } | null | undefined
    const code = firstText(line.currency_code, currency?.code, currency?.symbol) ?? ''
    totals.set(code, (totals.get(code) ?? 0) + fee)
  }

  if (totals.size === 0)
    return null

  return [...totals.entries()]
    .map(([code, total]) => `${formatLocalizedNumber(total, 'decimal')}${code ? ` ${code}` : ''}`)
    .join(' + ')
}

/** Human-readable duration derived from the optional start/end span. */
const deriveDuration = (start: string | null | undefined, end: string | null | undefined, label: (key: string) => string): string | null => {
  if (!start || !end)
    return null

  const from = new Date(`${start}T00:00:00`)
  const to = new Date(`${end}T00:00:00`)
  if (Number.isNaN(from.getTime()) || Number.isNaN(to.getTime()) || to < from)
    return null

  const days = Math.round((to.getTime() - from.getTime()) / 86_400_000)
  if (days < 31)
    return `${formatLocalizedNumber(days)} ${label('days')}`

  const months = Math.round(days / 30.44)
  if (months < 12)
    return `${formatLocalizedNumber(months)} ${label('months')}`

  const years = Math.floor(months / 12)
  const remainder = months % 12
  const parts = [`${formatLocalizedNumber(years)} ${label(years === 1 ? 'year' : 'years')}`]
  if (remainder)
    parts.push(`${formatLocalizedNumber(remainder)} ${label('months')}`)

  return parts.join(' ')
}

/** The contract details header is available for every contract type. */
export const canPrintContractHeader = (contract: Contract | null | undefined): boolean => !!contract

export const contractPrintTitle = (contract: Contract): string =>
  contract.contract
  || contract.contract_template?.name
  || translate('pages.contracts.allContracts.allContracts.untitledContract')

export const buildContractPrintHeader = (contract: Contract): ContractPrintHeader | null => {
  if (!canPrintContractHeader(contract))
    return null

  const snapshot = asRecord(contract.snapshot_data)
  const entity = asRecord(snapshot.entity)
  const slug = contractTypeSlug(contract)
  const label = (key: string) => translate(`pages.contracts.shared.header.${key}`)

  const meta = rows([
    [label('contractNumber'), firstText(contract.contract_number)],
    [label('contractType'), firstText(contract.contract_type?.name, contract.type)],
    [label('status'), contract.status ? translateBackendValue(contract.status) : null],
  ]).map(({ label: metaLabel, value }) => ({ label: metaLabel, value }))

  const sections: ContractHeaderSection[] = []

  if (slug === 'employment') {
    const employee = asRecord(contract.employee)
    const employeeSnapshot = asRecord(entity.employee)
    const emergency = [
      firstText(employeeSnapshot.emergency_contact_name, employee.emergency_contact_name),
      firstText(employeeSnapshot.emergency_contact_phone, employee.emergency_contact_phone),
    ].filter(Boolean).join(' · ') || null

    const salary = firstText(employeeSnapshot.salary, employee.salary)
    const paidLeave = firstText(employeeSnapshot.paid_leave_days, employee.off_day_allowance_days)
    const period = contract.end_date && contract.start_date
      ? `${formatLocalizedDate(contract.start_date)} — ${formatLocalizedDate(contract.end_date)}`
      : null
    const duration = deriveDuration(contract.start_date, contract.end_date, label)
      ?? firstText(employeeSnapshot.contract_duration, employee.contract_duration)

    sections.push({
      title: label('employeeDetails'),
      rows: rows([
        [label('employeeName'), firstText(employeeSnapshot.name, employee.name, contract.counterparty)],
        [label('education'), firstText(employeeSnapshot.education, employee.education_lvl)],
        [label('phone'), firstText(employeeSnapshot.phone, employee.contact)],
        [label('emergencyContact'), emergency],
        [label('nationalId'), firstText(employeeSnapshot.national_id, employee.id_number)],
        [label('position'), firstText(employeeSnapshot.position, employee.position)],
        [label('salary'), salary === null ? null : formatAmount(salary)],
        [label('contractDate'), contract.start_date ? formatLocalizedDate(contract.start_date) : null],
        [label('contractPeriod'), period],
        [label('contractDuration'), duration],
        [label('paidLeavePeriod'), paidLeave === null
          ? null
          : `${formatLocalizedNumber(paidLeave, 'decimal')} ${label('daysPerYear')}`],
      ]),
    })
  }

  if (slug === 'customer') {
    const applicant = asRecord(entity.applicant)
    const details = asRecord(entity.details)
    const level = asRecord(details.education_level)
    const semester = asRecord(details.semester)
    const percentage = firstText(details.grade_score)

    sections.push({
      title: label('applicantDetails'),
      rows: rows([
        [label('applicantName'), firstText(applicant.name, contract.counterparty)],
        [label('fatherName'), firstText(applicant.father_name)],
        [label('educationLevel'), firstText(level.name)],
        [label('phone'), firstText(applicant.contact, contract.counterparty_contact)],
        [label('semester'), firstText(semester.name)],
        [label('educationScore'), percentage === null ? null : `${formatLocalizedNumber(percentage, 'decimal')}%`],
        [label('amountPayable'), amountPayable(contract)],
      ]),
    })
  }

  // Other and custom agreements use the frozen free-text counterparty.
  if (slug !== 'employment' && slug !== 'customer') {
    const parties = asRecord(snapshot.parties ?? contract.parties)
    const counterparty = asRecord(parties.counterparty)
    const partyFields = asRecord(counterparty.fields)

    sections.push({
      title: label('counterpartyDetails'),
      rows: rows([
        [label('counterpartyName'), firstText(partyFields.counterparty, contract.counterparty)],
        [label('phone'), firstText(partyFields.counterparty_contact, contract.counterparty_contact)],
        [label('email'), firstText(partyFields.counterparty_email, contract.counterparty_email)],
        [label('address'), firstText(partyFields.counterparty_address, contract.counterparty_address)],
      ]),
    })
  }

  return {
    title: contractPrintTitle(contract),
    date: contract.start_date ? formatLocalizedDate(contract.start_date) : null,
    endDate: contract.end_date ? formatLocalizedDate(contract.end_date) : null,
    meta,
    sections,
  }
}
