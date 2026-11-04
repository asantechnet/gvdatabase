<script lang="ts" setup>
import type { Component } from "vue";
import { useRoute } from "vue-router";
import { PerfectScrollbar } from "vue3-perfect-scrollbar";
import { layoutConfig } from "@layouts";
import {
  VerticalNavGroup,
  VerticalNavLink,
  VerticalNavSectionTitle,
} from "@layouts/components";
import { useLayoutConfigStore } from "@layouts/stores/config";
import { injectionKeyIsVerticalNavHovered } from "@layouts/symbols";
import type {
  NavGroup,
  NavLink,
  NavSectionTitle,
  VerticalNavItems,
} from "@layouts/types";

interface Props {
  tag?: string | Component;
  navItems: VerticalNavItems;
  isOverlayNavActive: boolean;
  toggleIsOverlayNavActive: (value: boolean) => void;
}

const props = withDefaults(defineProps<Props>(), {
  tag: "aside",
});

const refNav = ref();
const configStore = useLayoutConfigStore();

const isHovered = useElementHover(refNav);
const isHoverExpansionAllowed = computed(() =>
  configStore.isVerticalNavHoverEnabled ? isHovered.value : false,
);

provide(injectionKeyIsVerticalNavHovered, isHoverExpansionAllowed);

const resolveNavItemComponent = (
  item: NavLink | NavSectionTitle | NavGroup,
): unknown => {
  if ("heading" in item) return VerticalNavSectionTitle;
  if ("children" in item) return VerticalNavGroup;

  return VerticalNavLink;
};

/*
  ℹ️ Close overlay side when route is changed
  Close overlay vertical nav when link is clicked
*/
const route = useRoute();

watch(
  () => route.name,
  () => {
    props.toggleIsOverlayNavActive(false);
  },
);

const isVerticalNavScrolled = ref(false);
const updateIsVerticalNavScrolled = (val: boolean) =>
  (isVerticalNavScrolled.value = val);

const handleNavScroll = (evt: Event) => {
  isVerticalNavScrolled.value = (evt.target as HTMLElement).scrollTop > 0;
};
</script>

<template>
  <Component
    :is="props.tag"
    ref="refNav"
    data-allow-mismatch
    class="layout-vertical-nav"
    :class="[
      {
        'overlay-nav': configStore.isLessThanOverlayNavBreakpoint,
        hovered: isHoverExpansionAllowed,
        visible: isOverlayNavActive,
        scrolled: isVerticalNavScrolled,
      },
    ]"
  >
    <!-- 👉 Header -->
    <div class="nav-header">
      <slot name="nav-header">
        <!-- 👉 Vertical nav actions -->
        <!-- Show toggle collapsible in >md and close button in <md -->
        <div class="header-action">
          <Component
            :is="layoutConfig.app.iconRenderer || 'div'"
            v-show="configStore.isVerticalNavCollapsed"
            class="d-none nav-unpin"
            :class="configStore.isVerticalNavCollapsed && 'd-md-block'"
            v-bind="layoutConfig.icons.verticalNavUnPinned"
            @click="
              configStore.isVerticalNavCollapsed =
                !configStore.isVerticalNavCollapsed
            "
          />
          <Component
            :is="layoutConfig.app.iconRenderer || 'div'"
            v-show="!configStore.isVerticalNavCollapsed"
            class="d-none nav-pin"
            :class="!configStore.isVerticalNavCollapsed && 'd-md-block'"
            v-bind="layoutConfig.icons.verticalNavPinned"
            @click="
              configStore.isVerticalNavCollapsed =
                !configStore.isVerticalNavCollapsed
            "
          />
          <Component
            :is="layoutConfig.app.iconRenderer || 'div'"
            class="d-md-none"
            v-bind="layoutConfig.icons.close"
            @click="toggleIsOverlayNavActive(false)"
          />
        </div>
      </slot>
    </div>
    <slot name="before-nav-items">
      <div class="vertical-nav-items-shadow" />
    </slot>
    <slot
      name="nav-items"
      :update-is-vertical-nav-scrolled="updateIsVerticalNavScrolled"
    >
      <PerfectScrollbar
        :key="String(configStore.isAppRTL)"
        tag="ul"
        class="nav-items"
        :options="{ wheelPropagation: false }"
        @ps-scroll-y="handleNavScroll"
      >
        <Component
          :is="resolveNavItemComponent(item)"
          v-for="(item, index) in navItems"
          :key="index"
          :item="item"
        />
      </PerfectScrollbar>
    </slot>
    <slot name="after-nav-items" />
  </Component>
