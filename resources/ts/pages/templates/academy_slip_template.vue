<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { AcademySlipPayload } from '@/api/academySlip'
import type { ReceiptMoney } from '@/api/receipts'
import { formatLocalizedDate } from '@/utils/localization'
import { documentBrandStyle } from '@/utils/documentBranding'
import { academySlipCopies, printAcademySlip, type AcademySlipPaperSize } from '@/utils/printAcademySlip'

export type { AcademySlipPaperSize } from '@/utils/printAcademySlip'

const twoUpModes: Record<string, { page: string; height: number }> = {
  'A4 2x A5': { page: 'A4', height: 148.3 },
  'A5 2x': { page: 'A5', height: 104.8 },
}

const props = withDefaults(defineProps<{
  slip: AcademySlipPayload
  copies?: number
  paperSize?: AcademySlipPaperSize
}>(), {
  copies: 1,
  paperSize: 'A5',
})

const rootRef = ref<HTMLElement | null>(null)
const { t, locale } = useI18n()

const direction = computed(() => (locale.value === 'en' ? 'ltr' : 'rtl'))
const copies = computed(() => academySlipCopies(props.copies))

const brandStyle = computed(() => documentBrandStyle(
  props.slip.company.primary_color,
  props.slip.company.secondary_color,
))

const formatMoney = (value: number | null | undefined, currency: ReceiptMoney | null) => {
  const decimals = currency?.decimalPlaces ?? 2
  const amount = Number(value ?? 0)

  return `${currency?.symbol ?? ''}${(Number.isFinite(amount) ? amount : 0).toFixed(decimals)} ${currency?.code ?? ''}`.trim()
}

const formatDate = (value: string | null | undefined) => value
  ? formatLocalizedDate(`${value}T00:00:00`, { day: '2-digit', month: 'short', year: 'numeric' })
  : '—'

const paperSizeCss = computed(() => props.paperSize ?? 'A5')
const twoUp = computed(() => twoUpModes[paperSizeCss.value] ?? null)
const isTwoUp = computed(() => twoUp.value !== null)

// The A5 2-up card is too small for the layout, so the whole content block is
// scaled down and its box grown by 1/scale to keep filling the card.
const twoUpContentScale = computed(() => (paperSizeCss.value === 'A5 2x' ? 0.7 : 1))

const contentStyle = computed(() => {
  const scale = twoUpContentScale.value

  if (scale === 1)
    return {}

  return {
    transform: `scale(${scale})`,
    transformOrigin: direction.value === 'rtl' ? 'top right' : 'top left',
  }
})

async function print() {
  if (!rootRef.value)
    return
  await printAcademySlip(rootRef.value, paperSizeCss.value)
}

defineExpose({ print })
</script>

