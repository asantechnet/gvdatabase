import api from './axios'

export type SearchResultType = 'applicant' | 'customer' | 'contract'

export interface SearchHit {
  type: SearchResultType
  id: number
  title: string
  subtitle: string
  route: { name: string; params?: Record<string, number | string> }
}

export type SearchGroup = 'applicants' | 'customers' | 'contracts'

export interface SearchResponse {
  query: string
  groups: Partial<Record<SearchGroup, SearchHit[]>>
}

export default {
  query: async (q: string, limit = 8) => (await api.get('/search', { params: { q, limit } })).data.data as SearchResponse,
}
