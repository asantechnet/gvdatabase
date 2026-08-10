import axios, { type AxiosInstance, type AxiosError, type InternalAxiosRequestConfig } from 'axios'

// Create axios instance
const api: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// Request interceptor for adding the authorization token
api.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = localStorage.getItem('access_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error: AxiosError) => {
    return Promise.reject(error)
  }
)

// Response interceptor for handling errors and standardizing response format
api.interceptors.response.use(
  (response) => {
    // Standardize all successful responses to have a data property
    if (response.data && !response.data.data) {
      return {
        ...response,
        data: {
          data: response.data,
          success: true
        }
      }
    }
    return {
      ...response,
      data: {
        ...response.data,
        success: true
      }
    }
  },
  async (error: AxiosError) => {
    const originalRequest = error.config as InternalAxiosRequestConfig & { _retry?: boolean }

    // Handle 401 Unauthorized (token expired)
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true

      try {
        const refreshToken = localStorage.getItem('refresh_token')
        if (refreshToken) {
          const response = await axios.post(`${import.meta.env.VITE_API_BASE_URL || '/api'}/auth/refresh-token`, {
            refresh_token: refreshToken,
          })

          const { access_token } = response.data.data
          localStorage.setItem('access_token', access_token)

          // Retry the original request with the new token
          originalRequest.headers.Authorization = `Bearer ${access_token}`
          return api(originalRequest)
        }
      } catch (refreshError) {
        // If refresh fails, redirect to login
        localStorage.clear()
        window.location.href = '/login'
        return Promise.reject(refreshError)
      }
    }

    // Standardize error responses
    if (error.response) {
      return Promise.reject({
        ...error.response.data,
        status: error.response.status,
        success: false
      })
    }

    return Promise.reject({
      message: error.message || 'Network Error',
      success: false
    })
  }
)

export default api
