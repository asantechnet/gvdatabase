<script setup lang="ts">
import type { ContractPrintHeader } from '@/composables/useContractPrintHeader'

/**
 * Print-only contract details header.
 *
 * Rendered at the top of the first contract page when the user opts in from the
 * print dialog. It carries the contract title + date separately, then one card
 * per counterparty section (employee / applicant).
 */
defineProps<{ header: ContractPrintHeader }>()
</script>

<template>
  <section class="cdh">
    <div class="cdh__top">
      <div class="cdh__title-block">
        <span class="cdh__eyebrow">{{ $t('pages.contracts.shared.header.contractDetails') }}</span>
        <h1 class="cdh__title">
          {{ header.title }}
        </h1>
      </div>
      <div
        v-if="header.date || header.endDate"
        class="cdh__dates"
      >
        <div
          v-if="header.date"
          class="cdh__date"
        >
          <span class="cdh__date-label">{{ $t('pages.contracts.shared.header.contractDate') }}</span>
          <span class="cdh__date-value">{{ header.date }}</span>
        </div>
        <div
          v-if="header.endDate"
          class="cdh__date"
        >
          <span class="cdh__date-label">{{ $t('pages.contracts.shared.header.endDate') }}</span>
          <span class="cdh__date-value">{{ header.endDate }}</span>
        </div>
      </div>
    </div>

    <div
      v-if="header.meta.length"
      class="cdh__meta"
    >
      <div
        v-for="item in header.meta"
        :key="item.label"
        class="cdh__meta-item"
      >
        <span class="cdh__meta-label">{{ item.label }}</span>
        <span class="cdh__meta-value">{{ item.value }}</span>
      </div>
    </div>

    <div
      v-if="header.sections.length"
      class="cdh__sections"
      :class="{ 'cdh__sections--single': header.sections.length === 1 }"
    >
      <div
        v-for="section in header.sections"
        :key="section.title"
        class="cdh__card"
      >
        <div class="cdh__card-head">
          <span class="cdh__card-bar" />
          <h2 class="cdh__card-title">{{ section.title }}</h2>
        </div>
        <dl class="cdh__rows">
          <div
            v-for="row in section.rows"
            :key="`${section.title}-${row.label}`"
            class="cdh__row"
          >
            <dt class="cdh__row-label">{{ row.label }}</dt>
            <dd class="cdh__row-value">{{ row.value }}</dd>
          </div>
        </dl>
      </div>
    </div>
  </section>
</template>

<style scoped>
.cdh {
  --cdh-accent: var(--brand-accent, #1e293b);
  break-inside: avoid;
  margin-block-end: 18px;
  padding-block-end: 14px;
  border-block-end: 2px solid #e2e8f0;
  color: #1f2937;
}

.cdh__top {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.cdh__eyebrow {
  display: block;
  font-size: 0.62rem;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--cdh-accent);
  opacity: 0.78;
  margin-block-end: 3px;
}

.cdh__title {
  margin: 0;
  font-size: 1.5rem;
  line-height: 1.15;
  font-weight: 800;
  letter-spacing: 0.01em;
  color: var(--cdh-accent);
}

.cdh__dates {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}

.cdh__date {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
  padding: 6px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #f8fafc;
  white-space: nowrap;
}

.cdh__date-label {
  font-size: 0.6rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #64748b;
}

.cdh__date-value {
  font-size: 0.9rem;
  font-weight: 700;
  color: #0f172a;
}

.cdh__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-block-start: 12px;
}

.cdh__meta-item {
  display: inline-flex;
  align-items: baseline;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(30, 41, 59, 0.06);
  font-size: 0.72rem;
}

.cdh__meta-label {
  color: #64748b;
  font-weight: 600;
}

.cdh__meta-value {
  color: #0f172a;
  font-weight: 700;
}

.cdh__sections {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-block-start: 14px;
}

.cdh__sections--single {
  grid-template-columns: minmax(0, 1fr);
}

.cdh__card {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #ffffff;
  overflow: hidden;
  break-inside: avoid;
}

.cdh__card-head {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: linear-gradient(90deg, rgba(30, 41, 59, 0.08) 0%, rgba(30, 41, 59, 0.015) 100%);
  border-block-end: 1px solid #e2e8f0;
}

.cdh__card-bar {
  inline-size: 4px;
  block-size: 15px;
  border-radius: 2px;
  background: var(--cdh-accent);
}

.cdh__card-title {
  margin: 0;
  font-size: 0.82rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #0f172a;
}

.cdh__rows {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0;
  margin: 0;
  padding: 6px 12px 10px;
}

.cdh__sections--single .cdh__rows {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.cdh__row {
  display: flex;
  flex-direction: column;
  gap: 1px;
  padding: 5px 8px 5px 0;
  min-inline-size: 0;
}

.cdh__row-label {
  font-size: 0.64rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #64748b;
}

.cdh__row-value {
  margin: 0;
  font-size: 0.82rem;
  font-weight: 600;
  color: #0f172a;
  overflow-wrap: anywhere;
}

[dir='rtl'] .cdh__row {
  padding: 5px 0 5px 8px;
}

[dir='rtl'] .cdh__dates {
  justify-content: flex-start;
}

[dir='rtl'] .cdh__date {
  align-items: flex-start;
}

@media (max-width: 640px) {
  .cdh__sections {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media print {
  .cdh,
  .cdh__card {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
</style>
