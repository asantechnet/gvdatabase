import { translate } from '@/utils/localization'
import { ref } from 'vue'
import ContractApi from '@/api/contracts'
import ContractTemplateApi, { type ContractTemplate } from '@/api/contractTemplate'
import type { Contract, ContractCreateData, ContractParams, ContractUpdateData } from '@/api/contracts'
import type { ListPage, ListPageParams } from '@/api/pagination'

export function useContracts() {
  const contracts = ref<Contract[]>([])
  const templates = ref<ContractTemplate[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  const fetchContracts = async (params?: ContractParams | boolean | 'customers' | 'employees' | 'other') => {
    try {
      isLoading.value = true
      error.value = null

      let request: ContractParams | undefined
      if (typeof params === 'object' && params !== null)
        request = params
      else if (params === true)
        request = { only_cancelled: true }
      else if (params === 'customers' || params === 'employees' || params === 'other')
        request = { audience: params }

      contracts.value = await ContractApi.getAll(request)
    }
    catch (err) {
      console.error('Failed to fetch contracts:', err)
      error.value = err instanceof Error ? err.message : translate('common.ui.failedToFetchContracts')
    }
    finally {
      isLoading.value = false
    }
  }

  /** One page of a contract list, for the paged tables. */
  const listContracts = async (params?: ContractParams & ListPageParams): Promise<ListPage<Contract>> => {
    try {
      isLoading.value = true
      error.value = null

      const result = await ContractApi.listPage(params)

      contracts.value = result.rows

      return result
    }
    catch (err) {
      console.error('Failed to fetch contracts:', err)
      error.value = err instanceof Error ? err.message : translate('common.ui.failedToFetchContracts')
      throw err
    }
    finally {
      isLoading.value = false
    }
  }

  const fetchTemplates = async () => {
    try {
      isLoading.value = true
      error.value = null
      templates.value = (await ContractTemplateApi.getAll()).filter(template => !template.service && template.type_slug !== 'customer')
    }
    catch (err) {
      console.error('Failed to fetch templates:', err)
      error.value = err instanceof Error ? err.message : translate('common.ui.failedToFetchTemplates')
    }
    finally {
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
    }
    catch (err) {
      console.error('Failed to create contract:', err)
      error.value = err instanceof Error ? err.message : translate('common.ui.failedToCreateContract')
      throw err
    }
    finally {
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
    }
    catch (err) {
      console.error('Failed to update contract:', err)
      error.value = err instanceof Error ? err.message : translate('common.ui.failedToUpdateContract')
      throw err
    }
    finally {
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
    }
    catch (err) {
      console.error('Failed to delete contract:', err)
      error.value = err instanceof Error ? err.message : translate('common.ui.failedToDeleteContract')
      throw err
    }
    finally {
      isLoading.value = false
    }
  }

  return {
    contracts,
    templates,
    isLoading,
    error,
    fetchContracts,
    listContracts,
    fetchTemplates,
    addContract,
    editContract,
    removeContract,
  }
}
