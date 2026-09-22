export default [
  {
    title: 'common.navigation.applicants',
    icon: { icon: 'tabler-school' },
    children: [
      {
        title: 'common.navigation.allApplicants',
        icon: { icon: 'tabler-users' },
        to: 'applicants',
        action: 'read',
        subject: 'view_applicants',
      },
      {
        title: 'common.navigation.pipelineBoard',
        icon: { icon: 'tabler-layout-kanban' },
        to: 'applicant-pipeline',
        action: 'read',
        subject: 'view_applicants',
      },
      {
        title: 'common.navigation.globalSearch',
        icon: { icon: 'tabler-search' },
        to: 'search',
      },
      {
        title: 'common.navigation.agencyOverview',
        icon: { icon: 'tabler-dashboard' },
        to: 'agency-overview',
        action: 'read',
        subject: 'view_dashboard',
      },
      {
        title: 'common.navigation.visaReports',
        icon: { icon: 'tabler-report' },
        to: 'visa-reports',
        action: 'read',
        subject: 'view_reports',
      },
    ],
  },
]
