<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

type DetailRecord = Record<string, unknown>

interface DetailField {
  key: string
  label?: string
}

const props = withDefaults(defineProps<{
  item: object
  title?: string
  subtitle?: string
  fields?: DetailField[]
  exclude?: string[]
  mobileAction?: boolean
}>(), {
  title: '',
  subtitle: '',
  fields: () => [],
  exclude: () => [],
  mobileAction: false,
})

const isOpen = ref(false)
const { t } = useI18n()
const hiddenKeys = new Set(['password', 'password_confirmation', 'remember_token', 'token', 'pivot'])
const record = computed(() => props.item as DetailRecord)

const humanize = (key: string) => key
  .replaceAll('.', ' ')
  .replaceAll('_', ' ')
  .replace(/([a-z])([A-Z])/g, '$1 $2')
  .replace(/^./, letter => letter.toUpperCase())

const getValue = (record: DetailRecord, path: string) => path
  .split('.')
  .reduce<unknown>((value, key) => value && typeof value === 'object' ? (value as DetailRecord)[key] : undefined, record)

const detailFields = computed(() => {
  const fields: DetailField[] = props.fields.length
    ? props.fields
    : Object.keys(record.value).map(key => ({ key }))

  return fields
    .filter(({ key }) => !hiddenKeys.has(key) && !props.exclude.includes(key))
    .map(field => ({
      ...field,
      label: field.label || humanize(field.key),
      value: getValue(record.value, field.key),
    }))
})

const isMetadataField = (key: string) => /(^id$|_id$|uuid|created|updated|deleted|timestamp)/i.test(key)
const primaryFields = computed(() => detailFields.value.filter(field => !isMetadataField(field.key)))
const metadataFields = computed(() => detailFields.value.filter(field => isMetadataField(field.key)))

const recordTitle = computed(() => props.title
  || String(record.value.name || record.value.title || record.value.invoice_number || record.value.contract_number || `#${record.value.id ?? ''}`))

const isDateValue = (value: unknown) => typeof value === 'string'
  && /^\d{4}-\d{2}-\d{2}(?:[T\s].*)?$/.test(value)

const formatScalar = (value: unknown) => {
  if (value === null || value === undefined || value === '') return '—'
  if (typeof value === 'boolean') return value ? t('common.values.yes') : t('common.values.no')
  if (isDateValue(value)) {
    const date = new Date(String(value))
    if (!Number.isNaN(date.getTime()))
      return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(date)
  }
  return String(value)
}

const summarizeObject = (value: DetailRecord) => String(
  value.name || value.title || value.code || value.shortname || value.type || value.email || value.id || '—',
)

const formatValue = (value: unknown) => {
  if (Array.isArray(value)) return value.length
    ? value.map(entry => typeof entry === 'object' && entry !== null ? summarizeObject(entry as DetailRecord) : formatScalar(entry)).join(', ')
    : '—'
  if (typeof value === 'object' && value !== null) return summarizeObject(value as DetailRecord)
  return formatScalar(value)
}

const iconFor = (key: string) => {
  if (/^id$|_id$|uuid/i.test(key)) return 'tabler-hash'
  if (/name|title|employee|customer|client|staff/i.test(key)) return 'tabler-user'
  if (/mail/i.test(key)) return 'tabler-mail'
  if (/phone|contact/i.test(key)) return 'tabler-phone'
  if (/date|time|created|updated|deadline/i.test(key)) return 'tabler-calendar'
  if (/amount|fee|salary|price|total|balance|rate/i.test(key)) return 'tabler-cash'
  if (/address|location|office/i.test(key)) return 'tabler-map-pin'
  if (/status/i.test(key)) return 'tabler-status-change'
  if (/note|description|reason|detail/i.test(key)) return 'tabler-notes'
  if (/type|category|role|position/i.test(key)) return 'tabler-category'
  if (/quantity|count|number|serial/i.test(key)) return 'tabler-list-numbers'
  return 'tabler-info-circle'
}

