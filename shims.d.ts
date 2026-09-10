export {};

declare module "*.vue" {
  import type { DefineComponent } from "vue";

  const component: DefineComponent<{}, {}, any>;
  export default component;
}

declare module "@vue/runtime-core" {
  interface ComponentCustomProperties {
    $t: (key: string, values?: Record<string, unknown>) => string;
    $vuetify: any;
    $router: import("vue-router").Router;
    $route: import("vue-router").RouteLocationNormalizedLoaded;
    $i18n: any;
    avatarText: typeof import("./resources/ts/@core/utils/formatters").avatarText;
    h: typeof import("vue").h;
    paginationMeta: typeof import("./resources/ts/utils/paginationMeta").paginationMeta;
    translate: typeof import("./resources/ts/utils/localization").translate;
    translateBackendValue: typeof import("./resources/ts/utils/localization").translateBackendValue;
    formatLocalizedDate: typeof import("./resources/ts/utils/localization").formatLocalizedDate;
    formatLocalizedNumber: typeof import("./resources/ts/utils/localization").formatLocalizedNumber;
    requiredValidator: typeof import("./resources/ts/@core/utils/validators").requiredValidator;
  }
}
