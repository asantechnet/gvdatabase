<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useToast } from "vue-toastification";
import { VDialog } from "vuetify/components";
import SalaryApi, {
  type PayrollRegisterRow,
  type PayrollRegisterTotals,
  type Salary,
  type SalaryDeduction,
} from "@/api/salary";
import CurrencyApi, { type Currency } from "@/api/currency";
import EmployeeApi, { type Employee } from "@/api/employee";
import EmployeeAdvanceApi, {
  type EmployeeAdvance,
  type CreateEmployeeAdvanceData,
} from "@/api/employeeAdvances";
import SettingApi, { type Setting } from "@/api/setting";
import PayslipTemplate, {
  type SalarySlipProps,
} from "./templates/payslip_template.vue";
import AdvanceReceiptTemplate, {
  type AdvanceReceiptData,
} from "./templates/advance_receipt_template.vue";
import PrintableListReport from "@/components/PrintableListReport.vue";
import ResponsiveDataTableServer from "@/components/responsive-data-table/ResponsiveDataTableServer.vue";
import {
  formatActiveMoney,
  formatLocalizedDate,
  translate,
} from "@/utils/localization";
import { printDocument } from "@/utils/printDocument";
import { moveCreatedRowFirst } from "@/utils/createdFirst";

const toast = useToast();
const { t: $t, locale } = useI18n();

interface PayrollRow {
  [key: string]: unknown;
  id: number;
  employeeId: number;
  name: string;
  position: string;
  base: number;
  workedDays: number | null;
  totalDays: number | null;
  offDays: number | null;
  absentDays: number | null;
  net: number | null;
  paid: number;
  outstanding: number;
  status: "missing" | "open" | "partially_paid" | "paid";
  salary: Salary | null;
}

const today = new Date();
const selectedYear = ref(today.getFullYear());
const selectedMonth = ref(today.getMonth() + 1);
const month = computed(
  () => `${selectedYear.value}-${String(selectedMonth.value).padStart(2, "0")}`,
);
const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear();

  return Array.from({ length: 11 }, (_, index) => {
    const year = currentYear - index;

    return {
      title: new Intl.NumberFormat(locale.value, { useGrouping: false }).format(year),
      value: year,
    };
  });
});
const monthOptions = computed(() =>
  Array.from({ length: 12 }, (_, index) => ({
    title: new Intl.DateTimeFormat(locale.value, { month: "long" }).format(
      new Date(2020, index, 1),
    ),
    value: index + 1,
  })),
);
const activeTab = ref<"payslips" | "advances">("payslips");
const search = ref("");
const employees = ref<Employee[]>([]);
// The register is employee-driven and paged on the server; `employees` stays
// the complete list the form pickers and receipts need.
const registerRows = ref<PayrollRegisterRow[]>([]);
const page = ref(1);
const perPage = ref(25);
const total = ref(0);
// Filled from every register row right before printing.
const reportRows = ref<string[][]>([]);
const salaries = ref<Salary[]>([]);
const advances = ref<EmployeeAdvance[]>([]);
const advancesPage = ref(1);
const advancesPerPage = ref(25);
const advancesTotal = ref(0);
const companySetting = ref<Setting | null>(null);
const baseCurrencyId = ref<number | null>(null);
const baseCurrency = ref<Pick<
  Currency,
  "id" | "code" | "symbol" | "decimalPlaces" | "rate"
> | null>(null);
const loading = ref(false);
const saving = ref(false);

// Dialog state ------------------------------------------------------------
const generateDialog = ref(false);
const deleteSalaryDialog = ref(false);
const selectedSalary = ref<Salary | null>(null);

const createDialog = ref(false);
const createForm = ref({
  employee_id: null as number | null,
  amountPaid: 0,
  date: today.toISOString().slice(0, 10),
  remarks: "",
  deductions: [] as SalaryDeduction[],
});

const deductionDialog = ref(false);
const deductionForm = ref({ description: "", amount: 0 });

const paymentDialog = ref(false);
const paymentForm = ref({
  amount: 0,
  date: today.toISOString().slice(0, 10),
  remarks: "",
});

const payslipDialog = ref(false);
const payslipProps = ref<SalarySlipProps | null>(null);
const payslipRef = ref<InstanceType<typeof PayslipTemplate> | null>(null);

const advanceDialog = ref(false);
const advanceForm = ref<{
  employee_id: number | null;
  amount: number;
  date: string;
  remarks: string;
}>({
  employee_id: null,
  amount: 0,
  date: today.toISOString().slice(0, 10),
  remarks: "",
});
const deleteAdvanceDialog = ref(false);
const selectedAdvance = ref<EmployeeAdvance | null>(null);
const settleDialog = ref(false);
const settleForm = ref({
  amount: 0,
  date: today.toISOString().slice(0, 10),
  remarks: "",
});
const editAdvanceDialog = ref(false);
const editAdvanceForm = ref({
  amount: 0,
  date: today.toISOString().slice(0, 10),
  remarks: "",
});
const advanceReceiptDialog = ref(false);
const advanceReceipt = ref<AdvanceReceiptData | null>(null);
const advanceReceiptRef = ref<InstanceType<
  typeof AdvanceReceiptTemplate
> | null>(null);

// Derived data ------------------------------------------------------------
const salaryByEmployee = computed(
  () => new Map(salaries.value.map((salary) => [salary.employee_id, salary])),
);

