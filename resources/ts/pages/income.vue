<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { VDialog } from "vuetify/components";
import { useToast } from "vue-toastification";
import { type FinanceType, getFinanceTypes } from "@/api/financeTypes";
import { financeTypeLabel } from "@/utils/financeTypes";
import {
  type FinanceRecordStatus,
  type Income,
  type IncomeCreateData,
  type IncomeParams,
  type IncomeUpdateData,
  type ApplicantContractPaymentOption,
  approveIncome,
  createIncome,
  deleteIncome,
  getIncomes,
  getIncomesPage,
  getApplicantContractPayments,
  rejectIncome,
  updateIncome,
} from "@/api/incomes";
import ReceiptApi from "@/api/receipts";
import ApplicantApi, { type Applicant } from "@/api/applicants";
import CurrencyApi, { type Currency } from "@/api/currency";
import { apiErrorMessage } from "@/utils/apiErrors";
import { formatActiveMoney } from "@/utils/localization";
import { printDocument } from "@/utils/printDocument";
import { moveCreatedRowFirst } from "@/utils/createdFirst";
import PrintableListReport from "@/components/PrintableListReport.vue";
import ResponsiveDataTableServer from "@/components/responsive-data-table/ResponsiveDataTableServer.vue";
import ErpDateRange from "@/components/erp/ErpDateRange.vue";
import TableEmptyState from "@/components/TableEmptyState.vue";
import TableLoadingSkeleton from "@/components/TableLoadingSkeleton.vue";
import ApplicantSelect from "@/components/income/ApplicantSelect.vue";
import ReceiptPrinter from "@/components/income/ReceiptPrinter.vue";
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
const applicants = ref<Applicant[]>([]);
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

const fetchApplicants = async () => {
  const response = await ApplicantApi.list({ per_page: 100 });

  applicants.value = response.data;
};

const openReceiptPreview = (income: Income) => {
  activeReceiptIncomeId.value = income.id;
  isReceiptPreviewOpen.value = true;
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
  Promise.all([fetchCurrencies(), fetchApplicants(), fetchTypes()])
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
    fetchApplicants(),
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

const handleApplicantChange = async (applicantId: number | null) => {
  selectedIncome.value.applicant_contract_id = null;
  await fetchApplicantContracts(applicantId);
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

const formatIncomeCurrency = (
  value: number | string,
  currency: Income["currency"] | undefined,
): string => formatActiveMoney(value, currency);

const formatRate = (value: number | string): string =>
  formatLocalizedNumber(value, { maximumFractionDigits: 4 });

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
  fetchApplicants();
});
</script>

