<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { CommissionSlipPayload } from '@/api/commission'
import type { ReceiptMoney } from '@/api/receipts'
import { formatLocalizedDate } from '@/utils/localization'
import { documentBrandStyle } from '@/utils/documentBranding'
import {
  commissionSlipCopies,
  printCommissionSlip,
  type CommissionSlipPaperSize,
} from '@/utils/printCommissionSlip'

export type { CommissionSlipPaperSize } from '@/utils/printCommissionSlip'

const twoUpModes: Record<string, { page: string; height: number }> = {
  'A4 2x A5': { page: 'A4', height: 148.3 },
  'A5 2x': { page: 'A5', height: 104.8 },
}

const props = withDefaults(defineProps<{
  slip: CommissionSlipPayload
  copies?: number
  paperSize?: CommissionSlipPaperSize
}>(), {
  copies: 1,
  paperSize: 'A5',
})

const rootRef = ref<HTMLElement | null>(null)
const { t, locale } = useI18n()

const direction = computed(() => (locale.value === 'en' ? 'ltr' : 'rtl'))
const copies = computed(() => commissionSlipCopies(props.copies))

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

const serviceLabel = (service: string | null, fallback?: string) => {
  if (!service)
    return fallback || '—'

  const key = service === 'test_offer' ? 'testOffer' : service
  const translationKey = `educational.builtInServices.${key}`
  const translated = t(translationKey)

  return translated === translationKey ? (fallback || service) : translated
}

const copyLabel = (copy: number) => copies.value > 1
  ? t(copy % 2 === 1 ? 'documents.commissionSlip.employeeCopy' : 'documents.commissionSlip.officeCopy')
  : ''

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
  await printCommissionSlip(rootRef.value, paperSizeCss.value)
}

defineExpose({ print })
</script>

