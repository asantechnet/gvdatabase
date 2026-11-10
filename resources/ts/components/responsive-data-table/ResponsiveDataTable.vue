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

.responsive-data-table .v-table__wrapper > table > tbody > tr.responsive-data-table__skeleton-row:hover {
  background: transparent !important;
}

.responsive-data-table .v-table__wrapper > table > tbody > tr.responsive-data-table__skeleton-row > td {
  block-size: 3.45rem;
  border-block-end: 0 !important;
  padding-block: .35rem !important;
}

.responsive-data-table .v-table__wrapper > table > tbody > tr.responsive-data-table__skeleton-row:nth-child(odd) > td {
  background: transparent;
}

.responsive-data-table .v-table__wrapper > table > tbody > tr.responsive-data-table__skeleton-row:nth-child(even) > td {
  background: rgba(var(--v-theme-on-surface), .035);
}

.responsive-data-table .responsive-data-table__skeleton-bar {
  display: block;
  block-size: .78rem;
  inline-size: 72%;
  overflow: hidden;
  position: relative;
  border-radius: 999px;
  background: rgba(var(--v-theme-on-surface), .11);
}

.responsive-data-table .responsive-data-table__skeleton-bar::after {
  position: absolute;
  content: '';
  inset: 0;
  background: linear-gradient(90deg, transparent, rgba(var(--v-theme-on-surface), .13), transparent);
  animation: responsive-data-table-skeleton-wave 1.45s ease-in-out infinite;
  transform: translateX(-100%);
}

/* Reserve the same checkbox/expand-cell footprint in skeletons without
   pretending a control is interactive. This keeps the final Actions column
   and its header aligned while a selectable table is loading. */
.responsive-data-table .responsive-data-table__skeleton-select {
  display: block;
  inline-size: 1.25rem;
}

.responsive-data-table .responsive-data-table__skeleton-row > td:nth-child(3n + 1) .responsive-data-table__skeleton-bar { inline-size: 58%; }
.responsive-data-table .responsive-data-table__skeleton-row > td:nth-child(3n + 2) .responsive-data-table__skeleton-bar { inline-size: 82%; }

@keyframes responsive-data-table-skeleton-wave {
  to { transform: translateX(100%); }
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

  /* Route tables can set generous desktop column minimums.  Once this
     component switches to cards, those values must not keep a one-cell table
     wider than the viewport. */
  .responsive-data-table.responsive-data-table--mobile-cards .v-table__wrapper {
    overflow: hidden !important;
  }

  .responsive-data-table.responsive-data-table--mobile-cards .v-table__wrapper > table {
    inline-size: 100% !important;
    min-inline-size: 0 !important;
    table-layout: auto !important;
  }

  .responsive-data-table.responsive-data-table--mobile-cards .responsive-data-table__mobile-row > td {
    min-inline-size: 0 !important;
    padding: .75rem !important;
    white-space: normal !important;
  }

  .responsive-data-table.responsive-data-table--mobile-cards .responsive-data-table__cards {
    gap: .75rem !important;
  }
}
</style>
