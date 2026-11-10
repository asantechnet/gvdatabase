<script setup lang="ts">
import { printDocument } from '@/utils/printDocument'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatAmountInWords } from '@/utils/amountInWords'
import { documentBrandStyle } from '@/utils/documentBranding'

export interface Company {
  name: string
  brandName: string
  addressLines: string[]
  email: string
  phone: string
  tinNumber?: string
  logo?: string
  signature?: string
  primaryColor?: string
  secondaryColor?: string
}

export interface Employee {
  name: string
  designation: string
  department?: string
  dateOfJoining: string
  payPeriod: string
  payDate: string
  accountNumber: string
  workedDays: number
  totalDays: number
  /** Approved company holidays and allowance-covered off days in the period. */
  offDays?: number
  absentDays: number
  tazkiraNumber?: string
  tinNumber?: string
}

export interface EarningDeduction {
  label: string
  amount: number
}

export interface SalarySlipProps {
  documentTitle?: string
  documentNumber?: string
  payslipSubtitle?: string
  currency?: string
  company: Company
  employee: Employee
  earnings: EarningDeduction[]
  deductions: EarningDeduction[]
  reimbursements?: EarningDeduction[]
  /** Advances recovered in this payroll period, itemized by reference. */
  advances?: EarningDeduction[]
  /** Total advance recovery, used when the itemized rows are unavailable. */
  advanceRecovery?: number
}

const props = withDefaults(defineProps<SalarySlipProps>(), {
  documentTitle: 'OFFICIAL PAYSLIP',
  currency: 'AFN',
  deductions: () => [],
  reimbursements: () => [],
  advances: () => [],
  advanceRecovery: 0,
})

const { locale } = useI18n()
const payslipContainerRef = ref<HTMLElement | null>(null)

const brandStyle = computed(() => documentBrandStyle(
  props.company.primaryColor,
  props.company.secondaryColor,
))

// A payslip is deliberately self-contained: it uses the company's logo rather
// than a configurable letterhead so the A4 payroll layout stays predictable.
const grossSalary = computed(() => props.earnings.reduce((sum, item) => sum + item.amount, 0))

const totalDeductions = computed(() => props.deductions.reduce((sum, item) => sum + item.amount, 0))

const totalReimbursements = computed(() => props.reimbursements.reduce((sum, item) => sum + item.amount, 0))

const netPay = computed(() => grossSalary.value - totalDeductions.value)

const totalNetPayable = computed(() => netPay.value + totalReimbursements.value)

const amountInWords = computed(() => formatAmountInWords(
  totalNetPayable.value,
  locale.value,
  { code: props.currency, decimalPlaces: 2 },
))

const offDays = computed(() => Number(props.employee.offDays ?? 0))

const attendanceMetrics = computed(() => [
  { key: 'total', value: props.employee.totalDays },
  { key: 'worked', value: props.employee.workedDays },
  { key: 'offDays', value: offDays.value },
  { key: 'absent', value: props.employee.absentDays },
])

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: props.currency || 'AFN',
    minimumFractionDigits: 2,
  }).format(amount).replace('AFN', '؋')
}

const printSalarySlip = async () => {
  if (!payslipContainerRef.value)
    return

  await printDocument({
    bodyClass: 'printing-payslip-document',
    pageStyle: 'size: A4 portrait; margin: 0;',
    element: payslipContainerRef.value,
  })
}

defineExpose({ print: printSalarySlip })

</script>

