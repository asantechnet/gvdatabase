<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { canAccess } from '@/utils/authorization'
import './workspace.scss'

const props = withDefaults(defineProps<{
  title: string
  section: 'contracts' | 'crm' | 'applicants' | 'dashboard'
  /** Pages whose own tab strip is the primary navigation hide the crumb trail. */
  breadcrumbs?: boolean
}>(), { breadcrumbs: true })
const { t } = useI18n()
const route = useRoute()
const links = computed(() => {
  const groups = {
    contracts: [
      ['private-contracts', 'educational.builtInServices.private', 'view_contracts'],
      ['scholarship-contracts', 'educational.builtInServices.scholarship', 'view_contracts'],
      ['test-offer-contracts', 'educational.builtInServices.testOffer', 'view_contracts'],
      ['academy-contracts', 'educational.builtInServices.academy', 'view_contracts'],
      ['other-contracts', 'common.navigation.otherContracts', 'view_contracts'],
      ['cancelled-contracts', 'common.navigation.cancelledContracts', 'view_contracts'],
    ],
    crm: [['visitor', 'common.navigation.visitor', 'view_visitors'], ['all-customers', 'common.navigation.applicants', 'view_applicants']],
    applicants: [],
    dashboard: [],
  }
  return groups[props.section].filter(([, , permission]) => canAccess(permission)).map(([name, label]) => ({ name, label }))
})
</script>

<template>
  <header class="erp-page-header">
    <div class="erp-page-heading">
      <VBreadcrumbs v-if="breadcrumbs" :items="[{ title: t('erp.home'), to: { name: 'home' } }, { title: t(`erp.${section}`), disabled: true }]" density="compact" class="pa-0 mb-1" />
      <h1 class="text-h5 font-weight-bold">{{ title }}</h1>
    </div>
    <nav v-if="links.length || $slots.navigation" class="erp-subnav" :aria-label="t(`erp.${section}`)">
      <VBtn v-for="link in links" :key="link.name" :to="{ name: link.name }" size="small" :variant="route.name === link.name ? 'flat' : 'tonal'" :color="route.name === link.name ? 'primary' : 'secondary'" :aria-current="route.name === link.name ? 'page' : undefined">{{ t(link.label) }}</VBtn>
    <slot name="navigation" /></nav>
    <div v-if="$slots.actions" class="erp-header-actions"><slot name="actions" /></div>
  </header>
</template>
