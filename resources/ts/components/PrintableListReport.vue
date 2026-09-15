<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { formatLocalizedDate } from '@/utils/localization'

const { t } = useI18n()

defineProps<{
  title: string
  subtitle?: string
  columns: string[]
  rows: Array<Array<string | number | null | undefined>>
}>()

const generatedAt = ref('')
const setGeneratedAt = () => { generatedAt.value = formatLocalizedDate(new Date(), 'long') }

onMounted(() => document.addEventListener('app:before-print', setGeneratedAt))
onBeforeUnmount(() => document.removeEventListener('app:before-print', setGeneratedAt))
</script>

<template>
  <section class="list-print-report">
    <header>
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
            {{ value || '—' }}
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

    <footer>{{ t('common.crmUi.printGenerated', { date: generatedAt }) }}</footer>
  </section>
</template>

<style>
.list-print-report { display: none; }

@media print {
  .list-print-report { display: block !important; color: #333; font-family: Arial, sans-serif; margin: 8mm; }
  html.locale-persian-script .list-print-report,
  html.locale-persian-script .list-print-report * { font-family: Vazirmatn, Estedad, Tahoma, sans-serif !important; }
  html.locale-persian-script .list-print-report { direction: rtl; text-align: start; }
  .list-print-report header { border-bottom: 3px solid #7367f0; margin-bottom: 18px; padding-bottom: 10px; }
  .list-print-report h1 { color: #666; font-size: 20px; line-height: 1.2; margin: 0 0 7px; }
  .list-print-report header p { color: #777; font-size: 11px; margin: 0; }
  .list-print-report table { border-collapse: collapse; font-size: 11px; width: 100%; }
  .list-print-report th { color: #777; font-size: 10px; font-weight: 600; text-align: start; }
  .list-print-report th, .list-print-report td { border-bottom: 1px solid #d9d9d9; padding: 9px 7px; vertical-align: top; }
  .list-print-report td.empty { color: #999; text-align: center; }
  .list-print-report footer { color: #666; font-size: 10px; margin-top: 22px; text-align: end; }
}
</style>
