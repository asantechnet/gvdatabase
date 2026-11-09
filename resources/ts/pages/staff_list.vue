<script setup lang="ts">
import { formatActiveMoney, translate } from '@/utils/localization'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { VDialog } from 'vuetify/components'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import EmployeeApi, { type CreateEmployeeData, type Employee, type EmployeeAccount, type EmployeeParams, type UpdateEmployeeData } from '@/api/employee'
import PositionApi, { type Position } from '@/api/position'
import CurrencyApi, { type Currency } from '@/api/currency'
import OfficeApi, { type Office } from '@/api/office'
import DepartmentApi, { type Department } from '@/api/department'
import { apiErrorMessage, apiFieldErrors } from '@/utils/apiErrors'
import { printDocument } from '@/utils/printDocument'
import { canAccess } from '@/utils/authorization'
import { useCookie } from '@core/composable/useCookie'
import PrintableListReport from '@/components/PrintableListReport.vue'
import ResponsiveDataTableServer from '@/components/responsive-data-table/ResponsiveDataTableServer.vue'
import AttachmentsDialog from '@/components/attachments/AttachmentsDialog.vue'
import ContactActionMenu from '@/components/ContactActionMenu.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpField from '@/components/erp/ErpField.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import StaffFormFields from '@/components/hr/StaffFormFields.vue'
import EmployeePaidTimeOffDialog from '@/components/paid-time-off/EmployeePaidTimeOffDialog.vue'
import { confirmActionWarning } from '@/composables/useActionWarning'
import { useAcademyEnabled } from '@/composables/useAcademyEnabled'

interface Staff {
  id: number
  user_id: number | null
  name: string
  father_name: string
  contact: string
  emergency_contact_name: string | null
  emergency_contact_phone: string | null
  id_number: string | null
  bank_account_number: string | null
  guarantor_name: string | null
  guarantor_id_number: string | null
  guarantor_phone: string | null
  guarantor_address: string | null
  guarantor_licence: string | null
  education_lvl: string
  position: string
  status: 'active' | 'inactive'
  email: string
  office_id: number | null
  office: Employee['office']
  departments?: Array<{ id: number; name: string }>
  department_ids?: number[]
  salary: number | null
  total_worked_hours: number
  addr: string
  join_date: string
  off_day_allowance_days: number | null
  is_commission_based: boolean
  service_commissions?: Array<{ service: string; percentage: number }>
  document?: Employee['document']
  avatar?: string | null
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
const isStaffDetailsDialogVisible = ref(false);
const isStaffDetailsLoading = ref(false);
const staffDetails = ref<Staff | null>(null);
const staffWizardStep = ref<1 | 2 | 3>(1);
const staffInitialForm = ref<Staff | null>(null);
const isDiscardingStaffChanges = ref(false);
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
const selectedStaff = ref<Staff | null>(null);
const staffForm = computed<Staff>(() => selectedStaff.value as Staff);
const searchQuery = ref("");
const filterPosition = ref<string | null>(null);
// Server-side status filter. The backend returns active employees only unless
// a management surface explicitly asks for inactive/all rows.
const statusFilter = ref<"all" | "active" | "inactive">("active");
const filterMenuOpen = ref(false);
const filterDraft = reactive({
  position: null as string | null,
  status: "active" as "all" | "active" | "inactive",
});
const statusFilterOptions = computed(() => [
  { title: translate("common.statuses.all"), value: "all" as const },
  { title: translate("common.statuses.active"), value: "active" as const },
  { title: translate("common.statuses.inactive"), value: "inactive" as const },
]);
// Start in the loading state so the table never flashes its empty state before
// the first directory request has a chance to resolve.
const isLoading = ref(true);
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

const offices = ref<Office[]>([])
const departments = ref<Department[]>([])
const userAccounts = ref<EmployeeAccount[]>([])
const isUserAccountsLoading = ref(false)
const formSubmissionError = ref('')
const userData = useCookie<{ employee?: { id?: number } | null } | null>('userData')
const leaveStaff = ref<Pick<Staff, 'id' | 'name'> | null>(null)
const isLeaveDialogVisible = ref(false)
const pendingStaffAction = ref<{ staff: Staff; type: 'delete' | 'status' } | null>(null)
const isStaffActionDialogVisible = ref(false)
// Kept only for the retired markup below while the page transitions to the
// reusable EmployeePaidTimeOffDialog. It is never mounted.
const isDeleteStaffDialogVisible = ref(false)
const leaveYear = ref(new Date().getFullYear())
const leaveAllowance = ref(0)
const leaveUsed = ref(0)
const leaveRemaining = ref(0)
const leaveRequests = ref<any[]>([])
const leaveError = ref('')
const isLeaveLoading = ref(false)
const leaveForm = ref({ id: null as number | null, start_date: '', end_date: '', duration_days: 1 as 0.5 | 1, reason: '' })
const requestedLeaveDays = computed(() => 0)
const isLeaveFormComplete = computed(() => false)
const canSaveLeave = computed(() => false)
const leaveLimitMessage = computed(() => '')
const changeLeaveYear = async () => {}
const saveLeave = async () => {}
const resetLeaveForm = () => {}
const editLeaveRequest = (_record: any) => {}
const deleteLeave = async (_id: number) => {}
const leaveRequestDetailFields = computed(() => [])
const attachmentTarget = ref<Staff | null>(null);
const isAttachmentsOpen = ref(false);

function openAttachments(staff: Staff) {
  attachmentTarget.value = staff;
  isAttachmentsOpen.value = true;
}

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
  { title: translate('common.messages.id'), key: 'id' },
  { title: translate('common.fields.name'), key: 'name' },
  { title: translate('common.messages.contact'), key: 'contact' },
  { title: translate('common.messages.position'), key: 'position' },
  { title: translate('common.fields.departments'), key: 'departments' },
  { title: translate('pages.settings.staffList.staffList.salary'), key: 'salary' },
  { title: translate('pages.settings.staffList.staffList.joinDate'), key: 'join_date' },
  { title: translate('common.fields.status'), key: 'status' },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false },
])

const staffDetailFields = computed(() => [
  { key: 'name', label: translate('common.fields.name') },
  { key: 'id_number', label: translate('common.messages.governmentIDNumber') },
  { key: 'contact', label: translate('common.messages.contact') },
  { key: 'email', label: translate('common.fields.email') },
  { key: 'emergency_contact_name', label: translate('pages.settings.staffList.staffList.emergencyContactName') },
  { key: 'emergency_contact_phone', label: translate('pages.settings.staffList.staffList.emergencyContactPhone') },
  { key: 'education_lvl', label: translate('common.messages.educationLevel') },
  { key: 'position', label: translate('common.messages.position') },
  { key: 'office.name', label: translate('common.fields.office') },
  { key: 'departments', label: translate('common.fields.departments') },
  { key: 'join_date', label: translate('common.messages.joinDate') },
  { key: 'salary', label: translate('common.messages.salary') },
  { key: 'bank_account_number', label: translate('common.ui.bankAccountNumber') },
  { key: 'addr', label: translate('common.fields.address') },
  { key: 'guarantor_name', label: translate('common.messages.guarantorName') },
  { key: 'guarantor_id_number', label: translate('common.messages.guarantorGovernmentID') },
  { key: 'guarantor_phone', label: translate('common.messages.guarantorPhone') },
  { key: 'guarantor_licence', label: translate('common.messages.guarantorLicence') },
  { key: 'guarantor_address', label: translate('common.messages.guarantorAddress') },
  { key: 'status', label: translate('common.fields.status') },
])

