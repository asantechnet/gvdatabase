<script setup lang="ts">
import { computed, ref } from 'vue';

// 1. Interfaces & Props
export interface InvoiceService {
  description: string;
  subDescription?: string;
  price: number;
  quantity: number;
  currency: string;
  exchangeRate?: number;
}

export interface ClientDetails {
  name: string;
  addressLines: string[];
}

export interface SenderDetails {
  brandName: string;
  logo?: string;
  addressLines: string[];
  phone?: string;
  email?: string;
  website?: string;
  signature?: string;
}

export interface InvoiceProps {
  invoiceNumber: string;
  dateOfIssue: string;
  dueDate: string;
  client: ClientDetails;
  sender: SenderDetails;
  services: InvoiceService[];
  discount?: number;
  taxRate?: number;
  notes?: string;
  paymentMethod?: string;
  paymentInstructions?: string[];
}

const props = withDefaults(defineProps<InvoiceProps>(), {
  invoiceNumber: 'INV-000',
  dateOfIssue: '',
  dueDate: '',
  client: () => ({ name: 'Valued Client', addressLines: [] }),
  sender: () => ({ brandName: 'Our Company', addressLines: [] }),
  services: () => [],
  discount: 0,
  taxRate: 0,
  notes: translate('documents.invoice.defaultPaymentTerms'),
  paymentMethod: '',
  paymentInstructions: () => []
});

// 2. Template Reference
const invoiceRef = ref<HTMLElement | null>(null);

// 3. Computed Calculations
const subtotal = computed(() => {
  if (!props.services || !Array.isArray(props.services)) return 0;
  return props.services.reduce((acc, item) => acc + ((item.price || 0) * (item.quantity || 0)), 0);
});

const taxAmount = computed(() => {
  const taxableAmount = Math.max(0, subtotal.value - (props.discount || 0));
  return (taxableAmount * (props.taxRate || 0)) / 100;
});

const grandTotal = computed(() => {
  return Math.max(0, subtotal.value - (props.discount || 0) + taxAmount.value);
});

const formatCurrency = (amount: number | string | null | undefined, currency = '$') => {
  const numericAmount = Number(amount);

  return `${currency}${(Number.isFinite(numericAmount) ? numericAmount : 0).toFixed(2)}`;
};

const formatExchangeRate = (rate: number | undefined) => {
  const numericRate = Number(rate);
  return Number.isFinite(numericRate) && numericRate > 0 ? numericRate.toFixed(4) : '—';
};

const primaryCurrency = computed(() => {
  return (props.services && props.services[0]?.currency) || '$';
});

