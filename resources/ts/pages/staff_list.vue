<script setup lang="ts">
import { formatActiveMoney, translate } from "@/utils/localization";
import { computed, onMounted, ref, watch } from "vue";
import { VDialog } from "vuetify/components";
import { useRouter } from "vue-router";
import { useToast } from "vue-toastification";
import EmployeeApi, {
  type CreateEmployeeData,
  type Employee,
  type EmployeeAccount,
  type EmployeeParams,
  type UpdateEmployeeData,
} from "@/api/employee";
import PositionApi, { type Position } from "@/api/position";
import CurrencyApi, { type Currency } from "@/api/currency";
import OfficeApi, { type Office } from "@/api/office";
import DepartmentApi, { type Department } from "@/api/department";
import LeaveRequestApi, { type LeaveRequest } from "@/api/leaveRequests";
import { apiErrorMessage, apiFieldErrors } from "@/utils/apiErrors";
import { printDocument } from "@/utils/printDocument";
import { canAccess } from "@/utils/authorization";
import { useCookie } from "@core/composable/useCookie";
import PrintableListReport from "@/components/PrintableListReport.vue";
import ResponsiveDataTableServer from "@/components/responsive-data-table/ResponsiveDataTableServer.vue";
import AttachmentsDialog from "@/components/attachments/AttachmentsDialog.vue";
import StaffCommissionFields from "@/components/StaffCommissionFields.vue";
import ContactActionMenu from "@/components/ContactActionMenu.vue";
import TableEmptyState from "@/components/TableEmptyState.vue";
import TableLoadingSkeleton from "@/components/TableLoadingSkeleton.vue";

interface Staff {
  id: number;
  user_id: number | null;
  name: string;
  father_name: string;
  contact: string;
  emergency_contact_name: string | null;
  emergency_contact_phone: string | null;
  id_number: string | null;
  bank_account_number: string | null;
  guarantor_name: string | null;
  guarantor_id_number: string | null;
  guarantor_phone: string | null;
  guarantor_address: string | null;
  guarantor_licence: string | null;
  education_lvl: string;
  position: string;
  status: "active" | "inactive";
  email: string;
  office_id: number | null;
  office: Employee["office"];
  department_id: number | null;
  department_name: string | null;
  supervisor_id: number | null;
  supervisor_name: string | null;
  salary: number | null;
  total_worked_hours: number;
  addr: string;
  join_date: string;
  off_day_allowance_days: number | null;
  is_commission_based: boolean;
  service_commissions?: Array<{ service: string; percentage: number }>;
  document?: Employee["document"];
  avatar?: string | null;
}

const staffList = ref<Staff[]>([]);
// The directory asks the server for one page at a time.
const page = ref(1);
const perPage = ref(25);
const total = ref(0);
// Filled from every matching staff row right before printing.
const reportRows = ref<string[][]>([]);
const router = useRouter();
const toast = useToast();
const isAddStaffDialogVisible = ref(false);
const isEditStaffDialogVisible = ref(false);
const isSelfProfileDialogVisible = ref(false);
const selfProfileForm = ref({
  name: "",
  father_name: "",
  contact: "",
  addr: "",
  emergency_contact_name: "",
  emergency_contact_phone: "",
  bank_account_number: "",
  guarantor_name: "",
  guarantor_id_number: "",
  guarantor_phone: "",
  guarantor_address: "",
  guarantor_licence: "",
});
const selfProfileId = ref<number | null>(null);
const isDeleteStaffDialogVisible = ref(false);
const isLeaveDialogVisible = ref(false);
const selectedStaff = ref<Staff | null>(null);
const searchQuery = ref("");
const filterPosition = ref<string | null>(null);
// Server-side status filter. The backend returns active employees only unless
// a management surface explicitly asks for inactive/all rows.
const statusFilter = ref<"all" | "active" | "inactive">("active");
const statusFilterOptions = computed(() => [
  { title: translate("common.statuses.all"), value: "all" as const },
  { title: translate("common.statuses.active"), value: "active" as const },
  { title: translate("common.statuses.inactive"), value: "inactive" as const },
]);
const isLoading = ref(false);
const isPositionsLoading = ref(false);
const positionOptions = ref<string[]>([]);
const positionIdMap = ref<Record<string, number>>({});
const positionSalaryMap = ref<Record<string, number>>({});
// Salaries are workspace money: the company's base currency is the only
// currency a staff record carries, so the directory prints its code.
const baseCurrency = ref<Pick<
  Currency,
  "code" | "symbol" | "decimalPlaces" | "rate"
> | null>(null);
const formatSalaryValue = (value: number | null | undefined) =>
  formatActiveMoney(value, baseCurrency.value ?? { code: "AFN" });
const formatSalary = (staff: Staff) => formatSalaryValue(staff.salary);
// Changing the position can rewrite pay from the position catalogue. Keep the
// previous value so a cancelled confirmation restores the record.
const positionBeforeChange = ref<string | null>(null);
const pendingPosition = ref<string | null>(null);
const isPositionSalaryDialogVisible = ref(false);
const pendingPositionSalary = computed(() =>
  pendingPosition.value === null
    ? null
    : (positionSalaryMap.value[pendingPosition.value] ?? null),
);

const offices = ref<Office[]>([]);
const departments = ref<Department[]>([]);
const userAccounts = ref<EmployeeAccount[]>([]);
const isUserAccountsLoading = ref(false);
const formSubmissionError = ref("");
const leaveStaff = ref<Staff | null>(null);
const leaveRequests = ref<LeaveRequest[]>([]);
const userData = useCookie<{ employee?: { id?: number } | null } | null>(
  "userData",
);
const leaveYear = ref(new Date().getFullYear());
const leaveAllowance = ref(0);
const leaveUsed = ref(0);
const leaveRemaining = ref(0);
const leaveForm = ref({
  id: null as number | null,
  start_date: new Date().toISOString().slice(0, 10),
  end_date: new Date().toISOString().slice(0, 10),
  duration_days: 1 as 0.5 | 1,
  reason: "",
});
const leaveError = ref("");
const isLeaveLoading = ref(false);
const leaveRequestDetailFields = computed(() => [
  { key: "date", label: translate("common.fields.date") },
  { key: "duration_days", label: translate("pages.leaveRequests.duration") },
  { key: "treatment", label: translate("pages.leaveRequests.treatment") },
  { key: "off_day_days", label: translate("common.attendanceStatuses.offDay") },
  { key: "absent_days", label: translate("common.attendanceStatuses.absent") },
  { key: "reason", label: translate("common.fields.reason") },
  { key: "approvedBy.name", label: translate("common.ui.approvedBy") },
  {
    key: "approval_note",
    label: translate("common.leaveApproval.justification"),
  },
]);
const attachmentTarget = ref<Staff | null>(null);
const isAttachmentsOpen = ref(false);

function openAttachments(staff: Staff) {
  attachmentTarget.value = staff;
  isAttachmentsOpen.value = true;
}

// Same range semantics as the self-service request dialog: Fridays are the
// weekly day off and are not counted as requested leave days.
const requestedLeaveDays = computed(() => {
  const start = new Date(`${leaveForm.value.start_date}T00:00:00`);
  const end = new Date(`${leaveForm.value.end_date}T00:00:00`);

  if (
    Number.isNaN(start.getTime()) ||
    Number.isNaN(end.getTime()) ||
    end < start
  )
    return 0;

  let days = 0;
  for (
    const day = new Date(start);
    day <= end;
    day.setDate(day.getDate() + 1)
  ) {
    if (day.getDay() !== 5) days++;
  }

  return days;
});

const isLeaveFormComplete = computed(() =>
  Boolean(
    leaveForm.value.start_date &&
    leaveForm.value.end_date &&
    leaveForm.value.reason.trim(),
  ),
);

const canSaveLeave = computed(() =>
  Boolean(!isLeaveLoading.value && isLeaveFormComplete.value),
);

const leaveLimitMessage = computed(() => {
  if (leaveForm.value.id || leaveRemaining.value > 0) return "";

  return leaveAllowance.value === 0
    ? "No off-day allowance is configured. Set the default in Settings, or give this employee a personal allowance."
    : `The ${leaveAllowance.value}-day off-day allowance is used; further approved days are recorded as absent unless an authority overrides them.`;
});

