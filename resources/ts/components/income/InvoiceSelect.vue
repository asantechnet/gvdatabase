<script setup lang="ts">
import type { Invoice } from '@/api/invoice'

const props = defineProps<{
  modelValue: number | null
  invoices: Invoice[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number | null]
  change: [value: number | null]
}>()

const formatAmount = (invoice: Invoice, value: number) => {
  const decimals = invoice.currency?.decimalPlaces ?? 2
  const amount = formatLocalizedNumber(Number(value || 0), {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  })

  return `${invoice.currency?.symbol ?? ''}${amount} ${invoice.currency?.shortname ?? ''}`.trim()
}

const customerName = (invoice: Invoice) => invoice.applicant?.name || invoice.deptor || ''
const companyName = () => ''
const update = (value: number | null) => {
  emit('update:modelValue', value)
  emit('change', value)
}
</script>

<template>
  <VSelect
    :model-value="modelValue"
    :items="invoices"
    item-title="invoice_number"
    item-value="id"
    :label="$t('common.messages.associatedInvoiceOptional')"
    prepend-inner-icon="tabler-file-invoice"
    clearable
    @update:model-value="update"
  >
    <template #selection="{ item }">
      <div class="invoice-select__selection">
        <strong>{{ item.raw.invoice_number }}</strong>
        <span>{{ customerName(item.raw) }}</span>
        <span class="invoice-select__selection-balance">
          {{ $t('common.messages.remainingBalance') }}: {{ formatAmount(item.raw, item.raw.balance) }}
        </span>
      </div>
    </template>

    <template #item="{ props: itemProps, item }">
      <VListItem v-bind="itemProps">
        <template #title>
          <div class="d-flex align-center justify-space-between gap-3">
            <strong>{{ item.raw.invoice_number }}</strong>
            <VChip
              size="x-small"
              :color="item.raw.status === 'Paid' ? 'success' : item.raw.status === 'Overdue' ? 'error' : 'warning'"
            >
              {{ translateBackendValue(item.raw.status) }}
            </VChip>
          </div>
        </template>
        <template #subtitle>
          <div class="invoice-select__customer">
            {{ customerName(item.raw) }}<span v-if="companyName(item.raw)"> · {{ companyName(item.raw) }}</span>
          </div>
          <div class="invoice-select__amounts">
            <span>{{ $t('pages.finance.income.income.invoiceTotal') }}: {{ formatAmount(item.raw, item.raw.total_amount) }}</span>
            <span>{{ $t('common.messages.remainingBalance') }}: {{ formatAmount(item.raw, item.raw.balance) }}</span>
          </div>
        </template>
      </VListItem>
    </template>
  </VSelect>
</template>

<style scoped>
.invoice-select__selection {
  display: flex;
  min-inline-size: 0;
  align-items: center;
  gap: 0.5rem;
}

.invoice-select__selection span {
  overflow: hidden;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.8125rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.invoice-select__selection-balance {
  margin-inline-start: auto;
}

.invoice-select__customer,
.invoice-select__amounts {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.invoice-select__amounts {
  display: flex;
  gap: 0.75rem;
  margin-block-start: 0.2rem;
  font-size: 0.75rem;
}

@media (max-width: 600px) {
  .invoice-select__selection-balance {
    display: none;
  }

  .invoice-select__amounts {
    flex-direction: column;
    gap: 0.1rem;
  }
}
</style>
