<script setup lang="ts">
import { translate } from '@/utils/localization'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

type DetailRecord = Record<string, unknown>

interface DetailField {
  key: string
  label?: string
}

interface DisplayField extends DetailField {
  value: unknown
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
const activeTab = ref('overview')
const { t, locale } = useI18n()
const record = computed(() => props.item as DetailRecord)

const detailsText = (key: string, fallback: string, values?: Record<string, unknown>) => {
  const value = t(`components.fullDetails.${key}`, values ?? {})

  return value === `components.fullDetails.${key}` || value.startsWith('[missing:') ? fallback : value
}

const hiddenKeys = new Set([
  'password',
  'password_confirmation',
  'remember_token',
  'token',
  'access_token',
  'refresh_token',
  'pivot',
  'laravel_through_key',
  'deleted_at',
  'avatar',
  'employeeAvatar',
])

const normalizedKey = (key: string) => key.replace(/[^a-z0-9]/gi, '').toLowerCase()
const isObject = (value: unknown): value is DetailRecord => value !== null && typeof value === 'object' && !Array.isArray(value)
const isEmpty = (value: unknown) => value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0)

const humanize = (key: string) => key
  .replaceAll('.', ' ')
  .replaceAll('_', ' ')
  .replaceAll('-', ' ')
  .replace(/([a-z])([A-Z])/g, '$1 $2')
  .replace(/\b\w/g, letter => letter.toUpperCase())
  .replace(/\bId\b/g, 'ID')

const friendlyLabels: Record<string, string> = {
  'addr': 'Address',
  'cnv_curr_shrt': 'Transaction currency',
  'base_curr_shrt': 'Base currency',
  'currentBaseAmount': 'Current base value',
  'currentBasePaidAmount': 'Paid in current base',
  'currentBaseBalance': 'Balance in current base',
  'deptor': 'Billed to',
  'recordedBy': 'Recorded by',
  'recorded_by': 'Recorded by',
  'salaryMonth': 'Payroll month',
  'off_day_days': 'Off-day days',
  'absent_days': 'Absent days',
}

const labelFor = (key: string) => {
  const fallback = friendlyLabels[key] || humanize(key)
  const alias = detailsText(`fieldAliases.${normalizedKey(key)}`, fallback)

  return detailsText(`fields.${normalizedKey(key)}`, alias)
}

const getValue = (source: DetailRecord, path: string) => path
  .split('.')
  .reduce<unknown>((value, key) => isObject(value) ? value[key] : undefined, source)

const relationKeys = computed(() => new Set(
  Object.entries(record.value)
    .filter(([, value]) => isObject(value) || Array.isArray(value))
    .map(([key]) => normalizedKey(key)),
))

const isForeignKey = (key: string) => {
  if (/^id$/i.test(key))
    return true
  const match = key.match(/^(.*?)(?:_id|Id)$/)
  if (!match)
    return false

  const stem = normalizedKey(match[1])

  const aliases: Record<string, string[]> = {
    currency: ['currency', 'currentbasecurrency'],
    recordedby: ['recordedby', 'recorder'],
    user: ['user', 'account'],
    template: ['template', 'contracttemplate'],
  }

  return relationKeys.value.has(stem)
    || (aliases[stem] || []).some(alias => relationKeys.value.has(alias))
    || /^(customer|employee|staff|office|position|service|currency|contract|template|user|role|permission|board|project|expense)$/i.test(stem)
}

const shouldHide = (key: string) => hiddenKeys.has(key)
  || props.exclude.includes(key)
  || isForeignKey(key)
  || key.startsWith('_')

const requestedFields = computed<DisplayField[]>(() => {
  const fields: DetailField[] = props.fields.length
    ? props.fields
    : Object.keys(record.value).map(key => ({ key }))

  return fields
    .filter(({ key }) => !shouldHide(key))
    // Pages may provide a polished label (for example "Available Quantity").
    // Treat it as a translation key candidate too, rather than letting that
    // English convenience label bypass the shared viewer localization.
    .map(field => ({ ...field, label: labelFor(field.label || field.key), value: getValue(record.value, field.key) }))
})

