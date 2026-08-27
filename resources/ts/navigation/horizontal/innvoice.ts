export default [
  {
    title: "common.navigation.invoice",
    icon: { icon: "tabler-invoice" },
    to: "innvoice",
    children: [
      {
        title: 'common.navigation.invoiceList',
        icon: { icon: 'tabler-file-invoice' },
        to: 'innvoice',
        action: 'read',
        subject: 'view_invoices',
      },
      {
        title: 'common.navigation.paymentDetails',
        icon: { icon: 'tabler-credit-card' },
        to: 'payment-details',
        action: 'read',
        subject: 'view_payment_details',
      },
    ],
  },
];
