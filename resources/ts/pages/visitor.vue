<script setup lang="ts">
import { translate } from '@/utils/localization'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAbility } from '@casl/vue'
import { useToast } from 'vue-toastification'
import PrintableListReport from '@/components/PrintableListReport.vue'
import VisitorProfileFields from '@/components/crm/VisitorProfileFields.vue'
import VisitorApi, { type CreateVisitorData, type UpdateVisitorData, type Visitor, type VisitorFormData } from '@/api/visitor'
import EmployeeApi, { type Employee } from '@/api/employee'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'
import { printDocument } from '@/utils/printDocument'
import { apiErrorMessage } from '@/utils/apiErrors'
import ContactActionMenu from '@/components/ContactActionMenu.vue'
import ResponsiveDataTableServer from '@/components/responsive-data-table/ResponsiveDataTableServer.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import TableLoadingSkeleton from '@/components/TableLoadingSkeleton.vue'
import { useCookie } from '@core/composable/useCookie'

const visitors = ref<Visitor[]>([])
// Both lists ask the server for one page at a time.
const page = ref(1)
const perPage = ref(25)
const total = ref(0)
// Filled from every matching visitor right before printing.
const reportRows = ref<string[][]>([])
const search = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const route = useRoute()
// This page hosts two lists. The Visitors list shows the records that are not
// marked worthy of follow-up; the Follow-up list shows the marked ones. The
// shared CRM strip selects the mode with `?list=follow-up`, so both lists stay
// deep-linkable and print their own report title.
const isFollowUpList = computed(() => route.query.list === 'follow-up')
const filterConsultantId = ref<number | null>(/^\d+$/.test(String(route.query.consultant_employee_id ?? '')) ? Number(route.query.consultant_employee_id) || null : null)
const isAddNewVisitorDialogVisible = ref(false)
const isEditVisitorDialogVisible = ref(false)
const isDeleteVisitorDialogVisible = ref(false)
const selectedVisitor = ref<Visitor | null>(null)
const isTableLoading = ref(false)
const isSaving = ref(false)
const consultantDirectory = ref<Employee[]>([])
const catalogs = ref<Partial<Record<'education-levels' | 'provinces' | 'english-levels' | 'acquisition-routes', MasterDataRecord[]>>>({})
const toast = useToast()
const ability = useAbility()
const canReview = computed(() => ability.can('read', 'review_visitors'))

// A consultant creating a visitor owns that visitor: the consultant field is
// pre-filled with the signed-in employee and locked so the case cannot be
// attributed to somebody else from the form.
const currentUser = useCookie<{ roles?: string[]; employee?: { id?: number } | null } | null>('userData')
const isConsultantUser = computed(() => (currentUser.value?.roles ?? [])
  .some(role => role === 'counselor' || role === 'senior_counselor'))
const lockedConsultantId = computed(() => (isConsultantUser.value ? currentUser.value?.employee?.id ?? null : null))
const consultantLocked = computed(() => isConsultantUser.value)

const emptyVisitorForm = (): VisitorFormData => ({
  name: '',
  contact: '',
  email: '',
  service: '',
  education_level_id: null,
  age: null,
  grade_score: null,
  province_id: null,
  english_level_id: null,
  acquisition_route_id: null,
  consultant_employee_id: lockedConsultantId.value,
  consultant_review: '',
  notes: '',
})

const visitorData = ref<VisitorFormData>(emptyVisitorForm())

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

const consultantLabel = (record: Pick<Visitor, 'consultant'>) => record.consultant?.name || translate('educational.unassigned')

const profilePayload = (): Omit<CreateVisitorData, 'date' | 'name' | 'contact' | 'service'> => ({
  email: visitorData.value.email || null,
  education_level_id: visitorData.value.education_level_id,
  age: visitorData.value.age === '' ? null : Number(visitorData.value.age),
  grade_score: visitorData.value.grade_score === '' ? null : visitorData.value.grade_score,
  province_id: visitorData.value.province_id,
  english_level_id: visitorData.value.english_level_id,
  acquisition_route_id: visitorData.value.acquisition_route_id,
  consultant_employee_id: consultantLocked.value ? lockedConsultantId.value : visitorData.value.consultant_employee_id,
  notes: visitorData.value.notes,
  ...(canReview.value ? { consultant_review: visitorData.value.consultant_review.trim() || null } : {}),
})

