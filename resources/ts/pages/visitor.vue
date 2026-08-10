<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import VisitorApi, { type Visitor, type CreateVisitorData, type UpdateVisitorData } from '@/api/visitor'

const visitors = ref<Visitor[]>([])
const search = ref('')
const filterCompany = ref<string | null>(null)
const filterMarketer = ref<string | null>(null)
const isAddNewVisitorDialogVisible = ref(false)
const isEditVisitorDialogVisible = ref(false)
const isDeleteVisitorDialogVisible = ref(false)
const selectedVisitor = ref<Visitor | null>(null)
const isLoading = ref(false)

const visitorData = ref<Omit<Visitor, 'id' | 'date'>>({
  name: '',
  company: '',
  contact: '',
  service: '',
  marketer: '',
})

const formErrors = ref({
  name: '',
  company: '',
  contact: '',
  service: '',
  marketer: '',
})

const resetForm = () => {
  visitorData.value = {
    name: '',
    company: '',
    contact: '',
    service: '',
    marketer: '',
  }
  formErrors.value = {
    name: '',
    company: '',
    contact: '',
    service: '',
    marketer: '',
  }
}

const validateForm = () => {
  let isValid = true

  // Validate Visitor Name
  if (!visitorData.value.name.trim()) {
    formErrors.value.name = 'Visitor name is required'
    isValid = false
  } else if (visitorData.value.name.length < 2) {
    formErrors.value.name = 'Visitor name must be at least 2 characters'
    isValid = false
  } else {
    formErrors.value.name = ''
  }

  // Validate Company
  if (!visitorData.value.company.trim()) {
    formErrors.value.company = 'Company name is required'
    isValid = false
  } else if (visitorData.value.company.length < 2) {
    formErrors.value.company = 'Company name must be at least 2 characters'
    isValid = false
  } else {
    formErrors.value.company = ''
  }

  // Validate Contact/Phone
  if (!visitorData.value.contact.trim()) {
    formErrors.value.contact = 'Contact number is required'
    isValid = false
  } else if (!/^[\d\s\-()+]{8,}$/.test(visitorData.value.contact)) {
    formErrors.value.contact = 'Please enter a valid phone number'
    isValid = false
  } else {
    formErrors.value.contact = ''
  }

  // Validate Service
  if (!visitorData.value.service.trim()) {
    formErrors.value.service = 'Service is required'
    isValid = false
  } else if (visitorData.value.service.length < 2) {
    formErrors.value.service = 'Service must be at least 2 characters'
    isValid = false
  } else {
    formErrors.value.service = ''
  }

  // Validate Marketer
  if (!visitorData.value.marketer.trim()) {
    formErrors.value.marketer = 'Marketer is required'
    isValid = false
  } else if (visitorData.value.marketer.length < 2) {
    formErrors.value.marketer = 'Marketer must be at least 2 characters'
    isValid = false
  } else {
    formErrors.value.marketer = ''
  }

  return isValid
}

const fetchVisitors = async () => {
  try {
    isLoading.value = true
    const response = await VisitorApi.getAll({
      search: search.value,
      company: filterCompany.value || undefined,
      marketer: filterMarketer.value || undefined
    })

    // Handle the response data properly
    if (response && Array.isArray(response)) {
      visitors.value = response
    } else if (response && response.data && Array.isArray(response.data)) {
      visitors.value = response.data
    } else {
      visitors.value = []
    }
  } catch (error) {
    console.error('Error fetching visitors:', error)
    visitors.value = []
  } finally {
    isLoading.value = false
  }
}

const filtered = computed(() => {
  return visitors.value.filter(v => {
    const matchesSearch = v.name.toLowerCase().includes(search.value.toLowerCase()) ||
      v.company.toLowerCase().includes(search.value.toLowerCase()) ||
      v.contact.includes(search.value)
    const matchesCompany = !filterCompany.value || v.company === filterCompany.value
    const matchesMarketer = !filterMarketer.value || v.marketer === filterMarketer.value
    return matchesSearch && matchesCompany && matchesMarketer
  })
})

const headers = [
  { title: 'Name', key: 'name', sortable: true },
  { title: 'Company', key: 'company', sortable: true },
  { title: 'Contact', key: 'contact', sortable: true },
  { title: 'Service', key: 'service', sortable: true },
  { title: 'Marketer', key: 'marketer', sortable: true },
  { title: 'Date', key: 'date', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false },
]

