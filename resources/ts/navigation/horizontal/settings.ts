export default [
  {
    title: "Options",
    icon: { icon: "tabler-layout-grid-add" },
    children: [
      {
        title: "Contract",
        children: [
          {
            title: "Policies",
            to: "contract-policies",
          },
        ],
      },
      {
        title: "Services",
        to: "options-services",
      },
      {
        title: "Invoice",
        to: "options-invoice",
      },
      {
        title: "Finance",
        children: [{ title: "Currencies", to: "options-currencies" }],
      },
      {
        title: "HRM",
        to: "options-hr",
      },
    ],
  },
];