<template>
  <div
    ref="payslipContainerRef"
    class="payslip-container print-document"
    :class="{ 'persian-document': ['fa-AF', 'ps-AF'].includes($i18n.locale) }"
    :style="brandStyle"
    :dir="$i18n.locale === 'en' ? 'ltr' : 'rtl'"
  >
    <div class="payslip-content">
      <header class="doc-head">
        <div class="brand">
          <img
            v-if="company.logo"
            :src="company.logo"
            :alt="company.name"
            class="brand__logo"
          >
          <div class="brand__text">
            <h1 class="brand__name">
              {{ company.name }}
            </h1>
            <span
              v-if="company.brandName"
              class="brand__badge"
            >{{ company.brandName }}</span>
            <p
              v-if="company.addressLines?.length"
              class="brand__meta"
            >
              {{ company.addressLines.join(' · ') }}
            </p>
            <p class="brand__meta">
              <bdi v-if="company.email">{{ company.email }}</bdi>
              <span v-if="company.email && company.phone"> · </span>
              <bdi v-if="company.phone">{{ company.phone }}</bdi>
            </p>
            <p
              v-if="company.tinNumber"
              class="brand__meta"
            >
              {{ $t('documents.payslip_template.payslipTemplate.mofTINRegNo') }} <strong>{{ company.tinNumber }}</strong>
            </p>
          </div>
        </div>

        <div class="doc-title">
          <span class="doc-title__eyebrow">{{ $t('documents.payslip_template.payslipTemplate.officialPAYSLIP') }}</span>
          <span class="doc-title__sub">شقه معاش رسمی / رسمی د معاش شقه</span>
          <dl class="doc-title__meta">
            <div>
              <dt>{{ $t('documents.payslip_template.payslipTemplate.docNo') }}</dt>
              <dd>{{ documentNumber || `PAY-${employee.payPeriod}` }}</dd>
            </div>
            <div>
              <dt>{{ $t('documents.payslip_template.payslipTemplate.payPeriod') }}</dt>
              <dd>{{ employee.payPeriod }}</dd>
            </div>
            <div>
              <dt>{{ $t('documents.payslip_template.payslipTemplate.payDate') }}</dt>
              <dd>{{ formatLocalizedDate(employee.payDate) }}</dd>
            </div>
          </dl>
        </div>
      </header>

      <section class="employee-strip">
        <div class="employee-strip__item employee-strip__item--wide">
          <span class="label">{{ $t('documents.payslip_template.payslipTemplate.employeeName') }}</span>
          <span class="value value--strong">{{ employee.name }}</span>
        </div>
        <div class="employee-strip__item">
          <span class="label">{{ $t('documents.payslip_template.payslipTemplate.designation') }}</span>
          <span class="value">{{ employee.designation }}</span>
        </div>
        <div class="employee-strip__item">
          <span class="label">{{ $t('common.fields.department') }}</span>
          <span class="value">{{ employee.department || '—' }}</span>
        </div>
        <div class="employee-strip__item">
          <span class="label">{{ $t('documents.payslip_template.payslipTemplate.dateOfJoining') }}</span>
          <span class="value">{{ formatLocalizedDate(employee.dateOfJoining) }}</span>
        </div>
        <div class="employee-strip__item">
          <span class="label">{{ $t('documents.payslip_template.payslipTemplate.tazkiraNationalID') }}</span>
          <span class="value value--mono">{{ employee.tazkiraNumber || '—' }}</span>
        </div>
        <div class="employee-strip__item">
          <span class="label">{{ $t('documents.payslip_template.payslipTemplate.bankAccountNo') }}</span>
          <span class="value value--mono">{{ employee.accountNumber || '—' }}</span>
        </div>
      </section>

      <section class="attendance-strip">
        <div
          v-for="metric in attendanceMetrics"
          :key="metric.key"
          class="attendance-strip__item"
          :class="{ 'attendance-strip__item--off': metric.key === 'offDays' && offDays > 0 }"
        >
          <span class="attendance-strip__label">
            {{ metric.key === 'total'
              ? $t('pages.payrolls.totalWorkingDays')
              : metric.key === 'worked'
                ? $t('pages.payrolls.workedDays')
                : metric.key === 'offDays'
                  ? $t('pages.payrolls.offDays')
                  : $t('pages.payrolls.absentDays') }}
          </span>
          <span class="attendance-strip__value">{{ metric.value }}</span>
        </div>
      </section>

      <div class="ledger-grid">
        <section class="ledger">
          <h3 class="ledger__title">
            {{ $t('documents.payslip_template.payslipTemplate.earningsDESCRIPTION') }}
          </h3>
          <table class="ledger__table">
            <tbody>
              <tr
                v-for="(item, index) in earnings"
                :key="`earning-${index}`"
              >
                <td>{{ item.label }}</td>
                <td class="num">
                  {{ formatCurrency(item.amount) }}
                </td>
              </tr>
              <tr class="ledger__total">
                <td>{{ $t('documents.payslip_template.payslipTemplate.grossEarnings') }}</td>
                <td class="num">
                  {{ formatCurrency(grossSalary) }}
                </td>
              </tr>
            </tbody>
          </table>
        </section>

        <section class="ledger">
          <h3 class="ledger__title">
            {{ $t('documents.payslip_template.payslipTemplate.deductionsDESCRIPTION') }}
          </h3>
          <table class="ledger__table">
            <tbody>
              <tr
                v-for="(item, index) in deductions"
                :key="`deduction-${index}`"
              >
                <td>{{ item.label }}</td>
                <td class="num">
                  {{ formatCurrency(item.amount) }}
                </td>
              </tr>
              <tr v-if="!deductions.length">
                <td colspan="2" class="muted">
                  {{ $t('common.statuses.none') }}
                </td>
              </tr>
              <tr class="ledger__total">
                <td>{{ $t('documents.payslip_template.payslipTemplate.totalDeductions') }}</td>
                <td class="num">
                  {{ formatCurrency(totalDeductions) }}
                </td>
              </tr>
            </tbody>
          </table>
        </section>
      </div>

      <section
        v-if="advances.length || advanceRecovery > 0"
        class="advances"
      >
        <h3 class="ledger__title">
          {{ $t('pages.payroll.staffSalary.staffSalary.advancesRecovered') }}
        </h3>
        <table class="ledger__table ledger__table--compact">
          <tbody>
            <tr
              v-for="(item, index) in advances"
              :key="`advance-${index}`"
            >
              <td>{{ item.label }}</td>
              <td class="num">
                {{ formatCurrency(item.amount) }}
              </td>
            </tr>
            <tr v-if="!advances.length">
              <td>{{ $t('pages.payroll.staffSalary.staffSalary.advanceRecovery') }}</td>
              <td class="num">
                {{ formatCurrency(advanceRecovery) }}
              </td>
            </tr>
          </tbody>
        </table>
      </section>

      <section
        v-if="reimbursements.length"
        class="advances"
      >
        <h3 class="ledger__title">
          {{ $t('documents.payslip_template.payslipTemplate.reimbursementsAdditions') }}
        </h3>
        <table class="ledger__table ledger__table--compact">
          <tbody>
            <tr
              v-for="(item, index) in reimbursements"
              :key="`reimbursement-${index}`"
            >
              <td>{{ item.label }}</td>
              <td class="num">
                {{ formatCurrency(item.amount) }}
              </td>
            </tr>
            <tr class="ledger__total">
              <td>{{ $t('documents.payslip_template.payslipTemplate.totalReimbursements') }}</td>
              <td class="num">
                {{ formatCurrency(totalReimbursements) }}
              </td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="net-banner">
        <div class="net-banner__info">
          <span class="net-banner__label">{{ $t('documents.payslip.totalNetPayable', { currency }) }}</span>
          <span class="net-banner__words">{{ $t('documents.payslip.amountInWords') }}: {{ amountInWords }}</span>
        </div>
        <div class="net-banner__amount">
          {{ formatCurrency(totalNetPayable) }}
        </div>
      </section>

      <footer class="signatures">
        <div class="signature">
          <div class="signature__line" />
          <span>{{ $t('documents.payslip_template.payslipTemplate.employeeSignature') }}</span>
        </div>
        <div class="signature">
          <div class="signature__line" />
          <span>{{ $t('documents.payslip_template.payslipTemplate.hrPayrollOfficer') }}</span>
        </div>
        <div class="signature">
          <img
            v-if="company.signature"
            :src="company.signature"
            :alt="$t('documents.payslip_template.payslipTemplate.companySignature')"
            class="signature__image"
          >
          <div class="signature__line" />
          <span>{{ $t('documents.payslip_template.payslipTemplate.companySignature') }}</span>
        </div>
      </footer>

    </div>
  </div>
