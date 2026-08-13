export default [
  {
    title: "common.navigation.setup",
    icon: { icon: "tabler-settings" },
    children: [
      {
        title: "common.navigation.users",
        to: "setup-users",
        action: 'read',
        subject: 'view_users',
      },
      {
        title: "common.navigation.roles",
        to: "setup-roles",
        action: 'read',
        subject: 'view_roles',
      },
      {
        title: "common.navigation.positions",
        to: "setup-positions",
        action: 'read',
        subject: 'view_positions',
      },
      {
        title: "common.navigation.marketers",
        to: "setup-marketers",
        action: 'read',
        subject: 'view_marketers',
      },
      {
        title: "common.navigation.offices",
        to: "setup-offices",
        action: 'read',
        subject: 'view_offices',
      },
      {
        title: "common.navigation.settings",
        to: "setup-settings",
        action: 'read',
        subject: 'view_settings',
      },
    ],
  },
];
