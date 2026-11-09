import api from './axios'
import type { AcademyEnrollmentRecord } from './academyEnrollments'

export interface AcademyStudentPayload {
  name: string
  father_name?: string | null
  father_phone?: string | null
  contact?: string | null
  email?: string | null
  address?: string | null
  notes?: string | null
  class_id: number
  started_on?: string | null
  ends_on?: string | null
}

export interface AcademyStudentFinanceCurrency {
  id: number
  code: string
  symbol: string | null
  decimal_places: number
}

export interface AcademyStudentFinanceIncome {
  id: number
  receipt_number: string | null
  type: string
  description: string | null
  amount: number
  quantity: number
  line_total: number
  date: string | null
  status: 'draft' | 'approved' | 'rejected'
  applicant_contract_id: number | null
  currency: AcademyStudentFinanceCurrency | null
}

export interface AcademyStudentFinance {
  student: {
    id: number
    student_number: string | null
    name: string | null
    father_name: string | null
    father_phone: string | null
    contact: string | null
    email: string | null
    address: string | null
    applicant_id: number | null
  }
  totals: Array<{ currency: AcademyStudentFinanceCurrency | null; amount: number }>
  incomes: AcademyStudentFinanceIncome[]
}

export const AcademyStudentsApi = {
  async create(payload: AcademyStudentPayload): Promise<AcademyEnrollmentRecord> {
    return (await api.post('/academy/students', payload)).data.data
  },

  async getFinance(studentId: number): Promise<AcademyStudentFinance> {
    return (await api.get(`/academy/students/${studentId}/finance`)).data.data
  },
}
