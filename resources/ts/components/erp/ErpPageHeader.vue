<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { canAccess } from '@/utils/authorization'
import './workspace.scss'

type HeaderSection = 'contracts' | 'crm' | 'applications' | 'dashboard' | 'tasks' | 'finance' | 'hr'

// One in-page destination inside a concept's navigation strip.
interface HeaderLink {
  name: string
  label: string
  icon: string
  color: string
  /** Permission required to see this destination; omit for permission-free pages. */
  permission?: string
  /** Any one of these permissions opens the destination. */
  permissions?: string[]
  /**
   * Query for the destination. Two destinations may share one route when the
   * query selects the mode, e.g. the CRM Visitors / Follow-up list pair.
   */
  query?: Record<string, string>
}

const props = withDefaults(defineProps<{
  title: string
  section: HeaderSection
  /** Optional sentence under the H1; pages with only a title omit it. */
  description?: string
  /** Pages whose own tab strip is the primary navigation hide the crumb trail. */
  breadcrumbs?: boolean
}>(), { breadcrumbs: true })
const { t } = useI18n()
const route = useRoute()
const navigation = ref<HTMLElement | null>(null)

// One in-page navigation per concept. Each destination owns its icon and colour
// so the strip reads as a set of purposeful workspaces rather than a row of
// identical buttons, and every page of a concept shows the same strip (this is
// what interconnects the Contracts, CRM and Applications pages).
const sections: Record<HeaderSection, HeaderLink[]> = {
  contracts: [
    { name: 'private-contracts', label: 'educational.builtInServices.private', icon: 'tabler-user', color: 'info', permission: 'view_private_contracts' },
    { name: 'scholarship-contracts', label: 'educational.builtInServices.scholarship', icon: 'tabler-school', color: 'primary', permission: 'view_scholarship_contracts' },
    { name: 'test-offer-contracts', label: 'educational.builtInServices.testOffer', icon: 'tabler-certificate', color: 'success', permission: 'view_test_offer_contracts' },
    { name: 'academy-contracts', label: 'educational.builtInServices.academy', icon: 'tabler-book', color: 'tertiary', permission: 'view_academy_contracts' },
    { name: 'other-contracts', label: 'common.navigation.otherContracts', icon: 'tabler-files', color: 'secondary', permission: 'view_other_contracts' },
    { name: 'cancelled-contracts', label: 'common.navigation.cancelledContracts', icon: 'tabler-ban', color: 'error', permissions: ['view_private_contracts', 'view_scholarship_contracts', 'view_test_offer_contracts', 'view_academy_contracts', 'view_employment_contracts', 'view_other_contracts'] },
  ],
  crm: [
    { name: 'visitor', label: 'common.navigation.visitor', icon: 'tabler-user-search', color: 'primary', permission: 'view_visitors' },
    { name: 'visitor', query: { list: 'follow-up' }, label: 'common.navigation.followUpList', icon: 'tabler-list-check', color: 'warning', permission: 'view_visitors' },
  ],
  applications: [
    { name: 'applications', label: 'educational.applications', icon: 'tabler-clipboard-list', color: 'primary', permission: 'view_applicants' },
    { name: 'application-reports', label: 'common.navigation.applicationReports', icon: 'tabler-report-analytics', color: 'tertiary', permission: 'view_visa_reports' },
  ],
  dashboard: [],
  tasks: [],
  finance: [],
  hr: [
    { name: 'staff-list', label: 'common.navigation.staffList', icon: 'tabler-users', color: 'primary', permission: 'view_employees' },
    { name: 'setup-departments', label: 'common.navigation.departments', icon: 'tabler-sitemap', color: 'info', permission: 'view_departments' },
    { name: 'employee-contracts', label: 'common.navigation.employmentContracts', icon: 'tabler-file-text', color: 'tertiary', permission: 'view_contracts' },
    { name: 'leave-requests', label: 'pages.leaveRequests.title', icon: 'tabler-beach', color: 'warning', permission: 'view_leave_requests' },
    { name: 'payrolls', label: 'common.navigation.payrolls', icon: 'tabler-wallet', color: 'success', permission: 'view_salaries' },
  ],
}

const links = computed(() => sections[props.section].filter(link => !link.permission || canAccess(link.permission)))
const sectionLabel = computed(() => props.section === 'hr'
  ? t('common.navigation.hrm')
  : t(`erp.${props.section}`))

// Destinations on the same route are told apart by the query markers the strip
// itself declares (`?list=follow-up`). Only those keys are compared, so unrelated
// params — such as the contract form's `?visitor=<id>` deep link — never change
// which destination is marked current.
const isCurrent = (link: HeaderLink) => {
  if (route.name !== link.name)
    return false
  const markers = new Set(links.value.flatMap(item => Object.keys(item.query ?? {})))
  const expected = link.query ?? {}

  return [...markers].every(key => String(route.query[key] ?? '') === (expected[key] ?? ''))
}

// On narrow screens the concept switcher is intentionally a single scrollable
// row. Keep the selected workspace visible without moving keyboard focus or
// unexpectedly changing the page's vertical scroll position.
const revealCurrentDestination = (behavior: ScrollBehavior) => {
  nextTick(() => {
    navigation.value
      ?.querySelector<HTMLElement>('[aria-current="page"]')
      ?.scrollIntoView({ block: 'nearest', inline: 'center', behavior })
  })
}

onMounted(() => revealCurrentDestination('auto'))
watch(() => route.fullPath, () => revealCurrentDestination('smooth'))
</script>

<template>
  <header class="erp-page-header">
    <div class="erp-page-heading">
      <VBreadcrumbs v-if="breadcrumbs" :items="[{ title: t('erp.home'), to: { name: 'home' } }, { title: sectionLabel, disabled: true }]" density="compact" class="pa-0 mb-1" />
      <h1 class="text-h5 font-weight-bold">{{ title }}</h1>
      <p
        v-if="description"
        class="erp-page-description text-body-2 text-medium-emphasis mb-0 mt-1"
      >
        {{ description }}
      </p>
    </div>
    <nav ref="navigation" v-if="links.length || $slots.navigation" class="erp-subnav" :aria-label="sectionLabel">
      <VChip
        v-for="link in links"
        :key="`${link.name}-${link.query?.list ?? ''}`"
        class="erp-subnav-chip"
        :to="{ name: link.name, query: link.query }"
        size="large"
        :color="link.color"
        :prepend-icon="link.icon"
        :variant="isCurrent(link) ? 'flat' : 'outlined'"
        :aria-current="isCurrent(link) ? 'page' : undefined"
      >
        {{ t(link.label) }}
      </VChip>
      <slot name="navigation" />
    </nav>
    <div v-if="$slots.actions" class="erp-header-actions"><slot name="actions" /></div>
  </header>
</template>
