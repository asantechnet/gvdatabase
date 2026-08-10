<script setup lang="ts">
import { computed, nextTick, ref, onMounted, watch } from 'vue'
import { VDialog } from 'vuetify/components'
import PayslipTemplate, { type SalarySlipProps } from './templates/payslip_template.vue'
import SalaryApi, { type Salary, type CreateSalaryData, type UpdateSalaryData } from '@/api/salary'
import { type WorthyEmployee } from '@/api/bonuslist'
import EmployeeApi, { type Employee } from '@/api/employee'
import CurrencyApi, { type Currency } from '@/api/currency'

interface FormError {
  field: string
  message: string
}

const salaries = ref<Salary[]>([])
const employees = ref<Employee[]>([])
const currencies = ref<Currency[]>([])
const grantedBonuses = ref<WorthyEmployee[]>([])
const isLoadingBonuses = ref(false)

const isAddSalaryDialogVisible = ref(false)
const isEditSalaryDialogVisible = ref(false)
const isDeleteSalaryDialogVisible = ref(false)
const selectedSalary = ref<Salary | null>(null)
const searchQuery = ref('')
const salaryMonthFilter = ref<string | null>(null)
const selectedSalaryAmountRange = ref<string | null>(null)
const isLoading = ref(false)

// Payslip Printing Reference & State
const payslipRef = ref<InstanceType<typeof PayslipTemplate> | null>(null)
const activePayslipProps = ref<SalarySlipProps | null>(null)

const salaryForm = ref({
  id: null as number | null,
  employee_id: null as number | null,
  employeeName: '',
  salaryMonth: new Date().toISOString().slice(0, 7),
  currency_id: null as number | null,
  amountPaid: 0,
  bonus: 0,
  workedDays: 30,
  totalDays: 30,
  totalAmount: 0,
  remarks: '',
  date: new Date().toISOString().split('T')[0]
})

const salaryFormErrors = ref<FormError[]>([])

const filteredSalaries = computed(() => {
  return salaries.value.filter(sal => {
    const matchesSearch = sal.employeeName.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      sal.remarks.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (sal.payslip_number || '').toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchesMonth = !salaryMonthFilter.value || sal.salaryMonth === salaryMonthFilter.value
    let matchesAmount = true
    if (selectedSalaryAmountRange.value === 'low') matchesAmount = sal.totalAmount < 40000
    if (selectedSalaryAmountRange.value === 'mid') matchesAmount = sal.totalAmount >= 40000 && sal.totalAmount <= 70000
    if (selectedSalaryAmountRange.value === 'high') matchesAmount = sal.totalAmount > 70000
    return matchesSearch && matchesMonth && matchesAmount
  })
})

const paymentTotalsByCurrency = computed(() => {
  return filteredSalaries.value.reduce<Record<string, { amount: number; currency: Salary['currency'] }>>((totals, salary) => {
    const key = salary.currency?.code ?? 'unknown'
    if (!totals[key]) totals[key] = { amount: 0, currency: salary.currency }
    totals[key].amount += salary.totalAmount
    return totals
  }, {})
})

const defaultCurrencyId = () => currencies.value.find(currency => currency.isBase)?.id ?? currencies.value[0]?.id ?? null
const selectedFormCurrency = computed(() => currencies.value.find(currency => currency.id === salaryForm.value.currency_id) ?? null)

const formatSalaryAmount = (amount: number, currency?: Salary['currency'] | null) => {
  const decimalPlaces = currency?.decimalPlaces ?? 2
  const formattedAmount = Number(amount || 0).toLocaleString('en-US', {
    minimumFractionDigits: decimalPlaces,
    maximumFractionDigits: decimalPlaces,
  })

  return `${currency?.symbol ?? ''}${formattedAmount}${currency?.code ? ` ${currency.code}` : ''}`
}

// The employee endpoint currently serializes position as a title string, while
// older callers type it as an object. Keep this page compatible with both.
const getEmployeePosition = (employee: Employee | undefined): string => {
  const position = employee?.position as unknown
  if (typeof position === 'string') return position
  if (position && typeof position === 'object' && 'name' in position && typeof position.name === 'string') return position.name

  return 'No position assigned'
}

