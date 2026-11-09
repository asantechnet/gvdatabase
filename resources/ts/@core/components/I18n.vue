<script setup lang="ts">
import type { I18nLanguage } from "@layouts/types";
import afFlag from "@/assets/flags/af.svg?url";
import gbFlag from "@/assets/flags/gb.svg?url";
import { loadLocale } from "@/plugins/i18n";

interface Props {
  languages: I18nLanguage[];
  location?: any;
}

const props = withDefaults(defineProps<Props>(), {
  location: "bottom end",
});

const { locale } = useI18n({ useScope: "global" });

const activeLanguage = computed(() =>
  props.languages.find((language) => language.i18nLang === locale.value),
);
// Bundle the flags with the app. `@iconify/vue` fetches remotely, which leaves
// a blank space in the navbar whenever the Iconify API is blocked or offline.
const languageFlag = (language: string) =>
  language === "en" ? gbFlag : afFlag;

// Locales other than the default one live in their own chunk. Load the target
// locale before switching so the UI never renders untranslated keys.
const switchLocale = async (next: string) => {
  if (next === locale.value) return;

  if (await loadLocale(next).catch(() => false)) locale.value = next;
};
</script>

<template>
  <VBtn
    variant="text"
    color="high-emphasis"
    class="language-switcher"
    :aria-label="activeLanguage ? $t(activeLanguage.label) : locale"
  >
    <img
      :src="languageFlag(locale)"
      width="23"
      height="17"
      class="language-switcher__flag"
      alt=""
      aria-hidden="true"
    >

    <!-- Menu -->
    <VMenu
      activator="parent"
      :location="props.location"
      offset="12px"
      width="175"
    >
      <!-- List -->
      <VList :selected="[locale]" color="primary">
        <!-- List item -->
        <VListItem
          v-for="lang in props.languages"
          :key="lang.i18nLang"
          :value="lang.i18nLang"
          @click="switchLocale(lang.i18nLang)"
        >
          <template #prepend>
            <img
              :src="languageFlag(lang.i18nLang)"
              width="22"
              height="17"
              class="me-3"
              alt=""
              aria-hidden="true"
            >
          </template>
          <!-- Language label -->
          <VListItemTitle>
            {{ $t(lang.label) }}
          </VListItemTitle>
        </VListItem>
      </VList>
    </VMenu>
  </VBtn>
</template>

<style scoped>
.language-switcher {
  min-inline-size: 40px;
  padding-inline: 8px;
}

.language-switcher :deep(.language-switcher__flag) {
  inline-size: 23px !important;
  block-size: 17px !important;
  object-fit: cover;
  border-radius: 2px;
}
</style>
