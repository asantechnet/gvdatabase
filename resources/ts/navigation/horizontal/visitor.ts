import { Icon } from "@iconify/vue";
import { h } from "vue";

export default [
  {
    title: "common.navigation.visitor",
    icon: { icon: () => h(Icon, { icon: "streamline-cyber:person" }) },
    to: "visitor",
    action: 'read',
    subject: 'view_visitors',
  },
];