const toPayrollRows = (register: PayrollRegisterRow[]): PayrollRow[] =>
  register.map(({ employee, salary: rowSalary }) => {
    const salary = rowSalary ?? salaryByEmployee.value.get(employee.id) ?? null;
    const base = salary
      ? Number(salary.totalAmount) - Number(salary.bonus ?? 0)
      : Number(employee.salary ?? 0);

    return {
      id: employee.id,
      employeeId: employee.id,
      name: employee.name,
      position: typeof employee.position === "string" ? employee.position : "",
      base,
      workedDays: salary ? Number(salary.workedDays ?? 0) : null,
      totalDays: salary ? Number(salary.totalDays ?? 0) : null,
      offDays: salary ? Number(salary.offDays ?? 0) : null,
      absentDays: salary ? Number(salary.absentDays ?? 0) : null,
      net: salary ? Number(salary.netAmount ?? 0) : null,
      paid: salary ? Number(salary.amountPaid ?? 0) : 0,
      outstanding: salary ? Number(salary.amountOutstanding ?? 0) : 0,
      status: !salary ? "missing" : (salary.paymentStatus ?? "open"),
      salary,
    };
  });

const rows = computed<PayrollRow[]>(() => toPayrollRows(registerRows.value));

// The month-wide figures come from the API, so they describe the whole
// register rather than the visible page.
const emptyTotals: PayrollRegisterTotals = {
  net: 0,
  paid: 0,
  outstanding: 0,
  generated: 0,
  missing: 0,
};
const totals = ref<PayrollRegisterTotals>({ ...emptyTotals });

// The register is printed in the payslip currency; when the visible page has
// no payslips yet (nothing generated) the company's base currency is used.
const currencyCode = computed(
  () => salaries.value[0]?.currency?.code ?? baseCurrency.value?.code ?? "AFN",
);
const currencySymbol = computed(
  () => salaries.value[0]?.currency?.symbol ?? baseCurrency.value?.symbol ?? "",
);
const displayMoneyCurrency = computed(
  () =>
    salaries.value[0]?.currency ??
    baseCurrency.value ?? { code: currencyCode.value },
);

const companyProfile = computed(() => ({
  name:
    companySetting.value?.original_company_name ||
    companySetting.value?.website_title ||
    "",
  brandName:
    companySetting.value?.short_company_name ||
    companySetting.value?.website_title ||
    "",
  addressLines: [companySetting.value?.physical_address].filter(
    Boolean,
  ) as string[],
  email: companySetting.value?.company_email || "",
  phone: companySetting.value?.phone || "",
  tinNumber: companySetting.value?.business_licence || undefined,
  logo: companySetting.value?.website_logo_url || undefined,
  signature: companySetting.value?.company_signature_url || undefined,
  primaryColor: companySetting.value?.primary_color || undefined,
  secondaryColor: companySetting.value?.secondary_color || undefined,
}));

// One formatter for the whole register: the currency code appears exactly once
// and no symbol is concatenated by hand.
const formatAmount = (amount: number | null | undefined) =>
  formatActiveMoney(amount, displayMoneyCurrency.value);

// Loading ----------------------------------------------------------------
// (see `load` below, which pages the register and the advances ledger)
async function load() {
  loading.value = true;
  try {
    const [
      register,
      employeeRows,
      advanceResponse,
      settingResponse,
      currencyResponse,
    ] = await Promise.all([
      SalaryApi.register({
        month: month.value,
        search: search.value.trim() || undefined,
        page: page.value,
        perPage: perPage.value,
      }),
      EmployeeApi.getOptions(),
      EmployeeAdvanceApi.listPage({
        page: advancesPage.value,
        perPage: advancesPerPage.value,
      }),
      SettingApi.getCompanyProfile(),
      CurrencyApi.getAll(),
    ]);

    registerRows.value = register.rows;
    total.value = register.total;
    totals.value = register.totals ?? { ...emptyTotals };
    // The month's payslips of this page drive the currency shown on screen and
    // the payslip dialogs; the full employee list serves the pickers.
    salaries.value = register.rows
      .map((row) => row.salary)
      .filter((salary): salary is Salary => salary !== null);
    employees.value = employeeRows;
    advances.value = advanceResponse.rows;
    advancesTotal.value = advanceResponse.total;
    companySetting.value = settingResponse.data;
    const base =
      currencyResponse.data.find((currency) => currency.isBase) ?? null;

    baseCurrency.value = base;
    baseCurrencyId.value = base?.id ?? null;
  } catch (error) {
  } finally {
    loading.value = false;
  }
}

function updateTableOptions(options: { page: number; itemsPerPage: number }) {
  if (page.value === options.page && perPage.value === options.itemsPerPage)
    return;

  page.value = options.page;
  perPage.value = options.itemsPerPage;
  void load();
}

function updateAdvancesTableOptions(options: {
  page: number;
  itemsPerPage: number;
}) {
  if (
    advancesPage.value === options.page &&
    advancesPerPage.value === options.itemsPerPage
  )
    return;

  advancesPage.value = options.page;
  advancesPerPage.value = options.itemsPerPage;
  void load();
}

// Typing in the register search is a filter change, so it starts from the first page.
const debouncedRegisterSearch = useDebounceFn(() => {
  page.value = 1;
  void load();
}, 300);

// Payroll actions --------------------------------------------------------
async function generateMissing() {
  saving.value = true;
  try {
    const result = await SalaryApi.generate(month.value);

    toast.success(
      $t("pages.payrolls.generatedCount", {
        created: result.created,
        skipped: result.skipped,
      }),
    );
    generateDialog.value = false;
    await load();
  } catch (error) {
  } finally {
    saving.value = false;
  }
}

function openCreate(row: PayrollRow) {
  createForm.value = {
    employee_id: row.employeeId,
    amountPaid: row.base,
    date: today.toISOString().slice(0, 10),
    remarks: "",
    deductions: [],
  };
  createDialog.value = true;
}

