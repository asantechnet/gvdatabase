export default [
  {
    title: 'common.navigation.options',
    icon: { icon: 'tabler-layout-grid-add' },
    children: [
      {
        title: 'common.navigation.contract',
        icon: { icon: 'tabler-file-text' },
        children: [
          {
            title: 'common.navigation.contractDocuments',
            icon: { icon: 'tabler-file-description' },
            to: 'contract-documents',
          },
          {
            title: 'common.navigation.educationalData',
            icon: { icon: 'tabler-school' },
            to: 'educational-master-data',
            action: 'read',
            subject: 'view_country',
          },
        ],
      },
      {
        title: 'common.navigation.services',
        icon: { icon: 'tabler-tool' },
        to: 'options-services',
        action: 'read',
        subject: 'view_services',
      },

      // {
      //   title: "common.navigation.invoice",
      //   icon: { icon: "tabler-file-invoice" },
      //   to: "options-invoice",
      //   action: 'read',
      //   subject: 'view_invoices',
      // },
      {
        title: 'common.navigation.finance',
        icon: { icon: 'tabler-coins' },
        children: [
          {
            title: 'common.navigation.currencies',
            icon: { icon: 'tabler-currency-dollar' },
            to: 'options-currencies',
            action: 'read',
            subject: 'view_currencies',
          },
        ],
      },

      // {
      //   title: "common.navigation.hrm",
      //   icon: { icon: "tabler-users-group" },
      //   to: "options-hr",
      //   action: 'read',
      //   subject: 'view_employees',
      // },
    ],
  },
]
