<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useDebounceFn } from "@vueuse/core";
import { useI18n } from "vue-i18n";
import { useCookie } from "@core/composable/useCookie";
import { useToast } from "vue-toastification";
import CommissionApi, {
  type CommissionAccrual,
  type CommissionSettlement,
  type CommissionSummary,
} from "@/api/commission";
import EmployeeApi, { type Employee } from "@/api/employee";
import CommissionSlipPrinter from "@/components/commission/CommissionSlipPrinter.vue";
import { canAccess } from "@/utils/authorization";
import {
  formatActiveMoney,
  translateApplicantStage,
} from "@/utils/localization";
import TableEmptyState from "@/components/TableEmptyState.vue";
import ErpField from "@/components/erp/ErpField.vue";
import ErpPageHeader from "@/components/erp/ErpPageHeader.vue";
import ErpSearchField from "@/components/erp/ErpSearchField.vue";
import DataTableFilterMenu from "@/components/responsive-data-table/DataTableFilterMenu.vue";
import EmployeeAvatar from "@/components/hr/EmployeeAvatar.vue";
import { moveRowIdFirst } from "@/utils/createdFirst";

const { t } = useI18n();
const tr = (key: string, values?: Record<string, unknown>) =>
  t(`common.crmUi.commissions.${key}`, values ?? {});

const toast = useToast();
const userData = useCookie<{ roles?: string[] } | null>("userData");
const loading = ref(false);
const activeTab = ref<"accruals" | "settlements">("accruals");
const accruals = ref<CommissionAccrual[]>([]);
const settlementAccruals = ref<CommissionAccrual[]>([]);
const settlements = ref<CommissionSettlement[]>([]);
const summary = ref<CommissionSummary[]>([]);
const employees = ref<Employee[]>([]);
const employeeFilter = ref<number | null>(null);
const statusFilter = ref<string | null>(null);
const startDateFilter = ref("");
const endDateFilter = ref("");
const search = ref("");
const loadingSettlementAccruals = ref(false);
const dialog = ref(false);
const adjustmentDialog = ref(false);
const paymentDialog = ref(false);
const selectedSettlement = ref<CommissionSettlement | null>(null);
const selectedAccrualIds = ref<number[]>([]);
const paymentDate = ref(new Date().toISOString().slice(0, 10));
const slipDialog = ref(false);
const printSettlementId = ref<number | null>(null);
const directPrintSlip = ref(false);

const form = ref({
  employee_id: null as number | null,
  base_currency_id: null as number | null,
  period_start: new Date(new Date().getFullYear(), new Date().getMonth(), 1)
    .toISOString()
    .slice(0, 10),
  period_end: new Date().toISOString().slice(0, 10),
  notes: "",
});

const adjustmentForm = ref({
  employee_id: null as number | null,
  base_currency_id: null as number | null,
  effective_date: new Date().toISOString().slice(0, 10),
  amount: 0,
  reason: "",
});

const commissionEmployees = computed(() =>
  employees.value.filter((employee) => employee.is_commission_based),
);
const isSuperAdmin = computed(
  () => userData.value?.roles?.includes("super_admin") ?? false,
);
const pendingCurrencies = computed(() => {
  const ids = new Set<number>();

  return settlementAccruals.value
    .filter(
      (row) =>
        row.status === "pending" &&
        (!form.value.employee_id || row.employee_id === form.value.employee_id),
    )
    .filter(
      (row) => !ids.has(row.base_currency_id) && ids.add(row.base_currency_id),
    )
    .map((row) => ({
      id: row.base_currency_id,
      title: `${row.base_currency.symbol} ${row.base_currency.code}`,
    }));
});

const summaryCards = computed(() =>
  (["pending", "approved", "paid"] as const).map((status, index) =>
    summary.value.find((row) => row.status === status) ?? {
      base_currency_id: -(index + 1),
      currency: { id: 0, code: "", symbol: "", decimal_places: 2 },
      status,
      count: 0,
      amount: 0,
    },
  ),
);
const totalAccrualCount = computed(() =>
  summaryCards.value.reduce((total, row) => total + row.count, 0),
);

const settlementCandidates = computed(() =>
  settlementAccruals.value.filter(
    (row) =>
      row.status === "pending" &&
      row.employee_id === form.value.employee_id &&
      row.base_currency_id === form.value.base_currency_id &&
      row.earned_at >= form.value.period_start &&
      row.earned_at <= form.value.period_end,
  ),
);

const selectedSettlementAccruals = computed(() =>
  settlementCandidates.value.filter((row) =>
    selectedAccrualIds.value.includes(row.id),
  ),
);

const settlementTotal = computed(() =>
  selectedSettlementAccruals.value.reduce(
    (total, row) => total + Number(row.commission_base_amount || 0),
    0,
  ),
);

const settlementCurrency = computed(
  () => settlementCandidates.value[0]?.base_currency,
);

const selectAllSettlementCandidates = () => {
  selectedAccrualIds.value = settlementCandidates.value.map((row) => row.id);
};

const loadSettlementAccruals = async () => {
  if (!form.value.employee_id) {
    settlementAccruals.value = [];
    return;
  }

  loadingSettlementAccruals.value = true;
  try {
    settlementAccruals.value = await CommissionApi.accruals({
      employee_id: form.value.employee_id,
      status: "pending",
    });
  } finally {
    loadingSettlementAccruals.value = false;
  }
};