</template>

<style lang="scss">
@use "@configured-variables" as variables;
@use "@layouts/styles/mixins";

// 👉 Vertical Nav
.layout-vertical-nav {
  --vertical-nav-transition-duration: 320ms;

  position: fixed;
  z-index: variables.$layout-vertical-nav-z-index;
  display: flex;
  flex-direction: column;
  block-size: 100%;
  inline-size: variables.$layout-vertical-nav-width;
  inset-block-start: 0;
  inset-inline-start: 0;
  transition:
    inline-size var(--vertical-nav-transition-duration) cubic-bezier(0.22, 1, 0.36, 1),
    transform var(--vertical-nav-transition-duration) cubic-bezier(0.22, 1, 0.36, 1),
    box-shadow var(--vertical-nav-transition-duration) ease;
  will-change: transform, inline-size, box-shadow;

  .nav-header {
    display: flex;
    align-items: center;

    .header-action {
      cursor: pointer;

      @at-root {
        #{variables.$selector-vertical-nav-mini} .nav-header .header-action {
          &.nav-pin,
          &.nav-unpin {
            display: none !important;
          }
        }
      }
    }
  }

  .app-title-wrapper {
    margin-inline-end: auto;
  }

  .nav-items {
    // Consume only the space left after the header/footer slots. A fixed
    // 100% block size adds the header height on top of the viewport and makes
    // PerfectScrollbar report overflow even when all links fit.
    flex: 1 1 auto;
    min-block-size: 0;
    block-size: auto;

    // ℹ️ We no loner needs this overflow styles as perfect scrollbar applies it
    // overflow-x: hidden;

    // // ℹ️ We used `overflow-y` instead of `overflow` to mitigate overflow x. Revert back if any issue found.
    // overflow-y: auto;
  }

  // PerfectScrollbar still renders its rails in the DOM when the container
  // does not overflow. Keep those inactive rails out of the layout entirely;
  // active rails remain available when the menu actually needs scrolling.
  .nav-items.ps:not(.ps--active-y) > .ps__rail-y,
  .nav-items.ps:not(.ps--active-x) > .ps__rail-x {
    display: none !important;
    pointer-events: none;
  }

  .nav-link,
  .nav-group {
    margin-block: 2px;
  }

  .nav-item-title {
    overflow: hidden;
    margin-inline-end: auto;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  // 👉 Collapsed
  .layout-vertical-nav-collapsed & {
    &:not(.hovered) {
      inline-size: variables.$layout-vertical-nav-collapsed-width;
    }
  }
}

// Small screen vertical nav transition
@media (max-width: 959px) {
  .layout-vertical-nav {
    // Compositing the drawer prevents a repaint of the navigation list on
    // every frame, which was the source of the rough mobile slide.
    backface-visibility: hidden;
    transform: translate3d(0, 0, 0);
    transition: transform 280ms cubic-bezier(0.22, 0.61, 0.36, 1);
    will-change: transform;

    &:not(.visible) {
      transform: translate3d(-#{variables.$layout-vertical-nav-width}, 0, 0);

      @include mixins.rtl {
        transform: translate3d(variables.$layout-vertical-nav-width, 0, 0);
      }
    }
  }
}
</style>
