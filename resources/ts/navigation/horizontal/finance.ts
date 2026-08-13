export default [
  {
    title: "common.navigation.finance",
    icon: { icon: "tabler-coins" },
    to: "finance",
    children: [
      {
        title: "common.navigation.income",
        to: "income",
        action: 'read',
        subject: 'view_incomes',
      },
      {
        title: "common.navigation.expenses",
        to: "expenses",
        action: 'read',
        subject: 'view_expenses',
      },
      {
        title: "common.navigation.stock",
        to: "stock",
        action: 'read',
        subject: 'view_stocks',
      },
      {
        title: "common.navigation.reports",
        to: "reports",
        action: 'read',
        subject: 'view_reports',
      },
    ],
  },
];