const listParams = () => ({
  date_from: dateFrom.value || undefined,
  date_to: dateTo.value || undefined,
  search: search.value || undefined,
  consultant_employee_id: filterConsultantId.value || undefined,
  follow_up: (isFollowUpList.value ? 1 : 0) as 0 | 1,
})

const fetchVisitors = async () => {
  try {
    isTableLoading.value = true

    const result = await VisitorApi.listPage({ ...listParams(), page: page.value, perPage: perPage.value })

    visitors.value = result.rows
    total.value = result.total
  }
  catch (error) {
    console.error('Error fetching visitors:', error)
    visitors.value = []
    total.value = 0
  }
  finally {
    isTableLoading.value = false
  }
}

function updateTableOptions(options: { page: number; itemsPerPage: number }) {
  if (page.value === options.page && perPage.value === options.itemsPerPage)
    return

  page.value = options.page
  perPage.value = options.itemsPerPage
  fetchVisitors()
}

// Typing in the search box is a filter change, so it starts from the first page.
const debouncedSearch = useDebounceFn(() => {
  page.value = 1
  void fetchVisitors()
}, 300)

const reload = () => {
  page.value = 1
  void fetchVisitors()
}

const headers = computed(() => [
  { title: translate('common.fields.name'), key: 'name', sortable: true },
  { title: translate('common.messages.contact'), key: 'contact', sortable: true },
  { title: translate('common.fields.email'), key: 'email', sortable: true },
  { title: translate('common.messages.service'), key: 'service', sortable: true },
  { title: translate('educational.educationLevel'), key: 'education', sortable: false },
  { title: translate('pages.contracts.visitor.visitor.province'), key: 'province', sortable: false },
  { title: translate('pages.contracts.visitor.visitor.englishLevel'), key: 'english', sortable: false },
  { title: translate('pages.contracts.visitor.visitor.acquisitionRoute'), key: 'acquisition', sortable: false },
  { title: translate('pages.contracts.visitor.visitor.consultant'), key: 'consultant', sortable: true },
  { title: translate('pages.contracts.visitor.visitor.review'), key: 'review', sortable: false },
  { title: translate('common.fields.date'), key: 'date', sortable: true },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' },
])

const visitorReportRow = (visitor: Visitor): string[] => [
  visitor.name,
  visitor.contact,
  visitor.email || '—',
  visitor.service,
  visitor.education_level?.name || '—',
  visitor.province?.name || '—',
  visitor.english_level?.name || '—',
  visitor.acquisition_route?.name || '—',
  consultantLabel(visitor),
  visitor.consultant_review || '—',
  visitor.date,
]

const printTable = async () => {
  // `printing-table-*` tells the shared print helper to isolate the
  // PrintableListReport rather than print this interactive data-table page.
  // The report covers the whole filtered set, so it is filled from every row.
  try {
    reportRows.value = (await VisitorApi.getAll(listParams())).map(visitorReportRow)
    await printDocument({ bodyClass: 'printing-table-visitors', pageStyle: 'size: A4 landscape; margin: 12mm;' })
  }
  catch (error) {
    console.error('Error preparing visitor report:', error)
    toast.error(apiErrorMessage(error))
  }
}

