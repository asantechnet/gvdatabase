<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useToast } from "vue-toastification";
import { type FinanceType, getFinanceTypes } from "@/api/financeTypes";
import { financeTypeLabel } from "@/utils/financeTypes";
import {
  type FinanceRecordStatus,
  type Income,
  type IncomeCreateData,
  type IncomeParams,
  type IncomeUpdateData,
  type IncomePayerOption,
  type ApplicantContractPaymentOption,
  approveIncome,
  createIncome,
  deleteIncome,
  getIncomes,
  getIncomesPage,
  getApplicantContractPayments,
  getIncomePayers,
  rejectIncome,
  updateIncome,
} from "@/api/incomes";
import ReceiptApi from "@/api/receipts";
import CurrencyApi, { type Currency } from "@/api/currency";
import { apiErrorMessage } from "@/utils/apiErrors";
import { formatActiveMoney } from "@/utils/localization";
import { printDocument } from "@/utils/printDocument";
import { moveCreatedRowFirst } from "@/utils/createdFirst";
import PrintableListReport from "@/components/PrintableListReport.vue";
import ResponsiveDataTableServer from "@/components/responsive-data-table/ResponsiveDataTableServer.vue";
import ErpDateRange from "@/components/erp/ErpDateRange.vue";
import ErpField from "@/components/erp/ErpField.vue";
import ErpPageHeader from "@/components/erp/ErpPageHeader.vue";
import ApplicantSelect from "@/components/income/ApplicantSelect.vue";
import ErpSearchField from "@/components/erp/ErpSearchField.vue";
import TableEmptyState from "@/components/TableEmptyState.vue";
import ReceiptPrinter from "@/components/income/ReceiptPrinter.vue";
import ConfirmDialog from "@/components/dialogs/ConfirmDialog.vue";
import AttachmentsDialog from "@/components/attachments/AttachmentsDialog.vue";
import PaymentOverridesDialog from "@/components/income/PaymentOverridesDialog.vue";
import ServicePaymentsDialog from "@/components/income/ServicePaymentsDialog.vue";
import { canAccess } from "@/utils/authorization";
import {
  changedIncomeFields,
  paymentEntryLimit,
} from "@/utils/incomePaymentBalance";

const incomes = ref<Income[]>([]);
// The ledger asks the server for one page at a time.
const page = ref(1);
const perPage = ref(25);
const total = ref(0);
// Filled from every matching receipt right before printing.
const reportRows = ref<string[][]>([]);
const isAddIncomeDialogVisible = ref(false);
const isEditIncomeDialogVisible = ref(false);
const isDeleteIncomeDialogVisible = ref(false);
type IncomeForm = Omit<
  IncomeCreateData,
  "applicant_contract_id" | "status"
> & {
  id: number;
  student_id: number | null;
  applicant_contract_id: number | null;
  base_amount: number;
  // A saved row can be approved, rejected or still a draft.
  status?: FinanceRecordStatus;
  receipt_number?: string;
  contract_service?: Income["contract_service"];
  currency?: Income["currency"];
  created_at?: string;
  updated_at?: string;
};

const emptyIncomeForm = (): IncomeForm => ({
  id: 0,
  applicant_id: null,
  student_id: null,
  applicant_contract_id: null,
  type: "",
  description: "",
  amount: 0,
  quantity: 1,
  currency_id: 0,
  rate: 1,
  base_amount: 0,
  date: new Date().toISOString().split("T")[0],
});

const selectedIncome = ref<IncomeForm>(emptyIncomeForm());
const searchQuery = ref("");
const filterType = ref<string | null>(null);
const filterStatus = ref<FinanceRecordStatus | null>(null);
const filterCurrency = ref<number | null>(null);
const dateFrom = ref("");
const dateTo = ref("");
const isLoading = ref(false);
const applicantContracts = ref<ApplicantContractPaymentOption[]>([]);
const currencies = ref<Currency[]>([]);
const payers = ref<IncomePayerOption[]>([]);
const formError = ref("");
const isReceiptPreviewOpen = ref(false);
const activeReceiptIncomeId = ref<number | null>(null);
const saveMode = ref<"direct" | "draft">("direct");
const canCreateDirect = computed(() => canAccess("create_incomes"));
const canCreateDraft = computed(() => canAccess("create_draft_finance_records"));
const canApproveDrafts = computed(() => canAccess("approve_draft_finance_records"));
const rejectDraftDialog = ref(false);
const rejectDraftTarget = ref<Income | null>(null);
const rejectionReason = ref("");
const rejectingDraft = ref(false);
const attachmentTarget = ref<Income | null>(null);
const isAttachmentsOpen = ref(false);
const isPaymentOverridesOpen = ref(false);
const isServicePaymentsOpen = ref(false);

function openAttachments(income: Income) {
  attachmentTarget.value = income;
  isAttachmentsOpen.value = true;
}
const toast = useToast();

const financeTypes = ref<FinanceType[]>([]);
const typeOptions = computed(() =>
  financeTypes.value.map((type) => ({
    title: financeTypeLabel(type),
    value: type.value,
  })),
);
const filterTypeOptions = typeOptions;
const typeLabel = (value: string) => {
  const type = financeTypes.value.find((type) => type.value === value);
  return type ? financeTypeLabel(type) : translateBackendValue(value);
};

async function fetchTypes() {
  try {
    financeTypes.value = await getFinanceTypes("income");
  } catch (error) {
  }
}

// Every currency on record can be used for a receipt; the base one is first.
const currencyOptions = computed(() =>
  currencies.value.map((currency) => ({
    title: `${currency.code} — ${currency.name}`,
    value: currency.id,
  })),
);

const selectedServiceLine = computed(() => {
  const lineId = selectedIncome.value?.applicant_contract_id;

  return lineId
    ? applicantContracts.value.find((line) => line.id === lineId)
    : undefined;
});

const selectedLineRemaining = computed(() => {
  const line = selectedServiceLine.value;
  if (!line) return 0;

  const original = incomes.value.find(
    (income) => income.id === selectedIncome.value.id,
  );
  return paymentEntryLimit(line, selectedIncome.value.rate, original);
});
const selectedCurrency = computed(
  () =>
    currencies.value.find(
      (currency) => currency.id === selectedIncome.value.currency_id,
    ) ?? null,
);
const selectedCurrencyDecimals = computed(
  () =>
    selectedServiceLine.value?.currency?.decimalPlaces ??
    selectedCurrency.value?.decimalPlaces ??
    2,
);
const selectedCurrencyStep = computed(
  () => 10 ** -selectedCurrencyDecimals.value,
);

// The applicant's contracted services are the payment subjects: one receipt
// covers one service.
const serviceLineOptions = computed(() =>
  applicantContracts.value.map((line) => ({
    title: [line.service_label, line.contract_number]
      .filter(Boolean)
      .join(" — "),
    value: line.id,
    props: {
      disabled:
        line.is_cancelled ||
        (line.remaining_total <= 0 &&
          !incomes.value.some(
            (income) =>
              income.id === selectedIncome.value.id &&
              income.applicant_contract_id === line.id,
          )),
    },
  })),
);
const currentBaseCurrency = computed(
  () => currencies.value.find((currency) => currency.isBase) ?? null,
);

