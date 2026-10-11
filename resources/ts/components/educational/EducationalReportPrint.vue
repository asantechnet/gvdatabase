<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useBranding } from '@/composables/useBranding'

/**
 * Theme-independent print document for the Visa Reports page.
 *
 * The on-screen report is a Vuetify table, so its colours come from the active
 * theme and a dark theme printed dark. This section is plain HTML with forced
 * light colours, hidden on screen and revealed only inside a print job
 * (`printDocument` clones it into `#app-print-host` and adds
 * `printing-isolated-document`; native Ctrl+P mirrors `printing-education-reports`).
 */
defineProps<{
  title: string
  subtitle?: string
  columns: string[]
  rows: Array<Array<string | number>>
  facts?: Array<{ label: string, value: string }>
  period?: string
  generatedAt?: string
  totalLabel?: string
  direction?: 'ltr' | 'rtl'
}>()

const { t } = useI18n()
const { logoUrl, originalCompanyName } = useBranding()
</script>

<template>
  <section
    class="education-print-report"
    :dir="direction ?? 'ltr'"
  >
    <header class="education-print-report__header">
      <div class="education-print-report__brand">
        <img
          :src="logoUrl"
          :alt="originalCompanyName"
          class="education-print-report__logo"
        >
        <p>{{ originalCompanyName }}</p>
      </div>
      <h1>{{ title }}</h1>
      <p v-if="subtitle">
        {{ subtitle }}
      </p>
    </header>

    <dl
      v-if="period || facts?.length || generatedAt"
      class="education-print-report__facts"
    >
      <div v-if="period">
        <dt>{{ t('educational.printPeriod') }}</dt>
        <dd>{{ period }}</dd>
      </div>
      <div
        v-for="fact in facts ?? []"
        :key="fact.label"
      >
        <dt>{{ fact.label }}</dt>
        <dd>{{ fact.value }}</dd>
      </div>
      <div v-if="generatedAt">
        <dt>{{ t('educational.printGeneratedAt') }}</dt>
        <dd>{{ generatedAt }}</dd>
      </div>
    </dl>

    <table>
      <thead>
        <tr>
          <th
            v-for="column in columns"
            :key="column"
          >
            {{ column }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(row, index) in rows"
          :key="index"
        >
          <td
            v-for="(value, cellIndex) in row"
            :key="cellIndex"
          >
            {{ value === null || value === undefined || value === '' ? '—' : value }}
          </td>
        </tr>
        <tr v-if="!rows.length">
          <td
            :colspan="columns.length || 1"
            class="education-print-report__empty"
          >
            {{ t('common.crmUi.printNoRecords') }}
          </td>
        </tr>
      </tbody>
    </table>

    <footer class="education-print-report__footer">
      <span>{{ originalCompanyName }}</span>
      <span v-if="totalLabel">{{ totalLabel }}</span>
      <span v-if="generatedAt">{{ t('common.crmUi.printGenerated', { date: generatedAt }) }}</span>
    </footer>
  </section>
</template>

<style>
/* Hidden on screen; each print path reveals it through its own body class. */
.education-print-report { display: none; }

@media print {
  body.printing-isolated-document .education-print-report,
  body.printing-education-reports .education-print-report {
    display: block !important;
    margin: 0;
    color: #1f2233;
    background: #fff;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10px;
    line-height: 1.5;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  html.locale-persian-script body.printing-isolated-document .education-print-report,
  html.locale-persian-script body.printing-isolated-document .education-print-report *,
  html.locale-persian-script body.printing-education-reports .education-print-report,
  html.locale-persian-script body.printing-education-reports .education-print-report * {
    font-family: Vazirmatn, Estedad, Tahoma, sans-serif !important;
  }

  body.printing-isolated-document .education-print-report__header,
  body.printing-education-reports .education-print-report__header {
    padding-block-end: 8px;
    border-block-end: 2px solid #4b4f66;
    margin-block-end: 12px;
  }

  body.printing-isolated-document .education-print-report__brand,
  body.printing-education-reports .education-print-report__brand {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  body.printing-isolated-document .education-print-report__logo,
  body.printing-education-reports .education-print-report__logo {
    max-width: 34mm;
    max-height: 14mm;
    object-fit: contain;
  }

  body.printing-isolated-document .education-print-report__brand p,
  body.printing-education-reports .education-print-report__brand p {
    margin: 0;
    color: #4b4f66;
    font-size: 11px;
    font-weight: 700;
  }

  body.printing-isolated-document .education-print-report h1,
  body.printing-education-reports .education-print-report h1 {
    margin: 6px 0 2px;
    color: #2f3349;
    font-size: 17px;
    line-height: 1.25;
  }

  body.printing-isolated-document .education-print-report__header > p,
  body.printing-education-reports .education-print-report__header > p {
    margin: 0;
    color: #6d7185;
    font-size: 10px;
  }

  body.printing-isolated-document .education-print-report__facts,
  body.printing-education-reports .education-print-report__facts {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 18px;
    margin: 0 0 12px;
    padding: 8px 10px;
    border: 1px solid #dfe2eb;
    border-radius: 5px;
    background: #f7f8fb;
  }

  body.printing-isolated-document .education-print-report__facts div,
  body.printing-education-reports .education-print-report__facts div {
    display: flex;
    gap: 5px;
    min-width: 0;
  }

  body.printing-isolated-document .education-print-report__facts dt,
  body.printing-education-reports .education-print-report__facts dt {
    color: #6d7185;
    font-weight: 700;
  }

  body.printing-isolated-document .education-print-report__facts dt::after,
  body.printing-education-reports .education-print-report__facts dt::after {
    content: ':';
  }

  body.printing-isolated-document .education-print-report__facts dd,
  body.printing-education-reports .education-print-report__facts dd {
    margin: 0;
    color: #2f3349;
    font-weight: 600;
    overflow-wrap: anywhere;
  }

  body.printing-isolated-document .education-print-report table,
  body.printing-education-reports .education-print-report table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9.5px;
  }

  body.printing-isolated-document .education-print-report thead,
  body.printing-education-reports .education-print-report thead {
    display: table-header-group;
  }

  body.printing-isolated-document .education-print-report tr,
  body.printing-education-reports .education-print-report tr {
    break-inside: avoid;
  }

  body.printing-isolated-document .education-print-report th,
  body.printing-education-reports .education-print-report th {
    padding: 6px 7px;
    color: #4b4f66;
    background: #eef0f6;
    border: 1px solid #dfe2eb;
    font-size: 8.5px;
    font-weight: 800;
    letter-spacing: .03em;
    text-align: start;
    text-transform: uppercase;
  }

  body.printing-isolated-document .education-print-report td,
  body.printing-education-reports .education-print-report td {
    padding: 5px 7px;
    color: #2f3349;
    border: 1px solid #e6e8f0;
    overflow-wrap: anywhere;
    text-align: start;
    vertical-align: top;
  }

  body.printing-isolated-document .education-print-report tbody tr:nth-child(even) td,
  body.printing-education-reports .education-print-report tbody tr:nth-child(even) td {
    background: #fbfbfd;
  }

  body.printing-isolated-document .education-print-report__empty,
  body.printing-education-reports .education-print-report__empty {
    padding: 16px;
    color: #6d7185;
    text-align: center;
  }

  body.printing-isolated-document .education-print-report__footer,
  body.printing-education-reports .education-print-report__footer {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    margin-block-start: 14px;
    padding-block-start: 8px;
    color: #6d7185;
    border-block-start: 1px solid #dfe2eb;
    font-size: 8.5px;
  }
}
</style>