const addNewVisitor = async () => {
  if (!validateForm())
    return

  try {
    isSaving.value = true

    const data: CreateVisitorData = {
      name: visitorData.value.name,
      contact: visitorData.value.contact,
      service: visitorData.value.service,
      date: new Date().toISOString().split('T')[0],
      ...profilePayload(),
    }

    await VisitorApi.create(data)
    // A new visitor belongs on the first page of the current list.
    reload()
    isAddNewVisitorDialogVisible.value = false
    resetForm()
  }
  catch (error) {
    console.error('Error creating visitor:', error)
    toast.error(apiErrorMessage(error))
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
      service: visitorData.value.service,
      ...profilePayload(),
    }

    await VisitorApi.update(selectedVisitor.value.id, data)
    await fetchVisitors()
    isEditVisitorDialogVisible.value = false
    resetForm()
  }
  catch (error) {
    console.error('Error updating visitor:', error)
    toast.error(apiErrorMessage(error))
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
    education_level_id: visitor.education_level_id,
    age: visitor.age,
    grade_score: visitor.grade_score,
    province_id: visitor.province_id,
    english_level_id: visitor.english_level_id,
    acquisition_route_id: visitor.acquisition_route_id,
    consultant_employee_id: visitor.consultant_employee_id,
    consultant_review: visitor.consultant_review || '',
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
    // The deleted row was the last one on this page, so step back instead of
    // showing an empty page.
    if (page.value > 1 && visitors.value.length === 1)
      page.value -= 1
    await fetchVisitors()
    isDeleteVisitorDialogVisible.value = false
  }
  catch (error) {
    console.error('Error deleting visitor:', error)
    toast.error(apiErrorMessage(error))
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
    // The record left this list, so the page is refilled from the server.
    await fetchVisitors()
    toast.success(translate(followUp ? 'pages.contracts.visitor.visitor.markedForFollowUp' : 'pages.contracts.visitor.visitor.removedFromFollowUp'))
  }
  catch (error) {
    toast.error(apiErrorMessage(error, translate('pages.contracts.visitor.visitor.followUpUpdateFailed')))
  }
  finally {
    isSaving.value = false
  }
}

const reviewDialog = ref(false)
const reviewVisitor = ref<Visitor | null>(null)
const consultantReview = ref('')

const openConsultantReview = (visitor: Visitor) => {
  reviewVisitor.value = visitor
  consultantReview.value = visitor.consultant_review || ''
  reviewDialog.value = true
}

// Review permission is deliberately separate from editing the visitor record.
const saveConsultantReview = async () => {
  if (!reviewVisitor.value)
    return

  try {
    isSaving.value = true
    const updated = await VisitorApi.updateConsultantReview(reviewVisitor.value.id, consultantReview.value.trim() || null)
    const index = visitors.value.findIndex(v => v.id === reviewVisitor.value?.id)
    if (index !== -1)
      visitors.value[index] = updated
    reviewDialog.value = false
    toast.success(translate('pages.contracts.visitor.visitor.consultantReviewSaved'))
  }
  catch (error) {
    toast.error(apiErrorMessage(error))
  }
  finally {
    isSaving.value = false
  }
}

watch(() => route.query.list, reload)

