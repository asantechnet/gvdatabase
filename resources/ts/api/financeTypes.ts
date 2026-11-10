import api from './axios'

export type FinanceTypeKind = 'income' | 'expense'
export interface FinanceType {
  id: number
  kind: FinanceTypeKind
  value: string
  name: string
  is_system: boolean
  created_at?: string | null
}

export const getFinanceTypes = async (kind: FinanceTypeKind): Promise<FinanceType[]> =>
  (await api.get<{ data: FinanceType[] }>(`/finance-types/${kind}`)).data.data

export const saveFinanceType = async (kind: FinanceTypeKind, name: string, id?: number): Promise<FinanceType> => {
  const response = id
    ? await api.put<{ data: FinanceType }>(`/finance-types/${kind}/${id}`, { name })
    : await api.post<{ data: FinanceType }>(`/finance-types/${kind}`, { name })

  return response.data.data
}

export const deleteFinanceType = async (kind: FinanceTypeKind, id: number): Promise<void> => {
  await api.delete(`/finance-types/${kind}/${id}`)
}
