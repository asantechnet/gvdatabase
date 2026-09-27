<script setup lang="ts">
import type { Invoice } from '@/api/invoice'

const props = defineProps<{ invoice: Invoice }>()

const amount = (value: number) => {
  const decimals = props.invoice.currency?.decimalPlaces ?? 2
  const formatted = formatLocalizedNumber(Number(value || 0), {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  })

  return `${props.invoice.currency?.symbol ?? ''}${formatted} ${props.invoice.currency?.shortname ?? ''}`.trim()
}

const customerContact = computed(() => props.invoice.applicant?.email || props.invoice.applicant?.contact || '')
const customerCompany = computed(() => '')
const visibleServices = computed(() => props.invoice.services.slice(0, 3))
const remainingServiceCount = computed(() => Math.max(props.invoice.services.length - visibleServices.value.length, 0))
</script>

<template>
  <VCard
    variant="tonal"
    color="primary"
    class="invoice-payment-context mt-3"
  >
    <VCardText class="pa-4">
      <div class="d-flex flex-wrap align-start justify-space-between gap-3 mb-4">
        <div class="d-flex align-start gap-3">
          <VAvatar
            color="primary"
            variant="tonal"
            rounded="lg"
          >
            <VIcon icon="tabler-file-invoice" />
          </VAvatar>
          <div>
            <div class="text-caption text-medium-emphasis">
              {{ $t('pages.finance.income.income.invoicePaymentContext') }}
            </div>
            <div class="text-subtitle-1 font-weight-bold">
              {{ invoice.invoice_number }}
            </div>
            <div class="text-body-2 text-medium-emphasis">
              {{ invoice.applicant?.name || invoice.deptor || $t('common.messages.notAvailable') }}
              <span v-if="customerCompany"> · {{ customerCompany }}</span>
            </div>
          </div>
        </div>
        <VChip
          size="small"
          :color="invoice.status === 'Paid' ? 'success' : invoice.status === 'Overdue' ? 'error' : 'warning'"
        >
          {{ translateBackendValue(invoice.status) }}
        </VChip>
      </div>

      <div class="invoice-payment-context__totals">
        <div>
          <span>{{ $t('pages.finance.income.income.invoiceTotal') }}</span>
          <strong>{{ amount(invoice.total_amount) }}</strong>
        </div>
        <div>
          <span>{{ $t('common.messages.alreadyPaid') }}</span>
          <strong class="text-success">{{ amount(invoice.paid_amount) }}</strong>
        </div>
        <div>
          <span>{{ $t('common.messages.remainingBalance') }}</span>
          <strong class="text-primary">{{ amount(invoice.balance) }}</strong>
        </div>
      </div>

      <VDivider class="my-4" />

      <VRow density="compact">
        <VCol
          v-if="customerContact"
          cols="12"
          sm="6"
        >
          <div class="invoice-payment-context__detail">
            <VIcon icon="tabler-address-book" />
            <span>{{ customerContact }}</span>
          </div>
        </VCol>
        <VCol
          v-if="invoice.issue_date"
          cols="12"
          sm="6"
        >
          <div class="invoice-payment-context__detail">
            <VIcon icon="tabler-calendar-event" />
            <span>{{ $t('pages.finance.income.income.issuedOn', { date: formatLocalizedDate(invoice.issue_date, 'short') }) }}</span>
          </div>
        </VCol>
        <VCol
          v-if="invoice.due_date"
          cols="12"
          sm="6"
        >
          <div class="invoice-payment-context__detail">
            <VIcon icon="tabler-calendar-due" />
            <span>{{ $t('pages.finance.income.income.dueOn', { date: formatLocalizedDate(invoice.due_date, 'short') }) }}</span>
          </div>
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <div class="invoice-payment-context__detail">
            <VIcon icon="tabler-coin" />
            <span>{{ invoice.currency?.name || invoice.currency?.shortname }}</span>
          </div>
        </VCol>
      </VRow>

      <div
        v-if="visibleServices.length"
        class="mt-4"
      >
        <div class="text-caption text-medium-emphasis mb-2">
          {{ $t('pages.finance.income.income.includedServices') }}
        </div>
        <div class="d-flex flex-wrap gap-2">
          <VChip
            v-for="service in visibleServices"
            :key="service.id"
            size="small"
            variant="outlined"
          >
            {{ service.name }} ×{{ service.quantity }}
          </VChip>
          <VChip
            v-if="remainingServiceCount"
            size="small"
            variant="outlined"
          >
            {{ $t('pages.finance.income.income.additionalServices', { count: remainingServiceCount }) }}
          </VChip>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.invoice-payment-context__totals {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.75rem;
}

.invoice-payment-context__totals > div {
  display: grid;
  gap: 0.2rem;
  padding: 0.75rem;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 0.5rem;
  background: rgba(var(--v-theme-surface), 0.5);
}

.invoice-payment-context__totals span,
.invoice-payment-context__detail {
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}

.invoice-payment-context__detail {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

@media (max-width: 600px) {
  .invoice-payment-context__totals {
    grid-template-columns: 1fr;
  }
}
</style>
