export default [
  {
    title: 'common.navigation.crm',

    // One concept, one global link: CRM lands on its Visitors page, and the CRM
    // in-page navigation (Visitors / Applicants) reaches the rest of the concept.
    icon: { icon: 'tabler-address-book' },
    to: 'visitor',
    action: 'read',
    subject: 'view_visitors',
  },
]
