<script setup lang="ts">
import { translate } from '@/utils/localization'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { VDialog } from 'vuetify/components'
import PayslipTemplate, { type SalarySlipProps } from './templates/payslip_template.vue'
import SalaryApi, { type AttendanceBreakdown, type CreateSalaryData, type Salary, type SalaryDeduction, type UpdateSalaryData } from '@/api/salary'
import { type WorthyEmployee } from '@/api/bonuslist'
import EmployeeApi, { type Employee } from '@/api/employee'
import CurrencyApi, { type Currency } from '@/api/currency'
import SettingApi, { type Setting } from '@/api/setting'
import { apiFieldErrors } from '@/utils/apiErrors'
import { printDocument } from '@/utils/printDocument'
import PrintableListReport from '@/components/PrintableListReport.vue'
import { avatarText } from '@/@core/utils/formatters'
import { formatLocalizedDate, formatLocalizedNumber } from '@/utils/localization'
import { useToast } from 'vue-toastification'
import { translateBackendValue } from '@/utils/localization'

const toast = useToast()
const { t } = useI18n()

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
const salaryMonthEmployeeFilter = ref<number | null>(null)
const isLoading = ref(false)

// The payroll workspace is divided into subpages like the educational
// master-data page: individual salary records and the month-by-month
// balance summary share one card and switch through these tabs.
const activeTab = ref<'salaries' | 'months'>('salaries')

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

const translatePaymentStatus = (status: string) => status === 'partially_paid'
  ? translate('common.statuses.partial')
  : translateBackendValue(status)

const getStatusColor = (salary: Salary) => {
  const status = activeTab.value === 'months' ? salary.paymentStatus : salary.paymentStatus
  return status === 'paid' ? 'success' : status === 'partially_paid' ? 'warning' : 'secondary'
}

const getStatusLabel = (salary: Salary) => {
  const status = activeTab.value === 'months' ? salary.paymentStatus : salary.paymentStatus
  return translatePaymentStatus(status)
}

const paymentTotalsByCurrency = computed(() => {
  return filteredSalaries.value.reduce<Record<string, { amount: number; currency: Salary['currency'] }>>((totals, salary) => {
    const key = salary.currency?.code ?? 'unknown'
    if (!totals[key])
      totals[key] = { amount: 0, currency: salary.currency }
    totals[key].amount += salary.totalAmount

    return totals
  }, {})
})

// Payroll periods are distinct from individual disbursements. This summary
// gives finance staff the month-by-month balance requested by the business.
const salaryMonths = computed(() => filteredSalaries.value
  .filter(salary => !salaryMonthEmployeeFilter.value || salary.employee_id === salaryMonthEmployeeFilter.value)
  .map(salary => ({
    ...salary,
    due: Number(salary.amountDue ?? salary.netAmount),
    paid: Number(salary.amountPaid ?? 0),
    outstanding: Number(salary.amountOutstanding ?? 0),
  })))

// The absence/leave deduction is locked and derived from the editable
// worked-days input: base salary is prorated by the share of unworked days.
const formMissingDays = computed(() => Math.max(0, Number(salaryForm.value.totalDays || 0) - Number(salaryForm.value.workedDays || 0)))
const automaticDeduction = computed(() => {
  const total = Number(salaryForm.value.totalDays || 0)
  if (total <= 0)
    return 0

  return Number(salaryForm.value.amountPaid || 0) * formMissingDays.value / total
})
const formDeductionTotal = computed(() => automaticDeduction.value + salaryForm.value.deductions.reduce((sum, deduction) => sum + Number(deduction.amount || 0), 0))
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

