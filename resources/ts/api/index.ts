// Export all API modules
import AuthApi from './auth'
import ContractApi from './contracts'
import ContractTemplateApi from './contractTemplate'
import CurrencyApi from './currency'
import DashboardApi from './dashboard'
import EmployeeApi from './employee'
import FileApi from './file'
import NotificationApi from './notification'
import AuditLogApi from './auditLog'
import PositionApi from './position'
import ReportApi from './report'
import SettingApi from './setting'
import UserApi from './user'
import LeaveRequestApi from './leaveRequests'

export type {
  ApiResponse as SettingApiResponse,
  Setting,
  SettingForm,
} from './setting'

export {
  AuthApi,
  ContractApi,
  ContractTemplateApi,
  CurrencyApi,
  DashboardApi,
  EmployeeApi,
  FileApi,
  NotificationApi,
  AuditLogApi,
  PositionApi,
  ReportApi,
  SettingApi,
  UserApi,
  LeaveRequestApi,
}

export default {
  AuthApi,
  UserApi,
  LeaveRequestApi,
  EmployeeApi,
  PositionApi,
  CurrencyApi,
  SettingApi,
  ContractApi,
  ContractTemplateApi,
  NotificationApi,
  AuditLogApi,
  DashboardApi,
  ReportApi,
  FileApi,
}
