<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import VisitorApi, { type Visitor, type CreateVisitorData, type UpdateVisitorData } from '@/api/visitor'
import MarketerApi, { type Marketer } from '@/api/marketer'

const visitors = ref<Visitor[]>([])
const search = ref('')
const filterCompany = ref<string | null>(null)
const filterMarketer = ref<string | null>(null)
const isAddNewVisitorDialogVisible = ref(false)
const isEditVisitorDialogVisible = ref(false)
const isDeleteVisitorDialogVisible = ref(false)
const selectedVisitor = ref<Visitor | null>(null)
const isLoading = ref(false)
const marketerDirectory = ref<Marketer[]>([])

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
    formErrors.value.name = translate('pages.contracts.visitor.visitor.visitorNameIsRequired')
    isValid = false
  } else if (visitorData.value.name.length < 2) {
    formErrors.value.name = translate('pages.contracts.visitor.visitor.visitorNameMustBeAtLeast2Characters')
    isValid = false
  } else {
    formErrors.value.name = ''
  }

  // Validate Company
  if (!visitorData.value.company.trim()) {
    formErrors.value.company = translate('pages.contracts.visitor.visitor.companyNameIsRequired')
    isValid = false
  } else if (visitorData.value.company.length < 2) {
    formErrors.value.company = translate('pages.contracts.visitor.visitor.companyNameMustBeAtLeast2Characters')
    isValid = false
  } else {
    formErrors.value.company = ''
  }

  // Validate Contact/Phone
  if (!visitorData.value.contact.trim()) {
    formErrors.value.contact = translate('common.messages.contactNumberIsRequired')
    isValid = false
  } else if (!/^[\d\s\-()+]{8,}$/.test(visitorData.value.contact)) {
    formErrors.value.contact = translate('common.messages.pleaseEnterAValidPhoneNumber')
    isValid = false
  } else {
    formErrors.value.contact = ''
  }

  // Validate Service
  if (!visitorData.value.service.trim()) {
    formErrors.value.service = translate('pages.contracts.visitor.visitor.serviceIsRequired')
    isValid = false
  } else if (visitorData.value.service.length < 2) {
    formErrors.value.service = translate('pages.contracts.visitor.visitor.serviceMustBeAtLeast2Characters')
    isValid = false
  } else {
    formErrors.value.service = ''
  }

  // Validate Marketer
  if (!visitorData.value.marketer.trim()) {
    formErrors.value.marketer = translate('pages.contracts.visitor.visitor.marketerIsRequired')
    isValid = false
  } else if (visitorData.value.marketer.length < 2) {
    formErrors.value.marketer = translate('pages.contracts.visitor.visitor.marketerMustBeAtLeast2Characters')
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

    visitors.value = response
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
  { title: translate('common.fields.name'), key: 'name', sortable: true },
  { title: translate('common.fields.company'), key: 'company', sortable: true },
  { title: translate('common.messages.contact'), key: 'contact', sortable: true },
  { title: translate('common.messages.service'), key: 'service', sortable: true },
  { title: translate('common.messages.marketer'), key: 'marketer', sortable: true },
  { title: translate('common.fields.date'), key: 'date', sortable: true },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false },
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

const marketers = computed(() => [...new Set([
  ...marketerDirectory.value.filter(marketer => marketer.status === 'active').map(marketer => marketer.name),
  ...visitors.value.map(visitor => visitor.marketer),
])].filter(Boolean).sort())

const services = computed(() => {
  return [...new Set(visitors.value.map(v => v.service))].filter(Boolean)
})

