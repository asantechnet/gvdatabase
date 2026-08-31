<script setup lang="ts">
import { computed, ref } from 'vue';
import type { PartyDetails } from '@/utils/contractPartyDetails'

// 1. Interfaces & Props
export interface CompanyDetails {
  brandName: string;
  logo?: string;
  slogan?: string;
  address?: string;
  phone?: string;
  email?: string;
  website?: string;
  licence?: string;
  signatureUrl?: string;
  letterheadUrl?: string;
  letterheadSafeX?: number;
  letterheadSafeY?: number;
  letterheadSafeWidth?: number;
  letterheadSafeHeight?: number;
}

export interface ContractClause {
  clauseNumber?: string;
  title?: string;
  content: string | string[];
}

export interface ContractPageProps {
  documentTitle?: string;
  documentNumber: string;
  company: CompanyDetails;
  footerText?: string;
  pageNumberString?: string;
  clauses?: ContractClause[];
  htmlContent?: string;
  showSignature?: boolean;
  contracteeName?: string;
  contracteeCompanyName?: string; // Added for flexibility (e.g., "John Doe" vs "Acme Corp")
  partyDetails?: PartyDetails;
  accentColor?: string;
}

const props = withDefaults(defineProps<ContractPageProps>(), {
  documentTitle: 'SERVICE AGREEMENT',
  footerText: translate('documents.contract.defaultFooter'),
  pageNumberString: 'Page 1 of 1',
  clauses: () => [],
  htmlContent: '',
  showSignature: false,
  contracteeName: 'Client / Counterparty',
  contracteeCompanyName: '',
  partyDetails: undefined,
  accentColor: '#1e293b'
});

// 2. Template Reference
const contractRef = ref<HTMLElement | null>(null);
const letterheadSafeAreaStyle = computed(() => ({
  '--letterhead-safe-x': `${props.company.letterheadSafeX ?? 8.5}%`,
  '--letterhead-safe-y': `${props.company.letterheadSafeY ?? 15}%`,
  '--letterhead-safe-width': `${props.company.letterheadSafeWidth ?? 83}%`,
  '--letterhead-safe-height': `${props.company.letterheadSafeHeight ?? 70}%`,
}));

// 3. Helper for legacy structured array/string clauses
const formatParagraphs = (content: string | string[]) => {
  return Array.isArray(content) ? content : [content];
};

// 4. Print Controller
const printContractPage = () => {
  if (!contractRef.value) return;

  const printWindow = window.open('', '_blank', 'width=900,height=1100');
  if (!printWindow) {
    alert(translate('documents.contract_page_template.contractPageTemplate.pleaseAllowPopupsToPrintTheContractPage'));
    return;
  }

  // Extract raw CSS rules directly from loaded stylesheets to prevent network load delays in popup
  let inlineCss = '';
  try {
    Array.from(document.styleSheets).forEach(sheet => {
      try {
        if (sheet.cssRules) {
          Array.from(sheet.cssRules).forEach(rule => {
            inlineCss += rule.cssText + '\n';
          });
        }
      } catch (e) {
        if (sheet.href) {
          inlineCss += `@import url("${sheet.href}");\n`;
        }
      }
    });
  } catch (e) {
    console.warn('Could not parse styleSheets inline:', e);
  }

  const fallbackStyles = Array.from(document.querySelectorAll('style, link[rel="stylesheet"]'))
    .map(el => el.outerHTML)
    .join('\n');

  const contentHtml = contractRef.value.innerHTML;

  printWindow.document.open();
  printWindow.document.write(`
    <!DOCTYPE html>
    <html lang="${document.documentElement.lang}" dir="${document.documentElement.dir}">
      <head>
        <title>Print Contract - ${props.documentTitle}</title>
        ${fallbackStyles}
        <style>
          ${inlineCss}

          @media print {
            @page {
              size: A4 portrait;
              margin: 0 !important;
            }
            body {
              margin: 0 !important;
              padding: 0 !important;
              background: #fff !important;
              -webkit-print-color-adjust: exact !important;
              print-color-adjust: exact !important;
            }
          }
          html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background: #ffffff;
          }
        </style>
      </head>
      <body>
        <div class="contract-page-container" style="--brand-accent: ${props.accentColor}">
          ${contentHtml}
        </div>
        <script>
          window.onload = async () => {
            const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
            await Promise.all(links.map(link => {
              if (link.sheet) return Promise.resolve();
              return new Promise(resolve => {
                link.onload = resolve;
                link.onerror = resolve;
                setTimeout(resolve, 300);
              });
            }));

            if (document.fonts) {
              await document.fonts.ready;
            }

            const pages = document.querySelectorAll('.contract-page-container');
            pages.forEach(page => {
              let zoom = 1.0;
              while (page.scrollHeight > page.clientHeight && zoom > 0.80) {
                zoom -= 0.02;
                page.style.zoom = zoom;
              }
            });

            const images = Array.from(document.images);
            await Promise.all(images.map(img => {
              if (img.complete) return Promise.resolve();
              return new Promise(resolve => {
                img.onload = resolve;
                img.onerror = resolve;
                setTimeout(resolve, 300);
              });
            }));

            await new Promise(resolve =>
              requestAnimationFrame(() => requestAnimationFrame(resolve))
            );

            setTimeout(() => {
              window.focus();
              window.print();
              window.close();
            }, 300);
          };
        <\/script>
      </body>
    </html>
  `);
  printWindow.document.close();
};

