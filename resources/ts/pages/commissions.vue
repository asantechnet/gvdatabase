<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
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
import TableLoadingSkeleton from "@/components/TableLoadingSkeleton.vue";
import { moveRowIdFirst } from "@/utils/createdFirst";

const { t } = useI18n();
const tr = (key: string, values?: Record<string, unknown>) =>
  t(`common.crmUi.commissions.${key}`, values ?? {});

const toast = useToast();
const userData = useCookie<{ roles?: string[] } | null>("userData");
const loading = ref(false);
const activeTab = ref<"accruals" | "settlements">("accruals");
const accruals = ref<CommissionAccrual[]>([]);
const settlements = ref<CommissionSettlement[]>([]);
const summary = ref<CommissionSummary[]>([]);
const employees = ref<Employee[]>([]);
const employeeFilter = ref<number | null>(null);
const statusFilter = ref<string | null>(null);
const dialog = ref(false);
const adjustmentDialog = ref(false);
const paymentDialog = ref(false);
const selectedSettlement = ref<CommissionSettlement | null>(null);
const selectedAccrualIds = ref<number[]>([]);
const paymentDate = ref(new Date().toISOString().slice(0, 10));
const slipDialog = ref(false);
const printSettlementId = ref<number | null>(null);

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
const filteredAccruals = computed(() =>
  accruals.value.filter(
    (row) => !employeeFilter.value || row.employee_id === employeeFilter.value,
  ),
);

