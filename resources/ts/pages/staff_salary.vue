<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { VDialog } from 'vuetify/components'
import PayslipTemplate, { type SalarySlipProps } from './templates/payslip_template.vue'
import SalaryApi, { type CreateSalaryData, type Salary, type SalaryDeduction, type UpdateSalaryData } from '@/api/salary'
import { type WorthyEmployee } from '@/api/bonuslist'
import EmployeeApi, { type Employee } from '@/api/employee'
import CurrencyApi, { type Currency } from '@/api/currency'
import SettingApi, { type Setting } from '@/api/setting'
import { apiFieldErrors } from '@/utils/apiErrors'
import { printDocument } from '@/utils/printDocument'
import PrintableListReport from '@/components/PrintableListReport.vue'
import { avatarText } from '@/@core/utils/formatters'

interface FormError {
  field: string
  message: string
}

const salaries = ref<Salary[]>([])
const employees = ref<Employee[]>([])
const currencies = ref<Currency[]>([])
const companySetting = ref<Setting | null>(null)
const grantedBonuses = ref<WorthyEmployee[]>([])
const isLoadingBonuses = ref(false)

const isAddSalaryDialogVisible = ref(false)
const isEditSalaryDialogVisible = ref(false)
const isDeleteSalaryDialogVisible = ref(false)
const selectedSalary = ref<Salary | null>(null)
const searchQuery = ref('')
const salaryMonthFilter = ref<string | null>(null)
const selectedSalaryAmountRange = ref<'low' | 'mid' | 'high' | null>(null)
const isLoading = ref(false)

// Payslip Printing Reference & State
const payslipRef = ref<InstanceType<typeof PayslipTemplate> | null>(null)
const activePayslipProps = ref<SalarySlipProps | null>(null)
const isPayslipPreviewOpen = ref(false)

const salaryForm = ref({
  id: null as number | null,
  employee_id: null as number | null,
  employeeName: '',
  salaryMonth: new Date().toISOString().slice(0, 7),
  currency_id: null as number | null,
  rate: 1,
  amountPaid: 0,
  bonus: 0,
  workedDays: 30,
  totalDays: 30,
  totalAmount: 0,
  deductions: [] as SalaryDeduction[],
  remarks: '',
  date: new Date().toISOString().split('T')[0],
})

const salaryFormErrors = ref<FormError[]>([])

const filteredSalaries = computed(() => {
  return salaries.value.filter(sal => {
    const matchesSearch = sal.employeeName.toLowerCase().includes(searchQuery.value.toLowerCase())
      || sal.remarks.toLowerCase().includes(searchQuery.value.toLowerCase())
      || (sal.payslip_number || '').toLowerCase().includes(searchQuery.value.toLowerCase())

    const matchesMonth = !salaryMonthFilter.value || sal.salaryMonth === salaryMonthFilter.value
    let matchesAmount = true
    if (selectedSalaryAmountRange.value === 'low')
      matchesAmount = sal.totalAmount < 40000
    if (selectedSalaryAmountRange.value === 'mid')
      matchesAmount = sal.totalAmount >= 40000 && sal.totalAmount <= 70000
    if (selectedSalaryAmountRange.value === 'high')
      matchesAmount = sal.totalAmount > 70000

    return matchesSearch && matchesMonth && matchesAmount
  })
})

const paymentTotalsByCurrency = computed(() => {
  return filteredSalaries.value.reduce<Record<string, { amount: number; currency: Salary['currency'] }>>((totals, salary) => {
    const key = salary.currency?.code ?? 'unknown'
    if (!totals[key])
      totals[key] = { amount: 0, currency: salary.currency }
    totals[key].amount += salary.totalAmount

    return totals
  }, {})
})

const formDeductionTotal = computed(() => salaryForm.value.deductions.reduce((sum, deduction) => sum + Number(deduction.amount || 0), 0))
const formNetAmount = computed(() => salaryForm.value.totalAmount - formDeductionTotal.value)

const defaultCurrencyId = () => currencies.value.find(currency => currency.isBase)?.id ?? currencies.value[0]?.id ?? null
const selectedFormCurrency = computed(() => currencies.value.find(currency => currency.isBase) ?? null)

const convertedBonusAmount = (bonus: WorthyEmployee, _currency = selectedFormCurrency.value) => {
  const bookedBase = currencies.value.find(currency => currency.id === bonus.baseCurrencyId)
  const bookedBaseAmount = Number(bonus.baseAmount ?? bonus.grantedAmount ?? bonus.selectedAmount ?? 0)
  const sourceRate = Number(bookedBase?.rate ?? 1)
  const targetRate = Number(_currency?.rate ?? 1)

  return sourceRate > 0 ? (bookedBaseAmount / sourceRate) * targetRate : bookedBaseAmount
}

const formatSalaryAmount = (amount: number, currency?: Salary['currency'] | Currency | null) => {
  const decimalPlaces = currency?.decimalPlaces ?? 2

  const formattedAmount = formatLocalizedNumber(Number(amount || 0), {
    minimumFractionDigits: decimalPlaces,
    maximumFractionDigits: decimalPlaces,
  })

  return `${currency?.symbol ?? ''}${formattedAmount}${currency?.code ? ` ${currency.code}` : ''}`
}

