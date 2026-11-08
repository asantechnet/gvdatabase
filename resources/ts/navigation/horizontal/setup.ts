export default [
  {
    title: 'common.navigation.setup',
    icon: { icon: 'tabler-settings-2' },
    children: [
      {
        title: 'common.navigation.users',
        icon: { icon: 'tabler-users-group' },
        to: 'setup-users',
        action: 'read',
        subject: 'view_users',
      },
      {
        title: 'common.navigation.roles',
        icon: { icon: 'tabler-shield-check' },
        to: 'setup-roles',
        action: 'read',
        subject: 'view_roles',
      },
      {
        title: 'common.navigation.settings',
        icon: { icon: 'tabler-adjustments-horizontal' },
        to: 'setup-settings',
        action: 'read',
        subject: 'view_settings',
      },
    ],
  },
]
