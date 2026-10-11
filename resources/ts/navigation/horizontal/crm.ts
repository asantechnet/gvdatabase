export default [
  {
    title: 'common.navigation.crm',
    icon: { icon: 'tabler-users-group' },
    children: [
      { title: 'common.navigation.applicants', icon: { icon: 'tabler-users' }, to: 'all-customers', action: 'read', subject: 'view_applicants' },
      { title: 'common.navigation.visitor', icon: { icon: 'tabler-user-search' }, to: 'visitor', action: 'read', subject: 'view_visitors' },
    ],
  },
]
