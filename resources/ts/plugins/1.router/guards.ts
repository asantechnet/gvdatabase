import type { Router } from "vue-router";
import { getAccessToken } from '@/utils/authStorage'
import { canNavigate } from '@layouts/plugins/casl'
import { canAccess } from '@/utils/authorization'

const authorizedLandingRoute = () => {
  if (canAccess('view_dashboard')) return { name: 'home' }
  if (canAccess('view_all_boards') || canAccess('view_assigned_boards')) return { name: 'tasks' }

  // Every authenticated user can still manage their own account, even when
  // their operational role has no application-area permissions yet.
  return { name: 'account-settings' }
}

export const setupGuards = (router: Router) => {
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