watch(
  [
    () => form.value.employee_id,
    () => form.value.base_currency_id,
    () => form.value.period_start,
    () => form.value.period_end,
  ],
  selectAllSettlementCandidates,
);

watch(() => form.value.employee_id, () => {
  if (dialog.value) loadSettlementAccruals();
});

watch(dialog, visible => {
  if (visible) loadSettlementAccruals();
});

const ledgerCurrencies = computed(() => {
  const rows = [
    ...summary.value.map((row) => row.currency),
    ...accruals.value.map((row) => row.base_currency),
  ];

  return [
    ...new Map(
      rows
        .filter(Boolean)
        .map((currency) => [
          currency.id,
          { id: currency.id, title: `${currency.symbol} ${currency.code}` },
        ]),
    ).values(),
  ];
});

const money = (
  amount: number,
  currency?: { symbol?: string; code?: string; decimal_places?: number },
) =>
  formatActiveMoney(
    amount,
    currency
      ? { ...currency, decimalPlaces: currency.decimal_places, rate: 1 }
      : undefined,
  );

const dockDialogCalendar = (_selectedDates: Date[], _dateString: string, instance: any) => {
  window.setTimeout(() => {
    const calendar = instance?.calendarContainer as HTMLElement | undefined;
    if (!calendar) return;
    calendar.style.transform = "translateY(0) scale(0.86)";
    calendar.style.transformOrigin = "top left";
    calendar.style.zIndex = "3100";
  });
};

const statusColor = (status: string) =>
  ({
    pending: "warning",
    draft: "info",
    approved: "primary",
    paid: "success",
    cancelled: "secondary",
    void: "secondary",
  })[status] ?? "secondary";
const statusLabel = (status: string) => tr(`statuses.${status}`);
const serviceLabel = (service: string | null) =>
  service
    ? t(
        `educational.builtInServices.${service === "test_offer" ? "testOffer" : service}`,
      )
    : "—";

const formatSettlementNumber = (number: string) => {
  const match = number.match(/^COM-(\d{4})-(\d+)$/i);
  if (!match) return number;

  return `COM-${match[1].slice(-2)}-${match[2].slice(-3).padStart(3, "0")}`;
};

const load = async () => {
  loading.value = true;
  try {
    const [accrualRows, settlementRows, summaryRows, employeeRows] =
      await Promise.all([
        CommissionApi.accruals({
          ...(employeeFilter.value ? { employee_id: employeeFilter.value } : {}),
          ...(activeTab.value === "accruals" && statusFilter.value ? { status: statusFilter.value } : {}),
          ...(startDateFilter.value ? { start_date: startDateFilter.value } : {}),
          ...(endDateFilter.value ? { end_date: endDateFilter.value } : {}),
          ...(search.value.trim() ? { search: search.value.trim() } : {}),
        }),
        CommissionApi.settlements({
          ...(employeeFilter.value
            ? { employee_id: employeeFilter.value }
            : {}),
          ...(activeTab.value === "settlements" && statusFilter.value ? { status: statusFilter.value } : {}),
          ...(search.value.trim() ? { search: search.value.trim() } : {}),
        }),
        CommissionApi.summary(
          employeeFilter.value
            ? { employee_id: employeeFilter.value }
            : undefined,
        ),
        EmployeeApi.getOptions(),
      ]);

    accruals.value = accrualRows;
    settlements.value = settlementRows;
    summary.value = summaryRows;
    employees.value = employeeRows;
  } catch (error) {
  } finally {
    loading.value = false;
  }
};

const searchLedger = useDebounceFn(load, 300);
watch(search, searchLedger);
watch(activeTab, () => {
  statusFilter.value = null;
  load();
});

const resetFilters = () => {
  employeeFilter.value = null;
  statusFilter.value = null;
  startDateFilter.value = "";
  endDateFilter.value = "";
  load();
};

const syncCases = async () => {
  loading.value = true;
  try {
    const result = await CommissionApi.sync();
    toast.success(
      `${tr("syncedCases", { count: result.cases })} ${tr("syncedVoided", { count: result.voided })}`,
    );
    await load();
  } catch (error) {
  } finally {
    loading.value = false;
  }
};

const openSettlement = () => {
  form.value.employee_id = employeeFilter.value;
  form.value.base_currency_id = null;
  const oldestPending = accruals.value
    .filter(
      (row) =>
        row.status === "pending" &&
        (!employeeFilter.value || row.employee_id === employeeFilter.value),
    )
    .sort((a, b) => a.earned_at.localeCompare(b.earned_at))[0];
  form.value.period_start =
    oldestPending?.earned_at ??
    new Date(new Date().getFullYear(), new Date().getMonth(), 1)
      .toISOString()
      .slice(0, 10);
  form.value.period_end = new Date().toISOString().slice(0, 10);
  form.value.notes = "";
  selectedAccrualIds.value = [];
  dialog.value = true;
};

