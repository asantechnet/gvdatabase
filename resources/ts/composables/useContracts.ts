import { ref } from 'vue'
import ContractApi, {
  type Contract,
  type ContractCreateData,
  type ContractUpdateData,
  type ContractTemplate
} from '@/api/contracts'

export function useContracts() {
  const contracts = ref<Contract[]>([])
  const templates = ref<ContractTemplate[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  const fetchContracts = async (onlyCancelled?: boolean) => {
    try {
      isLoading.value = true
      error.value = null
      const response = await ContractApi.getAll(onlyCancelled)
      contracts.value = response.data
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
      const response = await ContractApi.getTemplates()
      templates.value = response.data
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
      const response = await ContractApi.create(contractData)
      contracts.value.push(response.data)
      return response.data
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
      const response = await ContractApi.update(id, contractData)
      const index = contracts.value.findIndex(c => c.id === id)
      if (index !== -1) {
        contracts.value[index] = response.data
      }
      return response.data
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
      const response = await ContractApi.delete(id)
      if (response.success) {
        contracts.value = contracts.value.filter(c => c.id !== id)
      }
      return response.success
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
    removeContract
  }
}
