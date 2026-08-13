// Export all API modules
import AuthApi from "./auth";
import ContractTemplateApi from "./contractTemplate";
import CurrencyApi from "./currency";
import DashboardApi from "./dashboard";
import EmployeeApi from "./employee";
import FileApi from "./file";
import InvoiceApi from "./invoice";
import NotificationApi from "./notification";
import AuditLogApi from "./auditLog";
import PositionApi from "./position";
import ReportApi from "./report";
import SettingApi from "./setting";
import UserApi from "./user";
import PaidTimeOffApi from './paidTimeOff'
export type {
  ApiResponse as SettingApiResponse,
  Setting,
  SettingForm,
} from "./setting";

export {
  AuthApi,
  ContractTemplateApi,
  CurrencyApi,
  DashboardApi,
  EmployeeApi,
  FileApi,
  InvoiceApi,
  NotificationApi,
  AuditLogApi,
  PositionApi,
  ReportApi,
  SettingApi,
  UserApi,
  PaidTimeOffApi,
};

export default {
  AuthApi,
  UserApi,
  PaidTimeOffApi,
  EmployeeApi,
  PositionApi,
  InvoiceApi,
  CurrencyApi,
  SettingApi,
  ContractTemplateApi,
  NotificationApi,
  AuditLogApi,
  DashboardApi,
  ReportApi,
  FileApi,
};
