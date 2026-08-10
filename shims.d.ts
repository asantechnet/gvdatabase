declare module "*.vue" {
  import type { DefineComponent } from "vue";

  const component: DefineComponent<{}, {}, any>;
  export default component;
}

declare module "vue-prism-component" {
  import { ComponentOptions } from "vue";
  const component: ComponentOptions;
  export default component;
}
declare module "vue-shepherd";

// Global API type declaration
declare module "@vue/runtime-core" {
  interface ComponentCustomProperties {
    $api: {
      AuthApi: any;
      UserApi: any;
      EmployeeApi: any;
      PositionApi: any;
      EmployeeSalaryApi: any;
      InvoiceApi: any;
      ExpenceApi: any;
      CurrencyApi: any;
      SettingApi: any;
      PolicyApi: any;
      ContractTemplateApi: any;
      ContractApi: any;
      NotificationApi: any;
      DashboardApi: any;
      ReportApi: any;
      FileApi: any;
    };
  }
}
