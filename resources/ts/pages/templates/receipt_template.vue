<script setup lang="ts">
import { computed, ref } from 'vue'
import type { ReceiptLine, ReceiptMoney, ReceiptPayload } from '@/api/receipts'

export type ReceiptPaperSize = 'A3 landscape' | 'A4' | 'A4 landscape' | 'A5' | 'letter' | 'A4 2x A5' | 'A5 2x'

const twoUpModes: Record<string, { page: string; height: number }> = {
  'A4 2x A5': { page: 'A4', height: 148.3 },
  'A5 2x': { page: 'A5', height: 104.8 },
}

const props = withDefaults(defineProps<{
  receipt: ReceiptPayload
  copies?: number
  paperSize?: ReceiptPaperSize
}>(), {
  copies: 2,
  paperSize: 'A3 landscape',
})

const rootRef = ref<HTMLElement | null>(null)
const { t, locale } = useI18n()

const direction = computed(() => (locale.value === 'en' ? 'ltr' : 'rtl'))
const copies = computed(() => Math.max(1, Math.min(4, props.copies || 1)))

const letterhead = computed(() => props.receipt.letterhead)
const hasLetterhead = computed(() => Boolean(letterhead.value?.url))

const safeStyle = computed(() => ({
  insetInlineStart: `${letterhead.value?.safe?.x ?? 0}%`,
  insetBlockStart: `${letterhead.value?.safe?.y ?? 0}%`,
  width: `${letterhead.value?.safe?.width ?? 100}%`,
  height: `${letterhead.value?.safe?.height ?? 100}%`,
}))

const formatMoney = (value: number | null | undefined, currency: ReceiptMoney | null) => {
  const decimals = currency?.decimalPlaces ?? 2
  const amount = Number(value ?? 0)

  return `${currency?.symbol ?? ''}${(Number.isFinite(amount) ? amount : 0).toFixed(decimals)} ${currency?.code ?? ''}`.trim()
}

const formatAmount = (value: number | null | undefined, currency: ReceiptMoney | null) => formatMoney(value, currency ?? props.receipt.totals.currency)

const formatDate = (value: string | null | undefined) => value
  ? formatLocalizedDate(`${value}T00:00:00`, { day: '2-digit', month: 'short', year: 'numeric' })
  : '—'

const lines = computed<ReceiptLine[]>(() => [...(props.receipt.services ?? []), ...(props.receipt.packages ?? [])])

const paperSizeCss = computed(() => props.paperSize ?? 'A3 landscape')
const twoUp = computed(() => twoUpModes[paperSizeCss.value] ?? null)
const isTwoUp = computed(() => twoUp.value !== null)

/** In 2-up printing the first card of each pair is the customer copy and omits company-internal details. */
const isCustomerCopy = (copy: number) => isTwoUp.value && copy % 2 === 1

// The A5 2-up card is too small for the layout, so the whole content block is
// scaled down and its box grown by 1/scale to keep filling the card.
const twoUpContentScale = computed(() => (paperSizeCss.value === 'A5 2x' ? 0.7 : 1))

const contentStyle = computed(() => {
  const style: Record<string, string> = hasLetterhead.value ? { ...safeStyle.value } : {}
  const scale = twoUpContentScale.value

  if (scale !== 1) {
    if (hasLetterhead.value) {
      style.width = `${parseFloat(style.width || '100') / scale}%`
      style.height = `${parseFloat(style.height || '100') / scale}%`
    }
    style.transform = `scale(${scale})`
    style.transformOrigin = direction.value === 'rtl' ? 'top right' : 'top left'
  }

  return style
})

