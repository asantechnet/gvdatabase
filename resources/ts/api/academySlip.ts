import api from './axios'
import type { ReceiptMoney } from './receipts'

export interface AcademySlipCompany {
  name: string | null
  short_name: string | null
  primary_color: string
  secondary_color: string
  logo_url: string | null
  signature_url: string | null
  address: string | null
  phone: string | null
  email: string | null
  website: string | null
  licence: string | null
}

export interface AcademySlipApplicant {
  id: number
  name: string
  father_name: string | null
  contact: string | null
  father_phone: string | null
  email: string | null
  address: string | null
}

export interface AcademySlipAcademy {
  student_id: string | null
  program: string | null
  time: string | null
  duration: string | null
  class: string | null
  teachers: string[]
  started_on: string | null
  ends_on: string | null
}

export interface AcademySlipPayload {
  reference: string | null
  document: 'academy_slip'
  date: string | null
  company: AcademySlipCompany
  applicant: AcademySlipApplicant | null
  contract: { id: number; contract_number: string | null }
  academy: AcademySlipAcademy
  service: {
    id: number
    name: string
    service_label: string
    fee: number
    discount: number
    payment_status: string
    payment_status_label: string
    currency: ReceiptMoney | null
  } | null
  totals: {
    total_fee: number
    paid_amount: number
    balance: number
    currency: ReceiptMoney | null
  }
  signature: { url: string | null; name: string | null }
}

const AcademySlipApi = {
  async getForContract(contractId: number): Promise<AcademySlipPayload> {
    const response = await api.get<{ success: boolean; data: AcademySlipPayload }>(`/contracts/${contractId}/academy-slip`)

    return response.data.data
  },
}

export default AcademySlipApi