// The employee endpoint currently serializes position as a title string, while
// older callers type it as an object. Keep this page compatible with both.
const getEmployeePosition = (employee: Employee | undefined): string => {
  const position = employee?.position as unknown
  if (typeof position === 'string')
    return position
  if (position && typeof position === 'object' && 'name' in position && typeof position.name === 'string')
    return position.name

  return translate('pages.payroll.staffSalary.staffSalary.noPositionAssigned')
}

const formatAmountInWords = (amount: number, currencyCode: string): string => {
  return translate('documents.payslip.amountInWordsFallback', { amount: formatLocalizedNumber(amount, { minimumFractionDigits: 2 }), currency: currencyCode })
}

// Fetch all salaries from API
const fetchSalaries = async () => {
  try {
    isLoading.value = true

    const params = {
      searchQuery: searchQuery.value,
      salaryMonthFilter: salaryMonthFilter.value,
      selectedSalaryAmountRange: selectedSalaryAmountRange.value,
    }

    const response = await SalaryApi.getAll(params)

    salaries.value = response.data
  }
  catch (error) {
    console.error('Error fetching salaries:', error)
  }
  finally {
    isLoading.value = false
  }
}

const fetchEmployees = async () => {
  try {
    const response = await EmployeeApi.getAll()

    employees.value = response.data.data
  }
  catch (error) {
    console.error('Error fetching employees:', error)
  }
}

const fetchCurrencies = async () => {
  try {
    const response = await CurrencyApi.getAll()

    currencies.value = response.data.filter(currency => currency.isActive)
    if (!salaryForm.value.currency_id)
      salaryForm.value.currency_id = defaultCurrencyId()
  }
  catch (error) {
    console.error('Error fetching currencies:', error)
  }
}

const fetchGrantedBonuses = async () => {
  const { employee_id, salaryMonth } = salaryForm.value

  grantedBonuses.value = []
  salaryForm.value.bonus = 0
  calculateSalaryTotal()

  if (!employee_id || !/^\d{4}-(0[1-9]|1[0-2])$/.test(salaryMonth))
    return

  try {
    isLoadingBonuses.value = true

    const response = await SalaryApi.getGrantedBonuses(employee_id, salaryMonth)

    // Ignore an older response if the selection changed while it was loading.
    if (salaryForm.value.employee_id !== employee_id || salaryForm.value.salaryMonth !== salaryMonth)
      return

    grantedBonuses.value = response.data
    salaryForm.value.bonus = response.data.reduce((sum, bonus) => sum + convertedBonusAmount(bonus), 0)
    calculateSalaryTotal()
  }
  catch (error) {
    console.error('Error fetching granted bonuses:', error)
  }
  finally {
    isLoadingBonuses.value = false
  }
}

// Trigger Individual Payslip Print
const printEmployeePayslip = async (salary: Salary) => {
  const emp = employees.value.find(e => e.id === salary.employee_id)

  const earningsList = [
    { label: translate('pages.payroll.staffSalary.staffSalary.salaryPayment'), amount: salary.amountPaid },
  ]

  if (salary.bonus > 0)
    earningsList.push({ label: translate('common.messages.grantedBonus'), amount: salary.bonus })

  const deductionsList = salary.deductions.map(deduction => ({
    label: deduction.description,
    amount: deduction.amount,
  }))

  activePayslipProps.value = {
    documentTitle: 'OFFICIAL PAYSLIP',
    documentNumber: salary.payslip_number,
    payslipSubtitle: `Pay Period: ${salary.salaryMonth}`,
    currency: salary.currency?.code ?? 'AFN',
    company: {
      name: companySetting.value?.original_company_name || companySetting.value?.website_title || '',
      brandName: companySetting.value?.short_company_name || companySetting.value?.website_title || '',
      addressLines: [companySetting.value?.physical_address].filter(Boolean) as string[],
      email: companySetting.value?.company_email || '',
      phone: companySetting.value?.phone || '',
      tinNumber: companySetting.value?.business_licence || undefined,
      logo: companySetting.value?.website_logo_url || undefined,
      signature: companySetting.value?.company_signature_url || undefined,
    },
    employee: {
      name: salary.employeeName,
      designation: getEmployeePosition(emp),
      department: 'Operations',
      dateOfJoining: '1402-01-15 (2023-04-04)',
      payPeriod: salary.salaryMonth,
      payDate: salary.date,
      workedDays: salary.workedDays ?? 0,
      totalDays: salary.totalDays,
      paidLeaveDays: salary.paidLeaveDays,
      absentDays: salary.absentDays,
      unpaidLeaveDays: salary.unpaidLeaveDays,
      tazkiraNumber: emp?.id_number || 'N/A',
      accountNumber: emp?.bank_account_number || 'N/A',
    },
    earnings: earningsList,
    deductions: deductionsList,
    reimbursements: [],
    amountInWords: formatAmountInWords(salary.netAmount, salary.currency?.code ?? 'AFN'),
  }

  isPayslipPreviewOpen.value = true
  await nextTick()
}

const printPreviewedPayslip = () => payslipRef.value?.print()

