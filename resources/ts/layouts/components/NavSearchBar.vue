<script setup lang="ts">
import Shepherd from 'shepherd.js'
import { useRouter } from 'vue-router'
import type { RouteLocationRaw } from 'vue-router'
import { useConfigStore } from '@core/stores/config'

interface Suggestion {
  icon: string
  title: string
  url: RouteLocationRaw
}

interface SuggestionGroup {
  title: string
  content: Suggestion[]
}

interface SearchResultGroup {
  title: string
  children: Suggestion[]
}

defineOptions({ inheritAttrs: false })

const configStore = useConfigStore()
const router = useRouter()
const isAppSearchBarVisible = ref(false)
const isLoading = ref(false)
const searchQuery = ref('')
const searchResult = ref<SearchResultGroup[]>([])
const recentSuggestions = ref<Suggestion[]>([])
const recentStorageKey = 'erp-recent-destinations'

const suggestionGroups: SuggestionGroup[] = [
  {
    title: translate('components.navigation.navsearchbar.overview'),
    content: [
      { icon: 'tabler-home', title: translate('components.navigation.navsearchbar.home'), url: { name: 'home' } },
      { icon: 'tabler-chart-bar', title: translate('components.navigation.navsearchbar.reports'), url: { name: 'reports' } },
      { icon: 'tabler-notes', title: translate('components.navigation.navsearchbar.tasks'), url: { name: 'tasks' } },
    ],
  },
  {
    title: translate('components.navigation.navsearchbar.humanResources'),
    content: [
      { icon: 'tabler-users', title: translate('common.messages.staffList'), url: { name: 'staff-list' } },
      { icon: 'tabler-calendar-check', title: translate('common.messages.timesheet'), url: { name: 'timesheet' } },
      { icon: 'tabler-cash', title: translate('components.navigation.navsearchbar.staffSalary'), url: { name: 'staff-salary' } },
      { icon: 'tabler-award', title: translate('components.navigation.navsearchbar.bonusList'), url: { name: 'bonuslist' } },
    ],
  },
  {
    title: translate('components.navigation.navsearchbar.financeOperations'),
    content: [
      { icon: 'tabler-file-invoice', title: translate('components.navigation.navsearchbar.invoices'), url: { name: 'innvoice' } },
      { icon: 'tabler-arrow-down-circle', title: translate('components.navigation.navsearchbar.income'), url: { name: 'income' } },
      { icon: 'tabler-arrow-up-circle', title: translate('components.navigation.navsearchbar.expenses'), url: { name: 'expenses' } },
      { icon: 'tabler-package', title: translate('components.navigation.navsearchbar.asset'), url: { name: 'asset' } },
      { icon: 'tabler-user-search', title: translate('components.navigation.navsearchbar.visitors'), url: { name: 'visitor' } },
      { icon: 'tabler-file-description', title: translate('components.navigation.navsearchbar.contracts'), url: { name: 'contracts' } },
      { icon: 'tabler-users-group', title: translate('components.navigation.navsearchbar.customers'), url: { name: 'all-customers' } },
      { icon: 'tabler-file-x', title: translate('components.navigation.navsearchbar.cancelledContracts'), url: { name: 'cancelled-contracts' } },
    ],
  },
  {
    title: translate('components.navigation.navsearchbar.administration'),
    content: [
      { icon: 'tabler-users', title: translate('common.messages.users'), url: { name: 'setup-users' } },
      { icon: 'tabler-lock', title: translate('common.messages.roles'), url: { name: 'setup-roles' } },
      { icon: 'tabler-briefcase', title: translate('components.navigation.navsearchbar.positions'), url: { name: 'setup-positions' } },
      { icon: 'tabler-settings', title: translate('common.messages.settings'), url: { name: 'setup-settings' } },
      { icon: 'tabler-bell', title: translate('common.messages.notifications'), url: { name: 'notifications' } },
      { icon: 'tabler-files', title: translate('common.navigation.contractTemplates'), url: { name: 'contract-templates' } },
      { icon: 'tabler-list-details', title: translate('components.navigation.navsearchbar.contractPolicies'), url: { name: 'contract-policies' } },
      { icon: 'tabler-tool', title: translate('common.messages.services'), url: { name: 'options-services' } },
      { icon: 'tabler-currency-dollar', title: translate('components.navigation.navsearchbar.currencies'), url: { name: 'options-currencies' } },
      { icon: 'tabler-adjustments', title: translate('components.navigation.navsearchbar.financeOptions'), url: { name: 'options-finance' } },
    ],
  },
]

const noDataSuggestions: Suggestion[] = [
  { title: translate('common.messages.staffList'), icon: 'tabler-users', url: { name: 'staff-list' } },
  { title: translate('common.messages.timesheet'), icon: 'tabler-calendar-check', url: { name: 'timesheet' } },
  { title: translate('common.messages.settings'), icon: 'tabler-settings', url: { name: 'setup-settings' } },
]

const routeName = (suggestion: Suggestion) => {
  const location = suggestion.url

  return typeof location === 'object' && location !== null && 'name' in location
    ? String(location.name)
    : suggestion.title
}

const loadRecentSuggestions = () => {
  try {
    const stored = JSON.parse(localStorage.getItem(recentStorageKey) ?? '[]')

    recentSuggestions.value = Array.isArray(stored)
      ? stored.filter((item): item is Suggestion => typeof item?.title === 'string'
        && typeof item?.icon === 'string'
        && typeof item?.url === 'object'
        && item.url !== null).slice(0, 5)
      : []
  }
  catch {
    recentSuggestions.value = []
  }
}

