import { ofetch } from 'ofetch'

// Create a base fetch instance with token handling
const baseFetch = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  async onRequest({ options }) {
    const accessToken = useCookie('accessToken').value
    if (accessToken) {
      options.headers = new Headers(options.headers)
      options.headers.set('Authorization', `Bearer ${accessToken}`)
    }
  },
})

// Export a function that returns the HTTP method wrappers
export const useApi = () => {
  const get = <T>(url: string) => {
    return baseFetch<T>(url)
  }

  const post = <T>(url: string, payload?: any) => {
    return baseFetch<T>(url, {
      method: 'POST',
      body: payload,
    })
  }

  const put = <T>(url: string, payload?: any) => {
    return baseFetch<T>(url, {
      method: 'PUT',
      body: payload,
    })
  }

  const destroy = <T>(url: string) => {
    return baseFetch<T>(url, {
      method: 'DELETE',
    })
  }

  return {
    get,
    post,
    put,
    delete: destroy,
  }
}