const fetchApplicantContracts = async (applicantId?: number | null) => {
  if (!applicantId) {
    applicantContracts.value = [];

    return;
  }

  applicantContracts.value = await getApplicantContractPayments(applicantId);
};

const fetchCurrencies = async () => {
  const response = await CurrencyApi.getAll();

  currencies.value = response.data;
};

const fetchPayers = async () => {
  payers.value = await getIncomePayers();
};

const openReceiptPreview = (income: Income) => {
  activeReceiptIncomeId.value = income.id;
  isReceiptPreviewOpen.value = true;
};

const formatReceiptNumber = (income: Income) => {
  const receipt = income.receipt_number;
  const match = receipt?.match(/^RCPT-(\d{2,4})-(\d+)$/i);
  if (match)
    return `RCPT-${match[1].slice(-2)}-${String(Number(match[2])).padStart(3, "0")}`;

  return receipt || `RCPT-${String(income.id).padStart(3, "0")}`;
};

const setDefaultCurrency = () => {
  if (selectedIncome.value.currency_id) return;

  const currency =
    currencies.value.find((item) => item.isBase) ?? currencies.value[0];
  if (currency) {
    selectedIncome.value.currency_id = currency.id;
    selectedIncome.value.rate = currency.rate;
  }
};

const filterParams = (): IncomeParams => ({
  search: searchQuery.value || undefined,
  type: filterType.value || undefined,
  status: filterStatus.value || undefined,
  currency_id: filterCurrency.value || undefined,
  date_from: dateFrom.value || undefined,
  date_to: dateTo.value || undefined,
});

const fetchIncomes = async () => {
  try {
    isLoading.value = true;

    const result = await getIncomesPage({
      ...filterParams(),
      page: page.value,
      perPage: perPage.value,
    });

    incomes.value = result.rows;
    total.value = result.total;
  } catch (error) {
    console.error("Error fetching incomes:", error);
    incomes.value = [];
    total.value = 0;
  } finally {
    isLoading.value = false;
  }
};

function updateTableOptions(options: { page: number; itemsPerPage: number }) {
  if (page.value === options.page && perPage.value === options.itemsPerPage)
    return;

  page.value = options.page;
  perPage.value = options.itemsPerPage;
  fetchIncomes();
}

// A filter change starts from the first page.
const reload = () => {
  page.value = 1;
  void fetchIncomes();
};

const openAddIncomeDialog = () => {
  formError.value = "";
  saveMode.value = canCreateDirect.value ? "direct" : "draft";
  selectedIncome.value = emptyIncomeForm();
  applicantContracts.value = [];
  Promise.all([fetchCurrencies(), fetchPayers(), fetchTypes()])
    .then(() => {
      setDefaultCurrency();
      isAddIncomeDialogVisible.value = true;
    })
    .catch((error) => {
      console.error("Error loading income form options:", error);
      formError.value = translate("common.messages.unableToLoadOptions");
      isAddIncomeDialogVisible.value = true;
    });
};

const openEditIncomeDialog = (income: Income) => {
  formError.value = "";
  selectedIncome.value = { ...income };
  Promise.all([
    income.applicant_id
      ? fetchApplicantContracts(income.applicant_id)
      : Promise.resolve(),
    fetchCurrencies(),
    fetchTypes(),
    fetchPayers(),
  ])
    .then(() => {
      isEditIncomeDialogVisible.value = true;
    })
    .catch((error) => {
      console.error("Error loading income form options:", error);
      formError.value = translate("common.messages.unableToLoadOptions");
      isEditIncomeDialogVisible.value = true;
    });
};

const payerKey = computed<string | null>({
  get: () => {
    const income = selectedIncome.value;
    if (income.applicant_id)
      return `applicant:${income.applicant_id}`;
    if (income.student_id)
      return `student:${income.student_id}`;

    return null;
  },
  set: (key) => { void handlePayerChange(key); },
});

const handlePayerChange = async (key: string | null) => {
  const [type, rawId] = (key ?? "").split(":");
  const id = Number(rawId);
  const valid = Number.isSafeInteger(id) && id > 0;

  selectedIncome.value.applicant_id = type === "applicant" && valid ? id : null;
  selectedIncome.value.student_id = type === "student" && valid ? id : null;
  selectedIncome.value.applicant_contract_id = null;
  await fetchApplicantContracts(selectedIncome.value.applicant_id);
  setDefaultCurrency();
  calculateTotal();
};

const handleServiceLineChange = (lineId: number | null) => {
  if (!lineId) return;

  const line = applicantContracts.value.find((item) => item.id === lineId);
  if (!line) return;

  selectedIncome.value.type = "Service";
  selectedIncome.value.description = [line.service_label, line.contract_number]
    .filter(Boolean)
    .join(" — ");
  if (line.currency) {
    selectedIncome.value.currency_id = line.currency.id;
    selectedIncome.value.rate = line.currency_rate;
  }
  selectedIncome.value.quantity = 1;
  selectedIncome.value.amount = selectedLineRemaining.value;
  calculateTotal();
};

const handleCurrencyChange = (currencyId: number) => {
  const currency = currencies.value.find((item) => item.id === currencyId);
  if (currency) {
    selectedIncome.value.rate = currency.rate;
    calculateTotal();
  }
};

// Flatpickr renders at body level in dialogs. Keep its body popup docked to
// the field and above the modal instead of allowing it to jump to page top.
const dockDialogCalendar = (_selectedDates: Date[], _dateString: string, instance: any) => {
  window.setTimeout(() => {
    const calendar = instance?.calendarContainer as HTMLElement | undefined;
    if (!calendar)
      return;
    calendar.style.transform = "translateY(0) scale(0.86)";
    calendar.style.transformOrigin = "top left";
    calendar.style.zIndex = "3100";
  });
};

const formatSelectedLineAmount = (amount: number) =>
  amount.toFixed(selectedCurrencyDecimals.value);

const messageFromError = (error: unknown): string =>
  apiErrorMessage(error, "Unable to save the income record.");

const validateIncomeForm = () => {
  if (!selectedIncome.value.type)
    return translate("pages.finance.income.income.selectAnIncomeType");
  if (!selectedIncome.value.currency_id)
    return translate("common.messages.selectACurrency");
  if (
    !Number.isFinite(Number(selectedIncome.value.amount)) ||
    selectedIncome.value.amount <= 0
  )
    return translate("pages.finance.income.income.amountMustBeGreaterThanZero");
  if (
    !Number.isInteger(Number(selectedIncome.value.quantity)) ||
    selectedIncome.value.quantity < 1
  )
    return translate(
      "pages.finance.income.income.quantityMustBeAWholeNumberOfAtLeastOne",
    );
  if (
    !Number.isFinite(Number(selectedIncome.value.rate)) ||
    selectedIncome.value.rate <= 0
  )
    return translate(
      "pages.finance.income.income.exchangeRateMustBeGreaterThanZero",
    );
  if (!/^\d{4}-\d{2}-\d{2}$/.test(selectedIncome.value.date))
    return translate("pages.finance.income.income.selectAValidIncomeDate");

  if (
    selectedServiceLine.value &&
    selectedIncome.value.amount * selectedIncome.value.quantity >
      selectedLineRemaining.value + selectedCurrencyStep.value / 2
  )
    return `${translate("pages.finance.income.income.paymentExceedsRemainingBalance")} ${formatSelectedLineAmount(selectedLineRemaining.value)} ${selectedServiceLine.value.currency?.code ?? ""}`.trim();

  return "";
};

