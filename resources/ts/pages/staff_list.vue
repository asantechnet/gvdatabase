<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { VDialog } from 'vuetify/components'
import { useRouter } from 'vue-router'
import EmployeeApi, { type CommissionServiceOption, type CreateEmployeeData, type Employee, type EmployeeAccount, type UpdateEmployeeData } from '@/api/employee'
import PositionApi, { type Position } from '@/api/position'
import OfficeApi, { type Office } from '@/api/office'
import PaidTimeOffApi, { type PaidTimeOff } from '@/api/paidTimeOff'
import { apiErrorMessage, apiFieldErrors } from '@/utils/apiErrors'
import { printDocument } from '@/utils/printDocument'
import PrintableListReport from '@/components/PrintableListReport.vue'

interface Staff {
  id: number
  user_id: number | null
  name: string
  father_name: string
  contact: string
  id_number: string | null
  bank_account_number: string | null
  guarantor_id_number: string | null
  guarantor_phone: string | null
  guarantor_address: string | null
  guarantor_licence: string | null
  education_lvl: string
  position: string
  employment_type: 'salaried' | 'commission'
  email: string
  office_id: number | null
  office: Employee['office']
  salary: number | null
  commissions: Array<{ service_id: number; service?: string; fee?: number; percentage: number }>
  total_worked_hours: number
  addr: string
  join_date: string
  avatar?: string | null
}

const staffList = ref<Staff[]>([])
const router = useRouter()
const isAddStaffDialogVisible = ref(false)
const isEditStaffDialogVisible = ref(false)
const isDeleteStaffDialogVisible = ref(false)
const isPaidTimeOffDialogVisible = ref(false)
const selectedStaff = ref<Staff | null>(null)
const searchQuery = ref('')
const filterPosition = ref<string | null>(null)
const isLoading = ref(false)
const isPositionsLoading = ref(false)
const positionOptions = ref<string[]>([])
const positionIdMap = ref<Record<string, number>>({})
const positionSalaryMap = ref<Record<string, number>>({})
const offices = ref<Office[]>([])
const commissionServices = ref<CommissionServiceOption[]>([])
const userAccounts = ref<EmployeeAccount[]>([])
const isUserAccountsLoading = ref(false)
const formSubmissionError = ref('')
const leaveStaff = ref<Staff | null>(null)
const paidTimeOffs = ref<PaidTimeOff[]>([])
const paidTimeOffYear = ref(new Date().getFullYear())
const leaveAllowance = ref(0)
const leaveUsed = ref(0)
const leaveRemaining = ref(0)
const leaveForm = ref({ id: null as number | null, date: new Date().toISOString().slice(0, 10), duration_days: 1 as 0.5 | 1, reason: '' })
const leaveError = ref('')
const isLeaveLoading = ref(false)

const isPaidTimeOffFormComplete = computed(() => Boolean(
  leaveForm.value.date
  && leaveForm.value.reason.trim(),
))

const canSavePaidTimeOff = computed(() => Boolean(
  !isLeaveLoading.value
  && isPaidTimeOffFormComplete.value
  ,
))

const paidTimeOffLimitMessage = computed(() => {
  if (leaveForm.value.id || leaveRemaining.value > 0)
    return ''

  return leaveAllowance.value === 0
    ? 'No paid leave is configured. Set paid leave days per year in Settings.'
    : `The ${leaveAllowance.value}-day paid allowance is used; new leave will be unpaid unless an authority overrides it.`
})

const educationOptions = ['High School', 'Diploma', 'Bachelor', 'Master', 'PhD']
const avatarText = (name: string) => name.split(/\s+/).filter(Boolean).map(part => part[0]).slice(0, 2).join('').toUpperCase()

const printEmployeeContract = (staff: Staff) => {
  router.push({ name: 'employee-contracts', query: { employeeId: staff.id, print: '1' } })
}

const formErrors = ref({
  name: '',
  father_name: '',
  contact: '',
  id_number: '',
  email: '',
  addr: '',
  position: '',
  salary: '',
  commissions: '',
  education_lvl: '',
  join_date: '',
})

const resetFormErrors = () => {
  formErrors.value = {
    name: '',
    father_name: '',
    contact: '',
    id_number: '',
    email: '',
    addr: '',
    position: '',
    salary: '',
    commissions: '',
    education_lvl: '',
    join_date: '',
  }
}