defineExpose({
  print: printContractPage
});
</script>

<template>
  <div ref="contractRef" class="contract-page-container print-document" :class="{ 'persian-document': ['fa-AF', 'ps-AF'].includes($i18n.locale), 'has-letterhead': company.letterheadUrl }" :dir="$i18n.locale === 'en' ? 'ltr' : 'rtl'" :style="{ '--brand-accent': accentColor, ...letterheadSafeAreaStyle }">
    <img v-if="company.letterheadUrl" :src="company.letterheadUrl" alt="" class="letterhead-background" aria-hidden="true" />
    <!-- Sleek Margin Accents -->
    <div class="margin-rail-left"></div>
    <div class="margin-corner-top-right"></div>

    <div class="content-wrapper">
      <!-- Compact Header -->
      <header class="contract-header">
        <div class="header-left">
          <h1 class="document-title">{{ documentTitle }}</h1>
          <p>{{ documentNumber }}</p>
          <p v-if="company.slogan" class="company-slogan">{{ company.slogan }}</p>
            <p v-if="company.licence" class="company-slogan">{{ $t('documents.contract.licence', { licence: company.licence }) }}</p>
        </div>

        <div class="header-right">
          <img v-if="company.logo" :src="company.logo" :alt="company.brandName" class="company-logo" />
          <span class="brand-name">{{ company.brandName }}</span>
        </div>
      </header>
      <div class="header-divider"></div>

      <!-- Main Contract Body Area -->
      <main class="contract-body">
        <section v-if="partyDetails && (partyDetails.ourCompany.length || partyDetails.counterparty.length)" class="party-details">
          <h2 class="party-details-title">{{ $t('pages.contracts.shared.contractParties') }}</h2>
          <div class="party-details-grid">
            <div v-if="partyDetails.ourCompany.length" class="party-details-card">
              <h3>{{ company.brandName }}</h3>
              <dl>
                <template v-for="detail in partyDetails.ourCompany" :key="`company-${detail.label}`">
                  <dt>{{ detail.label }}</dt>
                  <dd>{{ detail.value }}</dd>
                </template>
              </dl>
            </div>
            <div v-if="partyDetails.counterparty.length" class="party-details-card">
              <h3>{{ contracteeName || $t('pages.contracts.allContracts.allContracts.counterparty') }}</h3>
              <dl>
                <template v-for="detail in partyDetails.counterparty" :key="`counterparty-${detail.label}`">
                  <dt>{{ detail.label }}</dt>
                  <dd>{{ detail.value }}</dd>
                </template>
              </dl>
            </div>
          </div>
        </section>

        <!-- Raw HTML Content -->
        <div v-if="htmlContent" class="html-contract-content" v-html="htmlContent"></div>

        <!-- Legacy Structured Clauses -->
        <template v-else>
          <section v-for="(clause, idx) in clauses" :key="idx" class="clause-section">
            <h2 v-if="clause.title || clause.clauseNumber" class="clause-heading">
              <span v-if="clause.clauseNumber" class="clause-number">{{ clause.clauseNumber }}.</span>
              {{ clause.title }}
            </h2>
            <div class="clause-content">
              <p v-for="(paragraph, pIdx) in formatParagraphs(clause.content)" :key="pIdx">
                {{ paragraph }}
              </p>
            </div>
          </section>
        </template>

        <div class="custom-content-slot">
          <slot></slot>
        </div>

        <!-- Signature Section -->
        <div v-if="showSignature" class="signature-section">
          <h3 class="signature-title">{{ $t('documents.contract_page_template.contractPageTemplate.inWITNESSWHEREOFThePartiesHeretoHaveExecutedThisAgreement') }}</h3>
          <div class="signature-grid">

            <!-- Company Signature Area -->
            <div class="signature-box">
              <img v-if="company.signatureUrl" :src="company.signatureUrl" alt="Authorized company signature" class="company-signature" />
              <div class="sig-line"></div>
              <p class="sig-name"><strong>{{ company.brandName }}</strong></p>
              <p class="sig-sub">{{ $t('documents.contract_page_template.contractPageTemplate.companyName') }}</p>
              <p class="sig-sub">{{ $t('common.messages.authorizedSignatureDate') }}</p>
            </div>

            <!-- Contractee Signature Area -->
            <div class="signature-box">
              <div class="sig-line"></div>
              <p class="sig-name"><strong>{{ contracteeName }}</strong></p>
              <p v-if="contracteeCompanyName" class="sig-sub">{{ contracteeCompanyName }}</p>
              <p class="sig-sub">{{ $t('documents.contract_page_template.contractPageTemplate.clientContracteeName') }}</p>
              <p class="sig-sub">{{ $t('common.messages.authorizedSignatureDate') }}</p>
            </div>

          </div>
        </div>
      </main>

      <!-- Fixed Bottom Footer -->
      <footer class="contract-footer-wrapper">
        <div class="footer-divider"></div>
        <div class="contract-footer">
          <div class="footer-col footer-message">
            <span>{{ footerText }}</span>
          </div>

          <div class="footer-col footer-contact">
            <span v-if="company.address">{{ company.address }}</span>
            <span v-if="company.address && company.phone"> {{ $t('common.messages.text') }} </span>
            <span v-if="company.phone">{{ $t('documents.contract.telephone', { phone: company.phone }) }}</span>
            <span v-if="company.email && (company.address || company.phone)"> {{ $t('common.messages.text') }} </span>
            <span v-if="company.email">{{ company.email }}</span>
          </div>

          <div class="footer-col footer-page">
            <span class="page-number">{{ pageNumberString }}</span>
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
  left: 8mm;
  width: 4px;
  background: var(--brand-accent);
  border-radius: 2px;
}

