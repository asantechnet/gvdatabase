<template>
  <div class="payslip-container" ref="payslipContainerRef">
    <!-- Corporate Header -->
    <header class="payslip-header">
      <div class="company-info">
        <div class="brand-wrapper">
          <h1 class="company-name">{{ company.name }}</h1>
          <span class="brand-badge">{{ company.brandName }}</span>
        </div>
        <div class="company-address" v-if="company.addressLines && company.addressLines.length">
          <p v-for="(line, idx) in company.addressLines" :key="idx">{{ line }}</p>
        </div>
        <p class="company-contact">{{ company.email }} &bull; {{ company.phone }}</p>
        <p class="company-tin" v-if="company.tinNumber">MoF TIN Reg No: <strong>{{ company.tinNumber }}</strong></p>
      </div>
      <div class="document-title">
        <h2>OFFICIAL PAYSLIP</h2>
        <p class="arabic-title">شقه معاش رسمی / رسمی د معاش شقه</p>
        <div class="doc-meta">
          <p><strong>Doc No:</strong> {{ documentNumber || 'PAY-' + employee.payPeriod }}</p>
          <p><strong>Pay Period:</strong> {{ employee.payPeriod }}</p>
          <p><strong>Pay Date:</strong> {{ employee.payDate }}</p>
        </div>
      </div>
    </header>

    <!-- Employee Pay Summary Card -->
    <section class="employee-card">
      <div class="grid-item">
        <span class="label">Employee Name</span>
        <span class="value font-bold">{{ employee.name }}</span>
      </div>
      <div class="grid-item">
        <span class="label">Designation</span>
        <span class="value">{{ employee.designation }}</span>
      </div>
      <div class="grid-item">
        <span class="label">Department</span>
        <span class="value">{{ employee.department || 'General Staff' }}</span>
      </div>
      <div class="grid-item">
        <span class="label">Date of Joining</span>
        <span class="value">{{ employee.dateOfJoining }}</span>
      </div>
      <div class="grid-item">
        <span class="label">Tazkira / National ID</span>
        <span class="value font-mono">{{ employee.tazkiraNumber || 'N/A' }}</span>
      </div>
      <div class="grid-item">
        <span class="label">Employee MoF TIN</span>
        <span class="value font-mono">{{ employee.tinNumber || 'N/A' }}</span>
      </div>
      <div class="grid-item">
        <span class="label">Bank Account No</span>
        <span class="value font-mono">{{ employee.accountNumber }}</span>
      </div>
      <div class="grid-item highlight-box">
        <span class="label text-primary">Worked / Total Days</span>
        <span class="value font-bold text-primary">{{ employee.workedDays }} / {{ employee.totalDays || 30 }}
          Days</span>
      </div>
    </section>

    <!-- Earnings and Deductions Table -->
    <section class="table-section">
      <h3 class="section-title">Earnings & Deductions Summary</h3>
      <table class="payslip-table">
        <thead>
          <tr>
            <th style="width: 35%">EARNINGS DESCRIPTION</th>
            <th style="width: 15%" class="text-right">AMOUNT</th>
            <th style="width: 35%">DEDUCTIONS DESCRIPTION</th>
            <th style="width: 15%" class="text-right">AMOUNT</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="index in Math.max(earnings.length, deductions.length)" :key="index">
            <td>{{ earnings[index - 1]?.label || '' }}</td>
            <td class="text-right font-mono">{{ earnings[index - 1] ? formatCurrency(earnings[index - 1].amount) : '' }}
            </td>
            <td>{{ deductions[index - 1]?.label || '' }}</td>
            <td class="text-right font-mono">{{ deductions[index - 1] ? formatCurrency(deductions[index - 1].amount) :
              '' }}</td>
          </tr>
          <tr class="total-row">
            <td>Gross Earnings</td>
            <td class="text-right font-mono">{{ formatCurrency(grossSalary) }}</td>
            <td>Total Deductions</td>
            <td class="text-right font-mono">{{ formatCurrency(totalDeductions) }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- Reimbursements Table -->
    <section class="table-section" v-if="reimbursements.length > 0">
      <h3 class="section-title">Reimbursements & Additions</h3>
      <table class="payslip-table">
        <thead>
          <tr>
            <th>DESCRIPTION</th>
            <th class="text-right" style="width: 20%">AMOUNT</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in reimbursements" :key="item.label">
            <td>{{ item.label }}</td>
            <td class="text-right font-mono">{{ formatCurrency(item.amount) }}</td>
          </tr>
          <tr class="total-row">
            <td>Total Reimbursements</td>
            <td class="text-right font-mono">{{ formatCurrency(totalReimbursements) }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- Final Net Payable Banner -->
    <section class="net-pay-banner">
      <div class="net-pay-info">
        <span class="net-label">TOTAL NET PAYABLE (AFN)</span>
        <span class="net-words" v-if="amountInWords">{{ amountInWords }}</span>
      </div>
      <div class="net-amount font-mono">
        {{ formatCurrency(totalNetPayable) }}
      </div>
    </section>

    <!-- Signatures Section -->
    <footer class="signatures">
      <div class="signature-box">
        <div class="signature-line"></div>
        <span>Employee Signature</span>
      </div>
      <div class="signature-box">
        <div class="signature-line"></div>
        <span>HR / Payroll Officer</span>
      </div>
      <div class="signature-box">
        <div class="signature-line"></div>
        <span>Finance Director Approval</span>
      </div>
    </footer>

    <!-- Compliance Note -->
    <div class="compliance-footer">
      <p>This document is generated in compliance with the Afghanistan Labor Law and Ministry of Finance (MoF) Wage
        Withholding Tax Regulations (Article 46). Valid without physical seal if digitally verified by HR.</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

export interface Company {
  name: string
  brandName: string
  addressLines: string[]
  email: string
  phone: string
  tinNumber?: string
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
  amountInWords: string
}

const props = withDefaults(defineProps<SalarySlipProps>(), {
  documentTitle: 'OFFICIAL PAYSLIP',
  currency: 'AFN',
  deductions: () => [],
  reimbursements: () => []
})

const payslipContainerRef = ref<HTMLElement | null>(null)

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
    minimumFractionDigits: 2
  }).format(amount).replace('AFN', '؋')
}

