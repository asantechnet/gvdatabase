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

// Computed properties
const grossSalary = computed(() => {
  return props.earnings.reduce((sum, item) => sum + item.amount, 0)
})

const totalDeductions = computed(() => {
  return props.deductions.reduce((sum, item) => sum + item.amount, 0)
})

const totalReimbursements = computed(() => {
  return props.reimbursements.reduce((sum, item) => sum + item.amount, 0)
})

const netPay = computed(() => {
  return grossSalary.value - totalDeductions.value
})

const totalNetPayable = computed(() => {
  return netPay.value + totalReimbursements.value
})

const formatCurrency = (amount: number) => {
  // Afghanistan Afghani Formatting
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: props.currency || 'AFN',
    minimumFractionDigits: 2,
  }).format(amount).replace('AFN', '؋')
}

const printSalarySlip = async () => {
  if (!payslipContainerRef.value) return
  await printDocument({ bodyClass: 'printing-payslip-document', pageStyle: 'size: A5; margin: 0;', element: payslipContainerRef.value })
}

defineExpose({
  print: printSalarySlip,
})
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
    <!-- Corporate Header -->
    <header class="payslip-header">
      <div class="company-info">
        <div class="brand-wrapper">
          <img
            v-if="company.logo"
            :src="company.logo"
            :alt="company.name"
            class="company-logo"
          >
          <h1 class="company-name">
            {{ company.name }}
          </h1>
          <span class="brand-badge">{{ company.brandName }}</span>
        </div>
        <div
          v-if="company.addressLines && company.addressLines.length"
          class="company-address"
        >
          <p
            v-for="(line, idx) in company.addressLines"
            :key="idx"
          >
            {{ line }}
          </p>
        </div>
        <p class="company-contact">
          <bdi>{{ company.email }}</bdi> · <bdi>{{ company.phone }}</bdi>
        </p>
        <p
          v-if="company.tinNumber"
          class="company-tin"
        >
          {{ $t('documents.payslip_template.payslipTemplate.mofTINRegNo') }} <strong>{{ company.tinNumber }}</strong>
        </p>
      </div>
      <div class="document-title">
        <h2>{{ $t('documents.payslip_template.payslipTemplate.officialPAYSLIP') }}</h2>
        <p class="arabic-title">
          شقه معاش رسمی / رسمی د معاش شقه
        </p>
        <div class="doc-meta">
          <p><strong>{{ $t('documents.payslip_template.payslipTemplate.docNo') }}</strong> {{ documentNumber || `PAY-${employee.payPeriod}` }}</p>
          <p><strong>{{ $t('documents.payslip_template.payslipTemplate.payPeriod') }}</strong> {{ employee.payPeriod }}</p>
          <p><strong>{{ $t('documents.payslip_template.payslipTemplate.payDate') }}</strong> {{ formatLocalizedDate(employee.payDate) }}</p>
        </div>
      </div>
    </header>

    <!-- Employee Pay Summary Card -->
    <section class="employee-card">
      <div class="grid-item">
        <span class="label">{{ $t('documents.payslip_template.payslipTemplate.employeeName') }}</span>
        <span class="value font-bold">{{ employee.name }}</span>
      </div>
      <div class="grid-item">
        <span class="label">{{ $t('documents.payslip_template.payslipTemplate.designation') }}</span>
        <span class="value">{{ employee.designation }}</span>
      </div>
      <div class="grid-item">
        <span class="label">{{ $t('common.fields.department') }}</span>
        <span class="value">{{ employee.department || 'General Staff' }}</span>
      </div>
      <div class="grid-item">
        <span class="label">{{ $t('documents.payslip_template.payslipTemplate.dateOfJoining') }}</span>
        <span class="value">{{ formatLocalizedDate(employee.dateOfJoining) }}</span>
      </div>
      <div class="grid-item">
        <span class="label">{{ $t('documents.payslip_template.payslipTemplate.tazkiraNationalID') }}</span>
        <span class="value font-mono">{{ employee.tazkiraNumber || 'N/A' }}</span>
      </div>
      <div class="grid-item">
        <span class="label">{{ $t('documents.payslip_template.payslipTemplate.employeeMoFTIN') }}</span>
        <span class="value font-mono">{{ employee.tinNumber || 'N/A' }}</span>
      </div>
      <div class="grid-item">
        <span class="label">{{ $t('documents.payslip_template.payslipTemplate.bankAccountNo') }}</span>
        <span class="value font-mono">{{ employee.accountNumber }}</span>
      </div>
      <div class="grid-item highlight-box">
        <span class="label text-primary">{{ $t('documents.payslip_template.payslipTemplate.workedTotalDays') }}</span>
        <span class="value font-bold text-primary">{{ $t('documents.payslip.workedDays', { worked: employee.workedDays, total: employee.totalDays || 30 }) }}</span>
      </div>
      <div class="grid-item">
        <span class="label">{{ $t('common.statuses.paidLeave') }}</span><span class="value">{{ employee.paidLeaveDays }}</span>
      </div>
      <div class="grid-item">
        <span class="label">{{ $t('components.dataTablePageKpis.absentDays') }}</span><span class="value">{{ employee.absentDays }}</span>
      </div>
      <div class="grid-item">
        <span class="label">{{ $t('documents.timesheet.statuses.unpaid-leave') }}</span><span class="value">{{ employee.unpaidLeaveDays }}</span>
      </div>
    </section>

    <!-- Earnings and Deductions Table -->
    <section class="table-section">
      <h3 class="section-title">
        {{ $t('documents.payslip_template.payslipTemplate.earningsDeductionsSummary') }}
      </h3>
      <table class="payslip-table">
        <thead>
          <tr>
            <th style="width: 35%">
              {{ $t('documents.payslip_template.payslipTemplate.earningsDESCRIPTION') }}
            </th>
            <th
              style="width: 15%"
              class="text-right"
            >
              {{ $t('common.fields.amount') }}
            </th>
            <th style="width: 35%">
              {{ $t('documents.payslip_template.payslipTemplate.deductionsDESCRIPTION') }}
            </th>
            <th
              style="width: 15%"
              class="text-right"
            >
              {{ $t('common.fields.amount') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="index in Math.max(earnings.length, deductions.length)"
            :key="index"
          >
            <td>{{ earnings[index - 1]?.label || '' }}</td>
            <td class="text-right font-mono">
              {{ earnings[index - 1] ? formatCurrency(earnings[index - 1].amount) : '' }}
            </td>
            <td>{{ deductions[index - 1]?.label || '' }}</td>
            <td class="text-right font-mono">
              {{ deductions[index - 1] ? formatCurrency(deductions[index - 1].amount)
                : '' }}
            </td>
          </tr>
          <tr class="total-row">
            <td>{{ $t('documents.payslip_template.payslipTemplate.grossEarnings') }}</td>
            <td class="text-right font-mono">
              {{ formatCurrency(grossSalary) }}
            </td>
            <td>{{ $t('documents.payslip_template.payslipTemplate.totalDeductions') }}</td>
            <td class="text-right font-mono">
              {{ formatCurrency(totalDeductions) }}
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- Reimbursements Table -->
    <section
      v-if="reimbursements.length > 0"
      class="table-section"
    >
      <h3 class="section-title">
        {{ $t('documents.payslip_template.payslipTemplate.reimbursementsAdditions') }}
      </h3>
      <table class="payslip-table">
        <thead>
          <tr>
            <th>{{ $t('common.fields.description') }}</th>
            <th
              class="text-right"
              style="width: 20%"
            >
              {{ $t('common.fields.amount') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="item in reimbursements"
            :key="item.label"
          >
            <td>{{ item.label }}</td>
            <td class="text-right font-mono">
              {{ formatCurrency(item.amount) }}
            </td>
          </tr>
          <tr class="total-row">
            <td>{{ $t('documents.payslip_template.payslipTemplate.totalReimbursements') }}</td>
            <td class="text-right font-mono">
              {{ formatCurrency(totalReimbursements) }}
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- Final Net Payable Banner -->
    <section class="net-pay-banner">
      <div class="net-pay-info">
        <span class="net-label">{{ $t('documents.payslip.totalNetPayable', { currency }) }}</span>
        <span
          v-if="amountInWords"
          class="net-words"
        >{{ amountInWords }}</span>
      </div>
      <div class="net-amount font-mono">
        {{ formatCurrency(totalNetPayable) }}
      </div>
    </section>

    <!-- Signatures Section -->
    <footer class="signatures">
      <div class="signature-box">
        <div class="signature-line" />
        <span>{{ $t('documents.payslip_template.payslipTemplate.employeeSignature') }}</span>
      </div>
      <div class="signature-box">
        <div class="signature-line" />
        <span>{{ $t('documents.payslip_template.payslipTemplate.hrPayrollOfficer') }}</span>
      </div>
      <div class="signature-box">
        <img
          v-if="company.signature"
          :src="company.signature"
          :alt="$t('documents.payslip_template.payslipTemplate.companySignature')"
          class="company-signature"
        >
        <div class="signature-line" />
        <span>{{ $t('documents.payslip_template.payslipTemplate.companySignature') }}</span>
      </div>
    </footer>

    <!-- Compliance Note -->
    <div class="compliance-footer">
      <p>{{ $t('documents.payslip_template.payslipTemplate.thisDocumentIsGeneratedInComplianceWithTheAfghanistanLaborLawAndMinist') }}</p>
    </div>
    </div>
  </div>
</template>

<style scoped>
/* Web Component Preview Styles - Constrained to 680px to match exactly 180mm printable A4 width */
.payslip-container {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  color: #1e293b;
  padding: 24px;
  max-width: 680px;
  width: 100%;
  margin: 0 auto;
  box-sizing: border-box;
  position: relative;
  background-size: 100% 100%;
  background-position: center;
  background-repeat: no-repeat;
}

/* With a letterhead the safe-zone owns all spacing, so the built-in padding is dropped. */
.payslip-container--letterhead {
  padding: 0;
}

.payslip-content {
  display: flex;
  min-height: 100%;
  flex-direction: column;
}

.payslip-content--safe {
  position: absolute;
  padding: 5mm;
  overflow: hidden;
}

.payslip-container.persian-document {
  font-family: Vazirmatn, 'Public Sans', sans-serif;
}

.payslip-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  border-bottom: 2px solid #1e293b;
  padding-bottom: 12px;
  margin-bottom: 16px;
}

.brand-wrapper {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.company-logo {
  width: 52px;
  height: 52px;
  object-fit: contain;
  flex: 0 0 auto;
}

.company-name {
  font-size: 20px;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.brand-badge {
  background-color: #f1f5f9;
  color: #334155;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 3px;
  text-transform: uppercase;
}

.company-address p,
.company-contact,
.company-tin {
  font-size: 13px;
  color: #475569;
  margin: 2px 0;
}

.document-title {
  text-align: right;
}

.document-title h2 {
  font-size: 18px;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.arabic-title {
  font-size: 12px;
  color: #64748b;
  direction: rtl;
  margin: 2px 0 6px 0;
  font-weight: 600;
}

.doc-meta p {
  font-size: 13px;
  color: #334155;
  margin: 2px 0;
}

.employee-card {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  background-color: #f8fafc;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  padding: 14px;
  margin-bottom: 16px;
}

.grid-item {
  display: flex;
  flex-direction: column;
}

.label {
  font-size: 11px;
  text-transform: uppercase;
  color: #64748b;
  font-weight: 700;
  margin-bottom: 2px;
}

.value {
  font-size: 13px;
  color: #0f172a;
  word-break: break-word;
}

.font-bold {
  font-weight: 700;
}

.font-mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.highlight-box {
  background-color: #eff6ff;
  padding: 4px 6px;
  border-radius: 3px;
  border: 1px solid #bfdbfe;
}

.text-primary {
  color: #1d4ed8 !important;
}

.table-section {
  margin-bottom: 16px;
}

.section-title {
  font-size: 13px;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 6px;
  text-transform: uppercase;
}

.payslip-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
}

.payslip-table th,
.payslip-table td {
  border: 1px solid #cbd5e1;
  padding: 8px 10px;
  text-align: left;
  word-wrap: break-word;
}

.payslip-table th {
  background-color: #f1f5f9;
  font-size: 11px;
  font-weight: 700;
  color: #334155;
}

.payslip-table td {
  font-size: 13px;
}

.text-right {
  text-align: right !important;
}

.total-row {
  font-weight: 700;
  background-color: #f8fafc;
}

.net-pay-banner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: #eff6ff;
  border: 1px solid #93c5fd;
  border-inline-start: 5px solid #1d4ed8;
  border-radius: 4px;
  padding: 14px 18px;
  margin-top: 16px;
  margin-bottom: 40px;
}

.net-pay-info {
  display: flex;
  flex-direction: column;
  max-width: 65%;
}

.net-label {
  font-size: 12px;
  font-weight: 700;
  color: #1e40af;
}

.net-words {
  font-size: 13px;
  color: #2563eb;
  font-style: italic;
  margin-top: 2px;
}

.net-amount {
  font-size: 20px;
  font-weight: 800;
  color: #1e3a8a;
}

.signatures {
  display: flex;
  justify-content: space-between;
  margin-top: 50px;
  margin-bottom: 20px;
}

.signature-box {
  width: 180px;
  text-align: center;
}

.signature-line {
  border-top: 1px solid #475569;
  margin-bottom: 6px;
}

.company-signature {
  display: block;
  max-width: 140px;
  max-height: 55px;
  object-fit: contain;
  margin: 0 auto 6px;
}

.signature-box span {
  font-size: 11px;
  color: #475569;
  font-weight: 600;
  text-transform: uppercase;
}

.compliance-footer {
  border-top: 1px dashed #cbd5e1;
  padding-top: 10px;
  text-align: center;
}

.compliance-footer p {
  font-size: 11px;
  color: #64748b;
}

[dir='rtl'] .document-title,
[dir='rtl'] .text-right {
  text-align: left !important;
}
</style>
