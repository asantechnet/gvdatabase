import axios, { type AxiosInstance, type AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { clearAuthTokens, getAccessToken, getRefreshToken, isRememberedSession, storeAuthTokens, type AuthTokens } from '@/utils/authStorage'
import { apiErrorMessage, apiFieldErrors, type ApiError } from '@/utils/apiErrors'
import { isApiRequestAllowed, permissionsForApiRequest } from '@/utils/apiAuthorization'
import { validateApiRequest } from '@/utils/apiValidation'
import { clearFormErrors, replaceFormErrors } from '@/utils/formErrors'
import { currentLocale } from '@/utils/localization'

// Create axios instance
const api: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// Refresh tokens are single-use. A shared renewal promise prevents several
// expired requests from racing and accidentally rotating the same token twice.
let refreshRequest: Promise<AuthTokens> | null = null

const renewSession = (refreshToken: string): Promise<AuthTokens> => {
  if (!refreshRequest) {
    refreshRequest = axios.post(`${import.meta.env.VITE_API_BASE_URL || '/api'}/auth/refresh-token`, {
      refresh_token: refreshToken,
      remember: isRememberedSession(),
    }, {
      headers: { 'Accept-Language': currentLocale() },
    }).then(response => {
      const tokens = response.data.data as AuthTokens
      storeAuthTokens(tokens, isRememberedSession())
      return tokens
    }).finally(() => {
      refreshRequest = null
    })
  }

  return refreshRequest
}

// Request interceptor for adding the authorization token
api.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    if (['post', 'put', 'patch', 'delete'].includes((config.method ?? '').toLowerCase()))
      clearFormErrors()
    config.headers['Accept-Language'] = currentLocale()

    const errors = validateApiRequest(config)
    if (Object.keys(errors).length) {
      replaceFormErrors(errors)
      const apiError: ApiError = {
        code: 'validation_failed',
        errors,
        success: false,
        handled: true,
      }

      const suppressErrorNotification = config.headers?.['X-Suppress-Error-Notification'] === 'true'
      if (typeof window !== 'undefined' && !suppressErrorNotification)
        window.dispatchEvent(new CustomEvent<ApiError>('api-error', { detail: apiError }))

      return Promise.reject(apiError)
    }

    const requiredPermissions = permissionsForApiRequest(config.method, config.url, config.data)
    if (requiredPermissions && !isApiRequestAllowed(config.method, config.url, config.data)) {
      const apiError: ApiError = {
        code: 'auth.forbidden',
        handled: true,
        success: false,
      }

      if (typeof window !== 'undefined')
        window.dispatchEvent(new CustomEvent<ApiError>('api-error', { detail: apiError }))

      return Promise.reject(apiError)
    }

    const token = getAccessToken()
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
    // Binary exports must remain raw Blobs; wrapping them as JSON corrupts
    // downloads and prevents object URLs from being created by the browser.
    if (response.config.responseType === 'blob')
      return response

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
    if ((error as ApiError).handled)
      return Promise.reject(error)

    const originalRequest = error.config as (InternalAxiosRequestConfig & { _retry?: boolean }) | undefined

    // Handle 401 Unauthorized (token expired)
    const isPublicAuthRequest = originalRequest?.url?.includes('/auth/login')
      || originalRequest?.url?.includes('/auth/refresh-token')

    if (error.response?.status === 401 && originalRequest && !originalRequest._retry && !isPublicAuthRequest) {
      originalRequest._retry = true

      try {
        const refreshToken = getRefreshToken()
        if (refreshToken) {
          const tokens = await renewSession(refreshToken)

          // Retry the original request with the new token
          originalRequest.headers.Authorization = `Bearer ${tokens.access_token}`
          return api(originalRequest)
        }
      } catch (refreshError) {
        // If refresh fails, redirect to login
        clearAuthTokens()
        window.location.href = '/login'
        return Promise.reject(refreshError)
      }
    }

    // Standardize error responses
    if (error.response) {
      const apiError = {
        ...(typeof error.response.data === 'object' && error.response.data !== null ? error.response.data : {}),
        status: error.response.status,
        success: false,
      } as ApiError

      apiError.message = apiErrorMessage(apiError)

      if (apiError.status === 422 && apiError.errors)
        replaceFormErrors(apiFieldErrors(apiError))

      if (typeof window !== 'undefined')
        window.dispatchEvent(new CustomEvent<ApiError>('api-error', { detail: apiError }))

      return Promise.reject(apiError)
    }

    const apiError = {
      code: 'server.error',
      message: error.message || 'Network Error',
      success: false
    } as ApiError

    if (typeof window !== 'undefined')
      window.dispatchEvent(new CustomEvent<ApiError>('api-error', { detail: apiError }))

    return Promise.reject(apiError)
  }
)

export default api
