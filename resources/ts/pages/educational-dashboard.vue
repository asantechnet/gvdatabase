<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import { RouterLink } from "vue-router";
import { useToast } from "vue-toastification";
import ErpPageHeader from "@/components/erp/ErpPageHeader.vue";
import ErpDateFilterMenu from "@/components/erp/ErpDateFilterMenu.vue";
import AgencyOverviewSkeleton from "@/components/dashboard/AgencyOverviewSkeleton.vue";
import DashboardApi, {
  type EducationalDashboard,
  type EducationalDashboardParams,
} from "@/api/educationalDashboard";
import { useDisplayCurrency } from "@/composables/useDisplayCurrency";
import { canAccess } from "@/utils/authorization";
import {
  formatActiveMoney,
  translateApplicantStage,
  translateBackendValue,
} from "@/utils/localization";

const { t, n } = useI18n();
const toast = useToast();
const data = ref<EducationalDashboard | null>(null);
const loading = ref(false);
const failed = ref(false);
const appliedFilters = ref<EducationalDashboardParams>({});
const dashboardContent = ref<HTMLElement>();
const skeletonHeights = ref<number[]>([]);
const skeletonSummaryHeight = ref<number>();
const { currencies: displayCurrencies, loadCurrencies: loadDisplayCurrencies } =
  useDisplayCurrency();

const applicationLink = (extra: Record<string, string | number> = {}) => ({
  name: "applications",
  query: {
    ...Object.fromEntries(
      Object.entries(appliedFilters.value).filter(([, value]) => value != null),
    ),
    ...extra,
  },
});

const money = (rows: Array<{ amount: number; currency: string | null }>) =>
  rows
    .map((row) =>
      row.currency
        ? formatActiveMoney(
            row.amount,
            displayCurrencies.value.find(
              (currency) => currency.code === row.currency,
            ) ?? { code: row.currency },
          )
        : `${n(row.amount, "decimal")} ${t("agencyDashboard.unspecifiedCurrency")}`,
    )
    .join(" · ") || n(0);

const alertGroups = [
  {
    key: "overdue_tasks" as const,
    label: () => t("educational.overdueCaseTasks"),
    icon: "tabler-calendar-exclamation",
    tone: "error",
  },
  {
    key: "stuck_applicants" as const,
    label: () => t("educational.stuckCases"),
    icon: "tabler-clock-exclamation",
    tone: "warning",
  },
  {
    key: "missing_documents" as const,
    label: () => t("agencyDashboard.documentsAwaited"),
    icon: "tabler-paperclip",
    tone: "warning",
  },
  {
    key: "contracts_requiring_attention" as const,
    label: () => t("educational.contractAttention"),
    icon: "tabler-file-alert",
    tone: "error",
  },
] as const;

const actionCount = computed(() =>
  data.value
    ? Object.values(data.value.attention.counts).reduce(
        (total, count) => total + count,
        0,
      ) +
      data.value.attention.pending_approvals +
      data.value.operations.unassigned_cases
    : 0,
);

const kpis = computed(() =>
  data.value
    ? [
        {
          key: "cases",
          label: t("educational.activeCasesLabel"),
          value: n(data.value.operations.active_cases),
          icon: "tabler-users-group",
          tone: "info",
          to: applicationLink({ status: "active", date_from: "", date_to: "" }),
        },
        {
          key: "actions",
          label: t("agencyDashboard.actionItems"),
          value: n(actionCount.value),
          icon: "tabler-alert-triangle",
          tone: "warning",
          href: "#agency-actions",
        },
        {
          key: "receivables",
          label: t("agencyDashboard.toCollect"),
          value: money(data.value.operations.outstanding),
          icon: "tabler-wallet",
          tone: "error",
          to: applicationLink({
            focus: "outstanding",
            date_from: "",
            date_to: "",
          }),
        },
        {
          key: "offers",
          label: t("agencyDashboard.admitted"),
          value: n(data.value.operations.admissions.admitted),
          icon: "tabler-school",
          tone: "success",
          to: applicationLink({
            focus: "admitted",
            date_from: "",
            date_to: "",
          }),
        },
      ]
    : [],
);