const rememberSuggestion = (suggestion: Suggestion) => {
  const key = routeName(suggestion)

  recentSuggestions.value = [
    suggestion,
    ...recentSuggestions.value.filter(item => routeName(item) !== key),
  ].slice(0, 5)
  localStorage.setItem(recentStorageKey, JSON.stringify(recentSuggestions.value))
}

const fetchResults = () => {
  const query = searchQuery.value.trim().toLowerCase()

  searchResult.value = query
    ? suggestionGroups
      .map(group => ({
        title: group.title,
        children: group.content.filter(item => item.title.toLowerCase().includes(query)),
      }))
      .filter(group => group.children.length)
    : []
}

watch(searchQuery, fetchResults)

const closeSearchBar = () => {
  isAppSearchBarVisible.value = false
  searchQuery.value = ''
}

const redirectToSuggestedPage = (selected: Suggestion) => {
  rememberSuggestion(selected)
  router.push(selected.url)
  closeSearchBar()
}

const openSearch = () => {
  isAppSearchBarVisible.value = true
  Shepherd.activeTour?.cancel()
}

onMounted(loadRecentSuggestions)

const LazyAppBarSearch = defineAsyncComponent(() => import('@core/components/AppBarSearch.vue'))
</script>

<template>
  <div
    class="d-flex align-center"
    v-bind="$attrs"
  >
    <IconBtn
      aria-label="Open command palette"
      @click="openSearch"
    >
      <VIcon icon="tabler-search" />
      <VTooltip activator="parent">
        Search and commands
      </VTooltip>
    </IconBtn>

    <span
      v-if="configStore.appContentLayoutNav === 'vertical'"
      class="d-none d-md-flex align-center text-disabled ms-2"
      aria-hidden="true"
    >
      <span class="me-2">{{ $t('common.actions.search') }}</span>
      <span
        class="meta-key"
        dir="ltr"
        aria-hidden="true"
      >&#8984;K</span>
    </span>
  </div>

  <LazyAppBarSearch
    v-model:is-dialog-visible="isAppSearchBarVisible"
    :search-results="searchResult"
    :is-loading="isLoading"
    @search="searchQuery = $event"
  >
    <template #suggestions>
      <VCardText class="app-bar-search-suggestions pa-12">
        <VRow>
          <VCol
            v-if="recentSuggestions.length"
            cols="12"
          >
            <p
              class="custom-letter-spacing text-disabled text-uppercase py-2 px-4 mb-0"
              style="font-size: 0.75rem; line-height: 0.875rem;"
            >
              Recent
            </p>
            <VList class="card-list d-flex flex-wrap">
              <VListItem
                v-for="item in recentSuggestions"
                :key="`recent-${routeName(item)}`"
                class="app-bar-search-suggestion mx-4 mt-2"
                @click="redirectToSuggestedPage(item)"
              >
                <VListItemTitle>{{ item.title }}</VListItemTitle>
                <template #prepend>
                  <VIcon
                    :icon="item.icon"
                    size="20"
                    class="me-n1"
                  />
                </template>
              </VListItem>
            </VList>
          </VCol>
          <VCol
            v-for="group in suggestionGroups"
            :key="group.title"
            cols="12"
            sm="6"
          >
            <p
              class="custom-letter-spacing text-disabled text-uppercase py-2 px-4 mb-0"
              style="font-size: 0.75rem; line-height: 0.875rem;"
            >
              {{ group.title }}
            </p>
            <VList class="card-list">
              <VListItem
                v-for="item in group.content"
                :key="item.title"
                class="app-bar-search-suggestion mx-4 mt-2"
                @click="redirectToSuggestedPage(item)"
              >
                <VListItemTitle>{{ item.title }}</VListItemTitle>
                <template #prepend>
                  <VIcon
                    :icon="item.icon"
                    size="20"
                    class="me-n1"
                  />
                </template>
              </VListItem>
            </VList>
          </VCol>
        </VRow>
      </VCardText>
    </template>

    <template #noDataSuggestion>
      <div class="mt-9">
        <span class="d-flex justify-center text-disabled mb-2">{{ $t('components.navigation.navsearchbar.trySearchingFor') }}</span>
        <h6
          v-for="suggestion in noDataSuggestions"
          :key="suggestion.title"
          class="app-bar-search-suggestion text-h6 font-weight-regular cursor-pointer py-2 px-4"
          @click="redirectToSuggestedPage(suggestion)"
        >
          <VIcon
            size="20"
            :icon="suggestion.icon"
            class="me-2"
          />
          <span>{{ suggestion.title }}</span>
        </h6>
      </div>
    </template>

    <template #searchResult="{ item }">
      <VListSubheader class="text-disabled custom-letter-spacing font-weight-regular ps-4">
        {{ item.title }}
      </VListSubheader>
      <VListItem
        v-for="list in item.children"
        :key="list.title"
        @click="redirectToSuggestedPage(list)"
      >
        <template #prepend>
          <VIcon
            size="20"
            :icon="list.icon"
            class="me-n1"
          />
        </template>
        <template #append>
          <VIcon
            size="20"
            icon="tabler-corner-down-left"
            class="enter-icon flip-in-rtl"
          />
        </template>
        <VListItemTitle>{{ list.title }}</VListItemTitle>
      </VListItem>
    </template>
  </LazyAppBarSearch>
</template>

<style lang="scss">
@use "@styles/variables/vuetify.scss";

.meta-key {
  border: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  block-size: 1.5625rem;
  font-size: 0.8125rem;
  line-height: 1.3125rem;
  padding-block: 0.125rem;
  padding-inline: 0.25rem;
}

.app-bar-search-dialog {
  .custom-letter-spacing {
    letter-spacing: 0.8px;
  }

  .card-list {
    --v-card-list-gap: 8px;
  }
}
</style>
