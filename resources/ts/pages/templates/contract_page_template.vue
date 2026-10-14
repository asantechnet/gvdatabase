<script setup lang="ts">
import { printDocument } from '@/utils/printDocument'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PartyDetails } from '@/utils/contractPartyDetails'
import type { ContractPrintHeader } from '@/composables/useContractPrintHeader'
import ContractPrintHeaderBlock from '@/components/contract/ContractPrintHeader.vue'

// 1. Interfaces & Props
export interface CompanyDetails {
  brandName: string
  logo?: string
  slogan?: string
  address?: string
  phone?: string
  email?: string
  website?: string
  licence?: string
  signatureUrl?: string
  letterheadUrl?: string
  letterheadSafeX?: number
  letterheadSafeY?: number
  letterheadSafeWidth?: number
  letterheadSafeHeight?: number
}

export interface ContractClause {
  clauseNumber?: string
  title?: string
  content: string | string[]
}

export interface ContractPageProps {
  documentTitle?: string
  documentNumber: string
  company: CompanyDetails
  footerText?: string
  pageNumberString?: string
  clauses?: ContractClause[]
  htmlContent?: string
  showSignature?: boolean
  contracteeName?: string
  contracteeCompanyName?: string // Added for flexibility (e.g., "John Doe" vs "Acme Corp")
  companySignatoryName?: string | null
  partyDetails?: PartyDetails
  headerDetails?: ContractPrintHeader | null
  accentColor?: string
}

const { t } = useI18n()
const props = withDefaults(defineProps<ContractPageProps>(), {
  documentTitle: '',
  footerText: undefined,
  pageNumberString: undefined,
  clauses: () => [],
  htmlContent: '',
  showSignature: false,
  contracteeName: 'Client / Counterparty',
  contracteeCompanyName: '',
  partyDetails: undefined,
  headerDetails: null,
  companySignatoryName: null,
  accentColor: '#1e293b',
})

const resolvedFooterText = computed(() => props.footerText ?? t('documents.contract.defaultFooter'))
const resolvedPageNumber = computed(() => props.pageNumberString ?? t('pages.contracts.allContracts.allContracts.pageNumber', { page: 1, total: 1 }))
const resolvedDocumentTitle = computed(() => props.documentTitle || t('pages.contracts.shared.contractAgreement'))

// 2. Template Reference
const contractRef = ref<HTMLElement | null>(null)

// A letterhead URL hides the built-in header/footer, so a URL whose file is
// gone would remove the document chrome entirely. Print paths resolve
// availability up front (see `utils/printArtwork`); this is the last-resort
// guard for anyone rendering or printing the page on its own.
const letterheadFailed = ref(false)
const hasLetterhead = computed(() => Boolean(props.company.letterheadUrl) && !letterheadFailed.value)

watch(() => props.company.letterheadUrl, () => { letterheadFailed.value = false })

const letterheadSafeAreaStyle = computed(() => ({
  '--letterhead-safe-x': `${props.company.letterheadSafeX ?? 8.5}%`,
  '--letterhead-safe-y': `${props.company.letterheadSafeY ?? 15}%`,
  '--letterhead-safe-width': `${props.company.letterheadSafeWidth ?? 83}%`,
  '--letterhead-safe-height': `${props.company.letterheadSafeHeight ?? 70}%`,
}))

// 3. Helper for legacy structured array/string clauses
const formatParagraphs = (content: string | string[]) => {
  return Array.isArray(content) ? content : [content]
}

// 4. Print Controller
const printContractPage = async () => {
  if (!contractRef.value) return
  await printDocument({ bodyClass: 'printing-contract-document', pageStyle: 'size: A4; margin: 0;', element: contractRef.value })
}

defineExpose({
  print: printContractPage,
})
</script>

