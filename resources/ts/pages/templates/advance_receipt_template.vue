<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { printDocument } from '@/utils/printDocument'
import { formatAmountInWords } from '@/utils/amountInWords'
import { documentBrandStyle } from '@/utils/documentBranding'
import { formatLocalizedDate } from '@/utils/localization'

export interface AdvanceReceiptMoney {
  code?: string | null
  symbol?: string | null
  decimalPlaces?: number | null
}

export interface AdvanceReceiptData {
  advanceNumber: string | null
  date: string
  amount: number
  currency: AdvanceReceiptMoney | null
  remarks?: string | null
  employee: {
    name: string
    idNumber?: string | null
    position?: string | null
    department?: string | null
  }
  company: {
    name: string
    brandName?: string
    logo?: string
    signature?: string
    addressLines?: string[]
    email?: string
    phone?: string
    licence?: string
    primaryColor?: string
    secondaryColor?: string
  }
}

const props = defineProps<{ receipt: AdvanceReceiptData }>()

const { locale } = useI18n()
const rootRef = ref<HTMLElement | null>(null)

const brandStyle = computed(() => documentBrandStyle(
  props.receipt.company.primaryColor,
  props.receipt.company.secondaryColor,
))

const amountInWords = computed(() => formatAmountInWords(
  props.receipt.amount,
  locale.value,
  props.receipt.currency,
))

/** One sheet carries the employee's copy and the company's copy. */
const copies = [
  { key: 'employee', label: 'documents.advanceReceipt.employeeCopy' },
  { key: 'company', label: 'documents.advanceReceipt.companyCopy' },
] as const

const money = computed(() => {
  const decimals = props.receipt.currency?.decimalPlaces ?? 2
  const amount = Number(props.receipt.amount ?? 0)

  return `${props.receipt.currency?.symbol ?? ''}${amount.toFixed(decimals)} ${props.receipt.currency?.code ?? ''}`.trim()
})

const formattedDate = computed(() => props.receipt.date
  ? formatLocalizedDate(`${props.receipt.date}T00:00:00`, { day: '2-digit', month: 'short', year: 'numeric' })
  : '—')

const print = async () => {
  if (!rootRef.value)
    return
  await printDocument({
    bodyClass: 'printing-advance-receipt',
    pageStyle: 'size: A4 portrait; margin: 10mm;',
    element: rootRef.value,
  })
}

defineExpose({ print })
</script>