function print() {
  if (!rootRef.value)
    return

  const popup = window.open('', '_blank', 'width=1200,height=900')
  if (!popup) {
    alert(t('documents.receipt.pleaseAllowPopups'))

    return
  }

  const styles = Array.from(document.querySelectorAll('style, link[rel="stylesheet"]'))
    .map(node => node.outerHTML)
    .join('\n')

  const up = twoUp.value

  const breakRules = up
    ? [
        `.receipt-sheet .receipt-card { height: ${up.height}mm; break-after: auto; page-break-after: auto; }`,
        '.receipt-sheet .receipt-card:nth-child(even):not(:last-child) { break-after: page; page-break-after: always; }',
        '.receipt-sheet .receipt-card:nth-child(odd):not(:last-child) { border-block-end: 1px dashed #444 !important; }',
      ]
    : [
        '.receipt-card:not(:last-child) { break-after: page; page-break-after: always; }',
      ]

  const pageStyle = [
    `@page { size: ${up ? up.page : paperSizeCss.value}; margin: 0; }`,
    'body { margin: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }',
    '.receipt-card { box-shadow: none !important; }',
    ...breakRules,
  ].join('\n')

  popup.document.write(`<!doctype html><html dir="${direction.value}"><head><title>${props.receipt.serial_number ?? 'receipt'}</title>${styles}<style>@media print { ${pageStyle} }</style></head><body>${rootRef.value.outerHTML}<script>onload=()=>{print();close()}<\/script></body></html>`)
  popup.document.close()
}

defineExpose({ print })
</script>

