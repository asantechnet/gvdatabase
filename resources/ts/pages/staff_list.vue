<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { VDialog } from 'vuetify/components'
import EmployeeApi, { type Employee, type EmployeeAccount, type CreateEmployeeData, type UpdateEmployeeData } from '@/api/employee'
import PositionApi from '@/api/position'
import OfficeApi, { type Office } from '@/api/office'
import PaidTimeOffApi, { type PaidTimeOff } from '@/api/paidTimeOff'
import ServiceApi, { type Service } from '@/api/services'
import { apiErrorMessage, apiFieldErrors } from '@/utils/apiErrors'
import { printDocument } from '@/utils/printDocument'
import PrintableListReport from '@/components/PrintableListReport.vue'
import { useRouter } from 'vue-router'

interface Staff {
  id: number
  user_id: number | null
  name: string
  father_name: string
  contact: string
  id_number: string | null
  tax_number: string | null
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
  commissions: Array<{ service_id: number; service?: string; percentage: number }>
  total_worked_hours: number
  addr: string
  ID_file: string | null
  assure_file: string | null
  join_date: string
  contract_duration: string
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
const offices = ref<Office[]>([])
const services = ref<Service[]>([])
const userAccounts = ref<EmployeeAccount[]>([])
const isUserAccountsLoading = ref(false)
const formSubmissionError = ref('')
const leaveStaff = ref<Staff | null>(null)
const paidTimeOffs = ref<PaidTimeOff[]>([])
const paidTimeOffYear = ref(new Date().getFullYear())
const leaveAllowance = ref(0)
const leaveUsed = ref(0)
const leaveRemaining = ref(0)
const leaveForm = ref({ id: null as number | null, date: new Date().toISOString().slice(0, 10), reason: '' })
const leaveError = ref('')
const isLeaveLoading = ref(false)

const isPaidTimeOffFormComplete = computed(() => Boolean(
  leaveForm.value.date
  && leaveForm.value.reason.trim(),
))

const canSavePaidTimeOff = computed(() => Boolean(
  !isLeaveLoading.value
  && isPaidTimeOffFormComplete.value
  && (leaveForm.value.id || leaveRemaining.value > 0),
))

const paidTimeOffLimitMessage = computed(() => {
  if (leaveForm.value.id || leaveRemaining.value > 0)
    return ''

  return leaveAllowance.value === 0
    ? 'No paid-off days are configured. Set Paid-off days per year in Settings before adding a record.'
    : `This employee has used all ${leaveAllowance.value} paid-off days for ${paidTimeOffYear.value}.`
})

const educationOptions = ['High School', 'Diploma', 'Bachelor', 'Master', 'PhD']
const contractDurationOptions = ['6 months', '1 year', '2 years', '3 years', 'Permanent']
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
  education_lvl: '',
  contract_duration: '',
  join_date: ''
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
    education_lvl: '',
    contract_duration: '',
    join_date: ''
  }
}

