export default [
  {
    title: 'common.navigation.sales',
    icon: { icon: 'tabler-coin' },
    children: [
      {
        title: 'common.navigation.serviceSales',
        icon: { icon: 'tabler-receipt' },
        to: 'educational-services',
        action: 'read',
        subject: 'view_educational_services',
      },
    ],
  },
]
