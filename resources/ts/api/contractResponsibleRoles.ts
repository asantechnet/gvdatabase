import api from './axios'

export interface ResponsibleRolePosition {
  id: number
  title: string
}

export interface ContractResponsibleRoles {
  positions: ResponsibleRolePosition[]
  selected_ids: number[]
}

const ContractResponsibleRolesApi = {
  get: async (): Promise<ContractResponsibleRoles> => {
    const response = await api.get('/contract-settings/responsible-roles')

    return response.data.data as ContractResponsibleRoles
  },
  update: async (positionIds: number[]): Promise<ContractResponsibleRoles> => {
    const response = await api.put('/contract-settings/responsible-roles', { position_ids: positionIds })

    return response.data.data as ContractResponsibleRoles
  },
}

export default ContractResponsibleRolesApi
