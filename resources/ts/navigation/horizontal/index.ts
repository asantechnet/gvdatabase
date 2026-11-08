import type { HorizontalNavItems } from "@layouts/types";
import academy from "./academy";
import applicantPortal from "./applicant-portal";
import applications from "./applications";
import contracts from "./contracts";
import crm from "./crm";
import finance from "./finance";
import home from "./home";
import hr from "./hr";
import settings from "./settings";
import setup from "./setup";
import tasks from "./tasks";

export default [
  ...home,
  ...crm,
  ...applicantPortal,
  ...contracts,
  ...academy,
  ...applications,
  ...hr,
  ...tasks,
  ...finance,
  ...settings,
  ...setup,
] as HorizontalNavItems;