async function saveCreate() {
  if (!createForm.value.employee_id) return;

  saving.value = true;
  try {
    if (!baseCurrencyId.value) {
      toast.error($t("pages.payrolls.baseCurrencyMissing"));

      return;
    }

    const created = await SalaryApi.create({
      employee_id: createForm.value.employee_id,
      salaryMonth: month.value,
      currency_id: baseCurrencyId.value,
      rate: 1,
      amountPaid: Number(createForm.value.amountPaid),
      remarks: createForm.value.remarks || undefined,
      date: createForm.value.date,
      deductions: createForm.value.deductions,
    });
    toast.success($t("pages.payrolls.saved"));
    createDialog.value = false;
    await load();
    const createdIndex = registerRows.value.findIndex(row => row.salary?.id === created.data.id);
    if (createdIndex > 0)
      registerRows.value.unshift(...registerRows.value.splice(createdIndex, 1));
  } catch (error) {
  } finally {
    saving.value = false;
  }
}

function openDeductions(salary: Salary) {
  selectedSalary.value = salary;
  deductionForm.value = { description: "", amount: 0 };
  deductionDialog.value = true;
}

async function addDeduction() {
  if (!selectedSalary.value) return;

  saving.value = true;
  try {
    await SalaryApi.createDeduction(selectedSalary.value.id, {
      description: deductionForm.value.description,
      amount: Number(deductionForm.value.amount),
    });
    toast.success($t("pages.payrolls.deductionSaved"));
    deductionForm.value = { description: "", amount: 0 };
    await refreshSelectedSalary();
  } catch (error) {
  } finally {
    saving.value = false;
  }
}

async function removeDeduction(deduction: SalaryDeduction) {
  if (!selectedSalary.value || !deduction.id) return;

  saving.value = true;
  try {
    await SalaryApi.deleteDeduction(selectedSalary.value.id, deduction.id);
    toast.success($t("pages.payrolls.deductionDeleted"));
    await refreshSelectedSalary();
  } catch (error) {
  } finally {
    saving.value = false;
  }
}

async function refreshSelectedSalary() {
  if (!selectedSalary.value) return;
  const response = await SalaryApi.getById(selectedSalary.value.id);

  selectedSalary.value = response.data;
  salaries.value = salaries.value.map((salary) =>
    salary.id === response.data.id ? response.data : salary,
  );
}

function openPayment(salary: Salary) {
  selectedSalary.value = salary;
  paymentForm.value = {
    amount: Number(salary.amountOutstanding ?? 0),
    date: today.toISOString().slice(0, 10),
    remarks: "",
  };
  paymentDialog.value = true;
}

async function savePayment() {
  if (!selectedSalary.value) return;

  saving.value = true;
  try {
    await SalaryApi.addPayment(selectedSalary.value.id, {
      amount: Number(paymentForm.value.amount),
      date: paymentForm.value.date,
      remarks: paymentForm.value.remarks || undefined,
    });
    toast.success($t("pages.payrolls.paymentSaved"));
    paymentDialog.value = false;
    await load();
  } catch (error) {
  } finally {
    saving.value = false;
  }
}

function confirmDelete(salary: Salary) {
  selectedSalary.value = salary;
  deleteSalaryDialog.value = true;
}

async function deleteSalary() {
  if (!selectedSalary.value) return;

  saving.value = true;
  try {
    await SalaryApi.delete(selectedSalary.value.id);
    toast.success($t("pages.payrolls.deleted"));
    deleteSalaryDialog.value = false;
    await load();
  } catch (error) {
  } finally {
    saving.value = false;
  }
}

async function openPayslip(row: PayrollRow) {
  const salary = row.salary;
  if (!salary) return;

  const employee = employees.value.find((item) => item.id === row.employeeId);
  const earnings = [
    {
      label: $t("pages.payrolls.baseSalary"),
      amount: Number(salary.totalAmount) - Number(salary.bonus ?? 0),
    },
  ];
  if (Number(salary.bonus ?? 0) > 0)
    earnings.push({
      label: $t("pages.payrolls.bonus"),
      amount: Number(salary.bonus),
    });

  payslipProps.value = {
    documentTitle: translate(
      "documents.payslip_template.payslipTemplate.officialPAYSLIP",
    ),
    documentNumber: salary.payslip_number,
    payslipSubtitle: $t("pages.payrolls.payPeriodLabel", {
      month: salary.salaryMonth,
    }),
    currency: salary.currency?.code ?? currencyCode.value,
    company: companyProfile.value,
    employee: {
      name: salary.employeeName,
      designation: getEmployeePosition(employee),
      department: employee?.department || "—",
      dateOfJoining: employee?.join_date || "—",
      payPeriod: salary.salaryMonth,
      payDate: salary.date,
      workedDays: Number(salary.workedDays ?? 0),
      totalDays: Number(salary.totalDays ?? 0),
      offDays: Number(salary.offDays ?? 0),
      absentDays: Number(salary.absentDays ?? 0),
      tazkiraNumber: employee?.id_number || "N/A",
      accountNumber: employee?.bank_account_number || "N/A",
    },
    earnings,
    deductions: (salary.deductions ?? []).map((deduction) => ({
      label: deduction.code === "advance_recovery"
        ? $t("pages.payroll.staffSalary.staffSalary.advanceRecovery")
        : deduction.isAutomatic
          ? $t("pages.payrolls.automaticDeduction")
          : deduction.description,
      amount: Number(deduction.amount),
    })),
    advances: (salary.advances ?? []).map((advance) => ({
      label: `${advance.advanceNumber ?? "—"}${advance.date ? ` · ${formatLocalizedDate(advance.date)}` : ""}`,
      amount: Number(advance.deductedAmount ?? advance.amount),
    })),
    advanceRecovery: Number(salary.advanceRecovery ?? 0),
    reimbursements: [],
  };
  payslipDialog.value = true;
  await nextTick();
}

