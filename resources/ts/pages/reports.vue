<script setup lang="ts">
import { printDocument } from '@/utils/printDocument'
import { ref } from 'vue'

// 1. Interfaces Schema Definitions
interface FinancialSummary {
  totalInvoiced: number
  totalExpenses: number
  netProfit: number
  activeUsersCount: number
  totalStaffOnboarded: number
}

interface ExpenseCategoryShare {
  name: string
  amount: number
  percentage: number
  color: string
}

interface PerformanceMetric {
  department: string
  allocatedBudget: number
  spentBudget: number
  efficiencyScore: number // percentage
}

// 2. Mock Core Analytics Data (Reflective of past features)
const analyticsSummary = ref<FinancialSummary>({
  totalInvoiced: 48950.00,
  totalExpenses: 14210.50,
  netProfit: 34739.50,
  activeUsersCount: 142,
  totalStaffOnboarded: 24,
})

const expenseDistribution = ref<ExpenseCategoryShare[]>([
  { name: 'Marketing & Ads', amount: 5400.00, percentage: 38, color: 'primary' },
  { name: 'Software / SaaS Licenses', amount: 3200.00, percentage: 22, color: 'info' },
  { name: 'Corporate HR Salaries', amount: 4100.50, percentage: 29, color: 'success' },
  { name: 'Office Utilities & Supplies', amount: 1510.00, percentage: 11, color: 'warning' },
])

const departmentPerformance = ref<PerformanceMetric[]>([
  { department: 'Engineering (Frontend/Backend)', allocatedBudget: 25000, spentBudget: 22400, efficiencyScore: 94 },
  { department: 'UI/UX Design Studios', allocatedBudget: 8000, spentBudget: 7900, efficiencyScore: 88 },
  { department: 'Marketing & Public Growth', allocatedBudget: 12000, spentBudget: 11200, efficiencyScore: 91 },
  { department: 'HR & Operations Admin', allocatedBudget: 5000, spentBudget: 4200, efficiencyScore: 96 },
])

// 3. States & Date Filters
const selectedQuarter = ref('Q3-2026')
const activePrintReport = ref(false)

const quartersList = [
  { title: 'Q1 (Jan - Mar 2026)', value: 'Q1-2026' },
  { title: 'Q2 (Apr - Jun 2026)', value: 'Q2-2026' },
  { title: 'Q3 (Jul - Sep 2026)', value: 'Q3-2026' },
]

// 4. Print Executive Report Routine
const triggerPrintReport = () => {
  void printDocument({
    bodyClass: 'printing-reports',
    pageStyle: 'size: letter portrait; margin: 15mm;',
    beforePrint: () => { activePrintReport.value = true },
    afterPrint: () => { activePrintReport.value = false },
  })
}
</script>