const printEmployeeContract = (staff: Staff) => {
  router.push({
    name: "employee-contracts",
    query: { employeeId: staff.id, print: "1" },
  });
};

const formErrors = ref({
  department_ids: '',
  name: '',
  father_name: '',
  contact: '',
  id_number: '',
  email: '',
  addr: '',
  position: '',
  salary: '',
  education_lvl: '',
  join_date: '',
})

const resetFormErrors = () => {
  formErrors.value = {
    department_ids: '',
    name: '',
    father_name: '',
    contact: '',
    id_number: '',
    email: '',
    addr: '',
    position: '',
    salary: '',
    education_lvl: '',
    join_date: '',
  }
}

const validateStaffForm = (throughStep: 1 | 2 | 3 = 3) => {
  const staff = selectedStaff.value
  if (!staff)
    return false

  let isValid = true
  const invalid = (field: keyof typeof formErrors.value, message: string) => {
    formErrors.value[field] = message
    isValid = false
  }
  const valid = (field: keyof typeof formErrors.value) => { formErrors.value[field] = '' }

  if (throughStep >= 1) {
    !staff.name.trim() ? invalid('name', translate('pages.settings.staffList.staffList.nameIsRequired'))
      : staff.name.length < 2 ? invalid('name', translate('pages.settings.staffList.staffList.nameMustBeAtLeast2Characters')) : valid('name')
    !staff.father_name.trim() ? invalid('father_name', translate('pages.settings.staffList.staffList.fatherNameIsRequired'))
      : staff.father_name.length < 2 ? invalid('father_name', translate('pages.settings.staffList.staffList.fatherNameMustBeAtLeast2Characters')) : valid('father_name')
    !staff.contact.trim() ? invalid('contact', translate('common.messages.contactNumberIsRequired'))
      : !/^[\d\s\-()+]{8,}$/.test(staff.contact) ? invalid('contact', translate('common.messages.pleaseEnterAValidPhoneNumber')) : valid('contact')
    !staff.id_number?.trim() ? invalid('id_number', translate('pages.settings.staffList.staffList.governmentIDIsRequired')) : valid('id_number')
    !staff.user_id && !staff.email.trim() ? invalid('email', translate('pages.settings.staffList.staffList.emailIsRequired'))
      : !staff.user_id && !/^[^\s@]+@[^\s@][^\s.@]*\.[^\s@]+$/.test(staff.email) ? invalid('email', translate('pages.settings.staffList.staffList.pleaseEnterAValidEmailAddress')) : valid('email')
    !staff.education_lvl ? invalid('education_lvl', translate('pages.settings.staffList.staffList.educationLevelIsRequired')) : valid('education_lvl')
  }

  if (throughStep >= 2) {
    !staff.position ? invalid('position', translate('pages.settings.staffList.staffList.positionIsRequired')) : valid('position')
    staff.salary === null || staff.salary === undefined ? invalid('salary', translate('pages.settings.staffList.staffList.salaryIsRequired'))
      : Number(staff.salary) < 0 ? invalid('salary', translate('pages.settings.staffList.staffList.salaryCannotBeNegative')) : valid('salary')
    !staff.join_date ? invalid('join_date', translate('pages.settings.staffList.staffList.joinDateIsRequired')) : valid('join_date')
  }

  if (throughStep >= 3) {
    !staff.addr.trim() ? invalid('addr', translate('pages.settings.staffList.staffList.addressIsRequired'))
      : staff.addr.length < 5 ? invalid('addr', translate('pages.settings.staffList.staffList.addressMustBeAtLeast5Characters')) : valid('addr')
  }

  return isValid
}

// Commission rates are edited per built-in service. The rates live on the
// selected employee so both the create and edit dialogs share one binding.
const { enabled: academyEnabled } = useAcademyEnabled();
const commissionServiceOptions = computed(() => [
  {
    value: "scholarship",
    title: translate("educational.builtInServices.scholarship"),
  },
  {
    value: "test_offer",
    title: translate("educational.builtInServices.testOffer"),
  },
  ...(academyEnabled.value
    ? [{ value: "academy", title: translate("educational.builtInServices.academy") }]
    : []),
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

type StaffFormMode = 'add' | 'edit'
const staffWizardSteps = computed(() => [
  { number: 1 as const, title: 'Personal & contact' },
  { number: 2 as const, title: 'Employment' },
  { number: 3 as const, title: 'Address & guarantor' },
])
const cloneStaffForm = (staff: Staff): Staff => JSON.parse(JSON.stringify(staff)) as Staff
const staffFormIsDirty = computed(() => selectedStaff.value !== null
  && staffInitialForm.value !== null
  && JSON.stringify(selectedStaff.value) !== JSON.stringify(staffInitialForm.value))

const finishStaffForm = (mode: StaffFormMode) => {
  if (mode === 'add')
    isAddStaffDialogVisible.value = false
  else
    isEditStaffDialogVisible.value = false

  staffInitialForm.value = null
  staffWizardStep.value = 1
  formSubmissionError.value = ''
}

const setCommissionEnabled = (value: boolean) => {
  if (selectedStaff.value)
    selectedStaff.value.is_commission_based = value
}

const requestStaffFormClose = async (mode: StaffFormMode) => {
  if (isLoading.value || isDiscardingStaffChanges.value)
    return

  if (staffFormIsDirty.value) {
    isDiscardingStaffChanges.value = true
    try {
      const discard = await confirmActionWarning({
        key: 'discard-form-changes',
        title: translate('common.actionWarnings.discardTitle'),
        message: translate('common.actionWarnings.discardMessage'),
        confirmText: translate('common.actionWarnings.discardConfirm'),
        cancelText: translate('common.actionWarnings.keepEditing'),
        icon: 'tabler-file-alert',
        tone: 'primary',
      })
      if (!discard)
        return
    }
    finally {
      isDiscardingStaffChanges.value = false
    }
  }

  finishStaffForm(mode)
}

const updateStaffDialog = (mode: StaffFormMode, visible: boolean) => {
  if (!visible)
    void requestStaffFormClose(mode)
}

const submitStaffWizard = (mode: StaffFormMode) => {
  if (staffWizardStep.value < 3) {
    if (validateStaffForm(staffWizardStep.value))
      staffWizardStep.value = (staffWizardStep.value + 1) as 1 | 2 | 3
    return
  }

  if (!validateStaffForm())
    return

  void (mode === 'add' ? addStaff() : updateStaff())
}

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
    departments: [],
    department_ids: [],
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
  staffWizardStep.value = 1;
  staffInitialForm.value = cloneStaffForm(selectedStaff.value);
  isAddStaffDialogVisible.value = true;
};

const canManageEmployees = computed(() => canAccess('edit_employees'))
const canAssignDepartments = computed(() => canAccess('manage_departments'))

const departmentSelectOptions = computed(() => departments.value.map(department => ({
  title: department.name,
  value: department.id,
})))

function staffDepartments(staff: Staff) {
  return staff.departments ?? []
}

// Hierarchy fields are only sent by a caller who may set them. The server
// ignores them otherwise; omitting them keeps the intent explicit.
function hierarchyPayload(): Pick<UpdateEmployeeData, 'department_ids'> | Record<string, never> {
  if (!canAssignDepartments.value)
    return {}

  return {
    department_ids: selectedStaff.value?.department_ids ?? [],
  }
}
const canEditOwnProfile = computed(() => canAccess('edit_own_employee_profile'))
const ownEmployeeId = computed(() => userData.value?.employee?.id ?? null)
const canEditStaff = (staff: Staff) => canManageEmployees.value || (canEditOwnProfile.value && staff.id === ownEmployeeId.value)
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
    department_ids: staff.department_ids ?? [],
    is_commission_based: staff.is_commission_based ?? false,
    service_commissions: staff.service_commissions ?? [],
  };
  positionBeforeChange.value = staff.position;
  resetFormErrors();
  formSubmissionError.value = "";
  staffWizardStep.value = 1;
  staffInitialForm.value = cloneStaffForm(selectedStaff.value);
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

