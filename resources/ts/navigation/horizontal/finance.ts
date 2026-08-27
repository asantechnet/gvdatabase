export default [
  {
    title: "common.navigation.finance",
    icon: { icon: "tabler-coins" },
    to: "finance",
    children: [
      {
        title: "common.navigation.income",
        icon: { icon: "tabler-arrow-down-left" },
        to: "income",
        action: 'read',
        subject: 'view_incomes',
      },
      {
        title: "common.navigation.expenses",
        icon: { icon: "tabler-arrow-up-right" },
        to: "expenses",
        action: 'read',
        subject: 'view_expenses',
      },
      {
        title: "common.navigation.stock",
        icon: { icon: "tabler-package" },
        to: "stock",
        action: 'read',
        subject: 'view_stocks',
      },
      {
        title: "common.navigation.reports",
        icon: { icon: "tabler-chart-bar" },
        to: "reports",
        action: 'read',
        subject: 'view_reports',
      },
    ],
  },
];
