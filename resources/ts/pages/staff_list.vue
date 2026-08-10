<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { VDialog } from 'vuetify/components'
import EmployeeApi, { type Employee, type CreateEmployeeData, type UpdateEmployeeData } from '@/api/employee'
import PositionApi from '@/api/position'

interface Staff extends Omit<Employee, 'position_id' | 'position' | 'email'> {
  position: string
  email: string
}

const staffList = ref<Staff[]>([])
const isAddStaffDialogVisible = ref(false)
const isEditStaffDialogVisible = ref(false)
const isDeleteStaffDialogVisible = ref(false)
const selectedStaff = ref<Staff | null>(null)
const searchQuery = ref('')
const filterPosition = ref<string | null>(null)
const filterBonus = ref<string | null>(null)
const isLoading = ref(false)
const isPositionsLoading = ref(false)
const positionOptions = ref<string[]>([])
const positionIdMap = ref<Record<string, number>>({})

const educationOptions = ['High School', 'Diploma', 'Bachelor', 'Master', 'PhD']
const bonusOptions = ['Yes', 'No']
const contractDurationOptions = ['6 months', '1 year', '2 years', '3 years', 'Permanent']

const formErrors = ref({
  name: '',
  father_name: '',
  contact: '',
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
    formErrors.value.name = 'Name is required'
    isValid = false
  } else if (selectedStaff.value.name.length < 2) {
    formErrors.value.name = 'Name must be at least 2 characters'
    isValid = false
  } else {
    formErrors.value.name = ''
  }

  // Validate Father Name
  if (!selectedStaff.value?.father_name.trim()) {
    formErrors.value.father_name = 'Father name is required'
    isValid = false
  } else if (selectedStaff.value.father_name.length < 2) {
    formErrors.value.father_name = 'Father name must be at least 2 characters'
    isValid = false
  } else {
    formErrors.value.father_name = ''
  }

  // Validate Contact
  if (!selectedStaff.value?.contact.trim()) {
    formErrors.value.contact = 'Contact number is required'
    isValid = false
  } else if (!/^[\d\s\-()+]{8,}$/.test(selectedStaff.value.contact)) {
    formErrors.value.contact = 'Please enter a valid phone number'
    isValid = false
  } else {
    formErrors.value.contact = ''
  }

  // Validate Email
  if (!selectedStaff.value?.email.trim()) {
    formErrors.value.email = 'Email is required'
    isValid = false
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(selectedStaff.value.email)) {
    formErrors.value.email = 'Please enter a valid email address'
    isValid = false
  } else {
    formErrors.value.email = ''
  }

  // Validate Address
  if (!selectedStaff.value?.addr.trim()) {
    formErrors.value.addr = 'Address is required'
    isValid = false
  } else if (selectedStaff.value.addr.length < 5) {
    formErrors.value.addr = 'Address must be at least 5 characters'
    isValid = false
  } else {
    formErrors.value.addr = ''
  }

  // Validate Position
  if (!selectedStaff.value?.position) {
    formErrors.value.position = 'Position is required'
    isValid = false
  } else {
    formErrors.value.position = ''
  }

  // Validate Salary
  if (selectedStaff.value?.salary === null || selectedStaff.value?.salary === undefined) {
    formErrors.value.salary = 'Salary is required'
    isValid = false
  } else if (selectedStaff.value.salary <= 0) {
    formErrors.value.salary = 'Salary must be greater than 0'
    isValid = false
  } else {
    formErrors.value.salary = ''
  }

  // Validate Education Level
  if (!selectedStaff.value?.education_lvl) {
    formErrors.value.education_lvl = 'Education level is required'
    isValid = false
  } else {
    formErrors.value.education_lvl = ''
  }

  // Validate Contract Duration
  if (!selectedStaff.value?.contract_duration) {
    formErrors.value.contract_duration = 'Contract duration is required'
    isValid = false
  } else {
    formErrors.value.contract_duration = ''
  }

  // Validate Join Date
  if (!selectedStaff.value?.join_date) {
    formErrors.value.join_date = 'Join date is required'
    isValid = false
  } else {
    formErrors.value.join_date = ''
  }

  return isValid
}

const openAddStaffDialog = () => {
  selectedStaff.value = {
    id: 0,
    name: '',
    father_name: '',
    contact: '',
    education_lvl: '',
    email: '',
    addr: '',
    position: '',
    salary: 0,
    bonus: false,
    ID_file: null,
    assure_file: null,
    join_date: new Date().toISOString().split('T')[0],
    contract_duration: ''
  }
  resetFormErrors()
  isAddStaffDialogVisible.value = true
}

