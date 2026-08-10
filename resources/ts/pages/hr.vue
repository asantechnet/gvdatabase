<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue'
import EmployeeApi from '@/api/employee'
import PositionApi from '@/api/position'

// 1. Types & Interfaces
interface Employee {
  id: number
  name: string
  qualification: string
  email: string
  phone: string
  speciality: string
  gender: 'Male' | 'Female' | 'Other'
  position: string
  position_id: number
  workStartTime: string
  workEndTime: string
}

interface Position {
  id: number
  title: string
  salary: number
}

interface FormError {
  field: string
  message: string
}

// 2. Reactive States
const employees = ref<Employee[]>([])
const positions = ref<Position[]>([])
const specialties = ['Frontend', 'Backend', 'UI/UX Design', 'DevOps', 'QA Automation', 'HR & Admin']
const genders = ['Male', 'Female', 'Other']

// Universal Global Queries
const employeeSearch = ref('')
const selectedSpecialityFilter = ref<string | null>(null)
const isLoadingEmployees = ref(false)
const isLoadingPositions = ref(false)

// Dialog Controllers
const isEmployeeDialogActive = ref(false)
const isEditMode = ref(false)

// Form Validation States
const employeeFormErrors = ref<FormError[]>([])

// Forms Model Blueprints
const defaultEmployeeForm: Omit<Employee, 'id'> = {
  name: '',
  qualification: '',
  email: '',
  phone: '',
  speciality: '',
  gender: 'Male',
  position: '',
  position_id: 0,
  workStartTime: '09:00',
  workEndTime: '18:00'
}
const employeeForm = ref({ ...defaultEmployeeForm, id: null as number | null })