const printSalarySlip = () => {
  if (!payslipContainerRef.value) return

  const printWindow = window.open('', '_blank')
  if (!printWindow) {
    alert('Please allow popups in your browser to print the payslip.')
    return
  }

  // Self-contained A4 Print Stylesheet with 180mm printable width protection
  const htmlContent = `
    <!DOCTYPE html>
    <html>
      <head>
        <title>Payslip - ${props.employee.name} - ${props.employee.payPeriod}</title>
        <style>
          @page {
            size: A4 portrait;
            margin: 15mm;
          }
          * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
          }
          body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            line-height: 1.4;
            background: #ffffff;
            font-size: 9.5pt;
            -webkit-font-smoothing: antialiased;
            width: 100%;
            padding: 20px;
          }
          .payslip-container {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
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
          .company-name {
            font-size: 16pt;
            font-weight: 800;
            color: #0f172a;
          }
          .brand-badge {
            background-color: #f1f5f9 !important;
            color: #334155;
            font-size: 7.5pt;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 3px;
            text-transform: uppercase;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          .company-address p, .company-contact, .company-tin {
            font-size: 8.5pt;
            color: #475569;
            margin: 2px 0;
          }
          .document-title {
            text-align: right;
          }
          .document-title h2 {
            font-size: 15pt;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 0.5px;
          }
          .arabic-title {
            font-size: 9pt;
            color: #64748b;
            direction: rtl;
            margin: 2px 0 6px 0;
            font-weight: 600;
          }
          .doc-meta p {
            font-size: 8.5pt;
            color: #334155;
            margin: 2px 0;
          }
          .employee-card {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            background-color: #f8fafc !important;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 12px;
            margin-bottom: 16px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          .grid-item {
            display: flex;
            flex-direction: column;
          }
          .label {
            font-size: 7pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 700;
            margin-bottom: 2px;
          }
          .value {
            font-size: 9pt;
            color: #0f172a;
            word-break: break-word;
          }
          .font-bold { font-weight: 700; }
          .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
          .highlight-box {
            background-color: #eff6ff !important;
            padding: 4px 6px;
            border-radius: 3px;
            border: 1px solid #bfdbfe;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          .text-primary { color: #1d4ed8 !important; }
          
          .table-section {
            margin-bottom: 16px;
          }
          .section-title {
            font-size: 10pt;
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
            padding: 6px 8px;
            text-align: left;
            word-wrap: break-word;
          }
          .payslip-table th {
            background-color: #f1f5f9 !important;
            font-size: 7.5pt;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          .payslip-table td {
            font-size: 8.5pt;
          }
          .text-right { text-align: right !important; }
          
          .total-row {
            font-weight: 700;
            background-color: #f8fafc !important;
            color: #0f172a;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          .net-pay-banner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #eff6ff !important;
            border: 1px solid #93c5fd;
            border-left: 5px solid #1d4ed8;
            border-radius: 4px;
            padding: 12px 16px;
            margin-top: 16px;
            margin-bottom: 40px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
          .net-pay-info {
            display: flex;
            flex-direction: column;
            max-width: 65%;
          }
          .net-label {
            font-size: 8.5pt;
            font-weight: 700;
            color: #1e40af;
            letter-spacing: 0.5px;
          }
          .net-words {
            font-size: 8.5pt;
            color: #2563eb;
            font-style: italic;
            margin-top: 2px;
          }
          .net-amount {
            font-size: 15pt;
            font-weight: 800;
            color: #1e3a8a;
          }
          .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: auto;
            page-break-inside: avoid;
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
          .signature-box span {
            font-size: 8pt;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
          }
          .compliance-footer {
            border-top: 1px dashed #cbd5e1;
            padding-top: 8px;
            text-align: center;
          }
          .compliance-footer p {
            font-size: 7.5pt;
            color: #64748b;
          }
        </style>
      </head>
      <body>
        ${payslipContainerRef.value.outerHTML}
      </body>
    </html>
  `

  printWindow.document.write(htmlContent)
  printWindow.document.close()
  printWindow.focus()

  setTimeout(() => {
    printWindow.print()
    printWindow.close()
  }, 250)
}

defineExpose({
  print: printSalarySlip
})
</script>

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
  border-left: 5px solid #1d4ed8;
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
</style>
