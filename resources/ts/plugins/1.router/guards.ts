import type { Router } from 'vue-router'
import { getAccessToken, getStoredPermissions } from '@/utils/authStorage'
import { canNavigate } from '@layouts/plugins/casl'
import { applyPermissions, canAccess } from '@/utils/authorization'
import { useCookie } from '@core/composable/useCookie'
import AuthApi from '@/api/auth'

let sessionPermissionRequest: Promise<void> | null = null
let sessionPermissionRequestAttempted = false

const isApplicantPortalUser = () => useCookie<{ roles?: string[] } | null>('userData').value?.roles?.includes('applicant') === true

const authorizedLandingRoute = () => {
  if (isApplicantPortalUser() && canAccess('view_own_applicant_portal'))
    return { name: 'applicant-portal' }
  if (canAccess('view_agency_overview'))
    return { name: 'home' }
  if (canAccess('view_all_boards') || canAccess('view_assigned_boards'))
    return { name: 'tasks' }

  // Every authenticated user can still manage their own account, even when
  // their operational role has no application-area permissions yet.
  return { name: 'account-settings' }
}

export const setupGuards = (router: Router) => {
  // The router is installed before the CASL Vue plugin. Restore permissions
  // here so a browser refresh authorizes the current URL instead of treating
  // the still-unhydrated ability as empty and redirecting to account settings.
  const persistedPermissions = getStoredPermissions()
  if (persistedPermissions.length) {
    applyPermissions(persistedPermissions)
  }
  else {
    const persistedUser = useCookie<{ permissions?: string[] } | null>('userData')
    if (persistedUser.value?.permissions)
      applyPermissions(persistedUser.value.permissions)
  }

  const hydrateSessionPermissions = async () => {
    // Login already stores the authoritative permissions. This also covers
    // navigation immediately after login without making a second request.
    if (getStoredPermissions().length || sessionPermissionRequestAttempted)
      return

    if (!sessionPermissionRequest) {
      sessionPermissionRequest = AuthApi.me()
        .then(response => {
          const user = response.data.data as { permissions?: string[] }
          const permissions = Array.isArray(user.permissions) ? user.permissions : []

          useCookie<{ permissions?: string[] } | null>('userData').value = user
          applyPermissions(permissions)
        })
        .catch(() => {
          // Let the normal auth guard decide the fallback when the session
          // cannot be hydrated (for example, an expired token).
        })
        .finally(() => {
          sessionPermissionRequestAttempted = true
          sessionPermissionRequest = null
        })
    }

    await sessionPermissionRequest
  }

  router.beforeEach(async to => {
    if (to.meta.public)
      return true

    const token = getAccessToken()
    const isLoggedIn = !!token

    if (isLoggedIn)
      await hydrateSessionPermissions()

    if (to.meta.unauthenticatedOnly) {
      if (isLoggedIn)
        return authorizedLandingRoute()

      return true
    }

    if (!isLoggedIn)
      return { path: '/login' }

    if (to.name === 'applicant-portal' && !isApplicantPortalUser())
      return authorizedLandingRoute()

    if (!canNavigate(to)) {
      const fallback = authorizedLandingRoute()

      return to.name === fallback.name ? false : fallback
    }

    return true
  })
}
