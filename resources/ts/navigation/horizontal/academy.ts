const viewAcademy = { action: 'read', subject: 'view_academy_contracts' }

export default [
  {
    title: 'common.navigation.academy',
    icon: { icon: 'tabler-school' },
    // Academy is one application-navigation destination. Its in-context tabs
    // live in the Academy page header, so entering it always starts at Classes.
    to: 'academy-classes',
    ...viewAcademy,
  },
]