<template>
  <div
    ref="contractRef"
    class="contract-page-container print-document"
    :class="{ 'persian-document': ['fa-AF', 'ps-AF'].includes($i18n.locale), 'has-letterhead': hasLetterhead }"
    :dir="$i18n.locale === 'en' ? 'ltr' : 'rtl'"
    :style="{ '--brand-accent': accentColor, ...letterheadSafeAreaStyle }"
  >
    <img
      v-if="hasLetterhead"
      :src="company.letterheadUrl"
      alt=""
      class="letterhead-background"
      aria-hidden="true"
      @error="letterheadFailed = true"
    >
    <!-- Sleek Margin Accents -->
    <div class="margin-rail-left" />
    <div class="margin-corner-top-right" />

    <div class="content-wrapper">
      <!-- The custom letterhead already contains the document chrome. -->
      <template v-if="!hasLetterhead">
        <!-- Compact Header -->
        <header class="contract-header">
          <div class="header-left">
            <h1 class="document-title">
              {{ resolvedDocumentTitle }}
            </h1>
            <p>{{ documentNumber }}</p>
            <p
              v-if="company.slogan"
              class="company-slogan"
            >
              {{ company.slogan }}
            </p>
            <p
              v-if="company.licence"
              class="company-slogan"
            >
              {{ $t('documents.contract.licence', { licence: company.licence }) }}
            </p>
          </div>

          <div class="header-right">
            <img
              v-if="company.logo"
              :src="company.logo"
              :alt="company.brandName"
              class="company-logo"
            >
            <span class="brand-name">{{ company.brandName }}</span>
          </div>
        </header>
        <div class="header-divider" />
      </template>

      <!-- Main Contract Body Area -->
      <main class="contract-body">
        <ContractPrintHeaderBlock
          v-if="headerDetails"
          :header="headerDetails"
        />

        <section
          v-if="partyDetails && (partyDetails.ourCompany.length || partyDetails.counterparty.length)"
          class="party-details"
        >
          <h2 class="party-details-title">
            {{ $t('pages.contracts.shared.contractParties') }}
          </h2>
          <div class="party-details-grid">
            <div
              v-if="partyDetails.ourCompany.length"
              class="party-details-card"
            >
              <h3>{{ company.brandName }}</h3>
              <dl>
                <template
                  v-for="detail in partyDetails.ourCompany"
                  :key="`company-${detail.label}`"
                >
                  <dt>{{ detail.label }}</dt>
                  <dd>{{ detail.value }}</dd>
                </template>
              </dl>
            </div>
            <div
              v-if="partyDetails.counterparty.length"
              class="party-details-card"
            >
              <h3>{{ contracteeName || $t('pages.contracts.allContracts.allContracts.counterparty') }}</h3>
              <dl>
                <template
                  v-for="detail in partyDetails.counterparty"
                  :key="`counterparty-${detail.label}`"
                >
                  <dt>{{ detail.label }}</dt>
                  <dd>{{ detail.value }}</dd>
                </template>
              </dl>
            </div>
          </div>
        </section>

        <!-- Raw HTML Content -->
        <div
          v-if="htmlContent"
          class="html-contract-content"
          v-html="htmlContent"
        />

        <!-- Legacy Structured Clauses -->
        <template v-else>
          <section
            v-for="(clause, idx) in clauses"
            :key="idx"
            class="clause-section"
          >
            <h2
              v-if="clause.title || clause.clauseNumber"
              class="clause-heading"
            >
              <span
                v-if="clause.clauseNumber"
                class="clause-number"
              >{{ clause.clauseNumber }}.</span>
              {{ clause.title }}
            </h2>
            <div class="clause-content">
              <p
                v-for="(paragraph, pIdx) in formatParagraphs(clause.content)"
                :key="pIdx"
              >
                {{ paragraph }}
              </p>
            </div>
          </section>
        </template>

        <div class="custom-content-slot">
          <slot />
        </div>

        <!-- Signature Section -->
        <div
          v-if="showSignature"
          class="signature-section"
        >
          <div class="signature-grid">
            <div class="signature-box">
              <div class="signature-slot">
                <img
                  v-if="company.signatureUrl"
                  :src="company.signatureUrl"
                  alt="Authorized company signature"
                  class="company-signature"
                >
              </div>
              <div class="sig-line" />
              <div class="sig-data">
                <p
                  v-if="companySignatoryName"
                  class="sig-name"
                >
                  {{ companySignatoryName }}
                </p>
                <p class="sig-detail">
                  {{ company.brandName }}
                </p>
              </div>
            </div>

            <div class="signature-box">
              <div
                class="signature-slot"
                aria-hidden="true"
              />
              <div class="sig-line" />
              <div class="sig-data">
                <p class="sig-name">
                  {{ contracteeName }}
                </p>
                <p
                  v-if="contracteeCompanyName"
                  class="sig-detail"
                >
                  {{ contracteeCompanyName }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </main>

      <!-- Fixed Bottom Footer -->
      <footer
        v-if="!hasLetterhead"
        class="contract-footer-wrapper"
      >
        <div class="footer-divider" />
        <div class="contract-footer">
          <div class="footer-col footer-message">
            <strong>{{ company.brandName }}</strong><br><span>{{ resolvedFooterText }}</span>
          </div>

          <div class="footer-col footer-contact">
            <span v-if="company.address">{{ company.address }}</span>
            <span v-if="company.address && company.phone"> {{ $t('common.messages.text') }} </span>
            <span v-if="company.phone">{{ $t('documents.contract.telephone', { phone: company.phone }) }}</span>
            <span v-if="company.email && (company.address || company.phone)"> {{ $t('common.messages.text') }} </span>
            <span v-if="company.email">{{ company.email }}</span>
          </div>

          <div class="footer-col footer-page">
            <span class="page-number">{{ resolvedPageNumber }}</span>
          </div>
        </div>
      </footer>
    </div>
  </div>
</template>

<style scoped>
/* 1. Base Container Setup: Exactly A4 dimensions */
.contract-page-container {
  --brand-accent: #1e293b;
  /* Shared contract font stack — must match the TipTap editor so the
     document looks the same on screen and on paper. */
  font-family: 'Vazirmatn', 'Noto Sans Arabic', Tahoma, 'Helvetica Neue', sans-serif;
  color: #2b2b2b;
  background: #ffffff;
  width: 100%;
  max-width: 210mm;
  min-height: 297mm;
  margin: 20px auto;
  padding: 16mm 18mm 12mm 18mm;
  box-sizing: border-box;
  position: relative;
  display: flex;
  flex-direction: column;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
}

/* Keep authored contract text untouched: only the surrounding document chrome
   receives the application Persian font. */
.contract-page-container.persian-document .contract-header,
.contract-page-container.persian-document .signature-section,
.contract-page-container.persian-document .contract-footer-wrapper {
  font-family: Vazirmatn, 'Public Sans', sans-serif;
}

.letterhead-background {
  position: absolute;
  z-index: 0;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  pointer-events: none;
}

.content-wrapper,
.margin-rail-left,
.margin-corner-top-right {
  position: relative;
  z-index: 1;
}

.has-letterhead .content-wrapper {
  position: absolute;
  inset: var(--letterhead-safe-y) auto auto var(--letterhead-safe-x);
  width: var(--letterhead-safe-width);
  height: var(--letterhead-safe-height);
}

.has-letterhead .margin-rail-left,
.has-letterhead .margin-corner-top-right {
  display: none;
}

/* Margin Accents */
.margin-rail-left {
  position: absolute;
  top: 16mm;
  bottom: 12mm;
  inset-inline-start: 8mm;
  width: 4px;
  background: var(--brand-accent);
  border-radius: 2px;
}

.margin-corner-top-right {
  position: absolute;
  top: 8mm;
  inset-inline-start: 8mm;
  width: 24px;
  height: 24px;
  border-top: 3px solid var(--brand-accent);
  border-inline-start: 3px solid var(--brand-accent);
  opacity: 0.6;
}

/* 2. Flex Layout */
.content-wrapper {
  display: flex;
  flex-direction: column;
  flex: 1 1 100%;
  justify-content: space-between;
  height: 100%;
}

/* Header */
.contract-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  padding-bottom: 8px;
  min-height: 40px;
}

