// Export all API modules
import AuthApi from "./auth";
import CompanyApi from "./company";
import ContractApi from "./contracts";
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
  ContractApi,
  ContractTemplateApi,
  CompanyApi,
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
  ContractApi,
  ContractTemplateApi,
  CompanyApi,
  NotificationApi,
  AuditLogApi,
  DashboardApi,
  ReportApi,
  FileApi,
};
