import type { Router } from "vue-router";
import { getAccessToken, getStoredPermissions } from '@/utils/authStorage'
import { canNavigate } from '@layouts/plugins/casl'
import { applyPermissions, canAccess } from '@/utils/authorization'
import { useCookie } from '@core/composable/useCookie'

const authorizedLandingRoute = () => {
  if (canAccess('view_dashboard')) return { name: 'home' }
  if (canAccess('view_all_boards') || canAccess('view_assigned_boards')) return { name: 'tasks' }

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

  router.beforeEach((to) => {
    if (to.meta.public) return true;

    const token = getAccessToken();
    const isLoggedIn = !!token;

    if (to.meta.unauthenticatedOnly) {
      if (isLoggedIn) return authorizedLandingRoute();

      return true;
    }

    if (!isLoggedIn) {
      return { path: "/login" };
    }

    if (!canNavigate(to)) {
      const fallback = authorizedLandingRoute()
      return to.name === fallback.name ? false : fallback
    }

    return true;
  });
};