<template>
  <div
    ref="rootRef"
    class="commission-sheet"
    :class="{ 'commission-sheet--two-up': isTwoUp, 'commission-sheet--two-up-compact': paperSizeCss === 'A5 2x' }"
    :style="[brandStyle, twoUp ? { '--commission-card-height': `${twoUp.height}mm` } : undefined]"
    :dir="direction"
  >
    <article
      v-for="copy in copies"
      :key="copy"
      class="commission-card"
    >
      <div
        class="commission-card__content"
        :style="contentStyle"
      >
        <header class="commission-head">
          <div class="commission-head__company">
            <img
              v-if="slip.company.logo_url"
              class="commission-head__logo"
              :src="slip.company.logo_url"
              alt=""
            >
            <div class="commission-head__identity">
              <div class="commission-head__name">
                {{ slip.company.name || slip.company.short_name || '—' }}
              </div>
              <div class="commission-head__meta">
                <span v-if="slip.company.address">{{ slip.company.address }}</span>
                <span v-if="slip.company.phone">{{ slip.company.phone }}</span>
                <span v-if="slip.company.email">{{ slip.company.email }}</span>
                <span v-if="slip.company.licence">{{ t('documents.receipt.license') }}: {{ slip.company.licence }}</span>
              </div>
            </div>
          </div>
          <div class="commission-head__title">
            <div class="commission-head__eyebrow">
              {{ t('documents.commissionSlip.title') }}
            </div>
            <div class="commission-head__sub">
              {{ t('documents.commissionSlip.subtitle') }}
            </div>
            <div
              v-if="copyLabel(copy)"
              class="commission-head__copy"
            >
              {{ copyLabel(copy) }}
            </div>
            <div class="commission-head__refs">
              <div><span>{{ t('documents.commissionSlip.reference') }}</span><strong>{{ slip.reference || '—' }}</strong></div>
              <div><span>{{ t('documents.commissionSlip.date') }}</span><strong>{{ formatDate(slip.date) }}</strong></div>
            </div>
          </div>
        </header>

        <div class="commission-grid">
          <section class="commission-block">
            <h4>{{ t('documents.commissionSlip.employeeDetails') }}</h4>
            <dl>
              <div><dt>{{ t('documents.commissionSlip.name') }}</dt><dd>{{ slip.employee?.name || '—' }}</dd></div>
              <div><dt>{{ t('documents.commissionSlip.fatherName') }}</dt><dd>{{ slip.employee?.father_name || '—' }}</dd></div>
              <div><dt>{{ t('documents.commissionSlip.contact') }}</dt><dd>{{ slip.employee?.contact || '—' }}</dd></div>
              <div><dt>{{ t('documents.commissionSlip.position') }}</dt><dd>{{ slip.employee?.position || '—' }}</dd></div>
              <div><dt>{{ t('documents.commissionSlip.department') }}</dt><dd>{{ slip.employee?.department || '—' }}</dd></div>
            </dl>
          </section>

          <section class="commission-block">
            <h4>{{ t('documents.commissionSlip.settlement') }}</h4>
            <dl>
              <div>
                <dt>{{ t('documents.commissionSlip.period') }}</dt>
                <dd>{{ formatDate(slip.settlement.period_start) }} — {{ formatDate(slip.settlement.period_end) }}</dd>
              </div>
              <div><dt>{{ t('documents.commissionSlip.status') }}</dt><dd>{{ t(`common.crmUi.commissions.statuses.${slip.settlement.status}`) }}</dd></div>
              <div><dt>{{ t('documents.commissionSlip.paymentDate') }}</dt><dd>{{ formatDate(slip.settlement.payment_date) }}</dd></div>
              <div v-if="slip.employee?.join_date"><dt>{{ t('documents.commissionSlip.joinDate') }}</dt><dd>{{ formatDate(slip.employee.join_date) }}</dd></div>
              <div v-if="slip.settlement.notes"><dt>{{ t('documents.commissionSlip.notes') }}</dt><dd>{{ slip.settlement.notes }}</dd></div>
            </dl>
          </section>
        </div>

        <section class="commission-lines">
          <h4>{{ t('documents.commissionSlip.commissions') }}</h4>
          <table>
            <thead>
              <tr>
                <th>{{ t('documents.commissionSlip.lineDate') }}</th>
                <th>{{ t('documents.commissionSlip.case') }}</th>
                <th>{{ t('documents.commissionSlip.service') }}</th>
                <th class="num">
                  {{ t('documents.commissionSlip.baseAmount') }}
                </th>
                <th class="num">
                  {{ t('documents.commissionSlip.rate') }}
                </th>
                <th class="num">
                  {{ t('documents.commissionSlip.commission') }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="line in slip.accruals"
                :key="line.id"
              >
                <td>{{ formatDate(line.earned_at) }}</td>
                <td>
                  <div>{{ line.case_name || '—' }}</div>
                  <div
                    v-if="line.contract_number"
                    class="muted"
                  >
                    {{ line.contract_number }}
                  </div>
                </td>
                <td>
                  <div>
                    {{ line.entry_type === 'adjustment'
                      ? t('common.crmUi.commissions.entryTypes.adjustment')
                      : serviceLabel(line.service, line.service_label) }}
                  </div>
                  <div
                    v-if="line.adjustment_reason"
                    class="muted"
                  >
                    {{ line.adjustment_reason }}
                  </div>
                </td>
                <td class="num">
                  {{ line.entry_type === 'adjustment' ? '—' : formatMoney(line.eligible_base_amount, slip.totals.currency) }}
                </td>
                <td class="num">
                  {{ line.entry_type === 'adjustment' ? '—' : `${line.percentage}%` }}
                </td>
                <td class="num">
                  {{ formatMoney(line.commission_base_amount, slip.totals.currency) }}
                </td>
              </tr>
              <tr v-if="!slip.accruals.length">
                <td
                  colspan="6"
                  class="muted center"
                >
                  {{ t('documents.commissionSlip.noEntries') }}
                </td>
              </tr>
            </tbody>
          </table>
        </section>

        <section class="commission-total">
          <div class="commission-total__count">
            {{ t('documents.commissionSlip.entryCount', slip.totals.count) }}
          </div>
          <div class="commission-total__amount">
            <span>{{ t('documents.commissionSlip.total') }}</span>
            <strong>{{ formatMoney(slip.totals.gross, slip.totals.currency) }}</strong>
          </div>
        </section>

        <footer class="commission-foot">
          <div class="commission-foot__note">
            {{ t('documents.commissionSlip.footerNote') }}
          </div>
          <div class="commission-foot__stamps">
            <div class="commission-stamp">
              <span>{{ t('documents.commissionSlip.employeeSignature') }}</span>
            </div>
            <div class="commission-stamp">
              <img
                v-if="slip.signature.url"
                :src="slip.signature.url"
                alt=""
              >
              <span>{{ t('documents.commissionSlip.authorizedSignature') }}</span>
            </div>
          </div>
        </footer>
      </div>
    </article>
  </div>
</template>

<style scoped>
.commission-sheet {
  display: flex;
  flex-direction: column;
  gap: 6mm;
  width: 100%;
  color: #1f2937;
  font-size: 8.5pt;
  line-height: 1.35;
}

.commission-card {
  position: relative;
  width: 100%;
  min-height: 120mm;
  overflow: hidden;
  background: #ffffff;
  border: 1px solid #d7dde5;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.1);
  overflow-wrap: anywhere;
}

.commission-card__content {
  display: flex;
  flex-direction: column;
  gap: 2.5mm;
  height: 100%;
  padding: 8mm;
}

.commission-card__content > * { flex-shrink: 0; }

.commission-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 5mm;
  padding-block-end: 2.5mm;
  border-block-end: 2px solid var(--doc-primary);
}

