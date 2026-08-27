export default [
  {
    title: "common.navigation.options",
    icon: { icon: "tabler-layout-grid-add" },
    children: [
      {
        title: "common.navigation.contract",
        icon: { icon: "tabler-file-text" },
        children: [
          {
            title: "common.navigation.contractTemplates",
            icon: { icon: "tabler-template" },
            to: "contract-templates",
            action: 'read',
            subject: 'view_contract_templates',
          },
          {
            title: "common.navigation.policies",
            icon: { icon: "tabler-shield-check" },
            to: "contract-policies",
            action: 'read',
            subject: 'view_contract_policies',
          },
        ],
      },
      {
        title: "common.navigation.services",
        icon: { icon: "tabler-tool" },
        to: "options-services",
        action: 'read',
        subject: 'view_services',
      },
      {
        title: "common.navigation.invoice",
        icon: { icon: "tabler-file-invoice" },
        to: "options-invoice",
        action: 'read',
        subject: 'view_invoices',
      },
      {
        title: "common.navigation.finance",
        icon: { icon: "tabler-coins" },
        children: [{ title: "common.navigation.currencies", icon: { icon: "tabler-currency-dollar" }, to: "options-currencies", action: 'read', subject: 'view_currencies' }],
      },
      {
        title: "common.navigation.hrm",
        icon: { icon: "tabler-users-group" },
        to: "options-hr",
        action: 'read',
        subject: 'view_employees',
      },
    ],
  },
];