<template>
  <PaymentOverridesDialog v-model="isPaymentOverridesOpen" />
  <ServicePaymentsDialog v-model="isServicePaymentsOpen" @created="reload" />
  <div class="income-page">
    <div
      class="income-page-head d-flex flex-wrap align-center justify-space-between gap-3 mb-6"
    >
      <div class="d-flex align-center ga-3">
        <VAvatar
          icon="tabler-receipt-2"
          color="primary"
          variant="tonal"
          rounded="lg"
        />
        <div>
          <h1 class="text-h5 font-weight-bold mb-0">
            {{ $t("pages.finance.income.income.incomeLedger") }}
          </h1>
          <p class="text-body-2 text-medium-emphasis mb-0">
            {{
              $t(
                "pages.finance.income.income.liveReceiptActivityAndCurrencyTotalsInOneView",
              )
            }}
          </p>
        </div>
      </div>
      <div class="d-flex flex-wrap align-center gap-2">
        <VBtn
          icon="tabler-printer"
          variant="text"
          :aria-label="$t('pages.finance.income.income.printIncomeRecords')"
          @click="printTable"
        >
          <VIcon icon="tabler-printer" />
          <VTooltip activator="parent" location="top">
            {{ $t("pages.finance.income.income.printIncomeRecords") }}
          </VTooltip>
        </VBtn>
        <VBtn
          v-if="canAccess('override_contract_payments')"
          icon="tabler-settings"
          variant="text"
          @click="isPaymentOverridesOpen = true"
        >
          <VIcon icon="tabler-settings" />
          <VTooltip activator="parent">{{
            $t("finance.paymentOverrides.title")
          }}</VTooltip>
        </VBtn>
        <VBtn
          v-if="canAccess('create_incomes')"
          prepend-icon="tabler-receipt"
          variant="tonal"
          @click="isServicePaymentsOpen = true"
        >
          {{ $t("finance.servicePayments.title") }}
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openAddIncomeDialog"
        >
          {{ $t("pages.finance.income.income.recordIncome") }}
        </VBtn>
      </div>
    </div>

    <VCard class="mb-6 filter-deck" elevation="0">
      <VCardText class="filter-deck__content">
        <VRow align="center">
          <VCol cols="12" md="8">
            <VTextField
              v-model="searchQuery"
              class="data-table-search"
              prepend-inner-icon="tabler-search"
              :placeholder="$t('pages.finance.income.income.searchIncome')"
              density="compact"
              clearable
              hide-details
              @keyup.enter="reload"
            />
          </VCol>

          <VCol
            cols="12"
            md="4"
            class="d-flex align-center justify-md-end ga-2"
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
              <VSelect
                v-model="filterStatus"
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
                density="compact"
                clearable
                hide-details
                @update:model-value="reload"
              />
              <VSelect
                v-model="filterType"
                :items="filterTypeOptions"
                :label="$t('common.messages.filterByType')"
                density="compact"
                clearable
                hide-details
                @update:model-value="reload"
              /><VSelect
                v-model="filterCurrency"
                :items="currencyOptions"
                :label="$t('common.messages.filterByCurrency')"
                density="compact"
                clearable
                hide-details
                @update:model-value="reload"
              />
              <ErpDateRange
                v-model:from="dateFrom"
                v-model:to="dateTo"
                @apply="reload"
              />
            </DataTableFilterMenu>
            <div class="text-caption text-medium-emphasis">
              <strong class="text-high-emphasis">{{ total }}</strong>
              {{ $t("pages.finance.income.income.recordsCurrentlyShown") }}
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard class="ledger-card" elevation="0">
      <VCardItem class="ledger-card__header">
        <VCardTitle class="ledger-card__title font-weight-bold">
          <VIcon icon="tabler-receipt-2" size="20" />{{
            $t("pages.finance.income.income.receiptStream")
          }}
        </VCardTitle>
        <template #append>
          <VChip size="small" color="primary" variant="tonal">
            {{ $t("pages.finance.income.income.liveLedger") }}
          </VChip>
        </template>
      </VCardItem>
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
              title: translate('common.fields.status'),
              key: 'status',
              align: 'start',
            },
            {
              title: translate('common.fields.type'),
              key: 'type',
              align: 'start',
            },
            {
              title: translate('common.messages.details'),
              key: 'description',
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
              title: translate('common.fields.date'),
              key: 'date',
              align: 'center',
            },
            {
              title: translate('common.messages.recordedBy'),
              key: 'recordedBy.name',
              align: 'start',
            },
            {
              title: translate('common.messages.actions'),
              key: 'actions',
              align: 'center',
              sortable: false,
            },
          ]"
          item-value="id"
          class="text-no-wrap"
          :loading="isLoading"
          mobile-breakpoint="sm"
          density="compact"
          @update:options="updateTableOptions"
        >
          <template #loading>
            <TableLoadingSkeleton
              :columns="8"
              :label="$t('common.messages.loading')"
            />
          </template>
          <template #no-data>
            <TableEmptyState
              icon="tabler-receipt-off"
              :description="$t('common.messages.noMatchingRecords')"
            />
          </template>
          <template #item.receipt_number="{ item }">
            <div class="font-weight-bold">
              {{
                item.receipt_number ||
                `RCPT-${String(item.id).padStart(6, "0")}`
              }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{
                $t("common.messages.currencyQuantity", {
                  currency: item.currency?.code ?? "—",
                  quantity: item.quantity,
                })
              }}
            </div>
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

          <template #item.description="{ item }">
            <div class="detail-copy">
              {{ item.description || "No description added" }}
            </div>
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
            <div class="text-caption text-medium-emphasis">
              {{
                $t("common.messages.quantityExchangeRate", {
                  quantity: item.quantity,
                  rate: formatRate(item.rate),
                })
              }}
            </div>
          </template>

          <template #item.date="{ item }">
            <span>{{ formatDate(item.date) }}</span>
          </template>
          <template #item.recordedBy.name="{ item }">
            <span>{{
              item.recordedBy?.name || $t("common.messages.systemLegacy")
            }}</span>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <VBtn
                v-if="canApproveDrafts && item.status === 'draft'"
                icon="tabler-check"
                variant="text"
                size="small"
                color="success"
                :aria-label="$t('finance.drafts.approve')"
                @click="approveDraft(item)"
              >
                <VIcon icon="tabler-check" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t("finance.drafts.approve") }}
                </VTooltip>
              </VBtn>
              <VBtn
                v-if="canApproveDrafts && item.status === 'draft'"
                icon="tabler-x"
                variant="text"
                size="small"
                color="error"
                :aria-label="$t('finance.drafts.reject')"
                @click="openRejectDraft(item)"
              >
                <VIcon icon="tabler-x" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t("finance.drafts.reject") }}
                </VTooltip>
              </VBtn>
              <VBtn
                icon="tabler-paperclip"
                variant="text"
                size="small"
                :aria-label="$t('common.attachments.title')"
                @click="openAttachments(item)"
              >
                <VIcon icon="tabler-paperclip" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t("common.attachments.title") }}
                </VTooltip>
              </VBtn>
              <FullDetailsButton :item="item" />
              <VBtn
                v-if="item.status === 'approved'"
                icon="tabler-receipt"
                variant="text"
                size="small"
                color="primary"
                :aria-label="$t('documents.receipt.printReceipt')"
                @click="openReceiptPreview(item)"
              >
                <VIcon icon="tabler-receipt" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t("documents.receipt.printReceipt") }}
                </VTooltip>
              </VBtn>
              <VBtn
                icon="tabler-edit"
                variant="text"
                size="small"
                color="medium-emphasis"
                :aria-label="$t('pages.finance.income.income.editIncomeRecord')"
                @click="openEditIncomeDialog(item)"
              >
                <VIcon icon="tabler-edit" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t("pages.finance.income.income.editIncomeRecord") }}
                </VTooltip>
              </VBtn>
              <VBtn
                icon="tabler-trash"
                variant="text"
                size="small"
                color="error"
                :aria-label="
                  $t('pages.finance.income.income.deleteIncomeRecord')
                "
                @click="openDeleteIncomeDialog(item)"
              >
                <VIcon icon="tabler-trash" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t("pages.finance.income.income.deleteIncomeRecord") }}
                </VTooltip>
              </VBtn>
            </div>
          </template>
        </ResponsiveDataTableServer>
      </div>
    </VCard>

    <ReceiptPrinter
      v-model="isReceiptPreviewOpen"
      :record-id="activeReceiptIncomeId"
      :loader="ReceiptApi.getForIncome"
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
    <VDialog v-model="isAddIncomeDialogVisible" max-width="900px">
      <VCard class="income-dialog">
        <VCardTitle class="pa-6 pb-2">
          <div class="d-flex align-center gap-3">
            <VAvatar color="primary" variant="tonal" icon="tabler-receipt" />
            <div>
              <div class="text-h5 font-weight-bold">
                {{ $t("pages.finance.income.income.recordIncome2") }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{
                  $t(
                    "pages.finance.income.income.captureAStandaloneReceiptOrApplyPaymentToAContractLine",
                  )
                }}
              </div>
            </div>
          </div>
        </VCardTitle>

        <VCardText>
          <VForm class="app-form">
            <VAlert v-if="formError" type="error" variant="tonal" class="mb-4">
              {{ formError }}
            </VAlert>
            <VRow>
              <VCol cols="12" md="6">
                <ApplicantSelect
                  v-model="selectedIncome.applicant_id"
                  :applicants="applicants"
                  :disabled="Boolean(selectedServiceLine)"
                  @update:model-value="handleApplicantChange"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedIncome.applicant_contract_id"
                  :items="serviceLineOptions"
                  :label="$t('pages.finance.income.income.service')"
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
              <VCol cols="12">
                <div class="d-flex flex-wrap gap-4 mb-1">
                  <VBtn
                    v-if="canCreateDirect"
                    :color="saveMode === 'direct' ? 'primary' : 'secondary'"
                    :variant="saveMode === 'direct' ? 'flat' : 'outlined'"
                    prepend-icon="tabler-device-floppy"
                    @click="saveMode = 'direct'"
                  >
                    {{ $t("pages.finance.income.income.saveModeDirect") }}
                  </VBtn>
                  <VBtn
                    v-if="canCreateDraft"
                    :color="saveMode === 'draft' ? 'primary' : 'secondary'"
                    :variant="saveMode === 'draft' ? 'flat' : 'outlined'"
                    prepend-icon="tabler-send"
                    @click="saveMode = 'draft'"
                  >
                    {{ $t("pages.finance.income.income.saveModeDraft") }}
                  </VBtn>
                </div>
                <p class="text-caption text-medium-emphasis mb-0">
                  {{
                    saveMode === "draft"
                      ? $t("pages.finance.income.income.saveModeDraftHint")
                      : $t("pages.finance.income.income.saveModeDirectHint")
                  }}
                </p>
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedIncome.type"
                  :items="typeOptions"
                  :label="$t('common.fields.type')"
                  :disabled="Boolean(selectedServiceLine)"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
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
                <VTextField
                  v-model.number="selectedIncome.amount"
                  :label="$t('common.fields.amount')"
                  type="number"
                  :step="selectedCurrencyStep"
                  :max="selectedServiceLine ? selectedLineRemaining : undefined"
                  required
                  @input="calculateTotal"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="selectedIncome.quantity"
                  :label="$t('common.messages.quantity')"
                  type="number"
                  :disabled="Boolean(selectedServiceLine)"
                  required
                  @input="calculateTotal"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="selectedIncome.currency_id"
                  :items="currencyOptions"
                  :label="$t('common.fields.currency')"
                  :disabled="Boolean(selectedServiceLine)"
                  required
                  @update:model-value="handleCurrencyChange"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="selectedIncome.rate"
                  :label="$t('common.messages.exchangeRate')"
                  type="number"
                  step="0.00000001"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedIncome.base_amount"
                  :label="$t('common.messages.totalAmount')"
                  type="number"
                  readonly
                />
              </VCol>
              <VCol cols="12" md="4">
                <AppDateTimePicker
                  v-model="selectedIncome.date"
                  :label="$t('common.fields.date')"
                  required
                  :config="{ dateFormat: 'Y-m-d' }"
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
            @click="isAddIncomeDialogVisible = false"
          >
            {{ $t("common.actions.cancel") }}
          </VBtn>
          <VBtn color="primary" @click="addIncome">
            {{ $t("common.actions.save") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit Income Dialog -->
    <VDialog v-model="isEditIncomeDialogVisible" max-width="900px">
      <VCard class="income-dialog">
        <VCardTitle class="pa-6 pb-2">
          <div class="d-flex align-center gap-3">
            <VAvatar color="primary" variant="tonal" icon="tabler-pencil" />
            <div>
              <div class="text-h5 font-weight-bold">
                {{ $t("pages.finance.income.income.editIncome") }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{
                  $t(
                    "pages.finance.income.income.updateReceiptDetailsOrItsContractAllocation",
                  )
                }}
              </div>
            </div>
          </div>
        </VCardTitle>

        <VCardText>
          <VForm class="app-form">
            <VAlert v-if="formError" type="error" variant="tonal" class="mb-4">
              {{ formError }}
            </VAlert>
            <VRow>
              <VCol cols="12" md="6">
                <ApplicantSelect
                  v-model="selectedIncome.applicant_id"
                  :applicants="applicants"
                  :disabled="Boolean(selectedServiceLine)"
                  @update:model-value="handleApplicantChange"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedIncome.applicant_contract_id"
                  :items="serviceLineOptions"
                  :label="$t('pages.finance.income.income.service')"
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
                <VSelect
                  v-model="selectedIncome.type"
                  :items="typeOptions"
                  :label="$t('common.fields.type')"
                  :disabled="Boolean(selectedServiceLine)"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
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
                <VTextField
                  v-model.number="selectedIncome.amount"
                  :label="$t('common.fields.amount')"
                  type="number"
                  :step="selectedCurrencyStep"
                  :max="selectedServiceLine ? selectedLineRemaining : undefined"
                  required
                  @input="calculateTotal"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="selectedIncome.quantity"
                  :label="$t('common.messages.quantity')"
                  type="number"
                  :disabled="Boolean(selectedServiceLine)"
                  required
                  @input="calculateTotal"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="selectedIncome.currency_id"
                  :items="currencyOptions"
                  :label="$t('common.fields.currency')"
                  :disabled="Boolean(selectedServiceLine)"
                  required
                  @update:model-value="handleCurrencyChange"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="selectedIncome.rate"
                  :label="$t('common.messages.exchangeRate')"
                  type="number"
                  step="0.00000001"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedIncome.base_amount"
                  :label="$t('common.messages.totalAmount')"
                  type="number"
                  readonly
                />
              </VCol>
              <VCol cols="12" md="4">
                <AppDateTimePicker
                  v-model="selectedIncome.date"
                  :label="$t('common.fields.date')"
                  required
                  :config="{ dateFormat: 'Y-m-d' }"
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
            @click="isEditIncomeDialogVisible = false"
          >
            {{ $t("common.actions.cancel") }}
          </VBtn>
          <VBtn color="primary" @click="updateIncomeRecord">
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

    <!-- Delete Income Dialog -->
    <VDialog v-model="isDeleteIncomeDialogVisible" max-width="500px">
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">{{
            $t("common.messages.confirmDelete")
          }}</span>
        </VCardTitle>

        <VCardText>
          {{
            $t("common.confirmations.areYouSureYouWantToDeleteThisIncomeRecord")
          }}
          <br />
          {{ $t("common.confirmations.thisActionCannotBeUndone") }}
        </VCardText>

        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isDeleteIncomeDialogVisible = false"
          >
            {{ $t("common.actions.cancel") }}
          </VBtn>
          <VBtn color="error" @click="deleteIncomeRecord">
            {{ $t("common.actions.delete") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
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
.filter-deck :deep(.v-field) {
  border-radius: 10px;
  background: rgb(var(--v-theme-on-surface) / 0.025);
}
.ledger-card__header {
  min-height: 68px;
  padding-inline: 20px;
}
.ledger-card__title {
  display: flex;
  align-items: center;
  gap: 9px;
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
  overflow-x: auto;
}
.ledger-card :deep(thead th) {
  height: 48px !important;
  color: rgb(var(--v-theme-on-surface) / 0.58) !important;
  font-size: 0.68rem !important;
  font-weight: 800 !important;
  letter-spacing: 0.075em;
  text-transform: uppercase;
  background: rgb(var(--v-theme-on-surface) / 0.018);
}
.ledger-card :deep(tbody td) {
  height: 66px !important;
  border-bottom-color: rgb(var(--v-border-color) / 0.7) !important;
}
.ledger-card :deep(tbody tr) {
  transition: background 0.16s ease;
}
.ledger-card :deep(tbody tr:hover) {
  background: rgb(var(--v-theme-primary) / 0.035) !important;
}
.ledger-empty {
  display: flex;
  align-items: center;
  gap: 14px;
  min-height: 176px;
  padding: 28px 32px;
  text-align: start;
  background: linear-gradient(
    90deg,
    rgb(var(--v-theme-success) / 0.07),
    transparent 58%
  );
}
.ledger-empty :deep(.v-btn) {
  margin-inline-start: auto;
}
@media (max-width: 600px) {
  .ledger-empty {
    align-items: flex-start;
    flex-direction: column;
    padding: 24px;
  }
  .ledger-empty :deep(.v-btn) {
    margin-inline-start: 0;
  }
}
</style>
