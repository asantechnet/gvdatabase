<script lang="ts">
import { ResponsiveDataTable } from './responsiveDataTable.ts'

export default ResponsiveDataTable
</script>

<style>
/* A quiet neutral row cue works in both themes without colouring data rows as
   though they were selected. Card layouts deliberately have no hover state. */
.responsive-data-table:not(.responsive-data-table--mobile-cards) .v-table__wrapper > table > tbody > tr:hover > td {
  background: rgba(var(--v-theme-on-surface), .035) !important;
}

.responsive-data-table .v-table__wrapper > table > tbody > tr:has(.table-empty-state):hover > td,
.responsive-data-table .v-table__wrapper > table > tbody > tr.responsive-data-table__skeleton-row:hover > td {
  background: transparent !important;
}

@media (max-width: 959px) {
  /* The table body becomes a card list at this breakpoint, so a desktop
     header row (and its duplicate labels) has no useful mobile purpose. */
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
