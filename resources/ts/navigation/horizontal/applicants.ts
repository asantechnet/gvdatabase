export default [
  {
    title: "common.navigation.applications",
    icon: { icon: "tabler-school" },
    action: "read",
    subject: "view_agency_overview",
    children: [
      {
        title: "educational.allApplications",
        icon: { icon: "tabler-users" },
        to: "applications",
        action: "read",
        subject: "view_applicants",
      },
      {
        title: "common.navigation.globalSearch",
        icon: { icon: "tabler-search" },
        to: "search",
      },
      {
        title: "common.navigation.visaReports",
        icon: { icon: "tabler-report" },
        to: "visa-reports",
        action: "read",
        subject: "view_visa_reports",
      },
    ],
  },
];
