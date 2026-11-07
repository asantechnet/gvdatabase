export default [
  {
    title: 'common.navigation.finance',
    icon: { icon: 'tabler-coins' },
    to: 'finance',
    children: [
      {
        title: 'common.navigation.income',
        icon: { icon: 'tabler-arrow-down-left' },
        to: 'income',
        action: 'read',
        subject: 'view_incomes',
      },
      {
        title: 'common.navigation.expenses',
        icon: { icon: 'tabler-arrow-up-right' },
        to: 'expenses',
        action: 'read',
        subject: 'view_expenses',
      },
      {
        title: 'common.navigation.paymentRequests',
        icon: { icon: 'tabler-file-invoice' },
        to: 'payment-requests',
        action: 'read',
        subject: 'view_pending_transactions',
      },
      {
        title: 'common.navigation.commissions',
        icon: { icon: 'tabler-percentage' },
        to: 'commissions',
        action: 'read',
        subject: 'view_commissions',
      },
      {
        title: 'common.navigation.asset',
        icon: { icon: 'tabler-package' },
        to: 'asset',
        action: 'read',
        subject: 'view_assets',
      },
      {
        title: 'common.navigation.reports',
        icon: { icon: 'tabler-chart-bar' },
        to: 'reports',
        action: 'read',
        subject: 'view_reports',
      },
      {
        title: 'common.navigation.financeActivityReport',
        icon: { icon: 'tabler-cash-banknote' },
        to: 'finance-activity-report',
        action: 'read',
        subject: 'view_financial_reports',
      },
    ],
  },
]
