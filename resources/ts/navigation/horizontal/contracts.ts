export default [
  {
    title: 'common.navigation.contract',

    // This forces Vue to bypass the Vuexy string-resolver and render the icon directly from the icon pack
    icon: { icon: 'tabler-file-text' },
    to: 'scholarship-contracts',
    children: [
      { title: 'educational.builtInServices.private', icon: { icon: 'tabler-user' }, to: 'private-contracts', action: 'read', subject: 'view_contracts' },
      { title: 'educational.builtInServices.scholarship', icon: { icon: 'tabler-school' }, to: 'scholarship-contracts', action: 'read', subject: 'view_contracts' },
      { title: 'educational.builtInServices.testOffer', icon: { icon: 'tabler-certificate' }, to: 'test-offer-contracts', action: 'read', subject: 'view_contracts' },
      { title: 'educational.builtInServices.academy', icon: { icon: 'tabler-book' }, to: 'academy-contracts', action: 'read', subject: 'view_contracts' },
      { title: 'common.navigation.otherContracts', icon: { icon: 'tabler-files' }, to: 'other-contracts', action: 'read', subject: 'view_contracts' },
      // The former dedicated "Applications" group is folded into Contracts, so
      // its pages remain reachable without a separate top-level entry.
      { title: 'educational.allApplications', icon: { icon: 'tabler-users' }, to: 'applications', action: 'read', subject: 'view_applicants' },
      { title: 'common.navigation.globalSearch', icon: { icon: 'tabler-search' }, to: 'search' },
      { title: 'common.navigation.visaReports', icon: { icon: 'tabler-report' }, to: 'visa-reports', action: 'read', subject: 'view_visa_reports' },
    ],
  },
]
