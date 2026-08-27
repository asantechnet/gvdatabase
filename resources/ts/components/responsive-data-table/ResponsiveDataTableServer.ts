import { VDataTableServer } from 'vuetify/components'
import { createResponsiveDataTable } from './responsiveDataTable'

// Keep a distinct registration name so Vuexy's VDataTableServer defaults and
// server-side events remain unchanged while sharing the mobile card renderer.
export const ResponsiveDataTableServer = createResponsiveDataTable(VDataTableServer, 'ResponsiveDataTableServer')
