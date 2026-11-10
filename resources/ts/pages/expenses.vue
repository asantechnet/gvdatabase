<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useToast } from "vue-toastification";
import { type FinanceType, getFinanceTypes } from "@/api/financeTypes";
import { financeTypeLabel } from "@/utils/financeTypes";
import CurrencyApi, { type Currency } from "@/api/currency";
import {
  type Expense,
  type ExpenseCreateData,
  type ExpenseFormData,
  type ExpenseUpdateData,
  type ExpenseParams,
  type FinanceRecordStatus,
  approveExpense,
  createExpense,
  deleteExpense,
  getExpenses,
  getExpensesPage,
  rejectExpense,
  updateExpense,
} from "@/api/expenses";
import { canAccess } from "@/utils/authorization";
import { apiErrorMessage, apiFieldErrors } from "@/utils/apiErrors";
import { formatActiveMoney } from "@/utils/localization";
import { printDocument } from "@/utils/printDocument";
import { moveCreatedRowFirst } from "@/utils/createdFirst";
import PrintableListReport from "@/components/PrintableListReport.vue";
import ResponsiveDataTableServer from "@/components/responsive-data-table/ResponsiveDataTableServer.vue";
import ErpDateRange from "@/components/erp/ErpDateRange.vue";
import ErpField from "@/components/erp/ErpField.vue";
import ErpPageHeader from "@/components/erp/ErpPageHeader.vue";
import ErpSearchField from "@/components/erp/ErpSearchField.vue";
import TableEmptyState from "@/components/TableEmptyState.vue";
import ExpenseFormDialog from "@/components/forms/ExpenseFormDialog.vue";
import AttachmentsDialog from "@/components/attachments/AttachmentsDialog.vue";
import ConfirmDialog from "@/components/dialogs/ConfirmDialog.vue";

const expenses = ref<Expense[]>([]);
// The ledger asks the server for one page at a time.
const page = ref(1);
const perPage = ref(25);
const total = ref(0);
// Filled from every matching expense right before printing.
const reportRows = ref<string[][]>([]);
const currencies = ref<Currency[]>([]);
const selectedExpense = ref<ExpenseFormData | null>(null);
const isAddExpenseDialogVisible = ref(false);
const isEditExpenseDialogVisible = ref(false);
const isDeleteExpenseDialogVisible = ref(false);
const searchQuery = ref("");
const filterType = ref<string | null>(null);
const filterStatus = ref<FinanceRecordStatus | null>(null);
const filterCurrency = ref<number | null>(null);
const dateFrom = ref("");
const dateTo = ref("");
const isLoading = ref(false);
const formError = ref("");
const fieldErrors = ref<Record<string, string>>({});
const attachmentTarget = ref<Expense | null>(null);
const isAttachmentsOpen = ref(false);
const toast = useToast();
const canCreateDirect = computed(() => canAccess("create_expenses"));
const canCreateDraft = computed(() => canAccess("create_draft_finance_records"));
const canApproveDrafts = computed(() => canAccess("approve_draft_finance_records"));
const rejectDraftDialog = ref(false);
const rejectDraftTarget = ref<Expense | null>(null);
const rejectionReason = ref("");
const rejectingDraft = ref(false);

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
    financeTypes.value = await getFinanceTypes("expense");
  } catch (error) {
  }
}

// Every currency on record can be used for an expense; the base one is first.
const currencyOptions = computed(() =>
  currencies.value.map((currency) => ({
    title: `${currency.code} — ${currency.name}`,
    value: currency.id,
    rate: currency.rate,
  })),
);

const fetchCurrencies = async () => {
  const response = await CurrencyApi.getAll();

  currencies.value = response.data;
};

const filterParams = (): ExpenseParams => ({
  search: searchQuery.value || undefined,
  type: filterType.value || undefined,
  status: filterStatus.value || undefined,
  currency_id: filterCurrency.value || undefined,
  date_from: dateFrom.value || undefined,
  date_to: dateTo.value || undefined,
});