<template>
  <div
    ref="rootRef"
    class="advance-sheet"
    :dir="$i18n.locale === 'en' ? 'ltr' : 'rtl'"
    :class="{ 'persian-document': ['fa-AF', 'ps-AF'].includes($i18n.locale) }"
    :style="brandStyle"
  >
    <article
      v-for="copy in copies"
      :key="copy.key"
      class="advance-card"
    >
      <header class="advance-head">
        <div class="advance-head__brand">
          <img
            v-if="receipt.company.logo"
            class="advance-head__logo"
            :src="receipt.company.logo"
            alt=""
          >
          <div>
            <div class="advance-head__company">
              {{ receipt.company.name }}
            </div>
            <div
              v-if="receipt.company.brandName"
              class="advance-head__badge"
            >
              {{ receipt.company.brandName }}
            </div>
            <div
              v-if="receipt.company.addressLines?.length"
              class="advance-head__meta"
            >
              {{ receipt.company.addressLines.join(' · ') }}
            </div>
            <div class="advance-head__meta">
              <span v-if="receipt.company.phone">{{ receipt.company.phone }}</span>
              <span v-if="receipt.company.email"> · {{ receipt.company.email }}</span>
              <span v-if="receipt.company.licence"> · {{ $t('documents.receipt.license') }}: {{ receipt.company.licence }}</span>
            </div>
          </div>
        </div>

        <div class="advance-head__title">
          <div class="advance-head__eyebrow">
            {{ $t('documents.advanceReceipt.title') }}
          </div>
          <div class="advance-head__copy">
            {{ $t(copy.label) }}
          </div>
          <div class="advance-head__serial">
            {{ $t('documents.advanceReceipt.receiptNo') }}
            <strong>{{ receipt.advanceNumber || '—' }}</strong>
          </div>
          <div class="advance-head__serial">
            {{ $t('documents.advanceReceipt.date') }}
            <strong>{{ formattedDate }}</strong>
          </div>
        </div>
      </header>

      <section class="advance-body">
        <dl class="advance-grid">
          <div class="advance-grid__wide">
            <dt>{{ $t('documents.advanceReceipt.employee') }}</dt>
            <dd>{{ receipt.employee.name }}</dd>
          </div>
          <div>
            <dt>{{ $t('documents.advanceReceipt.employeeId') }}</dt>
            <dd>{{ receipt.employee.idNumber || '—' }}</dd>
          </div>
          <div>
            <dt>{{ $t('documents.advanceReceipt.position') }}</dt>
            <dd>{{ receipt.employee.position || '—' }}</dd>
          </div>
          <div>
            <dt>{{ $t('documents.advanceReceipt.department') }}</dt>
            <dd>{{ receipt.employee.department || '—' }}</dd>
          </div>
          <div class="advance-grid__wide">
            <dt>{{ $t('documents.advanceReceipt.reason') }}</dt>
            <dd>{{ receipt.remarks || '—' }}</dd>
          </div>
        </dl>

        <div class="advance-amount">
          <div class="advance-amount__main">
            <span class="advance-amount__label">{{ $t('documents.advanceReceipt.amount') }}</span>
            <strong class="advance-amount__value">{{ money }}</strong>
          </div>
          <div class="advance-amount__words">
            <span class="advance-amount__label">{{ $t('documents.advanceReceipt.amountInWords') }}</span>
            <span>{{ amountInWords }}</span>
          </div>
        </div>

        <p class="advance-note">
          {{ $t('documents.advanceReceipt.note') }}
        </p>
      </section>

      <footer class="advance-signatures">
        <div class="advance-signature">
          <div class="advance-signature__line" />
          <div class="advance-signature__receiver">
            <span class="advance-signature__label">{{ $t('documents.advanceReceipt.receivedBy') }}</span>
            <span class="advance-signature__name">{{ receipt.employee.name }}</span>
          </div>
        </div>
        <div class="advance-signature">
          <img
            v-if="receipt.company.signature"
            :src="receipt.company.signature"
            alt=""
            class="advance-signature__image"
          >
          <div class="advance-signature__line" />
          <span>{{ $t('documents.advanceReceipt.companySignature') }}</span>
        </div>
      </footer>
    </article>
  </div>
</template>

<style scoped>
.advance-sheet {
  --advance-ink: #16212f;
  --advance-muted: #5b6b7c;
  --advance-line: #d6dee7;
  --advance-accent: var(--doc-primary);

  display: flex;
  flex-direction: column;
  gap: 5mm;
  width: 100%;
  color: var(--advance-ink);
  font-size: 8pt;
  line-height: 1.35;
}

.advance-card {
  display: flex;
  flex-direction: column;
  gap: 2.5mm;
  box-sizing: border-box;
  min-block-size: 125mm;
  padding: 6mm;
  border: 1px solid var(--advance-line);
  border-radius: 1.5mm;
  background: #fff;
  overflow: hidden;
}

.advance-card + .advance-card {
  border-block-start-style: dashed;
}

.persian-document {
  font-family: Vazirmatn, 'Public Sans', sans-serif;
}

.advance-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 4mm;
  padding-block-end: 2mm;
  border-block-end: 2px solid var(--advance-accent);
}

.advance-head__brand {
  display: flex;
  align-items: flex-start;
  gap: 2.5mm;
  min-inline-size: 0;
}