.header-left {
  display: flex;
  flex-direction: column;
}

.document-title {
  font-size: 1.35rem;
  font-weight: 700;
  letter-spacing: 1.5px;
  color: var(--brand-accent);
  text-transform: uppercase;
  margin: 0 0 4px 0;
  line-height: 1.1;
}

.company-slogan {
  font-size: 0.8rem;
  color: #64748b;
  font-style: italic;
  margin: 0;
}

.header-right {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

.company-logo {
  max-height: 38px;
  max-width: 130px;
  object-fit: contain;
}

.brand-name {
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
  letter-spacing: 0.5px;
}

.header-divider {
  height: 2px;
  background: linear-gradient(90deg, var(--brand-accent) 0%, #e2e8f0 100%);
  margin-bottom: 20px;
}

/* 3. Main Body */
.contract-body {
  flex: 1 1 auto;
  font-size: 0.92rem;
  line-height: 1.6;
  color: #334155;
}

.party-details {
  margin-bottom: 18px;
  break-inside: avoid;
  page-break-inside: avoid;
}

.party-details-title {
  margin: 0 0 8px;
  color: var(--brand-accent);
  font-size: 1rem;
}

.party-details-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.party-details-card {
  border: 1px solid #cbd5e1;
  border-radius: 5px;
  padding: 9px 10px;
  background: #f8fafc;
}

.party-details-card h3 {
  margin: 0 0 6px;
  color: #0f172a;
  font-size: 0.9rem;
}

.party-details-card dl {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 3px 8px;
  margin: 0;
  font-size: 0.76rem;
}

.party-details-card dt { font-weight: 700; }
.party-details-card dd { margin: 0; overflow-wrap: anywhere; }

/* --- HTML Prop Styling --- */
.html-contract-content :deep(h1) {
  font-size: 1.2rem;
  color: var(--brand-accent);
  margin-top: 1.1em;
  margin-bottom: 0.4em;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 4px;
  break-after: avoid;
  page-break-after: avoid;
}

.html-contract-content :deep(h2) {
  font-size: 1.05rem;
  color: #0f172a;
  margin-top: 1.1em;
  margin-bottom: 0.4em;
  break-after: avoid;
  page-break-after: avoid;
}

.html-contract-content :deep(p),
.html-contract-content :deep(li) {
  margin-bottom: 0.65em;
  text-align: justify;
}

.html-contract-content :deep(table) {
  width: 100%;
  border-collapse: collapse;
  margin: 1.2em 0;
  font-size: 0.88rem;
}

.html-contract-content :deep(th),
.html-contract-content :deep(td) {
  border: 1px solid #cbd5e1;
  padding: 6px 10px;
  text-align: start;
}

.html-contract-content :deep(th) {
  background-color: #f8fafc;
  color: var(--brand-accent);
  font-weight: 700;
}

.html-contract-content :deep(tr) {
  break-inside: avoid;
  page-break-inside: avoid;
}

/* Legacy Clause Section */
.clause-section {
  margin-bottom: 16px;
}

.clause-heading {
  font-size: 0.98rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 6px 0;
  display: flex;
  align-items: baseline;
  gap: 6px;
  break-after: avoid;
  page-break-after: avoid;
}

.clause-number {
  color: var(--brand-accent);
  font-weight: 700;
}

.clause-content p {
  margin: 0 0 8px 0;
  text-align: justify;
}

/* --- Signature Section --- */
.signature-section {
  margin-top: 40px;
  break-inside: avoid;
  page-break-inside: avoid;
}

.signature-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  align-items: end;
  gap: 56px;
}

.signature-box {
  display: grid;
  grid-template-rows: 64px 1px minmax(42px, auto);
  min-width: 0;
}

.signature-slot {
  display: flex;
  align-items: flex-end;
  height: 64px;
  padding-bottom: 5px;
}

.company-signature {
  width: 150px;
  height: 60px;
  object-fit: contain;
  object-position: left bottom;
}

.sig-line {
  height: 1px;
  width: 100%;
  background-color: #475569;
}

.sig-data {
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  min-height: 42px;
  padding-top: 10px;
}

.sig-name {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  color: #0f172a;
}

.sig-detail {
  margin: 3px 0 0;
  font-size: 0.75rem;
  color: #64748b;
}

/* 4. Footer Wrapper */
.contract-footer-wrapper {
  margin-top: auto;
  width: 100%;
  min-height: 30px;
}

.footer-divider {
  height: 1px;
  background: #e2e8f0;
  margin-top: 24px;
  margin-bottom: 10px;
}

.contract-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.75rem;
  color: #64748b;
  line-height: 1.4;
}