<template>
  <div class="reports-dashboard-wrapper">

    <div class="d-flex justify-between align-center mb-6 screen-only">
      <div>
        <h3 class="text-h4 font-weight-medium">Analytics & Performance Intelligence</h3>
        <p class="text-subtitle-2 text-muted mb-0">Cross-reference financial operations, human resource footprints, and
          ledger distribution.</p>
      </div>

      <div class="d-flex gap-3 align-center" style="margin-left: auto">
        <VSelect v-model="selectedQuarter" :items="quartersList" density="compact" hide-details style="width: 220px;"
          variant="solo" />
        <VSelect v-model="selectedQuarter" :items="quartersList" density="compact" hide-details style="width: 220px;"
          variant="solo" />
        <VBtn color="primary" @click="triggerPrintReport">
          <VIcon icon="tabler-printer" class="me-1" /> Print Report Statement
        </VBtn>
      </div>
    </div>

    <VRow class="mb-6 screen-only">
      <VCol cols="12" sm="6" md="3">
        <VCard class="p-2 border-start border-primary border-lg">
          <VCardText class="d-flex justify-between align-center">
            <div>
              <div class="text-xs text-muted uppercase font-weight-bold">Total Gross Revenue</div>
              <h3 class="text-h5 font-weight-black mt-1">${{ analyticsSummary.totalInvoiced.toLocaleString() }}</h3>
            </div>
            <VAvatar color="primary" variant="tonal" style="margin-left: auto" rounded size="42">
              <VIcon icon="tabler-file-invoice" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard class="p-2 border-start border-error border-lg">
          <VCardText class="d-flex justify-between align-center">
            <div>
              <div class="text-xs text-muted uppercase font-weight-bold">Total Expenses</div>
              <h3 class="text-h5 font-weight-black mt-1 text-error">${{ analyticsSummary.totalExpenses.toLocaleString()
              }}</h3>
            </div>
            <VAvatar color="error" variant="tonal" style="margin-left: auto" rounded size="42">
              <VIcon icon="tabler-receipt" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard class="p-2 border-start border-success border-lg">
          <VCardText class="d-flex justify-between align-center">
            <div>
              <div class="text-xs text-muted uppercase font-weight-bold">Net Profit Margin</div>
              <h3 class="text-h5 font-weight-black mt-1 text-success">${{ analyticsSummary.netProfit.toLocaleString() }}
              </h3>
            </div>
            <VAvatar color="success" variant="tonal" style="margin-left: auto" rounded size="42">
              <VIcon icon="tabler-chart-pie" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard class="p-2 border-start border-info border-lg">
          <VCardText class="d-flex justify-between align-center">
            <div>
              <div class="text-xs text-muted uppercase font-weight-bold">Onboarded Directory Size</div>
              <h3 class="text-h5 font-weight-black mt-1 text-info">{{ analyticsSummary.activeUsersCount }} Users / {{
                analyticsSummary.totalStaffOnboarded }} Staff</h3>
            </div>
            <VAvatar color="info" variant="tonal" style="margin-left: auto" rounded size="42">
              <VIcon icon="tabler-users" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VRow class="mb-6 screen-only">
      <VCol cols="12" md="5">
        <VCard title="Expenditure Ledger Categorization" h-100 class="h-100">
          <VCardText class="pt-2">
            <p class="text-caption text-muted mb-4">Live percentage distribution of cash flows across categorized
              transactional operational units.</p>

            <div class="d-flex flex-column gap-5">
              <div v-for="(category, index) in expenseDistribution" :key="index">
                <div class="d-flex justify-between align-center text-body-2 mb-1">
                  <span class="font-weight-medium text-high-emphasis">{{ category.name }}</span>
                  <span class="font-weight-bold text-secondary" style="margin-left: auto">${{
                    category.amount.toLocaleString() }} ({{
                      category.percentage }}%)</span>
                </div>
                <VProgressLinear :model-value="category.percentage" :color="category.color" height="8" rounded />
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" md="7">
        <VCard title="Departmental Operational Efficiency Tracking Matrix" class="h-100">
          <VCardText>
            <VTable class="text-no-wrap">
              <thead>
                <tr>
                  <th>Functional Core Department</th>
                  <th>Allocated Cap</th>
                  <th>Actual Outflows</th>
                  <th class="text-end">Performance Index</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(dept, index) in departmentPerformance" :key="index">
                  <td class="font-weight-semibold">{{ dept.department }}</td>
                  <td>${{ dept.allocatedBudget.toLocaleString() }}</td>
                  <td class="text-secondary">${{ dept.spentBudget.toLocaleString() }}</td>
                  <td class="text-end">
                    <VChip size="small" :color="dept.efficiencyScore >= 92 ? 'success' : 'warning'" variant="tonal"
                      class="font-weight-black">
                      {{ dept.efficiencyScore }}% Index
                    </VChip>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>


    <div v-if="activePrintReport" class="a4-print-report-canvas">
      <div class="print-report-header">
        <div>
          <h1 class="print-corporate-brand">ASANTECH OPERATIONAL SYSTEM REPORT</h1>
          <div class="print-report-sub-meta">Dashboard Intelligence Export Ledger Auditing Statement</div>
        </div>
        <div class="text-right font-mono text-xs">
          <div>Scope: {{ selectedQuarter }} Audit</div>
          <div>Generated Timestamp: 2026-07-08</div>
        </div>
      </div>

      <hr class="print-report-divider" />

      <h3 class="print-section-title-label">1. FISCAL & DIRECTORY SUMMARY BALANCES</h3>
      <table class="print-summary-table">
        <thead>
          <tr>
            <th>Financial Statement Element</th>
            <th class="text-right">Ledger Accounting Balance Value</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Total Aggregated Invoiced Revenue Volume</td>
            <td class="text-right font-weight-bold">${{ analyticsSummary.totalInvoiced.toFixed(2) }}</td>
          </tr>
          <tr>
            <td>Total Registered Operational Expenditure Outflows</td>
            <td class="text-right font-weight-bold text-error">${{ analyticsSummary.totalExpenses.toFixed(2) }}</td>
          </tr>
          <tr class="highlighted-profit-row">
            <td><strong>NET BALANCED RETAINED EARNINGS OPERATIONAL PROFIT</strong></td>
            <td class="text-right font-weight-black text-success">${{ analyticsSummary.netProfit.toFixed(2) }}</td>
          </tr>
          <tr>
            <td>Active Connected Application System Directory User Count</td>
            <td class="text-right">{{ analyticsSummary.activeUsersCount }} Registered Users</td>
          </tr>
          <tr>
            <td>Onboarded Corporate Workforce Personnel Record Count</td>
            <td class="text-right">{{ analyticsSummary.totalStaffOnboarded }} Active Staff Members</td>
          </tr>
        </tbody>
      </table>

      <h3 class="print-section-title-label">2. EXPENDITURE CAPITAL ACCOUNT ALLOCATIONS</h3>
      <table class="print-summary-table">
        <thead>
          <tr>
            <th>Expense Category Channel Description</th>
            <th class="text-center">Percent Ratio Share</th>
            <th class="text-right">Settled Amount</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, idx) in expenseDistribution" :key="idx">
            <td>{{ item.name }} Allocation Resource Provision</td>
            <td class="text-center">{{ item.percentage }}%</td>
            <td class="text-right">${{ item.amount.toFixed(2) }}</td>
          </tr>
        </tbody>
      </table>

      <h3 class="print-section-title-label">3. OPERATIONAL PERFORMANCE & AUDITING METRICS</h3>
      <table class="print-summary-table">
        <thead>
          <tr>
            <th>Audited Department Division</th>
            <th class="text-right">Allocated Budget Cap</th>
            <th class="text-right">Actual Spent Outflows</th>
            <th class="text-right">Efficiency Utilization Score</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(dept, idx) in departmentPerformance" :key="idx">
            <td>{{ dept.department }}</td>
            <td class="text-right">${{ dept.allocatedBudget.toLocaleString() }}.00</td>
            <td class="text-right">${{ dept.spentBudget.toLocaleString() }}.00</td>
            <td class="text-right font-weight-bold">{{ dept.efficiencyScore }}% Performance Rating</td>
          </tr>
        </tbody>
      </table>

      <div class="print-report-attestation-block">
        <div class="print-attestation-line-row">
          <div class="attestation-signature-box">
            <div class="attestation-blank-line"></div>
            <div>Chief Financial Comptroller Endorsement</div>
            <div class="text-muted text-xs">Internal Security Financial Audit Approval Signee</div>
          </div>
          <div class="attestation-signature-box">
            <div class="attestation-blank-line"></div>
            <div>System Operational Audit Director</div>
            <div class="text-muted text-xs">Infrastructure Operations Verification Endorsement Block</div>
          </div>
        </div>
      </div>

      <div class="print-report-footer-notice" style="padding-bottom: 6px">
        CONFIDENTIAL DATA SYSTEM REPORT EXPORT STATEMENT. FOR INTERNAL CORPORATE AUDITING PURPOSES ONLY.
      </div>
    </div>

  </div>
