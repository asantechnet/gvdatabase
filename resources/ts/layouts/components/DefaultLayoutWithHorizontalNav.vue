<script lang="ts" setup>
import navItems from "@/navigation/horizontal";

import { themeConfig } from "@themeConfig";

import Footer from "@/layouts/components/Footer.vue";

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

const {
  logoUrl,
  isLogoSquare,
  shortCompanyName,
  handleLogoLoad,
  loadBranding,
} = useBranding();

onMounted(async () => {
  await loadBranding();
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
        <img
          :src="logoUrl"
          :alt="shortCompanyName"
          class="navbar-logo"
          @load="handleLogoLoad"
        />

        <h1
          v-if="isLogoSquare"
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
  inline-size: auto;
  block-size: 2.5rem;
  max-inline-size: 10rem;
  max-block-size: 2.5rem;
  object-fit: contain;
}

@media (max-width: 599px) {
  .navbar-brand {
    display: none !important;
  }
}
</style>