const isSystemField = (key: string) => /(^uuid$|created|updated|timestamp|cancelled_at|voided_at)/i.test(key)

const isNarrativeField = (field: DisplayField) => /note|description|reason|remark|address|content|cover_text|instruction|detail|formula/i.test(field.key)
  || (typeof field.value === 'string' && field.value.length > 100)

const isMetricField = (field: DisplayField) => typeof field.value === 'number'
  && /amount|fee|salary|price|total|balance|rate|quantity|count|days|hours|score|percent|earned|discount|tax/i.test(field.key)

const scalarFields = computed(() => requestedFields.value.filter(field => !isObject(field.value) && !Array.isArray(field.value)))
const systemFields = computed(() => scalarFields.value.filter(field => isSystemField(field.key)))
const narrativeFields = computed(() => scalarFields.value.filter(field => !isSystemField(field.key) && isNarrativeField(field)))
const metricFields = computed(() => scalarFields.value.filter(field => !isSystemField(field.key) && !isNarrativeField(field) && isMetricField(field)))
const factFields = computed(() => scalarFields.value.filter(field => !isSystemField(field.key) && !isNarrativeField(field) && !isMetricField(field)))
const relatedRecords = computed(() => requestedFields.value.filter(field => isObject(field.value)))
const collections = computed(() => requestedFields.value.filter(field => Array.isArray(field.value) && field.value.length))

const meaningfulFieldCount = computed(() => requestedFields.value.filter(field => !isEmpty(field.value)).length)

