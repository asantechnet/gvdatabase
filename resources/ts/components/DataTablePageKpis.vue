<script setup lang="ts">
import { computed } from 'vue'

// Historically this component rendered a row of KPI cards under the page title.
// Data-table pages now show only the plain heading; dashboards/reports own their
// own metrics. The props are kept so existing call sites stay untouched.
type TableRow = Record<string, unknown>
interface BaseCurrency { code?: string | null; symbol?: string | null; decimalPlaces?: number | null }

const props = withDefaults(defineProps<{
  items: TableRow[]
  title: string
  description: string
  icon?: string
  statusKey?: string
  baseCurrency?: BaseCurrency | null
  showScrollHint?: boolean
}>(), {
  icon: 'tabler-table',
  statusKey: 'status',
  showScrollHint: true,
})

const { t } = useI18n()

const pageTranslationKeys: Record<string, string> = {
  'Visitors': 'visitors',
  'Asset inventory': 'assetInventory',
  'Staff salaries': 'staffSalaries',
  'Staff directory': 'staffDirectory',
  'Users': 'users',
  'Positions': 'positions',
  'Leave': 'paidTimeOff',
  'HR options': 'hrOptions',
  'Finance options': 'financeOptions',
  'Contract options': 'contractOptions',
  'Cancelled contracts': 'cancelledContracts',
  'Customer directory': 'customerDirectory',
  'Contracts': 'contracts',
  'Policy library': 'policyLibrary',
}

const pageTranslationKey = computed(() => pageTranslationKeys[props.title]
  ?? Object.values(pageTranslationKeys).find(key => t(`components.dataTablePageKpis.pages.${key}.title`) === props.title)
  ?? null)

const displayTitle = computed(() => pageTranslationKey.value
  ? t(`components.dataTablePageKpis.pages.${pageTranslationKey.value}.title`)
  : props.title)

const displayDescription = computed(() => pageTranslationKey.value
  ? t(`components.dataTablePageKpis.pages.${pageTranslationKey.value}.description`)
  : props.description)
</script>

<template>
  <section
    class="data-table-page-summary mb-6"
    :aria-label="$t('components.dataTablePageKpis.overview', { title: displayTitle })"
  >
    <div class="data-table-page-summary__heading">
      <div class="d-flex align-center ga-3">
        <VAvatar
          :icon="icon"
          color="primary"
          variant="tonal"
          rounded="lg"
        />
        <div>
          <h1 class="text-h5 font-weight-bold">
            {{ displayTitle }}
          </h1>
          <p class="text-body-2 text-medium-emphasis mb-0">
            {{ displayDescription }}
          </p>
        </div>
      </div>
      <span v-if="showScrollHint" class="data-table-page-summary__hint"><VIcon
        icon="tabler-arrows-left-right"
        size="16"
      /> {{ $t('components.dataTablePageKpis.scrollHint') }}</span>
    </div>
  </section>
</template>
