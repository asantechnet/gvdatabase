<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ContractStatus } from '@/api/contracts'
import { contractExpiryPresentation } from '@/utils/contractExpiry'

const props = defineProps<{
  endDate: string | null
  status: ContractStatus
}>()

const { locale, t } = useI18n()

const presentation = computed(() => contractExpiryPresentation(props.endDate, props.status))

const localizedDuration = computed(() => {
  const expiry = presentation.value
  if (expiry.months === null || expiry.days === null)
    return ''

  const number = new Intl.NumberFormat(locale.value)
  const parts: string[] = []
  if (expiry.months > 0)
    parts.push(`${number.format(expiry.months)} ${t(`pages.contracts.allContracts.allContracts.${expiry.months === 1 ? 'month' : 'months'}`)}`)
  if (expiry.days > 0 || parts.length === 0)
    parts.push(`${number.format(expiry.days)} ${t(`pages.contracts.allContracts.allContracts.${expiry.days === 1 ? 'day' : 'days'}`)}`)

  return parts.join(' ')
})

const tooltip = computed(() => {
  const expiry = presentation.value
  if (expiry.urgency === 'none')
    return t('pages.contracts.allContracts.allContracts.noExpirationDate')
  if (expiry.urgency === 'inactive') {
    return t('pages.contracts.allContracts.allContracts.inactiveScheduledEnd', {
      status: translateBackendValue(props.status),
      date: formatLocalizedDate(props.endDate),
    })
  }
  if (expiry.daysRemaining === 0)
    return t('pages.contracts.allContracts.allContracts.expiresToday')
  if (expiry.daysRemaining !== null && expiry.daysRemaining < 0)
    return t('pages.contracts.allContracts.allContracts.expiredAgo', { time: localizedDuration.value })

  const labelKeys: Record<string, string> = {
    critical: 'urgencyCritical',
    attention: 'urgencyAttention',
    watch: 'urgencyWatch',
    low: 'urgencyLow',
  }

  const labelKey = labelKeys[expiry.urgency] ?? 'urgencyWatch'

  return t('pages.contracts.allContracts.allContracts.expiresIn', {
    urgency: t(`pages.contracts.allContracts.allContracts.${labelKey}`),
    time: localizedDuration.value,
  })
})
</script>

<template>
  <VChip
    :color="presentation.color"
    :prepend-icon="presentation.icon"
    size="small"
    variant="tonal"
    class="font-weight-medium expiry-date-chip"
  >
    {{ endDate ? formatLocalizedDate(endDate) : $t('pages.contracts.allContracts.allContracts.noEndDate') }}
    <VTooltip
      activator="parent"
      location="top"
      open-on-hover
    >
      {{ tooltip }}
    </VTooltip>
  </VChip>
</template>

<style scoped>
.expiry-date-chip {
  cursor: help;
}
</style>
