<script setup lang="ts">
import { translate } from '@/utils/localization'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import ErpField from '@/components/erp/ErpField.vue'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAbility } from '@casl/vue'
import { useToast } from 'vue-toastification'
import PrintableListReport from '@/components/PrintableListReport.vue'
import VisitorApi, { type CreateVisitorData, type UpdateVisitorData, type Visitor } from '@/api/visitor'
import EmployeeApi, { type Employee } from '@/api/employee'
import { printDocument } from '@/utils/printDocument'
import { apiErrorMessage } from '@/utils/apiErrors'
import ContactActionMenu from '@/components/ContactActionMenu.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'
import { useCookie } from '@core/composable/useCookie'

interface VisitorForm {
  name: string
  contact: string
  email: string
  service: string
  counselor_employee_id: number | null
  notes: string
}

const visitors = ref<Visitor[]>([])
const search = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const route = useRoute()
// This page hosts two lists. The Visitors list shows the records that are not
// marked worthy of follow-up; the Follow-up list shows the marked ones. The
// shared CRM strip selects the mode with `?list=follow-up`, so both lists stay
// deep-linkable and print their own report title.
const isFollowUpList = computed(() => route.query.list === 'follow-up')
const filterCounselorId = ref<number | null>(/^\d+$/.test(String(route.query.counselor_employee_id ?? '')) ? Number(route.query.counselor_employee_id) || null : null)
const isAddNewVisitorDialogVisible = ref(false)
const isEditVisitorDialogVisible = ref(false)
const isDeleteVisitorDialogVisible = ref(false)
const selectedVisitor = ref<Visitor | null>(null)
const isTableLoading = ref(false)
const isSaving = ref(false)
const counselorDirectory = ref<Employee[]>([])
const toast = useToast()
const ability = useAbility()

// A counselor creating a visitor owns that visitor: the counselor field is
// pre-filled with the signed-in employee and locked so the case cannot be
// attributed to somebody else from the form.
const currentUser = useCookie<{ roles?: string[]; employee?: { id?: number } | null } | null>('userData')
const isCounselorUser = computed(() => (currentUser.value?.roles ?? [])
  .some(role => role === 'counselor' || role === 'senior_counselor'))
const lockedCounselorId = computed(() => (isCounselorUser.value ? currentUser.value?.employee?.id ?? null : null))
const counselorLocked = computed(() => lockedCounselorId.value !== null)

const emptyVisitorForm = (): VisitorForm => ({
  name: '',
  contact: '',
  email: '',
  service: '',
  counselor_employee_id: lockedCounselorId.value,
  notes: '',
})

const visitorData = ref<VisitorForm>(emptyVisitorForm())

const formErrors = ref({
  name: '',
  contact: '',
  service: '',
})

const resetForm = () => {
  visitorData.value = emptyVisitorForm()
  formErrors.value = {
    name: '',
    contact: '',
    service: '',
  }
}

const openAddDialog = () => {
  resetForm()
  isAddNewVisitorDialogVisible.value = true
}

const validateForm = () => {
  let isValid = true

  // Validate Visitor Name
  if (!visitorData.value.name.trim()) {
    formErrors.value.name = translate('pages.contracts.visitor.visitor.visitorNameIsRequired')
    isValid = false
  }
  else if (visitorData.value.name.length < 2) {
    formErrors.value.name = translate('pages.contracts.visitor.visitor.visitorNameMustBeAtLeast2Characters')
    isValid = false
  }
  else {
    formErrors.value.name = ''
  }

  // Validate Contact/Phone
  if (!visitorData.value.contact.trim()) {
    formErrors.value.contact = translate('common.messages.contactNumberIsRequired')
    isValid = false
  }
  else if (!/^[\d\s\-()+]{8,}$/.test(visitorData.value.contact)) {
    formErrors.value.contact = translate('common.messages.pleaseEnterAValidPhoneNumber')
    isValid = false
  }
  else {
    formErrors.value.contact = ''
  }

  // Validate Service
  if (!visitorData.value.service.trim()) {
    formErrors.value.service = translate('pages.contracts.visitor.visitor.serviceIsRequired')
    isValid = false
  }
  else if (visitorData.value.service.length < 2) {
    formErrors.value.service = translate('pages.contracts.visitor.visitor.serviceMustBeAtLeast2Characters')
    isValid = false
  }
  else {
    formErrors.value.service = ''
  }

  return isValid
}

const counselorLabel = (record: Pick<Visitor, 'counselor'>) => record.counselor?.name || translate('educational.unassigned')
const counselorPayload = () => ({ counselor_employee_id: visitorData.value.counselor_employee_id })

