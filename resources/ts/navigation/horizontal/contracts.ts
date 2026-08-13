export default [
  {
    title: "common.navigation.contract",
    // This forces Vue to bypass the Vuexy string-resolver and render the icon directly from the icon pack
    icon: { icon: "tabler-file-text" },
    to: "contracts",
    children: [
      { title: "common.navigation.allContracts", to: "contracts", action: 'read', subject: 'view_contracts' },
      { title: "common.navigation.allCustomers", to: "all-customers", action: 'read', subject: 'view_customers' },
      { title: "common.navigation.cancelledContracts", to: "cancelled-contracts", action: 'read', subject: 'view_contracts' },
    ],
  },
];
