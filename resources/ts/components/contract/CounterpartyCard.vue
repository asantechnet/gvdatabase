<script setup lang="ts">
/**
 * Read-only counterparty summary used during contract creation/editing.
 *
 * It makes explicit which domain record (Employee, Customer or Company) is
 * acting as the counterparty and which identity data will be inserted into
 * the contract. Editing the underlying record happens in its own domain
 * screen — never inside a contract form.
 */
export interface CounterpartyDetails {
  kind: 'employee' | 'customer' | 'company' | null
  name?: string | null
  subtitle?: string | null
  registrationNumber?: string | null
  taxNumber?: string | null
  phone?: string | null
  email?: string | null
  address?: string | null
  representativeName?: string | null
  representativeTitle?: string | null
}

defineProps<{
  details: CounterpartyDetails | null
}>()

const meta = {
  employee: {
    icon: 'tabler-user',
    label: 'Employee',
    color: 'info',
  },
  customer: {
    icon: 'tabler-users',
    label: 'Customer',
    color: 'success',
  },
  company: {
    icon: 'tabler-building',
    label: 'Business Company',
    color: 'primary',
  },
} as const

const rowsFor = (details: CounterpartyDetails) => [
  { icon: 'tabler-hash', label: 'Registration No.', value: details.registrationNumber },
  { icon: 'tabler-receipt-tax', label: 'Tax No.', value: details.taxNumber },
  { icon: 'tabler-phone', label: 'Phone', value: details.phone },
  { icon: 'tabler-mail', label: 'Email', value: details.email },
  { icon: 'tabler-map-pin', label: 'Address', value: details.address },
].filter(row => row.value)
</script>

<template>
  <VCard variant="outlined" class="counterparty-card" rounded="lg">
    <VCardItem class="pb-1">
      <template #prepend>
        <VAvatar :color="details?.kind ? meta[details.kind].color : undefined" variant="tonal" icon="tabler-help" size="40">
          <VIcon v-if="details?.kind" :icon="meta[details.kind].icon" />
        </VAvatar>
      </template>
      <VCardTitle class="text-body-1">Counterparty</VCardTitle>
      <VCardSubtitle>
        <VChip v-if="details?.kind" size="x-small" :color="meta[details.kind].color" variant="tonal" class="me-2">
          {{ meta[details.kind].label }}
        </VChip>
        Data from the selected {{ details?.kind ?? 'record' }} — read-only here
      </VCardSubtitle>
    </VCardItem>

    <VCardText v-if="details && details.name" class="pt-0">
      <p class="text-h6 mb-0">{{ details.name }}</p>
      <p v-if="details.subtitle" class="text-medium-emphasis text-caption">{{ details.subtitle }}</p>

      <VDivider class="my-3" />

      <div class="d-flex flex-column gap-2">
        <div v-for="row in rowsFor(details)" :key="row.label" class="d-flex align-center gap-2 text-body-2">
          <VIcon :icon="row.icon!" size="16" class="text-disabled" />
          <span class="text-disabled" style="min-inline-size: 130px">{{ row.label }}</span>
          <span class="font-weight-medium">{{ row.value }}</span>
        </div>
      </div>

      <template v-if="details.representativeName">
        <VDivider class="my-3" />
        <p class="text-subtitle-2 text-medium-emphasis mb-1">Representative</p>
        <p class="text-body-2 mb-0">
          {{ details.representativeName }}
          <span v-if="details.representativeTitle" class="text-medium-emphasis">— {{ details.representativeTitle }}</span>
        </p>
      </template>
    </VCardText>

    <VCardText v-else class="text-center py-8">
      <VIcon icon="tabler-file-invoice" size="40" class="text-disabled mb-2" />
      <p class="text-body-2 text-medium-emphasis mb-0">
        Select a template and counterparty to see their details here.
      </p>
    </VCardText>
  </VCard>
</template>