.margin-corner-top-right {
  position: absolute;
  top: 8mm;
  right: 8mm;
  width: 24px;
  height: 24px;
  border-top: 3px solid var(--brand-accent);
  border-inline-end: 3px solid var(--brand-accent);
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
  align-items: center;
  gap: 12px;
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
  margin-top: 30px;
  padding-top: 16px;
  border-top: 2px dashed #cbd5e1;
  break-inside: avoid;
  page-break-inside: avoid;
}

.signature-title {
  font-size: 0.9rem;
  color: #475569;
  margin-bottom: 65px;
  font-weight: 600;
}

.signature-grid {
  display: flex;
  justify-content: space-between;
  gap: 40px;
}

.signature-box {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.company-signature {
  width: 150px;
  height: 58px;
  object-fit: contain;
  object-position: left bottom;
  margin-bottom: 4px;
}

.sig-line {
  height: 1px;
  background-color: #0f172a;
  margin-bottom: 10px;
  width: 95%;
}

.sig-name {
  margin: 0 0 4px 0;
  font-size: 0.95rem;
  color: #0f172a;
}

.sig-sub {
  margin: 0 0 3px 0;
  font-size: 0.75rem;
  color: #64748b;
  letter-spacing: 0.3px;
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
  @page {
    size: A4 portrait;
    margin: 0 !important;
  }

  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  .contract-page-container {
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
    page-break-after: always !important;
    break-after: page !important;
  }

  .content-wrapper {
    display: flex !important;
    flex-direction: column !important;
    height: 100% !important;
    justify-content: space-between !important;
    overflow: visible !important;
  }

  .has-letterhead .content-wrapper {
    position: absolute !important;
    inset: var(--letterhead-safe-y) auto auto var(--letterhead-safe-x) !important;
    width: var(--letterhead-safe-width) !important;
    height: var(--letterhead-safe-height) !important;
  }

  .contract-header {
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

  .header-left,
  .header-right {
    display: flex !important;
    opacity: 1 !important;
    visibility: visible !important;
  }

  .document-title,
  .brand-name,
  .company-slogan {
    display: block !important;
    opacity: 1 !important;
    visibility: visible !important;
  }

  .header-divider {
    flex: 0 0 auto !important;
    flex-shrink: 0 !important;
    width: 100% !important;
    height: 2px !important;
    margin-bottom: 20px !important;
    opacity: 1 !important;
    visibility: visible !important;
  }

  .contract-body {
    flex: 1 1 auto !important;
    width: 100% !important;
    overflow: hidden !important;
  }

  .contract-footer-wrapper {
    flex: 0 0 auto !important;
    flex-shrink: 0 !important;
    width: 100% !important;
    min-height: 30px !important;
    margin-top: auto !important;
    display: block !important;
    opacity: 1 !important;
    visibility: visible !important;
  }

  .contract-footer {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    width: 100% !important;
    opacity: 1 !important;
    visibility: visible !important;
  }
}
</style>
