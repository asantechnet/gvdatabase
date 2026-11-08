export default [
  {
    title: 'common.navigation.contract',

    // One concept, one global link. The contract services, the CRM pages and the
    // applications pages are interconnected through the in-page navigation in
    // ErpPageHeader instead of nested menu groups, so this entry stays a single
    // destination (the default service) rather than a dropdown.
    icon: { icon: 'tabler-file-certificate' },
    to: 'private-contracts',
    action: 'read',
    subject: 'view_contracts',
  },
]