const validateStaffForm = () => {
  let isValid = true

  // Validate Name
  if (!selectedStaff.value?.name.trim()) {
    formErrors.value.name = translate('pages.settings.staffList.staffList.nameIsRequired')
    isValid = false
  }
  else if (selectedStaff.value.name.length < 2) {
    formErrors.value.name = translate('pages.settings.staffList.staffList.nameMustBeAtLeast2Characters')
    isValid = false
  }
  else {
    formErrors.value.name = ''
  }

  // Validate Father Name
  if (!selectedStaff.value?.father_name.trim()) {
    formErrors.value.father_name = translate('pages.settings.staffList.staffList.fatherNameIsRequired')
    isValid = false
  }
  else if (selectedStaff.value.father_name.length < 2) {
    formErrors.value.father_name = translate('pages.settings.staffList.staffList.fatherNameMustBeAtLeast2Characters')
    isValid = false
  }
  else {
    formErrors.value.father_name = ''
  }

  // Validate Contact
  if (!selectedStaff.value?.contact.trim()) {
    formErrors.value.contact = translate('common.messages.contactNumberIsRequired')
    isValid = false
  }
  else if (!/^[\d\s\-()+]{8,}$/.test(selectedStaff.value.contact)) {
    formErrors.value.contact = translate('common.messages.pleaseEnterAValidPhoneNumber')
    isValid = false
  }
  else {
    formErrors.value.contact = ''
  }

  // Validate Email
  if (!selectedStaff.value?.user_id && !selectedStaff.value?.email.trim()) {
    formErrors.value.email = translate('pages.settings.staffList.staffList.emailIsRequired')
    isValid = false
  }
  else if (!selectedStaff.value?.user_id && !/^[^\s@]+@[^\s@][^\s.@]*\.[^\s@]+$/.test(selectedStaff.value.email)) {
    formErrors.value.email = translate('pages.settings.staffList.staffList.pleaseEnterAValidEmailAddress')
    isValid = false
  }
  else {
    formErrors.value.email = ''
  }

  // Validate Address
  if (!selectedStaff.value?.addr.trim()) {
    formErrors.value.addr = translate('pages.settings.staffList.staffList.addressIsRequired')
    isValid = false
  }
  else if (selectedStaff.value.addr.length < 5) {
    formErrors.value.addr = translate('pages.settings.staffList.staffList.addressMustBeAtLeast5Characters')
    isValid = false
  }
  else {
    formErrors.value.addr = ''
  }

  if (!selectedStaff.value?.id_number?.trim()) {
    formErrors.value.id_number = translate('pages.settings.staffList.staffList.governmentIDIsRequired')
    isValid = false
  }
  else {
    formErrors.value.id_number = ''
  }

  // Validate Position
  if (!selectedStaff.value?.position) {
    formErrors.value.position = translate('pages.settings.staffList.staffList.positionIsRequired')
    isValid = false
  }
  else {
    formErrors.value.position = ''
  }

  // Validate Salary
  if (selectedStaff.value?.salary === null || selectedStaff.value?.salary === undefined) {
    formErrors.value.salary = translate('pages.settings.staffList.staffList.salaryIsRequired')
    isValid = false
  }
  else if (Number(selectedStaff.value.salary) < 0) {
    formErrors.value.salary = translate('pages.settings.staffList.staffList.salaryCannotBeNegative')
    isValid = false
  }
  else {
    formErrors.value.salary = ''
  }

  if (selectedStaff.value?.employment_type === 'commission' && selectedStaff.value.commissions.length === 0) {
    formErrors.value.commissions = translate('pages.settings.staffList.staffList.assignCommissionService')
    isValid = false
  }
  else {
    formErrors.value.commissions = ''
  }

  // Validate Education Level
  if (!selectedStaff.value?.education_lvl) {
    formErrors.value.education_lvl = translate('pages.settings.staffList.staffList.educationLevelIsRequired')
    isValid = false
  }
  else {
    formErrors.value.education_lvl = ''
  }

  // Validate Join Date
  if (!selectedStaff.value?.join_date) {
    formErrors.value.join_date = translate('pages.settings.staffList.staffList.joinDateIsRequired')
    isValid = false
  }
  else {
    formErrors.value.join_date = ''
  }

  return isValid
}

const openAddStaffDialog = () => {
  selectedStaff.value = {
    id: 0,
    user_id: null,
    name: '',
    father_name: '',
    contact: '',
    id_number: '',
    bank_account_number: '',
    guarantor_id_number: '',
    guarantor_phone: '',
    guarantor_address: '',
    guarantor_licence: '',
    education_lvl: '',
    email: '',
    addr: '',
    position: '',
    employment_type: 'salaried',
    office_id: null,
    office: null,
    salary: 0,
    commissions: [],
    total_worked_hours: 0,
    join_date: new Date().toISOString().split('T')[0],
  }
  resetFormErrors()
  formSubmissionError.value = ''
  isAddStaffDialogVisible.value = true
}

const openEditStaffDialog = (staff: Staff) => {
  // Keep edits local until Save; nested commission rows otherwise mutate the
  // table record even when the dialog is cancelled.
  selectedStaff.value = { ...staff, commissions: staff.commissions.map(commission => ({ ...commission })) }
  resetFormErrors()
  formSubmissionError.value = ''
  isEditStaffDialogVisible.value = true
}

const openDeleteStaffDialog = (staff: Staff) => {
  selectedStaff.value = staff
  isDeleteStaffDialogVisible.value = true
}

const loadPaidTimeOffs = async () => {
  if (!leaveStaff.value)
    return
  isLeaveLoading.value = true
  leaveError.value = ''
  try {
    const ledger = await PaidTimeOffApi.getAll(leaveStaff.value.id, paidTimeOffYear.value)

    paidTimeOffs.value = ledger.data
    leaveAllowance.value = ledger.allowance
    leaveUsed.value = ledger.used
    leaveRemaining.value = ledger.remaining
  }
  catch (error) {
    leaveError.value = apiErrorMessage(error, 'Unable to load leave records.')
  }
  finally {
    isLeaveLoading.value = false
  }
}

const openPaidTimeOffDialog = async (staff: Staff) => {
  leaveStaff.value = staff
  paidTimeOffYear.value = new Date().getFullYear()
  leaveForm.value = { id: null, date: new Date().toISOString().slice(0, 10), duration_days: 1, reason: '' }
  isPaidTimeOffDialogVisible.value = true
  await loadPaidTimeOffs()
}

const editPaidTimeOff = (record: PaidTimeOff) => {
  leaveForm.value = { id: record.id, date: record.date, duration_days: record.duration_days, reason: record.reason }
  leaveError.value = ''
}

const resetPaidTimeOffForm = () => {
  leaveForm.value = { id: null, date: `${paidTimeOffYear.value}-01-01`, duration_days: 1, reason: '' }
  leaveError.value = ''
}

const changePaidTimeOffYear = async () => {
  if (!leaveForm.value.id)
    leaveForm.value.date = `${paidTimeOffYear.value}-01-01`

  await loadPaidTimeOffs()
}

const savePaidTimeOff = async () => {
  if (!leaveStaff.value)
    return
  if (!leaveForm.value.date || !leaveForm.value.reason.trim()) {
    leaveError.value = translate(!leaveForm.value.date ? 'pages.users.staffList.dateRequired' : 'pages.users.staffList.reasonRequired')

    return
  }
  if (!leaveForm.value.date.startsWith(`${paidTimeOffYear.value}-`)) {
    leaveError.value = `Date: Choose a date in ${paidTimeOffYear.value}.`

    return
  }
  isLeaveLoading.value = true
  leaveError.value = ''
  try {
    const payload = { date: leaveForm.value.date, duration_days: leaveForm.value.duration_days, reason: leaveForm.value.reason.trim() }
    if (leaveForm.value.id)
      await PaidTimeOffApi.update(leaveStaff.value.id, leaveForm.value.id, payload)
    else
      await PaidTimeOffApi.create(leaveStaff.value.id, payload)
    resetPaidTimeOffForm()
    await loadPaidTimeOffs()
  }
  catch (error) {
    leaveError.value = apiErrorMessage(error, 'Unable to save leave.')
  }
  finally {
    isLeaveLoading.value = false
  }
}