const admissionStatuses = computed(() =>
  Object.entries(data.value?.operations.admissions ?? {})
    .filter(([, count]) => count > 0)
    .map(([status, count]) => ({
      label: translateBackendValue(status),
      count,
    })),
);

const stageRows = computed(() => {
  const totals = new Map<string, { count: number; keys: string[] }>();
  for (const stage of data.value?.pipeline.stages ?? []) {
    const name = translateApplicantStage(stage);
    const previous = totals.get(name) ?? { count: 0, keys: [] };
    totals.set(name, {
      count: previous.count + stage.count,
      keys: [...previous.keys, stage.key],
    });
  }

  return [...totals.entries()].map(([name, stage]) => {
    const isAdmissionStage = stage.keys.some((key) =>
      /university|offer|admission|appl/i.test(key),
    );
    const isDocumentStage = stage.keys.some((key) =>
      /document|attach/i.test(key),
    );
    const isVisaStage = stage.keys.some((key) => /visa/i.test(key));

    return {
      name,
      count: stage.count,
      route: applicationLink({
        stage: stage.keys[0],
        date_from: "",
        date_to: "",
      }),
      statuses: isAdmissionStage
        ? admissionStatuses.value
        : isDocumentStage
          ? [
              {
                label: t("agencyDashboard.documentsAwaited"),
                count: data.value?.attention.counts.missing_documents ?? 0,
              },
            ]
          : isVisaStage
            ? [
                {
                  label: t("educational.awaitingApproval"),
                  count: data.value?.attention.pending_approvals ?? 0,
                },
              ]
            : [
                {
                  label: t("educational.activeCasesLabel"),
                  count: stage.count,
                },
              ],
    };
  });
});

const largestStageCount = computed(() =>
  Math.max(1, ...stageRows.value.map((stage) => stage.count)),
);

const actionRows = computed(() => {
  if (!data.value) return [];

  const operationalRows = alertGroups.map((group) => {
    const hit = data.value!.attention[group.key][0];
    const count = data.value!.attention.counts[group.key];

    return {
      id: group.key,
      title: group.label(),
      reference: hit ? hit.subtitle || `#${hit.id}` : n(count),
      count,
      route: hit?.route ?? { name: "applications" },
      icon: group.icon,
      tone: group.tone,
    };
  });

  return [
    ...operationalRows,
    {
      id: "pending",
      title: t("educational.awaitingApproval"),
      reference: n(data.value.attention.pending_approvals),
      count: data.value.attention.pending_approvals,
      route:
        canAccess("view_all_finance_records") ||
        canAccess("view_own_finance_records")
          ? { name: "income" }
          : { name: "applications" },
      icon: "tabler-circle-check",
      tone: "warning",
    },
  ];
});

const financialRows = computed(() =>
  data.value
    ? [
        {
          label: t("educational.postedIncome"),
          value: money(data.value.financial.income),
          isZero: !data.value.financial.income.some((row) => row.amount),
          icon: "tabler-trending-up",
          tone: "success",
          to: canAccess("view_incomes") ? { name: "income" } : undefined,
        },
        {
          label: t("educational.postedExpenses"),
          value: money(data.value.financial.expenses),
          isZero: !data.value.financial.expenses.some((row) => row.amount),
          icon: "tabler-trending-down",
          tone: "error",
          to: canAccess("view_expenses") ? { name: "expenses" } : undefined,
        },
        {
          label: t("pages.payrolls.netPayroll"),
          value: money(data.value.financial.salaries),
          isZero: !data.value.financial.salaries.some((row) => row.amount),
          icon: "tabler-cash-banknote",
          tone: "info",
        },
      ]
    : [],
);

const staff = computed(() =>
  [...(data.value?.staff ?? [])]
    .sort((a, b) => b.active_applicants - a.active_applicants)
    .slice(0, 6),
);
const destinations = computed(() => (data.value?.countries ?? []).slice(0, 6));
const largestStaffWorkload = computed(() =>
  Math.max(1, ...staff.value.map((row) => row.active_applicants)),
);
const largestDestinationCount = computed(() =>
  Math.max(1, ...destinations.value.map((row) => row.active)),
);
const progressWidth = (value: number, maximum: number) =>
  `${Math.max(0, Math.min(100, (value / maximum) * 100))}%`;

