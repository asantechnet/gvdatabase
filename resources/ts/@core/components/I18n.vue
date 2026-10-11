<script setup lang="ts">
import type { I18nLanguage } from '@layouts/types'
import { loadLocale } from '@/plugins/i18n'

interface Props {
  languages: I18nLanguage[]
  location?: any
}

const props = withDefaults(defineProps<Props>(), {
  location: 'bottom end',
})

const { locale } = useI18n({ useScope: 'global' })

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
  <IconBtn>
    <VIcon icon="tabler-language" />

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
          <!-- Language label -->
          <VListItemTitle>
            {{ $t(lang.label) }}
          </VListItemTitle>
        </VListItem>
      </VList>
    </VMenu>
  </IconBtn>
</template>