<template>
  <div
    ref="rootRef"
    class="academy-sheet"
    :class="{ 'academy-sheet--two-up': isTwoUp, 'academy-sheet--two-up-compact': paperSizeCss === 'A5 2x' }"
    :style="[brandStyle, twoUp ? { '--academy-card-height': `${twoUp.height}mm` } : undefined]"
    :dir="direction"
  >
    <article
      v-for="copy in copies"
      :key="copy"
      class="academy-card"
    >
      <div
        class="academy-card__content"
        :style="contentStyle"
      >
        <header class="academy-head">
          <img
            v-if="slip.company.logo_url"
            class="academy-head__logo"
            :src="slip.company.logo_url"
            alt=""
          >
          <div class="academy-head__identity">
            <div class="academy-head__name">
              {{ slip.company.name || t('documents.academySlip.fallbackCompany') }}
            </div>
            <div class="academy-head__meta">
              <span v-if="slip.company.address">{{ slip.company.address }}</span>
              <span v-if="slip.company.phone">{{ slip.company.phone }}</span>
              <span v-if="slip.company.email">{{ slip.company.email }}</span>
              <span v-if="slip.company.licence">{{ slip.company.licence }}</span>
            </div>
          </div>
          <div class="academy-head__title">
            <div class="academy-head__eyebrow">
              {{ t('documents.academySlip.title') }}
            </div>
            <div class="academy-head__sub">
              {{ t('documents.academySlip.subtitle') }}
            </div>
            <div class="academy-head__refs">
              <div><span>{{ t('documents.academySlip.reference') }}</span><strong>{{ slip.reference || '—' }}</strong></div>
              <div><span>{{ t('documents.academySlip.date') }}</span><strong>{{ formatDate(slip.date) }}</strong></div>
            </div>
          </div>
        </header>

        <div class="academy-grid">
          <section v-if="slip.applicant" class="academy-block">
            <h4>{{ t('documents.academySlip.applicant') }}</h4>
            <dl>
              <div><dt>{{ t('documents.academySlip.name') }}</dt><dd>{{ slip.applicant.name }}</dd></div>
              <div><dt>{{ t('documents.academySlip.fatherName') }}</dt><dd>{{ slip.applicant.father_name || '—' }}</dd></div>
              <div><dt>{{ t('documents.academySlip.contact') }}</dt><dd>{{ slip.applicant.contact || '—' }}</dd></div>
              <div><dt>{{ t('documents.academySlip.email') }}</dt><dd>{{ slip.applicant.email || '—' }}</dd></div>
            </dl>
          </section>

          <section class="academy-block">
            <h4>{{ t('documents.academySlip.enrolment') }}</h4>
            <dl>
              <div><dt>{{ t('documents.academySlip.program') }}</dt><dd>{{ slip.academy.program || '—' }}</dd></div>
              <div><dt>{{ t('documents.academySlip.time') }}</dt><dd>{{ slip.academy.time || '—' }}</dd></div>
              <div><dt>{{ t('documents.academySlip.duration') }}</dt><dd>{{ slip.academy.duration || '—' }}</dd></div>
              <div><dt>{{ t('documents.academySlip.class') }}</dt><dd>{{ slip.academy.class || '—' }}</dd></div>
              <div><dt>{{ t('documents.academySlip.teachers') }}</dt><dd>{{ slip.academy.teachers.length ? slip.academy.teachers.join(', ') : '—' }}</dd></div>
            </dl>
          </section>
        </div>

        <section class="academy-lines">
          <h4>{{ t('documents.academySlip.payment') }}</h4>
          <table>
            <tbody>
              <tr>
                <th>{{ t('documents.academySlip.totalFee') }}</th>
                <td>{{ formatMoney(slip.totals.total_fee, slip.totals.currency) }}</td>
              </tr>
              <tr>
                <th>{{ t('documents.academySlip.paid') }}</th>
                <td>{{ formatMoney(slip.totals.paid_amount, slip.totals.currency) }}</td>
              </tr>
              <tr class="academy-lines__total">
                <th>{{ t('documents.academySlip.balance') }}</th>
                <td>{{ formatMoney(slip.totals.balance, slip.totals.currency) }}</td>
              </tr>
              <tr v-if="slip.service">
                <th>{{ t('documents.academySlip.paymentStatus') }}</th>
                <td>{{ t(`finance.paymentStatus.${slip.service.payment_status}`) }}</td>
              </tr>
            </tbody>
          </table>
        </section>

        <footer class="academy-foot">
          <div class="academy-foot__note">
            {{ t('documents.academySlip.footerNote') }}
          </div>
          <div class="academy-foot__signature">
            <img
              v-if="slip.signature.url"
              :src="slip.signature.url"
              alt=""
            >
            <div class="academy-foot__line" />
            <div class="academy-foot__name">
              {{ slip.signature.name || slip.company.name || '' }}
            </div>
          </div>
        </footer>
      </div>
    </article>
  </div>
</template>

<style scoped>
.academy-sheet {
  display: flex;
  flex-direction: column;
  gap: 6mm;
  width: 100%;
  color: #1f2937;
  font-size: 11px;
  line-height: 1.45;
}

.academy-card {
  position: relative;
  width: 100%;
  min-height: 120mm;
  overflow: hidden;
  background: #ffffff;
  border: 1px solid #d7dde5;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.1);
  overflow-wrap: anywhere;
}

.academy-card__content {
  display: flex;
  flex-direction: column;
  gap: 3mm;
  height: 100%;
  padding: 8mm;
}

.academy-card__content > * { flex-shrink: 0; }

.academy-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 6mm;
  padding-block-end: 2.5mm;
  border-block-end: 2px solid var(--doc-primary);
}

.academy-head__logo {
  width: 14mm;
  height: 14mm;
  object-fit: contain;
  flex-shrink: 0;
}

.academy-head__identity {
  flex: 1;
  min-inline-size: 0;
}

.academy-head__name {
  font-size: 13pt;
  font-weight: 700;
  line-height: 1.2;
  color: var(--doc-primary);
}

.academy-head__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 1mm 4mm;
  margin-block-start: 1mm;
  color: #52616b;
  font-size: 7.5pt;
}

