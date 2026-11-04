<script setup lang="ts">
import { useRouter } from 'vue-router'
import { themeConfig } from '@themeConfig'
import { useCookie } from '@core/composable/useCookie'
import NavBarI18n from '@core/components/I18n.vue'
import AppBrandMark from '@/components/erp/AppBrandMark.vue'
import Footer from '@/layouts/components/Footer.vue'
import NavBarNotifications from '@/layouts/components/NavBarNotifications.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import { useBranding } from '@/composables/useBranding'
import AuthApi from '@/api/auth'
import { clearAuthTokens, getRefreshToken } from '@/utils/authStorage'
import { clearPermissions } from '@/utils/authorization'

const { logoUrl, hasCompanyLogo, logoFailed, isLogoSquare, isMonochromeLogo, shortCompanyName, handleLogoLoad, handleLogoError, loadBranding } = useBranding()
const router = useRouter()
const userData = useCookie<{ name?: string; avatar?: string } | null>('userData')
const loggingOut = ref(false)

async function logout() {
  loggingOut.value = true
  try {
    await AuthApi.logout(getRefreshToken())
  }
  catch {
    // Clearing the local session is still correct for an expired token.
  }
  finally {
    useCookie('accessToken').value = null
    userData.value = null
    clearAuthTokens()
    clearPermissions()
    loggingOut.value = false
    await router.replace('/login')
  }
}

onMounted(loadBranding)
</script>

<template>
  <div class="layout-wrapper applicant-portal-layout">
    <header class="applicant-portal-header">
      <RouterLink to="/my-case" class="applicant-portal-brand">
        <AppBrandMark
          v-if="hasCompanyLogo && logoUrl && !logoFailed"
          :src="logoUrl"
          :alt="shortCompanyName"
          :monochrome="isMonochromeLogo"
          class="applicant-portal-brand__logo"
          @load="handleLogoLoad"
          @error="handleLogoError"
        />
        <span v-if="!hasCompanyLogo || logoFailed || isLogoSquare" class="applicant-portal-brand__name">{{ shortCompanyName }}</span>
      </RouterLink>

      <VSpacer />

      <NavBarI18n
        v-if="themeConfig.app.i18n.enable && themeConfig.app.i18n.langConfig?.length"
        :languages="themeConfig.app.i18n.langConfig"
      />
      <NavbarThemeSwitcher />
      <NavBarNotifications />
      <VMenu location="bottom end" offset="10">
        <template #activator="{ props }">
          <VAvatar
            v-bind="props"
            size="36"
            class="ms-2 cursor-pointer"
            color="primary"
            variant="tonal"
            :aria-label="userData?.name || 'Profile menu'"
          >
            <VImg v-if="userData?.avatar" :src="userData.avatar" />
            <VIcon v-else icon="tabler-user" />
          </VAvatar>
        </template>
        <VList min-width="160">
          <VListItem
            prepend-icon="tabler-logout"
            :title="$t('components.navigation.userprofile.logout')"
            :disabled="loggingOut"
            @click="logout"
          />
        </VList>
      </VMenu>
    </header>
    <slot />
    <footer class="applicant-portal-footer">
      <Footer />
    </footer>
  </div>
</template>

<style scoped>
.applicant-portal-layout {
  flex-direction: column;
  min-block-size: 100vh;
}

.applicant-portal-header {
  display: flex;
  align-items: center;
  min-block-size: 4rem;
  padding-inline: 1.5rem;
  background-color: var(--app-navigation-color, rgb(var(--v-theme-surface)));
  color: var(--app-navigation-on-color, rgb(var(--v-theme-on-surface)));
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  margin-bottom: 1.5rem;
}

.applicant-portal-brand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  color: inherit;
  text-decoration: none;
}

.applicant-portal-brand__logo {
  display: block;
  inline-size: auto;
  block-size: 2.5rem;
  max-inline-size: 10rem;
  max-block-size: 2.5rem;
  object-fit: contain;
}

.applicant-portal-brand__name {
  font-size: 1.1rem;
  font-weight: 600;
}

.applicant-portal-footer {
  flex: 0 0 2.75rem;
  block-size: 2.75rem;
  margin-top: 1rem;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  font-size: 0.8125rem;
}

@media (max-width: 599px) {
  .applicant-portal-header {
    padding-inline: 1rem;
  }
}
</style>
