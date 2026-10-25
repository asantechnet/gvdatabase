<script setup lang="ts">
import type { I18nLanguage } from '@layouts/types'
import { loadLocale } from '@/plugins/i18n'
import { Icon } from '@iconify/vue'

interface Props {
  languages: I18nLanguage[]
  location?: any
}

const props = withDefaults(defineProps<Props>(), {
  location: 'bottom end',
})

const { locale } = useI18n({ useScope: 'global' })

const activeLanguage = computed(() => props.languages.find(language => language.i18nLang === locale.value))
const languageFlag = (language: string) => language === 'en' ? 'flag:gb-4x3' : 'flag:af-4x3'

// Locales other than the default one live in their own chunk. Load the target
// locale before switching so the UI never renders untranslated keys.
const switchLocale = async (next: string) => {
  if (next === locale.value)
    return

  if (await loadLocale(next).catch(() => false))
    locale.value = next
}
</script>

<template>
  <VBtn
    variant="text"
    color="high-emphasis"
    class="language-switcher"
    :aria-label="activeLanguage ? $t(activeLanguage.label) : locale"
  >
    <Icon :icon="languageFlag(locale)" width="22" height="17" aria-hidden="true" />
    <span v-if="activeLanguage" class="language-switcher__label">{{ $t(activeLanguage.label) }}</span>

    <!-- Menu -->
    <VMenu
      activator="parent"
      :location="props.location"
      offset="12px"
      width="175"
    >
      <!-- List -->
      <VList
        :selected="[locale]"
        color="primary"
      >
        <!-- List item -->
        <VListItem
          v-for="lang in props.languages"
          :key="lang.i18nLang"
          :value="lang.i18nLang"
          @click="switchLocale(lang.i18nLang)"
        >
          <template #prepend>
            <Icon :icon="languageFlag(lang.i18nLang)" width="22" height="17" class="me-3" aria-hidden="true" />
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

.language-switcher__label {
  margin-inline-start: 8px;
}

@media (max-width: 599px) {
  .language-switcher__label { display: none; }
}
</style>
