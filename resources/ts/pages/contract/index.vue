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
const breadcrumbs = computed(() => [
  { title: t('erp.home'), to: { name: 'home' } },
  { title: t('common.navigation.options'), disabled: true },
  { title: t('common.navigation.contract'), disabled: true },
])

const section = ref<ContractDocsSection>('templates')

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
    <VBreadcrumbs :items="breadcrumbs" density="compact" class="pa-0 mb-1" />
    <h1 class="contract-docs__title text-h5 font-weight-bold mb-0">{{ t('common.navigation.contractDocuments') }}</h1>
    <VCard class="contract-docs__workspace">
      <VWindow v-model="section" class="contract-docs__window">
        <VWindowItem v-if="canViewTemplates" value="templates">
          <ContractTemplatesView embedded>
            <template #tabs>
              <VTabs v-model="section" show-arrows class="contract-docs__tabs px-4">
                <VTab v-for="key in availableSections" :key="key" :value="key">{{ labels[key] }}</VTab>
              </VTabs>
              <VDivider />
            </template>
          </ContractTemplatesView>
        </VWindowItem>
        <VWindowItem v-if="canViewPolicies" value="policies">
          <PolicyLibraryView embedded>
            <template #tabs>
              <VTabs v-model="section" show-arrows class="contract-docs__tabs px-4">
                <VTab v-for="key in availableSections" :key="key" :value="key">{{ labels[key] }}</VTab>
              </VTabs>
              <VDivider />
            </template>
          </PolicyLibraryView>
        </VWindowItem>
      </VWindow>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.contract-docs {
  display: flex;
  flex-direction: column;
  gap: .15rem;

  &__title { line-height: 1.35; margin-block-end: .6rem !important; }
  &__workspace { overflow: hidden; border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: .9rem; }
  &__tabs { background: transparent !important; box-shadow: none !important; }
  &__window { overflow: visible; }
}
</style>