</template>

<style scoped>
.payslip-container {
  --payslip-ink: #16212f;
  --payslip-muted: #5b6b7c;
  --payslip-line: #d6dee7;
  --payslip-accent: var(--doc-primary);
  --payslip-soft: var(--doc-primary-soft);

  box-sizing: border-box;
  width: 210mm;
  min-height: 297mm;
  max-width: 210mm;
  margin: 0 auto;
  padding: 12mm;
  position: relative;
  background: #fff;
  color: var(--payslip-ink);
  font-family: 'Public Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  font-size: 9pt;
  line-height: 1.35;
}
.payslip-content {
  display: flex;
  flex-direction: column;
  gap: 4mm;
  min-height: 100%;
}
.payslip-container.persian-document {
  font-family: Vazirmatn, 'Public Sans', sans-serif;
}

/* Header ------------------------------------------------------------------ */
.doc-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 5mm;
  padding-block-end: 3mm;
  border-block-end: 2.5px solid var(--payslip-accent);
}

.brand {
  display: flex;
  align-items: flex-start;
  gap: 4mm;
  min-inline-size: 0;
}

.brand__logo {
  inline-size: 25mm;
  block-size: 25mm;
  object-fit: contain;
  flex-shrink: 0;
}

.brand__payslip-title {
  display: flex;
  flex-direction: column;
  gap: .7mm;
  color: var(--payslip-accent);
  font-size: 14pt;
  font-weight: 800;
  letter-spacing: .04em;
}

