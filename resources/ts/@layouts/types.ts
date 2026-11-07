import type { OffsetOptions } from '@floating-ui/dom'
import type { RouteLocationRaw } from 'vue-router'
import type { AppContentLayoutNav, ContentWidth, FooterType, HorizontalNavType, NavbarType } from '@layouts/enums'

export interface LayoutConfig {
  app: {
    title: string
    logo: VNode
    contentWidth: typeof ContentWidth[keyof typeof ContentWidth]
    contentLayoutNav: typeof AppContentLayoutNav[keyof typeof AppContentLayoutNav]
    overlayNavFromBreakpoint: number

    // isRTL: boolean
    i18n: {
      enable: boolean
    }
    iconRenderer: Component
  }
  navbar: {
    type: typeof NavbarType[keyof typeof NavbarType]
    navbarBlur: boolean
  }
  footer: {
    type: typeof FooterType[keyof typeof FooterType]
  }
  verticalNav: {
    isVerticalNavCollapsed: boolean
    defaultNavItemIconProps: unknown
  }
  horizontalNav: {
    type: typeof HorizontalNavType[keyof typeof HorizontalNavType]
    transition: string | Component
    popoverOffset?: OffsetOptions
  }
  icons: {
    chevronDown: any
    chevronRight: any
    close: any
    verticalNavPinned: any
    verticalNavUnPinned: any
    sectionTitlePlaceholder: any
  }
}

export interface AclProperties {
  action: string
  subject: string
  /** Any one of these permission subjects opens the item. */
  anyPermissions?: string[]
}

// 👉 Vertical nav section title
export interface NavSectionTitle extends Partial<AclProperties> {
  heading: string
}

// 👉 Vertical nav link
declare type ATagTargetAttrValues = '_blank' | '_self' | '_parent' | '_top' | 'framename'
declare type ATagRelAttrValues =
  | 'alternate'
  | 'author'
  | 'bookmark'
  | 'external'
  | 'help'
  | 'license'
  | 'next'
  | 'nofollow'
  | 'noopener'
  | 'noreferrer'
  | 'prev'
  | 'search'
  | 'tag'

export interface NavLinkProps {
  to?: RouteLocationRaw | string | null
  href?: string
  target?: ATagTargetAttrValues
  rel?: ATagRelAttrValues

  /*
    ℹ️ RouterLink's built-in active matching ignores query/params, so links that
    share a route (e.g. Contracts services differing only by `serviceId`) would
    all highlight at once. We neutralize those automatic classes here and drive
    highlighting solely via the query-aware `isNavLinkActive` binding instead.
  */
  activeClass?: string
  exactActiveClass?: string
}

export interface NavLink extends NavLinkProps, Partial<AclProperties> {
  title: string
  /** Set false for labels supplied by application data rather than locale files. */
  isTitleTranslationKey?: boolean
  icon?: unknown
  badgeContent?: string
  badgeClass?: string
  disable?: boolean
  requiredRole?: string
}

// 👉 Vertical nav group
export interface NavGroup extends Partial<AclProperties> {
  title: string
  icon?: unknown
  badgeContent?: string
  badgeClass?: string
  children: (NavLink | NavGroup)[]
  disable?: boolean
}

export declare type VerticalNavItems = (NavLink | NavGroup | NavSectionTitle)[]
export declare type HorizontalNavItems = (NavLink | NavGroup)[]

// 👉 Components ========================

export interface I18nLanguage {
  label: string
  i18nLang: string
  isRTL: boolean
}

// avatar | text | icon
// Thanks: https://stackoverflow.com/a/60617060/10796681
export type Notification = {
  id: number
  title: string
  subtitle: string
  time: string
  goToUrl?: string | null
  color?: string
  isSeen: boolean
  // Announcement labels, rendered as tags under the notification body.
  labels?: string[]
  // Announcement sender, shown above the message so authorship stays visible.
  sender?: string
} & (
  | { img: string; text?: never; icon?: never }
  | { img?: never; text: string; icon?: never }
  | { img?: never; text?: never; icon: string }
)

export interface ThemeSwitcherTheme {
  name: string
  icon: string
}