let requestId = 0;
async function load(range: EducationalDashboardParams = appliedFilters.value) {
  if (
    loading.value ||
    (range.date_from && range.date_to && range.date_from > range.date_to)
  )
    return;

  const content = dashboardContent.value;
  skeletonHeights.value = content
    ? Array.from(
        content.querySelectorAll<HTMLElement>(
          ".dashboard-analytics > .dashboard-card",
        ),
      ).map((card) => card.getBoundingClientRect().height)
    : [];
  skeletonSummaryHeight.value = content
    ?.querySelector(".dashboard-kpis")
    ?.getBoundingClientRect().height;
  const id = ++requestId;
  loading.value = true;
  failed.value = false;

  try {
    const result = await DashboardApi.get(range);
    if (id === requestId) {
      data.value = result;
      appliedFilters.value = { ...range };
    }
  } catch {
    if (id === requestId) {
      failed.value = true;
      toast.error(t("educational.loadOverviewFailed"));
    }
  } finally {
    if (id === requestId) loading.value = false;
  }
}

onMounted(() => {
  if (canAccess("view_currencies"))
    void loadDisplayCurrencies().catch(() => undefined);
  void load();
});
</script>

<template>
  <main class="dashboard-home" :aria-busy="loading">
    <ErpPageHeader
      :title="t('agencyDashboard.title')"
      :description="t('agencyDashboard.welcomeBack')"
      section="dashboard"
      :breadcrumbs="false"
    >
      <template #actions>
        <ErpDateFilterMenu
          :from="appliedFilters.date_from"
          :to="appliedFilters.date_to"
          :disabled="loading"
          @apply="load"
        />
        <VBtn
          class="dashboard-button"
          size="small"
          prepend-icon="tabler-refresh"
          :disabled="loading"
          @click="load()"
          >{{ t("agencyDashboard.refresh") }}</VBtn
        >
      </template>
    </ErpPageHeader>

    <AgencyOverviewSkeleton
      v-if="loading"
      :panel-heights="skeletonHeights"
      :summary-height="skeletonSummaryHeight"
    />
    <VAlert v-else-if="failed" type="error" variant="tonal" class="mb-4"
      >{{ t("educational.loadOverviewFailed")
      }}<VBtn class="dashboard-button ms-2" size="small" @click="load()">{{
        t("agencyDashboard.refresh")
      }}</VBtn></VAlert
    >

    <div v-else-if="data" ref="dashboardContent" class="dashboard-content">
      <section class="dashboard-kpis" :aria-label="t('agencyDashboard.title')">
        <component
          :is="kpi.to ? RouterLink : 'a'"
          v-for="kpi in kpis"
          :key="kpi.key"
          class="dashboard-kpi"
          :to="kpi.to"
          :href="kpi.href"
        >
          <span
            class="dashboard-kpi__icon"
            :class="`dashboard-kpi__icon--${kpi.tone}`"
            ><VIcon :icon="kpi.icon"
          /></span>
          <span class="dashboard-kpi__content"
            ><strong>{{ kpi.value }}</strong
            ><small>{{ kpi.label }}</small></span
          >
        </component>
      </section>

      <section class="dashboard-analytics dashboard-analytics--primary">
        <article class="dashboard-card dashboard-card--stages">
          <header class="dashboard-card__header">
            <h2>{{ t("agencyDashboard.caseProgress") }}</h2>
          </header>
          <div v-if="stageRows.length" class="dashboard-stage-list">
            <RouterLink
              v-for="stage in stageRows"
              :key="stage.name"
              :to="stage.route"
              class="dashboard-stage"
            >
              <div class="dashboard-stage__top">
                <strong>{{ stage.name }}</strong>
                <b>{{ n(stage.count) }}</b>
              </div>
              <VProgressLinear
                class="dashboard-stage__progress"
                :model-value="(stage.count / largestStageCount) * 100"
                color="primary"
                height="8"
                rounded
              />
              <div class="dashboard-stage__statuses">
                <span v-for="status in stage.statuses" :key="status.label"
                  >{{ status.label }}: {{ n(status.count) }}</span
                >
              </div>
            </RouterLink>
          </div>
          <p v-else class="dashboard-empty dashboard-empty--panel">
            {{ t("educational.noActivePipeline") }}
          </p>
        </article>
        <article
          id="agency-actions"
          class="dashboard-card dashboard-card--actions"
        >
          <header class="dashboard-card__header">
            <h2>{{ t("agencyDashboard.priorities") }}</h2>
          </header>
          <div v-if="actionRows.length" class="dashboard-action-list">
            <RouterLink
              v-for="alert in actionRows"
              :key="alert.id"
              :to="alert.route"
              class="dashboard-action"
              :class="`dashboard-action--${alert.tone}`"
              ><span class="dashboard-action__icon"
                ><VIcon :icon="alert.icon" size="18" /></span
              ><span class="dashboard-action__content"
                ><strong>{{ alert.title }}</strong
                ><small>{{ alert.reference }}</small></span
              ><span class="dashboard-action__count">{{ n(alert.count) }}</span
              ><VIcon
                class="dashboard-action__arrow"
                icon="tabler-arrow-right"
                size="18"
            /></RouterLink>
          </div>
          <p v-else class="dashboard-empty dashboard-empty--panel">
            <VIcon icon="tabler-alert-triangle" size="22" />
            <span>{{ t("agencyDashboard.noPriorityItems") }}</span>
          </p>
        </article>
      </section>

      <section class="dashboard-analytics dashboard-analytics--secondary">
        <article class="dashboard-card">
          <header class="dashboard-card__header">
            <h2>{{ t("agencyDashboard.moneyAndSales") }}</h2>
          </header>
          <div class="dashboard-financial-list">
            <component
              :is="item.to ? RouterLink : 'div'"
              v-for="item in financialRows"
              :key="item.label"
              :to="item.to"
              class="dashboard-financial-row"
              :class="{
                'dashboard-financial-row--static': !item.to,
                'dashboard-financial-row--zero': item.isZero,
                [`dashboard-financial-row--${item.tone}`]: true,
              }"
              ><span
                class="dashboard-financial-row__icon"
                :class="`dashboard-financial-row__icon--${item.tone}`"
                ><VIcon :icon="item.icon" size="18" /></span
              ><span class="dashboard-financial-row__label">{{
                item.label
              }}</span
              ><strong>{{ item.value }}</strong></component
            >
          </div>
        </article>
        <article class="dashboard-card dashboard-card--distribution">
          <header class="dashboard-card__header">
            <h2>{{ t("agencyDashboard.teamWorkload") }}</h2>
          </header>
          <div v-if="staff.length" class="dashboard-distribution-list">
            <RouterLink
              v-for="member in staff"
              :key="member.id"
              :to="
                applicationLink({
                  responsible_employee_id: member.id,
                  status: 'active',
                })
              "
              class="dashboard-distribution-row"
            >
              <strong>{{ member.name }}</strong>
              <span class="dashboard-distribution-track" aria-hidden="true"
                ><i
                  :style="{
                    inlineSize: progressWidth(
                      member.active_applicants,
                      largestStaffWorkload,
                    ),
                  }"
              /></span>
              <b>{{ n(member.active_applicants) }}</b>
            </RouterLink>
          </div>
          <p v-else class="dashboard-empty dashboard-empty--panel">
            <VIcon icon="tabler-users-group" size="22" />
            <span>{{ t("educational.noStaff") }}</span>
          </p>
        </article>
        <article class="dashboard-card dashboard-card--distribution">
          <header class="dashboard-card__header">
            <h2>{{ t("agencyDashboard.destinations") }}</h2>
          </header>
          <div v-if="destinations.length" class="dashboard-distribution-list">
            <RouterLink
              v-for="country in destinations"
              :key="country.id"
              :to="applicationLink({ country_id: country.id })"
              class="dashboard-distribution-row dashboard-distribution-row--destination"
            >
              <strong>{{ country.name }}</strong>
              <span class="dashboard-distribution-track" aria-hidden="true"
                ><i
                  :style="{
                    inlineSize: progressWidth(
                      country.active,
                      largestDestinationCount,
                    ),
                  }"
              /></span>
              <b>{{ n(country.active) }}</b>
            </RouterLink>
          </div>
          <p v-else class="dashboard-empty dashboard-empty--panel">
            <VIcon icon="tabler-map-pin" size="22" />
            <span>{{ t("educational.noCountryData") }}</span>
          </p>
        </article>
      </section>
    </div>
  </main>