const educationOptions = [
  "High School",
  "Diploma",
  "Bachelor",
  "Master",
  "PhD",
];
const avatarText = (name: string) =>
  name
    .split(/\s+/)
    .filter(Boolean)
    .map((part) => part[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();

const staffHeaders = computed(() => [
  { title: translate("common.messages.id"), key: "id" },
  { title: translate("common.fields.name"), key: "name" },
  {
    title: translate("pages.settings.staffList.staffList.fatherName"),
    key: "father_name",
  },
  { title: translate("common.messages.contact"), key: "contact" },
  { title: translate("common.messages.position"), key: "position" },
  {
    title: translate("pages.settings.staffList.staffList.salary"),
    key: "salary",
  },
  {
    title: translate("pages.settings.staffList.staffList.workedHours"),
    key: "total_worked_hours",
  },
  {
    title: translate("pages.settings.staffList.staffList.joinDate"),
    key: "join_date",
  },
  { title: translate("common.fields.status"), key: "status" },
  {
    title: translate("common.messages.actions"),
    key: "actions",
    sortable: false,
  },
]);

const staffDetailFields = computed(() => [
  { key: "name", label: translate("common.fields.name") },
  {
    key: "father_name",
    label: translate("pages.settings.staffList.staffList.fatherName"),
  },
  { key: "id_number", label: translate("common.messages.governmentIDNumber") },
  { key: "contact", label: translate("common.messages.contact") },
  { key: "email", label: translate("common.fields.email") },
  {
    key: "emergency_contact_name",
    label: translate("pages.settings.staffList.staffList.emergencyContactName"),
  },
  {
    key: "emergency_contact_phone",
    label: translate(
      "pages.settings.staffList.staffList.emergencyContactPhone",
    ),
  },
  { key: "education_lvl", label: translate("common.messages.educationLevel") },
  { key: "position", label: translate("common.messages.position") },
  { key: "office.name", label: translate("common.fields.office") },
  { key: "department_name", label: translate("common.fields.department") },
  { key: "supervisor_name", label: translate("common.fields.supervisor") },
  { key: "join_date", label: translate("common.messages.joinDate") },
  { key: "salary", label: translate("common.messages.salary") },
  {
    key: "total_worked_hours",
    label: translate("pages.settings.staffList.staffList.workedHours"),
  },
  {
    key: "bank_account_number",
    label: translate("common.ui.bankAccountNumber"),
  },
  { key: "addr", label: translate("common.fields.address") },
  { key: "guarantor_name", label: translate("common.messages.guarantorName") },
  {
    key: "guarantor_id_number",
    label: translate("common.messages.guarantorGovernmentID"),
  },
  {
    key: "guarantor_phone",
    label: translate("common.messages.guarantorPhone"),
  },
  {
    key: "guarantor_licence",
    label: translate("common.messages.guarantorLicence"),
  },
  {
    key: "guarantor_address",
    label: translate("common.messages.guarantorAddress"),
  },
  { key: "status", label: translate("common.fields.status") },
]);

const printEmployeeContract = (staff: Staff) => {
  router.push({
    name: "employee-contracts",
    query: { employeeId: staff.id, print: "1" },
  });
};

const formErrors = ref({
  name: "",
  father_name: "",
  contact: "",
  id_number: "",
  email: "",
  addr: "",
  position: "",
  salary: "",
  education_lvl: "",
  join_date: "",
});

const resetFormErrors = () => {
  formErrors.value = {
    name: "",
    father_name: "",
    contact: "",
    id_number: "",
    email: "",
    addr: "",
    position: "",
    salary: "",
    education_lvl: "",
    join_date: "",
  };
};

const validateStaffForm = () => {
  let isValid = true;

  // Validate Name
  if (!selectedStaff.value?.name.trim()) {
    formErrors.value.name = translate(
      "pages.settings.staffList.staffList.nameIsRequired",
    );
    isValid = false;
  } else if (selectedStaff.value.name.length < 2) {
    formErrors.value.name = translate(
      "pages.settings.staffList.staffList.nameMustBeAtLeast2Characters",
    );
    isValid = false;
  } else {
    formErrors.value.name = "";
  }

  // Validate Father Name
  if (!selectedStaff.value?.father_name.trim()) {
    formErrors.value.father_name = translate(
      "pages.settings.staffList.staffList.fatherNameIsRequired",
    );
    isValid = false;
  } else if (selectedStaff.value.father_name.length < 2) {
    formErrors.value.father_name = translate(
      "pages.settings.staffList.staffList.fatherNameMustBeAtLeast2Characters",
    );
    isValid = false;
  } else {
    formErrors.value.father_name = "";
  }

  // Validate Contact
  if (!selectedStaff.value?.contact.trim()) {
    formErrors.value.contact = translate(
      "common.messages.contactNumberIsRequired",
    );
    isValid = false;
  } else if (!/^[\d\s\-()+]{8,}$/.test(selectedStaff.value.contact)) {
    formErrors.value.contact = translate(
      "common.messages.pleaseEnterAValidPhoneNumber",
    );
    isValid = false;
  } else {
    formErrors.value.contact = "";
  }

  // Validate Email
  if (!selectedStaff.value?.user_id && !selectedStaff.value?.email.trim()) {
    formErrors.value.email = translate(
      "pages.settings.staffList.staffList.emailIsRequired",
    );
    isValid = false;
  } else if (
    !selectedStaff.value?.user_id &&
    !/^[^\s@]+@[^\s@][^\s.@]*\.[^\s@]+$/.test(selectedStaff.value.email)
  ) {
    formErrors.value.email = translate(
      "pages.settings.staffList.staffList.pleaseEnterAValidEmailAddress",
    );
    isValid = false;
  } else {
    formErrors.value.email = "";
  }

  // Validate Address
  if (!selectedStaff.value?.addr.trim()) {
    formErrors.value.addr = translate(
      "pages.settings.staffList.staffList.addressIsRequired",
    );
    isValid = false;
  } else if (selectedStaff.value.addr.length < 5) {
    formErrors.value.addr = translate(
      "pages.settings.staffList.staffList.addressMustBeAtLeast5Characters",
    );
    isValid = false;
  } else {
    formErrors.value.addr = "";
  }

  if (!selectedStaff.value?.id_number?.trim()) {
    formErrors.value.id_number = translate(
      "pages.settings.staffList.staffList.governmentIDIsRequired",
    );
    isValid = false;
  } else {
    formErrors.value.id_number = "";
  }

  // Validate Position
  if (!selectedStaff.value?.position) {
    formErrors.value.position = translate(
      "pages.settings.staffList.staffList.positionIsRequired",
    );
    isValid = false;
  } else {
    formErrors.value.position = "";
  }

  // Validate Salary
  if (
    selectedStaff.value?.salary === null ||
    selectedStaff.value?.salary === undefined
  ) {
    formErrors.value.salary = translate(
      "pages.settings.staffList.staffList.salaryIsRequired",
    );
    isValid = false;
  } else if (Number(selectedStaff.value.salary) < 0) {
    formErrors.value.salary = translate(
      "pages.settings.staffList.staffList.salaryCannotBeNegative",
    );
    isValid = false;
  } else {
    formErrors.value.salary = "";
  }

  // Validate Education Level
  if (!selectedStaff.value?.education_lvl) {
    formErrors.value.education_lvl = translate(
      "pages.settings.staffList.staffList.educationLevelIsRequired",
    );
    isValid = false;
  } else {
    formErrors.value.education_lvl = "";
  }

  // Validate Join Date
  if (!selectedStaff.value?.join_date) {
    formErrors.value.join_date = translate(
      "pages.settings.staffList.staffList.joinDateIsRequired",
    );
    isValid = false;
  } else {
    formErrors.value.join_date = "";
  }

  return isValid;
};

// Commission rates are edited per built-in service. The rates live on the
// selected employee so both the create and edit dialogs share one binding.
const commissionServiceOptions = computed(() => [
  {
    value: "scholarship",
    title: translate("educational.builtInServices.scholarship"),
  },
  {
    value: "test_offer",
    title: translate("educational.builtInServices.testOffer"),
  },
  { value: "academy", title: translate("educational.builtInServices.academy") },
  { value: "private", title: translate("educational.builtInServices.private") },
]);

const commissionRate = (service: string) =>
  selectedStaff.value?.service_commissions?.find(
    (row) => row.service === service,
  )?.percentage ?? null;

const setCommissionRate = (service: string, value: unknown) => {
  if (!selectedStaff.value) return;

  const rows = [...(selectedStaff.value.service_commissions ?? [])];
  const index = rows.findIndex((row) => row.service === service);
  const percentage =
    value === "" || value === null || value === undefined ? 0 : Number(value);
  const row = {
    service,
    percentage: Number.isFinite(percentage) ? percentage : 0,
  };

  if (index === -1) rows.push(row);
  else rows[index] = row;

  selectedStaff.value.service_commissions = rows;
};

const commissionPayload = () =>
  selectedStaff.value?.is_commission_based
    ? (selectedStaff.value.service_commissions ?? []).filter(
        (row) => Number(row.percentage) > 0,
      )
    : [];

const openAddStaffDialog = () => {
  selectedStaff.value = {
    id: 0,
    user_id: null,
    name: "",
    father_name: "",
    contact: "",
    emergency_contact_name: null,
    emergency_contact_phone: null,
    id_number: "",
    bank_account_number: "",
    guarantor_id_number: "",
    guarantor_phone: "",
    guarantor_address: "",
    guarantor_licence: "",
    education_lvl: "",
    email: "",
    addr: "",
    position: "",
    status: "active",
    office_id: null,
    office: null,
    department_id: null,
    department_name: null,
    supervisor_id: null,
    supervisor_name: null,
    salary: 0,
    total_worked_hours: 0,
    join_date: new Date().toISOString().split("T")[0],
    off_day_allowance_days: null,
    guarantor_name: null,
    is_commission_based: false,
    service_commissions: [],
  };
  positionBeforeChange.value = "";
  resetFormErrors();
  formSubmissionError.value = "";
  isAddStaffDialogVisible.value = true;
};

const canManageEmployees = computed(() => canAccess("edit_employees"));
const canEditOwnProfile = computed(() =>
  canAccess("edit_own_employee_profile"),
);
const ownEmployeeId = computed(() => userData.value?.employee?.id ?? null);
const canEditStaff = (staff: Staff) =>
  canManageEmployees.value ||
  (canEditOwnProfile.value && staff.id === ownEmployeeId.value);
// A self-editor never sees payroll, hierarchy or account fields they cannot change.
const isSelfOnlyEdit = computed(() => !canManageEmployees.value);

const openEditStaffDialog = (staff: Staff) => {
  if (!canManageEmployees.value) {
    // A self-editor maintains personal, contact, guarantor and bank details
    // only. Payroll, position, office, hierarchy and account linkage stay HR.
    selfProfileId.value = staff.id;
    selfProfileForm.value = {
      name: staff.name ?? "",
      father_name: staff.father_name ?? "",
      contact: staff.contact ?? "",
      addr: staff.addr ?? "",
      emergency_contact_name: staff.emergency_contact_name ?? "",
      emergency_contact_phone: staff.emergency_contact_phone ?? "",
      bank_account_number: staff.bank_account_number ?? "",
      guarantor_name: staff.guarantor_name ?? "",
      guarantor_id_number: staff.guarantor_id_number ?? "",
      guarantor_phone: staff.guarantor_phone ?? "",
      guarantor_address: staff.guarantor_address ?? "",
      guarantor_licence: staff.guarantor_licence ?? "",
    };
    isSelfProfileDialogVisible.value = true;

    return;
  }

  // Keep edits local until Save.
  selectedStaff.value = {
    ...staff,
    is_commission_based: staff.is_commission_based ?? false,
    service_commissions: staff.service_commissions ?? [],
  };
  positionBeforeChange.value = staff.position;
  resetFormErrors();
  formSubmissionError.value = "";
  isEditStaffDialogVisible.value = true;
};

const saveSelfProfile = async () => {
  if (!selfProfileId.value) return;

  isLoading.value = true;
  formSubmissionError.value = "";
  try {
    const response = await EmployeeApi.update(selfProfileId.value, {
      ...selfProfileForm.value,
    });
    const updated = response.data?.data;
    if (updated) {
      const index = staffList.value.findIndex(
        (row) => row.id === selfProfileId.value,
      );
      if (index !== -1)
        staffList.value[index] = {
          ...staffList.value[index],
          ...updated,
          email: updated.email ?? staffList.value[index].email,
          salary: updated.salary ?? 0,
          join_date: updated.join_date ?? "",
          position: updated.position || staffList.value[index].position,
        };
    }
    isSelfProfileDialogVisible.value = false;
  } catch (error) {
    applyStaffApiErrors(error);
    formSubmissionError.value = apiErrorMessage(
      error,
      translate("pages.settings.staffList.staffList.unableToSaveEmployee"),
    );
  } finally {
    isLoading.value = false;
  }
};

const openDeleteStaffDialog = (staff: Staff) => {
  selectedStaff.value = staff;
  isDeleteStaffDialogVisible.value = true;
};

const loadLeaveRequests = async () => {
  if (!leaveStaff.value) return;
  isLeaveLoading.value = true;
  leaveError.value = "";
  try {
    const ledger = await LeaveRequestApi.getAll(
      leaveStaff.value.id,
      leaveYear.value,
    );

    leaveRequests.value = ledger.data;
    leaveAllowance.value = ledger.allowance;
    leaveUsed.value = ledger.used;
    leaveRemaining.value = ledger.remaining;
  } catch (error) {
    leaveError.value = apiErrorMessage(error, "Unable to load leave records.");
  } finally {
    isLeaveLoading.value = false;
  }
};

const openLeaveDialog = async (staff: Staff) => {
  leaveStaff.value = staff;
  leaveYear.value = new Date().getFullYear();
  const today = new Date().toISOString().slice(0, 10);
  leaveForm.value = {
    id: null,
    start_date: today,
    end_date: today,
    duration_days: 1,
    reason: "",
  };
  isLeaveDialogVisible.value = true;
  await loadLeaveRequests();
};

const editLeaveRequest = (record: LeaveRequest) => {
  leaveForm.value = {
    id: record.id,
    start_date: record.date,
    end_date: record.date,
    duration_days: record.duration_days,
    reason: record.reason,
  };
  leaveError.value = "";
};

const resetLeaveForm = () => {
  leaveForm.value = {
    id: null,
    start_date: `${leaveYear.value}-01-01`,
    end_date: `${leaveYear.value}-01-01`,
    duration_days: 1,
    reason: "",
  };
  leaveError.value = "";
};

const changeLeaveYear = async () => {
  if (!leaveForm.value.id) {
    leaveForm.value.start_date = `${leaveYear.value}-01-01`;
    leaveForm.value.end_date = `${leaveYear.value}-01-01`;
  }

  await loadLeaveRequests();
};

const saveLeave = async () => {
  if (!leaveStaff.value) return;
  if (
    !leaveForm.value.start_date ||
    !leaveForm.value.end_date ||
    !leaveForm.value.reason.trim()
  ) {
    leaveError.value = translate(
      !leaveForm.value.start_date
        ? "pages.users.staffList.dateRequired"
        : "pages.users.staffList.reasonRequired",
    );

    return;
  }
  if (
    !leaveForm.value.start_date.startsWith(`${leaveYear.value}-`) ||
    !leaveForm.value.end_date.startsWith(`${leaveYear.value}-`)
  ) {
    leaveError.value = `Date: Choose a date in ${leaveYear.value}.`;

    return;
  }
  if (leaveForm.value.end_date < leaveForm.value.start_date) {
    leaveError.value = translate("pages.leaveRequests.rangeExceeds", {
      count: leaveRemaining.value,
    });

    return;
  }
  isLeaveLoading.value = true;
  leaveError.value = "";
  try {
    if (leaveForm.value.id) {
      await LeaveRequestApi.update(leaveStaff.value.id, leaveForm.value.id, {
        date: leaveForm.value.start_date,
        duration_days: leaveForm.value.duration_days,
        reason: leaveForm.value.reason.trim(),
      });
    } else {
      await LeaveRequestApi.createRange(leaveStaff.value.id, {
        start_date: leaveForm.value.start_date,
        end_date: leaveForm.value.end_date,
        duration_days:
          requestedLeaveDays.value === 1 ? leaveForm.value.duration_days : 1,
        reason: leaveForm.value.reason.trim(),
      });
    }
    resetLeaveForm();
    await loadLeaveRequests();
  } catch (error) {
    leaveError.value = apiErrorMessage(error, "Unable to save leave.");
  } finally {
    isLeaveLoading.value = false;
  }
};

const deleteLeave = async (id: number) => {
  if (!leaveStaff.value) return;
  isLeaveLoading.value = true;
  try {
    await LeaveRequestApi.delete(leaveStaff.value.id, id);
    if (leaveForm.value.id === id) resetLeaveForm();
    await loadLeaveRequests();
  } catch (error) {
    leaveError.value = apiErrorMessage(error, "Unable to remove leave.");
  } finally {
    isLeaveLoading.value = false;
  }
};

const fetchBaseCurrency = async () => {
  try {
    const response = await CurrencyApi.getAll();

    baseCurrency.value =
      response.data.find((currency) => currency.isBase) ?? null;
  } catch (error) {
    console.error("Error fetching base currency:", error);
  }
};

const fetchPositions = async () => {
  try {
    isPositionsLoading.value = true;

    const response = await PositionApi.getAll();
    if (response.data && Array.isArray(response.data.data)) {
      positionOptions.value = response.data.data.map(
        (pos: Position) => pos.title,
      );
      positionIdMap.value = response.data.data.reduce(
        (map: Record<string, number>, pos: Position) => {
          map[pos.title] = pos.id;

          return map;
        },
        {},
      );
      positionSalaryMap.value = response.data.data.reduce(
        (map: Record<string, number>, pos: Position) => {
          map[pos.title] = Number(pos.salary ?? 0);

          return map;
        },
        {},
      );
    } else if (Array.isArray(response.data)) {
      positionOptions.value = response.data.map((pos: Position) => pos.title);
      positionIdMap.value = response.data.reduce(
        (map: Record<string, number>, pos: Position) => {
          map[pos.title] = pos.id;

          return map;
        },
        {},
      );
      positionSalaryMap.value = response.data.reduce(
        (map: Record<string, number>, pos: Position) => {
          map[pos.title] = Number(pos.salary ?? 0);

          return map;
        },
        {},
      );
    }
  } catch (error) {
    console.error("Error fetching positions:", error);
  } finally {
    isPositionsLoading.value = false;
  }
};

// The directory asks the server for one page at a time. A staff row falls back
// to the position catalogue when the API sends no position title.
const asStaffRow = (employee: Employee): Staff => ({
  ...employee,
  email: employee.email ?? "",
  salary: employee.salary ?? 0,
  join_date: employee.join_date ?? "",
  status: employee.status ?? "active",
  position:
    employee.position ||
    positionOptions.value[employee.position_id - 1] ||
    employee.position_id.toString(),
});

const fetchEmployees = async () => {
  try {
    isLoading.value = true;

    const result = await EmployeeApi.listPage({
      ...staffFilterParams(),
      page: page.value,
      perPage: perPage.value,
    });

    staffList.value = result.rows.map(asStaffRow);
    total.value = result.total;
  } catch (error) {
    console.error("Error fetching employees:", error);
  } finally {
    isLoading.value = false;
  }
};

function updateTableOptions(options: { page: number; itemsPerPage: number }) {
  if (page.value === options.page && perPage.value === options.itemsPerPage)
    return;

  page.value = options.page;
  perPage.value = options.itemsPerPage;
  void fetchEmployees();
}

// A filter change starts from the first page.
const reloadStaff = () => {
  page.value = 1;
  void fetchEmployees();
};

// Re-query the backend whenever the management status filter changes so the
// default (active) view never receives inactive employees in the first place.
watch(statusFilter, reloadStaff);

const fetchUserAccounts = async () => {
  try {
    isUserAccountsLoading.value = true;
    userAccounts.value = await EmployeeApi.getAccountOptions();
  } catch (error) {
    console.error("Error fetching user accounts:", error);
  } finally {
    isUserAccountsLoading.value = false;
  }
};

const userAccountOptions = computed(() =>
  userAccounts.value.map((user) => ({
    ...user,
    title: user.name,
    value: user.id,
    disabled: Boolean(
      user.employee && user.employee.id !== selectedStaff.value?.id,
    ),
  })),
);

const updateSelectedAccountEmail = (userId: number | null) => {
  const user = userAccounts.value.find((account) => account.id === userId);
  if (selectedStaff.value && user) selectedStaff.value.email = user.email;
};

const applyPositionSalary = (position: string) => {
  if (!selectedStaff.value) return;

  const catalogueSalary = positionSalaryMap.value[position];

  // No catalogue salary, or the same salary: nothing is rewritten, so no
  // confirmation is needed.
  if (
    catalogueSalary === undefined ||
    catalogueSalary === Number(selectedStaff.value.salary)
  ) {
    positionBeforeChange.value = position;

    return;
  }

  pendingPosition.value = position;
  isPositionSalaryDialogVisible.value = true;
};

const confirmPositionSalary = () => {
  if (!selectedStaff.value || pendingPosition.value === null) return;

  const salary = positionSalaryMap.value[pendingPosition.value];

  selectedStaff.value.position = pendingPosition.value;
  if (salary !== undefined) selectedStaff.value.salary = salary;

  positionBeforeChange.value = pendingPosition.value;
  pendingPosition.value = null;
  isPositionSalaryDialogVisible.value = false;
};

const cancelPositionSalary = () => {
  if (selectedStaff.value && positionBeforeChange.value !== null)
    selectedStaff.value.position = positionBeforeChange.value;

  pendingPosition.value = null;
  isPositionSalaryDialogVisible.value = false;
};

// Closing the dialog by Esc or an outside click is a cancellation too.
const handlePositionSalaryDialog = (open: boolean) => {
  if (!open && pendingPosition.value !== null) cancelPositionSalary();
};

const addStaff = async () => {
  if (!selectedStaff.value) return;
  if (!validateStaffForm()) return;

  try {
    isLoading.value = true;

    const positionId = positionIdMap.value[selectedStaff.value.position];

    const data: CreateEmployeeData = {
      user_id: selectedStaff.value.user_id,
      name: selectedStaff.value.name,
      father_name: selectedStaff.value.father_name,
      contact: selectedStaff.value.contact,
      emergency_contact_name: selectedStaff.value.emergency_contact_name,
      emergency_contact_phone: selectedStaff.value.emergency_contact_phone,
      id_number: selectedStaff.value.id_number ?? "",
      bank_account_number: selectedStaff.value.bank_account_number,
      guarantor_name: selectedStaff.value.guarantor_name,
      guarantor_id_number: selectedStaff.value.guarantor_id_number,
      guarantor_phone: selectedStaff.value.guarantor_phone,
      guarantor_address: selectedStaff.value.guarantor_address,
      guarantor_licence: selectedStaff.value.guarantor_licence,
      create_user: !selectedStaff.value.user_id,
      email: selectedStaff.value.email,
      education_lvl: selectedStaff.value.education_lvl,
      position_id: positionId,
      office_id: selectedStaff.value.office_id,
      department_id: selectedStaff.value.department_id,
      supervisor_id: selectedStaff.value.supervisor_id,
      salary: selectedStaff.value.salary,
      addr: selectedStaff.value.addr,
      join_date: selectedStaff.value.join_date,
      off_day_allowance_days: selectedStaff.value.off_day_allowance_days,
      is_commission_based: selectedStaff.value.is_commission_based,
      service_commissions: commissionPayload(),
      status: selectedStaff.value.status,
    };

    const response = await EmployeeApi.create(data);
    if (response.data && response.data.data) {
      staffList.value.unshift({
        ...response.data.data,
        email: response.data.data.email ?? "",
        salary: response.data.data.salary ?? 0,
        join_date: response.data.data.join_date ?? "",
        position:
          response.data.data.position ||
          positionOptions.value[response.data.data.position_id - 1] ||
          response.data.data.position_id.toString(),
      });
    }
    isAddStaffDialogVisible.value = false;
  } catch (error) {
    console.error("Error creating employee:", error);
    applyStaffApiErrors(error);
  } finally {
    isLoading.value = false;
  }
};

const updateStaff = async () => {
  if (!selectedStaff.value) return;
  if (!validateStaffForm()) return;

  try {
    isLoading.value = true;

    const positionId = positionIdMap.value[selectedStaff.value.position];

    const data: UpdateEmployeeData = {
      user_id: selectedStaff.value.user_id,
      name: selectedStaff.value.name,
      father_name: selectedStaff.value.father_name,
      contact: selectedStaff.value.contact,
      emergency_contact_name: selectedStaff.value.emergency_contact_name,
      emergency_contact_phone: selectedStaff.value.emergency_contact_phone,
      id_number: selectedStaff.value.id_number ?? "",
      bank_account_number: selectedStaff.value.bank_account_number,
      guarantor_name: selectedStaff.value.guarantor_name,
      guarantor_id_number: selectedStaff.value.guarantor_id_number,
      guarantor_phone: selectedStaff.value.guarantor_phone,
      guarantor_address: selectedStaff.value.guarantor_address,
      guarantor_licence: selectedStaff.value.guarantor_licence,
      create_user: false,
      email: selectedStaff.value.email,
      education_lvl: selectedStaff.value.education_lvl,
      position_id: positionId,
      status: selectedStaff.value.status,
      office_id: selectedStaff.value.office_id,
      department_id: selectedStaff.value.department_id,
      supervisor_id: selectedStaff.value.supervisor_id,
      salary: selectedStaff.value.salary,
      addr: selectedStaff.value.addr,
      join_date: selectedStaff.value.join_date,
      off_day_allowance_days: selectedStaff.value.off_day_allowance_days,
      is_commission_based: selectedStaff.value.is_commission_based,
      service_commissions: commissionPayload(),
    };

    const response = await EmployeeApi.update(selectedStaff.value.id, data);
    if (response.data && response.data.data) {
      const index = staffList.value.findIndex(
        (s) => s.id === selectedStaff.value?.id,
      );
      if (index !== -1) {
        staffList.value[index] = {
          ...response.data.data,
          email: response.data.data.email ?? "",
          salary: response.data.data.salary ?? 0,
          join_date: response.data.data.join_date ?? "",
          position:
            response.data.data.position ||
            positionOptions.value[response.data.data.position_id - 1] ||
            response.data.data.position_id.toString(),
        };
      }
    }
    isEditStaffDialogVisible.value = false;
  } catch (error) {
    console.error("Error updating employee:", error);
    applyStaffApiErrors(error);
  } finally {
    isLoading.value = false;
  }
};

const applyStaffApiErrors = (error: unknown) => {
  const errors = apiFieldErrors(error);
  const fieldMap: Record<string, keyof typeof formErrors.value> = {
    position_id: "position",
  };

  Object.entries(errors).forEach(([field, message]) => {
    const target = fieldMap[field] ?? field;
    if (target in formErrors.value)
      formErrors.value[target as keyof typeof formErrors.value] = message;
  });

  formSubmissionError.value = Object.keys(errors).length
    ? "Please correct the highlighted fields."
    : apiErrorMessage(
        error,
        "Unable to save the staff record. Please try again.",
      );
};

const deleteStaff = async () => {
  if (!selectedStaff.value) return;

  try {
    isLoading.value = true;
    await EmployeeApi.delete(selectedStaff.value.id);
    staffList.value = staffList.value.filter(
      (s) => s.id !== selectedStaff.value?.id,
    );
    isDeleteStaffDialogVisible.value = false;
  } catch (error) {
    console.error("Error deleting employee:", error);
  } finally {
    isLoading.value = false;
  }
};

/** Flip an employee between active and inactive through the regular update endpoint. */
const toggleStaffStatus = async (staff: Staff) => {
  const nextStatus: "active" | "inactive" =
    (staff.status ?? "active") === "active" ? "inactive" : "active";

  try {
    isLoading.value = true;
    const data: UpdateEmployeeData = { status: nextStatus };
    const response = await EmployeeApi.update(staff.id, data);
    if (response.data && response.data.data) {
      const updated = response.data.data;
      const index = staffList.value.findIndex((s) => s.id === staff.id);
      if (index !== -1) {
        staffList.value[index] = {
          ...staff,
          ...updated,
          email: updated.email ?? "",
          salary: updated.salary ?? 0,
          join_date: updated.join_date ?? "",
          status: updated.status,
          position:
            updated.position ||
            positionOptions.value[updated.position_id - 1] ||
            updated.position_id.toString(),
        };
      }
    }
    toast.success(
      translate("pages.settings.staffList.staffList.staffStatusUpdated"),
    );
  } catch (error) {
    console.error("Error updating employee status:", error);
    toast.error(
      apiErrorMessage(
        error,
        translate("pages.settings.staffList.staffList.statusUpdateFailed"),
      ),
    );
  } finally {
    isLoading.value = false;
  }
};

const staffFilterParams = (): EmployeeParams => ({
  status: statusFilter.value,
  search: searchQuery.value || undefined,
  position: filterPosition.value || undefined,
});

// Typing in the search box is a filter change, so it starts from the first page.
const debouncedStaffSearch = useDebounceFn(reloadStaff, 300);

const resetStaffFilters = () => {
  filterPosition.value = null;
  statusFilter.value = "active";
  searchQuery.value = "";
  page.value = 1;
  void fetchEmployees();
};

const staffReportRow = (staff: Staff): string[] => [
  String(staff.id),
  staff.name,
  staff.position,
  staff.contact,
  staff.email,
  staff.office?.name ?? "—",
];

// Print is global: the report is filled from every matching row first.
const printTable = async () => {
  try {
    const response = await EmployeeApi.getAll(staffFilterParams());
    const rows: Employee[] = Array.isArray(response.data?.data)
      ? response.data.data
      : [];

    reportRows.value = rows.map(asStaffRow).map(staffReportRow);
    await printDocument({
      bodyClass: "printing-table-staff",
      pageStyle: "size: A4 landscape; margin: 12mm;",
    });
  } catch (error) {
    console.error("Error preparing staff report:", error);
    toast.error(apiErrorMessage(error));
  }
};

onMounted(async () => {
  const [, officeRows, departmentRows] = await Promise.all([
    fetchPositions(),
    OfficeApi.getAll(),
    DepartmentApi.getAll(),
    fetchUserAccounts(),
    fetchBaseCurrency(),
  ]);

  offices.value = officeRows.filter((office) => office.status === "active");
  departments.value = departmentRows;
  await fetchEmployees();
});
</script>

<template>
  <div>
    <DataTablePageKpis
      :items="staffList"
      :title="$t('components.dataTablePageKpis.pages.staffDirectory.title')"
      :description="
        $t('components.dataTablePageKpis.pages.staffDirectory.description')
      "
      icon="tabler-users"
    />
    <VCard class="mb-6">
      <VCardText>
        <VRow align="center">
          <VCol cols="12" md="6">
            <VTextField
              v-model="searchQuery"
              class="data-table-search"
              prepend-inner-icon="tabler-search"
              :placeholder="
                $t('pages.settings.staffList.staffList.searchStaff')
              "
              density="compact"
              clearable
              hide-details
              @update:model-value="debouncedStaffSearch"
            />
          </VCol>

          <VCol cols="12" md="6" class="d-flex gap-2 justify-end">
            <DataTableFilterMenu
              :active="Boolean(filterPosition) || statusFilter !== 'active'"
              @reset="resetStaffFilters"
            >
              <VSelect
                v-model="filterPosition"
                :items="positionOptions"
                :label="
                  $t('pages.settings.staffList.staffList.filterByPosition')
                "
                density="compact"
                clearable
                hide-details
                :loading="isPositionsLoading"
                @update:model-value="reloadStaff"
              />
              <VSelect
                v-model="statusFilter"
                :items="statusFilterOptions"
                :label="$t('common.fields.status')"
                density="compact"
                hide-details
              />
            </DataTableFilterMenu>
            <VBtn icon="tabler-printer" variant="text" @click="printTable">
              <VIcon icon="tabler-printer" /><VTooltip
                activator="parent"
                location="top"
              >
                {{ $t("pages.settings.staffList.staffList.printStaffRecords") }}
              </VTooltip>
            </VBtn>
            <VBtn color="primary" @click="openAddStaffDialog">
              <VIcon icon="tabler-plus" class="me-1" />
              {{ $t("pages.settings.staffList.staffList.addStaff") }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <div class="table-container">
        <TableLoadingSkeleton
          v-if="isLoading"
          :columns="staffHeaders.length"
          :label="$t('common.messages.loading')"
        />
        <TableEmptyState
          v-else-if="!staffList.length"
          icon="tabler-user-off"
          :description="$t('educational.noStaff')"
        />
        <ResponsiveDataTableServer
          v-else
          :items="staffList"
          :items-length="total"
          :page="page"
          :items-per-page="perPage"
          :items-per-page-options="[10, 25, 50, 100]"
          :headers="staffHeaders"
          item-value="id"
          class="text-no-wrap staff-table"
          mobile-breakpoint="sm"
          density="comfortable"
          @update:options="updateTableOptions"
        >
          <template #item.salary="{ item }">
            <div>{{ formatSalary(item) }}</div>
          </template>

          <!-- Quick contact for the employee directory: the same menu the CRM
               lists use, offering this page's (employee) WhatsApp templates.
               Employees have no CRM conversation record, so the menu opens the
               chat and leaves logging to the CRM surfaces. -->
          <template #item.contact="{ item }">
            <ContactActionMenu
              kind="phone"
              :value="item.contact"
              audience="employee"
              :context="item"
            />
          </template>

          <template #item.name="{ item }">
            <div class="d-flex align-center gap-3">
              <VAvatar
                size="34"
                class="mx-2"
                :variant="item.avatar ? undefined : 'tonal'"
                :color="item.avatar ? undefined : 'primary'"
              >
                <VImg v-if="item.avatar" :src="item.avatar" :alt="item.name" />
                <span v-else>{{ avatarText(item.name) }}</span>
              </VAvatar>
              <span>{{ item.name }}</span>
            </div>
          </template>

          <template #item.status="{ item }">
            <VChip
              size="small"
              variant="tonal"
              :color="
                (item.status ?? 'active') === 'active' ? 'success' : 'warning'
              "
            >
              {{
                $t(
                  (item.status ?? "active") === "active"
                    ? "common.statuses.active"
                    : "common.statuses.inactive",
                )
              }}
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <FullDetailsButton
                :item="item"
                :fields="staffDetailFields"
                :title="item.name"
              />
              <VBtn
                icon="tabler-paperclip"
                variant="text"
                size="small"
                :aria-label="$t('common.attachments.title')"
                @click="openAttachments(item)"
              >
                <VIcon icon="tabler-paperclip" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t("common.attachments.title") }}
                </VTooltip>
              </VBtn>
              <VBtn
                icon="tabler-file-description"
                variant="text"
                size="small"
                color="primary"
                :aria-label="$t('common.ui.printEmploymentContract')"
                @click="printEmployeeContract(item)"
              >
                <VIcon icon="tabler-file-description" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t("common.ui.printEmploymentContract") }}
                </VTooltip>
              </VBtn>
              <VBtn
                icon="tabler-calendar-time"
                variant="text"
                size="small"
                color="primary"
                :aria-label="
                  $t('pages.settings.staffList.staffList.managePaidOffDays')
                "
                @click="openLeaveDialog(item)"
              >
                <VIcon icon="tabler-calendar-time" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{
                    $t("pages.settings.staffList.staffList.managePaidOffDays")
                  }}
                </VTooltip>
              </VBtn>
              <VBtn
                v-if="canEditStaff(item)"
                icon="tabler-edit"
                variant="text"
                size="small"
                color="medium-emphasis"
                :aria-label="
                  $t('pages.settings.staffList.staffList.editStaffRecord')
                "
                @click="openEditStaffDialog(item)"
              >
                <VIcon icon="tabler-edit" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t("pages.settings.staffList.staffList.editStaffRecord") }}
                </VTooltip>
              </VBtn>
              <VBtn
                icon
                variant="text"
                size="small"
                :color="
                  (item.status ?? 'active') === 'active' ? 'error' : 'success'
                "
                :aria-label="
                  (item.status ?? 'active') === 'active'
                    ? $t(
                        'pages.settings.staffList.staffList.deactivateStaffRecord',
                      )
                    : $t(
                        'pages.settings.staffList.staffList.activateStaffRecord',
                      )
                "
                @click="toggleStaffStatus(item)"
              >
                <VIcon
                  :icon="
                    (item.status ?? 'active') === 'active'
                      ? 'tabler-user-cancel'
                      : 'tabler-user-check'
                  "
                /><VTooltip activator="parent" location="top">
                  {{
                    (item.status ?? "active") === "active"
                      ? $t(
                          "pages.settings.staffList.staffList.deactivateStaffRecord",
                        )
                      : $t(
                          "pages.settings.staffList.staffList.activateStaffRecord",
                        )
                  }}
                </VTooltip>
              </VBtn>
              <VBtn
                icon="tabler-trash"
                variant="text"
                size="small"
                color="error"
                :aria-label="
                  $t('pages.settings.staffList.staffList.deleteStaffRecord')
                "
                @click="openDeleteStaffDialog(item)"
              >
                <VIcon icon="tabler-trash" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{
                    $t("pages.settings.staffList.staffList.deleteStaffRecord")
                  }}
                </VTooltip>
              </VBtn>
            </div>
          </template>
        </ResponsiveDataTableServer>
      </div>
    </VCard>

    <PrintableListReport
      :title="$t('common.crmUi.printReports.staff.title')"
      :subtitle="$t('common.crmUi.printReports.staff.subtitle')"
      :columns="[
        $t('common.crmUi.printReports.columns.id'),
        $t('common.crmUi.printReports.columns.name'),
        $t('common.crmUi.printReports.columns.position'),
        $t('common.crmUi.printReports.columns.contact'),
        $t('common.crmUi.printReports.columns.email'),
        $t('common.crmUi.printReports.columns.office'),
      ]"
      :rows="reportRows"
    />

    <!-- Add Staff Dialog -->
    <VDialog v-model="isAddStaffDialogVisible" max-width="1100px" scrollable>
      <VCard v-if="selectedStaff">
        <VCardTitle class="pa-5">
          <span class="headline">{{
            $t("pages.settings.staffList.staffList.addNewStaff")
          }}</span>
        </VCardTitle>

        <VCardText>
          <VForm class="app-form">
            <VAlert
              v-if="formSubmissionError"
              type="error"
              variant="tonal"
              class="mb-4"
            >
              {{ formSubmissionError }}
            </VAlert>
            <VRow>
              <FormSection
                title-key="common.fields.employee"
                description-key="common.messages.contact"
                icon="tabler-user"
              />
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.name"
                  :label="$t('common.messages.fullName')"
                  :error-messages="formErrors.name"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.father_name"
                  :label="$t('common.messages.fatherName')"
                  :error-messages="formErrors.father_name"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.contact"
                  :label="$t('common.fields.phone')"
                  :error-messages="formErrors.contact"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.emergency_contact_name"
                  :label="
                    $t(
                      'pages.settings.staffList.staffList.emergencyContactName',
                    )
                  "
                  clearable
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.emergency_contact_phone"
                  :label="
                    $t(
                      'pages.settings.staffList.staffList.emergencyContactPhone',
                    )
                  "
                  clearable
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="selectedStaff.user_id"
                  class="employee-account-select"
                  :items="userAccountOptions"
                  :label="$t('common.accountPicker.label')"
                  :placeholder="$t('common.accountPicker.placeholder')"
                  clearable
                  :loading="isUserAccountsLoading"
                  @update:model-value="updateSelectedAccountEmail"
                >
                  <template #selection="{ item }">
                    <div class="account-selection">
                      <span class="account-selection__name">{{
                        item.raw.name
                      }}</span
                      ><span class="account-selection__meta"
                        >#{{ item.raw.id }} · {{ item.raw.email }}</span
                      >
                    </div>
                  </template>
                  <template #item="{ props, item }">
                    <VListItem
                      v-bind="props"
                      class="account-option"
                      :title="item.raw.name"
                    >
                      <template #prepend>
                        <VAvatar color="primary" variant="tonal" size="38">
                          {{ avatarText(item.raw.name) }}
                        </VAvatar> </template
                      ><template #subtitle>
                        <div class="account-option__details">
                          <span>#{{ item.raw.id }} · {{ item.raw.email }}</span
                          ><span v-if="item.raw.employee"
                            ><VIcon icon="tabler-id-badge-2" size="14" />
                            {{
                              $t("common.accountPicker.linkedEmployee", {
                                name: item.raw.employee.name,
                                id: item.raw.employee.id,
                              })
                            }}</span
                          ><span v-if="!item.raw.employee">{{
                            $t("common.accountPicker.available")
                          }}</span>
                        </div> </template
                      ><template #append>
                        <span class="account-option__id"
                          >#{{ item.raw.id }}</span
                        >
                      </template>
                    </VListItem>
                  </template>
                </VSelect>
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.id_number"
                  :label="$t('common.messages.governmentIDNumber')"
                  :error-messages="formErrors.id_number"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.email"
                  :label="$t('common.fields.email')"
                  type="email"
                  :error-messages="formErrors.email"
                  :required="!selectedStaff.user_id"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="selectedStaff.education_lvl"
                  :items="educationOptions"
                  :label="$t('common.messages.educationLevel')"
                  :error-messages="formErrors.education_lvl"
                  required
                />
              </VCol>
              <FormSection
                title-key="common.messages.position"
                description-key="common.messages.contractDuration"
                icon="tabler-briefcase"
              />
              <VCol cols="12" md="4">
                <VSelect
                  v-model="selectedStaff.position"
                  :items="positionOptions"
                  :label="$t('common.messages.position')"
                  :error-messages="formErrors.position"
                  required
                  :loading="isPositionsLoading"
                  @update:model-value="applyPositionSalary"
                />
              </VCol>
              <VCol cols="12" md="4">
                <AppDateTimePicker
                  v-model="selectedStaff.join_date"
                  :label="$t('common.messages.joinDate')"
                  :error-messages="formErrors.join_date"
                  required
                  :config="{ dateFormat: 'Y-m-d' }"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.off_day_allowance_days"
                  type="number"
                  min="0"
                  step="0.5"
                  :label="
                    $t('pages.settings.staffList.staffList.offDayAllowanceDays')
                  "
                  :hint="
                    $t(
                      'pages.settings.staffList.staffList.offDayAllowanceDaysHint',
                    )
                  "
                  persistent-hint
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="selectedStaff.office_id"
                  :items="offices"
                  item-title="name"
                  item-value="id"
                  :label="$t('common.fields.office')"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="selectedStaff.department_id"
                  :items="departments"
                  item-title="name"
                  item-value="id"
                  :label="$t('common.fields.department')"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="selectedStaff.supervisor_id"
                  :items="
                    staffList.filter((staff) => staff.id !== selectedStaff?.id)
                  "
                  item-title="name"
                  item-value="id"
                  :label="$t('common.fields.supervisor')"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="selectedStaff.salary"
                  :label="$t('common.messages.salary')"
                  type="number"
                  min="0"
                  :error-messages="formErrors.salary"
                  required
                />
              </VCol>
              <StaffCommissionFields
                v-model:enabled="selectedStaff.is_commission_based"
                :options="commissionServiceOptions"
                :rate="commissionRate"
                @update:rate="setCommissionRate"
              />
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.bank_account_number"
                  :label="$t('common.ui.bankAccountNumber')"
                />
              </VCol>
              <FormSection
                title-key="common.fields.address"
                description-key="common.messages.guarantorAddress"
                icon="tabler-shield-check"
              />
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.guarantor_name"
                  :label="$t('common.messages.guarantorName')"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.guarantor_id_number"
                  :label="$t('common.messages.guarantorGovernmentID')"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.guarantor_phone"
                  :label="$t('common.messages.guarantorPhone')"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.guarantor_licence"
                  :label="$t('common.messages.guarantorLicence')"
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="selectedStaff.guarantor_address"
                  :label="$t('common.messages.guarantorAddress')"
                  rows="2"
                  auto-grow
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.addr"
                  :label="$t('common.fields.address')"
                  :error-messages="formErrors.addr"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="selectedStaff.status"
                  :items="
                    statusFilterOptions.filter(
                      (option) => option.value !== 'all',
                    )
                  "
                  :label="$t('common.fields.status')"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isAddStaffDialogVisible = false"
          >
            {{ $t("common.actions.cancel") }}
          </VBtn>
          <VBtn color="primary" :loading="isLoading" @click="addStaff">
            {{ $t("common.actions.save") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit Staff Dialog -->
    <VDialog v-model="isEditStaffDialogVisible" max-width="1100px" scrollable>
      <VCard v-if="selectedStaff">
        <VCardTitle class="pa-5">
          <span class="headline">{{
            $t("pages.settings.staffList.staffList.editStaff")
          }}</span>
        </VCardTitle>

        <VCardText>
          <VForm class="app-form">
            <VAlert
              v-if="formSubmissionError"
              type="error"
              variant="tonal"
              class="mb-4"
            >
              {{ formSubmissionError }}
            </VAlert>
            <VRow>
              <FormSection
                title-key="common.fields.employee"
                description-key="common.messages.contact"
                icon="tabler-user"
              />
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.name"
                  :label="$t('common.messages.fullName')"
                  :error-messages="formErrors.name"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.father_name"
                  :label="$t('common.messages.fatherName')"
                  :error-messages="formErrors.father_name"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.contact"
                  :label="$t('common.fields.phone')"
                  :error-messages="formErrors.contact"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.emergency_contact_name"
                  :label="
                    $t(
                      'pages.settings.staffList.staffList.emergencyContactName',
                    )
                  "
                  clearable
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.emergency_contact_phone"
                  :label="
                    $t(
                      'pages.settings.staffList.staffList.emergencyContactPhone',
                    )
                  "
                  clearable
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="selectedStaff.user_id"
                  class="employee-account-select"
                  :items="userAccountOptions"
                  :label="$t('common.accountPicker.label')"
                  :placeholder="$t('common.accountPicker.placeholder')"
                  clearable
                  :loading="isUserAccountsLoading"
                  @update:model-value="updateSelectedAccountEmail"
                >
                  <template #selection="{ item }">
                    <div class="account-selection">
                      <span class="account-selection__name">{{
                        item.raw.name
                      }}</span
                      ><span class="account-selection__meta"
                        >#{{ item.raw.id }} · {{ item.raw.email }}</span
                      >
                    </div>
                  </template>
                  <template #item="{ props, item }">
                    <VListItem
                      v-bind="props"
                      class="account-option"
                      :title="item.raw.name"
                    >
                      <template #prepend>
                        <VAvatar color="primary" variant="tonal" size="38">
                          {{ avatarText(item.raw.name) }}
                        </VAvatar> </template
                      ><template #subtitle>
                        <div class="account-option__details">
                          <span>#{{ item.raw.id }} · {{ item.raw.email }}</span
                          ><span v-if="item.raw.employee"
                            ><VIcon icon="tabler-id-badge-2" size="14" />
                            {{
                              $t("common.accountPicker.linkedEmployee", {
                                name: item.raw.employee.name,
                                id: item.raw.employee.id,
                              })
                            }}</span
                          ><span v-if="!item.raw.employee">{{
                            $t("common.accountPicker.available")
                          }}</span>
                        </div> </template
                      ><template #append>
                        <span class="account-option__id"
                          >#{{ item.raw.id }}</span
                        >
                      </template>
                    </VListItem>
                  </template>
                </VSelect>
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.id_number"
                  :label="$t('common.messages.governmentIDNumber')"
                  :error-messages="formErrors.id_number"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.email"
                  :label="$t('common.fields.email')"
                  type="email"
                  :error-messages="formErrors.email"
                  :required="!selectedStaff.user_id"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="selectedStaff.education_lvl"
                  :items="educationOptions"
                  :label="$t('common.messages.educationLevel')"
                  :error-messages="formErrors.education_lvl"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="selectedStaff.position"
                  :items="positionOptions"
                  :label="$t('common.messages.position')"
                  :error-messages="formErrors.position"
                  required
                  :loading="isPositionsLoading"
                  @update:model-value="applyPositionSalary"
                />
              </VCol>
              <FormSection
                title-key="common.messages.position"
                description-key="common.messages.contractDuration"
                icon="tabler-briefcase"
              />
              <VCol cols="12" md="4">
                <AppDateTimePicker
                  v-model="selectedStaff.join_date"
                  :label="$t('common.messages.joinDate')"
                  :error-messages="formErrors.join_date"
                  required
                  :config="{ dateFormat: 'Y-m-d' }"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.off_day_allowance_days"
                  type="number"
                  min="0"
                  step="0.5"
                  :label="
                    $t('pages.settings.staffList.staffList.offDayAllowanceDays')
                  "
                  :hint="
                    $t(
                      'pages.settings.staffList.staffList.offDayAllowanceDaysHint',
                    )
                  "
                  persistent-hint
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="selectedStaff.office_id"
                  :items="offices"
                  item-title="name"
                  item-value="id"
                  :label="$t('common.fields.office')"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="selectedStaff.department_id"
                  :items="departments"
                  item-title="name"
                  item-value="id"
                  :label="$t('common.fields.department')"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="selectedStaff.supervisor_id"
                  :items="
                    staffList.filter((staff) => staff.id !== selectedStaff?.id)
                  "
                  item-title="name"
                  item-value="id"
                  :label="$t('common.fields.supervisor')"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="selectedStaff.salary"
                  :label="$t('common.messages.salary')"
                  type="number"
                  min="0"
                  :error-messages="formErrors.salary"
                  required
                />
              </VCol>
              <StaffCommissionFields
                v-model:enabled="selectedStaff.is_commission_based"
                :options="commissionServiceOptions"
                :rate="commissionRate"
                @update:rate="setCommissionRate"
              />
              <FormSection
                title-key="common.fields.address"
                description-key="common.messages.guarantorAddress"
                icon="tabler-shield-check"
              />
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.bank_account_number"
                  :label="$t('common.ui.bankAccountNumber')"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.guarantor_name"
                  :label="$t('common.messages.guarantorName')"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.guarantor_id_number"
                  :label="$t('common.messages.guarantorGovernmentID')"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.guarantor_phone"
                  :label="$t('common.messages.guarantorPhone')"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.guarantor_licence"
                  :label="$t('common.messages.guarantorLicence')"
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="selectedStaff.guarantor_address"
                  :label="$t('common.messages.guarantorAddress')"
                  rows="2"
                  auto-grow
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="selectedStaff.addr"
                  :label="$t('common.fields.address')"
                  :error-messages="formErrors.addr"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="selectedStaff.status"
                  :items="[
                    { title: $t('common.statuses.active'), value: 'active' },
                    {
                      title: $t('common.statuses.inactive'),
                      value: 'inactive',
                    },
                  ]"
                  :label="$t('common.fields.status')"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isEditStaffDialogVisible = false"
          >
            {{ $t("common.actions.cancel") }}
          </VBtn>
          <VBtn color="primary" :loading="isLoading" @click="updateStaff">
            {{ $t("common.actions.save") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="isLeaveDialogVisible"
      max-width="900px"
      scrollable
      class="erp-form"
    >
      <VCard>
        <VCardTitle class="d-flex align-center pa-5">
          <div>
            <div class="text-h6">
              {{ $t("pages.settings.staffList.staffList.paidOffDays") }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ leaveStaff?.name }} · {{ leaveYear }}
            </div>
          </div>
          <VSpacer />
          <VTextField
            v-model.number="leaveYear"
            type="number"
            min="2000"
            max="3000"
            density="compact"
            hide-details
            style="max-width: 110px"
            @update:model-value="changeLeaveYear"
          />
        </VCardTitle>

        <VCardText>
          <div class="d-flex flex-wrap gap-2 mb-5">
            <VChip color="primary" variant="tonal">
              {{
                $t("pages.users.staffList.leaveAllowance", {
                  value: leaveAllowance,
                })
              }}
            </VChip>
            <VChip color="warning" variant="tonal">
              {{ $t("pages.users.staffList.leaveUsed", { value: leaveUsed }) }}
            </VChip>
            <VChip color="success" variant="tonal">
              {{
                $t("pages.users.staffList.leaveRemaining", {
                  value: leaveRemaining,
                })
              }}
            </VChip>
          </div>
          <VAlert v-if="leaveError" type="error" variant="tonal" class="mb-4">
            {{ leaveError }}
          </VAlert>
          <VAlert
            v-else-if="leaveLimitMessage"
            type="warning"
            variant="tonal"
            class="mb-4"
          >
            {{ leaveLimitMessage }}
          </VAlert>
          <VAlert
            v-else-if="requestedLeaveDays > leaveRemaining"
            type="warning"
            variant="tonal"
            class="mb-4"
          >
            {{
              $t("pages.leaveRequests.rangeExceeds", { count: leaveRemaining })
            }}
          </VAlert>

          <VRow class="align-end mb-3">
            <VCol cols="12" md="6">
              <AppDateTimePicker
                v-model="leaveForm.start_date"
                :label="$t('pages.leaveRequests.from')"
                :config="{ dateFormat: 'Y-m-d' }"
              />
            </VCol>
            <VCol cols="12" md="6">
              <AppDateTimePicker
                v-model="leaveForm.end_date"
                :label="$t('pages.leaveRequests.to')"
                :config="{ dateFormat: 'Y-m-d' }"
                :disabled="Boolean(leaveForm.id)"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="leaveForm.reason"
                :label="$t('common.fields.reason')"
                maxlength="1000"
              />
            </VCol>
            <VCol v-if="requestedLeaveDays === 1" cols="12" md="6">
              <VSelect
                v-model="leaveForm.duration_days"
                :items="[
                  { title: $t('pages.leaveRequests.fullDay'), value: 1 },
                  { title: $t('pages.leaveRequests.halfDay'), value: 0.5 },
                ]"
                :label="$t('pages.leaveRequests.duration')"
              />
            </VCol>
            <VCol cols="12" class="d-flex flex-wrap gap-2">
              <VBtn
                color="primary"
                :disabled="!canSaveLeave || isLeaveLoading"
                @click="saveLeave"
              >
                {{
                  leaveForm.id
                    ? $t("common.actions.update")
                    : $t("pages.leaveRequests.requestDays", {
                        count: requestedLeaveDays || "",
                      })
                }}
                <VTooltip activator="parent" location="top">
                  {{
                    !isLeaveFormComplete
                      ? "Enter both a date and reason."
                      : leaveLimitMessage
                  }}
                </VTooltip>
              </VBtn>
              <VBtn
                v-if="leaveForm.id"
                icon="tabler-x"
                variant="text"
                @click="resetLeaveForm"
              />
            </VCol>
          </VRow>

          <ResponsiveDataTable
            :headers="[
              { title: translate('common.fields.date'), key: 'date' },
              { title: translate('common.fields.reason'), key: 'reason' },
              {
                title: translate('common.messages.actions'),
                key: 'actions',
                sortable: false,
                align: 'end',
              },
            ]"
            :items="leaveRequests"
            item-value="id"
            :loading="isLeaveLoading"
            density="comfortable"
          >
            <template #item.actions="{ item }">
              <FullDetailsButton
                :item="item"
                :title="leaveStaff?.name"
                :fields="leaveRequestDetailFields"
              />
              <VBtn
                icon="tabler-edit"
                size="small"
                variant="text"
                @click="editLeaveRequest(item)"
              />
              <VBtn
                icon="tabler-trash"
                size="small"
                variant="text"
                color="error"
                @click="deleteLeave(item.id)"
              />
            </template>
          </ResponsiveDataTable>
        </VCardText>

        <VCardActions class="pa-5">
          <VSpacer /><VBtn
            variant="tonal"
            @click="isLeaveDialogVisible = false"
          >
            {{ $t("common.actions.close") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Self-service employee profile: personal, contact, guarantor and bank fields only. -->
    <VDialog v-model="isSelfProfileDialogVisible" max-width="840" scrollable>
      <VCard>
        <VCardTitle class="pa-5">
          {{ $t("pages.settings.staffList.staffList.editMyProfile") }}
        </VCardTitle>
        <VCardText>
          <VAlert
            v-if="formSubmissionError"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ formSubmissionError }}
          </VAlert>
          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                v-model="selfProfileForm.name"
                :label="$t('common.messages.fullName')"
                :error-messages="formErrors.name"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="selfProfileForm.father_name"
                :label="$t('common.messages.fatherName')"
                :error-messages="formErrors.father_name"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="selfProfileForm.contact"
                :label="$t('common.messages.contactPhone')"
                :error-messages="formErrors.contact"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="selfProfileForm.addr"
                :label="$t('common.fields.address')"
                :error-messages="formErrors.addr"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="selfProfileForm.emergency_contact_name"
                :label="
                  $t('pages.settings.staffList.staffList.emergencyContactName')
                "
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="selfProfileForm.emergency_contact_phone"
                :label="
                  $t('pages.settings.staffList.staffList.emergencyContactPhone')
                "
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="selfProfileForm.bank_account_number"
                :label="$t('common.ui.bankAccountNumber')"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="selfProfileForm.guarantor_name"
                :label="$t('common.messages.guarantorName')"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="selfProfileForm.guarantor_id_number"
                :label="$t('common.messages.guarantorGovernmentID')"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="selfProfileForm.guarantor_phone"
                :label="$t('common.messages.guarantorPhone')"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="selfProfileForm.guarantor_licence"
                :label="$t('common.messages.guarantorLicence')"
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model="selfProfileForm.guarantor_address"
                :label="$t('common.messages.guarantorAddress')"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn variant="tonal" @click="isSelfProfileDialogVisible = false">
            {{ $t("common.actions.cancel") }}
          </VBtn>
          <VBtn color="primary" :loading="isLoading" @click="saveSelfProfile">
            {{ $t("common.actions.save") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Position / salary confirmation: a position change never rewrites pay silently. -->
    <VDialog
      v-model="isPositionSalaryDialogVisible"
      max-width="520px"
      @update:model-value="handlePositionSalaryDialog"
    >
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">{{
            $t("pages.settings.staffList.staffList.positionSalaryTitle")
          }}</span>
        </VCardTitle>
        <VCardText>
          {{
            $t("pages.settings.staffList.staffList.positionSalaryConfirm", {
              position: pendingPosition,
              salary: formatSalaryValue(pendingPositionSalary),
            })
          }}
        </VCardText>
        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn variant="tonal" @click="cancelPositionSalary">
            {{ $t("common.actions.cancel") }}
          </VBtn>
          <VBtn color="primary" @click="confirmPositionSalary">
            {{ $t("common.actions.confirm") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Staff Dialog -->
    <VDialog v-model="isDeleteStaffDialogVisible" max-width="500px">
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">{{
            $t("common.messages.confirmDelete")
          }}</span>
        </VCardTitle>

        <VCardText>
          {{
            $t("common.confirmations.areYouSureYouWantToDeleteThisStaffRecord")
          }}
          <br />
          {{ $t("common.confirmations.thisActionCannotBeUndone") }}
        </VCardText>

        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isDeleteStaffDialogVisible = false"
          >
            {{ $t("common.actions.cancel") }}
          </VBtn>
          <VBtn color="error" :loading="isLoading" @click="deleteStaff">
            {{ $t("common.actions.delete") }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <AttachmentsDialog
      v-model="isAttachmentsOpen"
      type="employees"
      :record-id="attachmentTarget?.id ?? null"
      :title="attachmentTarget?.name || $t('common.attachments.title')"
    />
  </div>
</template>

<style scoped>
.employee-account-select :deep(.v-field) {
  background: rgba(var(--v-theme-on-surface), 0.035);
  border: 1px solid rgba(var(--v-theme-on-surface), 0.16);
  border-radius: 12px;
}
.employee-account-select :deep(.v-field--focused) {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), 0.12);
}
.account-selection {
  display: grid;
  line-height: 1.2;
  min-width: 0;
}
.account-selection__name {
  font-weight: 650;
  overflow: hidden;
  text-overflow: ellipsis;
}
.account-selection__meta,
.account-option__id {
  color: rgba(var(--v-theme-on-surface), 0.62);
  font-size: 0.75rem;
}
:global(.account-option) {
  margin: 4px 6px;
  border-radius: 10px;
}
:global(.account-option:hover) {
  background: rgba(var(--v-theme-primary), 0.1);
}
:global(.account-option__details) {
  display: grid;
  gap: 2px;
  margin-top: 2px;
  color: rgba(var(--v-theme-on-surface), 0.64);
  font-size: 0.75rem;
}
</style>

<style scoped>
.staff-table :deep(tbody td) {
  padding-block: 0.875rem;
}

.table-container {
  overflow-x: auto;
  scrollbar-width: none;
}

.table-container::-webkit-scrollbar,
.staff-table :deep(.v-data-table__wrapper::-webkit-scrollbar) {
  display: none;
}

.staff-table :deep(.v-data-table__wrapper) {
  overflow-x: auto;
  scrollbar-width: none;
}

.staff-table :deep(.v-data-table__wrapper > table) {
  min-inline-size: 1180px;
}

@media (max-width: 599px) {
  .staff-table :deep(.v-data-table__wrapper > table) {
    min-inline-size: 980px;
  }
}
</style>