const fetchVisitors = async () => {
  try {
    isTableLoading.value = true

    const response = await VisitorApi.getAll({
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
      search: search.value,
      counselor_employee_id: filterCounselorId.value || undefined,
      follow_up: isFollowUpList.value ? 1 : 0,
    })

    visitors.value = response
  }
  catch (error) {
    console.error('Error fetching visitors:', error)
    visitors.value = []
  }
  finally {
    isTableLoading.value = false
  }
}

const filtered = computed(() => {
  return visitors.value.filter(v => {
    const matchesSearch = v.name.toLowerCase().includes(search.value.toLowerCase())
      || v.contact.includes(search.value)
      || (v.email?.toLowerCase().includes(search.value.toLowerCase()) ?? false)

    const matchesCounselor = !filterCounselorId.value || v.counselor_employee_id === filterCounselorId.value

    return matchesSearch && matchesCounselor
  })
})

const headers = computed(() => [
  { title: translate('common.fields.name'), key: 'name', sortable: true },
  { title: translate('common.messages.contact'), key: 'contact', sortable: true },
  { title: translate('common.fields.email'), key: 'email', sortable: true },
  { title: translate('common.messages.service'), key: 'service', sortable: true },
  { title: translate('agencyStaff.counselor'), key: 'counselor', sortable: true },
  { title: translate('common.fields.date'), key: 'date', sortable: true },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' },
])

const printTable = () => {
  // `printing-table-*` tells the shared print helper to isolate the
  // PrintableListReport rather than print this interactive data-table page.
  void printDocument({ bodyClass: 'printing-table-visitors', pageStyle: 'size: A4 landscape; margin: 12mm;' })
}

const addNewVisitor = async () => {
  if (!validateForm())
    return

  try {
    isSaving.value = true

    const data: CreateVisitorData = {
      name: visitorData.value.name,
      contact: visitorData.value.contact,
      email: visitorData.value.email || null,
      service: visitorData.value.service,
      notes: visitorData.value.notes,
      date: new Date().toISOString().split('T')[0],
      ...counselorPayload(),
    }

    const response = await VisitorApi.create(data)
    if (response)
      visitors.value.push(response)

    isAddNewVisitorDialogVisible.value = false
    resetForm()
  }
  catch (error) {
    console.error('Error creating visitor:', error)
  }
  finally {
    isSaving.value = false
  }
}

const editVisitor = async () => {
  if (!selectedVisitor.value)
    return
  if (!validateForm())
    return

  try {
    isSaving.value = true

    const data: UpdateVisitorData = {
      name: visitorData.value.name,
      contact: visitorData.value.contact,
      email: visitorData.value.email || null,
      service: visitorData.value.service,
      notes: visitorData.value.notes,
      ...counselorPayload(),
    }

    const response = await VisitorApi.update(selectedVisitor.value.id, data)
    if (response) {
      const index = visitors.value.findIndex(v => v.id === selectedVisitor.value?.id)
      if (index !== -1)
        visitors.value[index] = response
    }
    isEditVisitorDialogVisible.value = false
    resetForm()
  }
  catch (error) {
    console.error('Error updating visitor:', error)
  }
  finally {
    isSaving.value = false
  }
}

const openEditDialog = (visitor: Visitor) => {
  selectedVisitor.value = visitor
  visitorData.value = {
    name: visitor.name,
    contact: visitor.contact,
    email: visitor.email ?? '',
    service: visitor.service,
    counselor_employee_id: visitor.counselor_employee_id,
    notes: visitor.notes || '',
  }
  isEditVisitorDialogVisible.value = true
}

const openDeleteDialog = (visitor: Visitor) => {
  selectedVisitor.value = visitor
  isDeleteVisitorDialogVisible.value = true
}

const deleteVisitor = async () => {
  if (!selectedVisitor.value)
    return

  try {
    isSaving.value = true
    await VisitorApi.delete(selectedVisitor.value.id)
    visitors.value = visitors.value.filter(v => v.id !== selectedVisitor.value?.id)
    isDeleteVisitorDialogVisible.value = false
  }
  catch (error) {
    console.error('Error deleting visitor:', error)
  }
  finally {
    isSaving.value = false
  }
}