const printTable = () => {
  window.print()
}

const addNewVisitor = async () => {
  if (!validateForm()) return

  try {
    isLoading.value = true
    const data: CreateVisitorData = {
      name: visitorData.value.name,
      company: visitorData.value.company,
      contact: visitorData.value.contact,
      service: visitorData.value.service,
      marketer: visitorData.value.marketer,
      date: new Date().toISOString().split('T')[0]
    }

    const response = await VisitorApi.create(data)
    if (response) {
      visitors.value.push(response)
    }
    isAddNewVisitorDialogVisible.value = false
    resetForm()
  } catch (error) {
    console.error('Error creating visitor:', error)
  } finally {
    isLoading.value = false
  }
}

const editVisitor = async () => {
  if (!selectedVisitor.value) return
  if (!validateForm()) return

  try {
    isLoading.value = true
    const data: UpdateVisitorData = {
      name: visitorData.value.name,
      company: visitorData.value.company,
      contact: visitorData.value.contact,
      service: visitorData.value.service,
      marketer: visitorData.value.marketer,
    }

    const response = await VisitorApi.update(selectedVisitor.value.id, data)
    if (response) {
      const index = visitors.value.findIndex(v => v.id === selectedVisitor.value?.id)
      if (index !== -1) {
        visitors.value[index] = response
      }
    }
    isEditVisitorDialogVisible.value = false
    resetForm()
  } catch (error) {
    console.error('Error updating visitor:', error)
  } finally {
    isLoading.value = false
  }
}

const openEditDialog = (visitor: Visitor) => {
  selectedVisitor.value = visitor
  visitorData.value = {
    name: visitor.name,
    company: visitor.company,
    contact: visitor.contact,
    service: visitor.service,
    marketer: visitor.marketer,
  }
  isEditVisitorDialogVisible.value = true
}

const openDeleteDialog = (visitor: Visitor) => {
  selectedVisitor.value = visitor
  isDeleteVisitorDialogVisible.value = true
}

const deleteVisitor = async () => {
  if (!selectedVisitor.value) return

  try {
    isLoading.value = true
    await VisitorApi.delete(selectedVisitor.value.id)
    visitors.value = visitors.value.filter(v => v.id !== selectedVisitor.value?.id)
    isDeleteVisitorDialogVisible.value = false
  } catch (error) {
    console.error('Error deleting visitor:', error)
  } finally {
    isLoading.value = false
  }
}

// Extract unique values for filters
const companies = computed(() => {
  return [...new Set(visitors.value.map(v => v.company))].filter(Boolean)
})

const marketers = computed(() => {
  return [...new Set(visitors.value.map(v => v.marketer))].filter(Boolean)
})

const services = computed(() => {
  return [...new Set(visitors.value.map(v => v.service))].filter(Boolean)
})

onMounted(() => {
  fetchVisitors()
})
</script>

