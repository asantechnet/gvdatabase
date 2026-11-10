import type { ApplicantContractPaymentOption, Income, IncomeUpdateData } from '@/api/incomes'

export interface ServicePaymentEntry {
  line: ApplicantContractPaymentOption
  selected: boolean
  amount: number | string
  rate: number | string
  state: 'pending' | 'saving' | 'saved' | 'failed' | 'uncertain'
  error?: unknown
  receipt?: Income
}

export function servicePaymentIssue(entry: ServicePaymentEntry): string | null {
  if (entry.line.is_cancelled || !entry.line.currency || entry.line.remaining_total <= 0)
    return 'unavailable'
  if (entry.amount === '' || !Number.isFinite(Number(entry.amount)) || Number(entry.amount) <= 0)
    return 'amount'
  if (entry.rate === '' || !Number.isFinite(Number(entry.rate)) || Number(entry.rate) < 0.00000001)
    return 'rate'
  if (Number(entry.amount) > paymentEntryLimit(entry.line, Number(entry.rate)) + 0.00001)
    return 'balance'

  return null
}

/** Stop on failure and never resubmit a completed or unconfirmed request. */
export async function submitServicePayments(entries: ServicePaymentEntry[], write: (entry: ServicePaymentEntry) => Promise<Income>, onCreated: (income: Income) => void): Promise<void> {
  for (const entry of entries.filter(item => item.selected && item.state !== 'saved' && item.state !== 'uncertain')) {
    entry.state = 'saving'
    entry.error = undefined
    try {
      entry.receipt = await write(entry)
    }
    catch (error) {
      const failure = error as { status?: number; code?: string }
      const rejected = (failure?.status && failure.status >= 400 && failure.status < 500)
        || failure?.code === 'validation_failed' || failure?.code === 'auth.forbidden'
      entry.state = rejected ? 'failed' : 'uncertain'
      entry.error = error

      return
    }
    entry.state = 'saved'
    onCreated(entry.receipt)
  }
}

export function paymentEntryLimit(line: ApplicantContractPaymentOption, receiptRate: number, original?: Income): number {
  const editingCredit = original?.applicant_contract_id === line.id
    ? Number(original.amount) * original.quantity / Number(original.rate) * line.currency_rate
    : 0

  const remaining = Math.max(0, line.payable_total - Math.max(0, line.amount_paid - editingCredit))

  return remaining / line.currency_rate * receiptRate
}

export function changedIncomeFields(original: Income, edited: IncomeUpdateData): IncomeUpdateData {
  const keys = ['applicant_id', 'student_id', 'applicant_contract_id', 'currency_id', 'type', 'description', 'amount', 'quantity', 'rate', 'date'] as const
  const numericKeys = new Set<string>(['applicant_id', 'student_id', 'applicant_contract_id', 'currency_id', 'amount', 'quantity', 'rate'])

  return Object.fromEntries(keys.filter(key => {
    if (edited[key] === undefined)
      return false
    const before = original[key]
    const after = edited[key]

    return numericKeys.has(key) && before != null && after != null
      ? Number(before) !== Number(after)
      : before !== after
  }).map(key => [key, edited[key]])) as IncomeUpdateData
}
