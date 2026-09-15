<script lang="ts" setup>
import { HorizontalNav } from '@layouts/components'
import type { HorizontalNavItems } from '@layouts/types'

// ℹ️ Using import from `@layouts` causing build to hangup
// import { useLayouts } from '@layouts'
import { useLayoutConfigStore } from '@layouts/stores/config'

defineProps<{
  navItems: HorizontalNavItems
}>()

const configStore = useLayoutConfigStore()
const navScroller = ref<HTMLElement>()

// Keep the behavior local to the navigation bar: no document-level listener,
// polling, or animation loop. Vertical wheel movement becomes horizontal only
// when there is actually hidden navigation content to reveal.
function scrollNavigationWithWheel(event: WheelEvent): void {
  const element = navScroller.value
  if (!element || event.ctrlKey || event.deltaY === 0 || element.scrollWidth <= element.clientWidth)
    return

  event.preventDefault()
  element.scrollLeft -= event.deltaY
}
</script>

<template>
  <div
    class="layout-wrapper"
    data-allow-mismatch
    :class="configStore._layoutClasses"
  >
    <div
      class="layout-navbar-and-nav-container"
      :class="configStore.isNavbarBlurEnabled && 'header-blur'"
    >
      <!-- 👉 Navbar -->
      <div class="layout-navbar">
        <div class="navbar-content-container">
          <slot name="navbar" />
        </div>
      </div>
      <!-- 👉 Navigation -->
      <div class="layout-horizontal-nav">
        <div
          ref="navScroller"
          class="horizontal-nav-content-container"
          @wheel="scrollNavigationWithWheel"
        >
          <HorizontalNav :nav-items="navItems" />
        </div>
      </div>
    </div>

    <main class="layout-page-content">
      <slot />
    </main>

    <!-- 👉 Footer -->
    <footer class="layout-footer">
      <div class="footer-content-container">
        <slot name="footer" />
      </div>
    </footer>
  </div>
</template>

<style lang="scss">
@use "@configured-variables" as variables;
@use "@layouts/styles/placeholders";
@use "@layouts/styles/mixins";

.layout-wrapper {
  &.layout-nav-type-horizontal {
    display: flex;
    flex-direction: column;

    // // TODO(v2): Check why we need height in vertical nav & min-height in horizontal nav
    // min-height: 100%;
    min-block-size: 100dvh;

    .layout-navbar-and-nav-container {
      z-index: 1;
    }

    .layout-navbar {
      z-index: variables.$layout-horizontal-nav-layout-navbar-z-index;
      block-size: 64px;
      min-block-size: 64px;

      // ℹ️ For now we are not independently managing navbar and horizontal nav so we won't use below style to avoid conflicting with combo style of navbar and horizontal nav
      // If we add independent style of navbar & horizontal nav then we have to add :not for avoiding conflict with combo styles
      // .layout-navbar-sticky & {
      //   @extend %layout-navbar-sticky;
      // }

      // ℹ️ For now we are not independently managing navbar and horizontal nav so we won't use below style to avoid conflicting with combo style of navbar and horizontal nav
      // If we add independent style of navbar & horizontal nav then we have to add :not for avoiding conflict with combo styles
      // .layout-navbar-hidden & {
      //   @extend %layout-navbar-hidden;
      // }
    }

    // 👉 Navbar
    .navbar-content-container {
      @include mixins.boxed-content;
      min-block-size: 64px;
    }

    // 👉   Content height fixed
    &.layout-content-height-fixed {
      max-block-size: 100dvh;

      .layout-page-content {
        overflow: hidden;

        > :first-child {
          max-block-size: 100%;
          overflow-y: auto;
        }
      }
    }

    // 👉 Footer
    // Boxed content
    .layout-footer {
      .footer-content-container {
        @include mixins.boxed-content;
      }
    }
  }

  // If both navbar & horizontal nav sticky
  &.layout-navbar-sticky.horizontal-nav-sticky {
    .layout-navbar-and-nav-container {
      position: sticky;
      inset-block-start: 0;
      will-change: transform;
    }
  }

  &.layout-navbar-hidden.horizontal-nav-hidden {
    .layout-navbar-and-nav-container {
      display: none;
    }
  }
}

// 👉 Horizontal nav nav
.layout-horizontal-nav {
  z-index: variables.$layout-horizontal-nav-z-index;

  // .horizontal-nav-sticky & {
  //   width: 100%;
  //   will-change: transform;
  //   position: sticky;
  //   top: 0;
  // }

  // .horizontal-nav-hidden & {
  //   display: none;
  // }

  .horizontal-nav-content-container {
    @include mixins.boxed-content(true);

    // Navigation is a single deliberate row. On smaller viewports or with
    // longer localized labels, it scrolls within its own bar instead of
    // increasing the header height or wrapping into a second row.
    overflow-x: auto;
    overflow-y: hidden;
    overscroll-behavior-inline: contain;
    scroll-behavior: smooth;
    scroll-snap-type: inline proximity;
    -ms-overflow-style: none;
    scrollbar-width: none;

    // The soft edge treatment hints at horizontal content without adding
    // fixed arrow controls that would behave poorly in RTL layouts.
    mask-image: linear-gradient(to right, transparent, #000 1rem, #000 calc(100% - 1rem), transparent);

    &::-webkit-scrollbar { display: none; }
  }
}
</style>
