import type { Router } from "vue-router";

export const setupGuards = (router: Router) => {
  router.beforeEach((to) => {
    if (to.meta.public) return true;

    const token = localStorage.getItem("access_token");
    const isLoggedIn = !!token;

    if (to.meta.unauthenticatedOnly) {
      if (isLoggedIn) return { path: "/home" };

      return true;
    }

    if (!isLoggedIn) {
      return { path: "/login" };
    }

    return true;
  });
};