const createSettlement = async () => {
  if (
    !form.value.employee_id ||
    !form.value.base_currency_id ||
    !selectedAccrualIds.value.length ||
    settlementTotal.value <= 0
  )
    return;
  loading.value = true;
  try {
    const created = await CommissionApi.createSettlement({
      employee_id: form.value.employee_id,
      base_currency_id: form.value.base_currency_id,
      period_start: form.value.period_start,
      period_end: form.value.period_end,
      accrual_ids: selectedAccrualIds.value,
      notes: form.value.notes || undefined,
    });
    dialog.value = false;
    toast.success(tr("settlementCreated"));
    await load();
    settlements.value = moveRowIdFirst(settlements.value, created.id);
    activeTab.value = "settlements";
  } catch (error) {
  } finally {
    loading.value = false;
  }
};

const createAdjustment = async () => {
  if (
    !adjustmentForm.value.employee_id ||
    !adjustmentForm.value.base_currency_id ||
    !adjustmentForm.value.amount ||
    adjustmentForm.value.reason.trim().length < 5
  )
    return;
  loading.value = true;
  try {
    const created = await CommissionApi.createAdjustment({
      ...adjustmentForm.value,
      employee_id: adjustmentForm.value.employee_id,
      base_currency_id: adjustmentForm.value.base_currency_id,
    });
    adjustmentDialog.value = false;
    toast.success(tr("adjustmentAdded"));
    await load();
    accruals.value = moveRowIdFirst(accruals.value, created.id);
  } catch (error) {
  } finally {
    loading.value = false;
  }
};

const approve = async (row: CommissionSettlement) => {
  try {
    await CommissionApi.approve(row.id);
    toast.success(tr("approvedSuccess", { number: row.settlement_number }));
    await load();
  } catch (error) {
  }
};

const printSlip = (row: CommissionSettlement) => {
  printSettlementId.value = row.id;
  directPrintSlip.value = true;
  slipDialog.value = true;
};

const openPayment = (row: CommissionSettlement) => {
  selectedSettlement.value = row;
  paymentDate.value = new Date().toISOString().slice(0, 10);
  paymentDialog.value = true;
};

const pay = async () => {
  if (!selectedSettlement.value) return;
  try {
    await CommissionApi.pay(selectedSettlement.value.id, paymentDate.value);
    paymentDialog.value = false;
    toast.success(
      tr("paidSuccess", { number: selectedSettlement.value.settlement_number }),
    );
    await load();
  } catch (error) {
  }
};

const cancel = async (row: CommissionSettlement) => {
  try {
    await CommissionApi.cancel(row.id);
    toast.success(tr("cancelledSuccess", { number: row.settlement_number }));
    await load();
  } catch (error) {
  }
};

onMounted(load);
</script>

