<script setup lang="ts">
import PrintableListReport from '@/components/PrintableListReport.vue'
import { ref, computed, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import VisitorApi, { type Visitor, type CreateVisitorData, type UpdateVisitorData } from '@/api/visitor'
import MarketerApi, { type Marketer } from '@/api/marketer'
import { printDocument } from '@/utils/printDocument'
import ContactActionMenu from '@/components/ContactActionMenu.vue'
import LeadApi from '@/api/lead'

const visitors = ref<Visitor[]>([])
const search = ref('')
const filterCompany = ref<string | null>(null)
const filterMarketer = ref<string | null>(null)
const isAddNewVisitorDialogVisible = ref(false)
const isEditVisitorDialogVisible = ref(false)
const isDeleteVisitorDialogVisible = ref(false)
const selectedVisitor = ref<Visitor | null>(null)
const isLoading = ref(false)
const convertingVisitorId = ref<number | null>(null)
const marketerDirectory = ref<Marketer[]>([])
const toast = useToast()

const visitorData = ref<Omit<Visitor, 'id' | 'date'>>({
  name: '',
  company: '',
  contact: '',
  email: '',
  service: '',
  marketer: '',
  notes: '',
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
    email: '',
    service: '',
    marketer: '',
    notes: '',
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
      || (v.email?.toLowerCase().includes(search.value.toLowerCase()) ?? false)
    const matchesCompany = !filterCompany.value || v.company === filterCompany.value
    const matchesMarketer = !filterMarketer.value || v.marketer === filterMarketer.value
    return matchesSearch && matchesCompany && matchesMarketer
  })
})

const headers = [
  { title: translate('common.fields.name'), key: 'name', sortable: true },
  { title: translate('common.fields.company'), key: 'company', sortable: true },
  { title: translate('common.messages.contact'), key: 'contact', sortable: true },
  { title: translate('common.fields.email'), key: 'email', sortable: true },
  { title: translate('common.messages.service'), key: 'service', sortable: true },
  { title: translate('common.messages.marketer'), key: 'marketer', sortable: true },
  { title: translate('common.fields.date'), key: 'date', sortable: true },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false },
]

const printTable = () => {
  void printDocument({ bodyClass: 'printing-visitor-list', pageStyle: 'size: A4 landscape; margin: 12mm;' })
}

