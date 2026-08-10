import { Icon } from "@iconify/vue";
import { h } from "vue";

export default [
  {
    title: "Tasks",
    icon: { icon: () => h(Icon, { icon: "solar:notes-broken" }) },
    to: "notes", // Corrected to kebab-case and property changed to 'route'
  },
];