.academy-head__title {
  text-align: end;
  min-inline-size: 0;
  max-inline-size: 42%;
  flex-shrink: 0;
}

.academy-head__eyebrow {
  font-size: 12pt;
  font-weight: 800;
  color: var(--doc-primary);
  text-transform: uppercase;
}

.academy-head__sub {
  color: #52616b;
  font-size: 7.5pt;
}

.academy-head__refs {
  margin-block-start: 1mm;
  font-size: 8pt;
}

.academy-head__refs div {
  display: flex;
  justify-content: flex-end;
  gap: 2mm;
}

.academy-head__refs span {
  color: #52616b;
}

.academy-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 5mm;
}

.academy-block h4,
.academy-lines h4 {
  margin: 0 0 1.5mm;
  padding-block-end: 1mm;
  border-block-end: 1px solid var(--doc-primary-tint);
  color: var(--doc-primary);
  font-size: 8pt;
  font-weight: 700;
  text-transform: uppercase;
}

.academy-block dl {
  display: grid;
  gap: 1mm;
  margin: 0;
}

.academy-block dl > div {
  display: flex;
  justify-content: space-between;
  gap: 3mm;
}

.academy-block dt {
  color: #52616b;
  font-size: 8pt;
}

.academy-block dd {
  margin: 0;
  font-size: 8pt;
  font-weight: 600;
  text-align: end;
}

.academy-lines table {
  width: 100%;
  border-collapse: collapse;
}

.academy-lines th,
.academy-lines td {
  padding: 1.5mm 2mm;
  border-block-end: 1px solid #e2e8f0;
  text-align: start;
  font-size: 8pt;
}

.academy-lines th {
  color: #52616b;
  font-weight: 600;
  width: 60%;
}

.academy-lines td {
  text-align: end;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.academy-lines__total th,
.academy-lines__total td {
  border-block-start: 1.5px solid var(--doc-secondary);
  background: var(--doc-primary-soft);
  color: var(--doc-primary);
  font-weight: 700;
}

.academy-foot {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 6mm;
  margin-block-start: auto;
  padding-block-start: 2mm;
}

.academy-foot__signature {
  min-inline-size: 45%;
  text-align: center;
}

.academy-foot__signature img {
  max-height: 12mm;
  object-fit: contain;
}

.academy-foot__line {
  border-block-start: 1px solid #9ca3af;
  margin-block-start: 1mm;
}

.academy-foot__name {
  color: #52616b;
  font-size: 8pt;
  margin-block-start: 0.5mm;
}

.academy-foot__note {
  color: #52616b;
  font-size: 7.5pt;
  max-inline-size: 45%;
}

/* Layout uses logical properties, so RTL/LTR mirror without extra rules. */

.academy-sheet--two-up .academy-card {
  height: var(--academy-card-height, 148.3mm);
  min-height: 0;
  font-size: 10px;
}

.academy-sheet--two-up .academy-card__content {
  gap: 2mm;
  padding: 5mm;
}

.academy-sheet--two-up-compact .academy-card__content {
  width: 142.857%;
  height: 142.857%;
}

.academy-sheet--two-up .academy-card:nth-child(odd):not(:last-child) {
  border-block-end: 1px dashed #444;
}
</style>

<style>
.academy-slip-print-host { display: none; }
.academy-slip-print-host[data-measuring] { display: block; position: fixed; inset-inline-start: -10000px; inset-block-start: 0; }
.academy-slip-print-host .academy-slip-print-page { gap: 0; margin: 0; padding: 0; box-sizing: border-box; }
.academy-slip-print-host .academy-card { min-height: 0; border: 0; box-shadow: none; box-sizing: border-box; }
.academy-slip-print-host .academy-card:not(:last-child) { border-block-end: 1px dashed #444; }
.academy-slip-print-host .academy-card__content { box-sizing: border-box; }

@media print {
  body.academy-slip-print-mode { margin: 0 !important; padding: 0 !important; min-height: 0 !important; height: auto !important; display: block !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body.academy-slip-print-mode > :not(.academy-slip-print-host) { display: none !important; }
  body.academy-slip-print-mode > .academy-slip-print-host { display: block !important; position: static !important; margin: 0 !important; padding: 0 !important; min-height: 0 !important; }
  body.academy-slip-print-mode .academy-slip-print-page { break-inside: avoid; page-break-inside: avoid; }
  body.academy-slip-print-mode .academy-slip-print-page:not(:last-child) { break-after: page; page-break-after: always; }
}
</style>