.advance-head__logo {
  inline-size: 11mm;
  block-size: 11mm;
  object-fit: contain;
}

.advance-head__company {
  font-size: 10.5pt;
  font-weight: 800;
  line-height: 1.15;
}

.advance-head__badge {
  display: inline-block;
  margin-block: 0.4mm;
  padding: 0.2mm 1.4mm;
  border-radius: 2px;
  background: var(--doc-primary-soft);
  color: var(--advance-accent);
  font-size: 6pt;
  font-weight: 700;
  text-transform: uppercase;
}

.advance-head__meta {
  color: var(--advance-muted);
  font-size: 6pt;
}

.advance-head__title {
  flex-shrink: 0;
  text-align: end;
}

.advance-head__eyebrow {
  color: var(--advance-accent);
  font-size: 9pt;
  font-weight: 800;
  letter-spacing: 0.04em;
}

.advance-head__copy {
  margin-block: 0.4mm 1mm;
  color: var(--advance-muted);
  font-size: 6.4pt;
  font-weight: 700;
  text-transform: uppercase;
}

.advance-head__serial {
  display: flex;
  justify-content: flex-end;
  gap: 1.5mm;
  font-size: 6.4pt;
}

.advance-body {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 2mm;
  min-block-size: 0;
}

.advance-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1.2mm 3mm;
  margin: 0;
}

.advance-grid > div {
  min-inline-size: 0;
}

.advance-grid__wide {
  grid-column: span 3;
}

.advance-grid dt {
  color: var(--advance-muted);
  font-size: 5.8pt;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.advance-grid dd {
  margin: 0;
  font-size: 7.4pt;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.advance-amount {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr);
  gap: 3mm;
  padding: 2mm 3mm;
  border: 1px solid var(--doc-primary-tint);
  border-inline-start: 3px solid var(--advance-accent);
  border-radius: 1.5mm;
  background: var(--doc-primary-soft);
}

.advance-amount__main,
.advance-amount__words {
  display: flex;
  flex-direction: column;
  min-inline-size: 0;
}

.advance-amount__label {
  color: var(--advance-accent);
  font-size: 5.6pt;
  font-weight: 800;
  text-transform: uppercase;
}

.advance-amount__value {
  color: var(--advance-accent);
  font-size: 12pt;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

.advance-amount__words span:last-child {
  font-size: 6.8pt;
  font-style: italic;
}

.advance-note {
  margin: 0;
  color: var(--advance-muted);
  font-size: 6.2pt;
}

.advance-signatures {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8mm;
  margin-block-start: auto;
  padding-block-start: 2mm;
}

.advance-signature {
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  min-block-size: 12mm;
  text-align: center;
}

.advance-signature__line {
  margin-block-end: 1mm;
  border-block-start: 1px solid #7b8a99;
}

.advance-signature__image {
  display: block;
  max-inline-size: 28mm;
  max-block-size: 9mm;
  margin: 0 auto 1mm;
  object-fit: contain;
}

.advance-signature span {
  color: var(--advance-muted);
  font-size: 6pt;
  font-weight: 700;
  text-transform: uppercase;
}

.advance-signature__receiver {
  display: flex;
  align-items: baseline;
  justify-content: center;
  gap: 1.2mm;
}

.advance-signature__name {
  min-inline-size: 0;
  overflow-wrap: anywhere;
  color: var(--advance-ink) !important;
  font-size: 7pt !important;
  font-weight: 800 !important;
  text-transform: none !important;
}
</style>

<style>
/* The print host is a body-level clone of the sheet; keep the two copies on
   one A5 page and never let a card spill onto a second sheet. */
@media print {
  body.printing-advance-receipt .advance-sheet {
    gap: 2mm !important;
  }

  body.printing-advance-receipt .advance-card {
    block-size: 99mm !important;
    min-block-size: 99mm !important;
    box-shadow: none !important;
    break-inside: avoid;
    page-break-inside: avoid;
  }
}
</style>
