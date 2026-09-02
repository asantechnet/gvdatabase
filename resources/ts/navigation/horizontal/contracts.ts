export default [
  {
    title: "common.navigation.contract",
    // This forces Vue to bypass the Vuexy string-resolver and render the icon directly from the icon pack
    icon: { icon: "tabler-file-text" },
    to: "contracts",
    children: [
      { title: "common.navigation.customers", icon: { icon: "tabler-users" }, to: "contracts", action: 'read', subject: 'view_contracts' },
      { title: "common.navigation.employees", icon: { icon: "tabler-user" }, to: "employee-contracts", action: 'read', subject: 'view_contracts' },
      { title: "common.navigation.companies", icon: { icon: "tabler-building" }, to: "companies", action: 'read', subject: 'view_companies' },
    ],
  },
];