// An issued receipt's value is protected: a value change opens a confirmation
// that shows old → new total and requires a written reason.
const revaluationDialog = ref(false);
const revaluationReason = ref("");
const revaluationError = ref("");
const revaluationOld = ref(0);
const revaluationNew = ref(0);
const pendingRevaluation = ref<IncomeUpdateData | null>(null);
const incomeTotal = (amount: number | string, quantity: number | string) =>
  Number(amount) * Number(quantity);

const openDeleteIncomeDialog = (income: Income) => {
  selectedIncome.value = income;
  isDeleteIncomeDialogVisible.value = true;
};

const addIncome = async () => {
  formError.value = validateIncomeForm();
  if (formError.value) return;

  try {
    formError.value = "";

    // A draft is a real ledger row, but it does not count as money until a
    // finance approver accepts it.
    if (saveMode.value === "draft") {
      const draft = await createIncome({
        ...(selectedIncome.value as IncomeCreateData),
        status: "draft",
      });
      isAddIncomeDialogVisible.value = false;
      toast.success(translate("pages.finance.income.income.draftSaved"));
      page.value = 1;
      await fetchIncomes();
      incomes.value = moveCreatedRowFirst(incomes.value, draft).slice(0, perPage.value);

      return;
    }

    const incomeData = { ...selectedIncome.value } as IncomeCreateData;

    const created = await createIncome(incomeData);
    toast.success(created.receipt_number ?? "");
    // A new receipt belongs on the first page of the current filters.
    page.value = 1;
    await fetchIncomes();
    incomes.value = moveCreatedRowFirst(incomes.value, created).slice(0, perPage.value);
    isAddIncomeDialogVisible.value = false;
  } catch (error) {
    console.error("Error creating income:", error);
    formError.value = messageFromError(error);
  }
};

const updateIncomeRecord = async () => {
  if (selectedIncome.value.id) {
    formError.value = validateIncomeForm();
    if (formError.value) return;

    try {
      formError.value = "";

      const original = incomes.value.find(
        (income) => income.id === selectedIncome.value.id,
      );
      if (!original) return;
      const incomeData = changedIncomeFields(original, selectedIncome.value);

      // Only value-bearing fields trigger the reason; a description or date
      // edit stays a free, unconfirmed change.
      const valueChanged = (
        ["amount", "quantity", "currency_id", "rate"] as Array<
          keyof IncomeUpdateData
        >
      ).some((field) => field in incomeData);
      if (valueChanged) {
        revaluationOld.value = incomeTotal(original.amount, original.quantity);
        revaluationNew.value = incomeTotal(
          selectedIncome.value.amount,
          selectedIncome.value.quantity,
        );
        revaluationReason.value = "";
        revaluationError.value = "";
        pendingRevaluation.value = incomeData;
        revaluationDialog.value = true;

        return;
      }

      await saveIncomeUpdate(incomeData);
    } catch (error) {
      console.error("Error updating income:", error);
      formError.value = messageFromError(error);
    }
  }
};

const saveIncomeUpdate = async (data: IncomeUpdateData) => {
  if (!selectedIncome.value.id) return;

  try {
    const updated = await updateIncome(selectedIncome.value.id, data);
    await fetchIncomes();
    isEditIncomeDialogVisible.value = false;
    revaluationDialog.value = false;
    toast.success(
      `${updated.receipt_number} · ${formatIncomeCurrency(incomeTotal(updated.amount, updated.quantity), updated.currency)}`,
    );
  } catch (error) {
    const message = messageFromError(error);

    formError.value = message;
    revaluationError.value = message;
  }
};

const confirmRevaluation = async () => {
  if (!pendingRevaluation.value) return;

  const reason = revaluationReason.value.trim();
  if (!reason) {
    revaluationError.value = translate("erp.required");

    return;
  }

  await saveIncomeUpdate({
    ...pendingRevaluation.value,
    revaluation_reason: reason,
  });
};

const statusColor = (status: FinanceRecordStatus) =>
  ({ draft: "warning", approved: "success", rejected: "error" })[status];

const approveDraft = async (income: Income) => {
  try {
    await approveIncome(income.id);
    await fetchIncomes();
    toast.success(translate("finance.drafts.approved"));
  } catch (error) {
    console.error("Error approving income draft:", error);
    toast.error(translate("finance.drafts.approveFailed"));
  }
};

const openRejectDraft = (income: Income) => {
  rejectDraftTarget.value = income;
  rejectionReason.value = "";
  rejectDraftDialog.value = true;
};

const rejectDraft = async () => {
  if (!rejectDraftTarget.value || !rejectionReason.value.trim()) return;

  rejectingDraft.value = true;
  try {
    await rejectIncome(rejectDraftTarget.value.id, rejectionReason.value.trim());
    rejectDraftDialog.value = false;
    await fetchIncomes();
    toast.success(translate("finance.drafts.rejected"));
  } catch (error) {
    console.error("Error rejecting income draft:", error);
    toast.error(translate("finance.drafts.rejectFailed"));
  } finally {
    rejectingDraft.value = false;
  }
};

const deleteIncomeRecord = async () => {
  if (selectedIncome.value.id) {
    try {
      const receiptNumber = selectedIncome.value.receipt_number;

      await deleteIncome(selectedIncome.value.id);
      toast.success(receiptNumber ?? "");
      // The deleted row was the last one on this page, so step back instead of
      // showing an empty page.
      if (page.value > 1 && incomes.value.length === 1) page.value -= 1;
      await fetchIncomes();
      isDeleteIncomeDialogVisible.value = false;
    } catch (error) {
      console.error("Error deleting income:", error);
    }
  }
};

const calculateTotal = () => {
  const decimals = currentBaseCurrency.value?.decimalPlaces ?? 2;

  selectedIncome.value.base_amount = Number(
    (
      (selectedIncome.value.amount * selectedIncome.value.quantity) /
      selectedIncome.value.rate
    ).toFixed(decimals),
  );
};

const fieldError = (field: "type" | "currency" | "amount" | "quantity" | "rate" | "date") => {
  if (!formError.value)
    return [];

  const matches = {
    type: !selectedIncome.value.type,
    currency: !selectedIncome.value.currency_id,
    amount: !Number.isFinite(Number(selectedIncome.value.amount)) || selectedIncome.value.amount <= 0,
    quantity: !Number.isInteger(Number(selectedIncome.value.quantity)) || selectedIncome.value.quantity < 1,
    rate: !Number.isFinite(Number(selectedIncome.value.rate)) || selectedIncome.value.rate <= 0,
    date: !/^\d{4}-\d{2}-\d{2}$/.test(selectedIncome.value.date),
  }[field];

  return matches ? [formError.value] : [];
};

