import { Icon } from "@iconify/vue";
import { h } from "vue";

export default [
  {
    title: "Visitor",
    icon: { icon: () => h(Icon, { icon: "streamline-cyber:person" }) },
    to: "visitor",
  },
];
