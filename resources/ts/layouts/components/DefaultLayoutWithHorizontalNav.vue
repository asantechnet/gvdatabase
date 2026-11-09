<script lang="ts" setup>
import baseNavItems from "@/navigation/horizontal";
import { useAcademyEnabled } from "@/composables/useAcademyEnabled";

import { themeConfig } from "@themeConfig";

import AppBrandMark from '@/components/erp/AppBrandMark.vue'
import Footer from '@/layouts/components/Footer.vue'

// Components
import NavBarNotifications from "@/layouts/components/NavBarNotifications.vue";
import NavBarAuditLogs from "@/layouts/components/NavBarAuditLogs.vue";
import NavbarPreferences from "@/layouts/components/NavbarPreferences.vue";
import NavBarI18n from "@core/components/I18n.vue";
import { HorizontalNavLayout } from "@layouts";
import NavSearchBar from "@/layouts/components/NavSearchBar.vue";
import HeaderPageGuide from "@/layouts/components/HeaderPageGuide.vue";
import UserProfile from "@/layouts/components/UserProfile.vue";
import { useBranding } from "@/composables/useBranding";

const { logoUrl, hasCompanyLogo, logoFailed, isLogoSquare, isMonochromeLogo, shortCompanyName, handleLogoLoad, handleLogoError, loadBranding } = useBranding()
const { enabled: academyEnabled, load: loadAcademyEnabled } = useAcademyEnabled()

// Academy is a company-level feature: when it is off the whole navigation
// group disappears, not just its pages.
const navItems = computed(() => academyEnabled.value
  ? baseNavItems
  : baseNavItems.filter(item => !('title' in item) || item.title !== 'common.navigation.academy'))

onMounted(async () => {
  await Promise.all([loadBranding(), loadAcademyEnabled()]);
});
</script>

<template>
  <HorizontalNavLayout :nav-items="navItems">
    <!-- 👉 navbar -->
    <template #navbar>
      <RouterLink
        to="/"
        class="app-logo d-flex align-center gap-x-3 navbar-brand"
      >
        <AppBrandMark
          v-if="hasCompanyLogo && logoUrl && !logoFailed"
          :src="logoUrl"
          :alt="shortCompanyName"
          :monochrome="isMonochromeLogo"
          class="navbar-logo"
          @load="handleLogoLoad"
          @error="handleLogoError"
        />

        <h1
          v-if="!hasCompanyLogo || logoFailed || isLogoSquare"
          class="app-title font-weight-bold leading-normal text-xl text-capitalize"
        >
          {{ shortCompanyName }}
        </h1>
      </RouterLink>
      <HeaderPageGuide class="ms-2" />
      <VSpacer />

      <NavSearchBar trigger-btn-class="ms-lg-n3" />

      <NavBarI18n
        v-if="
          themeConfig.app.i18n.enable && themeConfig.app.i18n.langConfig?.length
        "
        :languages="themeConfig.app.i18n.langConfig"
      />

      <NavbarPreferences />
      <!-- <NavbarShortcuts /> -->
      <NavBarAuditLogs />
      <NavBarNotifications class="me-2" />
      <UserProfile />
    </template>

    <!-- 👉 Pages -->
    <slot />

    <!-- 👉 Footer -->
    <template #footer>
      <Footer />
    </template>

    <!-- 👉 Customizer -->
    <!-- <TheCustomizer /> -->
  </HorizontalNavLayout>
</template>

<style scoped>
.navbar-logo {
  display: block;
  block-size: 2.5rem;
  inline-size: auto;
  max-block-size: 2.5rem;
  max-inline-size: 10rem;
  object-fit: contain;
}

@media (max-width: 599px) {
  .navbar-brand {
    display: none !important;
  }
}
</style>