const formatIncomeCurrency = (
  value: number | string,
  currency: Income["currency"] | undefined,
): string => formatActiveMoney(value, currency);

const incomeTypeColor = (type: string) =>
  ({
    Service: "primary",
    Product: "success",
    Investment: "warning",
    Other: "secondary",
  })[type] ?? "secondary";

const formatDate = (dateString: string | undefined | null): string => {
  if (!dateString) return "";

  return formatLocalizedDate(`${dateString}T00:00:00`, {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
};

const incomeReportRow = (income: Income): string[] => [
  income.receipt_number,
  income.type,
  income.description ?? "",
  income.contract_service?.service_label ?? "",
  formatIncomeCurrency(income.amount * income.quantity, income.currency),
  formatDate(income.date),
];

// Print is global: the report is filled from every matching row first.
const printTable = async () => {
  try {
    // Drafts are not money yet: the register defaults to approved receipts
    // unless the operator explicitly filters another status.
    reportRows.value = (
      await getIncomes({
        ...filterParams(),
        status: filterStatus.value ?? "approved",
      })
    ).map(incomeReportRow);
    await printDocument({
      bodyClass: "printing-table-income",
      pageStyle: "size: A4 landscape; margin: 12mm;",
    });
  } catch (error) {
    console.error("Error preparing income report:", error);
  }
};

onMounted(() => {
  fetchTypes();
  fetchIncomes();
  fetchCurrencies();
  fetchPayers();
});
</script>

<template>
  <PaymentOverridesDialog v-model="isPaymentOverridesOpen" />
  <ServicePaymentsDialog v-model="isServicePaymentsOpen" @created="reload" />
  <div class="income-page">
    <ErpPageHeader
      :title="$t('pages.finance.income.income.incomeLedger')"
      section="finance"
      class="mb-5"
    />

    <VCard class="ledger-card filter-deck ledger-workspace" elevation="0">
      <VCardText class="filter-deck__content">
        <VRow align="center">
          <VCol cols="12" md="5">
            <ErpSearchField
              v-model="searchQuery"
              :placeholder="$t('pages.finance.income.income.searchIncome')"
              @keyup.enter="reload"
            />
          </VCol>

          <VCol
            cols="12"
            md="7"
            class="income-toolbar-actions d-flex flex-wrap align-center justify-md-end ga-2"
          >
            <DataTableFilterMenu
              :active="
                Boolean(
                  filterType ||
                    filterStatus ||
                    filterCurrency ||
                    dateFrom ||
                    dateTo,
                )
              "
              @reset="
                filterType = null;
                filterStatus = null;
                filterCurrency = null;
                dateFrom = '';
                dateTo = '';
                reload();
              "
            >
              <ErpField
                v-model="filterStatus"
                kind="select"
                :items="[
                  { title: translate('common.statuses.draft'), value: 'draft' },
                  {
                    title: translate('common.statuses.approved'),
                    value: 'approved',
                  },
                  {
                    title: translate('common.statuses.rejected'),
                    value: 'rejected',
                  },
                ]"
                :label="$t('finance.drafts.filterByStatus')"
                :placeholder="$t('finance.drafts.filterByStatus')"
                clearable
              />
              <ErpField
                v-model="filterType"
                kind="select"
                :items="filterTypeOptions"
                :label="$t('common.messages.filterByType')"
                :placeholder="$t('common.messages.filterByType')"
                clearable
              /><ErpField
                v-model="filterCurrency"
                kind="select"
                :items="currencyOptions"
                :label="$t('common.messages.filterByCurrency')"
                :placeholder="$t('common.messages.filterByCurrency')"
                clearable
              />
              <ErpDateRange
                v-model:from="dateFrom"
                v-model:to="dateTo"
                :show-apply="false"
                @apply="reload"
              />
              <template #actions>
                <VBtn
                  variant="tonal"
                  color="secondary"
                  @click="filterType = null; filterStatus = null; filterCurrency = null; dateFrom = ''; dateTo = ''; reload()"
                >{{ $t('common.messages.reset') }}</VBtn>
                <VBtn color="primary" variant="flat" @click="reload">{{ $t('educational.apply') }}</VBtn>
              </template>
            </DataTableFilterMenu>
            <VBtn icon="tabler-printer" size="small" variant="tonal" :aria-label="$t('pages.finance.income.income.printIncomeRecords')" @click="printTable">
              <VIcon icon="tabler-printer" /><VTooltip activator="parent" location="top">{{ $t("pages.finance.income.income.printIncomeRecords") }}</VTooltip>
            </VBtn>
            <VBtn v-if="canAccess('override_contract_payments')" icon="tabler-settings" size="small" variant="tonal" :aria-label="$t('finance.paymentOverrides.title')" @click="isPaymentOverridesOpen = true">
              <VIcon icon="tabler-settings" /><VTooltip activator="parent">{{ $t("finance.paymentOverrides.title") }}</VTooltip>
            </VBtn>
            <VBtn v-if="canAccess('create_incomes')" size="small" prepend-icon="tabler-receipt" variant="tonal" @click="isServicePaymentsOpen = true">{{ $t("finance.servicePayments.title") }}</VBtn>
            <VBtn size="small" color="primary" prepend-icon="tabler-plus" @click="openAddIncomeDialog">{{ $t("pages.finance.income.income.recordIncome") }}</VBtn>
          </VCol>
        </VRow>
      </VCardText>
      <div class="table-container">
        <ResponsiveDataTableServer
          :items="incomes"
          :items-length="total"
          :page="page"
          :items-per-page="perPage"
          :items-per-page-options="[10, 25, 50, 100]"
          :headers="[
            {
              title: translate('pages.finance.income.income.receipt'),
              key: 'receipt_number',
              align: 'start',
            },
            {
              title: translate('common.fields.type'),
              key: 'type',
              align: 'start',
            },
            {
              title: translate('pages.finance.income.income.service'),
              key: 'contract_service',
              align: 'start',
            },
            {
              title: translate('common.fields.amount'),
              key: 'base_amount',
              align: 'end',
            },
            {
              title: translate('common.messages.quantity'),
              key: 'quantity',
              align: 'center',
            },
            {
              title: translate('common.fields.status'),
              key: 'status',
              align: 'start',
            },
            {
              title: translate('common.fields.date'),
              key: 'date',
              align: 'center',
            },
            {
              title: translate('common.messages.actions'),
              key: 'actions',
              align: 'center',
              sortable: false,
            },
          ]"
          item-value="id"
          class="income-data-table"
          :loading="isLoading"
          :skeleton-rows="5"
          :sticky-first-column="false"
          :sticky-last-column="false"
          :sticky-key-column="false"
          :sticky-action-column="false"
          density="compact"
          @update:options="updateTableOptions"
        >
          <template #no-data>
            <TableEmptyState
              icon="tabler-receipt-off"
              :title="$t('common.messages.noRecordsFound')"
              :description="$t('common.messages.noMatchingRecords')"
            />
          </template>
          <template #item.receipt_number="{ item }">
            <div class="font-weight-bold">
              {{
                formatReceiptNumber(item)
              }}
            </div>
            <!-- Removed duplicate currency and quantity detail from the receipt code. -->
            <!--
            <div class="text-caption text-medium-emphasis">
              {{
                  currency: item.currency?.code ?? "—",
              }}
            </div>
            -->
          </template>

          <template #item.status="{ item }">
            <VChip
              size="small"
              :color="statusColor(item.status)"
              variant="tonal"
            >
              {{ translateBackendValue(item.status) }}
            </VChip>
            <div
              v-if="item.rejection_reason"
              class="text-caption text-medium-emphasis detail-copy"
            >
              {{ item.rejection_reason }}
            </div>
          </template>

          <template #item.type="{ item }">
            <VChip
              size="small"
              :color="incomeTypeColor(item.type)"
              variant="tonal"
            >
              {{ typeLabel(item.type) }}
            </VChip>
          </template>

          <template #item.contract_service="{ item }">
            <div v-if="item.contract_service">
              <div class="font-weight-medium">
                {{ item.contract_service.service_label }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{
                  $t(
                    `finance.paymentStatus.${item.contract_service.payment_status}`,
                  )
                }}
                <template v-if="item.contract_service.contract_number">
                  · {{ item.contract_service.contract_number }}</template
                >
              </div>
            </div>
            <span v-else class="text-caption text-medium-emphasis">{{
              $t("pages.finance.income.income.standalone")
            }}</span>
          </template>

          <template #item.base_amount="{ item }">
            <div class="font-weight-bold text-success">
              {{
                formatIncomeCurrency(item.amount * item.quantity, item.currency)
              }}
            </div>
          </template>

          <template #item.quantity="{ item }">
            <span>{{ formatLocalizedNumber(item.quantity) }}</span>
          </template>

          <template #item.date="{ item }">
            <span>{{ formatDate(item.date) }}</span>
          </template>
          <template #item.actions="{ item }">
            <VMenu location="bottom end" content-class="income-actions-menu">
              <template #activator="{ props }">
                <VBtn v-bind="props" icon="tabler-dots-vertical" variant="text" size="small" class="ledger-action-trigger" :aria-label="$t('common.messages.actions')">
                  <VIcon icon="tabler-dots-vertical" />
                </VBtn>
              </template>
              <VList density="compact" min-width="200" class="income-actions-menu__list">
              <VListItem
                v-if="canApproveDrafts && item.status === 'draft'"
                color="success"
                prepend-icon="tabler-check"
                :title="$t('finance.drafts.approve')"
                @click="approveDraft(item)"
              />
              <VListItem
                v-if="canApproveDrafts && item.status === 'draft'"
                color="error"
                prepend-icon="tabler-x"
                :title="$t('finance.drafts.reject')"
                @click="openRejectDraft(item)"
              />
              <VListItem
                prepend-icon="tabler-paperclip"
                :title="$t('common.attachments.title')"
                @click="openAttachments(item)"
              />
              <FullDetailsButton :item="item" menu-action />
              <VListItem
                v-if="item.status === 'approved'"
                color="primary"
                prepend-icon="tabler-printer"
                :title="$t('documents.receipt.printReceipt')"
                @click="openReceiptPreview(item)"
              />
              <VListItem
                color="medium-emphasis"
                prepend-icon="tabler-edit"
                :title="$t('pages.finance.income.income.editIncomeRecord')"
                @click="openEditIncomeDialog(item)"
              />
              <VListItem
                color="error"
                prepend-icon="tabler-trash"
                :title="$t('pages.finance.income.income.deleteIncomeRecord')"
                @click="openDeleteIncomeDialog(item)"
              />
              </VList>
            </VMenu>
          </template>
        </ResponsiveDataTableServer>
      </div>
    </VCard>

    <ReceiptPrinter
      v-model="isReceiptPreviewOpen"
      :record-id="activeReceiptIncomeId"
      :loader="ReceiptApi.getForIncome"
      direct-print
    />

    <AttachmentsDialog
      v-model="isAttachmentsOpen"
      type="incomes"
      :record-id="attachmentTarget?.id ?? null"
      :title="
        attachmentTarget?.receipt_number || $t('common.attachments.title')
      "
    />

    <PrintableListReport
      :title="$t('common.crmUi.printReports.income.title')"
      :subtitle="$t('common.crmUi.printReports.income.subtitle')"
      :columns="[
        $t('common.crmUi.printReports.columns.receipt'),
        $t('common.crmUi.printReports.columns.type'),
        $t('common.crmUi.printReports.columns.details'),
        $t('common.crmUi.printReports.columns.service'),
        $t('common.crmUi.printReports.columns.amount'),
        $t('common.crmUi.printReports.columns.date'),
      ]"
      :rows="reportRows"
    />

    <!-- Add Income Dialog -->
    <VDialog v-model="isAddIncomeDialogVisible" max-width="900px" class="income-form-overlay" content-class="income-form-dialog">
      <VCard class="income-dialog">
        <VCardTitle class="income-dialog__header">
          <div class="d-flex align-center ga-3">
            <VAvatar color="primary" variant="tonal" size="38"><VIcon icon="tabler-receipt" /></VAvatar>
            <span class="text-h5 font-weight-bold">{{ $t("pages.finance.income.income.recordIncome2") }}</span>
          </div>
          <VBtn icon size="small" data-confirm-form-cancel class="income-dialog__close" :aria-label="$t('common.actions.close')" @click="isAddIncomeDialogVisible = false"><VIcon icon="tabler-x" /></VBtn>
        </VCardTitle>

        <VCardText class="pt-5">
          <VForm class="app-form">
            <VAlert v-if="formError" type="error" variant="tonal" class="mb-4">
              {{ formError }}
            </VAlert>
            <VRow>
              <VCol cols="12" md="6">
                <ApplicantSelect
                  v-model="payerKey"
                  :payers="payers"
                  :disabled="Boolean(selectedServiceLine)"
                />
              </VCol>
              <VCol cols="12" md="6">
                <ErpField
                  v-model="selectedIncome.applicant_contract_id"
                  kind="select"
                  :items="serviceLineOptions"
                  :label="$t('pages.finance.income.income.service')"
                  :placeholder="$t('pages.finance.income.income.service')"
                  :disabled="!selectedIncome.applicant_id"
                  clearable
                  :hint="
                    selectedIncome.applicant_id
                      ? $t(
                          'pages.finance.income.income.selectTheContractedServiceThisReceiptPaysFor',
                        )
                      : undefined
                  "
                  :persistent-hint="Boolean(selectedIncome.applicant_id)"
                  @update:model-value="handleServiceLineChange"
                />
              </VCol>
              <VCol v-if="selectedServiceLine" cols="12">
                <div class="d-flex flex-wrap gap-x-6 gap-y-2">
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t("common.messages.fee") }}
                    </div>
                    <div class="font-weight-medium">
                      {{
                        formatIncomeCurrency(
                          selectedServiceLine.fee,
                          selectedServiceLine.currency,
                        )
                      }}
                    </div>
                  </div>
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t("pages.finance.income.income.discount") }}
                    </div>
                    <div class="font-weight-medium">
                      {{
                        formatIncomeCurrency(
                          selectedServiceLine.discount,
                          selectedServiceLine.currency,
                        )
                      }}
                    </div>
                  </div>
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t("pages.finance.income.income.paidToDate") }}
                    </div>
                    <div class="font-weight-medium">
                      {{
                        formatIncomeCurrency(
                          selectedServiceLine.amount_paid,
                          selectedServiceLine.currency,
                        )
                      }}
                    </div>
                  </div>
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t("pages.finance.income.income.remaining") }}
                    </div>
                    <div class="font-weight-medium">
                      {{
                        formatIncomeCurrency(
                          selectedServiceLine.remaining_total,
                          selectedServiceLine.currency,
                        )
                      }}
                    </div>
                  </div>
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t("pages.finance.income.income.paymentStatus") }}
                    </div>
                    <VChip size="small" variant="tonal">{{
                      $t(
                        `finance.paymentStatus.${selectedServiceLine.payment_status}`,
                      )
                    }}</VChip>
                  </div>
                </div>
                <VAlert
                  type="info"
                  variant="tonal"
                  density="compact"
                  class="mt-3"
                >
                  {{
                    $t(
                      "pages.finance.income.income.customerTypeQuantityCurrencyAndExchangeRateComeFromTheSelectedService",
                    )
                  }}
                </VAlert>
              </VCol>
              <VCol cols="12" md="6">
                <ErpField
                  v-model="selectedIncome.type"
                  kind="select"
                  :items="typeOptions"
                  :label="$t('common.fields.type')"
                  :placeholder="$t('common.fields.type')"
                  :error-messages="fieldError('type')"
                  :disabled="Boolean(selectedServiceLine)"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <ErpField
                  v-model="selectedIncome.description"
                  :label="$t('common.messages.descriptionPaymentNoteOptional')"
                  :placeholder="
                    $t(
                      'common.messages.addAReferencePaymentNoteOrReceiptDetail',
                    )
                  "
                />
              </VCol>
              <VCol cols="12" md="4">
                <ErpField
                  v-model.number="selectedIncome.amount"
                  :label="$t('common.fields.amount')"
                  :placeholder="$t('common.fields.amount')"
                  type="number"
                  :step="selectedCurrencyStep"
                  :max="selectedServiceLine ? selectedLineRemaining : undefined"
                  required
                  :error-messages="fieldError('amount')"
                  @update:model-value="calculateTotal"
                />
              </VCol>
              <VCol cols="12" md="4">
                <ErpField
                  v-model.number="selectedIncome.quantity"
                  :label="$t('common.messages.quantity')"
                  :placeholder="$t('common.messages.quantity')"
                  type="number"
                  :disabled="Boolean(selectedServiceLine)"
                  required
                  :error-messages="fieldError('quantity')"
                  @update:model-value="calculateTotal"
                />
              </VCol>
              <VCol cols="12" md="4">
                <ErpField
                  v-model="selectedIncome.currency_id"
                  kind="select"
                  :items="currencyOptions"
                  :label="$t('common.fields.currency')"
                  :placeholder="$t('common.fields.currency')"
                  :disabled="Boolean(selectedServiceLine)"
                  required
                  :error-messages="fieldError('currency')"
                  @update:model-value="handleCurrencyChange"
                />
              </VCol>
              <VCol cols="12" md="4">
                <ErpField
                  v-model.number="selectedIncome.rate"
                  :label="$t('common.messages.exchangeRate')"
                  :placeholder="$t('common.messages.exchangeRate')"
                  type="number"
                  step="0.00000001"
                  required
                  :error-messages="fieldError('rate')"
                  @update:model-value="calculateTotal"
                />
              </VCol>
              <VCol cols="12" md="4">
                <ErpField
                  v-model="selectedIncome.base_amount"
                  :label="$t('common.messages.totalAmount')"
                  :placeholder="$t('common.messages.totalAmount')"
                  type="number"
                  readonly
                />
              </VCol>
              <VCol cols="12" md="4">
                <ErpField
                  v-model="selectedIncome.date"
                  :label="$t('common.fields.date')"
                  :placeholder="$t('common.fields.date')"
                  kind="date"
                  required
                  :error-messages="fieldError('date')"
                  :config="{ dateFormat: 'Y-m-d', static: false, onOpen: dockDialogCalendar }"
                />
              </VCol>
              <VCol cols="12">
                <div class="income-save-mode">
                  <VSwitch v-if="canCreateDirect && canCreateDraft" v-model="saveMode" true-value="direct" false-value="draft" color="primary" hide-details inset :label="saveMode === 'direct' ? $t('pages.finance.income.income.saveModeDirect') : $t('pages.finance.income.income.saveModeDraft')" />
                  <p class="text-caption text-medium-emphasis mb-0">{{ saveMode === 'draft' ? $t('pages.finance.income.income.saveModeDraftHint') : $t('pages.finance.income.income.saveModeDirectHint') }}</p>
                </div>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VCardActions class="income-dialog__footer">
          <VSpacer />
          <VBtn
            data-confirm-form-cancel
            class="income-dialog__cancel"
            @click="isAddIncomeDialogVisible = false"
          >
            {{ $t("common.actions.cancel") }}
          </VBtn>
          <VBtn color="primary" variant="flat" @click="addIncome">
            {{ $t("common.actions.save") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit Income Dialog -->
    <VDialog v-model="isEditIncomeDialogVisible" max-width="900px" class="income-form-overlay" content-class="income-form-dialog">
      <VCard class="income-dialog">
        <VCardTitle class="income-dialog__header">
          <div class="d-flex align-center ga-3">
            <VAvatar color="primary" variant="tonal" size="38"><VIcon icon="tabler-pencil" /></VAvatar>
            <span class="text-h5 font-weight-bold">{{ $t("pages.finance.income.income.editIncome") }}</span>
          </div>
          <VBtn icon size="small" data-confirm-form-cancel class="income-dialog__close" :aria-label="$t('common.actions.close')" @click="isEditIncomeDialogVisible = false"><VIcon icon="tabler-x" /></VBtn>
        </VCardTitle>

        <VCardText class="pt-5">
          <VForm class="app-form">
            <VAlert v-if="formError" type="error" variant="tonal" class="mb-4">
              {{ formError }}
            </VAlert>
            <VRow>
              <VCol cols="12" md="6">
                <ApplicantSelect
                  v-model="payerKey"
                  :payers="payers"
                  :disabled="Boolean(selectedServiceLine)"
                />
              </VCol>
              <VCol cols="12" md="6">
                <ErpField
                  v-model="selectedIncome.applicant_contract_id"
                  kind="select"
                  :items="serviceLineOptions"
                  :label="$t('pages.finance.income.income.service')"
                  :placeholder="$t('pages.finance.income.income.service')"
                  :disabled="!selectedIncome.applicant_id"
                  clearable
                  :hint="
                    selectedIncome.applicant_id
                      ? $t(
                          'pages.finance.income.income.selectTheContractedServiceThisReceiptPaysFor',
                        )
                      : undefined
                  "
                  :persistent-hint="Boolean(selectedIncome.applicant_id)"
                  @update:model-value="handleServiceLineChange"
                />
              </VCol>
              <VCol v-if="selectedServiceLine" cols="12">
                <div class="d-flex flex-wrap gap-x-6 gap-y-2">
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t("common.messages.fee") }}
                    </div>
                    <div class="font-weight-medium">
                      {{
                        formatIncomeCurrency(
                          selectedServiceLine.fee,
                          selectedServiceLine.currency,
                        )
                      }}
                    </div>
                  </div>
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t("pages.finance.income.income.discount") }}
                    </div>
                    <div class="font-weight-medium">
                      {{
                        formatIncomeCurrency(
                          selectedServiceLine.discount,
                          selectedServiceLine.currency,
                        )
                      }}
                    </div>
                  </div>
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t("pages.finance.income.income.paidToDate") }}
                    </div>
                    <div class="font-weight-medium">
                      {{
                        formatIncomeCurrency(
                          selectedServiceLine.amount_paid,
                          selectedServiceLine.currency,
                        )
                      }}
                    </div>
                  </div>
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t("pages.finance.income.income.remaining") }}
                    </div>
                    <div class="font-weight-medium">
                      {{
                        formatIncomeCurrency(
                          selectedServiceLine.remaining_total,
                          selectedServiceLine.currency,
                        )
                      }}
                    </div>
                  </div>
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ $t("pages.finance.income.income.paymentStatus") }}
                    </div>
                    <VChip size="small" variant="tonal">{{
                      $t(
                        `finance.paymentStatus.${selectedServiceLine.payment_status}`,
                      )
                    }}</VChip>
                  </div>
                </div>
                <VAlert
                  type="info"
                  variant="tonal"
                  density="compact"
                  class="mt-3"
                >
                  {{
                    $t(
                      "pages.finance.income.income.customerTypeQuantityCurrencyAndExchangeRateComeFromTheSelectedService",
                    )
                  }}
                </VAlert>
              </VCol>
              <VCol cols="12" md="6">
                <ErpField
                  v-model="selectedIncome.type"
                  kind="select"
                  :items="typeOptions"
                  :label="$t('common.fields.type')"
                  :placeholder="$t('common.fields.type')"
                  :error-messages="fieldError('type')"
                  :disabled="Boolean(selectedServiceLine)"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <ErpField
                  v-model="selectedIncome.description"
                  :label="$t('common.messages.descriptionPaymentNoteOptional')"
                  :placeholder="
                    $t(
                      'common.messages.addAReferencePaymentNoteOrReceiptDetail',
                    )
                  "
                />
              </VCol>
              <VCol cols="12" md="4">
                <ErpField
                  v-model.number="selectedIncome.amount"
                  :label="$t('common.fields.amount')"
                  :placeholder="$t('common.fields.amount')"
                  type="number"
                  :step="selectedCurrencyStep"
                  :max="selectedServiceLine ? selectedLineRemaining : undefined"
                  required
                  :error-messages="fieldError('amount')"
                  @update:model-value="calculateTotal"
                />
              </VCol>
              <VCol cols="12" md="4">
                <ErpField
                  v-model.number="selectedIncome.quantity"
                  :label="$t('common.messages.quantity')"
                  :placeholder="$t('common.messages.quantity')"
                  type="number"
                  :disabled="Boolean(selectedServiceLine)"
                  required
                  :error-messages="fieldError('quantity')"
                  @update:model-value="calculateTotal"
                />
              </VCol>
              <VCol cols="12" md="4">
                <ErpField
                  v-model="selectedIncome.currency_id"
                  kind="select"
                  :items="currencyOptions"
                  :label="$t('common.fields.currency')"
                  :placeholder="$t('common.fields.currency')"
                  :disabled="Boolean(selectedServiceLine)"
                  required
                  :error-messages="fieldError('currency')"
                  @update:model-value="handleCurrencyChange"
                />
              </VCol>
              <VCol cols="12" md="4">
                <ErpField
                  v-model.number="selectedIncome.rate"
                  :label="$t('common.messages.exchangeRate')"
                  :placeholder="$t('common.messages.exchangeRate')"
                  type="number"
                  step="0.00000001"
                  required
                  :error-messages="fieldError('rate')"
                  @update:model-value="calculateTotal"
                />
              </VCol>
              <VCol cols="12" md="4">
                <ErpField
                  v-model="selectedIncome.base_amount"
                  :label="$t('common.messages.totalAmount')"
                  :placeholder="$t('common.messages.totalAmount')"
                  type="number"
                  readonly
                />
              </VCol>
              <VCol cols="12" md="4">
                <ErpField
                  v-model="selectedIncome.date"
                  :label="$t('common.fields.date')"
                  :placeholder="$t('common.fields.date')"
                  kind="date"
                  required
                  :error-messages="fieldError('date')"
                  :config="{ dateFormat: 'Y-m-d', static: false, onOpen: dockDialogCalendar }"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VCardActions class="income-dialog__footer">
          <VSpacer />
          <VBtn
            data-confirm-form-cancel
            class="income-dialog__cancel"
            @click="isEditIncomeDialogVisible = false"
          >
            {{ $t("common.actions.cancel") }}
          </VBtn>
          <VBtn color="primary" variant="flat" @click="updateIncomeRecord">
            {{ $t("common.actions.save") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Receipt revaluation confirmation: issued values change only with a reason -->
    <VDialog v-model="revaluationDialog" max-width="520px">
      <VCard>
        <VCardTitle class="pa-5">
          {{ $t("pages.finance.income.income.editIncomeRecord") }}
        </VCardTitle>
        <VCardText>
          <VAlert type="warning" variant="tonal" class="mb-4">
            {{ formatIncomeCurrency(revaluationOld, selectedIncome.currency) }}
            →
            {{ formatIncomeCurrency(revaluationNew, selectedIncome.currency) }}
          </VAlert>
          <VTextarea
            v-model="revaluationReason"
            :label="$t('common.fields.reason')"
            :error-messages="revaluationError"
            rows="3"
            autofocus
          />
        </VCardText>
        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="revaluationDialog = false"
          >
            {{ $t("common.actions.cancel") }}
          </VBtn>
          <VBtn color="primary" @click="confirmRevaluation">
            {{ $t("common.actions.save") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="rejectDraftDialog" max-width="520px">
      <VCard :title="$t('finance.drafts.rejectTitle')">
        <VCardText>
          <VTextarea
            v-model="rejectionReason"
            :label="$t('finance.drafts.rejectionReason')"
            rows="3"
            autofocus
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="rejectDraftDialog = false"
          >
            {{ $t("common.actions.cancel") }}
          </VBtn>
          <VBtn
            color="error"
            :loading="rejectingDraft"
            :disabled="!rejectionReason.trim()"
            @click="rejectDraft"
          >
            {{ $t("finance.drafts.reject") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <ConfirmDialog
      v-model:is-dialog-visible="isDeleteIncomeDialogVisible"
      tone="error"
      icon="tabler-trash"
      :confirmation-question="$t('common.messages.confirmDelete')"
      :confirmation-message="`${$t('common.confirmations.areYouSureYouWantToDeleteThisIncomeRecord')} ${$t('common.confirmations.thisActionCannotBeUndone')}`"
      :confirm-text="$t('common.actions.delete')"
      :cancel-text="$t('common.actions.cancel')"
      :confirm-title="$t('common.actions.delete')"
      confirm-msg=""
      :cancel-title="$t('common.actions.cancel')"
      cancel-msg=""
      @confirm="confirmed => { if (confirmed) deleteIncomeRecord() }"
    />
  </div>
</template>

<style scoped>
.income-page {
  padding-bottom: 32px;
}
.filter-deck,
.ledger-card {
  overflow: hidden;
  border: 0;
  border-radius: 16px;
  box-shadow: 0 10px 28px rgb(15 23 42 / 0.08);
}
.filter-deck {
  background: rgb(var(--v-theme-surface));
}
.filter-deck__content {
  padding: 14px 18px !important;
}
.ledger-workspace .filter-deck__content {
  border-bottom: 1px solid rgb(var(--v-border-color) / .7);
}
.filter-deck :deep(.v-field) {
  border-radius: 10px;
  background: rgb(var(--v-theme-on-surface) / 0.025);
}
.detail-copy {
  max-width: 250px;
  overflow: hidden;
  color: rgb(var(--v-theme-on-surface) / 0.76);
  text-overflow: ellipsis;
}
.income-dialog :deep(.v-field__field),
.income-dialog :deep(.v-field__input),
.income-dialog :deep(.v-select__selection) {
  align-items: center;
}
.table-container {
  /* VDataTable owns its scroll surface. A second clipping container prevents
     wide ledger columns from taking their intrinsic, data-based width. */
  overflow: visible;
}
.income-data-table :deep(.v-table__wrapper) { overflow-x: auto !important; overflow-y: hidden; scrollbar-width: thin; }
.income-data-table :deep(.v-table__wrapper > table) { inline-size: max-content !important; min-inline-size: 100% !important; table-layout: auto !important; }
.income-data-table :deep(thead th),
.income-data-table :deep(tbody td) { inline-size: max-content; min-inline-size: max-content; white-space: nowrap; }
.ledger-card :deep(thead th) {
  height: 48px !important;
  color: rgb(var(--v-theme-on-surface) / 0.58) !important;
  font-size: 0.68rem !important;
  font-weight: 800 !important;
  letter-spacing: 0.075em;
  text-transform: uppercase;
  background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%);
}
.ledger-card :deep(tbody td) {
  height: 66px !important;
  border-bottom-color: rgb(var(--v-border-color) / 0.7) !important;
}
.ledger-card :deep(tbody td:last-child) {
  background: rgb(var(--v-theme-on-surface) / .035);
}
.ledger-card :deep(tbody tr) {
  transition: background 0.16s ease;
}
.ledger-card :deep(tbody tr:hover) {
  background: rgb(var(--v-theme-primary) / 0.035) !important;
}
.filter-deck :deep(.v-btn) { min-block-size: 38px; padding-inline: .85rem; font-size: .88rem; }
.income-dialog { overflow: hidden; }
.income-dialog__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.5rem;
  background: rgb(var(--v-theme-primary) / 0.07);
}
.income-dialog__close {
  background: rgb(var(--v-theme-error) / 0.14) !important;
  color: rgb(var(--v-theme-error)) !important;
}
.income-dialog__footer {
  padding: 1rem 1.5rem;
  background: color-mix(in srgb, rgb(var(--v-theme-surface)) 96%, rgb(var(--v-theme-on-surface)) 4%);
}
.income-dialog__footer :deep(.v-btn--variant-flat) { color: rgb(var(--v-theme-on-primary)) !important; }
.income-dialog__cancel {
  background: rgb(var(--v-theme-secondary) / 0.13) !important;
  color: rgb(var(--v-theme-secondary)) !important;
}
.income-dialog__cancel:hover {
  background: rgb(var(--v-theme-secondary)) !important;
  color: rgb(var(--v-theme-on-secondary)) !important;
}
.income-save-mode {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: .75rem 1.5rem;
  padding: .75rem 1rem;
  border-radius: 10px;
  background: rgb(var(--v-theme-primary) / 0.055);
}
:global(.income-form-overlay .v-overlay__scrim),
:global(.v-overlay:has(.income-form-dialog) .v-overlay__scrim) {
  background: rgba(var(--v-theme-on-surface), .3) !important;
  backdrop-filter: blur(7px);
  opacity: 1 !important;
}
:global(.income-form-dialog .income-dialog__header) { background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%) !important; border-block-end: 1px solid rgba(var(--v-theme-on-surface), .07); }
:global(.income-form-dialog .income-dialog__footer) { background: color-mix(in srgb, rgb(var(--v-theme-surface)) 96%, rgb(var(--v-theme-on-surface)) 4%) !important; border-block-start: 1px solid rgba(var(--v-theme-on-surface), .07); }
:global(.income-form-dialog .income-dialog__close) { min-inline-size: 2.25rem !important; block-size: 2.25rem !important; border-radius: 999px !important; background: rgba(var(--v-theme-error), .14) !important; color: rgb(var(--v-theme-error)) !important; }
:global(.income-form-dialog .income-dialog__close .v-icon) { color: inherit !important; }
:global(.income-form-dialog .income-dialog__close .v-btn__overlay) { opacity: 0 !important; }
:global(.income-form-dialog .income-dialog__cancel) { border: 1px solid rgba(var(--v-theme-secondary), .35) !important; background: rgba(var(--v-theme-secondary), .13) !important; color: rgb(var(--v-theme-secondary)) !important; box-shadow: none !important; }
:global(.income-form-dialog .income-dialog__cancel:hover) { background: rgb(var(--v-theme-secondary)) !important; color: rgb(var(--v-theme-on-secondary)) !important; }
:global(.income-form-dialog .income-dialog__footer .v-btn--variant-flat) { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
:global(.income-actions-menu .income-actions-menu__list) {
  display: flex;
  flex-direction: column;
  padding-block: .35rem;
}
:global(.income-actions-menu .v-list-item) { min-block-size: 2.65rem; }
:global(.ledger-action-trigger) { background: rgba(var(--v-theme-on-surface), .07) !important; color: rgba(var(--v-theme-on-surface), .72) !important; }
:global(.ledger-action-trigger:hover) { background: rgba(var(--v-theme-on-surface), .13) !important; }
@media (max-width: 600px) {
  .filter-deck__content { padding: 12px !important; }
  .income-toolbar-actions { justify-content: flex-start !important; }
  .income-toolbar-actions :deep(.v-btn) { max-inline-size: 100%; }
  .income-dialog__header, .income-dialog__footer { padding-inline: 1rem; }
}
</style>
