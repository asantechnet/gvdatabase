import { useApi } from './useApi'
import {
  AuthApi,
  ContractTemplateApi,
  CurrencyApi,
  DashboardApi,
  EmployeeApi,
  FileApi,
  InvoiceApi,
  NotificationApi,
  PositionApi,
  ReportApi,
  SettingApi,
  UserApi,
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