</template>

<style scoped>
.border-lg {
  border-inline-start-width: 6px !important;
}

.gap-5 {
  gap: 20px;
}

.gap-3 {
  gap: 12px;
}

.text-right {
  text-align: right;
}

.text-center {
  text-align: center;
}
</style>

<style>
@media print {

  /* Aggressively hide all Vuexy theme application structural layout shell frame tags */
  body.printing-reports .screen-only,
  body.printing-reports .layout-navbar,
  body.printing-reports .layout-navbar-and-nav-container,
  body.printing-reports .layout-horizontal-nav,
  body.printing-reports .v-navigation-drawer,
  body.printing-reports .v-app-bar,
  body.printing-reports .layout-footer,
  body.printing-reports .v-card,
  body.printing-reports .v-btn,
  body.printing-reports .v-dialog,
  body.printing-reports .v-overlay-container {
    display: none !important;
    opacity: 0 !important;
    visibility: hidden !important;
    height: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
  }

  /* Force viewport padding resets onto main component views containers */
  body.printing-reports .layout-page-content,
  body.printing-reports .v-main,
  body.printing-reports .layout-wrapper,
  body.printing-reports .layout-content-wrapper {
    /* padding: 0 !important;
    margin: 0 !important; */
    max-width: 100% !important;
    width: 100% !important;
  }

  body.printing-reports .a4-print-report-canvas {
    display: block !important;
    width: 100% !important;
    background: #ffffff !important;
    color: #000000 !important;
    font-family: Arial, sans-serif;
    line-height: 1.4;
  }
}