<template>
  <div
    ref="rootRef"
    class="receipt-sheet"
    :class="{ 'receipt-sheet--two-up': isTwoUp, 'receipt-sheet--two-up-compact': paperSizeCss === 'A5 2x' }"
    :style="twoUp ? { '--receipt-card-height': `${twoUp.height}mm` } : undefined"
    :dir="direction"
  >
    <article
      v-for="copy in copies"
      :key="copy"
      class="receipt-card"
    >
      <img
        v-if="hasLetterhead"
        class="receipt-card__art"
        :src="letterhead.url ?? undefined"
        alt=""
      >
      <div
        class="receipt-card__content"
        :class="{ 'receipt-card__content--safe': hasLetterhead }"
        :style="contentStyle"
      >
        <header class="receipt-head">
          <div class="receipt-head__company">
            <img
              v-if="receipt.company.logo_url"
              class="receipt-head__logo"
              :src="receipt.company.logo_url"
              alt=""
            >
            <div>
              <div class="receipt-head__name">
                {{ receipt.company.name || receipt.company.short_name || '—' }}
              </div>
              <div class="receipt-head__meta">
                <span v-if="receipt.company.address">{{ receipt.company.address }}</span>
                <span v-if="receipt.company.phone">{{ receipt.company.phone }}</span>
                <span v-if="receipt.company.email">{{ receipt.company.email }}</span>
                <span v-if="receipt.company.website">{{ receipt.company.website }}</span>
                <span v-if="receipt.company.licence">{{ t('documents.receipt.license') }}: {{ receipt.company.licence }}</span>
              </div>
            </div>
          </div>
          <div class="receipt-head__title">
            <div class="receipt-head__eyebrow">
              {{ t('documents.receipt.title') }}
            </div>
            <div class="receipt-head__serial">
              {{ receipt.serial_number || '—' }}
            </div>
            <div class="receipt-head__date">
              <span>{{ t('documents.receipt.paymentDate') }}</span>
              <strong>{{ formatDate(receipt.date) }}</strong>
            </div>
          </div>
        </header>

        <div
          class="receipt-grid"
          :class="{ 'receipt-grid--single': isCustomerCopy(copy) }"
        >
          <section class="receipt-block">
            <h4>{{ t('documents.receipt.receivedFrom') }}</h4>
            <dl>
              <div><dt>{{ t('common.fields.name') }}</dt><dd>{{ receipt.applicant?.name || '—' }}</dd></div>
              <div><dt>{{ t('documents.receipt.applicantId') }}</dt><dd>#{{ receipt.applicant?.id ?? '—' }}</dd></div>
              <div v-if="receipt.applicant?.father_name"><dt>{{ t('documents.receipt.fatherName') }}</dt><dd>{{ receipt.applicant.father_name }}</dd></div>
              <div><dt>{{ t('documents.receipt.phone') }}</dt><dd>{{ receipt.applicant?.contact || '—' }}</dd></div>
              <div v-if="receipt.applicant?.email"><dt>{{ t('documents.receipt.email') }}</dt><dd>{{ receipt.applicant.email }}</dd></div>
              <div v-if="receipt.applicant?.country"><dt>{{ t('documents.receipt.country') }}</dt><dd>{{ receipt.applicant.country }}</dd></div>
              <div v-if="receipt.applicant?.education_level"><dt>{{ t('documents.receipt.educationLevel') }}</dt><dd>{{ receipt.applicant.education_level }}</dd></div>
            </dl>
          </section>

          <section
            v-if="!isCustomerCopy(copy)"
            class="receipt-block"
          >
            <h4>{{ t('documents.receipt.paymentDetails') }}</h4>
            <dl>
              <div><dt>{{ t('documents.receipt.incomeName') }}</dt><dd>{{ receipt.income.name || '—' }}</dd></div>
              <div><dt>{{ t('documents.receipt.incomeId') }}</dt><dd>#{{ receipt.income.id }}</dd></div>
              <div><dt>{{ t('common.fields.type') }}</dt><dd>{{ translateBackendValue(receipt.income.type) }}</dd></div>
              <div><dt>{{ t('common.fields.amount') }}</dt><dd>{{ formatAmount(receipt.income.line_total, receipt.income.currency) }}</dd></div>
              <div><dt>{{ t('documents.receipt.invoice') }}</dt><dd>{{ receipt.invoice?.invoice_number || t('documents.receipt.invoiceOptional') }}</dd></div>
            </dl>
          </section>
        </div>

        <section class="receipt-lines">
          <h4>{{ t('documents.receipt.servicesProvided') }}</h4>
          <table>
            <thead>
              <tr>
                <th>{{ t('documents.receipt.item') }}</th>
                <th class="num">
                  {{ t('documents.receipt.fee') }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="line in lines"
                :key="`${line.kind ?? 'service'}-${line.id}`"
              >
                <td>{{ line.name }}</td>
                <td class="num">
                  {{ formatMoney(line.converted_fee, receipt.totals.currency) }}
                </td>
              </tr>
              <tr v-if="!lines.length">
                <td colspan="2" class="muted">
                  {{ t('documents.receipt.noServices') }}
                </td>
              </tr>
            </tbody>
          </table>
        </section>

        <section class="receipt-totals">
          <div><span>{{ t('documents.receipt.totalFee') }}</span><strong>{{ formatAmount(receipt.totals.total_fee, receipt.totals.currency) }}</strong></div>
          <div><span>{{ t('documents.receipt.paidToDate') }}</span><strong>{{ formatAmount(receipt.totals.paid_amount, receipt.totals.currency) }}</strong></div>
          <div><span>{{ t('documents.receipt.balance') }}</span><strong>{{ formatAmount(receipt.totals.balance, receipt.totals.currency) }}</strong></div>
          <div class="receipt-totals__received">
            <span>{{ t('documents.receipt.amountReceived') }}</span>
            <strong>{{ formatAmount(receipt.income.line_total, receipt.income.currency) }}</strong>
          </div>
        </section>

        <footer class="receipt-foot">
          <div class="receipt-foot__note">
            <p>{{ t('documents.receipt.thankYou') }}</p>
            <p v-if="receipt.recorded_by && !isCustomerCopy(copy)">
              {{ t('documents.receipt.recordedBy') }}: {{ receipt.recorded_by.name }}
            </p>
          </div>
          <div class="receipt-foot__stamps">
            <div class="receipt-stamp">
              <span>{{ t('documents.receipt.receivedBy') }}</span>
            </div>
            <div class="receipt-stamp">
              <img
                v-if="receipt.signature.url"
                :src="receipt.signature.url"
                alt=""
              >
              <span>{{ t('documents.receipt.authorizedSignature') }}</span>
            </div>
          </div>
        </footer>
      </div>
    </article>
  </div>
</template>

<style scoped>
.receipt-sheet {
  display: flex;
  flex-direction: column;
  gap: 6mm;
  width: 100%;
  color: #1f2933;
  font-size: 9pt;
  line-height: 1.4;
}

.receipt-card {
  position: relative;
  width: 100%;
  min-height: 120mm;
  overflow: hidden;
  background: #ffffff;
  border: 1px solid #d7dde5;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.1);
}

.receipt-card__art {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.receipt-card__content {
  display: flex;
  flex-direction: column;
  gap: 3mm;
  height: 100%;
  padding: 8mm;
}

.receipt-card__content--safe {
  position: absolute;
  padding: 3mm;
  overflow: hidden;
}

.receipt-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 6mm;
  padding-block-end: 2.5mm;
  border-block-end: 2px solid #16a1d7;
}

.receipt-head__company {
  display: flex;
  align-items: flex-start;
  gap: 3mm;
  min-inline-size: 0;
}

.receipt-head__logo {
  width: 14mm;
  height: 14mm;
  object-fit: contain;
}

.receipt-head__name {
  font-size: 12pt;
  font-weight: 700;
  line-height: 1.2;
}

.receipt-head__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 1mm 4mm;
  margin-block-start: 1mm;
  color: #52616b;
  font-size: 7.5pt;
}

