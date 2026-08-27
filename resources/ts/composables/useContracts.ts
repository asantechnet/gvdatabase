import { ref } from 'vue'
import ContractApi from '@/api/contracts'
import ContractTemplateApi, { type ContractTemplate } from '@/api/contractTemplate'
import type { Contract, ContractCreateData, ContractParams, ContractUpdateData } from '@/api/contracts'

export function useContracts() {
  const contracts = ref<Contract[]>([])
  const templates = ref<ContractTemplate[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  const fetchContracts = async (params?: ContractParams | boolean | 'customers' | 'employees' | 'companies' | 'other') => {
    try {
      isLoading.value = true
      error.value = null

      let request: ContractParams | undefined
      if (typeof params === 'object' && params !== null) {
        request = params
      } else if (params === true) {
        request = { only_cancelled: true }
      } else if (params === 'customers' || params === 'employees' || params === 'companies' || params === 'other') {
        request = { audience: params }
      }

      contracts.value = await ContractApi.getAll(request)
    } catch (err) {
      console.error('Failed to fetch contracts:', err)
      error.value = err instanceof Error ? err.message : 'Failed to fetch contracts'
    } finally {
      isLoading.value = false
    }
  }

  const fetchTemplates = async () => {
    try {
      isLoading.value = true
      error.value = null
      templates.value = await ContractTemplateApi.getAll()
    } catch (err) {
      console.error('Failed to fetch templates:', err)
      error.value = err instanceof Error ? err.message : 'Failed to fetch templates'
    } finally {
      isLoading.value = false
    }
  }

  const addContract = async (contractData: ContractCreateData) => {
    try {
      isLoading.value = true
      error.value = null
      const contract = await ContractApi.create(contractData)
      contracts.value.push(contract)
      return contract
    } catch (err) {
      console.error('Failed to create contract:', err)
      error.value = err instanceof Error ? err.message : 'Failed to create contract'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  const editContract = async (id: number, contractData: ContractUpdateData) => {
    try {
      isLoading.value = true
      error.value = null
      const contract = await ContractApi.update(id, contractData)
      const index = contracts.value.findIndex(c => c.id === id)
      if (index !== -1)
        contracts.value[index] = contract
      return contract
    } catch (err) {
      console.error('Failed to update contract:', err)
      error.value = err instanceof Error ? err.message : 'Failed to update contract'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  const removeContract = async (id: number) => {
    try {
      isLoading.value = true
      error.value = null
      await ContractApi.delete(id)
      contracts.value = contracts.value.filter(c => c.id !== id)
      return true
    } catch (err) {
      console.error('Failed to delete contract:', err)
      error.value = err instanceof Error ? err.message : 'Failed to delete contract'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  return {
    contracts,
    templates,
    isLoading,
    error,
    fetchContracts,
    fetchTemplates,
    addContract,
    editContract,
    removeContract,
  }
}
