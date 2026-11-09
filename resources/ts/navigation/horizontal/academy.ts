const viewAcademy = { action: 'read', subject: 'view_academy_contracts' }
const attendance = { action: 'read', subject: 'view_academy_attendance' }

export default [
  {
    title: 'common.navigation.academy',
    icon: { icon: 'tabler-school' },
    children: [
      { title: 'common.navigation.academyOverview', icon: { icon: 'tabler-layout-dashboard' }, to: 'academy-overview', ...viewAcademy },
      { title: 'common.navigation.academyClasses', icon: { icon: 'tabler-school' }, to: 'academy-classes', ...viewAcademy },
      { title: 'common.navigation.academyStudents', icon: { icon: 'tabler-users' }, to: 'academy-students', ...viewAcademy },
      { title: 'common.navigation.academyCatalog', icon: { icon: 'tabler-book-2' }, to: 'academy-catalog', ...viewAcademy },
      { title: 'common.navigation.academyAttendance', icon: { icon: 'tabler-calendar-check' }, to: 'academy-attendance', ...attendance },
      { title: 'common.navigation.academyCertificates', icon: { icon: 'tabler-certificate' }, to: 'academy-certificates', ...viewAcademy },
    ],
  },
]