const printInvoice = () => {
  if (!invoiceRef.value) return;

  const printWindow = window.open('', '_blank', 'width=700,height=950');
  if (!printWindow) {
    alert(translate('documents.invoice_template.invoiceTemplate.pleaseAllowPopupsToPrintTheInvoice'));
    return;
  }

  const styles = Array.from(document.querySelectorAll('style, link[rel="stylesheet"]'))
    .map(el => el.outerHTML)
    .join('\n');

  const invoiceHtml = invoiceRef.value.outerHTML;
  const documentDirection = invoiceRef.value.dir || 'ltr';

  printWindow.document.open();
  printWindow.document.write(`
    <!DOCTYPE html>
    <html lang="${document.documentElement.lang}" dir="${documentDirection}">
      <head>
        <title>Print Invoice - ${props.invoiceNumber}</title>
        ${styles}
        <style>
          /* 0. A5 PAGE SPECIFICATION & MARGINS */
          @media print {
            @page {
              size: A4;
              margin: 10mm;
            }
          }
          
          /* 1. COMPACT A5 ROOT STYLING */
          html, body {
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
            color: #2b2b2b !important;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif !important;
            font-size: 12px !important;
            height: auto !important;
            min-height: 0 !important;
            max-height: none !important;
            overflow: visible !important;
            position: static !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
          }

          html body .invoice-container,
          html body .invoice-container * {
            visibility: visible !important;
            opacity: 1 !important;
            box-sizing: border-box !important;
          }

          html body .invoice-container {
            display: block !important;
            position: static !important;
            width: 100% !important;
            max-width: none !important;
            margin: 0 !important;
            padding: 14mm !important;
            height: auto !important;
            overflow: visible !important;
            box-shadow: none !important;
          }

          html body .invoice-container.persian-document {
            font-family: Vazirmatn, 'Public Sans', sans-serif !important;
          }

          html[dir="rtl"] body .invoice-container {
            direction: rtl !important;
            text-align: right !important;
          }

          /* 2. SECTION ISOLATION & CLEARING */
          html body .invoice-header {
            display: flex !important;
            flex-direction: row !important;
            justify-content: space-between !important;
            align-items: flex-start !important;
            position: static !important;
            width: 100% !important;
            height: auto !important;
            max-height: none !important;
            overflow: visible !important;
            clear: both !important;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
          }

          html[dir="rtl"] body .text-right,
          html[dir="rtl"] body .footer-right {
            text-align: left !important;
          }

          html body .invoice-header {
            align-items: center !important;
            margin-bottom: 15px !important;
          }

          html body .metadata-row {
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 1rem !important;
            margin-bottom: 12px !important;
          }

          html body .address-meta {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
          }

          html body .totals-section {
            display: grid !important;
            grid-template-columns: 1fr 13rem !important;
            gap: 2rem !important;
          }

          /* 3. TABLE LAYOUT STABILITY */
          html body .services-table {
            display: table !important;
            width: 100% !important;
            border-collapse: collapse !important;
            margin-top: 10px !important;
            margin-bottom: 15px !important;
            position: static !important;
            height: auto !important;
            clear: both !important;
          }
          html body .services-table thead { display: table-header-group !important; }
          html body .services-table tbody { display: table-row-group !important; }
          html body .services-table tr { 
            display: table-row !important;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
          }
          html body .services-table th, 
          html body .services-table td { 
            display: table-cell !important;
            position: static !important;
            height: auto !important;
          }

          /* 4. TOTALS & FOOTER HIERARCHY */
          html body .grand-total-display,
          html body .summary-grid,
          html body .address-lines {
            display: flex !important;
            flex-direction: column !important;
            position: static !important;
            height: auto !important;
          }
          
          html body .summary-row {
            display: flex !important;
            flex-direction: row !important;
            justify-content: space-between !important;
            position: static !important;
            width: 100% !important;
          }

          html body .invoice-footer {
            display: grid !important;
            grid-template-columns: minmax(0, 1fr) 10rem !important;
            gap: 2rem !important;
            margin-top: 20px !important;
            align-items: flex-end !important;
          }
        </style>
      </head>
      <body>
        ${invoiceHtml}
        <script>
          window.onload = async () => {
            const images = Array.from(document.images);
            await Promise.all(images.map(img => {
              if (img.complete) return Promise.resolve();
              return new Promise(resolve => {
                img.onload = resolve;
                img.onerror = resolve;
              });
            }));

            if (document.fonts) {
              await document.fonts.ready;
            }

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
  print: printInvoice
});
</script>

<template>
  <div ref="invoiceRef" class="invoice-container a5-layout print-document" :class="{ 'persian-document': ['fa-AF', 'ps-AF'].includes($i18n.locale) }" :dir="$i18n.locale === 'en' ? 'ltr' : 'rtl'">
    <!-- Top Header -->
    <header class="invoice-header">
      <div class="title-section">
        <span class="invoice-kicker">{{ $t('documents.invoice_template.invoiceTemplate.invoice2') }}</span>
        <h1 class="invoice-title">{{ $t('documents.invoice_template.invoiceTemplate.invoice') }}</h1>
        <span class="invoice-number">{{ invoiceNumber }}</span>
      </div>
      <div class="logo-section">
        <img v-if="sender.logo" :src="sender.logo" :alt="sender.brandName" class="brand-logo" />
        <div v-else class="brand-mark">{{ sender.brandName.slice(0, 1) }}</div>
        <div class="brand-details">
          <h2 class="brand-name-fallback">{{ sender.brandName }}</h2>
          <span v-if="sender.phone || sender.email" class="brand-contact"><bdi>{{ sender.phone }}</bdi><template v-if="sender.phone && sender.email"> · </template><bdi>{{ sender.email }}</bdi></span>
        </div>
      </div>
    </header>

    <!-- Metadata Row 1 -->
    <section class="metadata-row primary-meta">
      <div class="meta-block">
        <span class="meta-label">{{ $t('documents.invoice_template.invoiceTemplate.invoice2') }}</span>
        <span class="meta-value">{{ invoiceNumber }}</span>
      </div>
      <div class="meta-block">
        <span class="meta-label">{{ $t('documents.invoice_template.invoiceTemplate.dateOFISSUE') }}</span>
        <span class="meta-value">{{ dateOfIssue }}</span>
      </div>
      <div class="meta-block text-right">
        <span class="meta-label">{{ $t('documents.invoice_template.invoiceTemplate.dueDATE') }}</span>
        <span class="meta-value">{{ dueDate }}</span>
      </div>
    </section>

    <!-- Metadata Row 2: Addresses -->
    <section class="metadata-row address-meta">
      <div class="address-block">
        <span class="meta-label">{{ $t('documents.invoice_template.invoiceTemplate.billTO') }}</span>
        <strong class="client-name">{{ client.name }}</strong>
        <div class="address-lines">
          <p v-for="(line, idx) in client.addressLines" :key="idx">{{ line }}</p>
        </div>
      </div>
      <div class="address-block text-right sender-details">
        <span class="meta-label">{{ $t('documents.invoice_template.invoiceTemplate.from') }}</span>
        <strong class="sender-name">{{ sender.brandName }}</strong>
        <div class="address-lines">
          <p v-for="(line, idx) in sender.addressLines" :key="idx">{{ line }}</p>
          <p v-if="sender.phone">{{ sender.phone }}</p>
          <p v-if="sender.email">{{ sender.email }}</p>
          <p v-if="sender.website">{{ sender.website }}</p>
        </div>
      </div>
    </section>

    <!-- Services Table -->
    <table class="services-table">
      <thead>
        <tr>
          <th class="th-desc">{{ $t('common.fields.description') }}</th>
          <th class="th-unit text-right">{{ $t('documents.invoice_template.invoiceTemplate.unitCOST') }}</th>
          <th class="th-qty text-right">{{ $t('documents.invoice_template.invoiceTemplate.qty') }}</th>
          <th class="th-rate text-right">{{ $t('documents.invoice_template.invoiceTemplate.currencyRate') }}</th>
          <th class="th-amount text-right">{{ $t('common.fields.amount') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(service, idx) in services" :key="idx">
          <td class="td-desc">
            <div class="service-title">{{ service.description }}</div>
            <div v-if="service.subDescription" class="service-sub">{{ service.subDescription }}</div>
          </td>
          <td class="td-unit text-right">{{ formatCurrency(service.price, service.currency) }}</td>
          <td class="td-qty text-right">{{ service.quantity }}</td>
          <td class="td-rate text-right">{{ formatExchangeRate(service.exchangeRate) }}</td>
          <td class="td-amount text-right">{{ formatCurrency(service.price * service.quantity, service.currency) }}</td>
        </tr>
      </tbody>
    </table>

    <!-- Totals Section -->
    <section class="totals-section">
      <div class="grand-total-display">
        <span class="grand-total-label">{{ $t('documents.invoice_template.invoiceTemplate.grandTOTAL') }}</span>
        <span class="grand-total-value">{{ formatCurrency(grandTotal, primaryCurrency) }}</span>
      </div>

      <div class="summary-grid">
        <div class="summary-row">
          <span class="summary-label">{{ $t('documents.invoice_template.invoiceTemplate.subtotal') }}</span>
          <span class="summary-value">{{ formatCurrency(subtotal, primaryCurrency) }}</span>
        </div>
        <div v-if="discount > 0" class="summary-row">
          <span class="summary-label">{{ $t('documents.invoice_template.invoiceTemplate.discount') }}</span>
          <span class="summary-value discount-val">-{{ formatCurrency(discount, primaryCurrency) }}</span>
        </div>
        <div v-if="taxRate > 0" class="summary-row">
          <span class="summary-label">{{ $t('documents.invoice_template.invoiceTemplate.taxRATE') }}</span>
          <span class="summary-value">{{ taxRate }}%</span>
        </div>
        <div v-if="taxRate > 0" class="summary-row">
          <span class="summary-label">{{ $t('documents.invoice_template.invoiceTemplate.tax') }}</span>
          <span class="summary-value">{{ formatCurrency(taxAmount, primaryCurrency) }}</span>
        </div>
        <div class="summary-row final-row">
          <span class="summary-label">{{ $t('documents.invoice_template.invoiceTemplate.total') }}</span>
          <span class="summary-value">{{ formatCurrency(grandTotal, primaryCurrency) }}</span>
        </div>
      </div>
    </section>

    <!-- Footer Notes & Payment Instructions -->
    <footer class="invoice-footer">
      <div class="footer-left">
        <div v-if="notes" class="footer-block">
          <span class="meta-label">{{ $t('common.fields.notes') }}</span>
          <p class="footer-text">{{ notes }}</p>
        </div>

        <div v-if="paymentInstructions && paymentInstructions.length > 0" class="footer-block">
          <span class="meta-label">{{ $t('documents.invoice_template.invoiceTemplate.paymentINSTRUCTIONS') }}</span>
          <div class="footer-text">
            <p v-for="(line, idx) in paymentInstructions" :key="idx">{{ line }}</p>
          </div>
        </div>

        <div v-if="paymentMethod" class="footer-block">
          <span class="meta-label">{{ $t('documents.invoice_template.invoiceTemplate.paymentMETHOD') }}</span>
          <p class="footer-text">{{ paymentMethod }}</p>
        </div>
      </div>

      <div class="footer-right">
        <div class="company-signature">
          <img v-if="sender.signature" :src="sender.signature" :alt="$t('documents.invoice_template.invoiceTemplate.companySignature')" />
          <div v-else class="signature-placeholder"></div>
          <span>{{ $t('documents.invoice_template.invoiceTemplate.companySignature') }}</span>
        </div>
        <strong class="thank-you-message">{{ $t('documents.invoice_template.invoiceTemplate.thankYOUFORYOURBUSINESS') }}</strong>
      </div>
    </footer>
  </div>
</template>

<style scoped>
.invoice-container {
  font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
  color: #2b2b2b;
  background: #ffffff;
  width: 100%;
  max-width: 210mm;
  margin: 0 auto;
  min-height: 297mm;
  padding: 16mm;
  box-sizing: border-box;
  font-size: 13px;
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
}

.invoice-container.persian-document {
  font-family: Vazirmatn, 'Public Sans', sans-serif;
}

.meta-label {
  display: block;
  color: #16a1d7;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.03125em;
  margin-bottom: 0.25rem;
  text-transform: uppercase;
}

.text-right {
  text-align: right;
}

.invoice-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.1rem 1.25rem;
  margin-bottom: 1.1rem;
  border-bottom: 4px solid #16a1d7;
  background: linear-gradient(135deg, #f7fcff 0%, #ffffff 68%);
}

.invoice-title {
  color: #16a1d7;
  font-size: 2.3rem;
  font-weight: 700;
  letter-spacing: 0.078125em;
  margin: 0;
  line-height: 1;
}

.invoice-kicker,
.invoice-number {
  display: block;
  color: #5d7480;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.invoice-number {
  margin-top: 0.25rem;
  color: #2b2b2b;
  font-size: 0.9rem;
  letter-spacing: 0.02em;
}

.logo-section {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.brand-logo {
  max-height: 3.25rem;
  max-width: 9rem;
  object-fit: contain;
}

.brand-name-fallback {
  font-size: 0.85rem;
  font-weight: 700;
  color: #2b2b2b;
  margin: 0;
}

.brand-mark {
  display: grid;
  place-items: center;
  width: 3rem;
  height: 3rem;
  border-radius: 50%;
  background: #16a1d7;
  color: #ffffff;
  font-size: 1.3rem;
  font-weight: 800;
}

.brand-details {
  display: flex;
  flex-direction: column;
}

.brand-contact {
  max-width: 14rem;
  color: #5d7480;
  font-size: 0.72rem;
  text-align: start;
}

.metadata-row {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.primary-meta .meta-block {
  padding: 0.65rem 0.75rem;
  border-inline-start: 3px solid #d6eef8;
  background: #f8fcfe;
}

.meta-value {
  font-size: 0.95rem;
  font-weight: 600;
  color: #2b2b2b;
}

.address-meta .address-block {
  padding: 0.9rem 1rem;
  border: 1px solid #dbe7ec;
  background: #ffffff;
}

.address-meta {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.client-name,
.sender-name {
  display: block;
  font-size: 0.95rem;
  font-weight: 600;
  margin-bottom: 0.2rem;
}

.sender-name {
  font-size: 1.05rem;
  font-weight: 400;
  letter-spacing: 0.03125em;
}

.address-lines p {
  margin: 0.1rem 0;
  font-size: 0.8rem;
  color: #555555;
  line-height: 1.35;
}

.services-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 1.25rem;
  margin-bottom: 1.5rem;
}

.services-table th {
  color: #16a1d7;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.03125em;
  border-top: 0.1rem solid #16a1d7;
  border-bottom: 0.1rem solid #16a1d7;
  padding: 0.65rem 0.5rem;
}

.services-table td {
  padding: 0.7rem 0.5rem;
  border-bottom: 0.05rem solid #e6e6e6;
  vertical-align: top;
  font-size: 0.82rem;
}

.service-title {
  font-weight: 600;
  color: #2b2b2b;
  margin-bottom: 0.1rem;
}

.service-sub {
  font-size: 0.75rem;
  color: #888888;
}

.th-desc,
.td-desc {
  width: 50%;
}

.th-unit,
.td-unit {
  width: 18%;
}

.th-qty,
.td-qty {
  width: 12%;
}

.th-amount,
.td-amount {
  width: 16%;
}

.th-rate,
.td-rate {
  width: 14%;
}

.totals-section {
  display: grid;
  grid-template-columns: 1fr 13rem;
  gap: 2rem;
  align-items: flex-start;
  margin-bottom: 1.25rem;
}

.grand-total-display {
  display: flex;
  flex-direction: column;
  align-self: end;
  padding: 1rem 1.25rem;
  border-inline-start: 5px solid #16a1d7;
  background: #f1fbff;
}

.grand-total-label {
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.0625em;
  color: #555555;
  margin-bottom: 0.2rem;
}

.grand-total-value {
  font-size: 1.5rem;
  font-weight: 400;
  color: #16a1d7;
  line-height: 1;
}

.summary-grid {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.82rem;
}

.summary-label {
  font-weight: 700;
  color: #2b2b2b;
}

.summary-value {
  color: #555555;
}

.discount-val {
  color: #16a1d7;
}

.final-row {
  margin-top: 0.15rem;
  padding-top: 0.35rem;
  font-weight: 700;
  border-top: 0.05rem solid #2b2b2b;
}

.final-row .summary-value {
  color: #2b2b2b;
  font-weight: 700;
}

.invoice-footer {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 10rem;
  gap: 2rem;
  align-items: end;
  margin-top: auto;
  padding-top: 1rem;
}

.footer-left {
  flex: 0 0 65%;
}

.footer-block {
  margin-bottom: 1rem;
}

.footer-block:last-child {
  margin-bottom: 0;
}

.footer-text {
  font-size: 0.75rem;
  color: #555555;
  line-height: 1.4;
  margin: 0;
}

.footer-text p {
  margin: 0.1rem 0;
}

.footer-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  text-align: right;
  padding-bottom: 0.1rem;
}

.company-signature {
  display: flex;
  flex-direction: column;
  width: 9rem;
  align-items: stretch;
  text-align: center;
  margin-bottom: 0.75rem;
}

.company-signature img {
  max-width: 6rem;
  max-height: 2.5rem;
  object-fit: contain;
  margin-bottom: 0.2rem;
}

.signature-placeholder {
  width: 100%;
  height: 2rem;
  border-bottom: 0.05rem solid #555555;
}

.company-signature span {
  color: #555555;
  font-size: 0.7rem;
  font-weight: 700;
}

.thank-you-message {
  display: block;
  width: 9rem;
  margin-top: 1rem;
  text-align: center;
  font-size: 0.8rem;
  font-weight: 700;
  color: #555555;
  letter-spacing: 0.03125em;
}

[dir='rtl'] .text-right,
[dir='rtl'] .footer-right {
  text-align: left;
}

[dir='rtl'] .company-signature {
  align-items: stretch;
}

[dir='rtl'] .footer-right {
  align-items: flex-start;
}

@media print {
  .invoice-container {
    width: 100%;
    min-height: 0;
    padding: 0;
    max-width: 100%;
    box-shadow: none;
  }

  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
</style>