.receipt-head__title {
  text-align: end;
  white-space: nowrap;
}

.receipt-head__eyebrow {
  color: #16a1d7;
  font-size: 12pt;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.receipt-head__serial {
  font-size: 10pt;
  font-weight: 700;
}

.receipt-head__date {
  display: flex;
  gap: 2mm;
  margin-block-start: 1mm;
  color: #52616b;
  font-size: 8pt;
}

.receipt-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 5mm;
}

.receipt-grid--single {
  grid-template-columns: 1fr;
}

.receipt-block h4,
.receipt-lines h4 {
  margin: 0 0 1.5mm;
  padding-block-end: 1mm;
  border-block-end: 1px solid #e2e8f0;
  color: #16a1d7;
  font-size: 8pt;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.receipt-block dl {
  display: grid;
  gap: 1mm;
  margin: 0;
}

.receipt-block dl > div {
  display: flex;
  justify-content: space-between;
  gap: 3mm;
}

.receipt-block dt {
  color: #52616b;
  font-size: 8pt;
}

.receipt-block dd {
  margin: 0;
  font-size: 8pt;
  font-weight: 600;
  text-align: end;
}

.receipt-lines table {
  width: 100%;
  border-collapse: collapse;
}

.receipt-lines th,
.receipt-lines td {
  padding: 1.5mm 2mm;
  border-block-end: 1px solid #e2e8f0;
  text-align: start;
  font-size: 8pt;
}

.receipt-lines th.num,
.receipt-lines td.num {
  text-align: end;
}

.receipt-lines .muted {
  color: #8895a1;
  text-align: center;
}

.receipt-totals {
  display: grid;
  grid-template-columns: repeat(3, 1fr) 1.3fr;
  gap: 2mm;
  margin-block-start: auto;
}

.receipt-totals > div {
  display: flex;
  flex-direction: column;
  gap: 0.5mm;
  padding: 2mm 3mm;
  border: 1px solid #e2e8f0;
  border-radius: 1.5mm;
}

.receipt-totals span {
  color: #52616b;
  font-size: 7pt;
}

.receipt-totals strong {
  font-size: 9.5pt;
  font-variant-numeric: tabular-nums;
}

.receipt-totals__received {
  border-color: #16a1d7 !important;
  background: #f1fbff;
}

.receipt-totals__received strong {
  color: #0f6f92;
}

.receipt-foot {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 6mm;
  padding-block-start: 2mm;
}

.receipt-foot__note {
  color: #52616b;
  font-size: 7.5pt;
}

.receipt-foot__note p {
  margin: 0.5mm 0;
}

.receipt-foot__stamps {
  display: flex;
  gap: 6mm;
}

.receipt-stamp {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  width: 40mm;
  min-height: 18mm;
  padding-block-start: 2mm;
  border-block-end: 1px solid #52616b;
}

.receipt-stamp img {
  max-width: 32mm;
  max-height: 12mm;
  object-fit: contain;
  margin-block-end: 1mm;
}

.receipt-stamp span {
  color: #52616b;
  font-size: 7pt;
  font-weight: 700;
  text-align: center;
}

/* Layout uses logical properties, so RTL/LTR mirror without extra rules. */

.receipt-sheet--two-up .receipt-card {
  height: var(--receipt-card-height, 148.3mm);
  font-size: 8pt;
}

.receipt-sheet--two-up .receipt-card__content {
  gap: 2mm;
  padding: 5mm;
}

.receipt-sheet--two-up-compact .receipt-card__content:not(.receipt-card__content--safe) {
  width: 142.857%;
  height: 142.857%;
}

.receipt-sheet--two-up .receipt-card:nth-child(odd):not(:last-child) {
  border-block-end: 1px dashed #444;
}

@media print {
  .receipt-sheet {
    gap: 0;
  }

  .receipt-card {
    min-height: 0;
    height: 100%;
    border: 0;
    box-shadow: none;
  }

  .receipt-card__content {
    height: 100%;
  }

  .receipt-sheet--two-up .receipt-card {
    height: var(--receipt-card-height, 148.3mm);
  }
}
</style>
