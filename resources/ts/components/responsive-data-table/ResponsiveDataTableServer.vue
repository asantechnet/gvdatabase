<script lang="ts">
import { ResponsiveDataTableServer } from './ResponsiveDataTableServer.ts'

export default ResponsiveDataTableServer
</script>

<style>
/* Server-backed lists use the same card renderer as local lists.  Keep this
   here too: route-level CSS for the local wrapper is not loaded on pages that
   only render VDataTableServer. */
.responsive-data-table:not(.responsive-data-table--mobile-cards) .v-table__wrapper > table > tbody > tr:hover > td {
  background: rgba(var(--v-theme-on-surface), .035) !important;
}

.responsive-data-table .v-table__wrapper > table > tbody > tr:has(.table-empty-state):hover > td,
.responsive-data-table .v-table__wrapper > table > tbody > tr.responsive-data-table__skeleton-row:hover > td {
  background: transparent !important;
}

@media (max-width: 959px) {
  .responsive-data-table.responsive-data-table--mobile-cards .v-table__wrapper > table > thead,
  .responsive-data-table.responsive-data-table--mobile-cards .v-data-table__thead {
    display: none !important;
  }

  .responsive-data-table.responsive-data-table--mobile-cards .v-table__wrapper > table > tbody > tr:hover > td,
  .responsive-data-table.responsive-data-table--mobile-cards .responsive-data-table__card:hover {
    background: transparent !important;
  }
}
</style>