const sections = computed(() => [
  { key: 'primary', title: t('common.messages.recordInformation'), icon: 'tabler-align-box-left-middle', fields: primaryFields.value },
  { key: 'metadata', title: t('common.messages.systemInformation'), icon: 'tabler-database', fields: metadataFields.value },
].filter(section => section.fields.length))

const openDetails = () => {
  isOpen.value = true
}
</script>

<template>
  <span class="full-details-action">
    <VBtn v-if="mobileAction" block class="responsive-data-table__action-button" color="info" variant="text" prepend-icon="tabler-eye" @click.stop="openDetails">
      {{ t('common.actions.viewFullDetails') }}
    </VBtn>
    <IconBtn v-else color="info" :aria-label="t('common.actions.viewFullDetails')" @click.stop="openDetails">
      <VIcon icon="tabler-eye" />
      <VTooltip activator="parent">{{ t('common.actions.viewFullDetails') }}</VTooltip>
    </IconBtn>

    <VDialog v-model="isOpen" max-width="760">
      <VCard class="record-details-dialog">
        <header class="record-details-dialog__header">
          <div class="record-details-dialog__identity">
            <div class="record-details-dialog__mark"><VIcon icon="tabler-file-description" size="20" /></div>
            <div class="overflow-hidden">
              <div class="record-details-dialog__eyebrow">
                <span>{{ t('common.messages.fullRecord') }}</span>
                <span class="record-details-dialog__count">{{ detailFields.length }}</span>
              </div>
              <h2 class="record-details-dialog__title">{{ recordTitle }}</h2>
            </div>
          </div>
          <IconBtn class="record-details-dialog__close" :aria-label="t('common.actions.close')" @click="isOpen = false">
            <VIcon icon="tabler-x" />
          </IconBtn>
        </header>

        <main class="record-details-dialog__body">
          <p v-if="subtitle" class="record-details-dialog__summary">{{ subtitle }}</p>

          <section v-for="section in sections" :key="section.key" class="record-details-section">
            <div class="record-details-section__heading">
              <VIcon :icon="section.icon" size="17" />
              <h3>{{ section.title }}</h3>
              <span>{{ section.fields.length }}</span>
            </div>

            <div class="record-details-section__grid">
              <div v-for="field in section.fields" :key="field.key" class="record-detail-field">
                <div class="record-detail-field__icon"><VIcon :icon="iconFor(field.key)" size="18" /></div>
                <div class="record-detail-field__content">
                  <div class="record-detail-field__label">
                    <span class="record-detail-field__bullet" />
                    <span>{{ field.label }}</span>
                  </div>
                  <div class="record-detail-field__value" :class="{ 'record-detail-field__value--empty': formatValue(field.value) === '—' }">
                    <template v-if="Array.isArray(field.value) && field.value.length">
                      <VChip v-for="(entry, index) in field.value" :key="index" size="small" variant="tonal" color="secondary">
                        {{ typeof entry === 'object' && entry !== null ? summarizeObject(entry as DetailRecord) : formatScalar(entry) }}
                      </VChip>
                    </template>
                    <template v-else>{{ formatValue(field.value) }}</template>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </main>
      </VCard>
    </VDialog>
  </span>
</template>

<style scoped lang="scss">
.record-details-dialog {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  max-block-size: min(88dvh, 760px);
  border: 0;
  border-radius: 0.875rem !important;
  box-shadow: 0 18px 60px rgba(20, 18, 50, 0.2) !important;
  overflow: hidden;
}

.record-details-dialog__header {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-block-size: 72px;
  padding: 0.875rem 1.125rem;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 1px rgba(var(--v-border-color), 0.16);
}

.record-details-dialog__identity {
  display: flex;
  min-inline-size: 0;
  align-items: center;
  gap: 0.75rem;
}

