export default [
  {
    title: 'common.navigation.setup',
    icon: { icon: 'tabler-settings' },
    children: [
      {
        title: 'common.navigation.users',
        icon: { icon: 'tabler-users' },
        to: 'setup-users',
        action: 'read',
        subject: 'view_users',
      },
      {
        title: 'common.navigation.roles',
        icon: { icon: 'tabler-shield' },
        to: 'setup-roles',
        action: 'read',
        subject: 'view_roles',
      },
      {
        title: 'common.navigation.positions',
        icon: { icon: 'tabler-briefcase' },
        to: 'setup-positions',
        action: 'read',
        subject: 'view_positions',
      },
      {
        title: 'common.navigation.offices',
        icon: { icon: 'tabler-building' },
        to: 'setup-offices',
        action: 'read',
        subject: 'view_offices',
      },
      {
        title: 'common.navigation.settings',
        icon: { icon: 'tabler-adjustments' },
        to: 'setup-settings',
        action: 'read',
        subject: 'view_settings',
      },
    ],
  },
]
