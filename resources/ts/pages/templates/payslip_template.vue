<script setup lang="ts">
import { printDocument } from '@/utils/printDocument'
import { computed, ref } from 'vue'

export interface Company {
  name: string
  brandName: string
  addressLines: string[]
  email: string
  phone: string
  tinNumber?: string
  logo?: string
  signature?: string
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
  paidLeaveDays: number
  /** Approved company off days inside the pay period (paid, non-worked). */
  offDays?: number
  absentDays: number
  unpaidLeaveDays: number
  tazkiraNumber?: string
  tinNumber?: string
}

export interface EarningDeduction {
  label: string
  amount: number
}

export interface LetterheadSafeArea {
  x: number
  y: number
  width: number
  height: number
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
  amountInWords: string
  /** Optional letterhead artwork from Settings → Documents. */
  letterheadUrl?: string
  /** Safe-zone (percent of the page) where the payslip content is allowed to land. */
  letterheadSafe?: LetterheadSafeArea
}

const props = withDefaults(defineProps<SalarySlipProps>(), {
  documentTitle: 'OFFICIAL PAYSLIP',
  currency: 'AFN',
  deductions: () => [],
  reimbursements: () => [],
  advances: () => [],
  letterheadUrl: '',
  letterheadSafe: () => ({ x: 8, y: 14, width: 84, height: 72 }),
})

const payslipContainerRef = ref<HTMLElement | null>(null)

const hasLetterhead = computed(() => Boolean(props.letterheadUrl))

const letterheadBackground = computed(() => (props.letterheadUrl
  ? { backgroundImage: `url(${props.letterheadUrl})` }
  : undefined))

const letterheadSafeStyle = computed(() => ({
  insetInlineStart: `${props.letterheadSafe?.x ?? 8}%`,
  insetBlockStart: `${props.letterheadSafe?.y ?? 14}%`,
  width: `${props.letterheadSafe?.width ?? 84}%`,
  height: `${props.letterheadSafe?.height ?? 72}%`,
}))

const grossSalary = computed(() => props.earnings.reduce((sum, item) => sum + item.amount, 0))

const totalDeductions = computed(() => props.deductions.reduce((sum, item) => sum + item.amount, 0))

const totalReimbursements = computed(() => props.reimbursements.reduce((sum, item) => sum + item.amount, 0))

const netPay = computed(() => grossSalary.value - totalDeductions.value)

const totalNetPayable = computed(() => netPay.value + totalReimbursements.value)

const offDays = computed(() => Number(props.employee.offDays ?? 0))

const attendanceMetrics = computed(() => [
  { key: 'worked', value: `${props.employee.workedDays} / ${props.employee.totalDays}` },
  { key: 'paidLeave', value: props.employee.paidLeaveDays },
  { key: 'offDays', value: offDays.value },
  { key: 'absent', value: props.employee.absentDays },
  { key: 'unpaidLeave', value: props.employee.unpaidLeaveDays },
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
  await printDocument({ bodyClass: 'printing-payslip-document', pageStyle: 'size: A5; margin: 0;', element: payslipContainerRef.value })
}

defineExpose({ print: printSalarySlip })
</script>

<template>
  <div
    ref="payslipContainerRef"
    class="payslip-container print-document"
    :class="{ 'persian-document': ['fa-AF', 'ps-AF'].includes($i18n.locale), 'payslip-container--letterhead': hasLetterhead }"
    :style="letterheadBackground"
    :dir="$i18n.locale === 'en' ? 'ltr' : 'rtl'"
  >
    <div
      class="payslip-content"
      :class="{ 'payslip-content--safe': hasLetterhead }"
      :style="hasLetterhead ? letterheadSafeStyle : undefined"
    >
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
            {{ metric.key === 'worked'
              ? $t('documents.payslip_template.payslipTemplate.workedTotalDays')
              : metric.key === 'paidLeave'
                ? $t('common.statuses.paidLeave')
                : metric.key === 'offDays'
                  ? $t('pages.payroll.staffSalary.staffSalary.offDays')
                  : metric.key === 'absent'
                    ? $t('components.dataTablePageKpis.absentDays')
                    : $t('documents.timesheet.statuses.unpaid-leave') }}
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
        v-if="advances.length"
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
          <span
            v-if="amountInWords"
            class="net-banner__words"
          >{{ amountInWords }}</span>
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

      <p class="compliance">
        {{ $t('documents.payslip_template.payslipTemplate.thisDocumentIsGeneratedInComplianceWithTheAfghanistanLaborLawAndMinist') }}
      </p>
    </div>
  </div>
</template>

<style scoped>
.payslip-container {
  --payslip-ink: #16212f;
  --payslip-muted: #5b6b7c;
  --payslip-line: #d6dee7;
  --payslip-accent: #0f6f92;
  --payslip-soft: #f4f8fb;

  box-sizing: border-box;
  width: 100%;
  max-width: 148mm;
  margin: 0 auto;
  padding: 7mm;
  background: #fff;
  background-size: 100% 100%;
  background-position: center;
  background-repeat: no-repeat;
  color: var(--payslip-ink);
  font-family: 'Public Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  font-size: 8.6pt;
  line-height: 1.35;
}

.payslip-container--letterhead {
  padding: 0;
}

.payslip-content {
  display: flex;
  flex-direction: column;
  gap: 3mm;
  min-height: 100%;
}

.payslip-content--safe {
  position: absolute;
  padding: 4mm;
  overflow: hidden;
}

.payslip-container.persian-document {
  font-family: Vazirmatn, 'Public Sans', sans-serif;
}

/* Header ------------------------------------------------------------------ */
.doc-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 6mm;
  padding-block-end: 3mm;
  border-block-end: 2.5px solid var(--payslip-accent);
}

