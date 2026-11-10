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
const isEmployment = computed(() => (props.contract.type_slug || props.contract.contract_template?.type_slug || '').toLowerCase() === 'employment')

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
    :class="{ 'official-contract-print--letterhead': hasLetterhead, 'official-contract-print--employment': isEmployment }"
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
        <div class="official-contract-print__cover-content">
          <img
            v-if="company.logo"
            :src="company.logo"
            :alt="company.brandName"
            class="official-contract-print__cover-logo"
          >
          <p class="official-contract-print__cover-kicker">
            {{ $t('pages.contracts.shared.officialPrint.officialDocument') }}
          </p>
          <h1>{{ data.title }}</h1>
        </div>
      </template>
    </section>

    <main class="official-contract-print__body official-contract-print-page">
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
      <section class="official-contract-print__title-banner">
        <dl
          v-if="data.metadata.length"
          class="official-contract-print__metadata"
        >
          <div
            v-for="item in data.metadata"
            :key="item.label"
            class="official-contract-print__metadata-field"
          >
            <dt>{{ item.label }}</dt>
            <dd>{{ item.value }}</dd>
          </div>
        </dl>
      </section>

      <section
        v-if="data.detailsTitle && data.applicantFields.length"
        class="official-contract-print__applicant-section"
      >
        <h2>{{ data.detailsTitle }}</h2>
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
      <section class="official-contract-print__signature">
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
            <strong>{{ company.brandName }}</strong>
            <span>{{ $t('pages.contracts.shared.officialPrint.authorizedSignature') }}</span>
          </div>
        </div>
      </section>
    </main>
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
  z-index: 1;
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

.official-contract-print--letterhead .official-contract-print__cover {
  margin-block-start: 0;
  margin-inline-start: 0;
}

.official-contract-print__cover-content {
  display: flex;
  min-height: 297mm;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6mm;
  padding: 20mm 25mm;
  text-align: center;
}

.official-contract-print__cover-logo {
  max-width: 58mm;
  max-height: 34mm;
  object-fit: contain;
}

.official-contract-print__cover-kicker {
  margin: 0;
}

.official-contract-print__cover-kicker {
  color: var(--official-secondary);
  font-size: 11pt;
  font-weight: 800;
  letter-spacing: .13em;
  text-transform: uppercase;
}

.official-contract-print__cover h1 {
  max-width: 145mm;
  margin: 0;
  color: #172033;
  font-size: 26pt;
  line-height: 1.25;
}

.official-contract-print__metadata dt,
.official-contract-print__form-field dt {
  color: #334155;
  font-size: 7.5pt;
  font-weight: 800;
  letter-spacing: .04em;
  text-transform: uppercase;
}

