<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import ContractTemplatesView from './policies.vue'
import PolicyLibraryView from './policy_library.vue'
import { canAccess } from '@/utils/authorization'

type ContractDocsSection = 'templates' | 'policies'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const canViewTemplates = computed(() => canAccess('view_contract_templates'))
const canViewPolicies = computed(() => canAccess('view_contract_policies'))

const availableSections = computed<ContractDocsSection[]>(() => [
  ...(canViewTemplates.value ? ['templates' as const] : []),
  ...(canViewPolicies.value ? ['policies' as const] : []),
])

const labels = computed<Record<ContractDocsSection, string>>(() => ({
  templates: t('pages.settings.policies.policies.contractTemplates'),
  policies: t('pages.settings.policies.policies.policyLibrary.title'),
}))

// A single page with in-page navigation (same pattern as educational-master-data).
// The active tab stays in `?tab=` so each section remains deep-linkable and
// old bookmarks keep working through the legacy route redirects.
const section = ref<ContractDocsSection>('templates')
const templatesView = ref<InstanceType<typeof ContractTemplatesView> | null>(null)
const policiesView = ref<InstanceType<typeof PolicyLibraryView> | null>(null)

const openEditor = () => {
  if (section.value === 'templates') templatesView.value?.openEditor()
  else policiesView.value?.openEditor()
}

function sectionFromQuery(): ContractDocsSection {
  const tab = String(route.query.tab ?? '')

  if (tab === 'policies' && canViewPolicies.value)
    return 'policies'
  if (tab === 'templates' && canViewTemplates.value)
    return 'templates'

  return availableSections.value[0] ?? 'templates'
}

watch(section, value => {
  if (route.query.tab !== value)
    router.replace({ query: { ...route.query, tab: value } })
})

watch(() => route.query.tab, () => {
  const next = sectionFromQuery()
  if (next !== section.value)
    section.value = next
})

onMounted(() => {
  section.value = sectionFromQuery()
  if (route.query.tab !== section.value)
    router.replace({ query: { ...route.query, tab: section.value } })
})
</script>

<template>
  <div class="contract-docs">
    <VCard class="contract-docs__workspace">
      <VCardItem>
      <VCardTitle>
        {{ t('common.navigation.contractDocuments') }} — {{ labels[section] }}
      </VCardTitle>
        <template #append><VBtn color="secondary" prepend-icon="tabler-plus" @click="openEditor">{{ t('common.actions.add') }}</VBtn></template>
      </VCardItem>
      <VCardText class="contract-docs__tabs-body pb-0">
        <VTabs
          v-model="section"
          show-arrows
        >
          <VTab
            v-for="key in availableSections"
            :key="key"
            :value="key"
          >
            {{ labels[key] }}
          </VTab>
        </VTabs>
      </VCardText>
    <VWindow
      v-model="section"
      class="contract-docs__window"
    >
      <VWindowItem
        v-if="canViewTemplates"
        value="templates"
      >
        <ContractTemplatesView ref="templatesView" embedded />
      </VWindowItem>
      <VWindowItem
        v-if="canViewPolicies"
        value="policies"
      >
        <PolicyLibraryView ref="policiesView" embedded />
      </VWindowItem>
    </VWindow>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.contract-docs {
  display: flex;
  flex-direction: column;
  gap: 0;

  &__workspace {
    overflow: hidden;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 0.9rem;
  }

  &__tabs-body {
    padding-block-start: 0;
  }

  &__window {
    overflow: visible;
  }
}
</style>
