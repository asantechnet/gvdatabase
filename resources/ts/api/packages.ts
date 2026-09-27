import api from './axios'

export interface PackageService { id: number; type: string; category: 'general' | 'scholarship' | 'test_offer'; fee: number; currency?: { id: number; code: string } }
export interface ServicePackage { id: number; name: string; description?: string | null; fee?: number | null; currency_id?: number | null; is_active: boolean; services: PackageService[] }
export interface PackageInput { name: string; description?: string | null; fee?: number | null; currency_id?: number | null; is_active?: boolean; service_ids: number[] }
const unwrap = <T>(response: { data: { data: T } }) => response.data.data

export default {
  list: async () => unwrap<ServicePackage[]>(await api.get('/packages')),
  create: async (data: PackageInput) => unwrap<ServicePackage>(await api.post('/packages', data)),
  update: async (id: number, data: Partial<PackageInput>) => unwrap<ServicePackage>(await api.put(`/packages/${id}`, data)),
  remove: async (id: number) => api.delete(`/packages/${id}`),
  issue: async (id: number, data: { applicant_id: number; sold_by_employee_id?: number | null; fee?: number | null; currency_id?: number | null; purchased_at: string; notes?: string | null }) => unwrap(await api.post(`/packages/${id}/issue`, data)),
}