function printPayslip() {
  payslipRef.value?.print();
}

// Print is global: the report is filled from every register row first.
const registerReportRow = (row: PayrollRow): string[] => [
  row.name,
  formatAmount(row.base),
  row.salary ? `${row.workedDays}/${row.totalDays}` : "—",
  row.offDays === null ? "—" : String(row.offDays),
  row.absentDays === null ? "—" : String(row.absentDays),
  formatAmount(row.net),
  formatAmount(row.paid),
  formatAmount(row.outstanding),
];

async function printRegister() {
  try {
    const collected: PayrollRow[] = [];
    let nextPage = 1;
    let lastPage = 1;

    do {
      const result = await SalaryApi.register({
        month: month.value,
        search: search.value.trim() || undefined,
        page: nextPage,
        perPage: 100,
      });

      collected.push(...toPayrollRows(result.rows));
      lastPage = result.lastPage;
      nextPage += 1;
    } while (nextPage <= lastPage);

    reportRows.value = collected.map(registerReportRow);
    await printDocument({
      bodyClass: "printing-table-payrolls",
      pageStyle: "size: A4 landscape; margin: 10mm;",
    });
  } catch (error) {
  }
}

function getEmployeePosition(employee: Employee | undefined): string {
  if (!employee) return $t("pages.payrolls.noPosition");

  return typeof employee.position === "string" && employee.position
    ? employee.position
    : $t("pages.payrolls.noPosition");
}

// Salary advances --------------------------------------------------------
function openAdvanceDialog() {
  advanceForm.value = {
    employee_id: null,
    amount: 0,
    date: today.toISOString().slice(0, 10),
    remarks: "",
  };
  advanceDialog.value = true;
}

async function saveAdvance() {
  if (!advanceForm.value.employee_id) return;

  saving.value = true;
  try {
    const created = await EmployeeAdvanceApi.create({
      employee_id: advanceForm.value.employee_id,
      amount: Number(advanceForm.value.amount),
      date: advanceForm.value.date,
      remarks: advanceForm.value.remarks,
    } as CreateEmployeeAdvanceData);
    toast.success($t("pages.payrolls.advanceSaved"));
    advanceDialog.value = false;
    advancesPage.value = 1;
    await load();
    advances.value = moveCreatedRowFirst(advances.value, created).slice(0, advancesPerPage.value);
  } catch (error) {
  } finally {
    saving.value = false;
  }
}

function openSettle(advance: EmployeeAdvance) {
  selectedAdvance.value = advance;
  settleForm.value = {
    amount: Number(advance.remainingAmount ?? advance.amount),
    date: today.toISOString().slice(0, 10),
    remarks: "",
  };
  settleDialog.value = true;
}

async function saveSettlement() {
  if (!selectedAdvance.value) return;

  saving.value = true;
  try {
    await EmployeeAdvanceApi.settle(selectedAdvance.value.id, {
      amount: Number(settleForm.value.amount),
      date: settleForm.value.date,
      remarks: settleForm.value.remarks,
    });
    toast.success($t("pages.payrolls.repaymentSaved"));
    settleDialog.value = false;
    await load();
  } catch (error) {
  } finally {
    saving.value = false;
  }
}

function openEditAdvance(advance: EmployeeAdvance) {
  selectedAdvance.value = advance;
  editAdvanceForm.value = {
    amount: Number(advance.amount),
    date: advance.date,
    remarks: advance.remarks ?? "",
  };
  editAdvanceDialog.value = true;
}

async function saveAdvanceEdit() {
  if (!selectedAdvance.value) return;

  saving.value = true;
  try {
    await EmployeeAdvanceApi.update(selectedAdvance.value.id, {
      amount: Number(editAdvanceForm.value.amount),
      date: editAdvanceForm.value.date,
      remarks: editAdvanceForm.value.remarks,
    });
    toast.success($t("pages.payrolls.advanceUpdated"));
    editAdvanceDialog.value = false;
    await load();
  } catch (error) {
  } finally {
    saving.value = false;
  }
}

/** The advance deduction is optional while the payslip has not paid out. */
async function toggleAdvanceRecovery(row: PayrollRow) {
  const salary = row.salary;
  if (!salary) return;

  saving.value = true;
  try {
    await SalaryApi.setAdvanceRecovery(
      salary.id,
      Number(salary.advanceRecovery ?? 0) <= 0,
    );
    toast.success($t("pages.payrolls.advanceRecoveryUpdated"));
    await load();
  } catch (error) {
  } finally {
    saving.value = false;
  }
}

function advanceStatusLabel(status: EmployeeAdvance["status"]) {
  return {
    settled: $t("pages.payrolls.settledAdvance"),
    partial: $t("pages.payrolls.partiallySettledAdvance"),
    outstanding: $t("pages.payrolls.outstandingAdvance"),
  }[status ?? "outstanding"];
}

function advanceStatusColor(status: EmployeeAdvance["status"]) {
  return {
    settled: "success",
    partial: "info",
    outstanding: "warning",
  }[status ?? "outstanding"];
}

function confirmDeleteAdvance(advance: EmployeeAdvance) {
  selectedAdvance.value = advance;
  deleteAdvanceDialog.value = true;
}

async function deleteAdvance() {
  if (!selectedAdvance.value) return;

  saving.value = true;
  try {
    await EmployeeAdvanceApi.remove(selectedAdvance.value.id);
    toast.success($t("pages.payrolls.advanceDeleted"));
    deleteAdvanceDialog.value = false;
    await load();
  } catch (error) {
  } finally {
    saving.value = false;
  }
}

