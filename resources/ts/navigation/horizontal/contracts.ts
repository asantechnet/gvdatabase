export default [
  {
    title: "Contract",
    // This forces Vue to bypass the Vuexy string-resolver and render the icon directly from the icon pack
    icon: { icon: "tabler-file-text" },
    to: "contracts",
    children: [
      { title: "All Contracts", to: "contracts" },
      { title: "All Customers", to: "all-customers" },
      { title: "Cancelled Contracts", to: "cancelled-contracts" },
    ],
  },
];