const formatAmountInWords = (amount: number, currencyCode: string): string => {
  return `${amount.toLocaleString('en-US', { minimumFractionDigits: 2 })} ${currencyCode} Only`
}

// Calculate MoF Wage Withholding Tax (Article 46 of Afghanistan Income Tax Law)
const calculateAfghanWithholdingTax = (grossAmount: number): number => {
  if (grossAmount <= 5000) return 0
  if (grossAmount <= 12500) return (grossAmount - 5000) * 0.02
  if (grossAmount <= 100000) return 150 + (grossAmount - 12500) * 0.10
  return 150 + 8750 + (grossAmount - 100000) * 0.20
}

// Fetch all salaries from API
const fetchSalaries = async () => {
  try {
    isLoading.value = true
    const params = {
      searchQuery: searchQuery.value,
      salaryMonthFilter: salaryMonthFilter.value,
      selectedSalaryAmountRange: selectedSalaryAmountRange.value
    }
    const response = await SalaryApi.getAll(params)
    salaries.value = response.data
  } catch (error) {
    console.error('Error fetching salaries:', error)
  } finally {
    isLoading.value = false
  }
}

const fetchEmployees = async () => {
  try {
    const response = await EmployeeApi.getAll()
    employees.value = response.data.data
  } catch (error) {
    console.error('Error fetching employees:', error)
  }
}

const fetchCurrencies = async () => {
  try {
    const response = await CurrencyApi.getAll()
    currencies.value = response.data.filter(currency => currency.isActive)
    if (!salaryForm.value.currency_id) salaryForm.value.currency_id = defaultCurrencyId()
  } catch (error) {
    console.error('Error fetching currencies:', error)
  }
}

const fetchGrantedBonuses = async () => {
  const { employee_id, salaryMonth } = salaryForm.value
  grantedBonuses.value = []
  salaryForm.value.bonus = 0
  calculateSalaryTotal()

  if (!employee_id || !/^\d{4}-(0[1-9]|1[0-2])$/.test(salaryMonth)) return

  try {
    isLoadingBonuses.value = true
    const response = await SalaryApi.getGrantedBonuses(employee_id, salaryMonth)

    // Ignore an older response if the selection changed while it was loading.
    if (salaryForm.value.employee_id !== employee_id || salaryForm.value.salaryMonth !== salaryMonth) return

    grantedBonuses.value = response.data
    salaryForm.value.bonus = response.data.reduce((sum, bonus) => sum + Number(bonus.selectedAmount), 0)
    calculateSalaryTotal()
  } catch (error) {
    console.error('Error fetching granted bonuses:', error)
  } finally {
    isLoadingBonuses.value = false
  }
}