const recordTitle = computed(() => props.title || String(
  record.value.name || record.value.title || record.value.legal_name
  || record.value.receipt_number || record.value.payslip_number || record.value.contract_number
  || record.value.item_name || record.value.item || detailsText('recordFallback', `Record ${record.value.id ? `#${record.value.id}` : ''}`, { id: record.value.id ? `#${record.value.id}` : '' }),
))

const recordKind = computed(() => {
  if (record.value.receipt_number)
    return detailsText('recordKinds.receipt', 'Receipt')
  if (record.value.payslip_number || record.value.salaryMonth)
    return detailsText('recordKinds.payroll', 'Payroll record')
  if (record.value.contract_number)
    return detailsText('recordKinds.contract', 'Contract')
  if (record.value.serial_number)
    return detailsText('recordKinds.asset', 'Asset item')
  if (record.value.contact && record.value.service)
    return detailsText('recordKinds.visitor', 'Visitor')
  if (record.value.position || record.value.father_name)
    return detailsText('recordKinds.employee', 'Employee')
  if (record.value.services)
    return detailsText('recordKinds.customer', 'Customer')

  return detailsText('recordKinds.record', 'Record details')
})

const recordStatus = computed(() => {
  const statusEntry = Object.entries(record.value).find(([key, value]) => /^(status|condition|state|is_active)$/i.test(key) && !isEmpty(value))
  if (!statusEntry)
    return null
  if (statusEntry[0] === 'is_active')
    return record.value.is_active ? detailsText('values.active', translate('serviceContracts.active')) : detailsText('values.inactive', translate('common.statuses.inactive'))

  return String(statusEntry[1])
})

const isDateValue = (value: unknown) => typeof value === 'string' && /^\d{4}-\d{2}-\d{2}(?:[T\s].*)?$/.test(value)

const currencyContext = (source: DetailRecord = record.value) => {
  const currency = (isObject(source.currency) ? source.currency : isObject(record.value.currency) ? record.value.currency : null)

  return currency ? String(currency.symbol || currency.code || currency.shortname || '') : String(source.cnv_curr_shrt || record.value.cnv_curr_shrt || '')
}

const formatScalar = (value: unknown, key = '', source: DetailRecord = record.value) => {
  if (isEmpty(value))
    return '—'
  if (typeof value === 'boolean')
    return value ? t('common.values.yes') : t('common.values.no')
  if (isDateValue(value)) {
    const date = new Date(String(value))
    if (!Number.isNaN(date.getTime())) {
      const includesTime = /[T\s]\d{2}:\d{2}/.test(String(value))

      return new Intl.DateTimeFormat(locale.value, includesTime
        ? { dateStyle: 'medium', timeStyle: 'short' }
        : { dateStyle: 'medium' }).format(date)
    }
  }
  if (typeof value === 'number') {
    if (/percent|tax/i.test(key))
      return `${new Intl.NumberFormat(locale.value, { maximumFractionDigits: 2 }).format(value)}%`
    const formatted = new Intl.NumberFormat(locale.value, { maximumFractionDigits: 2 }).format(value)

    return /amount|fee|salary|price|total|balance|paid|bonus|deduction|discount|earned/i.test(key)
      ? [currencyContext(source), formatted].filter(Boolean).join(' ')
      : formatted
  }

  const raw = String(value).replaceAll('_', ' ')

  return /status|condition|state|is_active/i.test(key)
    ? detailsText(`values.${normalizedKey(raw)}`, raw)
    : raw
}

const objectTitle = (value: DetailRecord, fallback: string) => String(
  value.name || value.title || value.legal_name || value.type || value.code || value.email || fallback,
)

const objectFields = (value: DetailRecord) => Object.entries(value)
  .filter(([key, entry]) => !shouldHide(key) && !isObject(entry) && !Array.isArray(entry) && !isSystemField(key))
  .map(([key, entry]) => ({ key, label: labelFor(key), value: entry }))

const nestedObjects = (value: DetailRecord) => Object.entries(value)
  .filter(([key, entry]) => !shouldHide(key) && isObject(entry))
  .map(([key, entry]) => ({ key, label: labelFor(key), value: entry as DetailRecord }))

const statusColor = (status: unknown) => {
  const value = String(status || '').toLowerCase()
  if (/active|paid|complete|approved|granted|good|new|issued/.test(value))
    return 'success'
  if (/pending|draft|partial|fair|paused|progress/.test(value))
    return 'warning'
  if (/overdue|cancel|void|expired|reject|damaged|poor|inactive|terminated/.test(value))
    return 'error'

  return 'info'
}

const iconFor = (key: string) => {
  if (/name|title|employee|customer|client|staff|representative/i.test(key))
    return 'tabler-user'
  if (/mail/i.test(key))
    return 'tabler-mail'
  if (/phone|contact/i.test(key))
    return 'tabler-phone'
  if (/date|time|deadline|month|duration/i.test(key))
    return 'tabler-calendar'
  if (/amount|fee|salary|price|total|balance|rate|bonus|tax/i.test(key))
    return 'tabler-cash'
  if (/address|location|office|city|country|province/i.test(key))
    return 'tabler-map-pin'
  if (/status|condition|state/i.test(key))
    return 'tabler-activity'
  if (/note|description|reason|detail|remark|content/i.test(key))
    return 'tabler-notes'
  if (/type|category|role|position|industry/i.test(key))
    return 'tabler-category'
  if (/quantity|count|number|serial|days|hours/i.test(key))
    return 'tabler-list-numbers'
  if (/file|document|licence/i.test(key))
    return 'tabler-paperclip'

  return 'tabler-info-circle'
}

const hrefFor = (value: unknown, key: string) => {
  if (isEmpty(value))
    return null
  const text = String(value)
  if (/mail/i.test(key))
    return `mailto:${text}`
  if (/phone|contact/i.test(key))
    return `tel:${text.replace(/\s/g, '')}`
  if (/website|url/i.test(key) && /^https?:\/\//i.test(text))
    return text
  if (/file|document|licence/i.test(key) && (/^https?:\/\//i.test(text) || text.startsWith('/')))
    return text

  return null
}

const tabs = computed(() => [
  { value: 'overview', label: detailsText('tabs.overview', 'Overview'), icon: 'tabler-layout-dashboard' },
  ...(relatedRecords.value.length ? [{ value: 'related', label: detailsText('tabs.related', 'Related'), icon: 'tabler-users-group', count: relatedRecords.value.length }] : []),
  ...collections.value.map(field => ({ value: `collection-${field.key}`, label: labelFor(field.label || field.key), icon: 'tabler-list-details', count: (field.value as unknown[]).length })),
  ...(systemFields.value.length ? [{ value: 'activity', label: detailsText('tabs.activity', 'Activity'), icon: 'tabler-history', count: systemFields.value.length }] : []),
])

watch(isOpen, open => {
  if (open)
    activeTab.value = 'overview'
})
</script>

<template>
  <span class="full-details-action">
    <VBtn
      v-if="mobileAction"
      block
      class="responsive-data-table__action-button"
      color="info"
      variant="text"
      prepend-icon="tabler-eye"
      @click.stop="isOpen = true"
    >
      {{ t('common.actions.viewFullDetails') }}
    </VBtn>
    <IconBtn
      v-else
      color="info"
      :aria-label="t('common.actions.viewFullDetails')"
      @click.stop="isOpen = true"
    >
      <VIcon icon="tabler-eye" />
      <VTooltip activator="parent">{{ t('common.actions.viewFullDetails') }}</VTooltip>
    </IconBtn>

    <VDialog
      v-model="isOpen"
      max-width="1080"
      scrollable
    >
      <VCard class="detail-viewer">
        <header class="detail-viewer__hero">
          <div class="detail-viewer__hero-icon"><VIcon
            :icon="iconFor(recordKind)"
            size="26"
          /></div>
          <div class="detail-viewer__identity">
            <div class="detail-viewer__eyebrow">{{ detailsText('eyebrow', `${recordKind} · ${meaningfulFieldCount} useful details`, { kind: recordKind, count: meaningfulFieldCount }) }}</div>
            <h2>{{ recordTitle }}</h2>
            <p v-if="subtitle">{{ subtitle }}</p>
          </div>
          <VChip
            v-if="recordStatus"
            class="detail-viewer__status"
            :color="statusColor(recordStatus)"
            variant="flat"
            size="small"
          >
            {{ formatScalar(recordStatus, 'status') }}
          </VChip>
          <IconBtn
            class="detail-viewer__close"
            :aria-label="t('common.actions.close')"
            @click="isOpen = false"
          ><VIcon icon="tabler-x" /></IconBtn>
        </header>

        <VTabs
          v-model="activeTab"
          class="detail-viewer__tabs"
          color="primary"
          show-arrows
        >
          <VTab
            v-for="tab in tabs"
            :key="tab.value"
            :value="tab.value"
          >
            <VIcon
              :icon="tab.icon"
              start
              size="18"
            />{{ tab.label }}
            <VChip
              v-if="tab.count"
              size="x-small"
              class="ms-2"
              variant="tonal"
            >{{ tab.count }}</VChip>
          </VTab>
        </VTabs>

        <VCardText class="detail-viewer__body">
          <VWindow v-model="activeTab">
            <VWindowItem value="overview">
              <div
                v-if="metricFields.length"
                class="detail-metrics"
              >
                <article
                  v-for="field in metricFields"
                  :key="field.key"
                  class="detail-metric"
                >
                  <div class="detail-metric__icon"><VIcon
                    :icon="iconFor(field.key)"
                    size="20"
                  /></div>
                  <div><span>{{ field.label }}</span><strong>{{ formatScalar(field.value, field.key) }}</strong></div>
                </article>
              </div>

              <section
                v-if="factFields.length"
                class="detail-section"
              >
                <div class="detail-section__title"><VIcon
                  icon="tabler-info-square-rounded"
                  size="19"
                /><div><h3>{{ detailsText('sections.essentialTitle', 'Essential information') }}</h3><p>{{ detailsText('sections.essentialDescription', 'The details needed to identify and understand this record.') }}</p></div></div>
                <div class="detail-facts">
                  <article
                    v-for="field in factFields"
                    :key="field.key"
                    class="detail-fact"
                    :class="{ 'detail-fact--empty': isEmpty(field.value) }"
                  >
                    <VIcon
                      :icon="iconFor(field.key)"
                      size="18"
                    />
                    <div><span>{{ field.label }}</span>
                      <a
                        v-if="hrefFor(field.value, field.key)"
                        :href="hrefFor(field.value, field.key) || undefined"
                        target="_blank"
                        rel="noopener noreferrer"
                      >{{ formatScalar(field.value, field.key) }}</a>
                      <VChip
                        v-else-if="/status|condition|state|type$/i.test(field.key) && !isEmpty(field.value)"
                        :color="statusColor(field.value)"
                        size="small"
                        variant="tonal"
                      >{{ formatScalar(field.value, field.key) }}</VChip>
                      <strong v-else>{{ formatScalar(field.value, field.key) }}</strong>
                    </div>
                  </article>
                </div>
              </section>

              <section
                v-if="narrativeFields.length"
                class="detail-section"
              >
                <div class="detail-section__title"><VIcon
                  icon="tabler-align-left"
                  size="19"
                /><div><h3>{{ detailsText('sections.notesTitle', 'Notes & context') }}</h3><p>{{ detailsText('sections.notesDescription', 'Supporting information kept in full, without truncation.') }}</p></div></div>
                <div class="detail-narratives">
                  <article
                    v-for="field in narrativeFields"
                    :key="field.key"
                  ><span>{{ field.label }}</span><p>{{ formatScalar(field.value, field.key) }}</p></article>
                </div>
              </section>

              <VAlert
                v-if="!metricFields.length && !factFields.length && !narrativeFields.length"
                type="info"
                variant="tonal"
              >{{ detailsText('sections.emptyOverview', 'No user-facing overview information is available for this record.') }}</VAlert>
            </VWindowItem>

            <VWindowItem value="related">
              <section class="detail-section detail-section--first">
                <div class="detail-section__title"><VIcon
                  icon="tabler-link"
                  size="19"
                /><div><h3>{{ detailsText('sections.relatedTitle', 'Connected records') }}</h3><p>{{ detailsText('sections.relatedDescription', 'Names and useful context are shown instead of database IDs.') }}</p></div></div>
                <div class="related-grid">
                  <article
                    v-for="relation in relatedRecords"
                    :key="relation.key"
                    class="related-card"
                  >
                    <header><div class="related-card__icon"><VIcon
                      :icon="iconFor(relation.key)"
                      size="20"
                    /></div><div><span>{{ relation.label }}</span><h4>{{ objectTitle(relation.value as DetailRecord, relation.label || '') }}</h4></div></header>
                    <dl>
                      <template
                        v-for="field in objectFields(relation.value as DetailRecord)"
                        :key="field.key"
                      >
                        <dt>{{ field.label }}</dt><dd>{{ formatScalar(field.value, field.key, relation.value as DetailRecord) }}</dd>
                      </template>
                    </dl>
                    <div
                      v-for="nested in nestedObjects(relation.value as DetailRecord)"
                      :key="nested.key"
                      class="related-card__nested"
                    >
                      <strong>{{ nested.label }}</strong>
                      <span>{{ objectFields(nested.value).map(field => `${field.label}: ${formatScalar(field.value, field.key, nested.value)}`).join(' · ') || objectTitle(nested.value, nested.label) }}</span>
                    </div>
                  </article>
                </div>
              </section>
            </VWindowItem>

            <VWindowItem
              v-for="collection in collections"
              :key="collection.key"
              :value="`collection-${collection.key}`"
            >
              <section class="detail-section detail-section--first">
                <div class="detail-section__title"><VIcon
                  icon="tabler-list-details"
                  size="19"
                /><div><h3>{{ collection.label }}</h3><p>{{ detailsText('sections.collectionDescription', `${(collection.value as unknown[]).length} connected entries, shown with their useful details.`, { count: (collection.value as unknown[]).length }) }}</p></div></div>
                <div
                  v-if="(collection.value as unknown[]).some(entry => isObject(entry))"
                  class="collection-list"
                >
                  <article
                    v-for="(entry, index) in (collection.value as unknown[])"
                    :key="index"
                    class="collection-card"
                  >
                    <div class="collection-card__number">{{ index + 1 }}</div>
                    <div
                      v-if="isObject(entry)"
                      class="collection-card__content"
                    >
                      <h4>{{ objectTitle(entry, `${collection.label} ${index + 1}`) }}</h4>
                      <div class="collection-card__fields">
                        <div
                          v-for="field in objectFields(entry)"
                          :key="field.key"
                        ><span>{{ field.label }}</span><strong>{{ formatScalar(field.value, field.key, entry) }}</strong></div>
                      </div>
                      <div
                        v-for="nested in nestedObjects(entry)"
                        :key="nested.key"
                        class="related-card__nested"
                      ><strong>{{ nested.label }}</strong><span>{{ objectFields(nested.value).map(field => `${field.label}: ${formatScalar(field.value, field.key, nested.value)}`).join(' · ') }}</span></div>
                    </div>
                    <strong v-else>{{ formatScalar(entry, collection.key) }}</strong>
                  </article>
                </div>
                <div
                  v-else
                  class="detail-chip-list"
                ><VChip
                  v-for="(entry, index) in (collection.value as unknown[])"
                  :key="index"
                  color="secondary"
                  variant="tonal"
                >{{ formatScalar(entry, collection.key) }}</VChip></div>
              </section>
            </VWindowItem>

            <VWindowItem value="activity">
              <section class="detail-section detail-section--first">
                <div class="detail-section__title"><VIcon
                  icon="tabler-history"
                  size="19"
                /><div><h3>{{ detailsText('sections.activityTitle', 'Record activity') }}</h3><p>{{ detailsText('sections.activityDescription', 'Lifecycle dates are separated from day-to-day business information.') }}</p></div></div>
                <div class="activity-timeline">
                  <article
                    v-for="field in systemFields"
                    :key="field.key"
                  ><div class="activity-timeline__dot" /><div><span>{{ field.label }}</span><strong>{{ formatScalar(field.value, field.key) }}</strong></div></article>
                </div>
              </section>
            </VWindowItem>
          </VWindow>
        </VCardText>

        <VCardActions class="detail-viewer__footer">
          <VBtn
            variant="flat"
            color="primary"
            prepend-icon="tabler-x"
          @click="isOpen = false"
          >{{ t('common.actions.close') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </span>
</template>

<style scoped lang="scss">
.detail-viewer { display: grid; grid-template-rows: auto auto minmax(0, 1fr) auto; max-block-size: min(92dvh, 900px); border-radius: 1rem !important; overflow: hidden; }
.detail-viewer__hero { display: flex; min-inline-size: 0; align-items: center; gap: 1rem; padding: 1.15rem 1.35rem; background: linear-gradient(135deg, rgba(var(--v-theme-primary), .12), rgba(var(--v-theme-info), .04) 52%, transparent); border-block-end: 1px solid rgba(var(--v-border-color), .12); }
.detail-viewer__hero-icon { display: grid; flex: 0 0 48px; block-size: 48px; border-radius: .85rem; background: rgb(var(--v-theme-primary)); box-shadow: 0 8px 20px rgba(var(--v-theme-primary), .22); color: rgb(var(--v-theme-on-primary)); place-items: center; }
.detail-viewer__identity { min-inline-size: 0; flex: 1; }
.detail-viewer__eyebrow { color: rgb(var(--v-theme-primary)); font-size: .7rem; font-weight: 750; letter-spacing: .065em; text-transform: uppercase; }
.detail-viewer__identity h2 { margin: .18rem 0 0; overflow: hidden; font-size: 1.35rem; line-height: 1.35; text-overflow: ellipsis; white-space: nowrap; }
.detail-viewer__identity p { margin: .2rem 0 0; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .82rem; }
.detail-viewer__status { flex: 0 0 auto; font-weight: 700; text-transform: capitalize; }
.detail-viewer__close { flex: 0 0 auto; }
.detail-viewer__tabs { min-block-size: 48px; padding-inline: .65rem; border-block-end: 1px solid rgba(var(--v-border-color), .14); background: rgb(var(--v-theme-surface)); }
.detail-viewer__body { min-block-size: 0; padding: 1.25rem 1.35rem 1.6rem !important; background: rgba(var(--v-theme-on-surface), .018); overflow-y: auto; overscroll-behavior: contain; }
.detail-metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: .75rem; margin-block-end: 1.5rem; }
.detail-metric { display: flex; min-inline-size: 0; align-items: center; gap: .75rem; padding: .9rem; border: 1px solid rgba(var(--v-border-color), .13); border-radius: .8rem; background: rgb(var(--v-theme-surface)); box-shadow: 0 3px 14px rgba(20, 18, 50, .035); }
.detail-metric__icon { display: grid; flex: 0 0 38px; block-size: 38px; border-radius: .7rem; background: rgba(var(--v-theme-primary), .1); color: rgb(var(--v-theme-primary)); place-items: center; }
.detail-metric div:last-child { min-inline-size: 0; display: grid; }
.detail-metric span, .detail-fact span, .collection-card__fields span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .69rem; font-weight: 650; letter-spacing: .025em; }
.detail-metric strong { overflow: hidden; font-size: 1.05rem; text-overflow: ellipsis; white-space: nowrap; }
.detail-section + .detail-section { margin-block-start: 1.65rem; }
.detail-section--first { margin-block-start: 0; }
.detail-section__title { display: flex; align-items: flex-start; gap: .6rem; margin-block-end: .8rem; color: rgb(var(--v-theme-primary)); }
.detail-section__title h3 { margin: 0; color: rgb(var(--v-theme-on-surface)); font-size: .92rem; }
.detail-section__title p { margin: .1rem 0 0; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .73rem; }
.detail-facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); column-gap: 1.1rem; border: 1px solid rgba(var(--v-border-color), .13); border-radius: .8rem; background: rgb(var(--v-theme-surface)); padding: .3rem .85rem; }
.detail-fact { display: grid; min-inline-size: 0; grid-template-columns: 28px minmax(0, 1fr); align-items: center; gap: .55rem; padding: .68rem .25rem; border-block-end: 1px solid rgba(var(--v-border-color), .08); }
.detail-fact > .v-icon { color: rgb(var(--v-theme-primary)); }
.detail-fact > div { min-inline-size: 0; display: grid; gap: .08rem; }
.detail-fact strong, .detail-fact a { overflow-wrap: anywhere; color: rgb(var(--v-theme-on-surface)); font-size: .86rem; font-weight: 550; text-decoration: none; }
.detail-fact a { color: rgb(var(--v-theme-primary)); }
.detail-fact--empty { opacity: .55; }
.detail-narratives { display: grid; gap: .7rem; }
.detail-narratives article { padding: .85rem 1rem; border-inline-start: 3px solid rgb(var(--v-theme-primary)); border-radius: 0 .65rem .65rem 0; background: rgb(var(--v-theme-surface)); }
.detail-narratives span { color: rgb(var(--v-theme-primary)); font-size: .7rem; font-weight: 700; text-transform: uppercase; }
.detail-narratives p { margin: .3rem 0 0; font-size: .86rem; line-height: 1.65; overflow-wrap: anywhere; white-space: pre-wrap; }
.related-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; }
.related-card { min-inline-size: 0; padding: 1rem; border: 1px solid rgba(var(--v-border-color), .13); border-radius: .8rem; background: rgb(var(--v-theme-surface)); }
.related-card header { display: flex; align-items: center; gap: .7rem; padding-block-end: .7rem; border-block-end: 1px solid rgba(var(--v-border-color), .1); }
.related-card__icon { display: grid; flex: 0 0 36px; block-size: 36px; border-radius: 50%; background: rgba(var(--v-theme-info), .11); color: rgb(var(--v-theme-info)); place-items: center; }
.related-card header span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .67rem; font-weight: 700; text-transform: uppercase; }
.related-card h4, .collection-card h4 { margin: .08rem 0 0; font-size: .92rem; }
.related-card dl { display: grid; grid-template-columns: minmax(90px, .7fr) minmax(0, 1.3fr); gap: .42rem .75rem; margin: .75rem 0 0; font-size: .78rem; }
.related-card dt { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); }
.related-card dd { margin: 0; font-weight: 550; overflow-wrap: anywhere; }
.related-card__nested { display: grid; gap: .18rem; margin-block-start: .7rem; padding: .55rem .65rem; border-radius: .5rem; background: rgba(var(--v-theme-primary), .05); font-size: .72rem; }
.related-card__nested strong { color: rgb(var(--v-theme-primary)); }
.related-card__nested span { overflow-wrap: anywhere; }
.collection-list { display: grid; gap: .7rem; }
.collection-card { display: grid; min-inline-size: 0; grid-template-columns: 32px minmax(0, 1fr); gap: .8rem; padding: .9rem; border: 1px solid rgba(var(--v-border-color), .13); border-radius: .8rem; background: rgb(var(--v-theme-surface)); }
.collection-card__number { display: grid; block-size: 28px; border-radius: .5rem; background: rgba(var(--v-theme-primary), .1); color: rgb(var(--v-theme-primary)); font-size: .72rem; font-weight: 750; place-items: center; }
.collection-card__content { min-inline-size: 0; }
.collection-card__fields { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .65rem 1rem; margin-block-start: .65rem; }
.collection-card__fields div { min-inline-size: 0; display: grid; }
.collection-card__fields strong { overflow-wrap: anywhere; font-size: .8rem; font-weight: 550; }
.detail-chip-list { display: flex; flex-wrap: wrap; gap: .5rem; }
.activity-timeline { position: relative; display: grid; gap: 0; padding-inline-start: .4rem; }
.activity-timeline::before { position: absolute; inset-block: 12px; inset-inline-start: 9px; inline-size: 2px; background: rgba(var(--v-theme-primary), .15); content: ''; }
.activity-timeline article { position: relative; display: flex; align-items: center; gap: .8rem; min-block-size: 60px; }
.activity-timeline__dot { z-index: 1; flex: 0 0 12px; block-size: 12px; border: 3px solid rgb(var(--v-theme-surface)); border-radius: 50%; background: rgb(var(--v-theme-primary)); box-shadow: 0 0 0 2px rgba(var(--v-theme-primary), .2); }
.activity-timeline article > div:last-child { display: grid; }
.activity-timeline span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .7rem; }
.activity-timeline strong { font-size: .85rem; }
.detail-viewer__footer { display: flex; min-block-size: 62px; align-items: center; justify-content: flex-end; gap: 1rem; padding: .7rem 1.35rem !important; border-block-start: 1px solid rgba(var(--v-border-color), .13); background: linear-gradient(90deg, rgba(var(--v-theme-primary), .055), rgba(var(--v-theme-surface), .98) 46%); }
.detail-viewer__footer .v-btn { flex: 0 0 auto; min-inline-size: 92px; }
@media (max-width: 700px) {
  .detail-viewer { max-block-size: calc(100dvh - 1rem); border-radius: .8rem !important; }
  .detail-viewer__hero { gap: .7rem; padding: .9rem; }
  .detail-viewer__hero-icon { flex-basis: 40px; block-size: 40px; }
  .detail-viewer__identity h2 { font-size: 1.05rem; }
  .detail-viewer__status { display: none; }
  .detail-viewer__body { padding: .9rem !important; }
  .detail-facts, .related-grid { grid-template-columns: minmax(0, 1fr); }
  .collection-card__fields { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .detail-viewer__footer { min-block-size: 56px; padding-inline: .9rem !important; }
}
@media (max-width: 420px) { .collection-card__fields { grid-template-columns: minmax(0, 1fr); } }
</style>