.commission-head__company {
  display: flex;
  align-items: flex-start;
  gap: 3mm;
  flex: 1;
  min-inline-size: 0;
}

.commission-head__logo {
  width: 13mm;
  height: 13mm;
  object-fit: contain;
  flex-shrink: 0;
}

.commission-head__identity {
  min-inline-size: 0;
}

.commission-head__name {
  color: var(--doc-primary);
  font-size: 12.5pt;
  font-weight: 700;
  line-height: 1.2;
}

.commission-head__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 1mm 3.5mm;
  margin-block-start: 1mm;
  color: #52616b;
  font-size: 7pt;
}

.commission-head__title {
  flex-shrink: 0;
  min-inline-size: 0;
  max-inline-size: 44%;
  text-align: end;
}

.commission-head__eyebrow {
  color: var(--doc-primary);
  font-size: 11.5pt;
  font-weight: 800;
  text-transform: uppercase;
}

.commission-head__sub {
  color: #52616b;
  font-size: 7pt;
}

.commission-head__copy {
  margin-block-start: 0.6mm;
  color: var(--doc-primary);
  font-size: 6.5pt;
  font-weight: 700;
  text-transform: uppercase;
}

.commission-head__refs {
  margin-block-start: 1mm;
  font-size: 7.5pt;
}

.commission-head__refs div {
  display: flex;
  justify-content: flex-end;
  gap: 2mm;
}

.commission-head__refs span {
  color: #52616b;
}

.commission-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4mm;
}

.commission-block h4,
.commission-lines h4 {
  margin: 0 0 1.2mm;
  padding-block-end: 0.8mm;
  border-block-end: 1px solid var(--doc-primary-tint);
  color: var(--doc-primary);
  font-size: 7.5pt;
  font-weight: 700;
  text-transform: uppercase;
}

.commission-block dl {
  display: grid;
  gap: 0.8mm;
  margin: 0;
}

.commission-block dl > div {
  display: flex;
  justify-content: space-between;
  gap: 3mm;
}

.commission-block dt {
  color: #52616b;
  font-size: 7.4pt;
  flex-shrink: 0;
}

.commission-block dd {
  margin: 0;
  font-size: 7.4pt;
  font-weight: 600;
  text-align: end;
  overflow-wrap: anywhere;
}

.commission-lines table {
  width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
}

.commission-lines th,
.commission-lines td {
  padding: 1mm 1.4mm;
  border-block-end: 1px solid #e2e8f0;
  text-align: start;
  vertical-align: top;
  font-size: 7.2pt;
}

.commission-lines th {
  color: #52616b;
  font-size: 6.6pt;
  font-weight: 700;
  text-transform: uppercase;
}