// Trigger Individual Payslip Print
const printEmployeePayslip = async (salary: Salary) => {
  const emp = employees.value.find(e => e.id === salary.employee_id)

  const earningsList = [
    { label: 'Salary Payment', amount: salary.amountPaid }
  ]

  if (salary.bonus > 0) {
    earningsList.push({ label: 'Granted Bonus', amount: salary.bonus })
  }

  const deductionsList = []

  // Absenteeism deduction if worked days are less than total days
  if (salary.workedDays < salary.totalDays) {
    const absentDays = salary.totalDays - salary.workedDays
    const dailyRate = salary.amountPaid / salary.totalDays
    deductionsList.push({
      label: `Unpaid Leave / Absenteeism (${absentDays} Days)`,
      amount: Math.round(dailyRate * absentDays)
    })
  }

  // Calculate MoF Article 46 Tax
  const mofTax = Math.round(calculateAfghanWithholdingTax(salary.totalAmount))
  if (mofTax > 0) {
    deductionsList.push({
      label: 'MoF Wage Withholding Tax (BRT Art. 46)',
      amount: mofTax
    })
  }

  activePayslipProps.value = {
    documentTitle: 'OFFICIAL PAYSLIP',
    documentNumber: salary.payslip_number,
    payslipSubtitle: `Pay Period: ${salary.salaryMonth}`,
    currency: salary.currency?.code ?? 'AFN',
    company: {
      name: 'Acme Technologies Afghanistan Ltd.',
      brandName: 'ACME AFGHANISTAN',
      addressLines: ['Shahr-e-Naw, District 10', 'Kabul, Afghanistan'],
      email: 'hr@acmetech.af',
      phone: '+93 (0) 20 210 4321',
      tinNumber: 'TIN-90082134'
    },
    employee: {
      name: salary.employeeName,
      designation: getEmployeePosition(emp),
      department: 'Operations',
      dateOfJoining: '1402-01-15 (2023-04-04)',
      payPeriod: salary.salaryMonth,
      payDate: salary.date,
      accountNumber: 'AIB-XXXX-XXXX-' + (emp?.id.toString().padStart(4, '0') || '1234'),
      workedDays: salary.workedDays,
      totalDays: salary.totalDays,
      tazkiraNumber: 'N/A',
      tinNumber: 'N/A'
    },
    earnings: earningsList,
    deductions: deductionsList,
    reimbursements: [],
    amountInWords: formatAmountInWords(salary.totalAmount - mofTax, salary.currency?.code ?? 'AFN')
  }

  await nextTick()
  if (payslipRef.value) {
    payslipRef.value.print()
  }
}

const openAddSalaryDialog = () => {
  salaryForm.value = {
    id: null,
    employee_id: null,
    employeeName: '',
    salaryMonth: new Date().toISOString().slice(0, 7),
    currency_id: defaultCurrencyId(),
    amountPaid: 0,
    bonus: 0,
    workedDays: 30,
    totalDays: 30,
    totalAmount: 0,
    remarks: '',
    date: new Date().toISOString().split('T')[0]
  }
  salaryFormErrors.value = []
  grantedBonuses.value = []
  isAddSalaryDialogVisible.value = true
  fetchGrantedBonuses()
}

const openEditSalaryDialog = (salary: Salary) => {
  selectedSalary.value = salary
  salaryForm.value = {
    id: salary.id,
    employee_id: salary.employee_id,
    employeeName: salary.employeeName,
    currency_id: salary.currencyId,
    salaryMonth: salary.salaryMonth,
    amountPaid: salary.amountPaid,
    bonus: salary.bonus,
    workedDays: salary.workedDays,
    totalDays: salary.totalDays,
    totalAmount: salary.totalAmount,
    remarks: salary.remarks,
    date: salary.date,
  }
  grantedBonuses.value = salary.bonuses
  salaryFormErrors.value = []
  isEditSalaryDialogVisible.value = true
}

const openDeleteSalaryDialog = (salary: Salary) => {
  selectedSalary.value = salary
  isDeleteSalaryDialogVisible.value = true
}

const calculateSalaryTotal = () => {
  salaryForm.value.totalAmount = salaryForm.value.amountPaid + salaryForm.value.bonus
}

const validateSalaryForm = () => {
  salaryFormErrors.value = []
  if (!salaryForm.value.employee_id) {
    salaryFormErrors.value.push({ field: 'employee', message: 'Employee is required' })
  }
  if (!salaryForm.value.salaryMonth) {
    salaryFormErrors.value.push({ field: 'salaryMonth', message: 'Salary month is required' })
  } else if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(salaryForm.value.salaryMonth)) {
    salaryFormErrors.value.push({ field: 'salaryMonth', message: 'Please select a valid month' })
  }
  if (!salaryForm.value.currency_id) {
    salaryFormErrors.value.push({ field: 'currency', message: 'Currency is required' })
  }
  if (salaryForm.value.amountPaid <= 0) {
    salaryFormErrors.value.push({ field: 'amountPaid', message: 'Amount paid must be greater than 0' })
  }
  if (salaryForm.value.workedDays < 0 || salaryForm.value.workedDays > 31) {
    salaryFormErrors.value.push({ field: 'workedDays', message: 'Worked days must be between 0 and 31' })
  }
  if (salaryForm.value.remarks && salaryForm.value.remarks.length > 255) {
    salaryFormErrors.value.push({ field: 'remarks', message: 'Remarks must be less than 255 characters' })
  }
  return salaryFormErrors.value.length === 0
}

