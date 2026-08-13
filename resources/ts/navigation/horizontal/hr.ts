export default [
  { title: 'Paid-off days', to: 'paid-time-offs', icon: { icon: 'tabler-beach' }, action: 'read', subject: 'view_paid_time_off' },
  {
    title: "common.navigation.hr",
    icon: { icon: "tabler-users-group" },
    to: "hr",
    children: [
      {
        title: "common.navigation.staffList",
        to: "staff-list",
        action: 'read',
        subject: 'view_employees',
      },
      {
        title: "common.navigation.timesheet",
        to: "timesheet",
        action: 'read',
        subject: 'view_attendance',
      },
      {
        title: "common.navigation.staffSalary",
        to: "staff-salary",
        action: 'read',
        subject: 'view_salaries',
      },
      {
        title: "common.navigation.bonusList",
        to: "bonuslist",
        action: 'read',
        subject: 'view_bonuses',
      },
    ],
  },
];
