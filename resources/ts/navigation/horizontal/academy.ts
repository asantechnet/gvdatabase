export default [
  {
    title: 'common.navigation.academy',
    icon: { icon: 'tabler-school' },
    action: 'read',
    subject: 'view_academy_contracts',
    children: [
      { title: 'common.navigation.academyOverview', icon: { icon: 'tabler-layout-dashboard' }, to: 'academy-overview' },
      { title: 'common.navigation.academyClasses', icon: { icon: 'tabler-school' }, to: 'academy-classes' },
      { title: 'common.navigation.academyStudents', icon: { icon: 'tabler-users' }, to: 'academy-students' },
      { title: 'common.navigation.academyCatalog', icon: { icon: 'tabler-book-2' }, to: 'academy-catalog' },
      { title: 'common.navigation.academyAttendance', icon: { icon: 'tabler-calendar-check' }, to: 'academy-attendance' },
      { title: 'common.navigation.academyCertificates', icon: { icon: 'tabler-certificate' }, to: 'academy-certificates' },
      { title: 'common.navigation.academyContracts', icon: { icon: 'tabler-file-text' }, to: 'academy-contracts' },
    ],
  },
]