const openAddSalaryDialog = () => {
  salaryForm.value = {
    id: null,
    employee_id: null,
    employeeName: '',
    salaryMonth: new Date().toISOString().slice(0, 7),
    currency_id: defaultCurrencyId(),
    rate: 1,
    amountPaid: 0,
    bonus: 0,
    workedDays: 30,
    totalDays: 30,
    totalAmount: 0,
    deductions: [],
    remarks: '',
    date: new Date().toISOString().split('T')[0],
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
    rate: 1,
    salaryMonth: salary.salaryMonth,
    amountPaid: salary.amountPaid,
    bonus: salary.bonus,
    workedDays: salary.workedDays ?? 0,
    totalDays: salary.totalDays,
    totalAmount: salary.totalAmount,
    deductions: salary.deductions.map(deduction => ({ ...deduction })),
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

// A new payslip begins with the agreed employee salary. It remains a normal,
// editable amount so adjustments and partial-month payments are still possible.
const applyEmployeeSalary = (employeeId: number | null) => {
  const employee = employees.value.find(item => item.id === employeeId)
  if (!employee || !isAddSalaryDialogVisible.value)
    return

  salaryForm.value.amountPaid = Number(employee.salary ?? 0)
  salaryForm.value.employeeName = employee.name
  calculateSalaryTotal()
}

const addDeduction = () => {
  salaryForm.value.deductions.push({ description: '', amount: 0 })
}

const removeDeduction = (index: number) => {
  salaryForm.value.deductions.splice(index, 1)
}

const validateSalaryForm = () => {
  salaryFormErrors.value = []
  if (!salaryForm.value.employee_id)
    salaryFormErrors.value.push({ field: 'employee', message: translate('pages.payroll.staffSalary.staffSalary.employeeIsRequired') })

  if (!salaryForm.value.salaryMonth)
    salaryFormErrors.value.push({ field: 'salaryMonth', message: translate('pages.payroll.staffSalary.staffSalary.salaryMonthIsRequired') })
  else if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(salaryForm.value.salaryMonth))
    salaryFormErrors.value.push({ field: 'salaryMonth', message: translate('pages.payroll.staffSalary.staffSalary.pleaseSelectAValidMonth') })

  if (!salaryForm.value.currency_id)
    salaryFormErrors.value.push({ field: 'currency', message: translate('pages.payroll.staffSalary.staffSalary.currencyIsRequired') })

  if (salaryForm.value.amountPaid <= 0)
    salaryFormErrors.value.push({ field: 'amountPaid', message: translate('pages.payroll.staffSalary.staffSalary.amountPaidMustBeGreaterThan0') })

  if (salaryForm.value.workedDays < 0 || salaryForm.value.workedDays > 31)
    salaryFormErrors.value.push({ field: 'workedDays', message: translate('pages.payroll.staffSalary.staffSalary.workedDaysMustBeBetween0And31') })

  if (salaryForm.value.totalDays < 1 || salaryForm.value.totalDays > 31)
    salaryFormErrors.value.push({ field: 'totalDays', message: translate('pages.payroll.staffSalary.staffSalary.totalDaysMustBeBetween1And31') })

  if (!/^\d{4}-\d{2}-\d{2}$/.test(salaryForm.value.date))
    salaryFormErrors.value.push({ field: 'date', message: translate('pages.payroll.staffSalary.staffSalary.paymentDateIsRequired') })

  if (salaryForm.value.remarks && salaryForm.value.remarks.length > 255)
    salaryFormErrors.value.push({ field: 'remarks', message: translate('pages.payroll.staffSalary.staffSalary.remarksMustBeLessThan255Characters') })

  salaryForm.value.deductions.forEach((deduction, index) => {
    if (!deduction.description.trim())
      salaryFormErrors.value.push({ field: `deduction-${index}-description`, message: translate('pages.payroll.staffSalary.staffSalary.deductionDescriptionIsRequired') })

    if (Number(deduction.amount) <= 0)
      salaryFormErrors.value.push({ field: `deduction-${index}-amount`, message: translate('pages.payroll.staffSalary.staffSalary.deductionAmountMustBeGreaterThan0') })
  })

  return salaryFormErrors.value.length === 0
}

const addSalary = async () => {
  if (!validateSalaryForm())
    return
  calculateSalaryTotal()

  try {
    isLoading.value = true

    const payload: CreateSalaryData = {
      employee_id: salaryForm.value.employee_id!,
      currency_id: salaryForm.value.currency_id!,
      rate: 1,
      salaryMonth: salaryForm.value.salaryMonth,
      amountPaid: salaryForm.value.amountPaid,
      workedDays: salaryForm.value.workedDays,
      totalDays: salaryForm.value.totalDays,
      remarks: salaryForm.value.remarks,
      date: salaryForm.value.date,
      deductions: salaryForm.value.deductions,
    }

    const response = await SalaryApi.create(payload)

    await fetchSalaries()
    isAddSalaryDialogVisible.value = false
  }
  catch (error) {
    console.error('Error creating salary:', error)
    applySalaryApiErrors(error)
  }
  finally {
    isLoading.value = false
  }
}

const updateSalary = async () => {
  if (!selectedSalary.value)
    return
  if (!validateSalaryForm())
    return
  calculateSalaryTotal()

  try {
    isLoading.value = true

    const payload: UpdateSalaryData = {
      employee_id: salaryForm.value.employee_id ?? undefined,
      currency_id: salaryForm.value.currency_id ?? undefined,
      rate: 1,
      salaryMonth: salaryForm.value.salaryMonth,
      amountPaid: salaryForm.value.amountPaid,
      workedDays: salaryForm.value.workedDays,
      totalDays: salaryForm.value.totalDays,
      remarks: salaryForm.value.remarks,
      date: salaryForm.value.date,
      deductions: salaryForm.value.deductions,
    }

    const response = await SalaryApi.update(selectedSalary.value.id, payload)

    await fetchSalaries()
    isEditSalaryDialogVisible.value = false
  }
  catch (error) {
    console.error('Error updating salary:', error)
    applySalaryApiErrors(error)
  }
  finally {
    isLoading.value = false
  }
}

const deleteSalary = async () => {
  if (!selectedSalary.value)
    return

  try {
    isLoading.value = true
    await SalaryApi.delete(selectedSalary.value.id)
    await fetchSalaries()
    isDeleteSalaryDialogVisible.value = false
  }
  catch (error) {
    console.error('Error deleting salary:', error)
  }
  finally {
    isLoading.value = false
  }
}

const getSalaryErrorMessage = (field: string) => {
  const error = salaryFormErrors.value.find(e => e.field === field)

  return error ? error.message : ''
}

const applySalaryApiErrors = (error: unknown) => {
  const fieldMap: Record<string, string> = {
    employee_id: 'employee',
    currency_id: 'currency',
    deductions: 'deductions',
  }

  salaryFormErrors.value = Object.entries(apiFieldErrors(error)).map(([field, message]) => ({
    field: fieldMap[field] ?? field.replace(/^deductions\.(\d+)\.(description|amount)$/, 'deduction-$1-$2'),
    message,
  }))

  if (!salaryFormErrors.value.length)
    salaryFormErrors.value = [{ field: 'form', message: translate('pages.payroll.staffSalary.staffSalary.unableToSaveTheSalaryPaymentPleaseTryAgain') }]
}

const printTable = () => {
  void printDocument({ bodyClass: 'printing-table-salaries', pageStyle: 'size: A4 landscape; margin: 12mm;' })

  // Legacy inline-print markup remains unreachable while existing deployments
  // transition to the shared, escaped printDocument implementation above.
  if (false as boolean) {
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
}

// Fetch salaries when component mounts
onMounted(async () => {
  companySetting.value = (await SettingApi.getCompanyProfile()).data
  fetchSalaries()
  fetchEmployees()
  fetchCurrencies()
})

// Watch for filter changes and fetch data
watch([searchQuery, salaryMonthFilter, selectedSalaryAmountRange], () => {
  fetchSalaries()
})

watch(() => [salaryForm.value.employee_id, salaryForm.value.salaryMonth, salaryForm.value.currency_id], () => {
  if (isAddSalaryDialogVisible.value)
    fetchGrantedBonuses()
})

watch(() => salaryForm.value.employee_id, applyEmployeeSalary)
</script>

<template>
  <div>
    <DataTablePageKpis
      :items="filteredSalaries"
      :base-currency="currencies.find(currency => currency.isBase) ?? null"
      title="Staff salaries"
      description="Review payroll records and surface payments that require attention."
      icon="tabler-wallet"
    />
    <VCard class="mb-6">
      <VCardText>
        <VRow
          align="center"
          class="table-toolbar"
        >
          <VCol
            cols="12"
            md="7"
            lg="9"
          >
            <VTextField
              v-model="searchQuery"
              class="data-table-search"
              prepend-inner-icon="tabler-search"
              :placeholder="$t('pages.payroll.staffSalary.staffSalary.searchSalaries')"
              density="compact"
              clearable
              hide-details
            />
          </VCol>

          <VCol
            cols="12"
            md="5"
            lg="3"
            class="table-toolbar-actions d-flex flex-nowrap justify-end gap-2"
          >
            <DataTableFilterMenu
              :active="Boolean(salaryMonthFilter || selectedSalaryAmountRange)"
              @reset="salaryMonthFilter = null; selectedSalaryAmountRange = null"
            >
              <VSelect
                v-model="salaryMonthFilter"
                :items="[
                  { title: translate('common.messages.allMonths'), value: null },
                  ...Array.from({ length: 12 }, (_, i) => {
                    const date = new Date()
                    date.setMonth(date.getMonth() - i)
                    return {
                      title: formatLocalizedDate(date, { month: 'long', year: 'numeric' }),
                      value: date.toISOString().slice(0, 7),
                    }
                  }),
                ]"
                :label="$t('pages.payroll.staffSalary.staffSalary.filterByMonth')"
                density="compact"
                clearable
                hide-details
              />
              <VSelect
                v-model="selectedSalaryAmountRange"
                :items="[
                  { title: translate('pages.payroll.staffSalary.staffSalary.under40000'), value: 'low' },
                  { title: '؋40,000 - ؋70,000', value: 'mid' },
                  { title: translate('pages.payroll.staffSalary.staffSalary.over70000'), value: 'high' },
                ]"
                :label="$t('pages.payroll.staffSalary.staffSalary.filterByAmount')"
                density="compact"
                clearable
                hide-details
              />
            </DataTableFilterMenu>
            <VBtn
              icon="tabler-table"
              variant="text"
              class="flex-shrink-0"
              @click="printTable"
            >
              <VIcon icon="tabler-table" /><VTooltip
                activator="parent"
                location="top"
              >
                {{ $t('pages.payroll.staffSalary.staffSalary.printAllSalaryRecords') }}
              </VTooltip>
            </VBtn>
            <VBtn
              color="primary"
              class="flex-shrink-0"
              @click="openAddSalaryDialog"
            >
              <VIcon
                icon="tabler-plus"
                class="me-1"
              />
              {{ $t('pages.payroll.staffSalary.staffSalary.addSalary') }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <div class="table-container">
        <ResponsiveDataTable
          :items="filteredSalaries"
          :headers="[
            { title: translate('pages.payroll.staffSalary.staffSalary.payslipNumber'), key: 'payslip_number', sortable: true },
            { title: translate('common.fields.employee'), key: 'employeeName', sortable: true },
            { title: translate('pages.payroll.staffSalary.staffSalary.payMonth'), key: 'salaryMonth', sortable: true },
            { title: translate('common.fields.currency'), key: 'currency.code', sortable: true },
            { title: translate('pages.payroll.staffSalary.staffSalary.totalPaid'), key: 'totalAmount', sortable: true },
            { title: translate('common.messages.actions'), key: 'actions', sortable: false },
          ]"
          item-value="id"
          class="text-no-wrap"
          :loading="isLoading"
          mobile-breakpoint="sm"
          density="compact"
        >
          <template #item.employeeName="{ item }">
            <div class="d-flex align-center gap-3">
              <VAvatar
                size="34"
                class="mx-2"
                :variant="item.employeeAvatar ? undefined : 'tonal'"
                :color="item.employeeAvatar ? undefined : 'primary'"
              >
                <VImg
                  v-if="item.employeeAvatar"
                  :src="item.employeeAvatar"
                  :alt="item.employeeName"
                />
                <span v-else>{{ avatarText(item.employeeName) }}</span>
              </VAvatar>
              <span>{{ item.employeeName }}</span>
            </div>
          </template>

          <template #item.workedDays="{ item }">
            <VChip
              size="small"
              :color="item.workedDays < item.totalDays ? 'warning' : 'success'"
              variant="tonal"
            >
              {{ $t('documents.payslip.workedDays', { worked: item.workedDays, total: item.totalDays }) }}
            </VChip>
          </template>
          <template #item.recordedBy.name="{ item }">
            <span>{{ item.recordedBy?.name || 'System / legacy' }}</span>
          </template>

          <template #item.bonus="{ item }">
            <span
              v-if="item.bonus > 0"
              class="text-info font-weight-medium"
            >+{{ formatSalaryAmount(item.bonus, item.currency) }}</span>
            <span
              v-else
              class="text-muted text-xs"
            >—</span>
          </template>

          <template #item.currency.code="{ item }">
            <VChip
              size="small"
              color="primary"
              variant="tonal"
              prepend-icon="tabler-cash"
            >
              {{ item.currency?.symbol || '' }} {{ item.currency?.code || item.cnvCurrShrt || '—' }}
            </VChip>
            <div class="text-caption text-medium-emphasis mt-1">
              Payment currency at booking · rate {{ item.rate }}
            </div>
          </template>

          <template #item.amountPaid="{ item }">
            <span class="font-weight-medium">{{ formatSalaryAmount(item.amountPaid, item.currency) }}</span>
          </template>

          <template #item.totalAmount="{ item }">
            <span class="font-weight-bold">{{ formatSalaryAmount(item.totalAmount, item.currency) }}</span>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <FullDetailsButton :item="item" />
              <VBtn
                icon="tabler-printer"
                variant="text"
                size="small"
                color="primary"
                @click="printEmployeePayslip(item)"
              >
                <VIcon icon="tabler-printer" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t('pages.payroll.staffSalary.staffSalary.printEmployeePayslip') }}
                </VTooltip>
              </VBtn>
              <VBtn
                icon="tabler-edit"
                variant="text"
                size="small"
                color="medium-emphasis"
                @click="openEditSalaryDialog(item)"
              >
                <VIcon icon="tabler-edit" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t('pages.payroll.staffSalary.staffSalary.editSalaryRecord') }}
                </VTooltip>
              </VBtn>
              <VBtn
                icon="tabler-trash"
                variant="text"
                size="small"
                color="error"
                @click="openDeleteSalaryDialog(item)"
              >
                <VIcon icon="tabler-trash" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t('pages.payroll.staffSalary.staffSalary.deleteSalaryRecord') }}
                </VTooltip>
              </VBtn>
            </div>
          </template>
        </ResponsiveDataTable>
      </div>
    </VCard>

    <PrintableListReport
      title="Staff Salaries Report"
      subtitle="Filtered salary payments"
      :columns="['Payslip', 'Employee', 'Pay month', 'Worked days', 'Currency', 'Total paid', 'Date']"
      :rows="filteredSalaries.map(salary => [salary.payslip_number, salary.employeeName, salary.salaryMonth, `${salary.workedDays}/${salary.totalDays}`, salary.currency?.code, formatSalaryAmount(salary.totalAmount, salary.currency), salary.date])"
    />

    <VDialog
      v-model="isPayslipPreviewOpen"
      width="80vw"
      max-width="1280"
      content-class="document-preview-modal"
    >
      <VCard class="document-preview-dialog">
        <VCardTitle
          class="document-preview-dialog__header"
          :dir="$i18n.locale === 'en' ? 'ltr' : 'rtl'"
        >
          <div class="document-preview-dialog__heading">
            <VAvatar
              color="primary"
              variant="tonal"
              size="38"
            >
              <VIcon icon="tabler-file-invoice" />
            </VAvatar>
            <div>
              <div class="text-h6 font-weight-bold">
                {{ $t('documents.payslip_template.payslipTemplate.officialPAYSLIP') }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ activePayslipProps?.documentNumber }}
              </div>
            </div>
          </div>
          <div class="document-preview-dialog__actions">
            <VBtn
              variant="tonal"
              color="secondary"
              @click="isPayslipPreviewOpen = false"
            >
              {{ $t('common.actions.cancel') }}
            </VBtn>
            <VBtn
              color="primary"
              variant="flat"
              prepend-icon="tabler-printer"
              @click="printPreviewedPayslip"
            >
              {{ $t('common.actions.print') }}
            </VBtn>
          </div>
        </VCardTitle>
        <VDivider />
        <VCardText class="document-preview-dialog__body">
          <PayslipTemplate
            v-if="activePayslipProps"
            ref="payslipRef"
            v-bind="activePayslipProps"
          />
        </VCardText>
      </VCard>
    </VDialog>

    <!-- Add Salary Dialog -->
    <VDialog
      v-model="isAddSalaryDialogVisible"
      max-width="800px"
    >
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">{{ $t('pages.payroll.staffSalary.staffSalary.addSalaryPayment') }}</span>
        </VCardTitle>
        <VCardText>
          <VForm class="app-form">
            <VAlert
              v-if="getSalaryErrorMessage('form') || getSalaryErrorMessage('deductions')"
              type="error"
              variant="tonal"
              class="mb-4"
            >
              {{ getSalaryErrorMessage('form') || getSalaryErrorMessage('deductions') }}
            </VAlert>
            <VRow>
              <VCol cols="12">
                <VSelect
                  v-model="salaryForm.employee_id"
                  :items="employees"
                  item-title="name"
                  item-value="id"
                  :label="$t('common.fields.employee')"
                  :error-messages="getSalaryErrorMessage('employee')"
                  required
                >
                  <template #item="{ props, item }">
                    <VListItem
                      v-bind="props"
                      :title="item.raw.name"
                      :subtitle="getEmployeePosition(item.raw)"
                    >
                      <template #prepend>
                        <VAvatar
                          size="32"
                          class="me-1"
                          :variant="item.raw.avatar ? undefined : 'tonal'"
                        >
                          <VImg
                            v-if="item.raw.avatar"
                            :src="item.raw.avatar"
                            :alt="item.raw.name"
                          />
                          <span v-else>{{ avatarText(item.raw.name) }}</span>
                        </VAvatar>
                      </template>
                    </VListItem>
                  </template>
                </VSelect>
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VTextField
                  v-model="salaryForm.salaryMonth"
                  :label="$t('common.messages.salaryMonth')"
                  type="month"
                  :error-messages="getSalaryErrorMessage('salaryMonth')"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VTextField
                  v-model.number="salaryForm.workedDays"
                  :label="$t('common.messages.workedDays')"
                  type="number"
                  step="0.5"
                  :error-messages="getSalaryErrorMessage('workedDays')"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppDateTimePicker
                  v-model="salaryForm.date"
                  :label="$t('common.messages.paymentDate')"
                  required
                  :error-messages="getSalaryErrorMessage('date')"
                  :config="{ dateFormat: 'Y-m-d' }"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VSelect
                  v-model="salaryForm.currency_id"
                  :items="currencies.filter(currency => currency.isBase)"
                  item-title="code"
                  item-value="id"
                  :label="$t('common.fields.currency')"
                  :error-messages="getSalaryErrorMessage('currency')"
                  required
                  readonly
                >
                  <template #selection="{ item }">
                    {{ item.raw.code }} ({{ item.raw.symbol }})
                  </template>
                  <template #item="{ props, item }">
                    <VListItem
                      v-bind="props"
                      :subtitle="item.raw.name"
                    />
                  </template>
                </VSelect>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="salaryForm.amountPaid"
                  :label="$t('common.messages.amountPaid')"
                  type="number"
                  :error-messages="getSalaryErrorMessage('amountPaid')"
                  required
                  @input="calculateSalaryTotal"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VCard
                  variant="tonal"
                  color="success"
                  class="h-100"
                >
                  <VCardText class="py-3">
                    <div class="d-flex justify-space-between align-center mb-2">
                      <span class="font-weight-medium">{{ $t('common.messages.grantedBonus2') }}</span>
                      <VProgressCircular
                        v-if="isLoadingBonuses"
                        indeterminate
                        size="18"
                        width="2"
                      />
                      <span
                        v-else
                        class="text-h6 font-weight-bold"
                      >{{ formatSalaryAmount(salaryForm.bonus, selectedFormCurrency) }}</span>
                    </div>
                    <div
                      v-if="grantedBonuses.length"
                      class="text-caption"
                    >
                      <div
                        v-for="bonus in grantedBonuses"
                        :key="bonus.id"
                      >
                        {{ translateBackendValue(bonus.selectedType) }} · {{ formatSalaryAmount(bonus.grantedAmount ?? bonus.selectedAmount) }}
                        → {{ formatSalaryAmount(convertedBonusAmount(bonus), selectedFormCurrency) }}
                      </div>
                    </div>
                    <div
                      v-else
                      class="text-caption text-medium-emphasis"
                    >
                      {{ $t('common.messages.noGrantedBonusForThisEmployeeAndMonth') }}
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
              <VCol cols="12">
                <VCard variant="outlined">
                  <VCardTitle class="d-flex justify-space-between align-center text-subtitle-1">
                    {{ $t('common.messages.deductions') }}
                    <VBtn
                      size="small"
                      variant="tonal"
                      prepend-icon="tabler-plus"
                      @click="addDeduction"
                    >
                      {{ $t('common.messages.addDeduction') }}
                    </VBtn>
                  </VCardTitle>
                  <VCardText>
                    <div
                      v-if="!salaryForm.deductions.length"
                      class="text-body-2 text-medium-emphasis"
                    >
                      {{ $t('common.messages.noDeductionsAdded') }}
                    </div>
                    <VRow
                      v-for="(deduction, index) in salaryForm.deductions"
                      :key="deduction.id ?? index"
                      align="center"
                    >
                      <VCol
                        cols="12"
                        md="7"
                      >
                        <VTextField
                          v-model="deduction.description"
                          :label="$t('common.fields.description')"
                          density="compact"
                          :error-messages="getSalaryErrorMessage(`deduction-${index}-description`)"
                        />
                      </VCol>
                      <VCol
                        cols="10"
                        md="4"
                      >
                        <VTextField
                          v-model.number="deduction.amount"
                          :label="$t('common.fields.amount')"
                          type="number"
                          density="compact"
                          :error-messages="getSalaryErrorMessage(`deduction-${index}-amount`)"
                        />
                      </VCol>
                      <VCol
                        cols="2"
                        md="1"
                        class="text-end"
                      >
                        <VBtn
                          icon="tabler-trash"
                          color="error"
                          variant="text"
                          size="small"
                          @click="removeDeduction(index)"
                        />
                      </VCol>
                    </VRow>
                    <div
                      v-if="salaryForm.deductions.length"
                      class="d-flex justify-space-between text-body-2 font-weight-medium mt-2"
                    >
                      <span>{{ $t('common.messages.totalDeductions') }}</span><span>{{ formatSalaryAmount(formDeductionTotal, selectedFormCurrency) }}</span>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="salaryForm.remarks"
                  :label="$t('common.messages.remarks')"
                  :error-messages="getSalaryErrorMessage('remarks')"
                  rows="2"
                  auto-grow
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isAddSalaryDialogVisible = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="isLoading"
            @click="addSalary"
          >
            {{ $t('common.actions.save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit Salary Dialog -->
    <VDialog
      v-model="isEditSalaryDialogVisible"
      max-width="800px"
    >
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">{{ $t('pages.payroll.staffSalary.staffSalary.editSalaryPayment') }}</span>
        </VCardTitle>
        <VCardText>
          <VForm class="app-form">
            <VAlert
              v-if="getSalaryErrorMessage('form') || getSalaryErrorMessage('deductions')"
              type="error"
              variant="tonal"
              class="mb-4"
            >
              {{ getSalaryErrorMessage('form') || getSalaryErrorMessage('deductions') }}
            </VAlert>
            <VRow>
              <VCol cols="12">
                <VSelect
                  v-model="salaryForm.employee_id"
                  :items="employees"
                  item-title="name"
                  item-value="id"
                  :label="$t('common.fields.employee')"
                  :error-messages="getSalaryErrorMessage('employee')"
                  disabled
                >
                  <template #item="{ props, item }">
                    <VListItem
                      v-bind="props"
                      :title="item.raw.name"
                      :subtitle="getEmployeePosition(item.raw)"
                    >
                      <template #prepend>
                        <VAvatar
                          size="32"
                          class="me-1"
                          :variant="item.raw.avatar ? undefined : 'tonal'"
                        >
                          <VImg
                            v-if="item.raw.avatar"
                            :src="item.raw.avatar"
                            :alt="item.raw.name"
                          />
                          <span v-else>{{ avatarText(item.raw.name) }}</span>
                        </VAvatar>
                      </template>
                    </VListItem>
                  </template>
                </VSelect>
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VTextField
                  v-model="salaryForm.salaryMonth"
                  :label="$t('common.messages.salaryMonth')"
                  type="month"
                  :error-messages="getSalaryErrorMessage('salaryMonth')"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VTextField
                  v-model.number="salaryForm.workedDays"
                  :label="$t('common.messages.workedDays')"
                  type="number"
                  step="0.5"
                  :error-messages="getSalaryErrorMessage('workedDays')"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppDateTimePicker
                  v-model="salaryForm.date"
                  :label="$t('common.messages.paymentDate')"
                  required
                  :error-messages="getSalaryErrorMessage('date')"
                  :config="{ dateFormat: 'Y-m-d' }"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VSelect
                  v-model="salaryForm.currency_id"
                  :items="currencies.filter(currency => currency.isBase)"
                  item-title="code"
                  item-value="id"
                  :label="$t('common.fields.currency')"
                  :error-messages="getSalaryErrorMessage('currency')"
                  disabled
                >
                  <template #selection="{ item }">
                    {{ item.raw.code }} ({{ item.raw.symbol }})
                  </template>
                  <template #item="{ props, item }">
                    <VListItem
                      v-bind="props"
                      :subtitle="item.raw.name"
                    />
                  </template>
                </VSelect>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="salaryForm.amountPaid"
                  :label="$t('common.messages.amountPaid')"
                  type="number"
                  :error-messages="getSalaryErrorMessage('amountPaid')"
                  required
                  @input="calculateSalaryTotal"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VCard
                  variant="tonal"
                  color="success"
                  class="h-100"
                >
                  <VCardText class="py-3">
                    <div class="d-flex justify-space-between align-center mb-2">
                      <span class="font-weight-medium">{{ $t('common.messages.grantedBonus2') }}</span>
                      <VProgressCircular
                        v-if="isLoadingBonuses"
                        indeterminate
                        size="18"
                        width="2"
                      />
                      <span
                        v-else
                        class="text-h6 font-weight-bold"
                      >{{ formatSalaryAmount(salaryForm.bonus, selectedFormCurrency) }}</span>
                    </div>
                    <div
                      v-if="grantedBonuses.length"
                      class="text-caption"
                    >
                      <div
                        v-for="bonus in grantedBonuses"
                        :key="bonus.id"
                      >
                        {{ translateBackendValue(bonus.selectedType) }} · {{ formatSalaryAmount(bonus.grantedAmount ?? bonus.selectedAmount) }}
                        → {{ formatSalaryAmount(bonus.salaryConversion?.convertedAmount ?? convertedBonusAmount(bonus), selectedFormCurrency) }}
                      </div>
                    </div>
                    <div
                      v-else
                      class="text-caption text-medium-emphasis"
                    >
                      {{ $t('common.messages.noGrantedBonusForThisEmployeeAndMonth') }}
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
              <VCol cols="12">
                <VCard variant="outlined">
                  <VCardTitle class="d-flex justify-space-between align-center text-subtitle-1">
                    {{ $t('common.messages.deductions') }}
                    <VBtn
                      size="small"
                      variant="tonal"
                      prepend-icon="tabler-plus"
                      @click="addDeduction"
                    >
                      {{ $t('common.messages.addDeduction') }}
                    </VBtn>
                  </VCardTitle>
                  <VCardText>
                    <div
                      v-if="!salaryForm.deductions.length"
                      class="text-body-2 text-medium-emphasis"
                    >
                      {{ $t('common.messages.noDeductionsAdded') }}
                    </div>
                    <VRow
                      v-for="(deduction, index) in salaryForm.deductions"
                      :key="deduction.id ?? index"
                      align="center"
                    >
                      <VCol
                        cols="12"
                        md="7"
                      >
                        <VTextField
                          v-model="deduction.description"
                          :label="$t('common.fields.description')"
                          density="compact"
                          :error-messages="getSalaryErrorMessage(`deduction-${index}-description`)"
                        />
                      </VCol>
                      <VCol
                        cols="10"
                        md="4"
                      >
                        <VTextField
                          v-model.number="deduction.amount"
                          :label="$t('common.fields.amount')"
                          type="number"
                          density="compact"
                          :error-messages="getSalaryErrorMessage(`deduction-${index}-amount`)"
                        />
                      </VCol>
                      <VCol
                        cols="2"
                        md="1"
                        class="text-end"
                      >
                        <VBtn
                          icon="tabler-trash"
                          color="error"
                          variant="text"
                          size="small"
                          @click="removeDeduction(index)"
                        />
                      </VCol>
                    </VRow>
                    <div
                      v-if="salaryForm.deductions.length"
                      class="d-flex justify-space-between text-body-2 font-weight-medium mt-2"
                    >
                      <span>{{ $t('common.messages.totalDeductions') }}</span><span>{{ formatSalaryAmount(formDeductionTotal, selectedFormCurrency) }}</span>
                    </div>
                  </VCardText>
                </VCard>
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="salaryForm.remarks"
                  :label="$t('common.messages.remarks')"
                  :error-messages="getSalaryErrorMessage('remarks')"
                  rows="2"
                  auto-grow
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isEditSalaryDialogVisible = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="isLoading"
            @click="updateSalary"
          >
            {{ $t('common.actions.save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Salary Dialog -->
    <VDialog
      v-model="isDeleteSalaryDialogVisible"
      max-width="500px"
    >
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">{{ $t('common.messages.confirmDelete') }}</span>
        </VCardTitle>
        <VCardText>
          {{ $t('common.confirmations.areYouSureYouWantToDeleteThisSalaryRecord') }}
          <br>
          {{ $t('common.confirmations.thisActionCannotBeUndone') }}
        </VCardText>
        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isDeleteSalaryDialogVisible = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="error"
            :loading="isLoading"
            @click="deleteSalary"
          >
            {{ $t('common.actions.delete') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.document-preview-dialog {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  height: 90vh;
  background: #edf2f7;
  overflow: hidden;
}

.document-preview-dialog__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.875rem 1.25rem;
  background: rgb(var(--v-theme-surface));
}

.document-preview-dialog__heading {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.document-preview-dialog__actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-inline-start: auto;
}

.document-preview-dialog__header[dir='rtl'] {
  flex-direction: row-reverse;
}

.document-preview-dialog__body {
  display: grid;
  place-items: start center;
  padding: 2rem;
  overflow: auto;
}
.table-container {
  overflow-x: auto;
}
</style>