onMounted(async () => {
  fetchVisitors()
  const [consultants, educationLevels, provinces, englishLevels, acquisitionRoutes] = await Promise.allSettled([
    EmployeeApi.getAssignmentOptions('counselor'),
    MasterDataApi.list('education-levels'),
    MasterDataApi.list('provinces'),
    MasterDataApi.list('english-levels'),
    MasterDataApi.list('acquisition-routes'),
  ])
  if (consultants.status === 'fulfilled')
    consultantDirectory.value = consultants.value
  else
    console.error(consultants.reason)
  catalogs.value = {
    'education-levels': educationLevels.status === 'fulfilled' ? educationLevels.value : [],
    provinces: provinces.status === 'fulfilled' ? provinces.value : [],
    'english-levels': englishLevels.status === 'fulfilled' ? englishLevels.value : [],
    'acquisition-routes': acquisitionRoutes.status === 'fulfilled' ? acquisitionRoutes.value : [],
  }
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
            @input="debouncedSearch"
          />
        </VCol>
        <VCol
          cols="12"
          md="5"
          class="table-toolbar-actions d-flex flex-nowrap justify-end gap-2"
        >
          <DataTableFilterMenu
            :active="Boolean(filterConsultantId || dateFrom || dateTo)"
            @reset="filterConsultantId = null; dateFrom = ''; dateTo = ''; reload()"
          >
            <ErpDateRange v-model:from="dateFrom" v-model:to="dateTo" @apply="fetchVisitors" />
            <VSelect
              v-model="filterConsultantId"
              :items="consultantDirectory"
              item-title="name"
              item-value="id"
              :label="$t('pages.contracts.visitor.visitor.consultant')"
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
      v-else-if="!total"
      :icon="isFollowUpList ? 'tabler-list-check' : 'tabler-walk'"
      :description="$t('common.messages.noRecordsFound')"
    />
    <ResponsiveDataTableServer
      v-else
      :headers="headers"
      :items="visitors"
      :items-length="total"
      :page="page"
      :items-per-page="perPage"
      :items-per-page-options="[10, 25, 50, 100]"
      item-value="id"
      class="crm-responsive-table"
      mobile-breakpoint="sm"
      mobile-cards
      mobile-title-key="name"
      mobile-action-key="actions"
      @update:options="updateTableOptions"
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

      <template #item.education="{ item }">
        <div class="d-flex flex-column">
          <span>{{ item.education_level?.name || '—' }}</span>
          <span v-if="item.grade_score !== null && item.grade_score !== ''" class="text-caption text-medium-emphasis">{{ item.grade_score }}%</span>
        </div>
      </template>

      <template #item.province="{ item }">
        <span class="text-medium-emphasis">{{ item.province?.name || '—' }}</span>
      </template>

      <template #item.english="{ item }">
        <span class="text-medium-emphasis">{{ item.english_level?.name || '—' }}</span>
      </template>

      <template #item.acquisition="{ item }">
        <span class="text-medium-emphasis">{{ item.acquisition_route?.name || '—' }}</span>
      </template>

      <template #item.consultant="{ item }">
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
          <span>{{ consultantLabel(item) }}</span>
        </div>
      </template>

      <template #item.review="{ item }"><span class="text-medium-emphasis">{{ item.consultant_review || '—' }}</span></template>

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
            v-if="canReview"
            icon="tabler-message"
            variant="text"
            size="small"
            color="success"
            :loading="isSaving"
            @click="openConsultantReview(item)"
          >
            <VIcon icon="tabler-message" /><VTooltip
              activator="parent"
              location="top"
            >
              {{ $t('pages.contracts.visitor.visitor.consultantReview') }}
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
    </ResponsiveDataTableServer>
  </VCard>
  <PrintableListReport
    :title="isFollowUpList ? $t('common.crmUi.printReports.followUp.title') : $t('common.crmUi.printReports.visitors.title')"
    :subtitle="isFollowUpList ? $t('common.crmUi.printReports.followUp.subtitle') : $t('common.crmUi.printReports.visitors.subtitle')"
    :columns="headers.slice(0, -1).map(header => header.title)"
    :rows="reportRows"
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
          <VisitorProfileFields
            v-model="visitorData"
            :catalogs="catalogs"
            :consultants="consultantDirectory"
            :consultant-locked="consultantLocked"
            :can-review="canReview"
            :errors="formErrors"
          />
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

  <VDialog v-model="reviewDialog" max-width="640">
    <VCard>
      <VCardTitle>{{ $t('pages.contracts.visitor.visitor.consultantReview') }}</VCardTitle>
      <VCardText>
        <VTextarea v-model="consultantReview" :label="$t('pages.contracts.visitor.visitor.consultantReview')" rows="4" auto-grow />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn variant="outlined" @click="reviewDialog = false">{{ $t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :loading="isSaving" @click="saveConsultantReview">{{ $t('common.actions.save') }}</VBtn>
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
          <VisitorProfileFields
            v-model="visitorData"
            :catalogs="catalogs"
            :consultants="consultantDirectory"
            :consultant-locked="consultantLocked"
            :can-review="canReview"
            :errors="formErrors"
          />
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