// Mark or unmark a visitor as worthy of follow-up. The record leaves the list on
// screen either way, so it is dropped instead of being left as a row that
// contradicts the active list.
const setFollowUp = async (visitor: Visitor, followUp: boolean) => {
  try {
    isSaving.value = true
    await VisitorApi.setFollowUp(visitor.id, followUp)
    visitors.value = visitors.value.filter(v => v.id !== visitor.id)
    toast.success(translate(followUp ? 'pages.contracts.visitor.visitor.markedForFollowUp' : 'pages.contracts.visitor.visitor.removedFromFollowUp'))
  }
  catch (error) {
    toast.error(apiErrorMessage(error, translate('pages.contracts.visitor.visitor.followUpUpdateFailed')))
  }
  finally {
    isSaving.value = false
  }
}

// Extract unique values for filters
const services = computed(() => {
  return [...new Set(visitors.value.map(v => v.service))].filter(Boolean)
})

watch(() => route.query.list, () => { fetchVisitors() })

onMounted(() => {
  fetchVisitors()
  EmployeeApi.getAssignmentOptions('counselor').then(data => { counselorDirectory.value = data }).catch(console.error)
})
</script>

<template>
  <div class="erp-workspace">
  <ErpPageHeader
    :title="isFollowUpList ? $t('common.navigation.followUpList') : $t('common.navigation.visitor')"
    section="crm"
  />
  <VCard>
    <VCardText>
      <VRow
        align="center"
        class="table-toolbar"
      >
        <VCol
          cols="12"
          md="7"
        >
          <VTextField
            v-model="search"
            class="data-table-search"
            prepend-inner-icon="tabler-search"
            :placeholder="$t('pages.contracts.visitor.visitor.searchVisitors')"
            density="compact"
            clearable
            hide-details
            @input="fetchVisitors"
          />
        </VCol>
        <VCol
          cols="12"
          md="5"
          class="table-toolbar-actions d-flex flex-nowrap justify-end gap-2"
        >
          <DataTableFilterMenu
            :active="Boolean(filterCounselorId || dateFrom || dateTo)"
            @reset="filterCounselorId = null; dateFrom = ''; dateTo = ''; fetchVisitors()"
          >
            <ErpDateRange v-model:from="dateFrom" v-model:to="dateTo" @apply="fetchVisitors" />
            <VSelect
              v-model="filterCounselorId"
              :items="counselorDirectory"
              item-title="name"
              item-value="id"
              :label="$t('agencyStaff.counselor')"
              density="compact"
              clearable
              hide-details
              @update:model-value="fetchVisitors"
            />
          </DataTableFilterMenu>
          <VBtn
            icon="tabler-printer"
            variant="text"
            :loading="isTableLoading"
            @click="printTable"
          />
          <!-- The Follow-up list is a curated list: visitors enter it from the
               Visitors list, so there is no add button here. -->
          <VBtn
            v-if="!isFollowUpList"
            color="primary"
            :disabled="isTableLoading"
            @click="openAddDialog"
          >
            <VIcon
              icon="tabler-plus"
              class="me-1"
            /> {{ $t('pages.contracts.visitor.visitor.addVisitor') }}
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>

    <TableLoadingSkeleton
      v-if="isTableLoading"
      :columns="headers.length"
      :label="$t('common.messages.loading')"
    />
    <TableEmptyState
      v-else-if="!filtered.length"
      :icon="isFollowUpList ? 'tabler-list-check' : 'tabler-walk'"
      :description="$t('common.messages.noRecordsFound')"
    />
    <ResponsiveDataTable
      v-else
      :headers="headers"
      :items="filtered"
      item-value="id"
      class="crm-responsive-table"
      mobile-breakpoint="sm"
      mobile-cards
      mobile-title-key="name"
      mobile-action-key="actions"
    >
      <!-- Quick contact belongs to the Follow-up list: unqualified visitors are
           shown as plain contact details until somebody marks them for follow-up. -->
      <template #item.contact="{ item }">
        <ContactActionMenu
          v-if="isFollowUpList"
          kind="phone"
          :value="item.contact"
          contactable-type="visitor"
          :contactable-id="item.id"
          audience="visitor"
          :context="item"
        />
        <span v-else>{{ item.contact }}</span>
      </template>

      <template #item.email="{ item }">
        <ContactActionMenu
          v-if="isFollowUpList && item.email"
          kind="email"
          :value="item.email"
          contactable-type="visitor"
          :contactable-id="item.id"
        />
        <span
          v-else
          class="text-medium-emphasis"
        >{{ item.email || '—' }}</span>
      </template>

      <template #item.counselor="{ item }">
        <div class="d-flex align-center ga-2">
          <VAvatar
            size="28"
            color="primary"
            variant="tonal"
          >
            <VIcon
              icon="tabler-user-star"
              size="16"
            />
          </VAvatar>
          <span>{{ counselorLabel(item) }}</span>
        </div>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex justify-end gap-1">
          <!-- Mark for follow-up: the only promotion path into the Follow-up list. -->
          <VBtn
            v-if="!isFollowUpList"
            icon="tabler-star"
            variant="text"
            size="small"
            color="warning"
            :loading="isSaving"
            @click="setFollowUp(item, true)"
          >
            <VIcon icon="tabler-star" /><VTooltip
              activator="parent"
              location="top"
            >
              {{ $t('pages.contracts.visitor.visitor.markForFollowUp') }}
            </VTooltip>
          </VBtn>
          <VMenu v-if="isFollowUpList && ability.can('read', 'create_contracts') && ability.can('read', 'view_contracts')">
            <template #activator="{ props: menuProps }">
              <VBtn v-bind="menuProps" icon="tabler-file-plus" variant="text" size="small"><VIcon icon="tabler-file-plus" /><VTooltip activator="parent">{{ $t('serviceContracts.create') }}</VTooltip></VBtn>
            </template>
            <VList>
              <VListItem :title="$t('educational.builtInServices.scholarship')" :to="{ name: 'scholarship-contracts', query: { visitor: item.id } }" />
              <VListItem :title="$t('educational.builtInServices.testOffer')" :to="{ name: 'test-offer-contracts', query: { visitor: item.id } }" />
              <VListItem :title="$t('educational.builtInServices.private')" :to="{ name: 'private-contracts', query: { visitor: item.id } }" />
              <VListItem :title="$t('educational.builtInServices.academy')" :to="{ name: 'academy-contracts', query: { visitor: item.id } }" />
            </VList>
          </VMenu>
          <CrmRelationshipDetails
            v-if="isFollowUpList"
            :id="item.id"
            type="visitor"
            :title="item.name"
          />
          <!-- The Follow-up list takes the visitor back off the list again, so the
               move stays reversible. -->
          <VBtn
            v-if="isFollowUpList"
            icon="tabler-star-off"
            variant="text"
            size="small"
            color="warning"
            :loading="isSaving"
            @click="setFollowUp(item, false)"
          >
            <VIcon icon="tabler-star-off" /><VTooltip
              activator="parent"
              location="top"
            >
              {{ $t('pages.contracts.visitor.visitor.removeFromFollowUp') }}
            </VTooltip>
          </VBtn>
          <VBtn
            icon="tabler-edit"
            variant="text"
            size="small"
            color="medium-emphasis"
            :loading="isSaving"
            @click="openEditDialog(item)"
          >
            <VIcon icon="tabler-edit" /><VTooltip
              activator="parent"
              location="top"
            >
              {{ $t('pages.contracts.visitor.visitor.editVisitor') }}
            </VTooltip>
          </VBtn>
          <VBtn
            icon="tabler-trash"
            variant="text"
            size="small"
            color="error"
            :loading="isSaving"
            @click="openDeleteDialog(item)"
          >
            <VIcon icon="tabler-trash" /><VTooltip
              activator="parent"
              location="top"
            >
              {{ $t('pages.contracts.visitor.visitor.deleteVisitor') }}
            </VTooltip>
          </VBtn>
        </div>
      </template>
    </ResponsiveDataTable>
  </VCard>
  <PrintableListReport
    :title="isFollowUpList ? $t('common.crmUi.printReports.followUp.title') : $t('common.crmUi.printReports.visitors.title')"
    :subtitle="isFollowUpList ? $t('common.crmUi.printReports.followUp.subtitle') : $t('common.crmUi.printReports.visitors.subtitle')"
    :columns="headers.slice(0, -1).map(header => header.title)"
    :rows="filtered.map(visitor => [visitor.name, visitor.contact, visitor.email || '—', visitor.service, counselorLabel(visitor), visitor.date])"
  />

  <!-- Add New Visitor Dialog -->
  <VDialog
    v-model="isAddNewVisitorDialogVisible"
    max-width="1000"
    class="erp-form"
    scrollable
  >
    <VCard>
      <VCardTitle class="pa-4">
        <span class="text-h5">{{ $t('pages.contracts.visitor.visitor.addNewVisitor') }}</span>
      </VCardTitle>

      <VCardText>
        <VForm
          class="app-form"
          @submit.prevent="addNewVisitor"
        >
          <VRow>
            <VCol
              cols="12"
              md="4"
            >
              <ErpField
                v-model="visitorData.name"
                :label="$t('common.messages.visitorName')"
                :placeholder="$t('common.messages.enterVisitorName')"
                :error-messages="formErrors.name"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <ErpField
                v-model="visitorData.contact"
                :label="$t('common.messages.contactPhone')"
                :placeholder="$t('common.messages.enterPhoneNumber')"
                :error-messages="formErrors.contact"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <ErpField
                v-model="visitorData.email"
                :label="$t('common.fields.email')"
                type="email"
                prepend-inner-icon="tabler-mail"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <ErpField
                v-model="visitorData.service"
                :label="$t('common.messages.service')"
                :placeholder="$t('common.messages.enterService')"
                :error-messages="formErrors.service"
                required
              />
            </VCol>
            <VCol cols="12" md="4">
              <VAutocomplete
                v-model="visitorData.counselor_employee_id"
                :items="counselorDirectory"
                item-title="name"
                item-value="id"
                :label="$t('agencyStaff.counselor')"
                :placeholder="$t('agencyStaff.counselor')"
                :disabled="counselorLocked"
                :clearable="!counselorLocked"
              />
            </VCol>
            <VCol cols="12">
              <ErpField kind="textarea"
                v-model="visitorData.notes"
                :label="$t('common.crmUi.visitorNotes')"
                rows="3"
                :hint="$t('common.crmUi.visitorNotesHint')"
                persistent-hint
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
          :disabled="isSaving"
          @click="isAddNewVisitorDialogVisible = false; resetForm()"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="isSaving"
          @click="addNewVisitor"
        >
          {{ $t('common.actions.add') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Edit Visitor Dialog -->
  <VDialog
    v-model="isEditVisitorDialogVisible"
    max-width="1000"
    class="erp-form"
    scrollable
  >
    <VCard>
      <VCardTitle class="pa-4">
        <span class="text-h5">{{ $t('pages.contracts.visitor.visitor.editVisitor') }}</span>
      </VCardTitle>

      <VCardText>
        <VForm
          class="app-form"
          @submit.prevent="editVisitor"
        >
          <VRow>
            <VCol
              cols="12"
              md="4"
            >
              <ErpField
                v-model="visitorData.name"
                :label="$t('common.messages.visitorName')"
                :placeholder="$t('common.messages.enterVisitorName')"
                :hint="$t('common.messages.enterTheFullNameOfTheVisitor')"
                persistent-hint
                :error-messages="formErrors.name"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <ErpField
                v-model="visitorData.contact"
                :label="$t('common.messages.contactPhone')"
                :placeholder="$t('common.messages.enterPhoneNumber')"
                :hint="$t('common.messages.enterTheVisitorSContactNumber')"
                persistent-hint
                :error-messages="formErrors.contact"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <ErpField
                v-model="visitorData.email"
                :label="$t('common.fields.email')"
                type="email"
                prepend-inner-icon="tabler-mail"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <ErpField
                v-model="visitorData.service"
                :label="$t('common.messages.service')"
                :placeholder="$t('common.messages.enterService')"
                :hint="$t('common.messages.enterTheServiceTheVisitorIsInterestedIn')"
                persistent-hint
                :error-messages="formErrors.service"
                required
              />
            </VCol>
            <VCol cols="12" md="4">
              <VAutocomplete
                v-model="visitorData.counselor_employee_id"
                :items="counselorDirectory"
                item-title="name"
                item-value="id"
                :label="$t('agencyStaff.counselor')"
                :placeholder="$t('agencyStaff.counselor')"
                :hint="$t('agencyStaff.optionalCounselor')"
                persistent-hint
                clearable
              />
            </VCol>
            <VCol cols="12">
              <ErpField kind="textarea"
                v-model="visitorData.notes"
                :label="$t('common.crmUi.visitorNotes')"
                rows="3"
                :hint="$t('common.crmUi.visitorNotesHint')"
                persistent-hint
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
          :disabled="isSaving"
          @click="isEditVisitorDialogVisible = false; resetForm()"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="isSaving"
          @click="editVisitor"
        >
          {{ $t('common.actions.save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Delete Visitor Dialog -->
  <VDialog
    v-model="isDeleteVisitorDialogVisible"
    max-width="500"
  >
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
          :disabled="isSaving"
          @click="isDeleteVisitorDialogVisible = false"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          color="error"
          :loading="isSaving"
          @click="deleteVisitor"
        >
          {{ $t('common.actions.delete') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
  </div>
</template>

<style>
@media (max-width: 1279px) { .table-toolbar-actions { justify-content: flex-start; } }
</style>
