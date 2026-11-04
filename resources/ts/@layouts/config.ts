import { breakpointsVuetify } from "@vueuse/core";
import {
  AppContentLayoutNav,
  ContentWidth,
  FooterType,
  HorizontalNavType,
  NavbarType,
} from "@layouts/enums";
import type { LayoutConfig } from "@layouts/types";

export const layoutConfig: LayoutConfig = {
  app: {
    title: "my-layout",
    logo: h("span"),
    contentWidth: ContentWidth.Boxed,
    contentLayoutNav: AppContentLayoutNav.Vertical,
    // Keep the permanent sidebar from the md breakpoint onward. The overlay
    // menu is reserved for widths below it.
    overlayNavFromBreakpoint: breakpointsVuetify.md - 1,

    // isRTL: false,
    i18n: {
      enable: true,
    },
    iconRenderer: h("div"),
  },
  navbar: {
    type: NavbarType.Sticky,
    navbarBlur: true,
  },
  footer: {
    type: FooterType.Static,
  },
  verticalNav: {
    isVerticalNavCollapsed: false,
    defaultNavItemIconProps: { icon: "tabler-circle" },
  },
  horizontalNav: {
    type: HorizontalNavType.Sticky,
    transition: "none",
    popoverOffset: 0,
  },
  icons: {
    chevronDown: { icon: "tabler-chevron-down" },
    chevronRight: { icon: "tabler-chevron-right" },
    close: { icon: "tabler-x" },
    verticalNavPinned: { icon: "tabler-circle-dot" },
    verticalNavUnPinned: { icon: "tabler-circle" },
    sectionTitlePlaceholder: { icon: "tabler-minus" },
  },
};