/* Hidden during normal display monitor dashboard usage */
.a4-print-report-canvas {
  display: none;
}

/* Structural Attestation Presentation formatting rules rules mapping items */
.print-corporate-brand {
  font-size: 20px;
  font-weight: 900;
  letter-spacing: -0.5px;
  color: #000000;
}

.print-report-sub-meta {
  font-size: 11px;
  color: #555555;
  text-transform: uppercase;
  font-weight: 600;
}

.print-report-divider {
  border: 0;
  border-top: 3px double #000000;
  margin: 15px 0;
}

.print-section-title-label {
  font-size: 12px;
  font-weight: bold;
  background: #f0f2f5;
  padding: 4px 8px;
  border-left: 4px solid #000000;
  margin-top: 25px;
  margin-bottom: 12px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.print-summary-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 20px;
  font-size: 12px;
}

.print-summary-table th {
  text-transform: uppercase;
  font-weight: bold;
  border-bottom: 2px solid #000000;
  padding: 6px;
  text-align: left;
  background: transparent;
  color: #000000;
}

.print-summary-table td {
  padding: 8px 6px;
  border-bottom: 1px solid #e2e8f0;
  color: #000000;
}

.highlighted-profit-row {
  background-color: #f8fafc;
  font-size: 13px;
  border-bottom: 2px solid #000000 !important;
  border-top: 1px solid #000000 !important;
}

.print-report-attestation-block {
  margin-top: 50px;
  page-break-inside: avoid;
}

.print-attestation-line-row {
  display: flex;
  justify-content: space-between;
  gap: 50px;
}

.attestation-signature-box {
  width: 45%;
  font-size: 11px;
  font-weight: bold;
}

.attestation-blank-line {
  border-top: 1px solid #000000;
  margin-bottom: 6px;
  margin-top: 40px;
}

.print-report-footer-notice {
  position: fixed;
  bottom: 0;
  width: 100%;
  text-align: center;
  font-size: 9px;
  color: #718096;
  border-top: 1px dashed #cbd5e1;
  padding-top: 4px;
  font-weight: bold;
}
</style>