// Prefill the editable worked/total day inputs from the attendance ledger.
const fetchAttendanceBreakdown = async () => {
  const { employee_id, salaryMonth } = salaryForm.value

  if (!employee_id || !/^\d{4}-(0[1-9]|1[0-2])$/.test(salaryMonth))
    return

  try {
    const response = await SalaryApi.getAttendanceBreakdown(employee_id, salaryMonth)

    // Ignore a stale response if the selection changed while loading.
    if (salaryForm.value.employee_id !== employee_id || salaryForm.value.salaryMonth !== salaryMonth)
      return

    salaryForm.value.workedDays = response.data.workedDays
    salaryForm.value.totalDays = response.data.totalDays
  }
  catch (error) {
    console.error('Error fetching attendance breakdown:', error)
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
    label: deduction.isAutomatic ? translate('common.messages.automaticAttendanceDeduction') : deduction.description,
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
    letterheadUrl: companySetting.value?.letterheads?.payslip?.url || undefined,
    letterheadSafe: companySetting.value?.letterheads?.payslip?.safe,
    employee: {
      name: salary.employeeName,
      designation: getEmployeePosition(emp),
      department: emp?.department || '—',
      dateOfJoining: emp?.join_date || '—',
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
  fetchAttendanceBreakdown()
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
    // Automatic lines are owned by the payroll calculation and rendered
    // separately; only manual deductions are editable here.
    deductions: salary.deductions.filter(deduction => !deduction.isAutomatic).map(deduction => ({ ...deduction })),
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

const printTable = () => void printDocument({ bodyClass: 'printing-table-salaries', pageStyle: 'size: A4 landscape; margin: 12mm;' })

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
  if (isAddSalaryDialogVisible.value) {
    fetchGrantedBonuses()
    fetchAttendanceBreakdown()
  }
})

watch(() => salaryForm.value.employee_id, applyEmployeeSalary)
</script>

<template>
  <div class="education-workspace">
    <VCard class="education-panel">
      <VCardTitle class="d-flex justify-space-between">
        <span>{{ $t('pages.payroll.staffSalary.staffSalary.title') }}</span>
        <VBtn v-if="activeTab === 'salaries'" prepend-icon="tabler-plus" @click="openAddSalaryDialog">{{ $t('pages.payroll.staffSalary.staffSalary.addSalary') }}</VBtn>
      </VCardTitle>
      <VCardText>
        <VTabs v-model="activeTab" show-arrows class="mb-4">
          <VTab value="salaries">{{ $t('pages.payroll.staffSalary.staffSalary.salariesTab') }}</VTab>
          <VTab value="months">{{ $t('pages.payroll.staffSalary.staffSalary.salaryMonthsTab') }}</VTab>
        </VTabs>
        <VProgressLinear v-if="isLoading" indeterminate class="my-4" />
        <VWindow v-model="activeTab">
          <VWindowItem value="salaries">
            <div class="education-table-scroll">
              <VTable density="compact" class="education-table">
                <thead>
                  <tr>
                    <th>{{ $t('pages.payroll.staffSalary.staffSalary.payslipNumber') }}</th>
                    <th>{{ $t('common.fields.employee') }}</th>
                    <th>{{ $t('pages.payroll.staffSalary.staffSalary.payMonth') }}</th>
                    <th>{{ $t('common.fields.currency') }}</th>
                    <th>{{ $t('pages.payroll.staffSalary.staffSalary.totalPaid') }}</th>
                    <th>{{ $t('common.fields.status') }}</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="salary in filteredSalaries" :key="salary.id">
                    <td>{{ salary.payslip_number }}</td>
                    <td>{{ salary.employeeName }}</td>
                    <td>{{ salary.salaryMonth }}</td>
                    <td>
                      <VChip size="small" color="primary" variant="tonal" prepend-icon="tabler-cash">{{ salary.currency?.symbol || '' }} {{ salary.currency?.code || '—' }}</VChip>
                    </td>
                    <td class="font-weight-bold">{{ formatSalaryAmount(salary.totalAmount, salary.currency) }}</td>
                    <td>
                      <VChip size="small" :color="getStatusColor(salary)" variant="tonal">{{ getStatusLabel(salary) }}</VChip>
                    </td>
                    <td class="text-no-wrap">
                      <VBtn icon="tabler-printer" size="small" variant="text" color="primary" @click="printEmployeePayslip(salary)" />
                      <VBtn icon="tabler-edit" size="small" variant="text" @click="openEditSalaryDialog(salary)" />
                      <VBtn icon="tabler-trash" size="small" variant="text" color="error" @click="openDeleteSalaryDialog(salary)" />
                    </td>
                  </tr>
                  <tr v-if="filteredSalaries.length === 0">
                    <td colspan="7" class="education-empty">{{ $t('common.messages.noRecordsFound') }}</td>
                  </tr>
                </tbody>
              </VTable>
            </div>
            <div class="table-toolbar mt-4">
              <VTextField v-model="searchQuery" prepend-inner-icon="tabler-search" :placeholder="$t('pages.payroll.staffSalary.staffSalary.searchSalaries')" density="compact" clearable hide-details class="data-table-search" style="max-width: 400px;" />
              <DataTableFilterMenu :active="Boolean(salaryMonthFilter || selectedSalaryAmountRange)" @reset="salaryMonthFilter = null; selectedSalaryAmountRange = null">
                <VSelect v-model="salaryMonthFilter" :items="[{ title: translate('common.messages.allMonths'), value: null }, ...Array.from({ length: 12 }, (_, i) => { const date = new Date(); date.setMonth(date.getMonth() - i); return { title: formatLocalizedDate(date, { month: 'long', year: 'numeric' }), value: date.toISOString().slice(0, 7) } })]" :label="$t('pages.payroll.staffSalary.staffSalary.filterByMonth')" density="compact" clearable hide-details />
                <VSelect v-model="selectedSalaryAmountRange" :items="[{ title: translate('pages.payroll.staffSalary.staffSalary.under40000'), value: 'low' }, { title: '40,000 - 70,000', value: 'mid' }, { title: translate('pages.payroll.staffSalary.staffSalary.over70000'), value: 'high' }]" :label="$t('pages.payroll.staffSalary.staffSalary.filterByAmount')" density="compact" clearable hide-details />
              </DataTableFilterMenu>
              <VBtn icon="tabler-printer" variant="text" @click="printTable" class="ml-2"><VIcon icon="tabler-printer" /><VTooltip activator="parent" location="top">{{ $t('pages.payroll.staffSalary.staffSalary.printAllSalaryRecords') }}</VTooltip></VBtn>
            </div>
          </VWindowItem>
          <VWindowItem value="months">
            <div v-if="salaryMonths.length" class="mb-4">
              <VSelect v-model="salaryMonthEmployeeFilter" :items="employees.map(employee => ({ title: employee.name, value: employee.id }))" :label="$t('pages.payroll.staffSalary.staffSalary.filterByEmployee')" clearable density="compact" hide-details style="min-width: 240px; max-inline-size: 320px" />
            </div>
            <div class="education-table-scroll">
              <VTable density="compact" class="education-table">
                <thead>
                  <tr>
                    <th>{{ $t('common.fields.employee') }}</th>
                    <th>{{ $t('pages.payroll.staffSalary.staffSalary.payMonth') }}</th>
                    <th>{{ $t('pages.payroll.staffSalary.staffSalary.due') }}</th>
                    <th>{{ $t('pages.payroll.staffSalary.staffSalary.paid') }}</th>
                    <th>{{ $t('pages.payroll.staffSalary.staffSalary.remaining') }}</th>
                    <th>{{ $t('common.fields.status') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="salary in salaryMonths" :key="salary.id">
                    <td>{{ salary.employeeName }}</td>
                    <td>{{ salary.salaryMonth }}</td>
                    <td>{{ formatSalaryAmount(salary.due, salary.currency) }}</td>
                    <td>{{ formatSalaryAmount(salary.paid, salary.currency) }}</td>
                    <td><strong :class="salary.outstanding > 0 ? 'text-warning' : 'text-success'">{{ formatSalaryAmount(salary.outstanding, salary.currency) }}</strong></td>
                    <td>
                      <VChip size="small" :color="getStatusColor(salary)" variant="tonal">{{ getStatusLabel(salary) }}</VChip>
                    </td>
                  </tr>
                  <tr v-if="salaryMonths.length === 0">
                    <td colspan="6" class="education-empty">{{ $t('common.messages.noRecordsFound') }}</td>
                  </tr>
                </tbody>
              </VTable>
            </div>
          </VWindowItem>
        </VWindow>
      </VCardText>
    </VCard>
    <PrintableListReport v-if="activeTab === 'salaries'" :title="$t('common.crmUi.printReports.salaries.title')" :subtitle="$t('common.crmUi.printReports.salaries.subtitle')" :columns="[$t('common.crmUi.printReports.columns.payslip'), $t('common.crmUi.printReports.columns.employee'), $t('common.crmUi.printReports.columns.payMonth'), $t('common.crmUi.printReports.columns.workedDays'), $t('common.crmUi.printReports.columns.currency'), $t('common.crmUi.printReports.columns.totalPaid'), $t('common.crmUi.printReports.columns.date')]" :rows="filteredSalaries.map(salary => [salary.payslip_number, salary.employeeName, salary.salaryMonth, `${salary.workedDays}/${salary.totalDays}`, salary.currency?.code, formatSalaryAmount(salary.totalAmount, salary.currency), salary.date])" />
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
              <VIcon icon="tabler-file-description" />
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
                md="3"
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
                md="3"
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
                md="3"
              >
                <VTextField
                  v-model.number="salaryForm.totalDays"
                  :label="$t('common.messages.totalDays')"
                  type="number"
                  :error-messages="getSalaryErrorMessage('totalDays')"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="3"
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
                    <VRow
                      v-if="automaticDeduction > 0"
                      align="center"
                      class="mb-1"
                    >
                      <VCol
                        cols="12"
                        md="7"
                      >
                        <div class="text-body-2 font-weight-medium">
                          {{ $t('common.messages.automaticAttendanceDeduction') }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          {{ $t('common.messages.unpaidAndAbsentDays', { days: formMissingDays }) }}
                        </div>
                      </VCol>
                      <VCol
                        cols="12"
                        md="5"
                        class="text-end font-weight-medium"
                      >
                        {{ formatSalaryAmount(automaticDeduction, selectedFormCurrency) }}
                      </VCol>
                    </VRow>
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
                md="3"
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
                md="3"
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
                md="3"
              >
                <VTextField
                  v-model.number="salaryForm.totalDays"
                  :label="$t('common.messages.totalDays')"
                  type="number"
                  :error-messages="getSalaryErrorMessage('totalDays')"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="3"
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
                    <VRow
                      v-if="automaticDeduction > 0"
                      align="center"
                      class="mb-1"
                    >
                      <VCol
                        cols="12"
                        md="7"
                      >
                        <div class="text-body-2 font-weight-medium">
                          {{ $t('common.messages.automaticAttendanceDeduction') }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          {{ $t('common.messages.unpaidAndAbsentDays', { days: formMissingDays }) }}
                        </div>
                      </VCol>
                      <VCol
                        cols="12"
                        md="5"
                        class="text-end font-weight-medium"
                      >
                        {{ formatSalaryAmount(automaticDeduction, selectedFormCurrency) }}
                      </VCol>
                    </VRow>
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

.staff-salary-currency-chip {
  margin-inline: 0.5rem;
}
</style>