onMounted(() => {
  fetchVisitors()
  MarketerApi.getAll().then(data => { marketerDirectory.value = data }).catch(console.error)
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
            :placeholder="$t('pages.contracts.visitor.visitor.searchVisitors')"
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
            :label="$t('pages.contracts.visitor.visitor.filterByCompany')"
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
            :label="$t('pages.contracts.visitor.visitor.filterByMarketer')"
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
            <VIcon icon="tabler-plus" class="me-1" /> {{ $t('pages.contracts.visitor.visitor.addVisitor') }}
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
            :title="$t('pages.contracts.visitor.visitor.editVisitor')"
            :loading="isLoading"
          />
          <VBtn
            icon="tabler-trash"
            variant="text"
            size="small"
            color="error"
            @click="openDeleteDialog(item)"
            :title="$t('pages.contracts.visitor.visitor.deleteVisitor')"
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
        <span class="text-h5">{{ $t('pages.contracts.visitor.visitor.addNewVisitor') }}</span>
      </VCardTitle>

      <VCardText>
        <VForm @submit.prevent="addNewVisitor">
          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                v-model="visitorData.name"
                :label="$t('common.messages.visitorName')"
                :placeholder="$t('common.messages.enterVisitorName')"
                :hint="$t('common.messages.enterTheFullNameOfTheVisitor')"
                persistent-hint
                :error-messages="formErrors.name"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="visitorData.company"
                :label="$t('common.fields.company')"
                :placeholder="$t('common.messages.enterCompanyName')"
                :hint="$t('common.messages.enterTheCompanyTheVisitorRepresents')"
                persistent-hint
                :error-messages="formErrors.company"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="visitorData.contact"
                :label="$t('common.messages.contactPhone')"
                :placeholder="$t('common.messages.enterPhoneNumber')"
                :hint="$t('common.messages.enterTheVisitorSContactNumber')"
                persistent-hint
                :error-messages="formErrors.contact"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="visitorData.service"
                :label="$t('common.messages.service')"
                :placeholder="$t('common.messages.enterService')"
                :hint="$t('common.messages.enterTheServiceTheVisitorIsInterestedIn')"
                persistent-hint
                :error-messages="formErrors.service"
                required
              />
            </VCol>
            <VCol cols="12">
              <VAutocomplete
                v-model="visitorData.marketer"
                :items="marketers"
                :label="$t('common.messages.marketer')"
                :placeholder="$t('common.messages.selectAMarketer')"
                :hint="$t('common.messages.manageMarketersFromSetupMarketers')"
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
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          @click="addNewVisitor"
          :loading="isLoading"
        >
          {{ $t('common.actions.add') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Edit Visitor Dialog -->
  <VDialog v-model="isEditVisitorDialogVisible" max-width="600">
    <VCard>
      <VCardTitle class="pa-4">
        <span class="text-h5">{{ $t('pages.contracts.visitor.visitor.editVisitor') }}</span>
      </VCardTitle>

      <VCardText>
        <VForm @submit.prevent="editVisitor">
          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                v-model="visitorData.name"
                :label="$t('common.messages.visitorName')"
                :placeholder="$t('common.messages.enterVisitorName')"
                :hint="$t('common.messages.enterTheFullNameOfTheVisitor')"
                persistent-hint
                :error-messages="formErrors.name"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="visitorData.company"
                :label="$t('common.fields.company')"
                :placeholder="$t('common.messages.enterCompanyName')"
                :hint="$t('common.messages.enterTheCompanyTheVisitorRepresents')"
                persistent-hint
                :error-messages="formErrors.company"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="visitorData.contact"
                :label="$t('common.messages.contactPhone')"
                :placeholder="$t('common.messages.enterPhoneNumber')"
                :hint="$t('common.messages.enterTheVisitorSContactNumber')"
                persistent-hint
                :error-messages="formErrors.contact"
                required
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="visitorData.service"
                :label="$t('common.messages.service')"
                :placeholder="$t('common.messages.enterService')"
                :hint="$t('common.messages.enterTheServiceTheVisitorIsInterestedIn')"
                persistent-hint
                :error-messages="formErrors.service"
                required
              />
            </VCol>
            <VCol cols="12">
              <VAutocomplete
                v-model="visitorData.marketer"
                :items="marketers"
                :label="$t('common.messages.marketer')"
                :placeholder="$t('common.messages.selectAMarketer')"
                :hint="$t('common.messages.manageMarketersFromSetupMarketers')"
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
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          @click="editVisitor"
          :loading="isLoading"
        >
          {{ $t('common.actions.save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Delete Visitor Dialog -->
  <VDialog v-model="isDeleteVisitorDialogVisible" max-width="500">
    <VCard>
      <VCardTitle class="text-h5 pa-4">
        {{ $t('common.confirmations.areYouSureYouWantToDeleteThisVisitor') }}
      </VCardTitle>

      <VCardText>
        {{ $t('common.confirmations.thisActionCannotBeUndoneTheVisitorRecordWillBePermanentlyRemovedFromTh') }}
      </VCardText>

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          variant="outlined"
          color="secondary"
          @click="isDeleteVisitorDialogVisible = false"
          :disabled="isLoading"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          color="error"
          @click="deleteVisitor"
          :loading="isLoading"
        >
          {{ $t('common.actions.delete') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