const requestStaffAction = (staff: Staff, type: 'delete' | 'status') => {
  pendingStaffAction.value = { staff, type }
  isStaffActionDialogVisible.value = true
}

const openLeaveDialog = (staff: Staff) => {
  leaveStaff.value = { id: staff.id, name: staff.name }
  isLeaveDialogVisible.value = true
}

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

const openStaffDetails = async (staff: Staff) => {
  staffDetails.value = staff
  isStaffDetailsDialogVisible.value = true
  isStaffDetailsLoading.value = true
  try {
    // Fetch the full resource when the profile is opened so departments,
    // commissions and the current document never come from a stale list row.
    const response = await EmployeeApi.getById(staff.id)
    if (response.data?.data)
      staffDetails.value = asStaffRow(response.data.data)
  }
  catch (error) {
    toast.error(apiErrorMessage(error, translate('pages.settings.staffList.staffList.unableToSaveEmployee')))
  }
  finally {
    isStaffDetailsLoading.value = false
  }
}

const staffDetailTiles = computed(() => {
  const staff = staffDetails.value
  if (!staff)
    return []

  return [
    { key: 'contact', label: translate('common.messages.contactPhone'), value: staff.contact, icon: 'tabler-phone' },
    { key: 'email', label: translate('common.fields.email'), value: staff.email, icon: 'tabler-mail' },
    { key: 'id-number', label: translate('common.messages.governmentIDNumber'), value: staff.id_number, icon: 'tabler-id-badge-2' },
    { key: 'position', label: translate('common.messages.position'), value: staff.position, icon: 'tabler-briefcase' },
    { key: 'office', label: translate('common.fields.office'), value: staff.office?.name, icon: 'tabler-building' },
    { key: 'departments', label: translate('common.fields.departments'), value: staffDepartments(staff).map(row => row.name).join(', '), icon: 'tabler-sitemap' },
    { key: 'join-date', label: translate('common.messages.joinDate'), value: staff.join_date, icon: 'tabler-calendar-event' },
    { key: 'salary', label: translate('common.messages.salary'), value: formatSalary(staff), icon: 'tabler-cash' },
    { key: 'education', label: translate('common.messages.educationLevel'), value: staff.education_lvl, icon: 'tabler-school' },
    { key: 'address', label: translate('common.fields.address'), value: staff.addr, icon: 'tabler-map-pin' },
    { key: 'bank', label: translate('common.ui.bankAccountNumber'), value: staff.bank_account_number, icon: 'tabler-building-bank' },
    { key: 'guarantor', label: translate('common.messages.guarantorName'), value: staff.guarantor_name, icon: 'tabler-shield-check' },
    { key: 'guarantor-phone', label: translate('common.messages.guarantorPhone'), value: staff.guarantor_phone, icon: 'tabler-phone-call' },
  ]
})

const staffCommissionSummary = computed(() => (staffDetails.value?.service_commissions ?? [])
  .filter(row => Number(row.percentage) > 0)
  .map(row => `${row.service}: ${row.percentage}%`)
  .join(' · '))

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

const hasActiveFilters = computed(() => Boolean(
  filterPosition.value || statusFilter.value !== 'active',
))

const syncFilterDraft = () => {
  filterDraft.position = filterPosition.value
  filterDraft.status = statusFilter.value
}

const applyStaffFilters = () => {
  filterPosition.value = filterDraft.position
  statusFilter.value = filterDraft.status
  reloadStaff()
}

watch(filterMenuOpen, open => {
  if (open)
    syncFilterDraft()
})

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
      ...hierarchyPayload(),
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
    finishStaffForm('add');
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
      ...hierarchyPayload(),
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
    finishStaffForm('edit');
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
    // Nested collection errors arrive as `department_ids.0`; the field itself
    // owns the message so the control can show it.
    const normalized = field.startsWith('department_ids') ? 'department_ids' : field
    const target = fieldMap[normalized] ?? normalized
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
  const staff = pendingStaffAction.value?.staff
  if (!staff) return;

  try {
    isLoading.value = true;
    await EmployeeApi.delete(staff.id);
    staffList.value = staffList.value.filter(
      (s) => s.id !== staff.id,
    );
    total.value = Math.max(0, total.value - 1)
    isStaffActionDialogVisible.value = false;
    pendingStaffAction.value = null
    toast.success(translate('pages.settings.staffList.staffList.deleteStaffRecord'))
  } catch (error) {
    console.error("Error deleting employee:", error);
    toast.error(apiErrorMessage(error, translate('pages.settings.staffList.staffList.unableToSaveEmployee')))
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
  } finally {
    isLoading.value = false;
  }
};

const confirmStaffAction = async () => {
  const action = pendingStaffAction.value
  if (!action)
    return

  if (action.type === 'delete') {
    await deleteStaff()
    return
  }

  await toggleStaffStatus(action.staff)
  isStaffActionDialogVisible.value = false
  pendingStaffAction.value = null
}

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
  staff.office?.name ?? '—',
  staffDepartments(staff).map(department => department.name).filter(Boolean).join(', ') || '—',
]

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
  }
};

onMounted(async () => {
  const [, officeRows, departmentRows] = await Promise.all([fetchPositions(), OfficeApi.getAll(), DepartmentApi.getAll(), fetchUserAccounts(), fetchBaseCurrency()])

  offices.value = officeRows.filter(office => office.status === 'active')
  departments.value = departmentRows
  await fetchEmployees()
})
</script>