.brand__payslip-title small {
  color: var(--payslip-muted);
  font-size: 7.8pt;
  font-weight: 700;
  letter-spacing: 0;
}

.brand__name {
  margin: 0;
  font-size: 13pt;
  font-weight: 800;
  letter-spacing: -0.01em;
}

.brand__badge {
  display: inline-block;
  margin-block: 1.4mm;
  padding: 0.4mm 1.6mm;
  border-radius: 2px;
  background: var(--payslip-soft);
  color: var(--payslip-accent);
  font-size: 7.4pt;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.brand__meta {
  margin: .8mm 0 0;
  color: var(--payslip-muted);
  font-size: 7.2pt;
}

.doc-title {
  text-align: end;
  min-inline-size: 0;
  flex-shrink: 0;
}

.doc-title__eyebrow {
  display: block;
  color: var(--payslip-accent);
  font-size: 12pt;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.doc-title__sub {
  display: block;
  margin-block: 1mm 3mm;
  color: var(--payslip-muted);
  font-size: 7pt;
  font-weight: 600;
}

.doc-title__meta {
  display: grid;
  gap: 1mm;
  margin: 0;
}

.doc-title__meta > div {
  display: flex;
  justify-content: flex-end;
  gap: 3mm;
  font-size: 9pt;
}

.doc-title__meta dt {
  color: var(--payslip-muted);
}

.doc-title__meta dd {
  margin: 0;
  font-weight: 700;
}

/* Employee + attendance ---------------------------------------------------- */
.employee-strip {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 2.5mm 3mm;
  padding: 3mm 3.5mm;
  border: 1px solid var(--payslip-line);
  border-radius: 1.5mm;
  background: var(--payslip-soft);
}

.employee-strip__item {
  display: flex;
  flex-direction: column;
  min-inline-size: 0;
}

.employee-strip__item--wide {
  grid-column: span 2;
}

.label {
  color: var(--payslip-muted);
  font-size: 7.8pt;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.value {
  overflow: hidden;
  font-size: 10.2pt;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.value--strong {
  font-weight: 800;
}

.value--mono {
  font-variant-numeric: tabular-nums;
}

.attendance-strip {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  border: 1px solid var(--payslip-line);
  border-radius: 1.5mm;
  overflow: hidden;
}

.attendance-strip__item {
  display: flex;
  flex-direction: column;
  gap: .8mm;
  padding: 2mm 2.25mm;
  text-align: center;
  border-inline-end: 1px solid var(--payslip-line);
}

.attendance-strip__item:last-child {
  border-inline-end: 0;
}

.attendance-strip__item--off {
  background: var(--doc-secondary-soft);
}

.attendance-strip__label {
  color: var(--payslip-muted);
  font-size: 7.4pt;
  font-weight: 700;
  text-transform: uppercase;
  white-space: nowrap;
}

.attendance-strip__value {
  font-size: 12pt;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

/* Ledgers ------------------------------------------------------------------ */
.ledger-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 5mm;
}

.ledger__title {
  margin: 0 0 2mm;
  color: var(--payslip-accent);
  font-size: 9pt;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.ledger__table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
}

.ledger__table td {
  padding: 2mm 2.5mm;
  border-block-end: 1px solid var(--payslip-line);
  font-size: 9.5pt;
  overflow-wrap: anywhere;
}

.ledger__table td.num {
  inline-size: 42%;
  text-align: end;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  white-space: nowrap;
}

.ledger__total td {
  border-block-end: 0;
  border-block-start: 1.2px solid var(--payslip-line);
  background: rgb(var(--v-theme-primary) / .12);
  font-weight: 800;
}

.ledger__table--compact td {
  padding-block: 1.5mm;
}

.muted {
  color: var(--payslip-muted);
  text-align: center;
}

.advances {
  break-inside: avoid;
}

/* Net banner --------------------------------------------------------------- */
.net-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 5mm;
  padding: 3.5mm 4mm;
  border: 1px solid var(--doc-primary-tint);
  border-inline-start: 4px solid var(--payslip-accent);
  border-inline-end: 3px solid var(--doc-secondary);
  border-radius: 1.5mm;
  background: var(--payslip-soft);
}

.net-banner__info {
  display: flex;
  flex-direction: column;
  min-inline-size: 0;
}

.net-banner__label {
  color: var(--payslip-accent);
  font-size: 9pt;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.net-banner__words {
  color: var(--payslip-muted);
  font-size: 8.2pt;
  font-style: italic;
}

.net-banner__amount {
  color: var(--payslip-accent);
  font-size: 15pt;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

/* Signatures --------------------------------------------------------------- */
.signatures {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 5mm;
  margin-block-start: 5mm;
  padding-block-start: 8mm;
  border-block-start: 1px solid var(--payslip-line);
}

.signature {
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  min-block-size: 15mm;
  text-align: center;
}

.signature__line {
  margin-block-end: 1.2mm;
  border-block-start: 1px solid #7b8a99;
}

.signature__image {
  display: block;
  max-inline-size: 32mm;
  max-block-size: 13mm;
  margin: 0 auto 1.5mm;
  object-fit: contain;
}

.signature span {
  color: var(--payslip-muted);
  font-size: 8pt;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

[dir='rtl'] .doc-title,
[dir='rtl'] .ledger__table td.num {
  text-align: start;
}

[dir='rtl'] .doc-title__meta > div {
  justify-content: flex-start;
}

@media print {
  :global(body.printing-payslip-document .payslip-container) {
    width: 210mm !important;
    min-height: 297mm !important;
    max-width: 210mm !important;
    box-shadow: none;
  }
}
</style>
