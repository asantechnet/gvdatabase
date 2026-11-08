export default [
  {
    title: 'common.navigation.tasks',
    icon: { icon: 'tabler-list-check' },
    to: 'tasks',
    anyPermissions: [
      'view_all_boards',
      'view_assigned_boards',
      'view_private_contracts',
      'view_scholarship_contracts',
      'view_test_offer_contracts',
      'view_academy_contracts',
      'view_other_contracts',
    ],
  },
]