<template>
  <VCard>
    <VCardText>
      <VRow align="center">
        <VCol cols="12" md="4">
          <VTextField
            v-model="search"
            prepend-inner-icon="tabler-search"
            placeholder="Search visitors..."
            density="compact"
            clearable
            hide-details
            @input="fetchVisitors"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect
            v-model="filterCompany"
            :items="companies"
            label="Filter by Company"
            density="compact"
            clearable
            hide-details
            @update:modelValue="fetchVisitors"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect
            v-model="filterMarketer"
            :items="marketers"
            label="Filter by Marketer"
            density="compact"
            clearable
            hide-details
            @update:modelValue="fetchVisitors"
          />
        </VCol>
        <VCol cols="12" md="2" class="text-end d-flex gap-2 justify-end">
          <VBtn
            icon="tabler-printer"
            variant="text"
            @click="printTable"
            :loading="isLoading"
          />
          <VBtn
            color="primary"
            @click="isAddNewVisitorDialogVisible = true"
            :loading="isLoading"
          >
            <VIcon icon="tabler-plus" class="me-1" /> Add Visitor
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>

    <VDataTable
      :headers="headers"
      :items="filtered"
      item-value="id"
      class="text-no-wrap"
      :loading="isLoading"
      mobile-breakpoint="sm"
      style="overflow-x: auto;"
    >
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <VBtn
            icon="tabler-edit"
            variant="text"
            size="small"
            color="medium-emphasis"
            @click="openEditDialog(item)"
            title="Edit visitor"
            :loading="isLoading"
          />
          <VBtn
            icon="tabler-trash"
            variant="text"
            size="small"
            color="error"
            @click="openDeleteDialog(item)"
            title="Delete visitor"
            :loading="isLoading"
          />
        </div>
      </template>
    </VDataTable>
  </VCard>

  <!-- Add New Visitor Dialog -->
  <VDialog v-model="isAddNewVisitorDialogVisible" max-width="600">
    <VCard>
      <VCardTitle class="pa-4">
        <span class="text-h5">Add New Visitor</span>
      </VCardTitle>

      <VCardText>
        <VForm @submit.prevent="addNewVisitor">
          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                v-model="visitorData.name"
                label="Visitor Name"
                placeholder="Enter visitor name"
                hint="Enter the full name of the visitor"
                persistent-hint
                :error-messages="formErrors.name"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="visitorData.company"
                label="Company"
                placeholder="Enter company name"
                hint="Enter the company the visitor represents"
                persistent-hint
                :error-messages="formErrors.company"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="visitorData.contact"
                label="Contact/Phone"
                placeholder="Enter phone number"
                hint="Enter the visitor's contact number"
                persistent-hint
                :error-messages="formErrors.contact"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="visitorData.service"
                label="Service"
                placeholder="Enter service"
                hint="Enter the service the visitor is interested in"
                persistent-hint
                :error-messages="formErrors.service"
                required
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model="visitorData.marketer"
                label="Marketer"
                placeholder="Enter marketer name"
                hint="Enter the marketer who will handle this visitor"
                persistent-hint
                :error-messages="formErrors.marketer"
                required
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          variant="outlined"
          color="secondary"
          @click="isAddNewVisitorDialogVisible = false; resetForm()"
          :disabled="isLoading"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          @click="addNewVisitor"
          :loading="isLoading"
        >
          Add
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Edit Visitor Dialog -->
  <VDialog v-model="isEditVisitorDialogVisible" max-width="600">
    <VCard>
      <VCardTitle class="pa-4">
        <span class="text-h5">Edit Visitor</span>
      </VCardTitle>

      <VCardText>
        <VForm @submit.prevent="editVisitor">
          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                v-model="visitorData.name"
                label="Visitor Name"
                placeholder="Enter visitor name"
                hint="Enter the full name of the visitor"
                persistent-hint
                :error-messages="formErrors.name"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="visitorData.company"
                label="Company"
                placeholder="Enter company name"
                hint="Enter the company the visitor represents"
                persistent-hint
                :error-messages="formErrors.company"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="visitorData.contact"
                label="Contact/Phone"
                placeholder="Enter phone number"
                hint="Enter the visitor's contact number"
                persistent-hint
                :error-messages="formErrors.contact"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="visitorData.service"
                label="Service"
                placeholder="Enter service"
                hint="Enter the service the visitor is interested in"
                persistent-hint
                :error-messages="formErrors.service"
                required
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model="visitorData.marketer"
                label="Marketer"
                placeholder="Enter marketer name"
                hint="Enter the marketer who will handle this visitor"
                persistent-hint
                :error-messages="formErrors.marketer"
                required
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          variant="outlined"
          color="secondary"
          @click="isEditVisitorDialogVisible = false; resetForm()"
          :disabled="isLoading"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          @click="editVisitor"
          :loading="isLoading"
        >
          Save
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Delete Visitor Dialog -->
  <VDialog v-model="isDeleteVisitorDialogVisible" max-width="500">
    <VCard>
      <VCardTitle class="text-h5 pa-4">
        Are you sure you want to delete this visitor?
      </VCardTitle>

      <VCardText>
        This action cannot be undone. The visitor record will be permanently removed from the system.
      </VCardText>

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          variant="outlined"
          color="secondary"
          @click="isDeleteVisitorDialogVisible = false"
          :disabled="isLoading"
        >
          Cancel
        </VBtn>
        <VBtn
          color="error"
          @click="deleteVisitor"
          :loading="isLoading"
        >
          Delete
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