const deletePaidTimeOff = async (id: number) => {
  if (!leaveStaff.value)
    return
  isLeaveLoading.value = true
  try {
    await PaidTimeOffApi.delete(leaveStaff.value.id, id)
    if (leaveForm.value.id === id)
      resetPaidTimeOffForm()
    await loadPaidTimeOffs()
  }
  catch (error) {
    leaveError.value = apiErrorMessage(error, 'Unable to remove leave.')
  }
  finally {
    isLeaveLoading.value = false
  }
}

const fetchPositions = async () => {
  try {
    isPositionsLoading.value = true

    const response = await PositionApi.getAll()
    if (response.data && Array.isArray(response.data.data)) {
      positionOptions.value = response.data.data.map((pos: Position) => pos.title)
      positionIdMap.value = response.data.data.reduce((map: Record<string, number>, pos: Position) => {
        map[pos.title] = pos.id

        return map
      }, {})
      positionSalaryMap.value = response.data.data.reduce((map: Record<string, number>, pos: Position) => {
        map[pos.title] = Number(pos.salary ?? 0)

        return map
      }, {})
    }
    else if (Array.isArray(response.data)) {
      positionOptions.value = response.data.map((pos: Position) => pos.title)
      positionIdMap.value = response.data.reduce((map: Record<string, number>, pos: Position) => {
        map[pos.title] = pos.id

        return map
      }, {})
      positionSalaryMap.value = response.data.reduce((map: Record<string, number>, pos: Position) => {
        map[pos.title] = Number(pos.salary ?? 0)

        return map
      }, {})
    }
  }
  catch (error) {
    console.error('Error fetching positions:', error)
  }
  finally {
    isPositionsLoading.value = false
  }
}

const fetchEmployees = async () => {
  try {
    isLoading.value = true

    const response = await EmployeeApi.getAll()
    if (response.data && Array.isArray(response.data.data)) {
      staffList.value = response.data.data.map(emp => ({
        ...emp,
        email: emp.email ?? '',
        salary: emp.salary ?? 0,
        join_date: emp.join_date ?? '',
        position: emp.position || positionOptions.value[emp.position_id - 1] || emp.position_id.toString(),
      }))
    }
    else if (Array.isArray(response.data)) {
      staffList.value = response.data.map(emp => ({
        ...emp,
        email: emp.email ?? '',
        salary: emp.salary ?? 0,
        join_date: emp.join_date ?? '',
        position: emp.position || positionOptions.value[emp.position_id - 1] || emp.position_id.toString(),
      }))
    }
  }
  catch (error) {
    console.error('Error fetching employees:', error)
  }
  finally {
    isLoading.value = false
  }
}

const fetchUserAccounts = async () => {
  try {
    isUserAccountsLoading.value = true
    userAccounts.value = await EmployeeApi.getAccountOptions()
  }
  catch (error) {
    console.error('Error fetching user accounts:', error)
  }
  finally {
    isUserAccountsLoading.value = false
  }
}

const userAccountOptions = computed(() => userAccounts.value.map(user => ({
  ...user,
  title: user.name,
  value: user.id,
  disabled: Boolean(user.employee && user.employee.id !== selectedStaff.value?.id),
})))

const updateSelectedAccountEmail = (userId: number | null) => {
  const user = userAccounts.value.find(account => account.id === userId)
  if (selectedStaff.value && user)
    selectedStaff.value.email = user.email
}

const applyPositionSalary = (position: string) => {
  if (selectedStaff.value && position in positionSalaryMap.value)
    selectedStaff.value.salary = positionSalaryMap.value[position]
}

const addStaff = async () => {
  if (!selectedStaff.value)
    return
  if (!validateStaffForm())
    return

  try {
    isLoading.value = true

    const positionId = positionIdMap.value[selectedStaff.value.position]

    const data: CreateEmployeeData = {
      user_id: selectedStaff.value.user_id,
      name: selectedStaff.value.name,
      father_name: selectedStaff.value.father_name,
      contact: selectedStaff.value.contact,
      id_number: selectedStaff.value.id_number ?? '',
      bank_account_number: selectedStaff.value.bank_account_number,
      guarantor_id_number: selectedStaff.value.guarantor_id_number,
      guarantor_phone: selectedStaff.value.guarantor_phone,
      guarantor_address: selectedStaff.value.guarantor_address,
      guarantor_licence: selectedStaff.value.guarantor_licence,
      create_user: !selectedStaff.value.user_id,
      email: selectedStaff.value.email,
      education_lvl: selectedStaff.value.education_lvl,
      position_id: positionId,
      employment_type: selectedStaff.value.employment_type,
      office_id: selectedStaff.value.office_id,
      salary: selectedStaff.value.salary,
      commissions: selectedStaff.value.commissions.map(row => ({ service_id: row.service_id, percentage: row.percentage })),
      addr: selectedStaff.value.addr,
      join_date: selectedStaff.value.join_date,
    }

    const response = await EmployeeApi.create(data)
    if (response.data && response.data.data) {
      staffList.value.unshift({
        ...response.data.data,
        email: response.data.data.email ?? '',
        salary: response.data.data.salary ?? 0,
        join_date: response.data.data.join_date ?? '',
        position: response.data.data.position || positionOptions.value[response.data.data.position_id - 1] || response.data.data.position_id.toString(),
      })
    }
    isAddStaffDialogVisible.value = false
  }
  catch (error) {
    console.error('Error creating employee:', error)
    applyStaffApiErrors(error)
  }
  finally {
    isLoading.value = false
  }
}

