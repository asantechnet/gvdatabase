<script lang="ts" setup>
import navItems from "@/navigation/vertical";
import { themeConfig } from "@themeConfig";

import AppBrandMark from '@/components/erp/AppBrandMark.vue'
import Footer from "@/layouts/components/Footer.vue";
import NavBarAuditLogs from "@/layouts/components/NavBarAuditLogs.vue";
import NavBarNotifications from "@/layouts/components/NavBarNotifications.vue";
import HeaderPageGuide from "@/layouts/components/HeaderPageGuide.vue";
import NavSearchBar from "@/layouts/components/NavSearchBar.vue";
import NavbarPreferences from "@/layouts/components/NavbarPreferences.vue";
import UserProfile from "@/layouts/components/UserProfile.vue";
import { useBranding } from "@/composables/useBranding";
import NavBarI18n from "@core/components/I18n.vue";
import { VerticalNavLayout } from "@layouts";
import { useLayoutConfigStore } from "@layouts/stores/config";

const {
  logoUrl,
  hasCompanyLogo,
  logoFailed,
  isLogoSquare,
  isMonochromeLogo,
  shortCompanyName,
  handleLogoLoad,
  handleLogoError,
  loadBranding,
} = useBranding();
const layoutConfigStore = useLayoutConfigStore();

const toggleSidebar = () => {
  const willCollapse = !layoutConfigStore.isVerticalNavCollapsed;
  layoutConfigStore.isVerticalNavCollapsed = willCollapse;
  layoutConfigStore.isVerticalNavHoverEnabled = !willCollapse;
};

onMounted(async () => {
  await loadBranding();
});
</script>

<template>
  <VerticalNavLayout :nav-items="navItems">
    <template #vertical-nav-header="{ toggleVerticalOverlayNavActive }">
      <RouterLink
        to="/"
        class="app-logo vertical-nav-brand"
        :class="{ 'vertical-nav-brand--wide': isLogoSquare === false }"
      >
        <AppBrandMark
          v-if="hasCompanyLogo && logoUrl && !logoFailed"
          :src="logoUrl"
          :alt="shortCompanyName"
          :monochrome="isMonochromeLogo"
          class="vertical-nav-brand__logo"
          @load="handleLogoLoad"
          @error="handleLogoError"
        />
        <span v-if="!hasCompanyLogo || logoFailed || isLogoSquare" class="app-title vertical-nav-brand__name">{{
          shortCompanyName
        }}</span>
      </RouterLink>

      <IconBtn
        class="vertical-nav-collapse-toggle ms-auto d-none d-md-inline-flex"
        :aria-label="
          $t(
            layoutConfigStore.isVerticalNavCollapsed
              ? 'common.actionWarnings.expandNavigation'
              : 'common.actionWarnings.collapseNavigation',
          )
        "
        @click="toggleSidebar"
      >
        <VIcon
          :icon="
            layoutConfigStore.isVerticalNavCollapsed
              ? 'tabler-layout-sidebar-left-expand'
              : 'tabler-layout-sidebar-left-collapse'
          "
        />
      </IconBtn>
      <IconBtn
        class="header-action ms-auto d-md-none"
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
          class="ms-n3 d-md-none"
          @click="toggleVerticalOverlayNavActive(true)"
        >
          <VIcon size="26" icon="tabler-menu-2" />
        </IconBtn>

        <NavSearchBar class="ms-md-n3" />
        <HeaderPageGuide />

        <VSpacer />

        <NavBarI18n
          v-if="
            themeConfig.app.i18n.enable &&
            themeConfig.app.i18n.langConfig?.length
          "
          :languages="themeConfig.app.i18n.langConfig"
        />
        <NavbarPreferences />
        <NavBarAuditLogs/>
        <NavBarNotifications class="me-3" />
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
  overflow: hidden;
  min-inline-size: 0;
}

.vertical-nav-brand__logo {
  display: block;
  flex: 0 0 auto;
  block-size: 2.5rem;
  inline-size: auto;
  max-block-size: 2.5rem;
  max-inline-size: 10rem;
  object-fit: contain;
}

.vertical-nav-brand__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

:global(
.layout-vertical-nav-collapsed .layout-vertical-nav:not(.hovered) .vertical-nav-brand
) {
  display: none;
}

:global(
.layout-vertical-nav-collapsed .layout-vertical-nav:not(.hovered) .vertical-nav-collapse-toggle
) {
  margin-inline: auto !important;
}
</style>
