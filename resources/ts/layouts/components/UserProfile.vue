<script setup lang="ts">
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useRouter } from 'vue-router'
import AuthApi from '@/api/auth'

const router = useRouter()
const ability = useAbility()
const userData = useCookie<any>('userData')

const userProfileList = [
  { type: 'navItem', icon: 'tabler-user', title: 'My Account', to: { name: 'account-settings', params: { tab: 'account' } } },
  { type: 'navItem', icon: 'tabler-lock', title: 'Security', to: { name: 'account-settings', params: { tab: 'security' } } },
  { type: 'navItem', icon: 'tabler-bell', title: 'Notification Preferences', to: { name: 'account-settings', params: { tab: 'notifications' } } },
  { type: 'navItem', icon: 'tabler-link', title: 'Connected Accounts', to: { name: 'account-settings', params: { tab: 'connections' } } },
  { type: 'divider' },
  { type: 'navItem', icon: 'tabler-bell-ringing', title: 'My Notifications', to: { name: 'notifications' } },
]

async function refreshUser() {
  try { userData.value = (await AuthApi.me()).data.data }
  catch { /* The auth guard handles an expired session. */ }
}

async function logout() {
  try { await AuthApi.logout() } catch { /* Clear local state even if the token has already expired. */ }
  useCookie('accessToken').value = null
  userData.value = null
  localStorage.removeItem('access_token')
  localStorage.removeItem('refresh_token')
  useCookie('userAbilityRules').value = null
  ability.update([])
  await router.replace('/login')
}

onMounted(refreshUser)
</script>

<template>
  <VBadge v-if="userData" dot bordered location="bottom right" offset-x="1" offset-y="2" color="success">
    <VAvatar size="38" class="cursor-pointer" :color="!userData.avatar ? 'primary' : undefined" :variant="!userData.avatar ? 'tonal' : undefined">
      <VImg v-if="userData.avatar" :src="userData.avatar" /><VIcon v-else icon="tabler-user" />
      <VMenu activator="parent" width="280" location="bottom end" offset="12px">
        <VList>
          <VListItem>
            <div class="d-flex gap-3 align-center"><VAvatar :color="!userData.avatar ? 'primary' : undefined" :variant="!userData.avatar ? 'tonal' : undefined"><VImg v-if="userData.avatar" :src="userData.avatar" /><VIcon v-else icon="tabler-user" /></VAvatar><div><h6 class="text-h6 font-weight-medium">{{ userData.name }}</h6><VListItemSubtitle class="text-disabled">{{ userData.email }}</VListItemSubtitle></div></div>
          </VListItem>
          <VDivider class="my-2" />
          <PerfectScrollbar :options="{ wheelPropagation: false }">
            <template v-for="item in userProfileList" :key="item.title">
              <VListItem v-if="item.type === 'navItem'" :to="item.to"><template #prepend><VIcon :icon="item.icon" size="22" /></template><VListItemTitle>{{ item.title }}</VListItemTitle></VListItem>
              <VDivider v-else class="my-2" />
            </template>
            <div class="px-4 py-2"><VBtn block size="small" color="error" append-icon="tabler-logout" @click="logout">Logout</VBtn></div>
          </PerfectScrollbar>
        </VList>
      </VMenu>
    </VAvatar>
  </VBadge>
</template>
