export default [
  {
    title: 'common.navigation.finance',
    icon: { icon: 'tabler-wallet' },
    to: 'finance',
    children: [
      {
        title: 'common.navigation.income',
        icon: { icon: 'tabler-circle-arrow-down' },
        to: 'income',
        action: 'read',
        subject: 'view_incomes',
      },
      {
        title: 'common.navigation.expenses',
        icon: { icon: 'tabler-circle-arrow-up' },
        to: 'expenses',
        action: 'read',
        subject: 'view_expenses',
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
        icon: { icon: 'tabler-building-warehouse' },
        to: 'asset',
        action: 'read',
        subject: 'view_assets',
      },
      {
        title: 'common.navigation.reports',
        icon: { icon: 'tabler-chart-donut' },
        to: 'reports',
        action: 'read',
        subject: 'view_reports',
      },
    ],
  },
]