const fetchExpenses = async () => {
  try {
    isLoading.value = true;

    const result = await getExpensesPage({
      ...filterParams(),
      page: page.value,
      perPage: perPage.value,
    });

    expenses.value = result.rows;
    total.value = result.total;
  } catch (error) {
    console.error("Error fetching expenses:", error);
    expenses.value = [];
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
  fetchExpenses();
}

// A filter change starts from the first page.
const reload = () => {
  page.value = 1;
  void fetchExpenses();
};

const setDefaultCurrency = () => {
  if (!selectedExpense.value || selectedExpense.value.currency_id) return;
  const currency =
    currencies.value.find((item) => item.isBase) ?? currencies.value[0];
  if (currency) {
    selectedExpense.value.currency_id = currency.id;
    selectedExpense.value.rate = currency.rate;
  }
};

const openAddExpenseDialog = async () => {
  formError.value = "";
  fieldErrors.value = {};
  selectedExpense.value = {
    applicant_id: null,
    currency_id: 0,
    type: "",
    description: "",
    amount: 0,
    quantity: 1,
    rate: 1,
    date: new Date().toISOString().slice(0, 10),
  };
  await Promise.all([fetchCurrencies(), fetchTypes()]);
  setDefaultCurrency();
  isAddExpenseDialogVisible.value = true;
};

const openEditExpenseDialog = async (expense: Expense) => {
  formError.value = "";
  fieldErrors.value = {};
  selectedExpense.value = { ...expense };
  await Promise.all([fetchCurrencies(), fetchTypes()]);
  isEditExpenseDialogVisible.value = true;
};

const openDeleteExpenseDialog = (expense: Expense) => {
  selectedExpense.value = expense;
  isDeleteExpenseDialogVisible.value = true;
};

function openAttachments(expense: Expense) {
  attachmentTarget.value = expense;
  isAttachmentsOpen.value = true;
}

const handleCurrencyChange = (currencyId: number) => {
  if (!selectedExpense.value) return;
  const currency = currencies.value.find((item) => item.id === currencyId);
  if (currency) {
    selectedExpense.value.rate = currency.rate;
    calculateTotal();
  }
};

const calculateTotal = () => {
  if (selectedExpense.value)
    selectedExpense.value.base_amount =
      (Number(selectedExpense.value.amount) *
        Number(selectedExpense.value.quantity)) /
      Number(selectedExpense.value.rate || 1);
};

const validateExpense = () => {
  if (!selectedExpense.value) return false;

  const expense = selectedExpense.value;

  fieldErrors.value = {};
  if (!expense.type?.trim())
    fieldErrors.value.type = translate(
      "pages.finance.expenses.expenses.selectAnExpenseType",
    );
  if (
    !Number.isInteger(Number(expense.currency_id)) ||
    Number(expense.currency_id) < 1
  )
    fieldErrors.value.currency_id = translate(
      "common.messages.selectACurrency",
    );
  if (!expense.description?.trim())
    fieldErrors.value.description = translate(
      "pages.finance.expenses.expenses.enterADescription",
    );
  if (!Number.isFinite(Number(expense.amount)) || Number(expense.amount) < 0)
    fieldErrors.value.amount = translate(
      "pages.finance.expenses.expenses.enterAnAmountOfZeroOrMore",
    );
  if (
    !Number.isInteger(Number(expense.quantity)) ||
    Number(expense.quantity) < 1
  )
    fieldErrors.value.quantity = translate(
      "pages.finance.expenses.expenses.enterAWholeQuantityOfAtLeast1",
    );
  if (!Number.isFinite(Number(expense.rate)) || Number(expense.rate) <= 0)
    fieldErrors.value.rate = translate("common.messages.exchangeRate");
  if (!expense.date || Number.isNaN(Date.parse(expense.date)))
    fieldErrors.value.date = translate(
      "pages.finance.expenses.expenses.chooseAValidExpenseDate",
    );

  return Object.keys(fieldErrors.value).length === 0;
};

const saveExpense = async (
  isEditing = false,
  submittedExpense?: ExpenseFormData,
  mode: "direct" | "draft" = "direct",
) => {
  if (submittedExpense) selectedExpense.value = submittedExpense;

  if (!selectedExpense.value) return;
  if (!validateExpense()) {
    formError.value = translate(
      "pages.finance.expenses.expenses.pleaseCorrectTheHighlightedFields",
    );

    return;
  }

  try {
    formError.value = "";
    fieldErrors.value = {};

    // A draft is a real ledger row, but it does not count as money until a
    // finance approver accepts it.
    if (!isEditing && mode === "draft") {
      const draft = await createExpense({
        ...(selectedExpense.value as ExpenseCreateData),
        status: "draft",
      });
      isAddExpenseDialogVisible.value = false;
      toast.success(
        translate("pages.finance.expenses.expenses.draftSaved"),
      );
      page.value = 1;
      await fetchExpenses();
      expenses.value = moveCreatedRowFirst(expenses.value, draft).slice(
        0,
        perPage.value,
      );

      return;
    }

    const payload = {
      applicant_id: selectedExpense.value.applicant_id,
      currency_id: selectedExpense.value.currency_id,
      type: selectedExpense.value.type,
      description: selectedExpense.value.description,
      amount: selectedExpense.value.amount,
      quantity: selectedExpense.value.quantity,
      rate: selectedExpense.value.rate,
      date: selectedExpense.value.date,
    };

    if (isEditing && "id" in selectedExpense.value) {
      await updateExpense(
        selectedExpense.value.id,
        payload as ExpenseUpdateData,
      );
      await fetchExpenses();
    } else {
      const created = await createExpense(payload as ExpenseCreateData);
      // A new record belongs on the first page of the current filters.
      page.value = 1;
      await fetchExpenses();
      expenses.value = moveCreatedRowFirst(expenses.value, created).slice(
        0,
        perPage.value,
      );
    }

    isAddExpenseDialogVisible.value = false;
    isEditExpenseDialogVisible.value = false;
  } catch (error) {
    console.error("Error saving expense:", error);
    fieldErrors.value = apiFieldErrors(error);
    formError.value = apiErrorMessage(
      error,
      "Unable to save the expense record.",
    );
  }
};

const deleteExpenseRecord = async () => {
  if (!selectedExpense.value || !("id" in selectedExpense.value)) return;
  const deletedId = selectedExpense.value.id;
  try {
    await deleteExpense(deletedId);
    // The deleted row was the last one on this page, so step back instead of
    // showing an empty page.
    if (page.value > 1 && expenses.value.length === 1) page.value -= 1;
    await fetchExpenses();
    isDeleteExpenseDialogVisible.value = false;
  } catch (error) {
    console.error("Error deleting expense:", error);
    formError.value = apiErrorMessage(
      error,
      "Unable to delete the expense record.",
    );
  }
};

const formatExpenseCurrency = (value: number, currency: Expense["currency"]) =>
  formatActiveMoney(value, currency);

const formatDate = (date: string) =>
  formatLocalizedDate(`${date}T00:00:00`, {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
const expenseTypeColor = (type: string) =>
  ({
    Utilities: "info",
    "Software/SaaS": "primary",
    Marketing: "warning",
    "Office Supplies": "secondary",
    "Travel & Dining": "success",
    Salaries: "error",
  })[type] ?? "secondary";
const statusColor = (status: FinanceRecordStatus) =>
  ({ draft: "warning", approved: "success", rejected: "error" })[status];

const approveDraft = async (expense: Expense) => {
  try {
    await approveExpense(expense.id);
    await fetchExpenses();
    toast.success(translate("finance.drafts.approved"));
  } catch (error) {
    console.error("Error approving expense draft:", error);
    toast.error(translate("finance.drafts.approveFailed"));
  }
};

const openRejectDraft = (expense: Expense) => {
  rejectDraftTarget.value = expense;
  rejectionReason.value = "";
  rejectDraftDialog.value = true;
};

const rejectDraft = async () => {
  if (!rejectDraftTarget.value || !rejectionReason.value.trim()) return;

  rejectingDraft.value = true;
  try {
    await rejectExpense(
      rejectDraftTarget.value.id,
      rejectionReason.value.trim(),
    );
    rejectDraftDialog.value = false;
    await fetchExpenses();
    toast.success(translate("finance.drafts.rejected"));
  } catch (error) {
    console.error("Error rejecting expense draft:", error);
    toast.error(translate("finance.drafts.rejectFailed"));
  } finally {
    rejectingDraft.value = false;
  }
};

const expenseReportRow = (expense: Expense): string[] => [
  `EXP-${String(expense.id).padStart(6, "0")}`,
  expense.type,
  expense.description,
  expense.currency?.code ?? "—",
  formatExpenseCurrency(expense.amount * expense.quantity, expense.currency),
  formatDate(expense.date),
];

const formatExpenseNumber = (expense: Expense) => `EXP-${String(new Date(expense.date).getFullYear()).slice(-2)}-${String(expense.id).padStart(3, "0")}`;

// Print is global: the report is filled from every matching row first.
const printTable = async () => {
  try {
    // The printed register defaults to approved rows; a status filter still
    // prints exactly the rows the operator selected.
    reportRows.value = (await getExpenses({
      ...filterParams(),
      status: filterStatus.value ?? "approved",
    })).map(expenseReportRow);
    await printDocument({
      bodyClass: "printing-table-expenses",
      pageStyle: "size: A4 landscape; margin: 12mm;",
    });
  } catch (error) {
    console.error("Error preparing expense report:", error);
  }
};

onMounted(() => {
  fetchTypes();
  fetchExpenses();
  fetchCurrencies();
});
</script>

<template>
  <div class="expense-page">
    <ErpPageHeader
      :title="$t('pages.finance.expenses.expenses.expenseLedger')"
      section="finance"
      class="mb-5"
    />

    <VCard class="ledger-card filter-deck ledger-workspace" elevation="0">
      <VCardText class="filter-deck__content">
        <VRow align="center">
          <VCol cols="12" md="5">
            <ErpSearchField
              v-model="searchQuery"
              :placeholder="
                $t(
                  'pages.finance.expenses.expenses.searchExpenseDescriptionsOrTypes',
                )
              "
              @keyup.enter="reload"
            />
          </VCol>
          <VCol
            cols="12"
            md="7"
            class="expense-toolbar-actions d-flex flex-wrap align-center justify-md-end ga-2"
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
                :label="$t('pages.finance.expenses.expenses.filterByType')"
                :placeholder="$t('pages.finance.expenses.expenses.filterByType')"
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
              />
              <template #actions>
                <VBtn variant="tonal" color="secondary" @click="filterType = null; filterStatus = null; filterCurrency = null; dateFrom = ''; dateTo = ''; reload()">{{ $t('common.messages.reset') }}</VBtn>
                <VBtn color="primary" variant="flat" @click="reload">{{ $t('educational.apply') }}</VBtn>
              </template>
            </DataTableFilterMenu>
            <VBtn icon="tabler-printer" size="small" variant="tonal" :aria-label="$t('pages.finance.expenses.expenses.printExpenseRecords')" @click="printTable"><VIcon icon="tabler-printer" /><VTooltip activator="parent">{{ $t('pages.finance.expenses.expenses.printExpenseRecords') }}</VTooltip></VBtn>
            <VBtn size="small" color="primary" prepend-icon="tabler-plus" @click="openAddExpenseDialog">{{ $t('pages.finance.expenses.expenses.recordExpense') }}</VBtn>
          </VCol>
        </VRow>
      </VCardText>
      <div class="table-container">
        <ResponsiveDataTableServer
          :items="expenses"
          :items-length="total"
          :page="page"
          :items-per-page="perPage"
          :items-per-page-options="[10, 25, 50, 100]"
          :headers="[
            {
              title: translate('pages.finance.expenses.expenses.expense'),
              key: 'id',
              align: 'start',
            },
            {
              title: translate('common.fields.type'),
              key: 'type',
              align: 'start',
            },
            {
              title: translate('common.messages.quantity'),
              key: 'quantity',
              align: 'center',
            },
            {
              title: translate('common.fields.amount'),
              key: 'base_amount',
              align: 'end',
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
          class="expense-data-table"
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
          <template #item.id="{ item }">
            <div class="font-weight-bold">
              <bdi>{{ formatExpenseNumber(item) }}</bdi>
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
              class="text-caption text-medium-emphasis"
            >
              {{ item.rejection_reason }}
            </div>
          </template>
          <template #item.type="{ item }">
            <VChip
              size="small"
              :color="expenseTypeColor(item.type)"
              variant="tonal"
            >
              {{ typeLabel(item.type) }}
            </VChip>
          </template>
          <template #item.currency="{ item }">
            <VChip v-if="item.currency" size="small" variant="outlined">
              {{ item.currency.code }} </VChip
            ><span v-else>—</span>
          </template>
          <template #item.base_amount="{ item }">
            <div class="font-weight-bold text-error">
              {{
                formatExpenseCurrency(
                  item.amount * item.quantity,
                  item.currency,
                )
              }}
            </div>
            <div v-if="false" class="text-caption text-medium-emphasis">
              {{ item.base_amount }} {{ item.base_curr_shrt }} ·
              {{ $t("serviceContracts.rate") }} {{ item.rate }}
            </div>
          </template>
          <template #item.quantity="{ item }"><span>{{ formatLocalizedNumber(item.quantity) }}</span></template>
          <template #item.date="{ item }">
            {{ formatDate(item.date) }}
          </template>
          <template #item.actions="{ item }">
            <VMenu location="bottom end" content-class="expense-actions-menu">
              <template #activator="{ props }"><VBtn v-bind="props" icon="tabler-dots-vertical" variant="text" size="small" class="ledger-action-trigger" :aria-label="$t('common.messages.actions')"><VIcon icon="tabler-dots-vertical" /></VBtn></template>
              <VList density="compact" min-width="200" class="expense-actions-menu__list">
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
              <FullDetailsButton :item="item" menu-action /><VListItem
                color="medium-emphasis"
                prepend-icon="tabler-edit"
                :title="$t('common.actions.edit')"
                @click="openEditExpenseDialog(item)"
              /><VListItem
                color="error"
                prepend-icon="tabler-trash"
                :title="$t('common.actions.delete')"
                @click="openDeleteExpenseDialog(item)"
              />
              </VList>
            </VMenu>
          </template>
        </ResponsiveDataTableServer>
      </div>
    </VCard>

    <PrintableListReport
      :title="$t('common.crmUi.printReports.expenses.title')"
      :subtitle="$t('common.crmUi.printReports.expenses.subtitle')"
      :columns="[
        $t('common.crmUi.printReports.columns.expense'),
        $t('common.crmUi.printReports.columns.type'),
        $t('common.crmUi.printReports.columns.details'),
        $t('common.crmUi.printReports.columns.currency'),
        $t('common.crmUi.printReports.columns.amount'),
        $t('common.crmUi.printReports.columns.date'),
      ]"
      :rows="reportRows"
    />

    <ExpenseFormDialog
      v-model="isAddExpenseDialogVisible"
      :initial-expense="selectedExpense"
      :currency-options="currencyOptions"
      :type-options="typeOptions"
      :can-create-direct="canCreateDirect"
      :can-create-draft="canCreateDraft"
      mode="create"
      @submit="(value, mode) => saveExpense(false, value, mode)"
    />
    <ExpenseFormDialog
      v-model="isEditExpenseDialogVisible"
      :initial-expense="selectedExpense"
      :currency-options="currencyOptions"
      :type-options="typeOptions"
      :can-create-direct="canCreateDirect"
      :can-create-draft="canCreateDraft"
      mode="edit"
      @submit="saveExpense(true, $event)"
    />

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
      v-model:is-dialog-visible="isDeleteExpenseDialogVisible"
      tone="error"
      icon="tabler-trash"
      :confirmation-question="$t('pages.finance.expenses.expenses.deleteExpense')"
      :confirmation-message="$t('pages.finance.expenses.expenses.thisRemovesTheSelectedExpenseRecordPermanently')"
      :confirm-text="$t('common.actions.delete')"
      :cancel-text="$t('common.actions.cancel')"
      :confirm-title="$t('common.actions.delete')"
      confirm-msg=""
      :cancel-title="$t('common.actions.cancel')"
      cancel-msg=""
      @confirm="confirmed => { if (confirmed) deleteExpenseRecord() }"
    />

    <AttachmentsDialog
      v-model="isAttachmentsOpen"
      type="expenses"
      :record-id="attachmentTarget?.id ?? null"
      :title="attachmentTarget?.description || $t('common.attachments.title')"
    />
  </div>
</template>

<style scoped>
.expense-page {
  padding-bottom: 32px;
}
.filter-deck,
.ledger-card {
  overflow: hidden;
  border: 0;
  border-radius: 16px;
  box-shadow: 0 10px 28px rgb(36 20 31 / 0.08);
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
.expense-dialog {
  overflow: visible;
}
.table-container {
  /* Keep one horizontal scroll owner: the Vuetify table wrapper. */
  overflow: visible;
}
.expense-data-table :deep(.v-table__wrapper) { overflow-x: auto !important; overflow-y: hidden; scrollbar-width: thin; }
.expense-data-table :deep(.v-table__wrapper > table) { inline-size: max-content !important; min-inline-size: 100% !important; table-layout: auto !important; }
.expense-data-table :deep(thead th),
.expense-data-table :deep(tbody td) { inline-size: max-content; min-inline-size: max-content; white-space: nowrap; }
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
.ledger-card :deep(tbody td:last-child) { background: rgb(var(--v-theme-on-surface) / .035); }
.ledger-card :deep(tbody tr) {
  transition: background 0.16s ease;
}
.ledger-card :deep(tbody tr:hover) {
  background: rgb(var(--v-theme-error) / 0.035) !important;
}
.filter-deck :deep(.v-btn) { min-block-size: 38px; padding-inline: .85rem; font-size: .88rem; }
:global(.expense-actions-menu .expense-actions-menu__list) { display: flex; flex-direction: column; padding-block: .35rem; }
:global(.expense-actions-menu .v-list-item) { min-block-size: 2.65rem; }
:global(.ledger-action-trigger) { background: rgba(var(--v-theme-on-surface), .07) !important; color: rgba(var(--v-theme-on-surface), .72) !important; }
:global(.ledger-action-trigger:hover) { background: rgba(var(--v-theme-on-surface), .13) !important; }
@media (max-width: 600px) {
  .filter-deck__content { padding: 12px !important; }
  .expense-toolbar-actions { justify-content: flex-start !important; }
}
</style>
