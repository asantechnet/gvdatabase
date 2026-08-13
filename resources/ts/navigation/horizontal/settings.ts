export default [
  {
    title: "common.navigation.options",
    icon: { icon: "tabler-layout-grid-add" },
    children: [
      {
        title: "common.navigation.contract",
        children: [
          {
            title: "common.navigation.policies",
            to: "contract-policies",
            action: 'read',
            subject: 'view_contract_templates',
          },
        ],
      },
      {
        title: "common.navigation.services",
        to: "options-services",
        action: 'read',
        subject: 'view_services',
      },
      {
        title: "common.navigation.invoice",
        to: "options-invoice",
        action: 'read',
        subject: 'view_invoices',
      },
      {
        title: "common.navigation.finance",
        children: [{ title: "common.navigation.currencies", to: "options-currencies", action: 'read', subject: 'view_currencies' }],
      },
      {
        title: "common.navigation.hrm",
        to: "options-hr",
        action: 'read',
        subject: 'view_employees',
      },
    ],
  },
];