const pendingCurrencies = computed(() => {
  const ids = new Set<number>();

  return accruals.value
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
  summary.value.filter((row) => row.status !== "void"),
);

const settlementCandidates = computed(() =>
  accruals.value.filter(
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

watch(
  [
    () => form.value.employee_id,
    () => form.value.base_currency_id,
    () => form.value.period_start,
    () => form.value.period_end,
  ],
  selectAllSettlementCandidates,
);

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

const load = async () => {
  loading.value = true;
  try {
    const [accrualRows, settlementRows, summaryRows, employeeRows] =
      await Promise.all([
        CommissionApi.accruals(),
        CommissionApi.settlements({
          ...(employeeFilter.value
            ? { employee_id: employeeFilter.value }
            : {}),
          ...(statusFilter.value ? { status: statusFilter.value } : {}),
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

const openSlip = (row: CommissionSettlement) => {
  printSettlementId.value = row.id;
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
    <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
      <div>
        <h1 class="text-h5 font-weight-bold">
          {{ tr("title") }}
        </h1>
        <p class="text-medium-emphasis mb-0">
          {{ tr("subtitle") }}
        </p>
      </div>
      <div class="d-flex flex-wrap ga-2">
        <VBtn
          v-if="canAccess('manage_commission_settlements')"
          variant="tonal"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="syncCases"
        >
          {{ tr("reconcile") }}
        </VBtn>
        <VBtn
          v-if="canAccess('manage_commission_settlements')"
          variant="tonal"
          prepend-icon="tabler-adjustments"
          @click="
            adjustmentForm.employee_id = employeeFilter;
            adjustmentDialog = true;
          "
        >
          {{ tr("addAdjustment") }}
        </VBtn>
        <VBtn
          v-if="canAccess('manage_commission_settlements')"
          color="primary"
          prepend-icon="tabler-plus"
          @click="openSettlement"
        >
          {{ tr("newSettlement") }}
        </VBtn>
      </div>
    </div>

    <VRow class="mb-3">
      <VCol
        v-for="row in summaryCards"
        :key="`${row.base_currency_id}-${row.status}`"
        cols="12"
        sm="6"
        lg="3"
      >
        <VCard>
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-capitalize text-medium-emphasis">{{
                statusLabel(row.status)
              }}</span>
              <VChip :color="statusColor(row.status)" size="small">
                {{ tr("accrualCount", { count: row.count }) }}
              </VChip>
            </div>
            <div class="text-h5 font-weight-bold">
              {{ money(row.amount, row.currency) }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard>
      <VCardText>
        <VRow align="center">
          <VCol cols="12" md="4">
            <VSelect
              v-model="employeeFilter"
              :items="commissionEmployees"
              item-title="name"
              item-value="id"
              :label="tr('employee')"
              clearable
              hide-details
              @update:model-value="load"
            />
          </VCol>
          <VCol cols="12" md="4">
            <VSelect
              v-if="activeTab === 'settlements'"
              v-model="statusFilter"
              :items="
                ['draft', 'approved', 'paid', 'cancelled'].map((value) => ({
                  title: statusLabel(value),
                  value,
                }))
              "
              :label="tr('settlementStatus')"
              clearable
              hide-details
              @update:model-value="load"
            />
          </VCol>
          <VCol cols="12" md="4" class="text-md-end">
            <VBtn
              variant="text"
              prepend-icon="tabler-refresh"
              :loading="loading"
              @click="load"
            >
              {{ tr("refresh") }}
            </VBtn>
          </VCol>
        </VRow>
        <VTabs v-model="activeTab" class="mt-4">
          <VTab value="accruals">{{ tr("accruals") }}</VTab>
          <VTab value="settlements">{{ tr("settlements") }}</VTab>
        </VTabs>
      </VCardText>

      <VWindow v-model="activeTab">
        <VWindowItem value="accruals">
          <ResponsiveDataTable
            :loading="loading"
            :items="filteredAccruals"
            pagination
            mobile-cards
            :mobile-fields="[
              'earned_at',
              'entry_type',
              'employee',
              'commission_base_amount',
              'status',
            ]"
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
            <template #loading>
              <TableLoadingSkeleton
                :columns="10"
                :label="$t('common.messages.loading')"
              />
            </template>
            <template #no-data>
              <TableEmptyState
                icon="tabler-receipt-off"
                :description="$t('common.messages.noMatchingRecords')"
              />
            </template>
            <template #item.earned_at="{ item }">{{ item.earned_at }}</template>
            <template #item.entry_type="{ item }">
              <VChip size="small" variant="tonal" class="ma-1">
                {{
                  item.entry_type === "earned"
                    ? tr("entryTypes.earned")
                    : tr("entryTypes.adjustment")
                }}
              </VChip>
              <div
                v-if="item.adjustment_reason"
                class="text-caption text-medium-emphasis"
              >
                {{ item.adjustment_reason }}
              </div>
            </template>
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
            mobile-cards
            :mobile-fields="['employee', 'period', 'gross_base_amount', 'status']"
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
            <template #item.period="{ item }"
              >{{ item.period_start }} — {{ item.period_end }}</template
            >
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
              <div class="d-flex ga-1">
                <VBtn
                  size="small"
                  variant="text"
                  prepend-icon="tabler-printer"
                  @click="openSlip(item)"
                  >{{ tr("printSlip") }}</VBtn
                >
                <VBtn
                  v-if="
                    item.status === 'draft' && canAccess('approve_commissions')
                  "
                  size="small"
                  variant="tonal"
                  @click="approve(item)"
                  >{{ tr("approve") }}</VBtn
                >
                <VBtn
                  v-if="
                    (item.status === 'approved' ||
                      (isSuperAdmin && item.status === 'draft')) &&
                    canAccess('pay_commissions')
                  "
                  size="small"
                  color="success"
                  variant="tonal"
                  @click="openPayment(item)"
                  >{{ tr("pay") }}</VBtn
                >
                <VBtn
                  v-if="
                    ['draft', 'approved'].includes(item.status) &&
                    canAccess('manage_commission_settlements')
                  "
                  size="small"
                  color="error"
                  variant="text"
                  @click="cancel(item)"
                  >{{ tr("cancel") }}</VBtn
                >
              </div>
            </template>
          </ResponsiveDataTable>
        </VWindowItem>
      </VWindow>
    </VCard>

    <VDialog v-model="dialog" max-width="900">
      <VCard :title="tr('createSettlement')">
        <VCardText>
          <VRow>
            <VCol cols="12" md="6">
              <VSelect
                v-model="form.employee_id"
                :items="commissionEmployees"
                item-title="name"
                item-value="id"
                :label="tr('commissionEmployee')"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VSelect
                v-model="form.base_currency_id"
                :items="pendingCurrencies"
                item-title="title"
                item-value="id"
                :label="tr('accrualCurrency')"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <AppDateTimePicker
                v-model="form.period_start"
                :label="tr('periodStart')"
                :config="{ dateFormat: 'Y-m-d' }"
                class="w-100"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <AppDateTimePicker
                v-model="form.period_end"
                :label="tr('periodEnd')"
                :config="{ dateFormat: 'Y-m-d' }"
                class="w-100"
              />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="form.notes" :label="tr('notes')" rows="2" />
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
                <template #no-data
                  ><TableEmptyState
                    icon="tabler-receipt-off"
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
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="dialog = false">{{ tr("cancel") }}</VBtn>
          <VBtn
            :loading="loading"
            :disabled="
              !selectedSettlementAccruals.length || settlementTotal <= 0
            "
            @click="createSettlement"
          >
            {{ isSuperAdmin ? tr("createSettlement") : tr("createDraft") }}
          </VBtn>
        </VCardActions>
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

    <VDialog v-model="adjustmentDialog" max-width="600">
      <VCard :title="tr('addAdjustment')">
        <VCardText>
          <VAlert type="warning" variant="tonal" class="mb-4">{{
            tr("adjustmentWarning")
          }}</VAlert>
          <VRow>
            <VCol cols="12">
              <VSelect
                v-model="adjustmentForm.employee_id"
                :items="commissionEmployees"
                item-title="name"
                item-value="id"
                :label="tr('commissionEmployee')"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VLabel class="mb-1">{{ tr("currency") }}</VLabel>
              <VSelect
                v-model="adjustmentForm.base_currency_id"
                :items="ledgerCurrencies"
                item-title="title"
                item-value="id"
                hide-details
              />
            </VCol>
            <VCol cols="12" sm="6">
              <AppDateTimePicker
                v-model="adjustmentForm.effective_date"
                :label="tr('effectiveDate')"
                :config="{ dateFormat: 'Y-m-d' }"
                class="w-100"
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model.number="adjustmentForm.amount"
                type="number"
                step="0.01"
                :label="tr('signedAmount')"
                :hint="tr('signedAmountHint')"
                persistent-hint
              />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="adjustmentForm.reason"
                :label="tr('reason')"
                rows="3"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="adjustmentDialog = false">{{
            tr("cancel")
          }}</VBtn>
          <VBtn :loading="loading" @click="createAdjustment">{{
            tr("addToLedger")
          }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <CommissionSlipPrinter
      v-model="slipDialog"
      :settlement-id="printSettlementId"
    />
  </div>
</template>