const addNewVisitor = async () => {
  if (!validateForm()) return

  try {
    isLoading.value = true
    const data: CreateVisitorData = {
      name: visitorData.value.name,
      company: visitorData.value.company,
      contact: visitorData.value.contact,
      email: visitorData.value.email || null,
      service: visitorData.value.service,
      marketer: visitorData.value.marketer,
      notes: visitorData.value.notes,
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
      email: visitorData.value.email || null,
      service: visitorData.value.service,
      marketer: visitorData.value.marketer,
      notes: visitorData.value.notes,
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
    email: visitor.email ?? '',
    service: visitor.service,
    marketer: visitor.marketer,
    notes: visitor.notes || '',
  }
  isEditVisitorDialogVisible.value = true
}

const openDeleteDialog = (visitor: Visitor) => {
  selectedVisitor.value = visitor
  isDeleteVisitorDialogVisible.value = true
}

const convertToLead = async (visitor: Visitor) => {
  try {
    convertingVisitorId.value = visitor.id
    await LeadApi.fromVisitor(visitor.id)
    visitors.value = visitors.value.filter(item => item.id !== visitor.id)
    toast.success('Visitor qualified as a lead')
  } catch {
    toast.error('Unable to convert visitor to lead')
  } finally {
    convertingVisitorId.value = null
  }
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
  <DataTablePageKpis :items="filtered" title="Visitors" description="Review visitor activity and maintain a clear record of every entry." icon="tabler-door-enter" />
  <VCard>
    <VCardText>
      <VRow align="center" class="table-toolbar">
        <VCol cols="12" md="7">
          <VTextField
            v-model="search"
            class="data-table-search" prepend-inner-icon="tabler-search"
            :placeholder="$t('pages.contracts.visitor.visitor.searchVisitors')"
            density="compact"
            clearable
            hide-details
            @input="fetchVisitors"
          />
        </VCol>
        <VCol cols="12" md="5" class="table-toolbar-actions d-flex flex-nowrap justify-end gap-2">
          <DataTableFilterMenu :active="Boolean(filterCompany || filterMarketer)"
            @reset="filterCompany = null; filterMarketer = null; fetchVisitors()">
            <VSelect v-model="filterCompany" :items="companies" :label="$t('pages.contracts.visitor.visitor.filterByCompany')" density="compact" clearable hide-details @update:modelValue="fetchVisitors" />
            <VSelect v-model="filterMarketer" :items="marketers" :label="$t('pages.contracts.visitor.visitor.filterByMarketer')" density="compact" clearable hide-details @update:modelValue="fetchVisitors" />
          </DataTableFilterMenu>
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

    <ResponsiveDataTable
      :headers="headers"
      :items="filtered"
      item-value="id"
      class="text-no-wrap"
      :loading="isLoading"
      mobile-breakpoint="sm"
      style="overflow-x: auto;"
    >
      <template #item.contact="{ item }">
        <ContactActionMenu kind="phone" :value="item.contact" contactable-type="visitor" :contactable-id="item.id" />
      </template>

      <template #item.email="{ item }">
        <ContactActionMenu v-if="item.email" kind="email" :value="item.email" contactable-type="visitor" :contactable-id="item.id" />
        <span v-else class="text-medium-emphasis">—</span>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <CrmRelationshipDetails type="visitor" :id="item.id" :title="item.name" />
          <VBtn icon="tabler-filter-plus" variant="text" size="small" color="success" @click="convertToLead(item)" :loading="convertingVisitorId === item.id" :disabled="convertingVisitorId !== null"><VIcon icon="tabler-filter-plus" /><VTooltip activator="parent" location="top">Qualify as lead</VTooltip></VBtn>
          <VBtn
            icon="tabler-edit"
            variant="text"
            size="small"
            color="medium-emphasis"
            @click="openEditDialog(item)"
            :loading="isLoading"
          ><VIcon icon="tabler-edit" /><VTooltip activator="parent" location="top">{{ $t('pages.contracts.visitor.visitor.editVisitor') }}</VTooltip></VBtn>
          <VBtn
            icon="tabler-trash"
            variant="text"
            size="small"
            color="error"
            @click="openDeleteDialog(item)"
            :loading="isLoading"
          ><VIcon icon="tabler-trash" /><VTooltip activator="parent" location="top">{{ $t('pages.contracts.visitor.visitor.deleteVisitor') }}</VTooltip></VBtn>
        </div>
      </template>
    </ResponsiveDataTable>
  </VCard>
  <PrintableListReport title="Visitors Report" subtitle="Filtered visitor records" :columns="headers.slice(0, -1).map(header => header.title)" :rows="filtered.map(visitor => [visitor.name, visitor.company, visitor.contact, visitor.email || '—', visitor.service, visitor.marketer, visitor.date])" />

  <!-- Add New Visitor Dialog -->
  <VDialog v-model="isAddNewVisitorDialogVisible" max-width="600">
    <VCard>
      <VCardTitle class="pa-4">
        <span class="text-h5">{{ $t('pages.contracts.visitor.visitor.addNewVisitor') }}</span>
      </VCardTitle>

      <VCardText>
        <VForm class="app-form" @submit.prevent="addNewVisitor">
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
            <VCol cols="12" md="6"><VTextField v-model="visitorData.email" :label="$t('common.fields.email')" type="email" prepend-inner-icon="tabler-mail" /></VCol>
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
            <VCol cols="12"><VTextarea v-model="visitorData.notes" label="Visitor notes" rows="3" hint="Capture interests, context, and anything useful before qualification." persistent-hint /></VCol>
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
        <VForm class="app-form" @submit.prevent="editVisitor">
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
            <VCol cols="12" md="6"><VTextField v-model="visitorData.email" :label="$t('common.fields.email')" type="email" prepend-inner-icon="tabler-mail" /></VCol>
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
            <VCol cols="12"><VTextarea v-model="visitorData.notes" label="Visitor notes" rows="3" hint="Capture interests, context, and anything useful before qualification." persistent-hint /></VCol>
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

<style>
@media print {
  body.printing-visitor-list .v-card,
  body.printing-visitor-list .v-card > .v-card-text,
  body.printing-visitor-list .v-data-table__td:last-child,
  body.printing-visitor-list .v-data-table__th:last-child,
  body.printing-visitor-list .v-data-table-footer,
  body.printing-visitor-list .layout-navbar,
  body.printing-visitor-list .layout-menu,
  body.printing-visitor-list .layout-footer { display: none !important; }
  body.printing-visitor-list .v-main,
  body.printing-visitor-list .layout-page-content { padding: 0 !important; margin: 0 !important; }
}
@media (max-width: 1279px) { .table-toolbar-actions { justify-content: flex-start; } }
</style>