function openAdvanceReceipt(advance: EmployeeAdvance) {
  const employee = employees.value.find(
    (item) => item.id === advance.employee_id,
  );
  advanceReceipt.value = {
    advanceNumber: advance.advanceNumber,
    date: advance.date,
    amount: advance.amount,
    currency: advance.currency,
    remarks: advance.remarks,
    employee: {
      name: advance.employeeName,
      idNumber: employee?.id_number ?? null,
      position: getEmployeePosition(employee),
      department: employee?.department ?? employee?.department_name ?? null,
    },
    company: companyProfile.value,
  };
  advanceReceiptDialog.value = true;
  void nextTick(() => advanceReceiptRef.value?.print());
}

function statusLabel(status: PayrollRow["status"]) {
  return {
    missing: $t("pages.payrolls.notGenerated"),
    open: $t("pages.payrolls.open"),
    partially_paid: $t("pages.payrolls.partiallyPaid"),
    paid: $t("common.statuses.paid"),
  }[status];
}

function statusColor(status: PayrollRow["status"]) {
  return {
    missing: "secondary",
    open: "warning",
    partially_paid: "info",
    paid: "success",
  }[status];
}

function updateMonth() {
  page.value = 1;
  void load();
}

watch(month, updateMonth);
onMounted(load);
</script>