const openEditStaffDialog = (staff: Staff) => {
  selectedStaff.value = {...staff}
  resetFormErrors()
  isEditStaffDialogVisible.value = true
}

const openDeleteStaffDialog = (staff: Staff) => {
  selectedStaff.value = staff
  isDeleteStaffDialogVisible.value = true
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
        position: emp.position?.name || positionOptions.value[emp.position_id - 1] || emp.position_id.toString()
      }))
    } else if (Array.isArray(response.data)) {
      staffList.value = response.data.map(emp => ({
        ...emp,
        email: emp.email ?? '',
        position: emp.position?.name || positionOptions.value[emp.position_id - 1] || emp.position_id.toString()
      }))
    }
  } catch (error) {
    console.error('Error fetching employees:', error)
  } finally {
    isLoading.value = false
  }
}

const addStaff = async () => {
  if (!selectedStaff.value) return
  if (!validateStaffForm()) return

  try {
    isLoading.value = true
    const positionId = positionIdMap.value[selectedStaff.value.position]
    const data: CreateEmployeeData = {
      name: selectedStaff.value.name,
      father_name: selectedStaff.value.father_name,
      contact: selectedStaff.value.contact,
      email: selectedStaff.value.email,
      education_lvl: selectedStaff.value.education_lvl,
      position_id: positionId,
      salary: selectedStaff.value.salary,
      bonus: selectedStaff.value.bonus,
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
        position: response.data.data.position?.name || positionOptions.value[response.data.data.position_id - 1] || response.data.data.position_id.toString()
      })
    }
    isAddStaffDialogVisible.value = false
  } catch (error) {
    console.error('Error creating employee:', error)
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
      name: selectedStaff.value.name,
      father_name: selectedStaff.value.father_name,
      contact: selectedStaff.value.contact,
      email: selectedStaff.value.email,
      education_lvl: selectedStaff.value.education_lvl,
      position_id: positionId,
      salary: selectedStaff.value.salary,
      bonus: selectedStaff.value.bonus,
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
          position: response.data.data.position?.name || positionOptions.value[response.data.data.position_id - 1] || response.data.data.position_id.toString()
        }
      }
    }
    isEditStaffDialogVisible.value = false
  } catch (error) {
    console.error('Error updating employee:', error)
  } finally {
    isLoading.value = false
  }
}

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
      staff.email.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      staff.position.toLowerCase().includes(searchQuery.value.toLowerCase())

    const matchesPosition = !filterPosition.value || staff.position === filterPosition.value
    const matchesBonus = !filterBonus.value ||
      (filterBonus.value === 'Yes' && staff.bonus) ||
      (filterBonus.value === 'No' && !staff.bonus)

    return matchesSearch && matchesPosition && matchesBonus
  })
})

const printTable = () => {
  window.print()
}

