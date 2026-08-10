import "vue-router";
declare module "vue-router" {
  interface RouteMeta {
    action?: string;
    subject?: string;
    layoutWrapperClasses?: string;
    navActiveLink?: RouteLocationRaw;
    layout?: "blank" | "default";
    unauthenticatedOnly?: boolean;
    public?: boolean;
  }
}

// Environment variables type declaration
interface ImportMetaEnv {
  readonly VITE_API_BASE_URL: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