const updateStaff = async () => {
  if (!selectedStaff.value)
    return
  if (!validateStaffForm())
    return

  try {
    isLoading.value = true

    const positionId = positionIdMap.value[selectedStaff.value.position]

    const data: UpdateEmployeeData = {
      user_id: selectedStaff.value.user_id,
      name: selectedStaff.value.name,
      father_name: selectedStaff.value.father_name,
      contact: selectedStaff.value.contact,
      id_number: selectedStaff.value.id_number ?? '',
      bank_account_number: selectedStaff.value.bank_account_number,
      guarantor_id_number: selectedStaff.value.guarantor_id_number,
      guarantor_phone: selectedStaff.value.guarantor_phone,
      guarantor_address: selectedStaff.value.guarantor_address,
      guarantor_licence: selectedStaff.value.guarantor_licence,
      create_user: false,
      email: selectedStaff.value.email,
      education_lvl: selectedStaff.value.education_lvl,
      position_id: positionId,
      employment_type: selectedStaff.value.employment_type,
      office_id: selectedStaff.value.office_id,
      salary: selectedStaff.value.salary,
      commissions: selectedStaff.value.commissions.map(row => ({ service_id: row.service_id, percentage: row.percentage })),
      addr: selectedStaff.value.addr,
      join_date: selectedStaff.value.join_date,
    }

    const response = await EmployeeApi.update(selectedStaff.value.id, data)
    if (response.data && response.data.data) {
      const index = staffList.value.findIndex(s => s.id === selectedStaff.value?.id)
      if (index !== -1) {
        staffList.value[index] = {
          ...response.data.data,
          email: response.data.data.email ?? '',
          salary: response.data.data.salary ?? 0,
          join_date: response.data.data.join_date ?? '',
          position: response.data.data.position || positionOptions.value[response.data.data.position_id - 1] || response.data.data.position_id.toString(),
        }
      }
    }
    isEditStaffDialogVisible.value = false
  }
  catch (error) {
    console.error('Error updating employee:', error)
    applyStaffApiErrors(error)
  }
  finally {
    isLoading.value = false
  }
}

const applyStaffApiErrors = (error: unknown) => {
  const errors = apiFieldErrors(error)
  const fieldMap: Record<string, keyof typeof formErrors.value> = { position_id: 'position' }

  Object.entries(errors).forEach(([field, message]) => {
    const target = fieldMap[field] ?? field
    if (target in formErrors.value)
      formErrors.value[target as keyof typeof formErrors.value] = message
  })

  formSubmissionError.value = Object.keys(errors).length
    ? 'Please correct the highlighted fields.'
    : apiErrorMessage(error, 'Unable to save the staff record. Please try again.')
}

const addCommission = () => selectedStaff.value?.commissions.push({ service_id: 0, percentage: 1 })
const removeCommission = (index: number) => selectedStaff.value?.commissions.splice(index, 1)

const commissionLabel = (service: CommissionServiceOption) => {
  const currency = service.currency
  const fee = Number(service.fee)
  const amount = Number.isFinite(fee) ? fee.toFixed(currency?.decimalPlaces ?? 2) : '—'
  const type = service.type || translate('pages.settings.staffList.staffList.service')

  return `${type} · ${currency?.symbol ?? ''}${amount} ${currency?.code ?? ''}`.trim()
}

const availableCommissionServices = (currentServiceId: number) => commissionServices.value.filter(service =>
  service.id === currentServiceId
  || !selectedStaff.value?.commissions.some(commission => commission.service_id === service.id),
)

const deleteStaff = async () => {
  if (!selectedStaff.value)
    return

  try {
    isLoading.value = true
    await EmployeeApi.delete(selectedStaff.value.id)
    staffList.value = staffList.value.filter(s => s.id !== selectedStaff.value?.id)
    isDeleteStaffDialogVisible.value = false
  }
  catch (error) {
    console.error('Error deleting employee:', error)
  }
  finally {
    isLoading.value = false
  }
}

const filteredStaff = computed(() => {
  return staffList.value.filter(staff => {
    const matchesSearch = staff.name.toLowerCase().includes(searchQuery.value.toLowerCase())
      || staff.father_name.toLowerCase().includes(searchQuery.value.toLowerCase())
      || staff.contact.includes(searchQuery.value)
      || (staff.id_number ?? '').includes(searchQuery.value)
      || staff.email.toLowerCase().includes(searchQuery.value.toLowerCase())
      || staff.position.toLowerCase().includes(searchQuery.value.toLowerCase())

    const matchesPosition = !filterPosition.value || staff.position === filterPosition.value

    return matchesSearch && matchesPosition
  })
})

const printTable = () => {
  void printDocument({ bodyClass: 'printing-table-staff', pageStyle: 'size: A4 landscape; margin: 12mm;' })
}

onMounted(async () => {
  const [, officeRows, commissionRows] = await Promise.all([fetchPositions(), OfficeApi.getAll(), EmployeeApi.getCommissionOptions(), fetchUserAccounts()])

  offices.value = officeRows.filter(office => office.status === 'active')
  commissionServices.value = commissionRows
  await fetchEmployees()
})
</script>