.record-details-dialog__mark {
  display: grid;
  flex: 0 0 38px;
  block-size: 38px;
  border-radius: 0.65rem;
  background: rgba(var(--v-theme-primary), 0.12);
  color: rgb(var(--v-theme-primary));
  place-items: center;
}

.record-details-dialog__eyebrow {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.065em;
  line-height: 1.2;
  text-transform: uppercase;
}

.record-details-dialog__count {
  display: inline-grid;
  min-inline-size: 1.25rem;
  block-size: 1.25rem;
  border-radius: 99px;
  background: rgba(var(--v-theme-primary), 0.1);
  color: rgb(var(--v-theme-primary));
  font-size: 0.65rem;
  place-items: center;
}

.record-details-dialog__title {
  margin: 0.2rem 0 0;
  overflow: hidden;
  color: rgb(var(--v-theme-on-surface));
  font-size: 1.0625rem;
  font-weight: 650;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.record-details-dialog__close {
  flex: 0 0 auto;
  margin-inline-start: 0.75rem;
}

.record-details-dialog__body {
  min-block-size: 0;
  padding: 1rem 1.125rem 1.25rem;
  background: rgb(var(--v-theme-surface));
  overflow-y: auto;
  overscroll-behavior: contain;
  scrollbar-gutter: stable;
}

.record-details-dialog__summary {
  margin: 0 0 1rem;
  padding: 0.7rem 0.85rem;
  border-radius: 0.625rem;
  background: rgba(var(--v-theme-primary), 0.06);
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.8125rem;
}

.record-details-section + .record-details-section {
  margin-block-start: 1.25rem;
}

.record-details-section__heading {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  margin-block-end: 0.55rem;
  color: rgb(var(--v-theme-primary));
}

.record-details-section__heading h3 {
  margin: 0;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.8125rem;
  font-weight: 700;
}

.record-details-section__heading span {
  color: rgba(var(--v-theme-on-surface), var(--v-disabled-opacity));
  font-size: 0.6875rem;
  font-weight: 600;
}

.record-details-section__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  column-gap: 1.5rem;
  row-gap: 0.125rem;
}

.record-detail-field {
  display: grid;
  min-inline-size: 0;
  grid-template-columns: 34px minmax(0, 1fr);
  align-items: start;
  gap: 0.7rem;
  padding: 0.65rem 0.25rem;
  border: 0;
  background: transparent;
}

.record-detail-field__icon {
  display: grid;
  block-size: 32px;
  border-radius: 0.55rem;
  background: rgba(var(--v-theme-primary), 0.075);
  color: rgb(var(--v-theme-primary));
  place-items: center;
}

.record-detail-field__content {
  min-inline-size: 0;
}

.record-detail-field__label {
  display: flex;
  align-items: center;
  gap: 0.42rem;
  margin-block: 0 0.18rem;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.7rem;
  font-weight: 650;
  letter-spacing: 0.015em;
}

.record-detail-field__bullet {
  flex: 0 0 4px;
  block-size: 4px;
  border-radius: 50%;
  background: rgb(var(--v-theme-primary));
}

.record-detail-field__value {
  display: flex;
  min-block-size: 1.4rem;
  flex-wrap: wrap;
  gap: 0.3rem;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.875rem;
  font-weight: 500;
  line-height: 1.5;
  overflow-wrap: anywhere;
  white-space: normal;
}

.record-detail-field__value--empty {
  color: rgba(var(--v-theme-on-surface), var(--v-disabled-opacity));
  font-weight: 400;
}

@media (max-width: 599px) {
  .record-details-dialog {
    max-block-size: calc(100dvh - 1.5rem);
    border-radius: 0.75rem !important;
  }

  .record-details-dialog__header {
    min-block-size: 64px;
    padding: 0.75rem 0.875rem;
  }

  .record-details-dialog__body {
    padding: 0.875rem;
  }

  .record-details-section__grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
