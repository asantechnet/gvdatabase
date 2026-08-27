import api from './axios'

export interface PaymentDetail {
  id: number
  method: string
  instruction: string
  created_at: string
  updated_at: string
}

export type PaymentDetailPayload = Pick<PaymentDetail, 'method' | 'instruction'>

const PaymentDetailApi = {
  async getAll(): Promise<PaymentDetail[]> {
    const response = await api.get('/payment-details')
    return response.data.data
  },

  async create(payload: PaymentDetailPayload): Promise<PaymentDetail> {
    const response = await api.post('/payment-details', payload)
    return response.data.data
  },

  async update(id: number, payload: PaymentDetailPayload): Promise<PaymentDetail> {
    const response = await api.put(`/payment-details/${id}`, payload)
    return response.data.data
  },

  async delete(id: number): Promise<void> {
    await api.delete(`/payment-details/${id}`)
  },
}

export default PaymentDetailApi