const addSalary = async () => {
  if (!validateSalaryForm()) return
  calculateSalaryTotal()

  try {
    isLoading.value = true
    const payload: CreateSalaryData = {
      employee_id: salaryForm.value.employee_id!,
      currency_id: salaryForm.value.currency_id!,
      salaryMonth: salaryForm.value.salaryMonth,
      amountPaid: salaryForm.value.amountPaid,
      workedDays: salaryForm.value.workedDays,
      totalDays: salaryForm.value.totalDays,
      remarks: salaryForm.value.remarks,
      date: salaryForm.value.date
    }

    const response = await SalaryApi.create(payload)
    await fetchSalaries()
    isAddSalaryDialogVisible.value = false
  } catch (error) {
    console.error('Error creating salary:', error)
  } finally {
    isLoading.value = false
  }
}

const updateSalary = async () => {
  if (!selectedSalary.value) return
  if (!validateSalaryForm()) return
  calculateSalaryTotal()

  try {
    isLoading.value = true
    const payload: UpdateSalaryData = {
      employee_id: salaryForm.value.employee_id,
      currency_id: salaryForm.value.currency_id ?? undefined,
      salaryMonth: salaryForm.value.salaryMonth,
      amountPaid: salaryForm.value.amountPaid,
      workedDays: salaryForm.value.workedDays,
      totalDays: salaryForm.value.totalDays,
      remarks: salaryForm.value.remarks,
      date: salaryForm.value.date
    }

    const response = await SalaryApi.update(selectedSalary.value.id, payload)
    await fetchSalaries()
    isEditSalaryDialogVisible.value = false
  } catch (error) {
    console.error('Error updating salary:', error)
  } finally {
    isLoading.value = false
  }
}

const deleteSalary = async () => {
  if (!selectedSalary.value) return

  try {
    isLoading.value = true
    await SalaryApi.delete(selectedSalary.value.id)
    await fetchSalaries()
    isDeleteSalaryDialogVisible.value = false
  } catch (error) {
    console.error('Error deleting salary:', error)
  } finally {
    isLoading.value = false
  }
}

const getSalaryErrorMessage = (field: string) => {
  const error = salaryFormErrors.value.find(e => e.field === field)
  return error ? error.message : ''
}

