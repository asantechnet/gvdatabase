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
import { useLayoutConfigStore } from '@layouts/stores/config'

const { logoUrl, isLogoSquare, shortCompanyName, handleLogoLoad, loadBranding } = useBranding()
const layoutConfigStore = useLayoutConfigStore()

onMounted(loadBranding)
</script>

<template>
  <VerticalNavLayout :nav-items="navItems">
    <template #vertical-nav-header="{ toggleVerticalOverlayNavActive }">
      <RouterLink
        to="/"
        class="app-logo vertical-nav-brand"
        :class="{ 'vertical-nav-brand--wide': isLogoSquare === false }"
      >
        <img
          :src="logoUrl"
          :alt="shortCompanyName"
          class="vertical-nav-brand__logo"
          @load="handleLogoLoad"
        >
        <span
          v-if="isLogoSquare"
          class="app-title vertical-nav-brand__name"
        >{{ shortCompanyName }}</span>
      </RouterLink>

      <IconBtn
        class="vertical-nav-collapse-toggle ms-auto d-none d-lg-inline-flex"
        :aria-label="$t(layoutConfigStore.isVerticalNavCollapsed ? 'common.actionWarnings.expandNavigation' : 'common.actionWarnings.collapseNavigation')"
        @click="layoutConfigStore.isVerticalNavCollapsed = !layoutConfigStore.isVerticalNavCollapsed"
      >
        <VIcon :icon="layoutConfigStore.isVerticalNavCollapsed ? 'tabler-layout-sidebar-left-expand' : 'tabler-layout-sidebar-left-collapse'" />
      </IconBtn>
      <IconBtn
        class="header-action ms-auto d-lg-none"
        :aria-label="$t('common.actionWarnings.closeNavigation')"
        @click="toggleVerticalOverlayNavActive(false)"
      >
        <VIcon icon="tabler-x" />
      </IconBtn>
    </template>

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
.vertical-nav-brand {
  min-inline-size: 0;
  overflow: hidden;
}

.vertical-nav-brand__logo {
  display: block;
  flex: 0 0 auto;
  block-size: 2.1rem;
  inline-size: auto;
  max-inline-size: 8rem;
  object-fit: contain;
}

.vertical-nav-brand__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

:global(.layout-vertical-nav-collapsed .layout-vertical-nav:not(.hovered) .vertical-nav-brand) {
  display: none;
}

:global(.layout-vertical-nav-collapsed .layout-vertical-nav:not(.hovered) .vertical-nav-collapse-toggle) {
  margin-inline: auto !important;
}
</style>