</template>

<style scoped lang="scss">
.dashboard-home {
  min-block-size: 100%;
  background: rgb(var(--v-theme-background));
}
.dashboard-content,
.dashboard-kpis,
.dashboard-analytics {
  display: grid;
  gap: 16px;
}
.dashboard-content {
  gap: 20px;
}
.dashboard-kpis {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}
.dashboard-analytics--primary {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.dashboard-analytics--secondary {
  grid-template-columns: repeat(3, minmax(0, 1fr));
  align-items: start;
}
.dashboard-kpi,
.dashboard-card {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 1px 2px rgb(0 0 0 / 5%);
}
.dashboard-card--distribution {
  align-self: start;
  padding: 1.25rem;
}
.dashboard-distribution-list {
  display: grid;
  gap: 19px;
}
.dashboard-distribution-row {
  display: grid;
  grid-template-columns: minmax(110px, 1fr) minmax(72px, 1.4fr) auto;
  align-items: center;
  gap: 12px;
  min-inline-size: 0;
  color: inherit;
  text-decoration: none;
}
.dashboard-distribution-row strong {
  overflow-wrap: anywhere;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.8125rem;
  font-weight: 650;
  line-height: 1.3;
}
.dashboard-distribution-row b {
  min-inline-size: 1.25rem;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.8125rem;
  font-variant-numeric: tabular-nums;
  text-align: end;
}
.dashboard-distribution-track {
  display: block;
  overflow: hidden;
  block-size: 8px;
  border-radius: 9999px;
  background: rgba(var(--v-theme-on-surface), 0.07);
}
.dashboard-distribution-track i {
  display: block;
  min-inline-size: 2px;
  block-size: 100%;
  border-radius: inherit;
  background: rgb(var(--v-theme-primary));
  transition: inline-size 0.2s ease;
}
.dashboard-distribution-row--destination .dashboard-distribution-track i {
  background: rgb(var(--v-theme-success));
}
.dashboard-distribution-row:hover strong,
.dashboard-distribution-row:focus-visible strong {
  color: rgb(var(--v-theme-primary));
}
.dashboard-distribution-row:focus-visible {
  outline: 2px solid rgba(var(--v-theme-primary), 0.45);
  outline-offset: 3px;
}
.dashboard-kpi {
  display: flex;
  align-items: center;
  gap: 14px;
  min-block-size: 112px;
  min-inline-size: 0;
  padding: calc(1.5rem + 4px) 1.25rem 1.5rem;
  color: inherit;
  text-decoration: none;
  transition:
    border-color 0.18s ease,
    box-shadow 0.18s ease;
  &:hover,
  &:focus-visible {
    border-color: rgba(var(--v-theme-primary), 0.45);
    box-shadow: 0 3px 8px rgb(0 0 0 / 7%);
    outline: none;
  }
}
.dashboard-kpi__icon {
  display: grid;
  flex: 0 0 48px;
  inline-size: 48px;
  block-size: 48px;
  place-items: center;
  border-radius: 10px;
  background: rgba(var(--v-theme-primary), 0.1);
  color: rgb(var(--v-theme-primary));
  opacity: 0.86;
}
.dashboard-kpi__icon :deep(.v-icon) {
  font-size: 1.5rem;
}
.dashboard-kpi__icon--info {
  color: rgb(var(--v-theme-info));
  background: rgba(var(--v-theme-info), 0.12);
}
.dashboard-kpi__icon--warning {
  color: rgb(var(--v-theme-warning));
  background: rgba(var(--v-theme-warning), 0.12);
}
.dashboard-kpi__icon--error {
  color: rgb(var(--v-theme-error));
  background: rgba(var(--v-theme-error), 0.12);
}
.dashboard-kpi__icon--success {
  color: rgb(var(--v-theme-success));
  background: rgba(var(--v-theme-success), 0.12);
}
.dashboard-kpi__content {
  display: grid;
  min-inline-size: 0;
  gap: 2px;
}
.dashboard-kpi__content strong {
  overflow: hidden;
  color: rgb(var(--v-theme-on-surface));
  font-size: 1.3rem;
  font-variant-numeric: tabular-nums;
  line-height: 1.25;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.dashboard-kpi__content small {
  overflow: hidden;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.3;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.dashboard-card {
  min-inline-size: 0;
  padding: 20px;
}
.dashboard-card__header {
  margin-block-end: 12px;
}
.dashboard-card__header h2 {
  margin: 0;
  color: rgb(var(--v-theme-on-surface));
  font-size: 1rem;
  font-weight: 650;
  line-height: 1.35;
}
.dashboard-card--stages,
.dashboard-card--actions {
  display: flex;
  flex-direction: column;
  block-size: 420px;
  max-block-size: 420px;
  overflow: hidden;
}
.dashboard-analytics--secondary > .dashboard-card {
  block-size: 324px;
  max-block-size: 324px;
}
.dashboard-stage-list {
  display: grid;
  flex: 1 1 auto;
  gap: 12px;
  min-block-size: 0;
  overflow-y: auto;
  padding-inline-end: 4px;
  scrollbar-width: none;
}
.dashboard-stage-list::-webkit-scrollbar,
.dashboard-action-list::-webkit-scrollbar {
  display: none;
}
.dashboard-stage {
  display: grid;
  gap: 7px;
  padding: 10px 12px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  border-radius: 8px;
  color: inherit;
  text-decoration: none;
  transition:
    border-color 0.18s ease,
    background-color 0.18s ease;
  &:hover,
  &:focus-visible {
    border-color: rgba(var(--v-theme-primary), 0.36);
    background: rgba(var(--v-theme-primary), 0.045);
    outline: none;
  }
}
.dashboard-stage__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-inline-size: 0;
}
.dashboard-stage__top strong {
  overflow: hidden;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.875rem;
  font-weight: 650;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.dashboard-stage__top b {
  flex: 0 0 auto;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.8125rem;
  font-variant-numeric: tabular-nums;
}
.dashboard-stage__progress {
  opacity: 0.92;
}
.dashboard-stage__statuses {
  display: flex;
  flex-wrap: wrap;
  gap: 5px 8px;
}
.dashboard-stage__statuses span {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
  line-height: 1.25;
}
.dashboard-empty {
  margin: 0;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.875rem;
}
.dashboard-empty--panel {
  display: grid;
  flex: 1 1 auto;
  align-content: center;
  justify-items: center;
  gap: 8px;
  min-block-size: 0;
  border: 1px dashed rgba(var(--v-theme-on-surface), 0.16);
  border-radius: 8px;
  text-align: center;
}
.dashboard-empty--panel .v-icon {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}
.dashboard-action-list {
  display: grid;
  flex: 1 1 auto;
  gap: 10px;
  min-block-size: 0;
  overflow-y: auto;
  padding-inline-end: 4px;
  scrollbar-width: none;
}
.dashboard-action {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto auto;
  align-items: center;
  gap: 12px;
  min-inline-size: 0;
  padding: 10px 12px;
  border-inline-start: 3px solid rgb(var(--v-theme-warning));
  border-radius: 6px;
  background: rgba(var(--v-theme-warning), 0.07);
  color: inherit;
  text-decoration: none;
  &--error {
    border-inline-start-color: rgb(var(--v-theme-error));
    background: rgba(var(--v-theme-error), 0.07);
  }
  &:hover {
    background: rgba(var(--v-theme-warning), 0.13);
  }
  &--error:hover {
    background: rgba(var(--v-theme-error), 0.13);
  }
}
.dashboard-action__icon {
  display: grid;
  inline-size: 32px;
  block-size: 32px;
  place-items: center;
  border-radius: 8px;
  color: rgb(var(--v-theme-on-warning));
  background: rgb(var(--v-theme-warning));
  box-shadow: 0 1px 2px rgba(var(--v-theme-warning), 0.24);
}
.dashboard-action--error .dashboard-action__icon {
  color: rgb(var(--v-theme-on-error));
  background: rgb(var(--v-theme-error));
  box-shadow: 0 1px 2px rgba(var(--v-theme-error), 0.24);
}
.dashboard-action__icon .v-icon {
  color: inherit;
}
.dashboard-action__content {
  display: grid;
  min-inline-size: 0;
  gap: 2px;
}
.dashboard-action strong {
  overflow: hidden;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.875rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.dashboard-action small {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
}
.dashboard-action__count {
  min-inline-size: 1.5rem;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
  font-variant-numeric: tabular-nums;
  text-align: end;
}
.dashboard-action > .v-icon {
  flex: 0 0 auto;
  color: rgb(var(--v-theme-primary));
}
:global(html[dir="rtl"] .dashboard-action__arrow) {
  transform: rotate(180deg);
}
.dashboard-financial-list {
  display: grid;
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}
.dashboard-financial-row {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) minmax(max-content, auto);
  align-items: center;
  gap: 12px;
  padding-block: 16px;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  color: inherit;
  text-decoration: none;
  &:last-child {
    border-block-end: 0;
  }
  &:not(&--static):hover .dashboard-financial-row__label {
    color: rgb(var(--v-theme-primary));
  }
}
.dashboard-financial-row__icon {
  display: grid;
  inline-size: 32px;
  block-size: 32px;
  place-items: center;
  border-radius: 8px;
  background: rgba(var(--v-theme-primary), 0.08);
}
.dashboard-financial-row__icon--success {
  color: rgb(var(--v-theme-success));
  background: rgba(var(--v-theme-success), 0.12);
}
.dashboard-financial-row__icon--error {
  color: rgb(var(--v-theme-error));
  background: rgba(var(--v-theme-error), 0.12);
}
.dashboard-financial-row__icon--info {
  color: rgb(var(--v-theme-info));
  background: rgba(var(--v-theme-info), 0.12);
}
.dashboard-financial-row__icon .v-icon {
  color: inherit;
  opacity: 0.8;
}
.dashboard-financial-row__label {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.875rem;
  font-weight: 550;
}
.dashboard-financial-row strong {
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.875rem;
  font-weight: 650;
  font-variant-numeric: tabular-nums;
  text-align: end;
}
.dashboard-financial-row--success strong {
  color: rgb(var(--v-theme-success));
}
.dashboard-financial-row--error strong {
  color: rgb(var(--v-theme-error));
}
.dashboard-financial-row--info strong {
  color: rgb(var(--v-theme-info));
}
.dashboard-financial-row--zero strong {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-weight: 500;
}
.dashboard-button {
  background: rgba(var(--v-theme-primary), 0.12) !important;
  color: rgb(var(--v-theme-on-surface)) !important;
}
.dashboard-button:hover,
.dashboard-button:focus-visible {
  background: rgb(var(--v-theme-primary)) !important;
  color: rgb(var(--v-theme-on-primary)) !important;
}
@media (max-width: 1279px) {
  .dashboard-analytics--secondary {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .dashboard-analytics--secondary > :last-child {
    grid-column: span 2;
  }
  .dashboard-kpis {
    display: flex;
    overflow-x: auto;
    padding-block: 2px;
    scrollbar-width: none;
    scroll-snap-type: inline mandatory;
  }
  .dashboard-kpis::-webkit-scrollbar {
    display: none;
  }
  .dashboard-kpi {
    flex: 0 0 260px;
    scroll-snap-align: start;
  }
}
@media (max-width: 959px) {
  .dashboard-analytics--primary {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 599px) {
  .dashboard-kpi {
    flex: 0 0 min(82vw, 300px);
    scroll-snap-align: start;
  }
  .dashboard-analytics--secondary {
    grid-template-columns: 1fr;
  }
  .dashboard-analytics--secondary > :last-child {
    grid-column: auto;
  }
  .dashboard-card {
    padding: 16px;
  }
  .dashboard-kpi {
    min-block-size: 104px;
    padding: 1.25rem 1rem;
  }
  .dashboard-kpi__icon {
    flex-basis: 44px;
    inline-size: 44px;
    block-size: 44px;
  }
}
</style>
