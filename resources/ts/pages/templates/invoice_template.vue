<script setup lang="ts">
import { computed, ref } from 'vue';

// 1. Interfaces & Props
export interface InvoiceService {
  description: string;
  subDescription?: string;
  price: number;
  quantity: number;
  currency: string;
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
  notes: 'Unless otherwise agreed in writing by the parties, all invoices are payable within thirty (30) days of the invoice date.',
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

const primaryCurrency = computed(() => {
  return (props.services && props.services[0]?.currency) || '$';
});

const printInvoice = () => {
  if (!invoiceRef.value) return;

  const printWindow = window.open('', '_blank', 'width=700,height=950');
  if (!printWindow) {
    alert('Please allow popups to print the invoice.');
    return;
  }

  const styles = Array.from(document.querySelectorAll('style, link[rel="stylesheet"]'))
    .map(el => el.outerHTML)
    .join('\n');

  const invoiceHtml = invoiceRef.value.innerHTML;

  printWindow.document.open();
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <title>Print Invoice - ${props.invoiceNumber}</title>
        ${styles}
        <style>
          /* 0. A5 PAGE SPECIFICATION & MARGINS */
          @media print {
            @page {
              size: A5;
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
            padding: 20px !important;
            height: auto !important;
            overflow: visible !important;
            box-shadow: none !important;
          }

          /* 2. SECTION ISOLATION & CLEARING */
          html body .invoice-header,
          html body .metadata-row,
          html body .totals-section,
          html body .invoice-footer {
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

          html body .invoice-header {
            align-items: center !important;
            margin-bottom: 15px !important;
          }

          html body .metadata-row {
            margin-bottom: 12px !important;
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
            margin-top: 20px !important;
            align-items: flex-end !important;
          }
        </style>
      </head>
      <body>
        <div class="invoice-container">
          ${invoiceHtml}
        </div>
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
  <div ref="invoiceRef" class="invoice-container a5-layout">
    <!-- Top Header -->
    <header class="invoice-header">
      <div class="title-section">
        <h1 class="invoice-title">INVOICE</h1>
      </div>
      <div class="logo-section">
        <img v-if="sender.logo" :src="sender.logo" :alt="sender.brandName" class="brand-logo" />
        <h2 v-else class="brand-name-fallback">{{ sender.brandName }}</h2>
      </div>
    </header>

    <!-- Metadata Row 1 -->
    <section class="metadata-row primary-meta">
      <div class="meta-block">
        <span class="meta-label">INVOICE #</span>
        <span class="meta-value">{{ invoiceNumber }}</span>
      </div>
      <div class="meta-block">
        <span class="meta-label">DATE OF ISSUE</span>
        <span class="meta-value">{{ dateOfIssue }}</span>
      </div>
      <div class="meta-block text-right">
        <span class="meta-label">DUE DATE</span>
        <span class="meta-value">{{ dueDate }}</span>
      </div>
    </section>

    <!-- Metadata Row 2: Addresses -->
    <section class="metadata-row address-meta">
      <div class="address-block">
        <span class="meta-label">BILL TO</span>
        <strong class="client-name">{{ client.name }}</strong>
        <div class="address-lines">
          <p v-for="(line, idx) in client.addressLines" :key="idx">{{ line }}</p>
        </div>
      </div>
      <div class="address-block text-right sender-details">
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
          <th class="th-desc">DESCRIPTION</th>
          <th class="th-unit text-right">UNIT COST</th>
          <th class="th-qty text-right">QTY</th>
          <th class="th-amount text-right">AMOUNT</th>
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
          <td class="td-amount text-right">{{ formatCurrency(service.price * service.quantity, service.currency) }}</td>
        </tr>
      </tbody>
    </table>

    <!-- Totals Section -->
    <section class="totals-section">
      <div class="grand-total-display">
        <span class="grand-total-label">GRAND TOTAL</span>
        <span class="grand-total-value">{{ formatCurrency(grandTotal, primaryCurrency) }}</span>
      </div>

      <div class="summary-grid">
        <div class="summary-row">
          <span class="summary-label">SUBTOTAL</span>
          <span class="summary-value">{{ formatCurrency(subtotal, primaryCurrency) }}</span>
        </div>
        <div v-if="discount > 0" class="summary-row">
          <span class="summary-label">DISCOUNT</span>
          <span class="summary-value discount-val">-{{ formatCurrency(discount, primaryCurrency) }}</span>
        </div>
        <div v-if="taxRate > 0" class="summary-row">
          <span class="summary-label">(TAX RATE)</span>
          <span class="summary-value">{{ taxRate }}%</span>
        </div>
        <div v-if="taxRate > 0" class="summary-row">
          <span class="summary-label">TAX</span>
          <span class="summary-value">{{ formatCurrency(taxAmount, primaryCurrency) }}</span>
        </div>
        <div class="summary-row final-row">
          <span class="summary-label">TOTAL</span>
          <span class="summary-value">{{ formatCurrency(grandTotal, primaryCurrency) }}</span>
        </div>
      </div>
    </section>

    <!-- Footer Notes & Payment Instructions -->
    <footer class="invoice-footer">
      <div class="footer-left">
        <div v-if="notes" class="footer-block">
          <span class="meta-label">NOTES</span>
          <p class="footer-text">{{ notes }}</p>
        </div>

        <div v-if="paymentInstructions && paymentInstructions.length > 0" class="footer-block">
          <span class="meta-label">PAYMENT INSTRUCTIONS</span>
          <div class="footer-text">
            <p v-for="(line, idx) in paymentInstructions" :key="idx">{{ line }}</p>
          </div>
        </div>

        <div v-if="paymentMethod" class="footer-block">
          <span class="meta-label">PAYMENT METHOD</span>
          <p class="footer-text">{{ paymentMethod }}</p>
        </div>
      </div>

      <div class="footer-right">
        <strong class="thank-you-message">THANK YOU FOR YOUR BUSINESS!</strong>
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
  max-width: 148mm;
  margin: 0 auto;
  padding: 1.5rem;
  box-sizing: border-box;
  font-size: 12px;
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
  margin-bottom: 1.25rem;
}

.invoice-title {
  color: #16a1d7;
  font-size: 1.75rem;
  font-weight: 300;
  letter-spacing: 0.078125em;
  margin: 0;
  line-height: 1;
}

.brand-logo {
  max-height: 2rem;
  max-width: 6rem;
  object-fit: contain;
}

.brand-name-fallback {
  font-size: 0.85rem;
  font-weight: 700;
  color: #2b2b2b;
  margin: 0;
}

.metadata-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 1.25rem;
}

.primary-meta .meta-block {
  flex: 1;
}

.meta-value {
  font-size: 0.85rem;
  color: #2b2b2b;
}

.address-meta .address-block {
  flex: 1;
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
  margin-top: 0.5rem;
  margin-bottom: 1rem;
}

.services-table th {
  color: #16a1d7;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.03125em;
  border-top: 0.1rem solid #16a1d7;
  border-bottom: 0.1rem solid #16a1d7;
  padding: 0.35rem 0.25rem;
}

.services-table td {
  padding: 0.45rem 0.25rem;
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
  width: 20%;
}

.totals-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.25rem;
}

.grand-total-display {
  display: flex;
  flex-direction: column;
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
  width: 8.5rem;
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
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
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
  flex: 0 0 35%;
  text-align: right;
  padding-bottom: 0.1rem;
}

.thank-you-message {
  font-size: 0.8rem;
  font-weight: 700;
  color: #555555;
  letter-spacing: 0.03125em;
}

@media print {
  .invoice-container {
    padding: 0;
    max-width: 100%;
  }

  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
</style>