const validateStaffForm = () => {
  let isValid = true

  // Validate Name
  if (!selectedStaff.value?.name.trim()) {
    formErrors.value.name = translate('pages.settings.staffList.staffList.nameIsRequired')
    isValid = false
  } else if (selectedStaff.value.name.length < 2) {
    formErrors.value.name = translate('pages.settings.staffList.staffList.nameMustBeAtLeast2Characters')
    isValid = false
  } else {
    formErrors.value.name = ''
  }

  // Validate Father Name
  if (!selectedStaff.value?.father_name.trim()) {
    formErrors.value.father_name = translate('pages.settings.staffList.staffList.fatherNameIsRequired')
    isValid = false
  } else if (selectedStaff.value.father_name.length < 2) {
    formErrors.value.father_name = translate('pages.settings.staffList.staffList.fatherNameMustBeAtLeast2Characters')
    isValid = false
  } else {
    formErrors.value.father_name = ''
  }

  // Validate Contact
  if (!selectedStaff.value?.contact.trim()) {
    formErrors.value.contact = translate('common.messages.contactNumberIsRequired')
    isValid = false
  } else if (!/^[\d\s\-()+]{8,}$/.test(selectedStaff.value.contact)) {
    formErrors.value.contact = translate('common.messages.pleaseEnterAValidPhoneNumber')
    isValid = false
  } else {
    formErrors.value.contact = ''
  }

  // Validate Email
  if (!selectedStaff.value?.user_id && !selectedStaff.value?.email.trim()) {
    formErrors.value.email = translate('pages.settings.staffList.staffList.emailIsRequired')
    isValid = false
  } else if (!selectedStaff.value?.user_id && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(selectedStaff.value.email)) {
    formErrors.value.email = translate('pages.settings.staffList.staffList.pleaseEnterAValidEmailAddress')
    isValid = false
  } else {
    formErrors.value.email = ''
  }

  // Validate Address
  if (!selectedStaff.value?.addr.trim()) {
    formErrors.value.addr = translate('pages.settings.staffList.staffList.addressIsRequired')
    isValid = false
  } else if (selectedStaff.value.addr.length < 5) {
    formErrors.value.addr = translate('pages.settings.staffList.staffList.addressMustBeAtLeast5Characters')
    isValid = false
  } else {
    formErrors.value.addr = ''
  }

  if (!selectedStaff.value?.id_number?.trim()) {
    formErrors.value.id_number = translate('pages.settings.staffList.staffList.governmentIDIsRequired')
    isValid = false
  } else {
    formErrors.value.id_number = ''
  }

  // Validate Position
  if (!selectedStaff.value?.position) {
    formErrors.value.position = translate('pages.settings.staffList.staffList.positionIsRequired')
    isValid = false
  } else {
    formErrors.value.position = ''
  }

  // Validate Salary
  if (selectedStaff.value?.employment_type === 'salaried' && (selectedStaff.value?.salary === null || selectedStaff.value?.salary === undefined)) {
    formErrors.value.salary = translate('pages.settings.staffList.staffList.salaryIsRequired')
    isValid = false
  } else if (selectedStaff.value?.employment_type === 'salaried' && Number(selectedStaff.value.salary) <= 0) {
    formErrors.value.salary = translate('pages.settings.staffList.staffList.salaryMustBeGreaterThan0')
    isValid = false
  } else {
    formErrors.value.salary = ''
  }

  // Validate Education Level
  if (!selectedStaff.value?.education_lvl) {
    formErrors.value.education_lvl = translate('pages.settings.staffList.staffList.educationLevelIsRequired')
    isValid = false
  } else {
    formErrors.value.education_lvl = ''
  }

  // Validate Contract Duration
  if (!selectedStaff.value?.contract_duration) {
    formErrors.value.contract_duration = translate('pages.settings.staffList.staffList.contractDurationIsRequired')
    isValid = false
  } else {
    formErrors.value.contract_duration = ''
  }

  // Validate Join Date
  if (!selectedStaff.value?.join_date) {
    formErrors.value.join_date = translate('pages.settings.staffList.staffList.joinDateIsRequired')
    isValid = false
  } else {
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
    tax_number: '',
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
    ID_file: null,
    assure_file: null,
    join_date: new Date().toISOString().split('T')[0],
    contract_duration: ''
  }
  resetFormErrors()
  formSubmissionError.value = ''
  isAddStaffDialogVisible.value = true
}

const openEditStaffDialog = (staff: Staff) => {
  selectedStaff.value = {...staff}
  resetFormErrors()
  formSubmissionError.value = ''
  isEditStaffDialogVisible.value = true
}

const openDeleteStaffDialog = (staff: Staff) => {
  selectedStaff.value = staff
  isDeleteStaffDialogVisible.value = true
}

const loadPaidTimeOffs = async () => {
  if (!leaveStaff.value) return
  isLeaveLoading.value = true
  leaveError.value = ''
  try {
    const ledger = await PaidTimeOffApi.getAll(leaveStaff.value.id, paidTimeOffYear.value)
    paidTimeOffs.value = ledger.data
    leaveAllowance.value = ledger.allowance
    leaveUsed.value = ledger.used
    leaveRemaining.value = ledger.remaining
  } catch (error) {
    leaveError.value = apiErrorMessage(error, 'Unable to load paid-off days.')
  } finally {
    isLeaveLoading.value = false
  }
}

