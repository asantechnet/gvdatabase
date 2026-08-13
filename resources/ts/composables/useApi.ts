import api from '@/api/axios'

// Export a function that returns the HTTP method wrappers
export const useApi = () => {
  const get = <T>(url: string) => {
    return api.get<T>(url).then(response => response.data.data)
  }

  const post = <T>(url: string, payload?: any) => {
    return api.post<T>(url, payload).then(response => response.data.data)
  }

  const put = <T>(url: string, payload?: any) => {
    return api.put<T>(url, payload).then(response => response.data.data)
  }

  const destroy = <T>(url: string) => {
    return api.delete<T>(url).then(response => response.data.data)
  }

  return {
    get,
    post,
    put,
    delete: destroy,
  }
}
