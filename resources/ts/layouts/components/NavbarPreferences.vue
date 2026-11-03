<script setup lang="ts">
import { AppContentLayoutNav } from "@layouts/enums";
import { useConfigStore } from "@core/stores/config";
import { useLayoutConfigStore } from "@layouts/stores/config";
import { onMounted } from "vue";
import { useDisplayCurrency } from "@/composables/useDisplayCurrency";
import { canAccess } from "@/utils/authorization";

const { t } = useI18n();
const configStore = useConfigStore();
const layoutConfigStore = useLayoutConfigStore();
const canSelectDisplayCurrency = canAccess("view_currencies");
const {
  loading: currenciesLoading,
  options: currencyOptions,
  selectedCode: selectedCurrencyCode,
  loadCurrencies,
} = useDisplayCurrency();

onMounted(() => {
  if (canSelectDisplayCurrency) void loadCurrencies().catch(() => undefined);
});

const themes = [
  {
    value: "light",
    icon: "tabler-sun-high",
    label: "components.navigation.preferences.light",
  },
  {
    value: "dark",
    icon: "tabler-moon-stars",
    label: "components.navigation.preferences.dark",
  },
  {
    value: "system",
    icon: "tabler-device-desktop-analytics",
    label: "components.navigation.preferences.system",
  },
] as const;

const navigationLayouts = [
  {
    value: AppContentLayoutNav.Vertical,
    icon: "tabler-layout-sidebar-left-expand",
    label: "components.navigation.preferences.sidebar",
  },
  {
    value: AppContentLayoutNav.Horizontal,
    icon: "tabler-layout-navbar",
    label: "components.navigation.preferences.topbar",
  },
] as const;
</script>

<template>
  <IconBtn :aria-label="t('components.navigation.preferences.title')">
    <VIcon icon="tabler-adjustments-horizontal" />
    <VTooltip activator="parent" location="bottom">
      {{ t("components.navigation.preferences.title") }}
    </VTooltip>

    <VMenu
      activator="parent"
      location="bottom end"
      offset="12px"
      :width="300"
      :close-on-content-click="false"
    >
      <VCard class="navbar-preferences pa-3">
        <div class="navbar-preferences__section">
          <p>{{ t("components.navigation.preferences.appearance") }}</p>
          <VBtnToggle
            :model-value="configStore.theme"
            divided
            mandatory
            density="compact"
            class="navbar-preferences__toggle"
            @update:model-value="configStore.theme = $event"
          >
            <VBtn
              v-for="theme in themes"
              :key="theme.value"
              :value="theme.value"
              :aria-label="t(theme.label)"
            >
              <VIcon :icon="theme.icon" size="18" />
              <span>{{ t(theme.label) }}</span>
            </VBtn>
          </VBtnToggle>
        </div>

        <template v-if="canSelectDisplayCurrency">
          <VDivider class="my-3" />
          <div class="navbar-preferences__section">
            <p>{{ t("components.navigation.preferences.displayCurrency") }}</p>
            <VSelect
              v-model="selectedCurrencyCode"
              :items="currencyOptions"
              :loading="currenciesLoading"
              prepend-inner-icon="tabler-arrows-left-right"
              variant="outlined"
              density="compact"
              hide-details
            />
          </div>
        </template>

        <VDivider class="navbar-preferences__navigation my-3" />

        <div class="navbar-preferences__section navbar-preferences__navigation">
          <p>{{ t("components.navigation.preferences.navigation") }}</p>
          <VBtnToggle
            :model-value="layoutConfigStore.appContentLayoutNav"
            divided
            mandatory
            density="compact"
            class="navbar-preferences__toggle"
            @update:model-value="layoutConfigStore.appContentLayoutNav = $event"
          >
            <VBtn
              v-for="layout in navigationLayouts"
              :key="layout.value"
              :value="layout.value"
              :aria-label="t(layout.label)"
            >
              <VIcon :icon="layout.icon" size="18" />
              <span>{{ t(layout.label) }}</span>
            </VBtn>
          </VBtnToggle>
        </div>
      </VCard>
    </VMenu>
  </IconBtn>
</template>

<style scoped lang="scss">
.navbar-preferences__section > p {
  margin-block: 0 8px;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
  font-weight: 650;
  letter-spacing: 0.02em;
}
.navbar-preferences__toggle {
  display: flex;
  inline-size: 100%;
}
.navbar-preferences__toggle :deep(.v-btn) {
  flex: 1 1 0;
  min-inline-size: 0;
  padding-inline: 8px;
}
.navbar-preferences__toggle :deep(.v-btn__content) {
  gap: 6px;
  font-size: 0.75rem;
}

@media (max-width: 959px) {
  .navbar-preferences__navigation {
    display: none;
  }
}
</style>