const openPaidTimeOffDialog = async (staff: Staff) => {
  leaveStaff.value = staff
  paidTimeOffYear.value = new Date().getFullYear()
  leaveForm.value = { id: null, date: new Date().toISOString().slice(0, 10), reason: '' }
  isPaidTimeOffDialogVisible.value = true
  await loadPaidTimeOffs()
}

const editPaidTimeOff = (record: PaidTimeOff) => {
  leaveForm.value = { id: record.id, date: record.date, reason: record.reason }
  leaveError.value = ''
}

const resetPaidTimeOffForm = () => {
  leaveForm.value = { id: null, date: `${paidTimeOffYear.value}-01-01`, reason: '' }
  leaveError.value = ''
}

const changePaidTimeOffYear = async () => {
  if (!leaveForm.value.id)
    leaveForm.value.date = `${paidTimeOffYear.value}-01-01`

  await loadPaidTimeOffs()
}

const savePaidTimeOff = async () => {
  if (!leaveStaff.value) return
  if (!leaveForm.value.date || !leaveForm.value.reason.trim()) {
    leaveError.value = translate(!leaveForm.value.date ? 'pages.users.staffList.dateRequired' : 'pages.users.staffList.reasonRequired')
    return
  }
  if (!leaveForm.value.date.startsWith(`${paidTimeOffYear.value}-`)) {
    leaveError.value = `Date: Choose a date in ${paidTimeOffYear.value}.`
    return
  }
  if (!leaveForm.value.id && leaveRemaining.value < 1) {
    leaveError.value = translate('pages.settings.staffList.staffList.paidOffDaysTheYearlyAllowanceHasBeenUsed')
    return
  }

  isLeaveLoading.value = true
  leaveError.value = ''
  try {
    const payload = { date: leaveForm.value.date, reason: leaveForm.value.reason.trim() }
    if (leaveForm.value.id)
      await PaidTimeOffApi.update(leaveStaff.value.id, leaveForm.value.id, payload)
    else
      await PaidTimeOffApi.create(leaveStaff.value.id, payload)
    resetPaidTimeOffForm()
    await loadPaidTimeOffs()
  } catch (error) {
    leaveError.value = apiErrorMessage(error, 'Unable to save paid-off day.')
  } finally {
    isLeaveLoading.value = false
  }
}

const deletePaidTimeOff = async (id: number) => {
  if (!leaveStaff.value) return
  isLeaveLoading.value = true
  try {
    await PaidTimeOffApi.delete(leaveStaff.value.id, id)
    if (leaveForm.value.id === id) resetPaidTimeOffForm()
    await loadPaidTimeOffs()
  } catch (error) {
    leaveError.value = apiErrorMessage(error, 'Unable to remove paid-off day.')
  } finally {
    isLeaveLoading.value = false
  }
}

const fetchPositions = async () => {
  try {
    isPositionsLoading.value = true
    const response = await PositionApi.getAll()
    if (response.data && Array.isArray(response.data.data)) {
      positionOptions.value = response.data.data.map((pos: any) => pos.title)
      positionIdMap.value = response.data.data.reduce((map: Record<string, number>, pos: any) => {
        map[pos.title] = pos.id
        return map
      }, {})
    } else if (Array.isArray(response.data)) {
      positionOptions.value = response.data.map((pos: any) => pos.title)
      positionIdMap.value = response.data.reduce((map: Record<string, number>, pos: any) => {
        map[pos.title] = pos.id
        return map
      }, {})
    }
  } catch (error) {
    console.error('Error fetching positions:', error)
  } finally {
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
        position: emp.position?.name || positionOptions.value[emp.position_id - 1] || emp.position_id.toString()
      }))
    } else if (Array.isArray(response.data)) {
      staffList.value = response.data.map(emp => ({
        ...emp,
        email: emp.email ?? '',
        salary: emp.salary ?? 0,
        join_date: emp.join_date ?? '',
        position: emp.position?.name || positionOptions.value[emp.position_id - 1] || emp.position_id.toString()
      }))
    }
  } catch (error) {
    console.error('Error fetching employees:', error)
  } finally {
    isLoading.value = false
  }
}

