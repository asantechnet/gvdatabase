import type { FinanceType } from '@/api/financeTypes'
import { translateBackendValue } from '@/utils/localization'

const defaultNames = new Set(['Service', 'Product', 'Investment', 'Other', 'Utilities', 'Software/SaaS', 'Marketing', 'Office Supplies', 'Travel & Dining', 'Salaries', 'Pending transaction'])

export const financeTypeLabel = (type: FinanceType): string =>
  type.name === type.value && defaultNames.has(type.name) ? translateBackendValue(type.name) : type.name
