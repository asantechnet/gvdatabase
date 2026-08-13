import type { RouteRecordRaw } from "vue-router";
import { getAccessToken } from '@/utils/authStorage'

// 👉 Redirects
export const redirects: RouteRecordRaw[] = [
  {
    path: "/",
    name: "index",
    redirect: (to) => {
      const token = getAccessToken();

      if (!token) {
        return { name: "login", query: to.query };
      }

      return { name: "home" };
    },
  },
  {
    path: "/pages/user-profile",
    name: "pages-user-profile",
    redirect: () => ({
      name: "pages-user-profile-tab",
      params: { tab: "profile" },
    }),
  },
  {
    path: "/pages/account-settings",
    name: "pages-account-settings",
    redirect: () => ({
      name: "pages-account-settings-tab",
      params: { tab: "account" },
    }),
  },
];

export const routes: RouteRecordRaw[] = [];
