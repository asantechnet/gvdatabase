<script setup lang="ts">
import { computed, ref } from 'vue'
import type { Contract } from '@/api/contracts'
import {
  type OfficialCompanyDetails,
  type OfficialPrintMargins,
  buildOfficialContractPrintData,
} from '@/utils/officialContractPrint'

const props = defineProps<{
  contract: Contract
  company: OfficialCompanyDetails
  includeCover: boolean
  useLetterhead: boolean
  coverUrl?: string | null
  margins: OfficialPrintMargins
}>()

const { locale } = useI18n()
const printElement = ref<HTMLElement | null>(null)
const data = computed(() => buildOfficialContractPrintData(props.contract, locale.value))
const hasLetterhead = computed(() => props.useLetterhead && Boolean(props.company.letterheadUrl))
const hasCoverArtwork = computed(() => props.includeCover && Boolean(props.coverUrl))
const coverDescription = computed(() => props.contract.cover_text || props.contract.contract_template?.cover_text || null)

const rootStyle = computed(() => ({
  '--official-print-top': `${props.margins.top}mm`,
  '--official-print-right': `${props.margins.right}mm`,
  '--official-print-bottom': `${props.margins.bottom}mm`,
  '--official-print-left': `${props.margins.left}mm`,
}))

defineExpose({ element: printElement })
</script>

<template>
  <div
    ref="printElement"
    class="official-contract-print"
    :class="{ 'official-contract-print--letterhead': hasLetterhead }"
    :dir="data.direction"
    :style="rootStyle"
  >
    <img
      v-if="hasLetterhead"
      :src="company.letterheadUrl"
      class="official-contract-print__letterhead"
      alt=""
      aria-hidden="true"
    >

    <header
      v-if="!hasLetterhead"
      class="official-contract-print__company-header"
    >
      <img
        v-if="company.logo"
        :src="company.logo"
        class="official-contract-print__logo"
        :alt="company.brandName"
      >
      <div class="official-contract-print__company-identity">
        <strong>{{ company.brandName }}</strong>
        <span v-if="company.address">{{ company.address }}</span>
      </div>
      <div
        v-if="company.phone || company.email || company.website"
        class="official-contract-print__company-contact"
      >
        <span v-if="company.phone">{{ company.phone }}</span>
        <span v-if="company.email">{{ company.email }}</span>
        <span v-if="company.website">{{ company.website }}</span>
      </div>
    </header>

    <footer
      v-if="!hasLetterhead && (company.address || company.phone || company.email || company.website)"
      class="official-contract-print__company-footer"
    >
      <span v-if="company.address">{{ company.address }}</span>
      <span v-if="company.phone">{{ company.phone }}</span>
      <span v-if="company.email">{{ company.email }}</span>
      <span v-if="company.website">{{ company.website }}</span>
    </footer>

    <section
      v-if="includeCover"
      class="official-contract-print__cover official-contract-print-page"
      :class="{ 'official-contract-print__cover--artwork': hasCoverArtwork }"
    >
      <img
        v-if="hasCoverArtwork"
        :src="coverUrl || undefined"
        alt=""
        class="official-contract-print__cover-artwork"
      >
      <template v-else>
        <div class="official-contract-print__cover-topline" />
        <div class="official-contract-print__cover-content">
          <img
            v-if="company.logo"
            :src="company.logo"
            :alt="company.brandName"
            class="official-contract-print__cover-logo"
          >
          <p class="official-contract-print__cover-company">
            {{ company.brandName }}
          </p>
          <p class="official-contract-print__cover-kicker">
            {{ $t('pages.contracts.shared.officialPrint.officialDocument') }}
          </p>
          <h1>{{ data.title }}</h1>
          <p
            v-if="coverDescription"
            class="official-contract-print__cover-description"
          >
            {{ coverDescription }}
          </p>
          <dl class="official-contract-print__cover-meta">
            <template
              v-for="item in data.metadata"
              :key="item.label"
            >
              <dt>{{ item.label }}</dt>
              <dd>{{ item.value }}</dd>
            </template>
          </dl>
        </div>
      </template>
    </section>

    <main class="official-contract-print__body official-contract-print-page">
      <section class="official-contract-print__title-banner">
        <span>{{ $t('pages.contracts.shared.officialPrint.officialDocument') }}</span>
        <h1>{{ data.title }}</h1>
        <dl
          v-if="data.metadata.length"
          class="official-contract-print__metadata"
        >
          <template
            v-for="item in data.metadata"
            :key="item.label"
          >
            <dt>{{ item.label }}</dt>
            <dd>{{ item.value }}</dd>
          </template>
        </dl>
      </section>

      <section
        v-if="data.applicantFields.length"
        class="official-contract-print__applicant-section"
      >
        <h2>{{ $t('pages.contracts.shared.officialPrint.applicantDetails') }}</h2>
        <dl class="official-contract-print__form-grid">
          <div
            v-for="item in data.applicantFields"
            :key="item.label"
            class="official-contract-print__form-field"
          >
            <dt>{{ item.label }}</dt>
            <dd>{{ item.value }}</dd>
          </div>
        </dl>
      </section>

      <!-- Contract content is sanitized by the server when the document is issued. -->
      <!-- eslint-disable vue/no-v-html -->
      <article
        v-if="data.content"
        class="official-contract-print__clauses"
        v-html="data.content"
      />
      <!-- eslint-enable vue/no-v-html -->
    </main>

    <section class="official-contract-print__signature-page official-contract-print-page">
      <div class="official-contract-print__signature-grid">
        <div class="official-contract-print__signature-block">
          <div
            class="official-contract-print__thumbprint"
            aria-hidden="true"
          />
          <div class="official-contract-print__signature-line" />
          <strong>{{ data.applicantSignatureLabel }}</strong>
          <span>{{ $t('pages.contracts.shared.officialPrint.signatureAndThumbprint') }}</span>
        </div>
        <div class="official-contract-print__signature-block">
          <div class="official-contract-print__signature-art">
            <img
              v-if="company.signatureUrl"
              :src="company.signatureUrl"
              alt=""
            >
          </div>
          <div class="official-contract-print__signature-line" />
          <strong>{{ contract.company_signatory_name || company.brandName }}</strong>
          <span>{{ $t('pages.contracts.shared.officialPrint.authorizedSignature') }}</span>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.official-contract-print {
  --official-primary: rgb(var(--v-theme-primary));
  --official-secondary: rgb(var(--v-theme-secondary));
  color: #172033;
  font-family: Vazirmatn, 'Noto Sans Arabic', 'Public Sans', Arial, sans-serif;
  font-size: 11pt;
  line-height: 1.72;
}

