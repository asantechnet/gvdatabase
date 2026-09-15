<script lang="ts" setup>
import { HorizontalNavGroup, HorizontalNavLink } from '@layouts/components'
import HeaderPageGuide from '@/layouts/components/HeaderPageGuide.vue'
import type { HorizontalNavItems, NavGroup, NavLink } from '@layouts/types'

defineProps<{
  navItems: HorizontalNavItems
}>()

const resolveNavItemComponent = (item: NavLink | NavGroup) => {
  if ('children' in item)
    return HorizontalNavGroup

  return HorizontalNavLink
}
</script>

<template>
  <ul class="nav-items">
    <li class="nav-page-guide">
      <HeaderPageGuide />
    </li>
    <Component
      :is="resolveNavItemComponent(item)"
      v-for="(item, index) in navItems"
      :key="index"
      data-allow-mismatch
      :item="item"
    />
  </ul>
</template>

<style lang="scss">
.layout-wrapper.layout-nav-type-horizontal {
  .nav-items {
    display: flex;
    flex-wrap: nowrap;
    inline-size: max-content;
    min-inline-size: 100%;
    white-space: nowrap;

    > .nav-link,
    > .nav-group,
    > .nav-page-guide {
      flex: 0 0 auto;
      scroll-snap-align: start;
    }

    > .nav-page-guide {
      display: flex;
      align-items: center;
      padding-inline: .5rem;
    }
  }
}
</style>