<template>
  <div class="erp-workspace staff-workspace">
    <ErpPageHeader :title="$t('common.navigation.staffList')" section="hr" />
    <VCard>
      <VCardText>
        <VRow align="center" class="table-toolbar">
          <VCol cols="12" md="6" lg="5">
            <ErpSearchField
              v-model="searchQuery"
              :placeholder="$t('pages.settings.staffList.staffList.searchStaff')"
              @input="debouncedStaffSearch"
            />
          </VCol>
          <VCol cols="12" md="6" lg="7" class="table-toolbar-actions d-flex flex-nowrap justify-end gap-2">
            <DataTableFilterMenu v-model="filterMenuOpen" :active="hasActiveFilters" :show-reset="false">
              <ErpField v-model="filterDraft.position" kind="select" :items="positionOptions" :label="$t('pages.settings.staffList.staffList.filterByPosition')" placeholder="All positions" clearable :loading="isPositionsLoading" />
              <ErpField v-model="filterDraft.status" kind="select" :items="statusFilterOptions" :label="$t('common.fields.status')" />
              <template #actions>
                <VBtn class="staff-filter-reset" variant="flat" @click="resetStaffFilters">{{ $t('common.messages.reset') }}</VBtn>
                <VBtn color="primary" variant="flat" class="staff-filter-apply" @click="applyStaffFilters">{{ $t('educational.apply') }}</VBtn>
              </template>
            </DataTableFilterMenu>
            <VBtn icon="tabler-printer" color="primary" variant="text" :aria-label="$t('common.actions.print')" @click="printTable" />
            <VBtn color="primary" :disabled="isLoading" @click="openAddStaffDialog"><VIcon icon="tabler-plus" class="me-1" />{{ $t('pages.settings.staffList.staffList.addStaff') }}</VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard class="staff-list-table-card mt-4">
      <ResponsiveDataTableServer
          :items="staffList"
          :items-length="total"
          :page="page"
          :items-per-page="perPage"
          :items-per-page-options="[10, 25, 50, 100]"
          :headers="staffHeaders"
          item-value="id"
          class="staff-responsive-table"
          mobile-breakpoint="sm"
          mobile-cards
          mobile-expand-all
          mobile-actions-as-menu
          mobile-title-key="name"
          mobile-action-key="actions"
          :mobile-fields="['position', 'contact', 'status']"
          :sticky-key-column="false"
          :sticky-action-column="false"
          :skeleton-rows="5"
          :show-scroll-affordance="false"
          :loading="isLoading"
          pagination
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

          <template #item.departments="{ item }">
            <div class="d-flex flex-wrap ga-1">
              <VChip
                v-for="department in staffDepartments(item)"
                :key="department.id"
                size="small"
                variant="tonal"
              >
                {{ department.name }}
              </VChip>
              <span v-if="!staffDepartments(item).length">—</span>
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
            <div class="d-flex align-center ga-1 justify-end">
              <VBtn icon="tabler-file-description" variant="text" size="small" color="primary" :aria-label="$t('common.ui.printEmploymentContract')" @click="printEmployeeContract(item)">
                <VTooltip activator="parent" location="top">{{ $t('common.ui.printEmploymentContract') }}</VTooltip>
              </VBtn>
              <VMenu location="bottom end">
                <template #activator="{ props: menuProps }">
                  <VBtn v-bind="menuProps" icon="tabler-dots-vertical" variant="tonal" size="small" class="staff-actions-trigger" :aria-label="$t('common.messages.actions')" />
                </template>
                <VList class="staff-actions-menu" density="compact" min-width="230">
                  <VListItem prepend-icon="tabler-address-book" :title="$t('common.actions.viewFullDetails')" @click="openStaffDetails(item)" />
                  <VListItem prepend-icon="tabler-calendar-time" :title="$t('pages.settings.staffList.staffList.managePaidOffDays')" @click="openLeaveDialog(item)" />
                  <VListItem prepend-icon="tabler-paperclip" :title="$t('common.attachments.title')" @click="openAttachments(item)" />
                  <VDivider class="my-1" />
                  <VListItem v-if="canEditStaff(item)" prepend-icon="tabler-edit" :title="$t('pages.settings.staffList.staffList.editStaffRecord')" @click="openEditStaffDialog(item)" />
                  <VListItem :prepend-icon="(item.status ?? 'active') === 'active' ? 'tabler-user-cancel' : 'tabler-user-check'" :title="$t((item.status ?? 'active') === 'active' ? 'pages.settings.staffList.staffList.deactivateStaffRecord' : 'pages.settings.staffList.staffList.activateStaffRecord')" @click="requestStaffAction(item, 'status')" />
                  <VListItem prepend-icon="tabler-trash" class="staff-actions-menu__delete" :title="$t('pages.settings.staffList.staffList.deleteStaffRecord')" @click="requestStaffAction(item, 'delete')" />
                </VList>
              </VMenu>
            </div>
          </template>
          <template #no-data>
            <div class="staff-empty-state">
              <TableEmptyState icon="tabler-user-off" :title="$t('common.navigation.staffList')" :description="$t('educational.noStaff')" :min-height="0" />
              <VBtn color="primary" variant="tonal" class="staff-empty-state__action" @click="openAddStaffDialog"><VIcon icon="tabler-plus" class="me-1" />{{ $t('pages.settings.staffList.staffList.addStaff') }}</VBtn>
            </div>
          </template>
        </ResponsiveDataTableServer>
    </VCard>

    <PrintableListReport
      :title="$t('common.crmUi.printReports.staff.title')"
      :subtitle="$t('common.crmUi.printReports.staff.subtitle')"
      :columns="[$t('common.crmUi.printReports.columns.id'), $t('common.crmUi.printReports.columns.name'), $t('common.crmUi.printReports.columns.position'), $t('common.crmUi.printReports.columns.contact'), $t('common.crmUi.printReports.columns.email'), $t('common.crmUi.printReports.columns.office'), $t('common.fields.departments')]"
      :rows="reportRows"
    />

    <VDialog
      :model-value="isAddStaffDialogVisible"
      :persistent="isLoading"
      max-width="980"
      content-class="staff-form-dialog"
      scroll-strategy="block"
      @update:model-value="updateStaffDialog('add', $event)"
    >
      <VCard v-if="selectedStaff" class="staff-form-card staff-form-card--wizard">
        <VCardTitle class="staff-dialog-title px-5 py-5">
          <span class="text-h5 staff-dialog-heading staff-dialog-heading--primary">{{ $t('pages.settings.staffList.staffList.addNewStaff') }}</span>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="error" class="staff-dialog-close" :aria-label="$t('common.actions.close')" :disabled="isLoading" @click="requestStaffFormClose('add')" />
        </VCardTitle>
        <div class="staff-wizard-progress" role="list" aria-label="Staff profile steps">
          <button v-for="step in staffWizardSteps" :key="step.number" type="button" class="staff-wizard-progress__step" :class="{ 'staff-wizard-progress__step--active': staffWizardStep === step.number, 'staff-wizard-progress__step--complete': staffWizardStep > step.number }" :disabled="step.number > staffWizardStep || isLoading" :aria-current="staffWizardStep === step.number ? 'step' : undefined" @click="step.number < staffWizardStep && (staffWizardStep = step.number)">
            <span class="staff-wizard-progress__indicator"><VIcon v-if="staffWizardStep > step.number" icon="tabler-check" size="15" /><span v-else>{{ step.number }}</span></span>
            <span class="staff-wizard-progress__label">{{ step.title }}</span>
          </button>
        </div>
        <VForm class="app-form staff-form-wizard" @submit.prevent="submitStaffWizard('add')">
          <VCardText class="staff-dialog-body pa-5 pa-sm-6">
            <VAlert v-if="formSubmissionError" type="error" variant="tonal" class="mb-4">{{ formSubmissionError }}</VAlert>
            <Transition name="staff-wizard-step" mode="out-in">
              <StaffFormFields
                :key="staffWizardStep"
                v-model="selectedStaff"
                :step="staffWizardStep"
                :errors="formErrors"
                :education-options="educationOptions"
                :position-options="positionOptions"
                :offices="offices"
                :departments="departments"
                :accounts="userAccounts"
                :is-positions-loading="isPositionsLoading"
                :is-accounts-loading="isUserAccountsLoading"
                :can-assign-departments="canAssignDepartments"
                :commission-options="commissionServiceOptions"
                :commission-rate="commissionRate"
                @position-change="applyPositionSalary"
                @account-change="updateSelectedAccountEmail"
                @commission-enabled="setCommissionEnabled"
                @commission-rate-change="(service, value) => setCommissionRate(service, value)"
              />
            </Transition>
          </VCardText>
          <VCardActions class="staff-dialog-actions staff-wizard-footer px-5 py-4">
            <VBtn v-if="staffWizardStep === 1" variant="flat" color="error" class="staff-dialog-cancel" :disabled="isLoading" @click="requestStaffFormClose('add')">{{ $t('common.actions.cancel') }}</VBtn>
            <VBtn v-else variant="tonal" color="secondary" prepend-icon="tabler-arrow-left" :disabled="isLoading" @click="staffWizardStep = (staffWizardStep - 1) as 1 | 2 | 3">{{ $t('pages.contracts.visitor.visitor.wizard.back') }}</VBtn>
            <VSpacer />
            <VBtn v-if="staffWizardStep < 3" type="submit" color="primary" variant="flat" class="staff-dialog-submit" append-icon="tabler-arrow-right" :disabled="isLoading">{{ $t('pages.contracts.visitor.visitor.wizard.next') }}</VBtn>
            <VBtn v-else type="submit" color="primary" variant="flat" class="staff-dialog-submit" prepend-icon="tabler-check" :loading="isLoading" :disabled="isLoading">{{ $t('common.actions.add') }}</VBtn>
          </VCardActions>
        </VForm>
      </VCard>
    </VDialog>

    <VDialog
      :model-value="isEditStaffDialogVisible"
      :persistent="isLoading"
      max-width="980"
      content-class="staff-form-dialog"
      scroll-strategy="block"
      @update:model-value="updateStaffDialog('edit', $event)"
    >
      <VCard v-if="selectedStaff" class="staff-form-card staff-form-card--wizard">
        <VCardTitle class="staff-dialog-title px-5 py-5">
          <span class="text-h5 staff-dialog-heading">{{ $t('pages.settings.staffList.staffList.editStaff') }}</span>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="error" class="staff-dialog-close" :aria-label="$t('common.actions.close')" :disabled="isLoading" @click="requestStaffFormClose('edit')" />
        </VCardTitle>
        <div class="staff-wizard-progress" role="list" aria-label="Staff profile steps">
          <button v-for="step in staffWizardSteps" :key="step.number" type="button" class="staff-wizard-progress__step" :class="{ 'staff-wizard-progress__step--active': staffWizardStep === step.number, 'staff-wizard-progress__step--complete': staffWizardStep > step.number }" :disabled="step.number > staffWizardStep || isLoading" :aria-current="staffWizardStep === step.number ? 'step' : undefined" @click="step.number < staffWizardStep && (staffWizardStep = step.number)">
            <span class="staff-wizard-progress__indicator"><VIcon v-if="staffWizardStep > step.number" icon="tabler-check" size="15" /><span v-else>{{ step.number }}</span></span>
            <span class="staff-wizard-progress__label">{{ step.title }}</span>
          </button>
        </div>
        <VForm class="app-form staff-form-wizard" @submit.prevent="submitStaffWizard('edit')">
          <VCardText class="staff-dialog-body pa-5 pa-sm-6">
            <VAlert v-if="formSubmissionError" type="error" variant="tonal" class="mb-4">{{ formSubmissionError }}</VAlert>
            <Transition name="staff-wizard-step" mode="out-in">
              <StaffFormFields
                :key="staffWizardStep"
                v-model="selectedStaff"
                :step="staffWizardStep"
                :errors="formErrors"
                :education-options="educationOptions"
                :position-options="positionOptions"
                :offices="offices"
                :departments="departments"
                :accounts="userAccounts"
                :is-positions-loading="isPositionsLoading"
                :is-accounts-loading="isUserAccountsLoading"
                :can-assign-departments="canAssignDepartments"
                :commission-options="commissionServiceOptions"
                :commission-rate="commissionRate"
                @position-change="applyPositionSalary"
                @account-change="updateSelectedAccountEmail"
                @commission-enabled="setCommissionEnabled"
                @commission-rate-change="(service, value) => setCommissionRate(service, value)"
              />
            </Transition>
          </VCardText>
          <VCardActions class="staff-dialog-actions staff-wizard-footer px-5 py-4">
            <VBtn v-if="staffWizardStep === 1" variant="flat" color="error" class="staff-dialog-cancel" :disabled="isLoading" @click="requestStaffFormClose('edit')">{{ $t('common.actions.cancel') }}</VBtn>
            <VBtn v-else variant="tonal" color="secondary" prepend-icon="tabler-arrow-left" :disabled="isLoading" @click="staffWizardStep = (staffWizardStep - 1) as 1 | 2 | 3">{{ $t('pages.contracts.visitor.visitor.wizard.back') }}</VBtn>
            <VSpacer />
            <VBtn v-if="staffWizardStep < 3" type="submit" color="primary" variant="flat" class="staff-dialog-submit" append-icon="tabler-arrow-right" :disabled="isLoading">{{ $t('pages.contracts.visitor.visitor.wizard.next') }}</VBtn>
            <VBtn v-else type="submit" color="primary" variant="flat" class="staff-dialog-submit" prepend-icon="tabler-check" :loading="isLoading" :disabled="isLoading">{{ $t('common.actions.save') }}</VBtn>
          </VCardActions>
        </VForm>
      </VCard>
    </VDialog>

    <VDialog v-model="isStaffDetailsDialogVisible" max-width="1040" content-class="staff-form-dialog staff-details-dialog" scrollable>
      <VCard class="staff-form-card">
        <VCardTitle class="staff-dialog-title px-5 py-5"><div><div class="staff-details-dialog__eyebrow">{{ $t('common.actions.viewFullDetails') }}</div><div class="staff-dialog-heading">{{ staffDetails?.name }}</div></div><VSpacer /><VBtn icon="tabler-x" variant="tonal" color="error" class="staff-dialog-close" :aria-label="$t('common.actions.close')" @click="isStaffDetailsDialogVisible = false" /></VCardTitle>
        <VCardText class="staff-dialog-body staff-details-dialog__body pa-5 pa-sm-6">
          <div v-if="isStaffDetailsLoading" class="staff-details-skeleton" aria-busy="true">
            <div class="staff-details-skeleton__summary"><VSkeletonLoader type="avatar" /><div><VSkeletonLoader type="heading" class="mb-2" /><VSkeletonLoader type="text" /></div></div>
            <div class="staff-details-grid"><VSkeletonLoader v-for="index in 10" :key="index" type="list-item-two-line" class="staff-detail-tile staff-detail-tile--skeleton" /></div>
          </div>
          <template v-else>
            <div class="staff-details-summary mb-5"><VAvatar size="56" :image="staffDetails?.avatar || undefined" color="primary" variant="tonal">{{ staffDetails ? avatarText(staffDetails.name) : '' }}</VAvatar><div><div class="text-h6 font-weight-bold">{{ staffDetails?.position || '—' }}</div><div class="text-body-2 text-medium-emphasis">{{ staffDetails?.office?.name || $t('common.fields.office') }} · {{ staffDetails?.status === 'inactive' ? $t('common.statuses.inactive') : $t('common.statuses.active') }}</div></div></div>
            <div class="staff-details-grid"><div v-for="field in staffDetailTiles" :key="field.key" class="staff-detail-tile"><VIcon :icon="field.icon" size="19" class="staff-detail-tile__icon" /><div class="staff-detail-tile__content"><span class="staff-detail-tile__label">{{ field.label }}</span><div v-if="field.key === 'contact' && staffDetails?.contact" dir="ltr" class="staff-contact-value"><ContactActionMenu kind="phone" :value="staffDetails.contact" audience="employee" :context="staffDetails" /></div><div v-else-if="field.key === 'email' && staffDetails?.email" dir="ltr" class="staff-contact-value"><ContactActionMenu kind="email" :value="staffDetails.email" :context="staffDetails" /></div><span v-else class="staff-detail-tile__value">{{ field.value || '—' }}</span></div></div><div v-if="staffCommissionSummary" class="staff-detail-tile staff-detail-tile--wide"><VIcon icon="tabler-percentage" size="19" class="staff-detail-tile__icon" /><div class="staff-detail-tile__content"><span class="staff-detail-tile__label">{{ $t('pages.settings.staffList.staffList.commissionPerService') }}</span><span class="staff-detail-tile__value">{{ staffCommissionSummary }}</span></div></div></div>
          </template>
        </VCardText>
      </VCard>
    </VDialog>

    <VDialog v-model="isStaffActionDialogVisible" max-width="480" class="staff-action-overlay">
      <VCard class="staff-action-dialog"><VCardText class="staff-action-dialog__content"><div class="staff-action-dialog__icon" :class="{ 'staff-action-dialog__icon--danger': pendingStaffAction?.type === 'delete' || pendingStaffAction?.staff.status === 'active' }"><VIcon :icon="pendingStaffAction?.type === 'delete' ? 'tabler-trash' : pendingStaffAction?.staff.status === 'active' ? 'tabler-user-cancel' : 'tabler-user-check'" size="30" /></div><h2 class="staff-action-dialog__title">{{ pendingStaffAction?.type === 'delete' ? $t('common.messages.confirmDelete') : $t(pendingStaffAction?.staff.status === 'active' ? 'pages.settings.staffList.staffList.deactivateStaffRecord' : 'pages.settings.staffList.staffList.activateStaffRecord') }}</h2><p class="staff-action-dialog__description">{{ pendingStaffAction?.type === 'delete' ? $t('common.confirmations.areYouSureYouWantToDeleteThisStaffRecord') : pendingStaffAction?.staff.status === 'active' ? `${pendingStaffAction?.staff.name} will be removed from active staff lists and cannot receive new work until reactivated.` : `${pendingStaffAction?.staff.name} will return to active staff lists and can be assigned work again.` }}</p></VCardText><VCardActions class="staff-action-dialog__actions"><VBtn class="staff-action-dialog__button staff-action-dialog__button--cancel" :disabled="isLoading" @click="isStaffActionDialogVisible = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn class="staff-action-dialog__button" :class="{ 'staff-action-dialog__button--delete': pendingStaffAction?.type === 'delete', 'staff-action-dialog__button--destructive': pendingStaffAction?.type === 'status' && pendingStaffAction?.staff.status === 'active' }" :loading="isLoading" @click="confirmStaffAction">{{ pendingStaffAction?.type === 'delete' ? $t('common.actions.delete') : $t('common.actions.confirm') }}</VBtn></VCardActions></VCard>
    </VDialog>

    <EmployeePaidTimeOffDialog v-model="isLeaveDialogVisible" :employee="leaveStaff" />

    <!-- Add Staff Dialog -->
    <VDialog v-if="false" v-model="isAddStaffDialogVisible" max-width="1100px" scrollable>
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
                  v-model="staffForm.name"
                  :label="$t('common.messages.fullName')"
                  :error-messages="formErrors.name"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="staffForm.father_name"
                  :label="$t('common.messages.fatherName')"
                  :error-messages="formErrors.father_name"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="staffForm.contact"
                  :label="$t('common.fields.phone')"
                  :error-messages="formErrors.contact"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="staffForm.emergency_contact_name"
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
                  v-model="staffForm.emergency_contact_phone"
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
                  v-model="staffForm.user_id"
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
                                name: item.raw.employee!.name,
                                id: item.raw.employee!.id,
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
                  v-model="staffForm.id_number"
                  :label="$t('common.messages.governmentIDNumber')"
                  :error-messages="formErrors.id_number"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="staffForm.email"
                  :label="$t('common.fields.email')"
                  type="email"
                  :error-messages="formErrors.email"
                  :required="!staffForm.user_id"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="staffForm.education_lvl"
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
                  v-model="staffForm.position"
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
                  v-model="staffForm.join_date"
                  :label="$t('common.messages.joinDate')"
                  :error-messages="formErrors.join_date"
                  required
                  :config="{ dateFormat: 'Y-m-d' }"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="staffForm.off_day_allowance_days"
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
                  v-model="staffForm.office_id"
                  :items="offices"
                  item-title="name"
                  item-value="id"
                  :label="$t('common.fields.office')"
                  clearable
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VAutocomplete
                  v-model="staffForm.department_ids"
                  :items="departmentSelectOptions"
                  item-title="title"
                  item-value="value"
                  :label="$t('common.fields.departments')"
                  :disabled="!canAssignDepartments"
                  :error-messages="formErrors.department_ids"
                  multiple
                  chips
                  closable-chips
                  clearable
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="staffForm.salary"
                  :label="$t('common.messages.salary')"
                  type="number"
                  min="0"
                  :error-messages="formErrors.salary"
                  required
                />
              </VCol>
              <StaffCommissionFields
                v-model:enabled="staffForm.is_commission_based"
                :options="commissionServiceOptions"
                :rate="commissionRate"
                @update:rate="setCommissionRate"
              />
              <VCol cols="12" md="4">
                <VTextField
                  v-model="staffForm.bank_account_number"
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
                  v-model="staffForm.guarantor_name"
                  :label="$t('common.messages.guarantorName')"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="staffForm.guarantor_id_number"
                  :label="$t('common.messages.guarantorGovernmentID')"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="staffForm.guarantor_phone"
                  :label="$t('common.messages.guarantorPhone')"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="staffForm.guarantor_licence"
                  :label="$t('common.messages.guarantorLicence')"
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="staffForm.guarantor_address"
                  :label="$t('common.messages.guarantorAddress')"
                  rows="2"
                  auto-grow
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="staffForm.addr"
                  :label="$t('common.fields.address')"
                  :error-messages="formErrors.addr"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="staffForm.status"
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
    <VDialog v-if="false" v-model="isEditStaffDialogVisible" max-width="1100px" scrollable>
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
                  v-model="staffForm.name"
                  :label="$t('common.messages.fullName')"
                  :error-messages="formErrors.name"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="staffForm.father_name"
                  :label="$t('common.messages.fatherName')"
                  :error-messages="formErrors.father_name"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="staffForm.contact"
                  :label="$t('common.fields.phone')"
                  :error-messages="formErrors.contact"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="staffForm.emergency_contact_name"
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
                  v-model="staffForm.emergency_contact_phone"
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
                  v-model="staffForm.user_id"
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
                                name: item.raw.employee!.name,
                                id: item.raw.employee!.id,
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
                  v-model="staffForm.id_number"
                  :label="$t('common.messages.governmentIDNumber')"
                  :error-messages="formErrors.id_number"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="staffForm.email"
                  :label="$t('common.fields.email')"
                  type="email"
                  :error-messages="formErrors.email"
                  :required="!staffForm.user_id"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="staffForm.education_lvl"
                  :items="educationOptions"
                  :label="$t('common.messages.educationLevel')"
                  :error-messages="formErrors.education_lvl"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="staffForm.position"
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
                  v-model="staffForm.join_date"
                  :label="$t('common.messages.joinDate')"
                  :error-messages="formErrors.join_date"
                  required
                  :config="{ dateFormat: 'Y-m-d' }"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="staffForm.off_day_allowance_days"
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
                  v-model="staffForm.office_id"
                  :items="offices"
                  item-title="name"
                  item-value="id"
                  :label="$t('common.fields.office')"
                  clearable
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VAutocomplete
                  v-model="staffForm.department_ids"
                  :items="departmentSelectOptions"
                  item-title="title"
                  item-value="value"
                  :label="$t('common.fields.departments')"
                  :disabled="!canAssignDepartments"
                  :error-messages="formErrors.department_ids"
                  multiple
                  chips
                  closable-chips
                  clearable
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model.number="staffForm.salary"
                  :label="$t('common.messages.salary')"
                  type="number"
                  min="0"
                  :error-messages="formErrors.salary"
                  required
                />
              </VCol>
              <StaffCommissionFields
                v-model:enabled="staffForm.is_commission_based"
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
                  v-model="staffForm.bank_account_number"
                  :label="$t('common.ui.bankAccountNumber')"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="staffForm.guarantor_name"
                  :label="$t('common.messages.guarantorName')"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="staffForm.guarantor_id_number"
                  :label="$t('common.messages.guarantorGovernmentID')"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="staffForm.guarantor_phone"
                  :label="$t('common.messages.guarantorPhone')"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="staffForm.guarantor_licence"
                  :label="$t('common.messages.guarantorLicence')"
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="staffForm.guarantor_address"
                  :label="$t('common.messages.guarantorAddress')"
                  rows="2"
                  auto-grow
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="staffForm.addr"
                  :label="$t('common.fields.address')"
                  :error-messages="formErrors.addr"
                  required
                />
              </VCol>
              <VCol cols="12" md="4">
                <VSelect
                  v-model="staffForm.status"
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
      v-if="false"
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
      max-width="480"
      class="staff-action-overlay"
      @update:model-value="handlePositionSalaryDialog"
    >
      <VCard class="staff-action-dialog">
        <VCardText class="staff-action-dialog__content">
          <div class="staff-action-dialog__icon staff-action-dialog__icon--warning"><VIcon icon="tabler-cash" size="30" /></div>
          <h2 class="staff-action-dialog__title">{{ $t("pages.settings.staffList.staffList.positionSalaryTitle") }}</h2>
          <p class="staff-action-dialog__description">{{ $t("pages.settings.staffList.staffList.positionSalaryConfirm", { position: pendingPosition, salary: formatSalaryValue(pendingPositionSalary) }) }}</p>
        </VCardText>
        <VCardActions class="staff-action-dialog__actions">
          <VBtn class="staff-action-dialog__button staff-action-dialog__button--cancel" @click="cancelPositionSalary">{{ $t("common.actions.cancel") }}</VBtn>
          <VBtn class="staff-action-dialog__button" @click="confirmPositionSalary">{{ $t("common.actions.confirm") }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Staff Dialog -->
    <VDialog v-if="false" v-model="isDeleteStaffDialogVisible" max-width="500px">
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

<style lang="scss">
.staff-responsive-table tbody td { padding-block: .875rem; }
.staff-empty-state { display: grid; justify-items: center; padding-block: 1.25rem 2rem; }
.staff-empty-state__action { margin-block-start: -.25rem; }
.staff-actions-menu__delete { color: rgb(var(--v-theme-error)) !important; }

.staff-form-dialog .staff-form-card { overflow: hidden; }
.staff-form-dialog .staff-form-card--wizard { max-block-size: calc(100vh - 32px); }
.staff-form-dialog .staff-dialog-title,
.staff-form-dialog .staff-dialog-actions {
  display: flex;
  align-items: center;
  border-color: rgba(var(--v-theme-on-surface), .035);
  border-style: solid;
  border-width: 0;
  background-color: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%);
}
.staff-form-dialog .staff-dialog-title { border-block-end-width: 1px; }
.staff-form-dialog .staff-dialog-actions { border-block-start-width: 1px; }
.staff-form-dialog .staff-dialog-heading { font-size: 1.625rem; font-weight: 700; }
.staff-form-dialog .staff-dialog-heading--primary { font-size: 2rem; }
.staff-form-dialog .staff-dialog-body { background-color: rgb(var(--v-theme-surface)); }
.staff-form-dialog .staff-dialog-close {
  inline-size: 36px;
  block-size: 36px;
  border: 1px solid rgba(var(--v-theme-error), .38);
  border-radius: 50%;
  color: rgb(var(--v-theme-error));
}
.staff-form-dialog .staff-dialog-close:hover { background: rgba(var(--v-theme-error), .18) !important; }
.staff-form-dialog button.staff-dialog-cancel.v-btn {
  border: 1px solid rgba(var(--v-theme-error), .62) !important;
  background: rgba(var(--v-theme-error), .12) !important;
  color: rgb(var(--v-theme-error)) !important;
}
.staff-form-dialog button.staff-dialog-cancel.v-btn:hover,
.staff-form-dialog button.staff-dialog-cancel.v-btn:focus-visible {
  border-color: rgb(var(--v-theme-error));
  background: rgb(var(--v-theme-error)) !important;
  color: rgb(var(--v-theme-on-error)) !important;
}
.staff-form-dialog .staff-dialog-submit { background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; }
.staff-form-dialog .staff-dialog-submit:hover { filter: brightness(.9); }
.v-overlay:has(.staff-form-dialog) .v-overlay__scrim,
.staff-action-overlay .v-overlay__scrim { background: rgba(var(--v-theme-on-surface), .28) !important; backdrop-filter: blur(5px); opacity: 1 !important; }