<template>
  <div class="payrolls-page pa-4">
    <DataTablePageKpis
      :items="rows"
      :title="$t('pages.payrolls.title')"
      :description="$t('pages.payrolls.subtitle')"
      icon="tabler-wallet"
    />
    <VCard class="payrolls-screen">
      <VCardTitle class="d-flex flex-wrap align-center ga-3 pa-5">
        <VIcon icon="tabler-wallet" />{{ $t("pages.payrolls.title")
        }}<VSpacer /><div class="d-flex flex-wrap align-center ga-2">
          <VSelect
            v-model="selectedYear"
            :items="yearOptions"
            :label="$t('common.fields.year')"
            density="compact"
            hide-details
            style="max-width: 120px"
          />
          <VSelect
            v-model="selectedMonth"
            :items="monthOptions"
            :label="$t('common.fields.month')"
            density="compact"
            hide-details
            style="max-width: 160px"
          />
        </div><VBtn
          prepend-icon="tabler-player-play"
          :loading="loading"
          @click="generateDialog = true"
        >
          {{ $t("pages.payrolls.generate") }} </VBtn
        ><VBtn
          prepend-icon="tabler-printer"
          variant="tonal"
          :disabled="activeTab !== 'payslips'"
          @click="printRegister"
        >
          {{ $t("common.actions.print") }}
        </VBtn>
      </VCardTitle>
      <VCardText class="pb-0">
        <div class="payroll-kpis">
          <VCard border variant="flat">
            <VCardText>
              <div class="text-caption text-medium-emphasis">
                {{ $t("pages.payrolls.netPayroll") }}
              </div>
              <div class="text-h5">
                {{ formatAmount(totals.net) }}
              </div>
            </VCardText>
          </VCard>
          <VCard border variant="flat">
            <VCardText>
              <div class="text-caption text-medium-emphasis">
                {{ $t("pages.payrolls.paid") }}
              </div>
              <div class="text-h5 text-success">
                {{ formatAmount(totals.paid) }}
              </div>
            </VCardText>
          </VCard>
          <VCard border variant="flat">
            <VCardText>
              <div class="text-caption text-medium-emphasis">
                {{ $t("pages.payrolls.outstanding") }}
              </div>
              <div class="text-h5 text-warning">
                {{ formatAmount(totals.outstanding) }}
              </div>
            </VCardText>
          </VCard>
          <VCard border variant="flat">
            <VCardText>
              <div class="text-caption text-medium-emphasis">
                {{ $t("pages.payrolls.progress") }}
              </div>
              <div class="text-h5">
                {{ totals.generated }} · {{ totals.missing }}
                {{ $t("pages.payrolls.missing") }}
              </div>
            </VCardText>
          </VCard>
        </div>
        <VTabs v-model="activeTab" class="mt-4">
          <VTab value="payslips">
            {{ $t("pages.payrolls.payslipsTab") }}
          </VTab>
          <VTab value="advances">
            {{ $t("pages.payrolls.advancesTab") }}
          </VTab>
        </VTabs>
      </VCardText>
      <VCardText>
        <VWindow v-model="activeTab" class="payroll-window">
          <VWindowItem value="payslips">
            <div class="d-flex flex-wrap align-center justify-end ga-3 mb-4 px-4">
              <VTextField
                v-model="search"
                :label="$t('common.actions.search')"
                prepend-inner-icon="tabler-search"
                density="compact"
                hide-details
                style="max-width: 320px"
                @update:model-value="debouncedRegisterSearch"
              />
            </div>
            <ResponsiveDataTableServer
              :items="rows"
              :items-length="total"
              :page="page"
              :items-per-page="perPage"
              :items-per-page-options="[10, 25, 50, 100]"
              :loading="loading"
              item-value="id"
              :headers="[
                { title: $t('common.fields.employee'), key: 'name' },
                { title: $t('pages.payrolls.baseSalary'), key: 'base' },
                { title: $t('pages.payrolls.worked'), key: 'workedDays' },
                { title: $t('pages.payrolls.offDays'), key: 'offDays' },
                { title: $t('pages.payrolls.absent'), key: 'absentDays' },
                { title: $t('pages.payrolls.net'), key: 'net' },
                { title: $t('pages.payrolls.paid'), key: 'paid' },
                { title: $t('pages.payrolls.outstanding'), key: 'outstanding' },
                { title: $t('common.fields.status'), key: 'status' },
                {
                  title: $t('common.messages.actions'),
                  key: 'actions',
                  sortable: false,
                  align: 'end',
                },
              ]"
            >
              <template #item.name="{ item }">
                <div>
                  <div class="font-weight-medium">
                    {{ item.name }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ item.position || "—" }}
                  </div>
                </div>
              </template>
              <template #item.base="{ item }">
                {{ formatAmount(item.base) }}
              </template>
              <template #item.workedDays="{ item }">
                {{
                  item.salary ? `${item.workedDays} / ${item.totalDays}` : "—"
                }}
              </template>
              <template #item.offDays="{ item }">
                {{ item.offDays ?? "—" }}
              </template>
              <template #item.absentDays="{ item }">
                {{ item.absentDays ?? "—" }}
              </template>
              <template #item.net="{ item }">
                {{ formatAmount(item.net) }}
              </template>
              <template #item.paid="{ item }">
                {{ formatAmount(item.paid) }}
              </template>
              <template #item.outstanding="{ item }">
                {{ formatAmount(item.outstanding) }}
              </template>
              <template #item.status="{ item }">
                <VChip
                  :color="statusColor(item.status)"
                  size="small"
                  variant="tonal"
                >
                  {{ statusLabel(item.status) }}
                </VChip>
              </template>
              <template #item.actions="{ item }">
                <div class="d-flex justify-end ga-1">
                  <VBtn
                    v-if="!item.salary"
                    icon="tabler-file-plus"
                    size="small"
                    variant="text"
                    color="primary"
                    :title="$t('pages.payrolls.createPayslip')"
                    :aria-label="$t('pages.payrolls.createPayslip')"
                    @click="openCreate(item)"
                  />
                  <template v-else>
                    <VBtn
                      icon="tabler-printer"
                      size="small"
                      variant="text"
                      :title="$t('pages.payrolls.printPayslip')"
                      :aria-label="$t('pages.payrolls.printPayslip')"
                      @click="openPayslip(item)"
                    />
                    <VBtn
                      icon="tabler-arrow-back-up"
                      size="small"
                      variant="text"
                      :color="
                        Number(item.salary.advanceRecovery ?? 0) > 0
                          ? 'warning'
                          : 'success'
                      "
                      :disabled="
                        Number(item.salary.advanceRecovery ?? 0) > 0 &&
                        (item.salary.payments?.length ?? 0) > 0
                      "
                      :title="
                        Number(item.salary.advanceRecovery ?? 0) > 0
                          ? $t('pages.payrolls.removeAdvanceRecovery')
                          : $t('pages.payrolls.applyAdvanceRecovery')
                      "
                      :aria-label="
                        Number(item.salary.advanceRecovery ?? 0) > 0
                          ? $t('pages.payrolls.removeAdvanceRecovery')
                          : $t('pages.payrolls.applyAdvanceRecovery')
                      "
                      @click="toggleAdvanceRecovery(item)"
                    />
                    <VBtn
                      icon="tabler-receipt-refund"
                      size="small"
                      variant="text"
                      :title="$t('pages.payrolls.addDeduction')"
                      :aria-label="$t('pages.payrolls.addDeduction')"
                      @click="openDeductions(item.salary!)"
                    />
                    <VBtn
                      icon="tabler-cash-plus"
                      size="small"
                      variant="text"
                      color="success"
                      :title="$t('pages.payrolls.addPayment')"
                      :aria-label="$t('pages.payrolls.addPayment')"
                      @click="openPayment(item.salary!)"
                    />
                    <VBtn
                      icon="tabler-trash"
                      size="small"
                      variant="text"
                      color="error"
                      :title="$t('pages.payrolls.deletePayslip')"
                      :aria-label="$t('pages.payrolls.deletePayslip')"
                      @click="confirmDelete(item.salary!)"
                    />
                  </template>
                </div>
              </template>
            </ResponsiveDataTableServer>
          </VWindowItem>
          <VWindowItem value="advances">
            <div class="d-flex flex-wrap align-center justify-end ga-3 mb-4 px-4">
              <VBtn
                prepend-icon="tabler-plus"
                color="primary"
                @click="openAdvanceDialog"
              >
                {{ $t("pages.payrolls.addAdvance") }}
              </VBtn>
            </div>
            <ResponsiveDataTableServer
              :items="advances"
              :items-length="advancesTotal"
              :page="advancesPage"
              :items-per-page="advancesPerPage"
              :items-per-page-options="[10, 25, 50, 100]"
              :loading="loading"
              item-value="id"
              :headers="[
                {
                  title: $t('pages.payrolls.advanceNumber'),
                  key: 'advanceNumber',
                },
                { title: $t('common.fields.employee'), key: 'employeeName' },
                { title: $t('pages.payrolls.date'), key: 'date' },
                { title: $t('pages.payrolls.amount'), key: 'amount' },
                {
                  title: $t('pages.payrolls.remainingAdvance'),
                  key: 'remainingAmount',
                },
                { title: $t('common.fields.status'), key: 'status' },
                {
                  title: $t('common.messages.actions'),
                  key: 'actions',
                  sortable: false,
                  align: 'end',
                },
              ]"
            >
              <template #item.date="{ item }">
                {{
                  item.date
                    ? formatLocalizedDate(`${item.date}T00:00:00`, {
                        day: "2-digit",
                        month: "short",
                        year: "numeric",
                      })
                    : "—"
                }}
              </template>
              <template #item.amount="{ item }">
                {{ formatAmount(item.amount) }}
              </template>
              <template #item.remainingAmount="{ item }">
                {{ formatAmount(item.remainingAmount) }}
              </template>
              <template #item.status="{ item }">
                <VChip
                  :color="advanceStatusColor(item.status)"
                  size="small"
                  variant="tonal"
                >
                  {{ advanceStatusLabel(item.status) }}
                </VChip>
              </template>
              <template #item.actions="{ item }">
                <div class="d-flex justify-end ga-1">
                  <VBtn
                    icon="tabler-printer"
                    size="small"
                    variant="text"
                    :title="$t('pages.payrolls.printReceipt')"
                    :aria-label="$t('pages.payrolls.printReceipt')"
                    @click="openAdvanceReceipt(item)"
                  />
                  <VBtn
                    icon="tabler-receipt-refund"
                    size="small"
                    variant="text"
                    color="success"
                    :disabled="item.status === 'settled'"
                    :title="$t('pages.payrolls.recordRepayment')"
                    :aria-label="$t('pages.payrolls.recordRepayment')"
                    @click="openSettle(item)"
                  />
                  <VBtn
                    icon="tabler-pencil"
                    size="small"
                    variant="text"
                    :title="$t('pages.payrolls.editAdvance')"
                    :aria-label="$t('pages.payrolls.editAdvance')"
                    @click="openEditAdvance(item)"
                  />
                  <VBtn
                    icon="tabler-trash"
                    size="small"
                    variant="text"
                    color="error"
                    :title="$t('common.actions.delete')"
                    :aria-label="$t('common.actions.delete')"
                    @click="confirmDeleteAdvance(item)"
                  />
                </div>
              </template>
            </ResponsiveDataTableServer>
          </VWindowItem>
        </VWindow>
      </VCardText>
    </VCard>

    <PrintableListReport
      :title="$t('pages.payrolls.registerTitle')"
      :subtitle="month"
      :columns="[
        $t('common.fields.employee'),
        $t('pages.payrolls.baseSalary'),
        $t('pages.payrolls.worked'),
        $t('pages.payrolls.offDays'),
        $t('pages.payrolls.absent'),
        $t('pages.payrolls.net'),
        $t('pages.payrolls.paid'),
        $t('pages.payrolls.outstanding'),
      ]"
      :rows="reportRows"
    />

    <!-- Generate missing payslips -->
    <VDialog v-model="generateDialog" max-width="480">
      <VCard :title="$t('pages.payrolls.generateTitle')">
        <VCardText>
          {{
            $t("pages.payrolls.generateHint", { month, count: totals.missing })
          }}
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="generateDialog = false">
            {{ $t("common.actions.cancel") }}
          </VBtn>
          <VBtn color="primary" :loading="saving" @click="generateMissing">
            {{ $t("pages.payrolls.generate") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Create one payslip -->
    <VDialog v-model="createDialog" max-width="600">
      <VCard :title="$t('pages.payrolls.createPayslip')">
        <VCardText class="d-flex flex-column ga-4">
          <VSelect
            v-model="createForm.employee_id"
            :items="
              employees.map((employee) => ({
                title: employee.name,
                value: employee.id,
              }))
            "
            :label="$t('common.fields.employee')"
            disabled
          />
          <VTextField
            v-model.number="createForm.amountPaid"
            type="number"
            min="0"
            :label="$t('pages.payrolls.baseSalary')"
          />
          <VTextField
            v-model="createForm.date"
            type="date"
            :label="$t('pages.payrolls.paymentDate')"
          />
          <VTextField
            v-model="createForm.remarks"
            :label="$t('pages.payrolls.remarks')"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="createDialog = false">
            {{ $t("common.actions.cancel") }}
          </VBtn>
          <VBtn color="primary" :loading="saving" @click="saveCreate">
            {{ $t("common.actions.save") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Deductions -->
    <VDialog v-model="deductionDialog" max-width="640">
      <VCard :title="$t('pages.payrolls.deductionsTitle')">
        <VCardText>
          <VTable
            v-if="selectedSalary?.deductions?.length"
            density="compact"
            class="mb-5"
          >
            <thead>
              <tr>
                <th>{{ $t("pages.payrolls.description") }}</th>
                <th class="text-end">
                  {{ $t("pages.payrolls.amount") }}
                </th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="deduction in selectedSalary.deductions"
                :key="deduction.id"
              >
                <td>
                  {{
                    deduction.isAutomatic
                      ? $t("pages.payrolls.automaticDeduction")
                      : deduction.description
                  }}
                </td>
                <td class="text-end">
                  {{ formatAmount(deduction.amount) }}
                </td>
                <td class="text-end">
                  <VBtn
                    v-if="!deduction.isAutomatic"
                    icon="tabler-trash"
                    size="x-small"
                    variant="text"
                    color="error"
                    :loading="saving"
                    @click="removeDeduction(deduction)"
                  />
                </td>
              </tr>
            </tbody>
          </VTable>
          <VAlert v-else variant="tonal" color="info" class="mb-5">
            {{ $t("pages.payrolls.noDeductions") }}
          </VAlert>
          <div class="d-flex flex-column ga-4">
            <VTextField
              v-model="deductionForm.description"
              :label="$t('pages.payrolls.description')"
            />
            <VTextField
              v-model.number="deductionForm.amount"
              type="number"
              min="0"
              :label="$t('pages.payrolls.amount')"
            />
          </div>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="deductionDialog = false">
            {{ $t("common.actions.close") }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            :disabled="!deductionForm.description || deductionForm.amount <= 0"
            @click="addDeduction"
          >
            {{ $t("pages.payrolls.addDeduction") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Payment -->
    <VDialog v-model="paymentDialog" max-width="520">
      <VCard :title="$t('pages.payrolls.addPayment')">
        <VCardText class="d-flex flex-column ga-4">
          <VTextField
            v-model.number="paymentForm.amount"
            type="number"
            min="0"
            :label="$t('pages.payrolls.amount')"
          />
          <VTextField
            v-model="paymentForm.date"
            type="date"
            :label="$t('pages.payrolls.paymentDate')"
          />
          <VTextField
            v-model="paymentForm.remarks"
            :label="$t('pages.payrolls.remarks')"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="paymentDialog = false">
            {{ $t("common.actions.cancel") }}
          </VBtn>
          <VBtn
            color="success"
            :loading="saving"
            :disabled="paymentForm.amount <= 0"
            @click="savePayment"
          >
            {{ $t("common.actions.save") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete payslip -->
    <VDialog v-model="deleteSalaryDialog" max-width="480">
      <VCard :title="$t('pages.payrolls.deletePayslip')">
        <VCardText>
          {{
            $t("pages.payrolls.confirmDelete", {
              employee: selectedSalary?.employeeName ?? "",
            })
          }}
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="deleteSalaryDialog = false">
            {{ $t("common.actions.cancel") }}
          </VBtn>
          <VBtn color="error" :loading="saving" @click="deleteSalary">
            {{ $t("common.actions.delete") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Advance -->
    <VDialog v-model="advanceDialog" max-width="520">
      <VCard :title="$t('pages.payrolls.addAdvance')">
        <VCardText class="d-flex flex-column ga-4">
          <VSelect
            v-model="advanceForm.employee_id"
            :items="
              employees.map((employee) => ({
                title: employee.name,
                value: employee.id,
              }))
            "
            :label="$t('common.fields.employee')"
          />
          <VTextField
            v-model.number="advanceForm.amount"
            type="number"
            min="0"
            :label="$t('pages.payrolls.amount')"
          />
          <VTextField
            v-model="advanceForm.date"
            type="date"
            :label="$t('pages.payrolls.date')"
          />
          <VTextField
            v-model="advanceForm.remarks"
            :label="$t('pages.payrolls.remarks')"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="advanceDialog = false">
            {{ $t("common.actions.cancel") }}
          </VBtn>
          <VBtn color="primary" :loading="saving" @click="saveAdvance">
            {{ $t("common.actions.save") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="deleteAdvanceDialog" max-width="480">
      <VCard :title="$t('pages.payrolls.deleteAdvance')">
        <VCardText>
          {{
            $t("pages.payrolls.confirmDeleteAdvance", {
              employee: selectedAdvance?.employeeName ?? "",
            })
          }}
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="deleteAdvanceDialog = false">
            {{ $t("common.actions.cancel") }}
          </VBtn>
          <VBtn color="error" :loading="saving" @click="deleteAdvance">
            {{ $t("common.actions.delete") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Advance repayment -->
    <VDialog v-model="settleDialog" max-width="520">
      <VCard :title="$t('pages.payrolls.repaymentTitle')">
        <VCardText class="d-flex flex-column ga-4">
          <VAlert v-if="selectedAdvance" variant="tonal" color="info">
            {{ selectedAdvance.advanceNumber }} ·
            {{ formatAmount(selectedAdvance.remainingAmount) }}
            {{ $t("pages.payrolls.remainingAdvance") }}
          </VAlert>
          <VTextField
            v-model.number="settleForm.amount"
            type="number"
            min="0"
            :label="$t('pages.payrolls.amount')"
          />
          <VTextField
            v-model="settleForm.date"
            type="date"
            :label="$t('pages.payrolls.date')"
          />
          <VTextField
            v-model="settleForm.remarks"
            :label="$t('pages.payrolls.remarks')"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="settleDialog = false">
            {{ $t("common.actions.cancel") }}
          </VBtn>
          <VBtn
            color="success"
            :loading="saving"
            :disabled="settleForm.amount <= 0"
            @click="saveSettlement"
          >
            {{ $t("common.actions.save") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit advance -->
    <VDialog v-model="editAdvanceDialog" max-width="520">
      <VCard :title="$t('pages.payrolls.editAdvance')">
        <VCardText class="d-flex flex-column ga-4">
          <VTextField
            v-model.number="editAdvanceForm.amount"
            type="number"
            min="0"
            :label="$t('pages.payrolls.amount')"
          />
          <VTextField
            v-model="editAdvanceForm.date"
            type="date"
            :label="$t('pages.payrolls.date')"
          />
          <VTextField
            v-model="editAdvanceForm.remarks"
            :label="$t('pages.payrolls.remarks')"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="editAdvanceDialog = false">
            {{ $t("common.actions.cancel") }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            :disabled="editAdvanceForm.amount <= 0"
            @click="saveAdvanceEdit"
          >
            {{ $t("common.actions.save") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Payslip preview -->
    <VDialog v-model="payslipDialog" max-width="1100" scrollable>
      <VCard class="document-preview-dialog">
        <VCardText class="pa-0">
          <PayslipTemplate
            v-if="payslipProps"
            ref="payslipRef"
            v-bind="payslipProps"
          />
        </VCardText>
        <VCardActions class="justify-end pa-4">
          <VBtn variant="text" @click="payslipDialog = false">
            {{ $t("common.actions.close") }}
          </VBtn>
          <VBtn
            prepend-icon="tabler-printer"
            color="primary"
            @click="printPayslip"
          >
            {{ $t("common.actions.print") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Advance receipt preview -->
    <VDialog v-model="advanceReceiptDialog" max-width="900" scrollable>
      <VCard class="document-preview-dialog">
        <VCardText class="pa-0">
          <AdvanceReceiptTemplate
            v-if="advanceReceipt"
            ref="advanceReceiptRef"
            :receipt="advanceReceipt"
          />
        </VCardText>
        <VCardActions class="justify-end pa-4">
          <VBtn variant="text" @click="advanceReceiptDialog = false">
            {{ $t("common.actions.close") }}
          </VBtn>
          <VBtn
            prepend-icon="tabler-printer"
            color="primary"
            @click="advanceReceiptRef?.print()"
          >
            {{ $t("common.actions.print") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.payroll-kpis {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}

.payroll-window {
  overflow: visible;
}

.document-preview-dialog {
  max-height: 88vh;
}
</style>
