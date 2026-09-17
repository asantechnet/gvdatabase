export default [
  {
    title: 'Applicants',
    icon: { icon: 'tabler-school' },
    children: [
      {
        title: 'All applicants',
        icon: { icon: 'tabler-users' },
        to: 'applicants',
        action: 'read',
        subject: 'view_applicants',
      },
      {
        title: 'Pipeline board',
        icon: { icon: 'tabler-kanban' },
        to: 'applicant-pipeline',
        action: 'read',
        subject: 'view_applicants',
      },
      {
        title: 'Search',
        icon: { icon: 'tabler-search' },
        to: 'search',
      },
    ],
  },
]