<template>
  <div class="erp-workspace">
    <ErpPageHeader :title="tr('title')" section="finance" class="mb-5" />

    <div class="commission-kpis mb-5">
      <VCard
        v-for="row in summaryCards"
        :key="`${row.base_currency_id}-${row.status}`"
        class="commission-kpi"
        border
        variant="flat"
      >
        <VCard class="pa-4" variant="flat">
          <div class="d-flex align-center justify-space-between">
            <div>
              <span class="text-caption text-uppercase text-medium-emphasis font-weight-medium">{{
                statusLabel(row.status)
              }}</span>
              <h3 class="text-h4 font-weight-bold mt-1">{{ money(row.amount, row.currency) }}</h3>
              <span class="text-caption text-medium-emphasis">{{ tr("accrualCount", { count: row.count }) }}</span>
            </div>
            <VAvatar :color="statusColor(row.status)" variant="tonal" size="48" rounded="lg"><VIcon icon="tabler-coin" size="24" /></VAvatar>
          </div>
        </VCard>
      </VCard>
      <VCard class="commission-kpi" border variant="flat">
        <VCard class="pa-4" variant="flat"><div class="d-flex align-center justify-space-between"><div><span class="text-caption text-uppercase text-medium-emphasis font-weight-medium">{{ tr('accruals') }}</span><h3 class="text-h4 font-weight-bold mt-1">{{ totalAccrualCount }}</h3><span class="text-caption text-medium-emphasis">{{ tr('accrualCount', { count: totalAccrualCount }) }}</span></div><VAvatar color="primary" variant="tonal" size="48" rounded="lg"><VIcon icon="tabler-list-check" size="24" /></VAvatar></div></VCard>
      </VCard>
    </div>

    <VCard>
      <VCardText>
        <VRow align="center" class="ga-y-3">
          <VCol cols="12" md="5"><ErpSearchField v-model="search" :placeholder="$t('common.actions.search')" :disabled="loading" /></VCol>
          <VCol cols="12" md="7" class="commission-toolbar-actions d-flex flex-wrap align-center justify-md-end ga-2">
            <DataTableFilterMenu :active="Boolean(employeeFilter || statusFilter || startDateFilter || endDateFilter)" @reset="resetFilters">
              <ErpField v-model="employeeFilter" kind="select" :items="commissionEmployees" item-title="name" item-value="id" :label="tr('employee')" :placeholder="tr('employee')" clearable />
              <ErpField v-model="statusFilter" kind="select" :items="(activeTab === 'accruals' ? ['pending', 'approved', 'paid', 'void'] : ['draft', 'approved', 'paid', 'cancelled']).map(value => ({ title: statusLabel(value), value }))" :label="tr('settlementStatus')" :placeholder="tr('settlementStatus')" clearable />
              <template v-if="activeTab === 'accruals'"><ErpField v-model="startDateFilter" kind="date" :label="tr('periodStart')" :placeholder="tr('periodStart')" :config="{ dateFormat: 'Y-m-d' }" /><ErpField v-model="endDateFilter" kind="date" :label="tr('periodEnd')" :placeholder="tr('periodEnd')" :config="{ dateFormat: 'Y-m-d' }" /></template>
              <template #actions><VBtn variant="tonal" color="secondary" @click="resetFilters">{{ $t('common.messages.reset') }}</VBtn><VBtn color="primary" variant="flat" @click="load">{{ $t('educational.apply') }}</VBtn></template>
            </DataTableFilterMenu>
            <VBtn v-if="canAccess('manage_commission_settlements')" size="small" variant="tonal" prepend-icon="tabler-refresh" :loading="loading" @click="syncCases">{{ tr('reconcile') }}</VBtn>
            <VBtn v-if="canAccess('manage_commission_settlements')" size="small" variant="tonal" prepend-icon="tabler-adjustments" @click="adjustmentForm.employee_id = employeeFilter; adjustmentDialog = true">{{ tr('addAdjustment') }}</VBtn>
            <VBtn v-if="canAccess('manage_commission_settlements')" size="small" color="primary" prepend-icon="tabler-plus" @click="openSettlement">{{ tr('newSettlement') }}</VBtn>
          </VCol>
        </VRow>
        <VTabs v-model="activeTab" class="mt-4 commission-tabs">
          <VTab value="accruals">{{ tr("accruals") }}</VTab>
          <VTab value="settlements">{{ tr("settlements") }}</VTab>
        </VTabs>
      </VCardText>

      <VWindow v-model="activeTab">
        <VWindowItem value="accruals">
          <ResponsiveDataTable
            :loading="loading"
            :items="accruals"
            pagination
            :skeleton-rows="5"
            class="commission-table"
            :show-scroll-affordance="false"
            mobile-cards
            mobile-title-key="contract_number"
            :mobile-fields="['employee', 'commission_base_amount', 'status']"
            :sticky-first-column="false"
            :sticky-last-column="false"
            :sticky-key-column="false"
            :headers="[
              {
                title: t('common.crmUi.commissionHeaders.date'),
                key: 'earned_at',
              },
              {
                title: t('common.crmUi.commissionHeaders.type'),
                key: 'entry_type',
              },
              {
                title: $t('common.messages.details'),
                key: 'details',
              },
              {
                title: t('common.crmUi.commissionHeaders.employee'),
                key: 'employee',
              },
              {
                title: t('common.crmUi.commissionHeaders.contract'),
                key: 'contract_number',
              },
              {
                title: t('common.crmUi.commissionHeaders.case'),
                key: 'case_name',
              },
              {
                title: t('common.crmUi.commissionHeaders.service'),
                key: 'service',
              },
              {
                title: t('common.crmUi.commissionHeaders.eligibleRevenue'),
                key: 'eligible_base_amount',
              },
              {
                title: t('common.crmUi.commissionHeaders.rate'),
                key: 'percentage',
              },
              {
                title: t('common.crmUi.commissionHeaders.commission'),
                key: 'commission_base_amount',
              },
              {
                title: t('common.crmUi.commissionHeaders.status'),
                key: 'status',
              },
            ]"
          >
            <template #no-data>
              <TableEmptyState
                icon="tabler-receipt-off"
                :title="$t('common.messages.noRecordsFound')"
                :description="$t('common.messages.noMatchingRecords')"
              />
            </template>
            <template #item.earned_at="{ item }">{{ item.earned_at }}</template>
            <template #item.employee="{ item }"><div class="d-flex align-center ga-2"><EmployeeAvatar :name="item.employee" :src="item.employee_avatar" size="28" /><span>{{ item.employee }}</span></div></template>
            <template #item.entry_type="{ item }">
              <VChip size="small" variant="tonal" class="ma-1">
                {{
                  item.entry_type === "earned"
                    ? tr("entryTypes.earned")
                    : tr("entryTypes.adjustment")
                }}
              </VChip>
            </template>
            <template #item.details="{ item }">{{ item.adjustment_reason || '—' }}</template>
            <template #item.contract_number="{ item }">{{
              item.contract_number ?? item.class_name ?? "—"
            }}</template>
            <template #item.case_name="{ item }">
              <div v-if="item.case_id" class="d-flex align-center ga-2">
                <RouterLink
                  v-if="canAccess('view_applicants')"
                  :to="{ name: 'applicant-case', params: { id: item.case_id } }"
                  class="text-decoration-none"
                  >{{ item.case_name }}</RouterLink
                >
                <span v-else>{{ item.case_name ?? item.class_name ?? '—' }}</span>
                <VChip
                  v-if="item.case_stage_name"
                  size="x-small"
                  variant="tonal"
                  >{{
                    translateApplicantStage({
                      key: item.case_stage_key ?? undefined,
                      name: item.case_stage_name,
                    })
                  }}</VChip
                >
              </div>
              <span v-else>—</span>
            </template>
            <template #item.service="{ item }">{{
              serviceLabel(item.service)
            }}</template>
            <template #item.eligible_base_amount="{ item }">{{
              money(item.eligible_base_amount, item.base_currency)
            }}</template>
            <template #item.percentage="{ item }"
              >{{ item.percentage }}%</template
            >
            <template #item.commission_base_amount="{ item }"
              ><strong>{{
                money(item.commission_base_amount, item.base_currency)
              }}</strong></template
            >
            <template #item.status="{ item }">
              <VChip
                :color="statusColor(item.status)"
                size="small"
                class="text-capitalize"
                >{{ statusLabel(item.status) }}</VChip
              >
            </template>
          </ResponsiveDataTable>
        </VWindowItem>

        <VWindowItem value="settlements">
          <ResponsiveDataTable
            :loading="loading"
            :items="settlements"
            pagination
            :skeleton-rows="5"
            class="commission-table"
            :show-scroll-affordance="false"
            mobile-cards
            mobile-title-key="settlement_number"
            mobile-action-key="actions"
            :mobile-fields="['employee', 'gross_base_amount', 'status']"
            mobile-actions-as-menu
            :sticky-first-column="false"
            :sticky-last-column="false"
            :sticky-key-column="false"
            :sticky-action-column="false"
            :headers="[
              {
                title: t('common.crmUi.commissionHeaders.settlement'),
                key: 'settlement_number',
              },
              {
                title: t('common.crmUi.commissionHeaders.employee'),
                key: 'employee',
              },
              {
                title: t('common.crmUi.commissionHeaders.period'),
                key: 'period',
              },
              {
                title: t('common.crmUi.commissions.accruals'),
                key: 'accrual_count',
              },
              {
                title: t('common.crmUi.commissionHeaders.amount'),
                key: 'gross_base_amount',
              },
              {
                title: t('common.crmUi.commissionHeaders.status'),
                key: 'status',
              },
              {
                title: t('common.crmUi.commissionHeaders.paymentDate'),
                key: 'payment_date',
              },
              {
                title: t('common.crmUi.commissionHeaders.actions'),
                key: 'actions',
                sortable: false,
              },
            ]"
          >
            <template #no-data>
              <TableEmptyState
                icon="tabler-receipt-off"
                :title="$t('common.messages.noRecordsFound')"
                :description="$t('common.messages.noMatchingRecords')"
              />
            </template>
            <template #item.period="{ item }"
              >{{ item.period_start }} — {{ item.period_end }}</template
            >
            <template #item.employee="{ item }"><div class="d-flex align-center ga-2"><EmployeeAvatar :name="item.employee" :src="item.employee_avatar" size="28" /><span>{{ item.employee }}</span></div></template>
            <template #item.settlement_number="{ item }"><strong>{{ formatSettlementNumber(item.settlement_number) }}</strong></template>
            <template #item.gross_base_amount="{ item }"
              ><strong>{{
                money(item.gross_base_amount, item.base_currency)
              }}</strong></template
            >
            <template #item.status="{ item }">
              <VChip
                :color="statusColor(item.status)"
                size="small"
                class="text-capitalize"
                >{{ statusLabel(item.status) }}</VChip
              >
            </template>
            <template #item.payment_date="{ item }">{{
              item.payment_date ?? "—"
            }}</template>
            <template #item.actions="{ item }">
              <VMenu location="bottom end" content-class="commission-actions-menu">
                <template #activator="{ props }"><VBtn v-bind="props" icon="tabler-dots-vertical" variant="text" size="small" class="commission-action-trigger" :aria-label="$t('common.messages.actions')"><VIcon icon="tabler-dots-vertical" /></VBtn></template>
                <VList density="compact" min-width="190" class="commission-actions-menu__list">
                  <VListItem prepend-icon="tabler-printer" @click="printSlip(item)">{{ tr("printSlip") }}</VListItem>
                  <VListItem v-if="item.status === 'draft' && canAccess('approve_commissions')" color="primary" prepend-icon="tabler-check" @click="approve(item)">{{ tr("approve") }}</VListItem>
                  <VListItem v-if="(item.status === 'approved' || (isSuperAdmin && item.status === 'draft')) && canAccess('pay_commissions')" color="success" prepend-icon="tabler-cash" @click="openPayment(item)">{{ tr("pay") }}</VListItem>
                  <VListItem v-if="['draft', 'approved'].includes(item.status) && canAccess('manage_commission_settlements')" color="error" prepend-icon="tabler-x" @click="cancel(item)">{{ tr("cancel") }}</VListItem>
                </VList>
              </VMenu>
            </template>
          </ResponsiveDataTable>
        </VWindowItem>
      </VWindow>
    </VCard>

    <VDialog v-model="dialog" max-width="900" class="commission-form-overlay" content-class="commission-form-dialog">
      <VCard class="commission-dialog app-form-dialog">
        <VCardTitle class="commission-dialog__header"><div class="d-flex align-center ga-3"><VAvatar color="primary" variant="tonal" size="38"><VIcon icon="tabler-file-invoice" /></VAvatar><span class="text-h5 font-weight-bold">{{ tr('createSettlement') }}</span></div><VBtn icon size="small" data-confirm-form-cancel class="commission-dialog__close" :aria-label="$t('common.actions.close')" @click="dialog = false"><VIcon icon="tabler-x" /></VBtn></VCardTitle>
        <VForm class="app-form" @submit.prevent="createSettlement"><VCardText class="pt-5"><VRow>
            <VCol cols="12" md="6">
              <ErpField
                v-model="form.employee_id"
                kind="select"
                :items="commissionEmployees"
                item-title="name"
                item-value="id"
                :label="tr('commissionEmployee')"
                :placeholder="tr('commissionEmployee')"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <ErpField
                v-model="form.base_currency_id"
                kind="select"
                :items="pendingCurrencies"
                item-title="title"
                item-value="id"
                :label="tr('accrualCurrency')"
                :placeholder="tr('accrualCurrency')"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <ErpField
                v-model="form.period_start"
                kind="date"
                :label="tr('periodStart')"
                :placeholder="tr('periodStart')"
                :config="{ dateFormat: 'Y-m-d', static: false, onOpen: dockDialogCalendar }"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <ErpField
                v-model="form.period_end"
                kind="date"
                :label="tr('periodEnd')"
                :placeholder="tr('periodEnd')"
                :config="{ dateFormat: 'Y-m-d', static: false, onOpen: dockDialogCalendar }"
                required
              />
            </VCol>
            <VCol cols="12">
              <ErpField v-model="form.notes" kind="textarea" :label="tr('notes')" :placeholder="tr('notes')" rows="2" />
            </VCol>
            <VCol cols="12">
              <VAlert
                :type="settlementTotal > 0 ? 'info' : 'warning'"
                variant="tonal"
              >
                {{
                  tr("settlementPreview", {
                    count: selectedSettlementAccruals.length,
                    amount: money(settlementTotal, settlementCurrency),
                  })
                }}
              </VAlert>
            </VCol>
            <VCol cols="12">
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="text-subtitle-2">{{ tr("selectAccruals") }}</span>
                <div class="d-flex ga-1">
                  <VBtn
                    size="small"
                    variant="text"
                    @click="selectAllSettlementCandidates"
                    >{{ tr("selectAll") }}</VBtn
                  >
                  <VBtn
                    size="small"
                    variant="text"
                    @click="selectedAccrualIds = []"
                    >{{ tr("clearSelection") }}</VBtn
                  >
                </div>
              </div>
              <ResponsiveDataTable
                v-model="selectedAccrualIds"
                :items="settlementCandidates"
                :loading="loadingSettlementAccruals"
                :skeleton-rows="4"
                class="commission-selection-table"
                :show-scroll-affordance="false"
                :headers="[
                  {
                    title: t('common.crmUi.commissionHeaders.date'),
                    key: 'earned_at',
                  },
                  {
                    title: t('common.crmUi.commissionHeaders.contract'),
                    key: 'contract_number',
                  },
                  {
                    title: t('common.crmUi.commissionHeaders.service'),
                    key: 'service',
                  },
                  {
                    title: t('common.crmUi.commissionHeaders.commission'),
                    key: 'commission_base_amount',
                  },
                ]"
                item-value="id"
                show-select
                density="compact"
              >
                <template #loading><span /></template>
                <template #no-data
                  ><TableEmptyState
                    icon="tabler-receipt-off"
                    :title="$t('common.messages.noRecordsFound')"
                    :description="tr('noMatchingAccruals')"
                    min-height="180"
                /></template>
                <template #item.service="{ item }">{{
                  serviceLabel(item.service)
                }}</template>
                <template #item.commission_base_amount="{ item }">{{
                  money(item.commission_base_amount, item.base_currency)
                }}</template>
              </ResponsiveDataTable>
            </VCol>
          </VRow></VCardText>
        <VCardActions class="commission-dialog__footer">
          <VSpacer />
          <VBtn data-confirm-form-cancel class="commission-dialog__cancel" @click="dialog = false">{{ tr("cancel") }}</VBtn>
          <VBtn
            color="primary"
            variant="flat"
            type="submit"
            :loading="loading"
            :disabled="
              !selectedSettlementAccruals.length || settlementTotal <= 0
            "
          >
            {{ isSuperAdmin ? tr("createSettlement") : tr("createDraft") }}
          </VBtn>
        </VCardActions></VForm>
      </VCard>
    </VDialog>

    <VDialog v-model="paymentDialog" max-width="480">
      <VCard :title="tr('paySettlement')">
        <VCardText>
          <VAlert type="info" variant="tonal" class="mb-4">{{
            tr("paymentWarning")
          }}</VAlert>
          <AppDateTimePicker
            v-model="paymentDate"
            :label="tr('paymentDate')"
            :config="{ dateFormat: 'Y-m-d' }"
            class="w-100"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="paymentDialog = false">{{
            tr("cancel")
          }}</VBtn>
          <VBtn color="success" @click="pay">{{ tr("recordPayment") }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="adjustmentDialog" max-width="600" class="commission-form-overlay" content-class="commission-form-dialog">
      <VCard class="commission-dialog app-form-dialog">
        <VCardTitle class="commission-dialog__header"><div class="d-flex align-center ga-3"><VAvatar color="primary" variant="tonal" size="38"><VIcon icon="tabler-adjustments" /></VAvatar><span class="text-h5 font-weight-bold">{{ tr('addAdjustment') }}</span></div><VBtn icon size="small" data-confirm-form-cancel class="commission-dialog__close" :aria-label="$t('common.actions.close')" @click="adjustmentDialog = false"><VIcon icon="tabler-x" /></VBtn></VCardTitle>
        <VForm class="app-form" @submit.prevent="createAdjustment"><VCardText class="pt-5">
          <VAlert type="warning" variant="tonal" class="mb-4">{{
            tr("adjustmentWarning")
          }}</VAlert>
          <VRow>
            <VCol cols="12" md="6">
              <ErpField
                v-model="adjustmentForm.employee_id"
                kind="select"
                :items="commissionEmployees"
                item-title="name"
                item-value="id"
                :label="tr('commissionEmployee')"
                :placeholder="tr('commissionEmployee')"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <ErpField
                v-model="adjustmentForm.base_currency_id"
                kind="select"
                :items="ledgerCurrencies"
                item-title="title"
                item-value="id"
                :label="tr('currency')"
                :placeholder="tr('currency')"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <ErpField
                v-model="adjustmentForm.effective_date"
                kind="date"
                :label="tr('effectiveDate')"
                :placeholder="tr('effectiveDate')"
                :config="{ dateFormat: 'Y-m-d', static: false, onOpen: dockDialogCalendar }"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <ErpField
                v-model.number="adjustmentForm.amount"
                type="number"
                step="0.01"
                :label="tr('signedAmount')"
                :placeholder="tr('signedAmount')"
                :hint="tr('signedAmountHint')"
                persistent-hint
                required
              />
            </VCol>
            <VCol cols="12">
              <ErpField
                v-model="adjustmentForm.reason"
                kind="textarea"
                :label="tr('reason')"
                :placeholder="tr('reason')"
                rows="3"
                required
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="commission-dialog__footer">
          <VSpacer />
          <VBtn data-confirm-form-cancel class="commission-dialog__cancel" @click="adjustmentDialog = false">{{
            tr("cancel")
          }}</VBtn>
          <VBtn color="primary" variant="flat" type="submit" :loading="loading">{{
            tr("addToLedger")
          }}</VBtn>
        </VCardActions></VForm>
      </VCard>
    </VDialog>

    <CommissionSlipPrinter
      v-model="slipDialog"
      :settlement-id="printSettlementId"
      :direct-print="directPrintSlip"
    />
  </div>
</template>

<style scoped>
.commission-tabs :deep(.v-slide-group__content) { flex-wrap: wrap; }
.commission-kpis { display: grid; gap: 12px; grid-template-columns: repeat(4, minmax(220px, 1fr)); }
.commission-kpis::-webkit-scrollbar { display: none; }
.commission-kpi { min-inline-size: 0; border-radius: 12px !important; }
.commission-kpi > .v-card { box-shadow: none !important; }
.commission-kpi :deep(.text-h4) { font-size: 1.15rem !important; line-height: 1.3; }
.commission-table :deep(thead th),
.commission-selection-table :deep(thead th) {
  height: 48px !important;
  background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%) !important;
  color: rgb(var(--v-theme-on-surface) / .58) !important;
  font-size: .68rem !important;
  font-weight: 800 !important;
  letter-spacing: .075em;
  text-transform: uppercase;
}
.commission-table :deep(tbody td) { min-block-size: 66px; }
.commission-table :deep(tbody td:last-child) { background: rgba(var(--v-theme-on-surface), .035); }
.commission-table :deep(.v-table__wrapper), .commission-table :deep(.v-data-table__wrapper) { overflow-x: auto !important; overflow-y: hidden; scrollbar-width: none; }
.commission-table :deep(.v-table__wrapper::-webkit-scrollbar), .commission-table :deep(.v-data-table__wrapper::-webkit-scrollbar) { display: none; }
.commission-table :deep(.v-table__wrapper > table) { inline-size: max-content !important; min-inline-size: 100% !important; table-layout: auto !important; }
.commission-table :deep(.v-table__wrapper > table > thead > tr > th), .commission-table :deep(.v-table__wrapper > table > tbody > tr > td) { inline-size: max-content; min-inline-size: max-content !important; white-space: nowrap !important; }
.commission-table :deep(.v-table__wrapper > table > thead > tr > :nth-child(1)), .commission-table :deep(.v-table__wrapper > table > tbody > tr > :nth-child(1)) { min-inline-size: 9rem; }
.commission-table :deep(.v-table__wrapper > table > thead > tr > :nth-child(2)), .commission-table :deep(.v-table__wrapper > table > tbody > tr > :nth-child(2)) { min-inline-size: 11rem; }
.commission-table :deep(.v-table__wrapper > table > thead > tr > :nth-child(3)), .commission-table :deep(.v-table__wrapper > table > tbody > tr > :nth-child(3)) { min-inline-size: 17rem; }
.commission-table :deep(.v-table__wrapper > table > thead > tr > :nth-child(4)), .commission-table :deep(.v-table__wrapper > table > tbody > tr > :nth-child(4)) { min-inline-size: 14rem; }
.commission-table :deep(.v-table__wrapper > table > thead > tr > :nth-child(5)), .commission-table :deep(.v-table__wrapper > table > tbody > tr > :nth-child(5)) { min-inline-size: 13rem; }
.commission-table :deep(.v-table__wrapper > table > thead > tr > :nth-child(6)), .commission-table :deep(.v-table__wrapper > table > tbody > tr > :nth-child(6)) { min-inline-size: 17rem; }
.commission-table :deep(.v-table__wrapper > table > thead > tr > :nth-child(7)), .commission-table :deep(.v-table__wrapper > table > tbody > tr > :nth-child(7)) { min-inline-size: 12rem; }
.commission-table :deep(.v-table__wrapper > table > thead > tr > :nth-child(8)), .commission-table :deep(.v-table__wrapper > table > tbody > tr > :nth-child(8)) { min-inline-size: 13rem; }
.commission-table :deep(.v-table__wrapper > table > thead > tr > :nth-child(9)), .commission-table :deep(.v-table__wrapper > table > tbody > tr > :nth-child(9)) { min-inline-size: 8rem; }
.commission-table :deep(.v-table__wrapper > table > thead > tr > :nth-child(10)), .commission-table :deep(.v-table__wrapper > table > tbody > tr > :nth-child(10)) { min-inline-size: 14rem; }
.commission-table :deep(.v-table__wrapper > table > thead > tr > :nth-child(11)), .commission-table :deep(.v-table__wrapper > table > tbody > tr > :nth-child(11)) { min-inline-size: 10rem; }
.commission-selection-table :deep(.v-table__wrapper) { scrollbar-width: none; }
.commission-selection-table :deep(.v-table__wrapper::-webkit-scrollbar) { display: none; }
.commission-selection-table :deep(table) { min-inline-size: 42rem; }
.commission-selection-table :deep(thead th), .commission-selection-table :deep(tbody td) { white-space: nowrap; }
.commission-toolbar-actions :deep(.v-btn) { min-block-size: 38px; padding-inline: .85rem; font-size: .88rem; }
.commission-dialog { overflow: hidden; }
.commission-dialog__header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.5rem; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%); border-block-end: 1px solid rgba(var(--v-theme-on-surface), .07); }
.commission-dialog__footer { padding: 1rem 1.5rem; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 96%, rgb(var(--v-theme-on-surface)) 4%); border-block-start: 1px solid rgba(var(--v-theme-on-surface), .07); }
.commission-dialog__close { min-inline-size: 2.25rem !important; block-size: 2.25rem !important; border-radius: 999px !important; background: rgba(var(--v-theme-error), .14) !important; color: rgb(var(--v-theme-error)) !important; }
.commission-dialog__close :deep(.v-btn__overlay) { opacity: 0 !important; }
.commission-dialog__cancel { border: 1px solid rgba(var(--v-theme-secondary), .35) !important; background: rgba(var(--v-theme-secondary), .13) !important; color: rgb(var(--v-theme-secondary)) !important; box-shadow: none !important; }
.commission-dialog__cancel:hover { background: rgb(var(--v-theme-secondary)) !important; color: rgb(var(--v-theme-on-secondary)) !important; }
:global(.commission-form-overlay .v-overlay__scrim),
:global(.v-overlay:has(.commission-form-dialog) .v-overlay__scrim) { background: rgba(var(--v-theme-on-surface), .3) !important; backdrop-filter: blur(7px); opacity: 1 !important; }
:global(.commission-actions-menu .commission-actions-menu__list) { display: flex; flex-direction: column; padding-block: .35rem; }
:global(.commission-actions-menu .v-list-item) { min-block-size: 2.65rem; }
:global(.commission-action-trigger) { background: rgba(var(--v-theme-on-surface), .07) !important; color: rgba(var(--v-theme-on-surface), .72) !important; }
:global(.commission-action-trigger:hover) { background: rgba(var(--v-theme-on-surface), .13) !important; }
@media (max-width: 959px) {
  .commission-kpis { display: flex; overflow-x: auto; padding-block-end: .25rem; scrollbar-width: none; }
  .commission-kpi { flex: 0 0 17rem; }
}
@media (max-width: 600px) {
  .commission-dialog__header, .commission-dialog__footer { padding-inline: 1rem; }
}
</style>