.official-contract-print__letterhead {
  display: none;
}

.official-contract-print__company-header,
.official-contract-print__company-footer {
  display: none;
}

.official-contract-print__cover {
  position: relative;
  box-sizing: border-box;
  min-height: 297mm;
  width: 210mm;
  margin-block-start: calc(-1 * var(--official-print-top));
  margin-inline-start: calc(-1 * var(--official-print-left));
  overflow: hidden;
  background: #fff;
  break-after: page;
  page-break-after: always;
}

.official-contract-print__cover-topline {
  block-size: 12mm;
  background: var(--official-primary);
}

.official-contract-print__cover-content {
  display: flex;
  min-height: 285mm;
  flex-direction: column;
  align-items: center;
  padding: 34mm 25mm;
  text-align: center;
}

.official-contract-print__cover-logo {
  max-width: 42mm;
  max-height: 28mm;
  margin-block-end: 12mm;
  object-fit: contain;
}

.official-contract-print__cover-company,
.official-contract-print__cover-kicker {
  margin: 0;
}

.official-contract-print__cover-company {
  color: var(--official-primary);
  font-size: 15pt;
  font-weight: 800;
}

.official-contract-print__cover-kicker {
  margin-block-start: 23mm;
  color: var(--official-secondary);
  font-size: 9pt;
  font-weight: 800;
  letter-spacing: .13em;
  text-transform: uppercase;
}

.official-contract-print__cover h1 {
  max-width: 145mm;
  margin: 5mm 0 0;
  color: #172033;
  font-size: 28pt;
  line-height: 1.25;
}

.official-contract-print__cover-description {
  max-width: 135mm;
  margin: 9mm 0 0;
  color: #44526a;
  white-space: pre-line;
}

.official-contract-print__cover-meta {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  width: 100%;
  margin: auto 0 0;
  border-block: 1px solid #cbd5e1;
  text-align: start;
}

.official-contract-print__cover-meta dt,
.official-contract-print__metadata dt,
.official-contract-print__form-field dt {
  color: #506079;
  font-size: 8pt;
  font-weight: 800;
  letter-spacing: .04em;
  text-transform: uppercase;
}

.official-contract-print__cover-meta dd {
  margin: 1mm 0 0;
  font-weight: 700;
}

.official-contract-print__cover-meta dt,
.official-contract-print__cover-meta dd {
  padding: 4mm;
}

.official-contract-print__cover-artwork {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.official-contract-print__body {
  min-width: 0;
}

.official-contract-print__title-banner {
  margin-block-end: 8mm;
  border-block: 2px solid var(--official-primary);
  padding: 4mm 0;
  break-inside: avoid;
  page-break-inside: avoid;
}

.official-contract-print__title-banner > span {
  display: block;
  color: var(--official-secondary);
  font-size: 8pt;
  font-weight: 800;
  letter-spacing: .12em;
  text-transform: uppercase;
}

.official-contract-print__title-banner h1 {
  margin: 1mm 0 4mm;
  color: var(--official-primary);
  font-size: 19pt;
  line-height: 1.3;
}

.official-contract-print__metadata {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 2mm 4mm;
  margin: 0;
}

.official-contract-print__metadata dd,
.official-contract-print__form-field dd {
  margin: 1mm 0 0;
  color: #172033;
  font-weight: 700;
  overflow-wrap: anywhere;
}

.official-contract-print__applicant-section {
  margin-block-end: 8mm;
  break-inside: avoid;
  page-break-inside: avoid;
}

.official-contract-print__applicant-section h2 {
  margin: 0;
  border: 1px solid var(--official-primary);
  border-block-end: 0;
  background: color-mix(in srgb, var(--official-primary) 9%, white);
  color: var(--official-primary);
  padding: 3mm 4mm;
  font-size: 11pt;
}

.official-contract-print__form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  margin: 0;
  border-inline-start: 1px solid #94a3b8;
  border-block-start: 1px solid #94a3b8;
}