.brand {
  display: flex;
  align-items: flex-start;
  gap: 3mm;
  min-inline-size: 0;
}

.brand__logo {
  inline-size: 13mm;
  block-size: 13mm;
  object-fit: contain;
  flex-shrink: 0;
}

.brand__name {
  margin: 0;
  font-size: 13pt;
  font-weight: 800;
  letter-spacing: -0.01em;
}

.brand__badge {
  display: inline-block;
  margin-block: 1mm;
  padding: 0.4mm 1.6mm;
  border-radius: 2px;
  background: var(--payslip-soft);
  color: var(--payslip-accent);
  font-size: 6.8pt;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.brand__meta {
  margin: 0.5mm 0 0;
  color: var(--payslip-muted);
  font-size: 6.6pt;
}

.doc-title {
  text-align: end;
  min-inline-size: 0;
  flex-shrink: 0;
}

.doc-title__eyebrow {
  display: block;
  color: var(--payslip-accent);
  font-size: 11pt;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.doc-title__sub {
  display: block;
  margin-block: 0.6mm 2mm;
  color: var(--payslip-muted);
  font-size: 7pt;
  font-weight: 600;
}

.doc-title__meta {
  display: grid;
  gap: 0.6mm;
  margin: 0;
}

.doc-title__meta > div {
  display: flex;
  justify-content: flex-end;
  gap: 2mm;
  font-size: 7pt;
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
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 2mm 4mm;
  padding: 2.5mm 3mm;
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
  font-size: 6.2pt;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.value {
  overflow: hidden;
  font-size: 8pt;
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
  grid-template-columns: repeat(5, minmax(0, 1fr));
  border: 1px solid var(--payslip-line);
  border-radius: 1.5mm;
  overflow: hidden;
}

.attendance-strip__item {
  display: flex;
  flex-direction: column;
  gap: 0.5mm;
  padding: 1.8mm 2mm;
  text-align: center;
  border-inline-end: 1px solid var(--payslip-line);
}

.attendance-strip__item:last-child {
  border-inline-end: 0;
}

.attendance-strip__item--off {
  background: #eef7f1;
}

.attendance-strip__label {
  color: var(--payslip-muted);
  font-size: 6.2pt;
  font-weight: 700;
  text-transform: uppercase;
  white-space: nowrap;
}

.attendance-strip__value {
  font-size: 9pt;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

/* Ledgers ------------------------------------------------------------------ */
.ledger-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 4mm;
}

.ledger__title {
  margin: 0 0 1.2mm;
  color: var(--payslip-accent);
  font-size: 7pt;
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
  padding: 1.3mm 1.6mm;
  border-block-end: 1px solid var(--payslip-line);
  font-size: 7.6pt;
  overflow-wrap: anywhere;
}

.ledger__table td.num {
  inline-size: 34%;
  text-align: end;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

.ledger__total td {
  border-block-end: 0;
  border-block-start: 1.2px solid var(--payslip-line);
  background: var(--payslip-soft);
  font-weight: 800;
}

.ledger__table--compact td {
  padding-block: 1mm;
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
  padding: 3mm 4mm;
  border: 1px solid #9ec7d8;
  border-inline-start: 4px solid var(--payslip-accent);
  border-radius: 1.5mm;
  background: #f2f9fc;
}

.net-banner__info {
  display: flex;
  flex-direction: column;
  min-inline-size: 0;
}

.net-banner__label {
  color: #0b5673;
  font-size: 7pt;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.net-banner__words {
  color: var(--payslip-muted);
  font-size: 6.8pt;
  font-style: italic;
}

.net-banner__amount {
  color: #0b4a63;
  font-size: 14pt;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

/* Signatures --------------------------------------------------------------- */
.signatures {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 5mm;
  margin-block-start: auto;
  padding-block-start: 4mm;
}

.signature {
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  min-block-size: 14mm;
  text-align: center;
}

.signature__line {
  margin-block-end: 1.2mm;
  border-block-start: 1px solid #7b8a99;
}

.signature__image {
  display: block;
  max-inline-size: 26mm;
  max-block-size: 10mm;
  margin: 0 auto 1mm;
  object-fit: contain;
}

.signature span {
  color: var(--payslip-muted);
  font-size: 6.4pt;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.compliance {
  margin: 0;
  padding-block-start: 2mm;
  border-block-start: 1px dashed var(--payslip-line);
  color: var(--payslip-muted);
  font-size: 6.2pt;
  text-align: center;
}

[dir='rtl'] .doc-title,
[dir='rtl'] .ledger__table td.num {
  text-align: start;
}

[dir='rtl'] .doc-title__meta > div {
  justify-content: flex-start;
}

@media print {
  .payslip-container {
    box-shadow: none;
  }
}
</style>
