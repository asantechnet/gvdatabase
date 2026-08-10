// Export all API modules
import AuthApi from "./auth";
import ContractTemplateApi from "./contractTemplate";
import CurrencyApi from "./currency";
import DashboardApi from "./dashboard";
import EmployeeApi from "./employee";
import FileApi from "./file";
import InvoiceApi from "./invoice";
import NotificationApi from "./notification";
import PositionApi from "./position";
import ReportApi from "./report";
import SettingApi from "./setting";
import UserApi from "./user";
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
  PositionApi,
  ReportApi,
  SettingApi,
  UserApi,
};

export default {
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
};
