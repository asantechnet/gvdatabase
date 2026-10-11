import type { Contract } from '@/api/contracts'
import type { CompanyDetails } from '@/pages/templates/contract_page_template.vue'

export interface PartyDetail {
  label: string
  value: string
}

export interface PartyDetails {
  ourCompany: PartyDetail[]
  counterparty: PartyDetail[]
}

type RecordValue = Record<string, unknown>

const systemContractTypes = new Set(['employment', 'customer', 'other'])

const asRecord = (value: unknown): RecordValue =>
  value && typeof value === 'object' && !Array.isArray(value) ? value as RecordValue : {}

const text = (value: unknown): string | null => {
  if (typeof value !== 'string' && typeof value !== 'number')
    return null

  const normalized = String(value).trim()

  return normalized || null
}

const fields = (items: Array<[string, unknown]>): PartyDetail[] =>
  items.flatMap(([label, value]) => {
    const normalized = text(value)

    return normalized ? [{ label, value: normalized }] : []
  })

export const contractTypeSlug = (contract: Contract): string =>
  (contract.type_slug ?? contract.contract_template?.type_slug ?? contract.type ?? '').toLowerCase()

/** Party details are a print-only feature for supported system agreements. */
export const canPrintPartyDetails = (contract: Contract): boolean =>
  !contract.is_custom && systemContractTypes.has(contractTypeSlug(contract))

/**
 * Prefer contract snapshots so a reprint reflects the parties' data when the
 * agreement was issued; document fields supply any values outside the snapshot.
 */
export const partyDetailsFor = (contract: Contract, company: CompanyDetails): PartyDetails => {
  const snapshot = asRecord(contract.snapshot_data)
  const parties = asRecord(snapshot.parties ?? contract.parties)
  const ourCompany = asRecord(parties.our_company)
  const entity = asRecord(snapshot.entity)
  const type = contractTypeSlug(contract)

  const ourCompanyDetails = fields([
    ['Company', ourCompany.name ?? company.brandName],
    ['Registration / licence', ourCompany.business_licence ?? company.licence],
    ['Email', ourCompany.email ?? company.email],
    ['Phone', ourCompany.phone ?? company.phone],
    ['Address', ourCompany.address ?? company.address],
  ])

  if (type === 'employment') {
    const employee = asRecord(entity.employee ?? contract.employee)

    return {
      ourCompany: ourCompanyDetails,
      counterparty: fields([
        ['Employee', employee.name ?? contract.counterparty],
        ['Father name', employee.father_name],
        ['National ID', employee.national_id ?? employee.id_number],
        ['Position', employee.position ?? contract.counterparty_company],
        ['Department', employee.department],
        ['Office', employee.office],
        ['Phone', employee.phone ?? employee.contact ?? contract.counterparty_contact],
        ['Email', employee.email ?? contract.counterparty_email],
        ['Address', employee.address ?? employee.addr ?? contract.counterparty_address],
      ]),
    }
  }

  if (type === 'customer') {
    const applicant = asRecord(entity.applicant)
    const party = asRecord(parties.counterparty)

    return {
      ourCompany: ourCompanyDetails,
      counterparty: fields([
        ['Applicant', applicant.name ?? party.name ?? contract.counterparty],
        ['Father name', applicant.father_name],
        ['Phone', applicant.contact ?? party.phone ?? contract.counterparty_contact],
        ['Email', applicant.email ?? party.email ?? contract.counterparty_email],
        ['Address', applicant.address ?? party.address ?? contract.counterparty_address],
      ]),
    }
  }

  const counterparty = asRecord(asRecord(parties.counterparty).fields)

  return {
    ourCompany: ourCompanyDetails,
    counterparty: fields([
      ['Counterparty', counterparty.counterparty ?? contract.counterparty],
      ['Phone', counterparty.counterparty_contact ?? contract.counterparty_contact],
      ['Email', counterparty.counterparty_email ?? contract.counterparty_email],
      ['Address', counterparty.counterparty_address ?? contract.counterparty_address],
    ]),
  }
}
