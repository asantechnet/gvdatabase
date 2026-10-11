import api from './axios'

export type EducationalCatalog =
  | 'countries'
  | 'education-levels'
  | 'languages'

  | 'universities'
  | 'semesters'
  | 'subjects'
  | 'tests'
  | 'skills'
  | 'programs'
  | 'times'
  | 'durations'
  | 'teachers'
  | 'scholarships'
  | 'classes'

export interface MasterDataRecord {
  id: number
  name: string
  code?: string
  description?: string | null
  service_type?: string | null
  country_id?: number | null
  city?: string | null
  website?: string | null
  contact?: string | null
  email?: string | null
  sort_order?: number
  is_active: boolean
}

const base = (catalog: EducationalCatalog) => `/educational-master-data/${catalog}`

export default {
  list: async (catalog: EducationalCatalog) => (await api.get(base(catalog))).data.data as MasterDataRecord[],
  create: async (catalog: EducationalCatalog, data: Partial<MasterDataRecord>) => (await api.post(base(catalog), data)).data.data as MasterDataRecord,
  update: async (catalog: EducationalCatalog, id: number, data: Partial<MasterDataRecord>) => (await api.put(`${base(catalog)}/${id}`, data)).data.data as MasterDataRecord,
  remove: async (catalog: EducationalCatalog, id: number) => api.delete(`${base(catalog)}/${id}`),
}
