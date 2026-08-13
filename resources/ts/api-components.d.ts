import '@vue/runtime-core'

declare module '@vue/runtime-core' {
  interface ComponentCustomProperties {
    $api: {
      AuthApi: any
      UserApi: any
      EmployeeApi: any
      PositionApi: any
      EmployeeSalaryApi: any
      InvoiceApi: any
      ExpenceApi: any
      CurrencyApi: any
      SettingApi: any
      PolicyApi: any
      ContractTemplateApi: any
      ContractApi: any
      NotificationApi: any
      DashboardApi: any
      ReportApi: any
      FileApi: any
    }
  }
}
