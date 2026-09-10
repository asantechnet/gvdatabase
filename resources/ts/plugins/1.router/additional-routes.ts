import type { RouteRecordRaw } from 'vue-router'
import { getAccessToken } from '@/utils/authStorage'

// 👉 Redirects
export const redirects: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'index',
    redirect: to => {
      const token = getAccessToken()

      if (!token)
        return { name: 'login', query: to.query }

      return { name: 'home' }
    },
  },
  {
    path: '/pages/user-profile',
    redirect: { name: 'account-settings', params: { tab: 'account' } },
  },
  {
    path: '/pages/account-settings',
    redirect: { name: 'account-settings', params: { tab: 'account' } },
  },
]

export const routes: RouteRecordRaw[] = []