.official-contract-print__form-field {
  min-width: 0;
  border-inline-end: 1px solid #94a3b8;
  border-block-end: 1px solid #94a3b8;
  padding: 3mm 4mm;
}

.official-contract-print__clauses :deep(h1),
.official-contract-print__clauses :deep(h2),
.official-contract-print__clauses :deep(h3) {
  margin: 7mm 0 2.5mm;
  color: #172033;
  line-height: 1.4;
  break-after: avoid;
  page-break-after: avoid;
}

.official-contract-print__clauses :deep(h1) { font-size: 15pt; }
.official-contract-print__clauses :deep(h2) { font-size: 13pt; }
.official-contract-print__clauses :deep(h3) { font-size: 11pt; }

.official-contract-print__clauses :deep(p),
.official-contract-print__clauses :deep(li) {
  margin-block: 0 3mm;
  text-align: justify;
}

.official-contract-print__clauses :deep(table) {
  width: 100%;
  margin-block: 5mm;
  border-collapse: collapse;
}

.official-contract-print__clauses :deep(th),
.official-contract-print__clauses :deep(td) {
  border: 1px solid #94a3b8;
  padding: 2.5mm 3mm;
  text-align: start;
  vertical-align: top;
}

.official-contract-print__clauses :deep(th) {
  background: #eaf0f7;
  color: #172033;
  font-weight: 800;
}

.official-contract-print__clauses :deep(tr) {
  break-inside: avoid;
  page-break-inside: avoid;
}

.official-contract-print__signature-page {
  display: flex;
  min-height: calc(297mm - var(--official-print-top) - var(--official-print-bottom));
  flex-direction: column;
  justify-content: flex-end;
  break-before: page;
  page-break-before: always;
}

.official-contract-print__signature-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20mm;
}

.official-contract-print__signature-block {
  display: flex;
  min-width: 0;
  min-height: 42mm;
  flex-direction: column;
  justify-content: end;
}

.official-contract-print__thumbprint,
.official-contract-print__signature-art {
  display: flex;
  block-size: 26mm;
  align-items: end;
  justify-content: center;
}

.official-contract-print__thumbprint {
  inline-size: 22mm;
  margin-inline-start: auto;
  border: 1px dashed #64748b;
  border-radius: 2mm;
}

.official-contract-print__signature-art img {
  max-width: 42mm;
  max-height: 24mm;
  object-fit: contain;
}

.official-contract-print__signature-line {
  border-block-end: 1px solid #172033;
  margin-block: 3mm 2mm;
}

.official-contract-print__signature-block strong { font-size: 10pt; }
.official-contract-print__signature-block span { color: #506079; font-size: 8pt; }

@media print {
  :global(body.printing-contract-document *) {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  :global(body.printing-contract-document .official-contract-print) {
    color: #172033 !important;
  }

  :global(body.printing-contract-document .official-contract-print__letterhead) {
    position: fixed;
    z-index: -1;
    display: block;
    inset: 0;
    width: 210mm;
    height: 297mm;
    object-fit: cover;
  }

  :global(body.printing-contract-document .official-contract-print__company-header) {
    position: fixed;
    z-index: 1;
    top: 9mm;
    right: 18mm;
    left: 18mm;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 4mm;
    border-block-end: 1.5px solid #172033;
    padding-block-end: 3mm;
    color: #172033;
    font-size: 8pt;
  }

  :global(body.printing-contract-document .official-contract-print__logo) {
    max-width: 28mm;
    max-height: 18mm;
    object-fit: contain;
  }

  :global(body.printing-contract-document .official-contract-print__company-identity),
  :global(body.printing-contract-document .official-contract-print__company-contact) {
    display: flex;
    min-width: 0;
    flex-direction: column;
  }

  :global(body.printing-contract-document .official-contract-print__company-contact) {
    align-items: end;
    text-align: end;
  }

  :global(body.printing-contract-document .official-contract-print__company-footer) {
    position: fixed;
    z-index: 1;
    right: 18mm;
    bottom: 9mm;
    left: 18mm;
    display: flex;
    justify-content: center;
    gap: 3mm;
    border-block-start: 1px solid #64748b;
    padding-block-start: 2mm;
    color: #475569;
    font-size: 7.5pt;
  }

  :global(body.printing-contract-document .official-contract-print__cover:not(:last-child)) {
    break-after: page;
    page-break-after: always;
  }

  :global(body.printing-contract-document .official-contract-print__body) {
    min-height: 0;
  }

  :global(body.printing-contract-document .official-contract-print__signature-page) {
    break-before: page;
    page-break-before: always;
  }
}
</style>
