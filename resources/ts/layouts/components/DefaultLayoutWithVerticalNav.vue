<script lang="ts" setup>
import navItems from '@/navigation/vertical'
import { themeConfig } from '@themeConfig'

import Footer from '@/layouts/components/Footer.vue'
import NavBarAuditLogs from '@/layouts/components/NavBarAuditLogs.vue'
import NavBarNotifications from '@/layouts/components/NavBarNotifications.vue'
import NavSearchBar from '@/layouts/components/NavSearchBar.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'
import NavbarLayoutSwitcher from '@/layouts/components/NavbarLayoutSwitcher.vue'
import { useBranding } from '@/composables/useBranding'
import NavBarI18n from '@core/components/I18n.vue'
import { VerticalNavLayout } from '@layouts'

const { logoUrl, shortCompanyName, loadBranding } = useBranding()

onMounted(loadBranding)
</script>

<template>
  <VerticalNavLayout :nav-items="navItems">
    <template #navbar="{ toggleVerticalOverlayNavActive }">
      <div class="d-flex h-100 align-center">
        <IconBtn
          id="vertical-nav-toggle-btn"
          class="ms-n3 d-lg-none"
          @click="toggleVerticalOverlayNavActive(true)"
        >
          <VIcon
            size="26"
            icon="tabler-menu-2"
          />
        </IconBtn>

        <RouterLink to="/" class="d-flex align-center gap-2 me-4 navbar-brand">
          <img :src="logoUrl" :alt="shortCompanyName" class="navbar-logo">
          <span class="text-h6 font-weight-bold text-no-wrap d-none d-sm-inline">{{ shortCompanyName }}</span>
        </RouterLink>

        <NavSearchBar class="ms-lg-n3" />

        <VSpacer />

        <NavBarI18n
          v-if="themeConfig.app.i18n.enable && themeConfig.app.i18n.langConfig?.length"
          :languages="themeConfig.app.i18n.langConfig"
        />
        <NavbarThemeSwitcher />
        <NavbarLayoutSwitcher />
        <NavBarAuditLogs class="me-1" />
        <NavBarNotifications class="me-1" />
        <UserProfile />
      </div>
    </template>

    <slot />

    <template #footer>
      <Footer />
    </template>
  </VerticalNavLayout>
</template>

<style scoped>
.navbar-logo {
  block-size: 2rem;
  inline-size: 2rem;
  object-fit: contain;
}

@media (max-width: 599px) {
  .navbar-brand { display: none !important; }
}
</style>