<!-- Keep these table rules global: ResponsiveDataTable renders Vuetify's table
 through a render function, as do the CRM visitor/follow-up tables. -->
<style>
.commission-table .v-table__wrapper {
  overflow-x: auto !important;
  overflow-y: hidden;
  scrollbar-width: none;
}
.commission-table .v-table__wrapper::-webkit-scrollbar { display: none; }
.commission-table .v-table__wrapper > table {
  inline-size: max-content !important;
  min-inline-size: 96rem !important;
  table-layout: auto !important;
}
.commission-table .v-table__wrapper > table > thead > tr > th,
.commission-table .v-table__wrapper > table > tbody > tr > td {
  min-inline-size: 12rem !important;
  padding-inline: 1.15rem !important;
  white-space: nowrap !important;
}
.commission-table .v-table__wrapper > table > thead > tr > :nth-child(1),
.commission-table .v-table__wrapper > table > tbody > tr > :nth-child(1) { min-inline-size: 14rem !important; }
.commission-table .v-table__wrapper > table > thead > tr > :nth-child(2),
.commission-table .v-table__wrapper > table > tbody > tr > :nth-child(2) { min-inline-size: 17rem !important; }
.commission-table .v-table__wrapper > table > thead > tr > :nth-child(3),
.commission-table .v-table__wrapper > table > tbody > tr > :nth-child(3) { min-inline-size: 21rem !important; }
.commission-table .v-table__wrapper > table > thead > tr > :nth-child(4),
.commission-table .v-table__wrapper > table > tbody > tr > :nth-child(4) { min-inline-size: 15rem !important; }
</style>