.footer-col {
  flex: 1;
}

.footer-message {
  text-align: start;
  font-weight: 500;
  color: #475569;
}

.footer-contact {
  text-align: center;
}

.footer-page {
  text-align: end;
}

.page-number {
  font-weight: 600;
  color: #334155;
  background: #f1f5f9;
  padding: 2px 8px;
  border-radius: 12px;
}

/* 5. Print Optimization */
@media print {

  :global(body.printing-contract-document) * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  :global(body.printing-contract-document) .contract-page-container {
    width: 210mm !important;
    max-width: 210mm !important;
    height: 297mm !important;
    min-height: 297mm !important;
    max-height: 297mm !important;
    margin: 0 auto !important;
    padding: 15mm 18mm 15mm 18mm !important;
    box-shadow: none !important;
    position: relative !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: space-between !important;
    box-sizing: border-box !important;
    overflow: visible !important;
    page-break-after: auto !important;
    break-after: auto !important;
  }

  :global(body.printing-contract-document) .content-wrapper {
    display: flex !important;
    flex-direction: column !important;
    height: 100% !important;
    justify-content: space-between !important;
    overflow: visible !important;
  }

  :global(body.printing-contract-document) .has-letterhead .content-wrapper {
    position: absolute !important;
    inset: var(--letterhead-safe-y) auto auto var(--letterhead-safe-x) !important;
    width: var(--letterhead-safe-width) !important;
    height: var(--letterhead-safe-height) !important;
  }

  :global(body.printing-contract-document) .contract-header {
    flex: 0 0 auto !important;
    flex-shrink: 0 !important;
    width: 100% !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: flex-end !important;
    padding-bottom: 8px !important;
    min-height: 40px !important;
    opacity: 1 !important;
    visibility: visible !important;
    padding-top: 10px !important;
  }

  :global(body.printing-contract-document) .header-left,
  :global(body.printing-contract-document) .header-right {
    display: flex !important;
    opacity: 1 !important;
    visibility: visible !important;
  }

  :global(body.printing-contract-document) .document-title,
  :global(body.printing-contract-document) .brand-name,
  :global(body.printing-contract-document) .company-slogan {
    display: block !important;
    opacity: 1 !important;
    visibility: visible !important;
  }

  :global(body.printing-contract-document) .header-divider {
    flex: 0 0 auto !important;
    flex-shrink: 0 !important;
    width: 100% !important;
    height: 2px !important;
    margin-bottom: 20px !important;
    opacity: 1 !important;
    visibility: visible !important;
  }

  :global(body.printing-contract-document) .contract-body {
    flex: 1 1 auto !important;
    width: 100% !important;
    overflow: hidden !important;
  }

  :global(body.printing-contract-document) .contract-footer-wrapper {
    flex: 0 0 auto !important;
    flex-shrink: 0 !important;
    width: 100% !important;
    min-height: 30px !important;
    margin-top: auto !important;
    display: block !important;
    opacity: 1 !important;
    visibility: visible !important;
  }

  :global(body.printing-contract-document) .contract-footer {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    width: 100% !important;
    opacity: 1 !important;
    visibility: visible !important;
  }
}
</style>