.staff-form-wizard .staff-dialog-body { max-block-size: calc(100vh - 200px); overflow: auto; scrollbar-width: none; }
.staff-form-wizard .staff-dialog-body::-webkit-scrollbar { display: none; }
.staff-wizard-progress { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; padding: 14px 20px; border-block-end: 1px solid rgba(var(--v-theme-on-surface), .06); background: color-mix(in srgb, rgb(var(--v-theme-surface)) 96%, rgb(var(--v-theme-primary)) 4%); }
.staff-wizard-progress__step { position: relative; display: flex; align-items: center; gap: 8px; min-inline-size: 0; padding: 4px; border: 0; border-radius: 8px; background: transparent; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); cursor: default; font: inherit; text-align: start; }
.staff-wizard-progress__step:not(:last-child)::after { position: absolute; inset-inline-start: calc(100% - 4px); inline-size: 8px; block-size: 1px; background: rgba(var(--v-theme-on-surface), .14); content: ''; }
.staff-wizard-progress__step--complete { cursor: pointer; }
.staff-wizard-progress__step:disabled { opacity: 1; }
.staff-wizard-progress__indicator { display: grid; flex: 0 0 auto; inline-size: 25px; block-size: 25px; border: 1px solid rgba(var(--v-theme-on-surface), .18); border-radius: 50%; place-items: center; font-size: .75rem; font-weight: 700; }
.staff-wizard-progress__label { overflow: hidden; font-size: .8rem; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.staff-wizard-progress__step--active { color: rgb(var(--v-theme-primary)); }
.staff-wizard-progress__step--active .staff-wizard-progress__indicator { border-color: rgba(var(--v-theme-primary), .3); background: rgba(var(--v-theme-primary), .12); }
.staff-wizard-progress__step--complete .staff-wizard-progress__indicator { border-color: transparent; background: rgba(var(--v-theme-on-surface), .08); }
.staff-form-wizard .staff-profile-fields { --v-gutter-x: 16px; --v-gutter-y: 16px; }
.staff-wizard-step-enter-active, .staff-wizard-step-leave-active { transition: opacity .2s ease, transform .2s ease; }
.staff-wizard-step-enter-from { opacity: 0; transform: translateY(8px); }
.staff-wizard-step-leave-to { opacity: 0; transform: translateY(-6px); }

.staff-details-dialog__eyebrow { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .75rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }
.staff-details-summary { display: flex; align-items: center; gap: 1rem; }
.staff-details-skeleton { display: grid; gap: 1.25rem; }
.staff-details-skeleton__summary { display: flex; align-items: center; gap: 1rem; }
.staff-details-skeleton__summary > div { flex: 1; max-inline-size: 15rem; }
.staff-detail-tile--skeleton { display: block; min-block-size: 5.25rem; }
.staff-detail-tile--skeleton .v-skeleton-loader__bone { margin: 0; }
.staff-details-grid { display: grid; gap: .875rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.staff-detail-tile { display: flex; align-items: flex-start; gap: .75rem; min-block-size: 5.25rem; padding: 1rem; border: 1px solid rgba(var(--v-border-color), .42); border-radius: 12px; background: color-mix(in srgb, rgb(var(--v-theme-surface)) 96%, rgb(var(--v-theme-on-surface)) 4%); }
.staff-detail-tile--wide { grid-column: 1 / -1; }
.staff-detail-tile__icon { flex: none; margin-block-start: .15rem; color: rgb(var(--v-theme-primary)); }
.staff-detail-tile__content { display: grid; gap: .25rem; min-inline-size: 0; }
.staff-detail-tile__label { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .75rem; font-weight: 600; }
.staff-detail-tile__value { overflow-wrap: anywhere; color: rgb(var(--v-theme-on-surface)); font-size: .9375rem; font-weight: 600; line-height: 1.45; }

.staff-action-dialog { overflow: hidden; border: 1px solid rgba(var(--v-border-color), .2) !important; border-radius: 16px !important; background: rgb(var(--v-theme-surface)) !important; box-shadow: 0 24px 48px rgba(0, 0, 0, .18) !important; text-align: center; }
.staff-action-dialog__content { display: flex; flex-direction: column; align-items: center; padding: 2rem 2rem 1.25rem !important; }
.staff-action-dialog__icon { display: grid; inline-size: 4.25rem; block-size: 4.25rem; margin-block-end: 1.25rem; border-radius: 50%; place-items: center; background: rgba(var(--v-theme-primary), .14); color: rgb(var(--v-theme-primary)); }
.staff-action-dialog__icon--danger { background: rgba(var(--v-theme-error), .14); color: rgb(var(--v-theme-error)); }
.staff-action-dialog__icon--warning { background: rgba(var(--v-theme-warning), .16); color: rgb(var(--v-theme-warning)); }
.staff-action-dialog__title { margin: 0; font-size: 1.2rem; font-weight: 700; line-height: 1.45; }
.staff-action-dialog__description { max-inline-size: 24rem; margin: .65rem 0 0; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); line-height: 1.65; }
.staff-action-dialog__actions { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; padding: 0 2rem 2rem !important; }
.staff-action-dialog__button { min-block-size: 2.85rem; border-radius: 10px !important; background: rgb(var(--v-theme-primary)) !important; color: rgb(var(--v-theme-on-primary)) !important; font-weight: 700; }
.staff-action-dialog__button--cancel { border: 1px solid rgba(var(--v-theme-secondary), .52) !important; background: rgba(var(--v-theme-secondary), .13) !important; color: rgb(var(--v-theme-secondary)) !important; }
.staff-action-dialog__button--delete { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }
.staff-action-dialog__button--destructive { background: rgb(var(--v-theme-error)) !important; color: rgb(var(--v-theme-on-error)) !important; }

@media (max-width: 959px) {
  .staff-responsive-table .v-table__wrapper { overflow: clip; }
  .staff-responsive-table .v-table__wrapper > table { inline-size: 100%; min-inline-size: 0; table-layout: fixed; }
  .staff-responsive-table.responsive-data-table--mobile-cards .responsive-data-table__mobile-row > td { padding: .75rem; }
}
@media (max-width: 600px) {
  .staff-details-grid { grid-template-columns: 1fr; }
  .staff-detail-tile--wide { grid-column: auto; }
  .staff-action-dialog__content, .staff-action-dialog__actions { padding-inline: 1.25rem !important; }
  .staff-form-dialog .staff-dialog-title, .staff-form-dialog .staff-dialog-body { padding: 16px !important; }
  .staff-form-dialog .staff-dialog-actions { padding: 12px 16px !important; }
  .staff-form-dialog .staff-dialog-heading--primary { font-size: 1.75rem; }
}
</style>