// 4. Table Header Mapping Configurations
const employeeHeaders = [
  { title: 'Employee Profile', key: 'name', sortable: true },
  { title: 'Speciality & Position', key: 'speciality', sortable: true },
  { title: 'Contact Info', key: 'email', sortable: false },
  { title: 'Shift Schedule', key: 'workStartTime', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' as const },
]

// 5. Validation Rules
const validateEmployeeForm = () => {
  employeeFormErrors.value = []

  if (!employeeForm.value.name) {
    employeeFormErrors.value.push({ field: 'name', message: 'Name is required' })
  } else if (employeeForm.value.name.length > 255) {
    employeeFormErrors.value.push({ field: 'name', message: 'Name must be less than 255 characters' })
  }

  if (!employeeForm.value.gender) {
    employeeFormErrors.value.push({ field: 'gender', message: 'Gender is required' })
  } else if (employeeForm.value.gender.length > 50) {
    employeeFormErrors.value.push({ field: 'gender', message: 'Gender must be less than 50 characters' })
  }

  if (!employeeForm.value.email) {
    employeeFormErrors.value.push({ field: 'email', message: 'Email is required' })
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(employeeForm.value.email)) {
    employeeFormErrors.value.push({ field: 'email', message: 'Email must be valid' })
  } else if (employeeForm.value.email.length > 255) {
    employeeFormErrors.value.push({ field: 'email', message: 'Email must be less than 255 characters' })
  }

  if (!employeeForm.value.phone) {
    employeeFormErrors.value.push({ field: 'phone', message: 'Phone is required' })
  } else if (employeeForm.value.phone.length > 20) {
    employeeFormErrors.value.push({ field: 'phone', message: 'Phone must be less than 20 characters' })
  }

  if (!employeeForm.value.position_id) {
    employeeFormErrors.value.push({ field: 'position', message: 'Position is required' })
  }

  if (!employeeForm.value.qualification) {
    employeeFormErrors.value.push({ field: 'qualification', message: 'Qualification is required' })
  } else if (employeeForm.value.qualification.length > 255) {
    employeeFormErrors.value.push({ field: 'qualification', message: 'Qualification must be less than 255 characters' })
  }

  return employeeFormErrors.value.length === 0
}

const getErrorMessage = (field: string, formType: 'employee' | 'salary') => {
  const errors = formType === 'employee' ? employeeFormErrors.value : []
  const error = errors.find(e => e.field === field)
  return error ? error.message : ''
}

// 6. API Data Fetching
const fetchEmployees = async () => {
  try {
    isLoadingEmployees.value = true
    const response = await EmployeeApi.getAll({
      search: employeeSearch.value
    })
    employees.value = response.data.data.map((emp: any) => ({
      ...emp,
      qualification: emp.education,
      position: emp.position?.title,
      position_id: emp.position_id,
      speciality: emp.speciality || '',
      workStartTime: emp.work_start_time || '09:00',
      workEndTime: emp.work_end_time || '18:00'
    }))
  } catch (error: any) {
    console.error('Error fetching employees:', error)
    if (error.response?.data?.errors) {
      alert('Error loading employees: ' + JSON.stringify(error.response.data.errors))
    } else {
      alert('Error loading employees. Please try again.')
    }
  } finally {
    isLoadingEmployees.value = false
  }
}

const fetchPositions = async () => {
  try {
    isLoadingPositions.value = true
    const response = await PositionApi.getAll()
    positions.value = response.data.data
  } catch (error: any) {
    console.error('Error fetching positions:', error)
    if (error.response?.data?.errors) {
      alert('Error loading positions: ' + JSON.stringify(error.response.data.errors))
    } else {
      alert('Error loading positions. Please try again.')
    }
  } finally {
    isLoadingPositions.value = false
  }
}

// 7. Dynamic Real-time Computed Filtering Arrays
const filteredEmployees = computed(() => {
  return employees.value.filter(emp => {
    const matchesSearch = emp.name.toLowerCase().includes(employeeSearch.value.toLowerCase()) ||
      emp.speciality.toLowerCase().includes(employeeSearch.value.toLowerCase()) ||
      emp.qualification.toLowerCase().includes(employeeSearch.value.toLowerCase())
    const matchesSpeciality = !selectedSpecialityFilter.value || emp.speciality === selectedSpecialityFilter.value
    return matchesSearch && matchesSpeciality
  })
})

// 8. Core Employee CRUDS
const openCreateEmployee = () => {
  isEditMode.value = false
  employeeForm.value = { ...defaultEmployeeForm, id: null }
  employeeFormErrors.value = []
  isEmployeeDialogActive.value = true
}

const openEditEmployee = (item: Employee) => {
  isEditMode.value = true
  employeeForm.value = {
    ...item,
    qualification: item.qualification,
    position: item.position,
    position_id: item.position_id
  }
  employeeFormErrors.value = []
  isEmployeeDialogActive.value = true
}

const saveEmployee = async () => {
  if (!validateEmployeeForm()) return

  try {
    const employeeData = {
      name: employeeForm.value.name,
      gender: employeeForm.value.gender,
      email: employeeForm.value.email,
      phone: employeeForm.value.phone,
      position_id: employeeForm.value.position_id,
      education: employeeForm.value.qualification,
      work_start_time: employeeForm.value.workStartTime,
      work_end_time: employeeForm.value.workEndTime
    }

    if (isEditMode.value && employeeForm.value.id !== null) {
      await EmployeeApi.update(employeeForm.value.id, employeeData)
    } else {
      await EmployeeApi.create(employeeData)
    }
    await fetchEmployees()
    isEmployeeDialogActive.value = false
  } catch (error: any) {
    console.error('Error saving employee:', error)
    if (error.response?.data?.errors) {
      // Map backend errors to form fields
      Object.entries(error.response.data.errors).forEach(([field, messages]) => {
        const message = Array.isArray(messages) ? messages[0] : messages
        employeeFormErrors.value.push({
          field: field === 'position_id' ? 'position' : field,
          message: message
        })
      })
    } else {
      alert('Error saving employee. Please try again.')
    }
  }
}

const deleteEmployee = async (id: number) => {
  if (confirm('Permanently remove this employee? (Note: Salary history remains unaffected)')) {
    try {
      await EmployeeApi.delete(id)
      await fetchEmployees()
    } catch (error: any) {
      console.error('Error deleting employee:', error)
      if (error.response?.data?.message) {
        alert(error.response.data.message)
      } else {
        alert('Error deleting employee. Please try again.')
      }
    }
  }
}

// 10. Lifecycle Hooks
onMounted(async () => {
  await fetchPositions()
  await fetchEmployees()
})

// Watch for search changes
watch(employeeSearch, () => {
  fetchEmployees()
})
</script>

<template>
  <div class="d-flex flex-column gap-8">

    <div>
      <div class="d-flex align-center justify-between mb-4">
        <h3 class="text-h5 font-weight-medium">Internal Corporate Directory</h3>
      </div>

      <VCard class="mb-4">
        <VCardText>
          <VRow align="center">
            <VCol cols="12" md="5">
              <VTextField v-model="employeeSearch" prepend-inner-icon="tabler-search"
                placeholder="Search staff names, degrees, titles..." density="compact" hide-details clearable />
            </VCol>
            <VCol cols="12" sm="6" md="4">
              <VSelect v-model="selectedSpecialityFilter" :items="specialties" label="Filter Functional Team"
                density="compact" hide-details clearable />
            </VCol>
            <VCol cols="12" sm="6" md="3" class="text-end">
              <VBtn color="primary" class="w-100" @click="openCreateEmployee">
                <VIcon icon="tabler-user-plus" class="me-1" /> Hire Employee
              </VBtn>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <VCard>
        <VDataTable :headers="employeeHeaders" :items="filteredEmployees" :loading="isLoadingEmployees" class="text-no-wrap">
          <template #item.name="{ item }">
            <div class="d-flex flex-column py-2">
              <span class="font-weight-medium text-high-emphasis text-body-1">{{ item.name }}</span>
              <span class="text-xs text-muted font-italic">{{ item.qualification }} ({{ item.gender }})</span>
            </div>
          </template>

          <template #item.speciality="{ item }">
            <div class="d-flex flex-column align-start gap-1">
              <span class="text-body-2 font-weight-semibold">{{ item.position }}</span>
              <VChip size="x-small" variant="tonal" color="info">{{ item.speciality }}</VChip>
            </div>
          </template>

          <template #item.email="{ item }">
            <div class="d-flex flex-column text-body-2">
              <span>{{ item.email }}</span>
              <span class="text-xs text-secondary font-mono">{{ item.phone }}</span>
            </div>
          </template>

          <template #item.workStartTime="{ item }">
            <VChip size="small" variant="outlined" color="secondary" class="font-mono">
              <VIcon icon="tabler-clock" size="14" class="me-1" />
              {{ item.workStartTime }} - {{ item.workEndTime }}
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex justify-end gap-1">
              <VBtn icon="tabler-edit" variant="text" size="small" color="medium-emphasis"
                @click="openEditEmployee(item)" />
              <VBtn icon="tabler-trash" variant="text" size="small" color="error" @click="deleteEmployee(item.id)" />
            </div>
          </template>
        </VDataTable>
      </VCard>
    </div>

    <VDialog v-model="isEmployeeDialogActive" max-width="700">
      <VCard :title="isEditMode ? 'Modify Personnel Parameters' : 'Onboard New Profile Asset'">
        <VCardText>
          <VRow>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="employeeForm.name"
                label="Legal Full Name"
                :error="!!getErrorMessage('name', 'employee')"
                :error-messages="getErrorMessage('name', 'employee')"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VSelect
                v-model="employeeForm.gender"
                :items="genders"
                label="Gender Identity"
                :error="!!getErrorMessage('gender', 'employee')"
                :error-messages="getErrorMessage('gender', 'employee')"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="employeeForm.email"
                label="Corporate Email Connection"
                :error="!!getErrorMessage('email', 'employee')"
                :error-messages="getErrorMessage('email', 'employee')"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="employeeForm.phone"
                label="Direct Phone Line"
                :error="!!getErrorMessage('phone', 'employee')"
                :error-messages="getErrorMessage('phone', 'employee')"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VSelect
                v-model="employeeForm.speciality"
                :items="specialties"
                label="Team Domain Specialization"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VSelect
                v-model="employeeForm.position_id"
                :items="positions"
                :item-title="'title'"
                :item-value="'id'"
                label="Organizational Position"
                :error="!!getErrorMessage('position', 'employee')"
                :error-messages="getErrorMessage('position', 'employee')"
                :loading="isLoadingPositions"
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model="employeeForm.qualification"
                label="Academic Degrees & Core Accreditations"
                :error="!!getErrorMessage('qualification', 'employee')"
                :error-messages="getErrorMessage('qualification', 'employee')"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="employeeForm.workStartTime"
                type="time"
                label="Shift Entry Timestamp"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="employeeForm.workEndTime"
                type="time"
                label="Shift Exit Clockout"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardText class="d-flex justify-end gap-3 mt-2">
          <VBtn color="secondary" variant="tonal" @click="isEmployeeDialogActive = false">Cancel</VBtn>
          <VBtn color="primary" @click="saveEmployee">Save Configuration</VBtn>
        </VCardText>
      </VCard>
    </VDialog>

  </div>
</template>

<style scoped>
.max-w-300 {
  max-width: 300px;
}
</style>
