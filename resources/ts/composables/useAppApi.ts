import { useApi } from './useApi'
import {
  AuthApi,
  UserApi,
  EmployeeApi,
  PositionApi,
  InvoiceApi,
  CurrencyApi,
  SettingApi,
  ContractTemplateApi,
  NotificationApi,
  DashboardApi,
  ReportApi,
  FileApi,
} from '@/api'

export function useAppApi() {
  const api = useApi()

  return {
    api, // Include the base API methods
    auth: AuthApi,
    users: UserApi,
    employees: EmployeeApi,
    positions: PositionApi,
    invoices: InvoiceApi,
    currencies: CurrencyApi,
    settings: SettingApi,
    contractTemplates: ContractTemplateApi,
    notifications: NotificationApi,
    dashboard: DashboardApi,
    reports: ReportApi,
    files: FileApi,
  }
}

export default useAppApi
