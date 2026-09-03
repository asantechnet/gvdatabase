export default [
  {
    title: "common.navigation.hr",
    icon: { icon: "tabler-users-group" },
    to: "hr",
    children: [
      {
        title: "common.navigation.staffList",
        icon: { icon: "tabler-users" },
        to: "staff-list",
        action: 'read',
        subject: 'view_employees',
      },
      {
        title: "common.navigation.timesheet",
        icon: { icon: "tabler-clock" },
        to: "timesheet",
        action: 'read',
        subject: 'view_attendance',
      },
      {
        title: 'pages.paidTimeOff.title',
        to: 'leaves',
        icon: { icon: 'tabler-beach' },
        action: 'read',
        subject: 'view_paid_time_off',
      },
      {
        title: "common.navigation.staffSalary",
        icon: { icon: "tabler-wallet" },
        to: "staff-salary",
        action: 'read',
        subject: 'view_salaries',
      },
      {
        title: "common.navigation.commissions",
        icon: { icon: "tabler-percentage" },
        to: "commissions",
        action: 'read',
        subject: 'view_commissions',
      },
      {
        title: "common.navigation.bonusList",
        icon: { icon: "tabler-gift" },
        to: "bonuslist",
        action: 'read',
        subject: 'view_bonuses',
      },
    ],
  },
];