.commission-lines th:nth-child(1) { width: 15%; }
.commission-lines th:nth-child(2) { width: 27%; }
.commission-lines th:nth-child(3) { width: 24%; }
.commission-lines th:nth-child(4) { width: 14%; }
.commission-lines th:nth-child(5) { width: 8%; }
.commission-lines th:nth-child(6) { width: 12%; }

.commission-lines td.num,
.commission-lines th.num {
  text-align: end;
  font-variant-numeric: tabular-nums;
}

.commission-lines .muted {
  color: #7b8794;
  font-size: 6.4pt;
}

.commission-lines .center {
  text-align: center;
}

.commission-total {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 4mm;
  padding: 1.8mm 3mm;
  border: 1px solid var(--doc-secondary-line);
  border-inline-start: 3px solid var(--doc-secondary);
  border-radius: 1.5mm;
  background: var(--doc-primary-soft);
}

.commission-total__count {
  color: #52616b;
  font-size: 7pt;
}

.commission-total__amount {
  display: flex;
  align-items: baseline;
  gap: 2.5mm;
  text-align: end;
}

.commission-total__amount span {
  color: #52616b;
  font-size: 7pt;
  font-weight: 700;
  text-transform: uppercase;
}

.commission-total__amount strong {
  color: var(--doc-primary);
  font-size: 11pt;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

.commission-foot {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 5mm;
  margin-block-start: auto;
  padding-block-start: 2mm;
  flex-wrap: wrap;
}

.commission-foot__note {
  color: #52616b;
  font-size: 7pt;
  max-inline-size: 45%;
}

.commission-foot__stamps {
  display: flex;
  gap: 5mm;
  flex-wrap: wrap;
}

.commission-stamp {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  width: 34mm;
  min-height: 15mm;
  padding-block-start: 1.5mm;
  border-block-end: 1px solid #9ca3af;
}

.commission-stamp img {
  max-width: 28mm;
  max-height: 10mm;
  object-fit: contain;
  margin-block-end: 0.8mm;
}

.commission-stamp span {
  color: #52616b;
  font-size: 6.4pt;
  font-weight: 700;
  text-align: center;
}

/* Layout uses logical properties, so RTL/LTR mirror without extra rules. */

.commission-sheet--two-up .commission-card {
  height: var(--commission-card-height, 148.3mm);
  min-height: 0;
  font-size: 7.8pt;
}

.commission-sheet--two-up .commission-card__content {
  gap: 1.8mm;
  padding: 4.5mm;
}

.commission-sheet--two-up-compact .commission-card__content {
  width: 142.857%;
  height: 142.857%;
}

.commission-sheet--two-up .commission-card:nth-child(odd):not(:last-child) {
  border-block-end: 1px dashed #444;
}
</style>

<style>
.commission-slip-print-host { display: none; }
.commission-slip-print-host[data-measuring] { display: block; position: fixed; inset-inline-start: -10000px; inset-block-start: 0; }
.commission-slip-print-host .commission-slip-print-page { gap: 0; margin: 0; padding: 0; box-sizing: border-box; }
.commission-slip-print-host .commission-card { min-height: 0; border: 0; box-shadow: none; box-sizing: border-box; }
.commission-slip-print-host .commission-card:not(:last-child) { border-block-end: 1px dashed #444; }
.commission-slip-print-host .commission-card__content { box-sizing: border-box; }

@media print {
  body.commission-slip-print-mode { margin: 0 !important; padding: 0 !important; min-height: 0 !important; height: auto !important; display: block !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body.commission-slip-print-mode > :not(.commission-slip-print-host) { display: none !important; }
  body.commission-slip-print-mode > .commission-slip-print-host { display: block !important; position: static !important; margin: 0 !important; padding: 0 !important; min-height: 0 !important; }
  body.commission-slip-print-mode .commission-slip-print-page { break-inside: avoid; page-break-inside: avoid; }
  body.commission-slip-print-mode .commission-slip-print-page:not(:last-child) { break-after: page; page-break-after: always; }
}
</style>