const fetchUserAccounts = async () => {
  try {
    isUserAccountsLoading.value = true
    userAccounts.value = await EmployeeApi.getAccountOptions()
  } catch (error) {
    console.error('Error fetching user accounts:', error)
  } finally {
    isUserAccountsLoading.value = false
  }
}

const userAccountOptions = computed(() => userAccounts.value.map(user => ({
  title: `#${user.id} ${user.name} (${user.email})${user.employee ? ` - Linked to ${user.employee.name} (#${user.employee.id})` : ''}`,
  value: user.id,
  disabled: Boolean(user.employee && user.employee.id !== selectedStaff.value?.id),
})))

const updateSelectedAccountEmail = (userId: number | null) => {
  const user = userAccounts.value.find(account => account.id === userId)
  if (selectedStaff.value && user)
    selectedStaff.value.email = user.email
}

const addStaff = async () => {
  if (!selectedStaff.value) return
  if (!validateStaffForm()) return

  try {
    isLoading.value = true
    const positionId = positionIdMap.value[selectedStaff.value.position]
    const data: CreateEmployeeData = {
      user_id: selectedStaff.value.user_id,
      name: selectedStaff.value.name,
      father_name: selectedStaff.value.father_name,
      contact: selectedStaff.value.contact,
      id_number: selectedStaff.value.id_number ?? '',
      tax_number: selectedStaff.value.tax_number,
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
      ID_file: selectedStaff.value.ID_file,
      assure_file: selectedStaff.value.assure_file,
      join_date: selectedStaff.value.join_date,
      contract_duration: selectedStaff.value.contract_duration
    }

    const response = await EmployeeApi.create(data)
    if (response.data && response.data.data) {
      staffList.value.unshift({
        ...response.data.data,
        email: response.data.data.email ?? '',
        salary: response.data.data.salary ?? 0,
        join_date: response.data.data.join_date ?? '',
        position: response.data.data.position?.name || positionOptions.value[response.data.data.position_id - 1] || response.data.data.position_id.toString()
      })
    }
    isAddStaffDialogVisible.value = false
  } catch (error) {
    console.error('Error creating employee:', error)
    applyStaffApiErrors(error)
  } finally {
    isLoading.value = false
  }
}

const updateStaff = async () => {
  if (!selectedStaff.value) return
  if (!validateStaffForm()) return

  try {
    isLoading.value = true
    const positionId = positionIdMap.value[selectedStaff.value.position]
    const data: UpdateEmployeeData = {
      user_id: selectedStaff.value.user_id,
      name: selectedStaff.value.name,
      father_name: selectedStaff.value.father_name,
      contact: selectedStaff.value.contact,
      id_number: selectedStaff.value.id_number ?? '',
      tax_number: selectedStaff.value.tax_number,
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
      ID_file: selectedStaff.value.ID_file,
      assure_file: selectedStaff.value.assure_file,
      join_date: selectedStaff.value.join_date,
      contract_duration: selectedStaff.value.contract_duration
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
          position: response.data.data.position?.name || positionOptions.value[response.data.data.position_id - 1] || response.data.data.position_id.toString()
        }
      }
    }
    isEditStaffDialogVisible.value = false
  } catch (error) {
    console.error('Error updating employee:', error)
    applyStaffApiErrors(error)
  } finally {
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

const deleteStaff = async () => {
  if (!selectedStaff.value) return

  try {
    isLoading.value = true
    await EmployeeApi.delete(selectedStaff.value.id)
    staffList.value = staffList.value.filter(s => s.id !== selectedStaff.value?.id)
    isDeleteStaffDialogVisible.value = false
  } catch (error) {
    console.error('Error deleting employee:', error)
  } finally {
    isLoading.value = false
  }
}

const filteredStaff = computed(() => {
  return staffList.value.filter(staff => {
    const matchesSearch = staff.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      staff.father_name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      staff.contact.includes(searchQuery.value) ||
      (staff.id_number ?? '').includes(searchQuery.value) ||
      staff.email.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      staff.position.toLowerCase().includes(searchQuery.value.toLowerCase())

    const matchesPosition = !filterPosition.value || staff.position === filterPosition.value
    return matchesSearch && matchesPosition
  })
})

const printTable = () => {
  void printDocument({ bodyClass: 'printing-table-staff', pageStyle: 'size: A4 landscape; margin: 12mm;' })
}

onMounted(async () => {
  const [, officeRows, serviceRows] = await Promise.all([fetchPositions(), OfficeApi.getAll(), ServiceApi.getAll(), fetchUserAccounts()])
  offices.value = officeRows.filter(office => office.status === 'active')
  services.value = serviceRows.data.filter(service => service.status === 'active')
  await fetchEmployees()
})
</script>

<template>
  <div>
    <DataTablePageKpis :items="filteredStaff" title="Staff directory" description="See your team, their assignments, and records that need follow-up." icon="tabler-users" />
    <VCard class="mb-6">
      <VCardText>
        <VRow align="center">
          <VCol cols="12" md="6">
            <VTextField
              v-model="searchQuery"
              class="data-table-search" prepend-inner-icon="tabler-search"
              :placeholder="$t('pages.settings.staffList.staffList.searchStaff')"
              density="compact"
              clearable
              hide-details
            />
          </VCol>

          <VCol cols="12" md="6" class="d-flex gap-2 justify-end">
            <DataTableFilterMenu :active="Boolean(filterPosition)" @reset="filterPosition = null">
              <VSelect v-model="filterPosition" :items="positionOptions" :label="$t('pages.settings.staffList.staffList.filterByPosition')"
                density="compact" clearable hide-details :loading="isPositionsLoading" />
            </DataTableFilterMenu>
            <VBtn
              icon="tabler-printer"
              variant="text"
              @click="printTable"
            ><VTooltip activator="parent" location="top">{{ $t('pages.settings.staffList.staffList.printStaffRecords') }}</VTooltip></VBtn>
            <VBtn
              color="primary"
              @click="openAddStaffDialog"
            >
              <VIcon icon="tabler-plus" class="me-1" />
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
            { title: translate('common.messages.actions'), key: 'actions', sortable: false }
          ]"
          item-value="id"
          class="text-no-wrap staff-table"
          :loading="isLoading"
          mobile-breakpoint="sm"
          density="comfortable"
        >
          <template #item.salary="{ item }">
            <span>{{ item.employment_type === 'commission' ? 'Commission' : `$${Number(item.salary || 0).toFixed(2)}` }}</span>
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
                <VImg v-if="item.avatar" :src="item.avatar" :alt="item.name" />
                <span v-else>{{ avatarText(item.name) }}</span>
              </VAvatar>
              <span>{{ item.name }}</span>
            </div>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <FullDetailsButton :item="item" :title="item.name" />
              <VBtn icon="tabler-file-description" variant="text" size="small" color="primary" @click="printEmployeeContract(item)"><VTooltip activator="parent" location="top">Print employment contract</VTooltip></VBtn>
              <VBtn
                icon="tabler-calendar-time"
                variant="text"
                size="small"
                color="primary"
                @click="openPaidTimeOffDialog(item)"
              ><VTooltip activator="parent" location="top">{{ $t('pages.settings.staffList.staffList.managePaidOffDays') }}</VTooltip></VBtn>
              <VBtn
                icon="tabler-edit"
                variant="text"
                size="small"
                color="medium-emphasis"
                @click="openEditStaffDialog(item)"
              ><VTooltip activator="parent" location="top">{{ $t('pages.settings.staffList.staffList.editStaffRecord') }}</VTooltip></VBtn>
              <VBtn
                icon="tabler-trash"
                variant="text"
                size="small"
                color="error"
                @click="openDeleteStaffDialog(item)"
              ><VTooltip activator="parent" location="top">{{ $t('pages.settings.staffList.staffList.deleteStaffRecord') }}</VTooltip></VBtn>
            </div>
          </template>
        </ResponsiveDataTable>
      </div>
    </VCard>

    <PrintableListReport title="Staff Report" subtitle="Filtered staff records" :columns="['ID', 'Name', 'Position', 'Contact', 'Email', 'Office']" :rows="filteredStaff.map(staff => [staff.id, staff.name, staff.position, staff.contact, staff.email, staff.office?.name])" />

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
            <VAlert v-if="formSubmissionError" type="error" variant="tonal" class="mb-4">
              {{ formSubmissionError }}
            </VAlert>
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.name"
                  :label="$t('common.fields.name')"
                  :error-messages="formErrors.name"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.father_name"
                  :label="$t('common.messages.fatherName')"
                  :error-messages="formErrors.father_name"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.contact"
                  :label="$t('common.messages.contactPhone')"
                  :error-messages="formErrors.contact"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStaff.user_id"
                  :items="userAccountOptions"
                  label="User account"
                  hint="Leave empty to create a new account with the employee email."
                  persistent-hint
                  clearable
                  :loading="isUserAccountsLoading"
                  @update:model-value="updateSelectedAccountEmail"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.id_number"
                  :label="$t('common.messages.governmentIDNumber')"
                  :error-messages="formErrors.id_number"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.email"
                  :label="$t('common.fields.email')"
                  type="email"
                  :error-messages="formErrors.email"
                  :required="!selectedStaff.user_id"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStaff.education_lvl"
                  :items="educationOptions"
                  :label="$t('common.messages.educationLevel')"
                  :error-messages="formErrors.education_lvl"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStaff.position"
                  :items="positionOptions"
                  :label="$t('common.messages.position')"
                  :error-messages="formErrors.position"
                  required
                  :loading="isPositionsLoading"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect v-model="selectedStaff.office_id" :items="offices" item-title="name" item-value="id" :label="$t('common.fields.office')" clearable />
              </VCol>
              <VCol cols="12" md="6"><VSelect v-model="selectedStaff.employment_type" :items="[{title:'Salaried',value:'salaried'},{title:'On commission',value:'commission'}]" label="Employment type" /></VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-if="selectedStaff.employment_type === 'salaried'"
                  v-model.number="selectedStaff.salary"
                  :label="$t('common.messages.salary')"
                  type="number"
                  :error-messages="formErrors.salary"
                  required
                />
              </VCol>
              <VCol v-if="selectedStaff.employment_type === 'commission'" cols="12">
                <div class="d-flex justify-space-between align-center mb-2"><span class="text-subtitle-2">Service commissions</span><VBtn size="small" variant="tonal" prepend-icon="tabler-plus" @click="addCommission">Add service</VBtn></div>
                <VRow v-for="(commission, index) in selectedStaff.commissions" :key="index"><VCol cols="8"><VSelect v-model="commission.service_id" :items="services" item-title="type" item-value="id" label="Service" /></VCol><VCol cols="3"><VTextField v-model.number="commission.percentage" type="number" min="0.01" max="100" label="Percentage" suffix="%" /></VCol><VCol cols="1"><VBtn icon="tabler-x" variant="text" color="error" @click="removeCommission(index)" /></VCol></VRow>
              </VCol>
              <VCol cols="12" md="6"><VTextField v-model="selectedStaff.tax_number" label="Tax number" /></VCol>
              <VCol cols="12" md="6"><VTextField v-model="selectedStaff.bank_account_number" label="Bank account number" /></VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStaff.contract_duration"
                  :items="contractDurationOptions"
                  :label="$t('common.messages.contractDuration')"
                  :error-messages="formErrors.contract_duration"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.ID_file"
                  :label="$t('common.messages.idFile')"
                  :placeholder="$t('common.messages.uploadFileName')"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.assure_file"
                  :label="$t('common.messages.assuranceFile')"
                  :placeholder="$t('common.messages.uploadFileName')"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model="selectedStaff.guarantor_id_number" :label="$t('common.messages.guarantorGovernmentID')" />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model="selectedStaff.guarantor_phone" :label="$t('common.messages.guarantorPhone')" />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model="selectedStaff.guarantor_licence" :label="$t('common.messages.guarantorLicence')" />
              </VCol>
              <VCol cols="12">
                <VTextarea v-model="selectedStaff.guarantor_address" :label="$t('common.messages.guarantorAddress')" rows="2" auto-grow />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.addr"
                  :label="$t('common.fields.address')"
                  :error-messages="formErrors.addr"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppDateTimePicker
                  v-model="selectedStaff.join_date"
                  :label="$t('common.messages.joinDate')"
                  :error-messages="formErrors.join_date"
                  required
                  :config="{ dateFormat: 'Y-m-d' }"
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
            @click="addStaff"
            :loading="isLoading"
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
            <VAlert v-if="formSubmissionError" type="error" variant="tonal" class="mb-4">
              {{ formSubmissionError }}
            </VAlert>
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.name"
                  :label="$t('common.fields.name')"
                  :error-messages="formErrors.name"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.father_name"
                  :label="$t('common.messages.fatherName')"
                  :error-messages="formErrors.father_name"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.contact"
                  :label="$t('common.messages.contactPhone')"
                  :error-messages="formErrors.contact"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStaff.user_id"
                  :items="userAccountOptions"
                  label="User account"
                  hint="Linked accounts are labeled with their employee name and ID."
                  persistent-hint
                  clearable
                  :loading="isUserAccountsLoading"
                  @update:model-value="updateSelectedAccountEmail"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.id_number"
                  :label="$t('common.messages.governmentIDNumber')"
                  :error-messages="formErrors.id_number"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.email"
                  :label="$t('common.fields.email')"
                  type="email"
                  :error-messages="formErrors.email"
                  :required="!selectedStaff.user_id"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStaff.education_lvl"
                  :items="educationOptions"
                  :label="$t('common.messages.educationLevel')"
                  :error-messages="formErrors.education_lvl"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStaff.position"
                  :items="positionOptions"
                  :label="$t('common.messages.position')"
                  :error-messages="formErrors.position"
                  required
                  :loading="isPositionsLoading"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect v-model="selectedStaff.office_id" :items="offices" item-title="name" item-value="id" :label="$t('common.fields.office')" clearable />
              </VCol>
              <VCol cols="12" md="6"><VSelect v-model="selectedStaff.employment_type" :items="[{title:'Salaried',value:'salaried'},{title:'On commission',value:'commission'}]" label="Employment type" /></VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-if="selectedStaff.employment_type === 'salaried'"
                  v-model.number="selectedStaff.salary"
                  :label="$t('common.messages.salary')"
                  type="number"
                  :error-messages="formErrors.salary"
                  required
                />
              </VCol>
              <VCol v-if="selectedStaff.employment_type === 'commission'" cols="12">
                <div class="d-flex justify-space-between align-center mb-2"><span class="text-subtitle-2">Service commissions</span><VBtn size="small" variant="tonal" prepend-icon="tabler-plus" @click="addCommission">Add service</VBtn></div>
                <VRow v-for="(commission, index) in selectedStaff.commissions" :key="index"><VCol cols="8"><VSelect v-model="commission.service_id" :items="services" item-title="type" item-value="id" label="Service" /></VCol><VCol cols="3"><VTextField v-model.number="commission.percentage" type="number" min="0.01" max="100" label="Percentage" suffix="%" /></VCol><VCol cols="1"><VBtn icon="tabler-x" variant="text" color="error" @click="removeCommission(index)" /></VCol></VRow>
              </VCol>
              <VCol cols="12" md="6"><VTextField v-model="selectedStaff.tax_number" label="Tax number" /></VCol>
              <VCol cols="12" md="6"><VTextField v-model="selectedStaff.bank_account_number" label="Bank account number" /></VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStaff.contract_duration"
                  :items="contractDurationOptions"
                  :label="$t('common.messages.contractDuration')"
                  :error-messages="formErrors.contract_duration"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.ID_file"
                  :label="$t('common.messages.idFile')"
                  :placeholder="$t('common.messages.uploadFileName')"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.assure_file"
                  :label="$t('common.messages.assuranceFile')"
                  :placeholder="$t('common.messages.uploadFileName')"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model="selectedStaff.guarantor_id_number" :label="$t('common.messages.guarantorGovernmentID')" />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model="selectedStaff.guarantor_phone" :label="$t('common.messages.guarantorPhone')" />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField v-model="selectedStaff.guarantor_licence" :label="$t('common.messages.guarantorLicence')" />
              </VCol>
              <VCol cols="12">
                <VTextarea v-model="selectedStaff.guarantor_address" :label="$t('common.messages.guarantorAddress')" rows="2" auto-grow />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.addr"
                  :label="$t('common.fields.address')"
                  :error-messages="formErrors.addr"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppDateTimePicker
                  v-model="selectedStaff.join_date"
                  :label="$t('common.messages.joinDate')"
                  :error-messages="formErrors.join_date"
                  required
                  :config="{ dateFormat: 'Y-m-d' }"
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
            @click="updateStaff"
            :loading="isLoading"
          >
            {{ $t('common.actions.save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="isPaidTimeOffDialogVisible" max-width="900px">
      <VCard>
        <VCardTitle class="d-flex align-center pa-5">
          <div>
            <div class="text-h6">{{ $t('pages.settings.staffList.staffList.paidOffDays') }}</div>
            <div class="text-caption text-medium-emphasis">{{ leaveStaff?.name }} · {{ paidTimeOffYear }}</div>
          </div>
          <VSpacer />
          <VTextField v-model.number="paidTimeOffYear" type="number" min="2000" max="3000" density="compact" hide-details style="max-width: 110px" @update:model-value="changePaidTimeOffYear" />
        </VCardTitle>

        <VCardText>
          <div class="d-flex flex-wrap gap-2 mb-5">
            <VChip color="primary" variant="tonal">{{ $t('pages.users.staffList.leaveAllowance', { value: leaveAllowance }) }}</VChip>
            <VChip color="warning" variant="tonal">{{ $t('pages.users.staffList.leaveUsed', { value: leaveUsed }) }}</VChip>
            <VChip color="success" variant="tonal">{{ $t('pages.users.staffList.leaveRemaining', { value: leaveRemaining }) }}</VChip>
          </div>
          <VAlert v-if="leaveError" type="error" variant="tonal" class="mb-4">{{ leaveError }}</VAlert>
          <VAlert v-else-if="paidTimeOffLimitMessage" type="warning" variant="tonal" class="mb-4">{{ paidTimeOffLimitMessage }}</VAlert>

          <VRow class="align-end mb-3">
            <VCol cols="12" md="3">
              <AppDateTimePicker v-model="leaveForm.date" :label="$t('common.fields.date')" :config="{ dateFormat: 'Y-m-d' }" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="leaveForm.reason" :label="$t('common.fields.reason')" maxlength="1000" />
            </VCol>
            <VCol cols="12" md="3" class="d-flex gap-2">
              <VBtn
                color="primary"
                :loading="isLeaveLoading"
                :disabled="!canSavePaidTimeOff"
                @click="savePaidTimeOff"
              >
                {{ leaveForm.id ? 'Update' : 'Add day' }}
                <VTooltip activator="parent" location="top">
                  {{ !isPaidTimeOffFormComplete ? 'Enter both a date and reason.' : paidTimeOffLimitMessage }}
                </VTooltip>
              </VBtn>
              <VBtn v-if="leaveForm.id" icon="tabler-x" variant="text" @click="resetPaidTimeOffForm" />
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
              <VBtn icon="tabler-edit" size="small" variant="text" @click="editPaidTimeOff(item)" />
              <VBtn icon="tabler-trash" size="small" variant="text" color="error" @click="deletePaidTimeOff(item.id)" />
            </template>
          </ResponsiveDataTable>
        </VCardText>

        <VCardActions class="pa-5"><VSpacer /><VBtn variant="tonal" @click="isPaidTimeOffDialogVisible = false">{{ $t('common.actions.close') }}</VBtn></VCardActions>
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
            @click="deleteStaff"
            :loading="isLoading"
          >
            {{ $t('common.actions.delete') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.staff-table :deep(tbody td) {
  padding-block: 0.875rem;
}

.table-container {
  overflow-x: auto;
}
</style>
