<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useBranding } from '@/composables/useBranding'
import { formatLocalizedDate } from '@/utils/localization'

const { t } = useI18n()
const { logoUrl, originalCompanyName } = useBranding()

defineProps<{
  title: string
  subtitle?: string
  columns: string[]
  rows: Array<Array<string | number | null | undefined>>
  summaryLabel?: string
  summaryValue?: string | number
  total?: number
}>()

const generatedAt = ref('')
const setGeneratedAt = () => { generatedAt.value = formatLocalizedDate(new Date(), 'long') }

onMounted(() => document.addEventListener('app:before-print', setGeneratedAt))
onBeforeUnmount(() => document.removeEventListener('app:before-print', setGeneratedAt))
</script>

<template>
  <section class="list-print-report">
    <header>
      <img v-if="logoUrl" :src="logoUrl" :alt="originalCompanyName" class="list-print-logo">
      <p class="list-print-company">{{ originalCompanyName }}</p>
      <h1>{{ title }}</h1>
      <p v-if="subtitle">
        {{ subtitle }}
      </p>
    </header>

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
            {{ value ?? '—' }}
          </td>
        </tr>
        <tr v-if="!rows.length">
          <td
            :colspan="columns.length"
            class="empty"
          >
            {{ t('common.crmUi.printNoRecords') }}
          </td>
        </tr>
      </tbody>
    </table>

    <table class="list-print-report__summary" aria-label="Report total">
      <tbody>
        <tr>
          <th>{{ summaryLabel ?? t('educational.reportColumns.total') }}</th>
          <td>{{ summaryValue ?? total ?? rows.length }}</td>
        </tr>
      </tbody>
    </table>

    <footer>{{ originalCompanyName }} · {{ t('common.crmUi.printGenerated', { date: generatedAt }) }} · {{ rows.length }}</footer>
  </section>
</template>

<style>
.list-print-report { display: none; }

@media print {
  body.printing-isolated-document .list-print-report,
  body.printing-isolated-document .list-print-report * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  body.printing-isolated-document .list-print-logo { max-width: 45mm; max-height: 18mm; object-fit: contain; float: inline-end; }
  body.printing-isolated-document .list-print-company { font-weight: 700; }
  body.printing-isolated-document .list-print-report thead { display: table-header-group; }
  body.printing-isolated-document .list-print-report tr { break-inside: avoid; }
  body.printing-isolated-document .list-print-report { display: block !important; color: #333; font-family: Arial, sans-serif; margin: 8mm; }
  html.locale-persian-script body.printing-isolated-document .list-print-report,
  html.locale-persian-script body.printing-isolated-document .list-print-report * { font-family: Vazirmatn, Estedad, Tahoma, sans-serif !important; }
  html.locale-persian-script body.printing-isolated-document .list-print-report { direction: rtl; text-align: start; }
  body.printing-isolated-document .list-print-report header { border-bottom: 2px solid rgb(var(--v-theme-primary)); margin-bottom: 18px; padding-bottom: 10px; }
  body.printing-isolated-document .list-print-report h1 { color: rgb(var(--v-theme-primary)); font-size: 20px; line-height: 1.2; margin: 0 0 7px; }
  body.printing-isolated-document .list-print-report header p { color: #777; font-size: 11px; margin: 0; }
  body.printing-isolated-document .list-print-report table { border-collapse: collapse; font-size: 11px; width: 100%; }
  body.printing-isolated-document .list-print-report th { color: rgb(var(--v-theme-primary)); font-size: 10px; font-weight: 700; text-align: start; }
  body.printing-isolated-document .list-print-report th, body.printing-isolated-document .list-print-report td { border-bottom: 1px solid #d9d9d9; padding: 9px 7px; vertical-align: top; }
  body.printing-isolated-document .list-print-report td.empty { color: #999; text-align: center; }
  body.printing-isolated-document .list-print-report__summary { width: auto; min-width: 38mm; margin-block-start: 12px; margin-inline-start: auto; border-collapse: separate; border-spacing: 0; background: rgba(var(--v-theme-primary), .12); color: rgb(var(--v-theme-primary)); }
  body.printing-isolated-document .list-print-report__summary th,
  body.printing-isolated-document .list-print-report__summary td { border: 1px solid rgba(var(--v-theme-primary), .34); color: inherit; font-weight: 800; padding: 7px 10px; }
  body.printing-isolated-document .list-print-report__summary th { background: rgba(var(--v-theme-primary), .12); text-align: start; }
  body.printing-isolated-document .list-print-report__summary td { min-width: 13mm; text-align: center; }
  body.printing-isolated-document .list-print-report footer { color: #666; font-size: 10px; margin-top: 22px; text-align: end; }
}
</style>
