export default [
  {
    title: 'common.navigation.applications',

    // One concept, one global link. Applications, Global Search and Visa Reports
    // are three pages of the same concept, so they are interconnected through the
    // in-page navigation in ErpPageHeader rather than a nested menu group.
    icon: { icon: 'tabler-files' },
    to: 'applications',
    action: 'read',
    subject: 'view_applicants',
  },
]