onMounted(() => {
  fetchPositions()
  fetchEmployees()
})
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <VRow align="center">
          <VCol cols="12" md="4">
            <VTextField
              v-model="searchQuery"
              prepend-inner-icon="tabler-search"
              placeholder="Search staff..."
              density="compact"
              clearable
              hide-details
            />
          </VCol>

          <VCol cols="12" sm="6" md="2">
            <VSelect
              v-model="filterPosition"
              :items="positionOptions"
              label="Filter by Position"
              density="compact"
              clearable
              hide-details
              :loading="isPositionsLoading"
            />
          </VCol>

          <VCol cols="12" sm="6" md="2">
            <VSelect
              v-model="filterBonus"
              :items="bonusOptions"
              label="Filter by Bonus"
              density="compact"
              clearable
              hide-details
            />
          </VCol>

          <VCol cols="12" md="4" class="d-flex gap-2 justify-end">
            <VBtn
              icon="tabler-printer"
              variant="text"
              @click="printTable"
              title="Print staff records"
            />
            <VBtn
              color="primary"
              @click="openAddStaffDialog"
            >
              <VIcon icon="tabler-plus" class="me-1" />
              Add Staff
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <div class="table-container">
        <VDataTable
          :items="filteredStaff"
          :headers="[
            { title: 'ID', key: 'id' },
            { title: 'Name', key: 'name' },
            { title: 'Father Name', key: 'father_name' },
            { title: 'Contact', key: 'contact' },
            { title: 'Position', key: 'position' },
            { title: 'Salary', key: 'salary' },
            { title: 'Bonus', key: 'bonus' },
            { title: 'Join Date', key: 'join_date' },
            { title: 'Actions', key: 'actions', sortable: false }
          ]"
          item-value="id"
          class="text-no-wrap"
          :loading="isLoading"
          mobile-breakpoint="sm"
          density="compact"
        >
          <template #item.salary="{ item }">
            <span>${{ item.salary.toFixed(2) }}</span>
          </template>

          <template #item.bonus="{ item }">
            <VChip
              :color="item.bonus ? 'success' : 'error'"
              size="small"
            >
              {{ item.bonus ? 'Yes' : 'No' }}
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <VBtn
                icon="tabler-edit"
                variant="text"
                size="small"
                color="medium-emphasis"
                @click="openEditStaffDialog(item)"
                title="Edit staff record"
              />
              <VBtn
                icon="tabler-trash"
                variant="text"
                size="small"
                color="error"
                @click="openDeleteStaffDialog(item)"
                title="Delete staff record"
              />
            </div>
          </template>
        </VDataTable>
      </div>
    </VCard>

    <!-- Add Staff Dialog -->
    <VDialog
      v-model="isAddStaffDialogVisible"
      max-width="800px"
    >
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">Add New Staff</span>
        </VCardTitle>

        <VCardText>
          <VForm>
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.name"
                  label="Name"
                  :error-messages="formErrors.name"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.father_name"
                  label="Father Name"
                  :error-messages="formErrors.father_name"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.contact"
                  label="Contact/Phone"
                  :error-messages="formErrors.contact"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.email"
                  label="Email"
                  type="email"
                  :error-messages="formErrors.email"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStaff.education_lvl"
                  :items="educationOptions"
                  label="Education Level"
                  :error-messages="formErrors.education_lvl"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStaff.position"
                  :items="positionOptions"
                  label="Position"
                  :error-messages="formErrors.position"
                  required
                  :loading="isPositionsLoading"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="selectedStaff.salary"
                  label="Salary"
                  type="number"
                  :error-messages="formErrors.salary"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStaff.contract_duration"
                  :items="contractDurationOptions"
                  label="Contract Duration"
                  :error-messages="formErrors.contract_duration"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VCheckbox
                  v-model="selectedStaff.bonus"
                  label="Bonus Eligible"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.ID_file"
                  label="ID File"
                  placeholder="Upload file name"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.assure_file"
                  label="Assurance File"
                  placeholder="Upload file name"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.addr"
                  label="Address"
                  :error-messages="formErrors.addr"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.join_date"
                  label="Join Date"
                  type="date"
                  :error-messages="formErrors.join_date"
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
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            @click="addStaff"
            :loading="isLoading"
          >
            Save
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit Staff Dialog -->
    <VDialog
      v-model="isEditStaffDialogVisible"
      max-width="800px"
    >
      <VCard>
        <VCardTitle class="pa-5">
          <span class="headline">Edit Staff</span>
        </VCardTitle>

        <VCardText>
          <VForm>
            <VRow>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.name"
                  label="Name"
                  :error-messages="formErrors.name"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.father_name"
                  label="Father Name"
                  :error-messages="formErrors.father_name"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.contact"
                  label="Contact/Phone"
                  :error-messages="formErrors.contact"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.email"
                  label="Email"
                  type="email"
                  :error-messages="formErrors.email"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStaff.education_lvl"
                  :items="educationOptions"
                  label="Education Level"
                  :error-messages="formErrors.education_lvl"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStaff.position"
                  :items="positionOptions"
                  label="Position"
                  :error-messages="formErrors.position"
                  required
                  :loading="isPositionsLoading"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model.number="selectedStaff.salary"
                  label="Salary"
                  type="number"
                  :error-messages="formErrors.salary"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="selectedStaff.contract_duration"
                  :items="contractDurationOptions"
                  label="Contract Duration"
                  :error-messages="formErrors.contract_duration"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VCheckbox
                  v-model="selectedStaff.bonus"
                  label="Bonus Eligible"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.ID_file"
                  label="ID File"
                  placeholder="Upload file name"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.assure_file"
                  label="Assurance File"
                  placeholder="Upload file name"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.addr"
                  label="Address"
                  :error-messages="formErrors.addr"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="selectedStaff.join_date"
                  label="Join Date"
                  type="date"
                  :error-messages="formErrors.join_date"
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
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            @click="updateStaff"
            :loading="isLoading"
          >
            Save
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
          <span class="headline">Confirm Delete</span>
        </VCardTitle>

        <VCardText>
          Are you sure you want to delete this staff record?
          <br>
          This action cannot be undone.
        </VCardText>

        <VCardActions class="pa-5">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isDeleteStaffDialogVisible = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="error"
            @click="deleteStaff"
            :loading="isLoading"
          >
            Delete
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.table-container {
  overflow-x: auto;
}
</style>
