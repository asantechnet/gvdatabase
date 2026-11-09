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

/* Keep loading rows in the table itself.  This is intentionally compact so it
 * has the same rhythm as the contracts lists instead of looking like five
 * disconnected empty cards. */
.responsive-data-table .v-table__wrapper > table > tbody > tr.responsive-data-table__skeleton-row > td {
  block-size: 3.8rem;
  border-block-end: 0 !important;
  padding-block: .55rem !important;
}

.responsive-data-table .v-table__wrapper > table > tbody > tr.responsive-data-table__skeleton-row:nth-child(odd) > td {
  background: rgba(var(--v-theme-on-surface), .025);
}

.responsive-data-table .v-table__wrapper > table > tbody > tr.responsive-data-table__skeleton-row:nth-child(even) > td {
  background: rgba(var(--v-theme-on-surface), .045);
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

.responsive-data-table .responsive-data-table__skeleton-row > td:nth-child(3n + 1) .responsive-data-table__skeleton-bar { inline-size: 58%; }
.responsive-data-table .responsive-data-table__skeleton-row > td:nth-child(3n + 2) .responsive-data-table__skeleton-bar { inline-size: 82%; }

@keyframes responsive-data-table-skeleton-wave {
  to { transform: translateX(100%); }
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
