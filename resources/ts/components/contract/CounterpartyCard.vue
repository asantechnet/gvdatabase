<script setup lang="ts">
/**
 * Counterparty summary used during contract creation/editing.
 *
 * It makes explicit which domain record (Employee or Applicant) is
 * acting as the counterparty and which identity data will be inserted into
 * the contract. Editing the underlying record happens in its own domain
 * screen — never inside a contract form.
 *
 * When `editable` is set (the contract form), it also hosts the contract's
 * start/end date selectors so the adoption period lives next to the
 * counterparty it belongs to.
 */
export interface CounterpartyDetails {
  kind: 'employee' | 'customer' | null
  name?: string | null
  subtitle?: string | null
  phone?: string | null
  email?: string | null
  address?: string | null
}

const props = withDefaults(defineProps<{
  details: CounterpartyDetails | null
  startDate?: string | null
  endDate?: string | null
  editable?: boolean
  startRequired?: boolean
  showEndDateHint?: boolean
  /** Override the card title (other contracts have no counterparty). */
  titleKey?: string | null
}>(), {
  startDate: null,
  endDate: null,
  editable: false,
  startRequired: false,
  showEndDateHint: true,
  titleKey: null,
})

const emit = defineEmits<{
  (e: 'update:startDate', value: string | null): void
  (e: 'update:endDate', value: string | null): void
}>()

const { t } = useI18n()
const tr = (key: string, values: Record<string, unknown> = {}) => t(`components.contractCounterpartyCard.${key}`, values)

const meta = {
  employee: {
    icon: 'tabler-user',
    labelKey: 'components.contractCounterpartyCard.types.employee',
    color: 'info',
  },
  customer: {
    icon: 'tabler-users',
    labelKey: 'components.contractCounterpartyCard.types.customer',
    color: 'success',
  },
} as const

const rowsFor = (details: CounterpartyDetails) => [
  { icon: 'tabler-phone', label: tr('phone'), value: details.phone },
  { icon: 'tabler-mail', label: tr('email'), value: details.email },
  { icon: 'tabler-map-pin', label: tr('address'), value: details.address },
].filter(row => row.value)

const hasDates = computed(() => props.editable || !!props.startDate || !!props.endDate)

const startRules = computed(() => (props.editable && props.startRequired
  ? [(value: unknown) => !!value || tr('startDateRequired')]
  : []))

const dockCalendar = (_dates: Date[], _value: string, instance: { calendarContainer: HTMLElement }) => {
  requestAnimationFrame(() => {
    instance.calendarContainer.style.transform = 'translateY(0) scale(0.86)'
    instance.calendarContainer.style.transformOrigin = 'top left'
    instance.calendarContainer.style.zIndex = '2400'
  })
}
</script>

<template>
  <VCard variant="outlined" class="counterparty-card" rounded="lg">
    <VCardItem class="pb-1">
      <template #prepend>
        <VAvatar :color="details?.kind ? meta[details.kind].color : undefined" variant="tonal" size="40">
          <VIcon :icon="details?.kind ? meta[details.kind].icon : 'tabler-users-group'" />
        </VAvatar>
      </template>
      <VCardTitle class="text-body-1">
        {{ t(titleKey ?? 'components.contractCounterpartyCard.title') }}
      </VCardTitle>
      <VCardSubtitle>
        <VChip v-if="details?.kind" size="x-small" :color="meta[details.kind].color" variant="tonal" class="me-2">
          {{ t(meta[details.kind].labelKey) }}
        </VChip>
        {{ tr('selectedRecordHint', { type: details?.kind ? t(meta[details.kind].labelKey) : tr('record') }) }}
      </VCardSubtitle>
    </VCardItem>

    <VCardText v-if="details && details.name" class="pt-0">
      <p class="text-h6 mb-0">
        {{ details.name }}
      </p>
      <p v-if="details.subtitle" class="text-medium-emphasis text-caption">
        {{ details.subtitle }}
      </p>

      <VDivider class="my-3" />

      <div class="d-flex flex-column gap-2">
        <div v-for="row in rowsFor(details)" :key="row.label" class="d-flex align-center gap-2 text-body-2">
          <VIcon :icon="row.icon!" size="16" class="text-disabled" />
          <span class="text-disabled" style="min-inline-size: 130px">{{ row.label }}</span>
          <span class="font-weight-medium">{{ row.value }}</span>
        </div>
      </div>

    </VCardText>

    <VCardText v-else class="text-center py-8">
      <VIcon icon="tabler-file-description" size="40" class="text-disabled mb-2" />
      <p class="text-body-2 text-medium-emphasis mb-0">
        {{ tr('empty') }}
      </p>
    </VCardText>

    <VCardText v-if="hasDates" class="pt-0">
      <VDivider class="my-3" />

      <p class="text-subtitle-2 text-medium-emphasis mb-2">
        {{ tr('contractDates') }}
      </p>

      <VRow v-if="editable" dense>
        <VCol cols="12" sm="6">
          <AppDateTimePicker :model-value="startDate ?? ''" :label="tr('startDate')"
            prepend-inner-icon="tabler-calendar-event" :rules="startRules" required
            :config="{ dateFormat: 'Y-m-d', static: false, onOpen: dockCalendar }"
            @update:model-value="emit('update:startDate', $event === '' ? null : $event)" />
        </VCol>
        <VCol cols="12" sm="6">
          <AppDateTimePicker :model-value="endDate ?? ''" :label="tr('endDate')"
            prepend-inner-icon="tabler-calendar-check" :hint="showEndDateHint ? tr('endDateHint') : undefined" :persistent-hint="showEndDateHint" clearable
            :config="{ dateFormat: 'Y-m-d', static: false, onOpen: dockCalendar }"
            @update:model-value="emit('update:endDate', $event === '' ? null : $event)" />
        </VCol>
      </VRow>

      <div v-else class="d-flex flex-column gap-2">
        <div v-if="startDate" class="d-flex align-center gap-2 text-body-2">
          <VIcon icon="tabler-calendar-event" size="16" class="text-disabled" />
          <span class="text-disabled" style="min-inline-size: 130px">{{ tr('startDate') }}</span>
          <span class="font-weight-medium">{{ startDate }}</span>
        </div>
        <div v-if="endDate" class="d-flex align-center gap-2 text-body-2">
          <VIcon icon="tabler-calendar-check" size="16" class="text-disabled" />
          <span class="text-disabled" style="min-inline-size: 130px">{{ tr('endDate') }}</span>
          <span class="font-weight-medium">{{ endDate }}</span>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.counterparty-card {
  border-color: rgba(var(--v-theme-primary), .18) !important;
  background: linear-gradient(135deg, rgba(var(--v-theme-primary), .055), rgb(var(--v-theme-surface)) 42%) !important;
  box-shadow: 0 8px 24px rgba(var(--v-theme-on-surface), .04);
}

.counterparty-card :deep(.v-card-title) { font-weight: 700; }
.counterparty-card :deep(.v-avatar) { box-shadow: inset 0 0 0 1px rgba(var(--v-theme-primary), .12); }
</style>