const printTable = () => {
  const printWindow = window.open('', '_blank')
  if (printWindow) {
    const html = `
      <html>
        <head>
          <title>Afghanistan Staff Salaries Report</title>
          <style>
            @page { size: A4 landscape; margin: 15mm; }
            body { font-family: Arial, sans-serif; margin: 0; color: #1e293b; }
            h1 { text-align: center; color: #1e3a8a; margin-bottom: 4px; }
            p.sub { text-align: center; color: #64748b; margin-top: 0; font-size: 14px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 12px; }
            th, td { border: 1px solid #cbd5e1; padding: 8px; text-align: left; }
            th { background-color: #f1f5f9; color: #334155; font-weight: bold; }
            tr:nth-child(even) { background-color: #f8fafc; }
            .right { text-align: right; }
          </style>
        </head>
        <body>
          <h1>Staff Salaries Report</h1>
          <p class="sub">Acme Technologies Afghanistan Ltd. — Ministry of Finance Compliant</p>
          <table>
            <thead>
              <tr>
                <th>Employee</th>
                <th>Pay Month</th>
                <th>Worked Days</th>
                <th>Currency</th>
                <th class="right">Granted Bonus</th>
                <th class="right">Total Paid</th>
                <th>Remarks</th>
                <th>Payment Date</th>
              </tr>
            </thead>
            <tbody>
              ${filteredSalaries.value.map(salary => `
                <tr>
                  <td><strong>${salary.employeeName}</strong></td>
                  <td>${salary.salaryMonth}</td>
                  <td>${salary.workedDays} / ${salary.totalDays} Days</td>
                  <td>${salary.currency?.code ?? '—'}</td>
                  <td class="right">${formatSalaryAmount(salary.bonus, salary.currency)}</td>
                  <td class="right"><strong>${formatSalaryAmount(salary.totalAmount, salary.currency)}</strong></td>
                  <td>${salary.remarks}</td>
                  <td>${salary.date}</td>
                </tr>
              `).join('')}
              ${Object.values(paymentTotalsByCurrency.value).map(total => `
                <tr>
                  <td colspan="5" class="right" style="font-weight: bold;">Total Paid (${total.currency?.code ?? 'Unknown currency'}):</td>
                  <td class="right" style="font-weight: bold; color: #1e3a8a;">${formatSalaryAmount(total.amount, total.currency)}</td>
                  <td colspan="2"></td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </body>
      </html>
    `
    printWindow.document.write(html)
    printWindow.document.close()
    printWindow.focus()
    printWindow.print()
  }
}

// Fetch salaries when component mounts
onMounted(() => {
  fetchSalaries()
  fetchEmployees()
  fetchCurrencies()
})

// Watch for filter changes and fetch data
watch([searchQuery, salaryMonthFilter, selectedSalaryAmountRange], () => {
  fetchSalaries()
})

watch(() => [salaryForm.value.employee_id, salaryForm.value.salaryMonth], () => {
  if (isAddSalaryDialogVisible.value) fetchGrantedBonuses()
})
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <VRow align="center">
          <VCol cols="12" md="4">
            <VTextField v-model="searchQuery" prepend-inner-icon="tabler-search" placeholder="Search salaries..."
              density="compact" clearable hide-details />
          </VCol>

          <VCol cols="12" sm="6" md="3">
            <VSelect v-model="salaryMonthFilter" :items="[
              { title: 'All Months', value: null },
              ...Array.from({ length: 12 }, (_, i) => {
                const date = new Date()
                date.setMonth(date.getMonth() - i)
                return {
                  title: date.toLocaleString('default', { month: 'long', year: 'numeric' }),
                  value: date.toISOString().slice(0, 7)
                }
              })
            ]" label="Filter by Month" density="compact" clearable hide-details />
          </VCol>

          <VCol cols="12" sm="6" md="3">
            <VSelect v-model="selectedSalaryAmountRange" :items="[
              { title: 'Under ؋40,000', value: 'low' },
              { title: '؋40,000 - ؋70,000', value: 'mid' },
              { title: 'Over ؋70,000', value: 'high' }
            ]" label="Filter by Amount" density="compact" clearable hide-details />
          </VCol>

          <VCol cols="12" md="2" class="d-flex gap-2 justify-end">
            <VBtn icon="tabler-table" variant="text" @click="printTable" title="Print all salary records" />
            <VBtn color="primary" @click="openAddSalaryDialog">
              <VIcon icon="tabler-plus" class="me-1" />
              Add Salary
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <div class="table-container">
        <VDataTable :items="filteredSalaries" :headers="[
          { title: 'Payslip Number', key: 'payslip_number', sortable: true },
          { title: 'Employee', key: 'employeeName', sortable: true },
          { title: 'Pay Month', key: 'salaryMonth', sortable: true },
          { title: 'Worked Days', key: 'workedDays', sortable: true },
          { title: 'Currency', key: 'currency.code', sortable: true },
          { title: 'Amount Paid', key: 'amountPaid', sortable: true },
          { title: 'Granted Bonus', key: 'bonus', sortable: true },
          { title: 'Total Paid', key: 'totalAmount', sortable: true },
          { title: 'Remarks', key: 'remarks', sortable: false },
          { title: 'Date', key: 'date', sortable: true },
          { title: 'Actions', key: 'actions', sortable: false }
        ]" item-value="id" class="text-no-wrap" :loading="isLoading" mobile-breakpoint="sm" density="compact">

          <template #item.workedDays="{ item }">
            <VChip size="small" :color="item.workedDays < item.totalDays ? 'warning' : 'success'" variant="tonal">
              {{ item.workedDays }} / {{ item.totalDays }} Days
            </VChip>
          </template>

          <template #item.bonus="{ item }">
            <span v-if="item.bonus > 0" class="text-info font-weight-medium">+{{ formatSalaryAmount(item.bonus, item.currency) }}</span>
            <span v-else class="text-muted text-xs">—</span>
          </template>

          <template #item.amountPaid="{ item }">
            <span class="font-weight-medium">{{ formatSalaryAmount(item.amountPaid, item.currency) }}</span>
          </template>

          <template #item.totalAmount="{ item }">
            <span class="font-weight-bold">{{ formatSalaryAmount(item.totalAmount, item.currency) }}</span>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <VBtn icon="tabler-printer" variant="text" size="small" color="primary"
                @click="printEmployeePayslip(item)" title="Print employee payslip" />
              <VBtn icon="tabler-edit" variant="text" size="small" color="medium-emphasis"
                @click="openEditSalaryDialog(item)" title="Edit salary record" />
              <VBtn icon="tabler-trash" variant="text" size="small" color="error" @click="openDeleteSalaryDialog(item)"
                title="Delete salary record" />
            </div>
          </template>
        </VDataTable>
      </div>
    </VCard>

    <!-- Hidden Payslip Template Instance for Printing -->
    <div style="display: none;">
      <PayslipTemplate v-if="activePayslipProps" ref="payslipRef" v-bind="activePayslipProps" />
    </div>

    <!-- Add Salary Dialog -->
    <VDialog v-model="isAddSalaryDialogVisible" max-width="800px">
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">Add Salary Payment</span>
        </VCardTitle>
        <VCardText>
          <VForm>
            <VRow>
              <VCol cols="12">
                <VSelect v-model="salaryForm.employee_id" :items="employees" item-title="name" item-value="id"
                  label="Employee" :error-messages="getSalaryErrorMessage('employee')" required>
                  <template #item="{ props, item }">
                    <VListItem v-bind="props" :title="item.raw.name"
                      :subtitle="getEmployeePosition(item.raw)" />
                  </template>
                </VSelect>
              </VCol>
              <VCol cols="12" md="4">
                <VTextField v-model="salaryForm.salaryMonth" label="Salary Month" type="month"
                  :error-messages="getSalaryErrorMessage('salaryMonth')" required />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField v-model.number="salaryForm.workedDays" label="Worked Days" type="number"
                  :error-messages="getSalaryErrorMessage('workedDays')" required />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField v-model="salaryForm.date" label="Payment Date" type="date" required />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect v-model="salaryForm.currency_id" :items="currencies" item-title="code" item-value="id"
                  label="Currency" :error-messages="getSalaryErrorMessage('currency')" required>
                  <template #selection="{ item }">{{ item.raw.code }} ({{ item.raw.symbol }})</template>
                  <template #item="{ props, item }"><VListItem v-bind="props" :subtitle="item.raw.name" /></template>
                </VSelect>
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model.number="salaryForm.amountPaid" label="Amount Paid" type="number"
                  :error-messages="getSalaryErrorMessage('amountPaid')" @input="calculateSalaryTotal" required />
              </VCol>
              <VCol cols="12" md="6">
                <VCard variant="tonal" color="success" class="h-100">
                  <VCardText class="py-3">
                    <div class="d-flex justify-space-between align-center mb-2">
                      <span class="font-weight-medium">Granted bonus</span>
                      <VProgressCircular v-if="isLoadingBonuses" indeterminate size="18" width="2" />
                      <span v-else class="text-h6 font-weight-bold">{{ formatSalaryAmount(salaryForm.bonus, selectedFormCurrency) }}</span>
                    </div>
                    <div v-if="grantedBonuses.length" class="text-caption">
                      <div v-for="bonus in grantedBonuses" :key="bonus.id">
                        {{ bonus.selectedType }} · ؋{{ Number(bonus.selectedAmount).toLocaleString() }}
                      </div>
                    </div>
                    <div v-else class="text-caption text-medium-emphasis">No granted bonus for this employee and month.</div>
                  </VCardText>
                </VCard>
              </VCol>
              <VCol cols="12">
                <VTextarea v-model="salaryForm.remarks" label="Remarks"
                  :error-messages="getSalaryErrorMessage('remarks')" rows="2" auto-grow />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="isAddSalaryDialogVisible = false">Cancel</VBtn>
          <VBtn color="primary" @click="addSalary" :loading="isLoading">Save</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit Salary Dialog -->
    <VDialog v-model="isEditSalaryDialogVisible" max-width="800px">
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">Edit Salary Payment</span>
        </VCardTitle>
        <VCardText>
          <VForm>
            <VRow>
              <VCol cols="12">
                <VSelect v-model="salaryForm.employee_id" :items="employees" item-title="name" item-value="id"
                  label="Employee" :error-messages="getSalaryErrorMessage('employee')" disabled>
                  <template #item="{ props, item }">
                    <VListItem v-bind="props" :title="item.raw.name"
                      :subtitle="getEmployeePosition(item.raw)" />
                  </template>
                </VSelect>
              </VCol>
              <VCol cols="12" md="4">
                <VTextField v-model="salaryForm.salaryMonth" label="Salary Month" type="month"
                  :error-messages="getSalaryErrorMessage('salaryMonth')" required />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField v-model.number="salaryForm.workedDays" label="Worked Days" type="number"
                  :error-messages="getSalaryErrorMessage('workedDays')" required />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField v-model="salaryForm.date" label="Payment Date" type="date" required />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect v-model="salaryForm.currency_id" :items="currencies" item-title="code" item-value="id"
                  label="Currency" :error-messages="getSalaryErrorMessage('currency')" required>
                  <template #selection="{ item }">{{ item.raw.code }} ({{ item.raw.symbol }})</template>
                  <template #item="{ props, item }"><VListItem v-bind="props" :subtitle="item.raw.name" /></template>
                </VSelect>
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model.number="salaryForm.amountPaid" label="Amount Paid" type="number"
                  :error-messages="getSalaryErrorMessage('amountPaid')" @input="calculateSalaryTotal" required />
              </VCol>
              <VCol cols="12" md="6">
                <VCard variant="tonal" color="success" class="h-100">
                  <VCardText class="py-3">
                    <div class="d-flex justify-space-between align-center mb-2">
                      <span class="font-weight-medium">Granted bonus</span>
                      <VProgressCircular v-if="isLoadingBonuses" indeterminate size="18" width="2" />
                      <span v-else class="text-h6 font-weight-bold">{{ formatSalaryAmount(salaryForm.bonus, selectedFormCurrency) }}</span>
                    </div>
                    <div v-if="grantedBonuses.length" class="text-caption">
                      <div v-for="bonus in grantedBonuses" :key="bonus.id">
                        {{ bonus.selectedType }} · ؋{{ Number(bonus.selectedAmount).toLocaleString() }}
                      </div>
                    </div>
                    <div v-else class="text-caption text-medium-emphasis">No granted bonus for this employee and month.</div>
                  </VCardText>
                </VCard>
              </VCol>
              <VCol cols="12">
                <VTextarea v-model="salaryForm.remarks" label="Remarks"
                  :error-messages="getSalaryErrorMessage('remarks')" rows="2" auto-grow />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="isEditSalaryDialogVisible = false">Cancel</VBtn>
          <VBtn color="primary" @click="updateSalary" :loading="isLoading">Save</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Salary Dialog -->
    <VDialog v-model="isDeleteSalaryDialogVisible" max-width="500px">
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">Confirm Delete</span>
        </VCardTitle>
        <VCardText>
          Are you sure you want to delete this salary record?
          <br>
          This action cannot be undone.
        </VCardText>
        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn color="secondary" variant="tonal" @click="isDeleteSalaryDialogVisible = false">Cancel</VBtn>
          <VBtn color="error" @click="deleteSalary" :loading="isLoading">Delete</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.table-container {
  overflow-x: auto;
}
</style>