<template>
  <div>
    <DataTablePageKpis
      :items="filteredStaff"
      title="Staff directory"
      description="See your team, their assignments, and records that need follow-up."
      icon="tabler-users"
    />
    <VCard class="mb-6">
      <VCardText>
        <VRow align="center">
          <VCol
            cols="12"
            md="6"
          >
            <VTextField
              v-model="searchQuery"
              class="data-table-search"
              prepend-inner-icon="tabler-search"
              :placeholder="$t('pages.settings.staffList.staffList.searchStaff')"
              density="compact"
              clearable
              hide-details
            />
          </VCol>

          <VCol
            cols="12"
            md="6"
            class="d-flex gap-2 justify-end"
          >
            <DataTableFilterMenu
              :active="Boolean(filterPosition)"
              @reset="filterPosition = null"
            >
              <VSelect
                v-model="filterPosition"
                :items="positionOptions"
                :label="$t('pages.settings.staffList.staffList.filterByPosition')"
                density="compact"
                clearable
                hide-details
                :loading="isPositionsLoading"
              />
            </DataTableFilterMenu>
            <VBtn
              icon="tabler-printer"
              variant="text"
              @click="printTable"
            >
              <VIcon icon="tabler-printer" /><VTooltip
                activator="parent"
                location="top"
              >
                {{ $t('pages.settings.staffList.staffList.printStaffRecords') }}
              </VTooltip>
            </VBtn>
            <VBtn
              color="primary"
              @click="openAddStaffDialog"
            >
              <VIcon
                icon="tabler-plus"
                class="me-1"
              />
              {{ $t('pages.settings.staffList.staffList.addStaff') }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <div class="table-container">
        <ResponsiveDataTable
          :items="filteredStaff"
          :headers="[
            { title: translate('common.messages.id'), key: 'id' },
            { title: translate('common.fields.name'), key: 'name' },
            { title: translate('pages.settings.staffList.staffList.fatherName'), key: 'father_name' },
            { title: translate('pages.settings.staffList.staffList.governmentID'), key: 'id_number' },
            { title: translate('common.messages.contact'), key: 'contact' },
            { title: translate('common.messages.position'), key: 'position' },
            { title: translate('common.fields.office'), key: 'office' },
            { title: translate('pages.settings.staffList.staffList.salary'), key: 'salary' },
            { title: translate('pages.settings.staffList.staffList.workedHours'), key: 'total_worked_hours' },
            { title: translate('pages.settings.staffList.staffList.joinDate'), key: 'join_date' },
            { title: translate('common.messages.actions'), key: 'actions', sortable: false },
          ]"
          item-value="id"
          class="text-no-wrap staff-table"
          :loading="isLoading"
          mobile-breakpoint="sm"
          density="comfortable"
        >
          <template #item.salary="{ item }">
            <div>${{ Number(item.salary || 0).toFixed(2) }}</div>
            <VChip
              v-if="item.employment_type === 'commission'"
              size="x-small"
              color="primary"
              variant="tonal"
            >
              Base + commission
            </VChip>
          </template>

          <template #item.office="{ item }">
            <span>{{ item.office?.name || '—' }}</span>
          </template>

          <template #item.name="{ item }">
            <div class="d-flex align-center gap-3">
              <VAvatar
                size="34"
                class="mx-2"
                :variant="item.avatar ? undefined : 'tonal'"
                :color="item.avatar ? undefined : 'primary'"
              >
                <VImg
                  v-if="item.avatar"
                  :src="item.avatar"
                  :alt="item.name"
                />
                <span v-else>{{ avatarText(item.name) }}</span>
              </VAvatar>
              <span>{{ item.name }}</span>
            </div>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <FullDetailsButton
                :item="item"
                :title="item.name"
              />
              <VBtn
                icon="tabler-file-description"
                variant="text"
                size="small"
                color="primary"
                @click="printEmployeeContract(item)"
              >
                <VIcon icon="tabler-file-description" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  Print employment contract
                </VTooltip>
              </VBtn>
              <VBtn
                icon="tabler-calendar-time"
                variant="text"
                size="small"
                color="primary"
                @click="openPaidTimeOffDialog(item)"
              >
                <VIcon icon="tabler-calendar-time" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t('pages.settings.staffList.staffList.managePaidOffDays') }}
                </VTooltip>
              </VBtn>
              <VBtn
                icon="tabler-edit"
                variant="text"
                size="small"
                color="medium-emphasis"
                @click="openEditStaffDialog(item)"
              >
                <VIcon icon="tabler-edit" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t('pages.settings.staffList.staffList.editStaffRecord') }}
                </VTooltip>
              </VBtn>
              <VBtn
                icon="tabler-trash"
                variant="text"
                size="small"
                color="error"
                @click="openDeleteStaffDialog(item)"
              >
                <VIcon icon="tabler-trash" /><VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ $t('pages.settings.staffList.staffList.deleteStaffRecord') }}
                </VTooltip>
              </VBtn>
            </div>
          </template>
        </ResponsiveDataTable>
      </div>
    </VCard>

    <PrintableListReport
      :title="$t('common.crmUi.printReports.staff.title')"
      :subtitle="$t('common.crmUi.printReports.staff.subtitle')"
      :columns="[$t('common.crmUi.printReports.columns.id'), $t('common.crmUi.printReports.columns.name'), $t('common.crmUi.printReports.columns.position'), $t('common.crmUi.printReports.columns.contact'), $t('common.crmUi.printReports.columns.email'), $t('common.crmUi.printReports.columns.office')]"
      :rows="filteredStaff.map(staff => [staff.id, staff.name, staff.position, staff.contact, staff.email, staff.office?.name])"
    />

    <!-- Add Staff Dialog -->
    <VDialog
      v-model="isAddStaffDialogVisible"
      max-width="800px"
    >
      <VCard v-if="selectedStaff">
        <VCardTitle class="pa-5">
          <span class="headline">{{ $t('pages.settings.staffList.staffList.addNewStaff') }}</span>
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
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedStaff.name"
                  :label="$t('common.messages.fullName')"
                  :error-messages="formErrors.name"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedStaff.father_name"
                  :label="$t('common.messages.fatherName')"
                  :error-messages="formErrors.father_name"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedStaff.contact"
                  :label="$t('common.fields.phone')"
                  :error-messages="formErrors.contact"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
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
                      <span class="account-selection__name">{{ item.raw.name }}</span><span class="account-selection__meta">#{{ item.raw.id }} · {{ item.raw.email }}</span>
                    </div>
                  </template>
                  <template #item="{ props, item }">
                    <VListItem
                      v-bind="props"
                      class="account-option"
                      :title="item.raw.name"
                    >
                      <template #prepend>
                        <VAvatar
                          color="primary"
                          variant="tonal"
                          size="38"
                        >
                          {{ avatarText(item.raw.name) }}
                        </VAvatar>
                      </template><template #subtitle>
                        <div class="account-option__details">
                          <span>#{{ item.raw.id }} · {{ item.raw.email }}</span><span v-if="item.raw.employee"><VIcon
                            icon="tabler-id-badge-2"
                            size="14"
                          /> {{ $t('common.accountPicker.linkedEmployee', { name: item.raw.employee.name, id: item.raw.employee.id }) }}</span><span v-if="item.raw.marketer"><VIcon
                            icon="tabler-speakerphone"
                            size="14"
                          /> {{ $t('common.accountPicker.linkedMarketer', { name: item.raw.marketer.name, id: item.raw.marketer.id }) }}</span><span v-if="!item.raw.employee && !item.raw.marketer">{{ $t('common.accountPicker.available') }}</span>
                        </div>
                      </template><template #append>
                        <span class="account-option__id">#{{ item.raw.id }}</span>
                      </template>
                    </VListItem>
                  </template>
                </VSelect>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedStaff.id_number"
                  :label="$t('common.messages.governmentIDNumber')"
                  :error-messages="formErrors.id_number"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedStaff.email"
                  :label="$t('common.fields.email')"
                  type="email"
                  :error-messages="formErrors.email"
                  :required="!selectedStaff.user_id"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
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
              <VCol
                cols="12"
                md="6"
              >
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
              <VCol
                cols="12"
                md="6"
              >
                <AppDateTimePicker
                  v-model="selectedStaff.join_date"
                  :label="$t('common.messages.joinDate')"
                  :error-messages="formErrors.join_date"
                  required
                  :config="{ dateFormat: 'Y-m-d' }"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="selectedStaff.office_id"
                  :items="offices"
                  item-title="name"
                  item-value="id"
                  :label="$t('common.fields.office')"
                  clearable
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="selectedStaff.employment_type"
                  :items="[{ title: 'Salaried', value: 'salaried' }, { title: 'On commission', value: 'commission' }]"
                  label="Employment type"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="selectedStaff.salary"
                  :label="selectedStaff.employment_type === 'commission' ? 'Base salary' : $t('common.messages.salary')"
                  type="number"
                  min="0"
                  :error-messages="formErrors.salary"
                  required
                />
              </VCol>
              <VCol
                v-if="selectedStaff.employment_type === 'commission'"
                cols="12"
              >
                <VCard
                  variant="flat"
                  class="commission-capabilities"
                >
                  <VCardText class="commission-capabilities__body">
                    <div class="commission-capabilities__header">
                      <div class="d-flex align-start ga-3">
                        <VAvatar
                          size="38"
                          color="primary"
                          variant="tonal"
                          rounded="lg"
                        >
                          <VIcon
                            icon="tabler-briefcase-2"
                            size="21"
                          />
                        </VAvatar>
                        <div>
                          <div class="text-subtitle-1 font-weight-semibold">
                            {{ $t('common.crmUi.commissionRateSetup.title') }}
                          </div>
                          <p class="commission-capabilities__description">
                            {{ $t('common.crmUi.commissionRateSetup.description') }}
                          </p>
                        </div>
                      </div>
                      <VBtn
                        size="small"
                        color="primary"
                        variant="flat"
                        prepend-icon="tabler-plus"
                        @click="addCommission"
                      >
                        Add service
                      </VBtn>
                    </div>
                    <VAlert
                      v-if="formErrors.commissions"
                      type="error"
                      variant="tonal"
                      density="compact"
                      class="commission-capabilities__alert"
                    >
                      {{ formErrors.commissions }}
                    </VAlert>
                    <VRow
                      v-for="(commission, index) in selectedStaff.commissions"
                      :key="index"
                      align="center"
                      class="commission-capabilities__row"
                    >
                      <VCol
                        cols="12"
                        sm="8"
                      >
                        <VSelect
                          v-model="commission.service_id"
                          :items="availableCommissionServices(commission.service_id)"
                          :item-title="commissionLabel"
                          item-value="id"
                          :label="$t('common.crmUi.commissionRateSetup.service')"
                          placeholder="Choose a service"
                          density="comfortable"
                        />
                      </VCol>
                      <VCol
                        cols="10"
                        sm="3"
                      >
                        <VTextField
                          v-model.number="commission.percentage"
                          type="number"
                          min="0.01"
                          max="100"
                          label="Commission rate"
                          suffix="%"
                          density="comfortable"
                        />
                      </VCol>
                      <VCol
                        cols="2"
                        sm="1"
                        class="text-end"
                      >
                        <VBtn
                          icon="tabler-x"
                          variant="text"
                          color="error"
                          @click="removeCommission(index)"
                        >
                          <VTooltip activator="parent">
                            Remove service
                          </VTooltip>
                        </VBtn>
                      </VCol>
                    </VRow>
                  </VCardText>
                </VCard>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedStaff.bank_account_number"
                  label="Bank account number"
                />
              </VCol>
              <FormSection
                title-key="common.fields.address"
                description-key="common.messages.guarantorAddress"
                icon="tabler-shield-check"
              />
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedStaff.guarantor_id_number"
                  :label="$t('common.messages.guarantorGovernmentID')"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedStaff.guarantor_phone"
                  :label="$t('common.messages.guarantorPhone')"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
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
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedStaff.addr"
                  :label="$t('common.fields.address')"
                  :error-messages="formErrors.addr"
                  required
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
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="isLoading"
            @click="addStaff"
          >
            {{ $t('common.actions.save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit Staff Dialog -->
    <VDialog
      v-model="isEditStaffDialogVisible"
      max-width="800px"
    >
      <VCard v-if="selectedStaff">
        <VCardTitle class="pa-5">
          <span class="headline">{{ $t('pages.settings.staffList.staffList.editStaff') }}</span>
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
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedStaff.name"
                  :label="$t('common.messages.fullName')"
                  :error-messages="formErrors.name"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedStaff.father_name"
                  :label="$t('common.messages.fatherName')"
                  :error-messages="formErrors.father_name"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedStaff.contact"
                  :label="$t('common.fields.phone')"
                  :error-messages="formErrors.contact"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
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
                      <span class="account-selection__name">{{ item.raw.name }}</span><span class="account-selection__meta">#{{ item.raw.id }} · {{ item.raw.email }}</span>
                    </div>
                  </template>
                  <template #item="{ props, item }">
                    <VListItem
                      v-bind="props"
                      class="account-option"
                      :title="item.raw.name"
                    >
                      <template #prepend>
                        <VAvatar
                          color="primary"
                          variant="tonal"
                          size="38"
                        >
                          {{ avatarText(item.raw.name) }}
                        </VAvatar>
                      </template><template #subtitle>
                        <div class="account-option__details">
                          <span>#{{ item.raw.id }} · {{ item.raw.email }}</span><span v-if="item.raw.employee"><VIcon
                            icon="tabler-id-badge-2"
                            size="14"
                          /> {{ $t('common.accountPicker.linkedEmployee', { name: item.raw.employee.name, id: item.raw.employee.id }) }}</span><span v-if="item.raw.marketer"><VIcon
                            icon="tabler-speakerphone"
                            size="14"
                          /> {{ $t('common.accountPicker.linkedMarketer', { name: item.raw.marketer.name, id: item.raw.marketer.id }) }}</span><span v-if="!item.raw.employee && !item.raw.marketer">{{ $t('common.accountPicker.available') }}</span>
                        </div>
                      </template><template #append>
                        <span class="account-option__id">#{{ item.raw.id }}</span>
                      </template>
                    </VListItem>
                  </template>
                </VSelect>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedStaff.id_number"
                  :label="$t('common.messages.governmentIDNumber')"
                  :error-messages="formErrors.id_number"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedStaff.email"
                  :label="$t('common.fields.email')"
                  type="email"
                  :error-messages="formErrors.email"
                  :required="!selectedStaff.user_id"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="selectedStaff.education_lvl"
                  :items="educationOptions"
                  :label="$t('common.messages.educationLevel')"
                  :error-messages="formErrors.education_lvl"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
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
              <VCol
                cols="12"
                md="6"
              >
                <AppDateTimePicker
                  v-model="selectedStaff.join_date"
                  :label="$t('common.messages.joinDate')"
                  :error-messages="formErrors.join_date"
                  required
                  :config="{ dateFormat: 'Y-m-d' }"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="selectedStaff.office_id"
                  :items="offices"
                  item-title="name"
                  item-value="id"
                  :label="$t('common.fields.office')"
                  clearable
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="selectedStaff.employment_type"
                  :items="[{ title: 'Salaried', value: 'salaried' }, { title: 'On commission', value: 'commission' }]"
                  label="Employment type"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="selectedStaff.salary"
                  :label="selectedStaff.employment_type === 'commission' ? 'Base salary' : $t('common.messages.salary')"
                  type="number"
                  min="0"
                  :error-messages="formErrors.salary"
                  required
                />
              </VCol>
              <VCol
                v-if="selectedStaff.employment_type === 'commission'"
                cols="12"
              >
                <VCard
                  variant="flat"
                  class="commission-capabilities"
                >
                  <VCardText class="commission-capabilities__body">
                    <div class="commission-capabilities__header">
                      <div class="d-flex align-start ga-3">
                        <VAvatar
                          size="38"
                          color="primary"
                          variant="tonal"
                          rounded="lg"
                        >
                          <VIcon
                            icon="tabler-briefcase-2"
                            size="21"
                          />
                        </VAvatar>
                        <div>
                          <div class="text-subtitle-1 font-weight-semibold">
                            {{ $t('common.crmUi.commissionRateSetup.title') }}
                          </div>
                          <p class="commission-capabilities__description">
                            {{ $t('common.crmUi.commissionRateSetup.description') }}
                          </p>
                        </div>
                      </div>
                      <VBtn
                        size="small"
                        color="primary"
                        variant="flat"
                        prepend-icon="tabler-plus"
                        @click="addCommission"
                      >
                        Add service
                      </VBtn>
                    </div>
                    <VAlert
                      v-if="formErrors.commissions"
                      type="error"
                      variant="tonal"
                      density="compact"
                      class="commission-capabilities__alert"
                    >
                      {{ formErrors.commissions }}
                    </VAlert>
                    <VRow
                      v-for="(commission, index) in selectedStaff.commissions"
                      :key="index"
                      align="center"
                      class="commission-capabilities__row"
                    >
                      <VCol
                        cols="12"
                        sm="8"
                      >
                        <VSelect
                          v-model="commission.service_id"
                          :items="availableCommissionServices(commission.service_id)"
                          :item-title="commissionLabel"
                          item-value="id"
                          :label="$t('common.crmUi.commissionRateSetup.service')"
                          placeholder="Choose a service"
                          density="comfortable"
                        />
                      </VCol>
                      <VCol
                        cols="10"
                        sm="3"
                      >
                        <VTextField
                          v-model.number="commission.percentage"
                          type="number"
                          min="0.01"
                          max="100"
                          label="Commission rate"
                          suffix="%"
                          density="comfortable"
                        />
                      </VCol>
                      <VCol
                        cols="2"
                        sm="1"
                        class="text-end"
                      >
                        <VBtn
                          icon="tabler-x"
                          variant="text"
                          color="error"
                          @click="removeCommission(index)"
                        >
                          <VTooltip activator="parent">
                            Remove service
                          </VTooltip>
                        </VBtn>
                      </VCol>
                    </VRow>
                  </VCardText>
                </VCard>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedStaff.bank_account_number"
                  label="Bank account number"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedStaff.guarantor_id_number"
                  :label="$t('common.messages.guarantorGovernmentID')"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedStaff.guarantor_phone"
                  :label="$t('common.messages.guarantorPhone')"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
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
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="selectedStaff.addr"
                  :label="$t('common.fields.address')"
                  :error-messages="formErrors.addr"
                  required
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
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="isLoading"
            @click="updateStaff"
          >
            {{ $t('common.actions.save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="isPaidTimeOffDialogVisible"
      max-width="900px"
    >
      <VCard>
        <VCardTitle class="d-flex align-center pa-5">
          <div>
            <div class="text-h6">
              {{ $t('pages.settings.staffList.staffList.paidOffDays') }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ leaveStaff?.name }} · {{ paidTimeOffYear }}
            </div>
          </div>
          <VSpacer />
          <VTextField
            v-model.number="paidTimeOffYear"
            type="number"
            min="2000"
            max="3000"
            density="compact"
            hide-details
            style="max-width: 110px"
            @update:model-value="changePaidTimeOffYear"
          />
        </VCardTitle>

        <VCardText>
          <div class="d-flex flex-wrap gap-2 mb-5">
            <VChip
              color="primary"
              variant="tonal"
            >
              {{ $t('pages.users.staffList.leaveAllowance', { value: leaveAllowance }) }}
            </VChip>
            <VChip
              color="warning"
              variant="tonal"
            >
              {{ $t('pages.users.staffList.leaveUsed', { value: leaveUsed }) }}
            </VChip>
            <VChip
              color="success"
              variant="tonal"
            >
              {{ $t('pages.users.staffList.leaveRemaining', { value: leaveRemaining }) }}
            </VChip>
          </div>
          <VAlert
            v-if="leaveError"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ leaveError }}
          </VAlert>
          <VAlert
            v-else-if="paidTimeOffLimitMessage"
            type="warning"
            variant="tonal"
            class="mb-4"
          >
            {{ paidTimeOffLimitMessage }}
          </VAlert>

          <VRow class="align-end mb-3">
            <VCol
              cols="12"
              md="3"
            >
              <AppDateTimePicker
                v-model="leaveForm.date"
                :label="$t('common.fields.date')"
                :config="{ dateFormat: 'Y-m-d' }"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="leaveForm.reason"
                :label="$t('common.fields.reason')"
                maxlength="1000"
              />
            </VCol>
            <VCol
              cols="12"
              md="2"
            >
              <VSelect
                v-model="leaveForm.duration_days"
                :items="[{ title: 'Full day', value: 1 }, { title: 'Half day', value: 0.5 }]"
                label="Duration"
              />
            </VCol>
            <VCol
              cols="12"
              md="3"
              class="d-flex gap-2"
            >
              <VBtn
                color="primary"
                :loading="isLeaveLoading"
                :disabled="!canSavePaidTimeOff"
                @click="savePaidTimeOff"
              >
                {{ leaveForm.id ? 'Update' : 'Add day' }}
                <VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ !isPaidTimeOffFormComplete ? 'Enter both a date and reason.' : paidTimeOffLimitMessage }}
                </VTooltip>
              </VBtn>
              <VBtn
                v-if="leaveForm.id"
                icon="tabler-x"
                variant="text"
                @click="resetPaidTimeOffForm"
              />
            </VCol>
          </VRow>

          <ResponsiveDataTable
            :headers="[
              { title: translate('common.fields.date'), key: 'date' },
              { title: translate('common.fields.reason'), key: 'reason' },
              { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' },
            ]"
            :items="paidTimeOffs"
            item-value="id"
            :loading="isLeaveLoading"
            density="comfortable"
          >
            <template #item.actions="{ item }">
              <FullDetailsButton :item="item" />
              <VBtn
                icon="tabler-edit"
                size="small"
                variant="text"
                @click="editPaidTimeOff(item)"
              />
              <VBtn
                icon="tabler-trash"
                size="small"
                variant="text"
                color="error"
                @click="deletePaidTimeOff(item.id)"
              />
            </template>
          </ResponsiveDataTable>
        </VCardText>

        <VCardActions class="pa-5">
          <VSpacer /><VBtn
            variant="tonal"
            @click="isPaidTimeOffDialogVisible = false"
          >
            {{ $t('common.actions.close') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Staff Dialog -->
    <VDialog
      v-model="isDeleteStaffDialogVisible"
      max-width="500px"
    >
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">{{ $t('common.messages.confirmDelete') }}</span>
        </VCardTitle>

        <VCardText>
          {{ $t('common.confirmations.areYouSureYouWantToDeleteThisStaffRecord') }}
          <br>
          {{ $t('common.confirmations.thisActionCannotBeUndone') }}
        </VCardText>

        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isDeleteStaffDialogVisible = false"
          >
            {{ $t('common.actions.cancel') }}
          </VBtn>
          <VBtn
            color="error"
            :loading="isLoading"
            @click="deleteStaff"
          >
            {{ $t('common.actions.delete') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.commission-capabilities {
  overflow: hidden;
  border: 1px solid rgba(var(--v-theme-primary), .24);
  border-radius: 16px;
  background: linear-gradient(135deg, rgba(var(--v-theme-primary), .12), rgba(var(--v-theme-surface), .98) 58%) !important;
  box-shadow: inset 0 1px rgba(var(--v-theme-on-surface), .05);
}

.commission-capabilities__body {
  padding: 1.25rem !important;
}

.commission-capabilities__header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-block-end: 1rem;
}

.commission-capabilities__description {
  max-inline-size: 620px;
  margin: .2rem 0 0;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: .8rem;
  line-height: 1.45;
}

.commission-capabilities__alert {
  margin-block-end: 1rem;
}

.commission-capabilities__row {
  margin: 0 !important;
  padding: .25rem 0;
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), .09);
}

.commission-capabilities__row :deep(.v-col) {
  padding-block: .65rem;
}

@media (max-width: 599px) {
  .commission-capabilities__body { padding: 1rem !important; }
  .commission-capabilities__header > .v-btn { inline-size: 100%; }
}

.employee-account-select :deep(.v-field) { background: rgba(var(--v-theme-on-surface), .035); border: 1px solid rgba(var(--v-theme-on-surface), .16); border-radius: 12px; }
.employee-account-select :deep(.v-field--focused) { border-color: rgb(var(--v-theme-primary)); box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), .12); }
.account-selection { display: grid; line-height: 1.2; min-width: 0; }
.account-selection__name { font-weight: 650; overflow: hidden; text-overflow: ellipsis; }
.account-selection__meta, .account-option__id { color: rgba(var(--v-theme-on-surface), .62); font-size: .75rem; }
:global(.account-option) { margin: 4px 6px; border-radius: 10px; }
:global(.account-option:hover) { background: rgba(var(--v-theme-primary), .10); }
:global(.account-option__details) { display: grid; gap: 2px; margin-top: 2px; color: rgba(var(--v-theme-on-surface), .64); font-size: .75rem; }
</style>

<style scoped>
.staff-table :deep(tbody td) {
  padding-block: 0.875rem;
}

.table-container {
  overflow-x: auto;
}
</style>
