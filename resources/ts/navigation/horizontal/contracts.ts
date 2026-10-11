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
    ],
  },
]