.official-contract-print__cover-artwork {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.official-contract-print__body {
  display: flex;
  box-sizing: border-box;
  min-height: calc(297mm - var(--official-print-top) - var(--official-print-bottom));
  min-width: 0;
  flex-direction: column;
}

.official-contract-print--letterhead .official-contract-print__body {
  box-decoration-break: clone;
  padding: var(--official-print-top) var(--official-print-right) var(--official-print-bottom) var(--official-print-left);
  -webkit-box-decoration-break: clone;
}

.official-contract-print__title-banner {
  margin-block-end: 5mm;
  break-inside: avoid;
  page-break-inside: avoid;
}

.official-contract-print__title-banner > span {
  display: block;
  color: #334155;
  font-size: 8pt;
  font-weight: 800;
  letter-spacing: .12em;
  text-transform: uppercase;
}

.official-contract-print__title-banner h1 {
  margin: 1mm 0 3mm;
  color: #172033;
  font-size: 17pt;
  line-height: 1.3;
}

.official-contract-print--employment .official-contract-print__title-banner h1 {
  font-size: 13pt;
}

.official-contract-print__metadata {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8mm;
  margin: 0;
}

.official-contract-print__metadata-field {
  display: flex;
  min-width: 0;
  align-items: baseline;
  justify-content: space-between;
  gap: 2mm;
}

.official-contract-print__metadata dd,
.official-contract-print__form-field dd {
  margin: 0;
  color: #0f172a;
  font-weight: 700;
  overflow-wrap: anywhere;
}

.official-contract-print__metadata dt {
  font-size: 6.75pt;
}

.official-contract-print__metadata dd {
  font-size: 8pt;
}

.official-contract-print__applicant-section {
  margin-block-end: 6mm;
  break-inside: avoid;
  page-break-inside: avoid;
}

.official-contract-print__applicant-section h2 {
  margin: 0;
  border: 1px solid var(--official-primary);
  border-block-end: 0;
  background: color-mix(in srgb, var(--official-primary) 16%, white);
  color: #172033;
  padding: 1.75mm 2.5mm;
  font-size: 9pt;
}

.official-contract-print__form-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  margin: 0;
  border-inline-start: 1px solid color-mix(in srgb, var(--official-primary) 55%, #1e293b);
  border-block-start: 1px solid color-mix(in srgb, var(--official-primary) 55%, #1e293b);
}

.official-contract-print__form-field {
  min-width: 0;
  border-inline-end: 1px solid color-mix(in srgb, var(--official-primary) 55%, #1e293b);
  border-block-end: 1px solid color-mix(in srgb, var(--official-primary) 55%, #1e293b);
  padding: 1.75mm 2.5mm;
}

.official-contract-print__form-field dt {
  font-size: 7pt;
}

.official-contract-print__form-field dd {
  font-size: 8.5pt;
  line-height: 1.35;
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
  font-size: 10pt;
  text-align: justify;
}

.official-contract-print--employment .official-contract-print__clauses :deep(p),
.official-contract-print--employment .official-contract-print__clauses :deep(li) {
  font-size: 8.5pt;
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
  background: color-mix(in srgb, var(--official-primary) 13%, white);
  color: #172033;
  font-weight: 800;
}

.official-contract-print__clauses :deep(tr) {
  break-inside: avoid;
  page-break-inside: avoid;
}

.official-contract-print__signature {
  /* Signatures belong after the agreement instead of being forced against the
     printable edge. This leaves a reliable, professional signing area on
     shorter employment agreements as well. */
  margin-block-start: 10mm;
  padding-block-start: 2mm;
  break-inside: avoid;
  page-break-inside: avoid;
}

.official-contract-print__signature-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20mm;
  margin-block-end: 8mm;
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
  visibility: hidden;
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
    color: #0f172a !important;
  }

  :global(body.printing-contract-document .official-contract-print__title-banner > span),
  :global(body.printing-contract-document .official-contract-print__title-banner h1),
  :global(body.printing-contract-document .official-contract-print__applicant-section h2),
  :global(body.printing-contract-document .official-contract-print__form-field dt),
  :global(body.printing-contract-document .official-contract-print__metadata dt) {
    color: #172033 !important;
  }

  :global(body.printing-contract-document .official-contract-print__applicant-section h2) {
    border-color: var(--official-primary) !important;
    background: color-mix(in srgb, var(--official-primary) 18%, white) !important;
  }

  :global(body.printing-contract-document .official-contract-print__form-grid),
  :global(body.printing-contract-document .official-contract-print__form-field) {
    border-color: color-mix(in srgb, var(--official-primary) 55%, #1e293b) !important;
  }

  :global(body.printing-contract-document .official-contract-print__clauses th),
  :global(body.printing-contract-document .official-contract-print__clauses td) {
    border-color: #475569 !important;
  }

  :global(body.printing-contract-document .official-contract-print__clauses th) {
    background: color-mix(in srgb, var(--official-primary) 14%, white) !important;
  }

  :global(body.printing-contract-document .official-contract-print__letterhead) {
    position: fixed;
    z-index: 0;
    display: block;
    top: 0;
    left: 0;
    width: 210mm;
    height: 297mm;
    object-fit: cover;
  }

  :global(body.printing-contract-document .official-contract-print__body) {
    position: relative;
    z-index: 1;
  }

  :global(body.printing-contract-document .official-contract-print__company-header) {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 3mm;
    border-block-end: 1.5px solid #172033;
    margin-block-end: 5mm;
    padding-block-end: 2mm;
    color: #0f172a;
    font-size: 8.25pt;
  }

  :global(body.printing-contract-document .official-contract-print__logo) {
    max-width: 35mm;
    max-height: 19mm;
    object-fit: contain;
  }

  :global(body.printing-contract-document .official-contract-print__company-identity),
  :global(body.printing-contract-document .official-contract-print__company-contact) {
    display: flex;
    min-width: 0;
    flex-direction: column;
  }

  :global(body.printing-contract-document .official-contract-print__company-identity strong) {
    font-size: 9pt;
  }

  :global(body.printing-contract-document .official-contract-print__company-contact) {
    align-items: end;
    text-align: end;
  }

  :global(body.printing-contract-document .official-contract-print__company-footer) {
    position: fixed;
    z-index: 1;
    right: 0;
    bottom: 3mm;
    left: 0;
    display: flex;
    justify-content: center;
    gap: 4mm;
    border-block-start: 1px solid #64748b;
    padding-block-start: 1.5mm;
    color: #1e293b;
    font-size: 8pt;
    text-align: center;
  }

  :global(body.printing-contract-document .official-contract-print__cover:not(:last-child)) {
    break-after: page;
    page-break-after: always;
  }

}
</style>
