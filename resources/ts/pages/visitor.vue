<script setup lang="ts">
import { translate, translateBackendValue } from '@/utils/localization'
import { BUILT_IN_SERVICES, type BuiltInService, type ConsultantReview } from '@/api/applicants'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'
import ErpDateRange from '@/components/erp/ErpDateRange.vue'
import ErpSearchField from '@/components/erp/ErpSearchField.vue'
import { computed, onMounted, reactive, ref, watch } from 'vue'
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
import ResponsiveDataTableServer from '@/components/responsive-data-table/ResponsiveDataTableServer.vue'
import TableEmptyState from '@/components/TableEmptyState.vue'
import ContactActionMenu from '@/components/ContactActionMenu.vue'
import CrmRelationshipDetails from '@/components/crm/CrmRelationshipDetails.vue'
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
const filterCountryId = ref<number | null>(null)
const filterService = ref<BuiltInService | ''>('')
const sortBy = ref<'name' | 'contact' | 'email' | 'service' | 'date'>('date')
const sortDirection = ref<'asc' | 'desc'>('desc')
const filterMenuOpen = ref(false)
const filterDraft = reactive({
  dateFrom: '',
  dateTo: '',
  consultantId: null as number | null,
  countryId: null as number | null,
  service: '' as BuiltInService | '',
})
const isAddNewVisitorDialogVisible = ref(false)
const isEditVisitorDialogVisible = ref(false)
const isDeleteVisitorDialogVisible = ref(false)
const isVisitorDetailsDialogVisible = ref(false)
const selectedVisitor = ref<Visitor | null>(null)
const visitorDetails = ref<Visitor | null>(null)
const isTableLoading = ref(false)
const isSaving = ref(false)
const consultantDirectory = ref<Employee[]>([])
const catalogs = ref<Partial<Record<'countries' | 'education-levels' | 'provinces' | 'english-levels' | 'acquisition-routes', MasterDataRecord[]>>>({})
const toast = useToast()
const ability = useAbility()
const canReview = computed(() => ability.can('read', 'review_visitors'))
const hasActiveFilters = computed(() => Boolean(
  filterConsultantId.value
  || filterCountryId.value
  || filterService.value
  || dateFrom.value
  || dateTo.value,
))
const serviceOptions = computed(() => BUILT_IN_SERVICES.map(service => ({
  title: translate(`educational.builtInServices.${service === 'test_offer' ? 'testOffer' : service}`),
  value: service,
})))
const activeCountries = computed(() => (catalogs.value.countries ?? [])
  .filter(country => country.is_active)
  .map(country => ({ title: country.name, value: country.id })))
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
  country_id: null,
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
  else if (!BUILT_IN_SERVICES.includes(visitorData.value.service as (typeof BUILT_IN_SERVICES)[number])) {
    // The select only offers built-in services; this catches a stale or
    // hand-crafted value before the request is sent.
    formErrors.value.service = translate('pages.contracts.visitor.visitor.serviceIsRequired')
    isValid = false
  }
  else {
    formErrors.value.service = ''
  }

  return isValid
}

const consultantLabel = (record: Pick<Visitor, 'consultant'>) => record.consultant?.name || translate('educational.unassigned')
const reviewColor = (review: ConsultantReview | null): 'error' | 'warning' | 'success' | undefined => {
  const colors: Record<ConsultantReview, 'error' | 'warning' | 'success'> = {
    bad: 'error',
    good: 'warning',
    great: 'success',
  }

  return review ? colors[review] : undefined
}

const openVisitorDetails = (visitor: Visitor) => {
  visitorDetails.value = visitor
  isVisitorDetailsDialogVisible.value = true
}

const visitorDetailFields = computed(() => {
  const visitor = visitorDetails.value
  if (!visitor)
    return []

  return [
    { key: 'name', label: translate('common.messages.visitorName'), value: visitor.name, icon: 'tabler-user' },
    { key: 'contact', label: translate('common.messages.contactPhone'), value: visitor.contact, icon: 'tabler-phone' },
    { key: 'email', label: translate('common.fields.email'), value: visitor.email, icon: 'tabler-mail' },
    { key: 'service', label: translate('common.messages.service'), value: serviceLabel(visitor), icon: 'tabler-briefcase' },
    { key: 'country', label: translate('common.fields.country'), value: visitor.country?.name, icon: 'tabler-world' },
    { key: 'education', label: translate('educational.educationLevel'), value: visitor.education_level?.name, icon: 'tabler-school' },
    { key: 'age', label: translate('pages.contracts.visitor.visitor.age'), value: visitor.age, icon: 'tabler-user' },
    { key: 'grade-score', label: translate('pages.contracts.visitor.visitor.gradeScoreLabel'), value: visitor.grade_score == null || visitor.grade_score === '' ? null : `${visitor.grade_score}%`, icon: 'tabler-percentage' },
    { key: 'province', label: translate('pages.contracts.visitor.visitor.province'), value: visitor.province?.name, icon: 'tabler-map-pin' },
    { key: 'english', label: translate('pages.contracts.visitor.visitor.englishLevel'), value: visitor.english_level?.name, icon: 'tabler-language' },
    { key: 'acquisition-route', label: translate('pages.contracts.visitor.visitor.acquisitionRoute'), value: visitor.acquisition_route?.name, icon: 'tabler-speakerphone' },
    { key: 'consultant', label: translate('pages.contracts.visitor.visitor.consultant'), value: consultantLabel(visitor), icon: 'tabler-user-star' },
    { key: 'review', label: translate('pages.contracts.visitor.visitor.review'), value: visitor.consultant_review ? translateBackendValue(visitor.consultant_review) : null, icon: 'tabler-message' },
    { key: 'date', label: translate('common.fields.date'), value: visitor.date, icon: 'tabler-calendar' },
  ]
})

const profilePayload = (): Omit<CreateVisitorData, 'date' | 'name' | 'contact' | 'service'> => ({
  email: visitorData.value.email || null,
  country_id: visitorData.value.country_id,
  education_level_id: visitorData.value.education_level_id,
  age: visitorData.value.age === '' || visitorData.value.age == null ? null : Number(visitorData.value.age),
  grade_score: visitorData.value.grade_score === '' || visitorData.value.grade_score == null ? null : visitorData.value.grade_score,
  province_id: visitorData.value.province_id,
  english_level_id: visitorData.value.english_level_id,
  acquisition_route_id: visitorData.value.acquisition_route_id,
  consultant_employee_id: consultantLocked.value ? lockedConsultantId.value : visitorData.value.consultant_employee_id,
  notes: visitorData.value.notes,
  ...(canReview.value ? { consultant_review: visitorData.value.consultant_review || null } : {}),
})

const listParams = () => ({
  date_from: dateFrom.value || undefined,
  date_to: dateTo.value || undefined,
  search: search.value || undefined,
  consultant_employee_id: filterConsultantId.value || undefined,
  country_id: filterCountryId.value || undefined,
  service: filterService.value || undefined,
  sort_by: sortBy.value,
  sort_direction: sortDirection.value,
  follow_up: (isFollowUpList.value ? 1 : 0) as 0 | 1,
})

const syncFilterDraft = () => {
  filterDraft.dateFrom = dateFrom.value
  filterDraft.dateTo = dateTo.value
  filterDraft.consultantId = filterConsultantId.value
  filterDraft.countryId = filterCountryId.value
  filterDraft.service = filterService.value
}

const applyFilters = () => {
  if (filterDraft.dateFrom && filterDraft.dateTo && filterDraft.dateFrom > filterDraft.dateTo)
    return

  dateFrom.value = filterDraft.dateFrom
  dateTo.value = filterDraft.dateTo
  filterConsultantId.value = filterDraft.consultantId
  filterCountryId.value = filterDraft.countryId
  filterService.value = filterDraft.service
  filterMenuOpen.value = false
  reload()
}

const resetFilters = () => {
  dateFrom.value = ''
  dateTo.value = ''
  filterConsultantId.value = null
  filterCountryId.value = null
  filterService.value = ''
  syncFilterDraft()
  filterMenuOpen.value = false
  reload()
}

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

function updateTableOptions(options: { page: number; itemsPerPage: number; sortBy?: Array<{ key: string; order?: 'asc' | 'desc' }> }) {
  const requestedSort = options.sortBy?.[0]
  const sortableFields = ['name', 'contact', 'email', 'service', 'date'] as const
  const nextSortBy = requestedSort && sortableFields.includes(requestedSort.key as typeof sortableFields[number])
    ? requestedSort.key as typeof sortableFields[number]
    : 'date'
  const nextDirection = requestedSort?.order === 'asc' ? 'asc' : 'desc'

  if (page.value === options.page && perPage.value === options.itemsPerPage && sortBy.value === nextSortBy && sortDirection.value === nextDirection)
    return

  page.value = options.page
  perPage.value = options.itemsPerPage
  sortBy.value = nextSortBy
  sortDirection.value = nextDirection
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

// The list shows the service in the reader's language; legacy free text that
// has not been normalized yet stays visible exactly as stored.
const serviceLabel = (visitor: Visitor) => visitor.service_label_key ? translate(visitor.service_label_key) : (visitor.service_label || visitor.service)

const headers = computed(() => [
  { title: translate('common.fields.name'), key: 'name', sortable: true },
  { title: translate('common.messages.contact'), key: 'contact', sortable: true },
  ...(isFollowUpList.value ? [{ title: translate('common.fields.email'), key: 'email', sortable: true }] : []),
  { title: translate('common.messages.service'), key: 'service', sortable: true },
  { title: translate('common.fields.country'), key: 'country', sortable: false },
  { title: translate('educational.educationLevel'), key: 'education', sortable: false },
  { title: translate('pages.contracts.visitor.visitor.consultant'), key: 'consultant', sortable: false },
  { title: translate('pages.contracts.visitor.visitor.review'), key: 'review', sortable: false },
  { title: translate('common.fields.date'), key: 'date', sortable: true },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' },
])

const visitorReportRow = (visitor: Visitor): string[] => [
  visitor.name,
  visitor.contact,
  ...(isFollowUpList.value ? [visitor.email || '—'] : []),
  serviceLabel(visitor),
  visitor.country?.name || '—',
  visitor.education_level?.name || '—',
  consultantLabel(visitor),
  visitor.consultant_review ? translateBackendValue(visitor.consultant_review) : '—',
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
      // validateForm() guarantees one of the four keys before we get here.
      service: visitorData.value.service as BuiltInService,
      date: new Date().toISOString().split('T')[0],
      ...profilePayload(),
    }

    const created = await VisitorApi.create(data)
    // Keep the list responsive after saving instead of replacing every row
    // with a full-table loading state.
    if (!isFollowUpList.value) {
      visitors.value = [created, ...visitors.value].slice(0, perPage.value)
      total.value += 1
    }
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
      service: visitorData.value.service as BuiltInService,
      ...profilePayload(),
    }

    const updated = await VisitorApi.update(selectedVisitor.value.id, data)
    const index = visitors.value.findIndex(visitor => visitor.id === updated.id)
    if (index !== -1)
      visitors.value[index] = updated
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
    // Only canonical keys are selectable; a legacy free-text service has to be
    // re-chosen from the four built-ins before the visitor can be saved again.
    service: visitor.service_key ?? '',
    country_id: visitor.country_id,
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
    visitors.value = visitors.value.filter(visitor => visitor.id !== selectedVisitor.value?.id)
    total.value = Math.max(0, total.value - 1)
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
    // The record leaves the active list. Removing only that row keeps the
    // other records usable while the action finishes.
    visitors.value = visitors.value.filter(row => row.id !== visitor.id)
    total.value = Math.max(0, total.value - 1)
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
const consultantReview = ref<ConsultantReview | ''>('')
const reviewOptions = computed(() => [
  { title: translate('common.statuses.bad'), value: 'bad' },
  { title: translate('common.statuses.good'), value: 'good' },
  { title: translate('common.statuses.great'), value: 'great' },
])

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
    const updated = await VisitorApi.updateConsultantReview(reviewVisitor.value.id, consultantReview.value || null)
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
watch(filterMenuOpen, open => {
  if (open)
    syncFilterDraft()
})

onMounted(async () => {
  fetchVisitors()
  const [consultants, countries, educationLevels, provinces, englishLevels, acquisitionRoutes] = await Promise.allSettled([
    EmployeeApi.getAssignmentOptions('counselor'),
    MasterDataApi.list('countries'),
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
    countries: countries.status === 'fulfilled' ? countries.value : [],
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
          <ErpSearchField
            v-model="search"
            :placeholder="$t('pages.contracts.visitor.visitor.searchVisitors')"
            @input="debouncedSearch"
          />
        </VCol>
        <VCol
          cols="12"
          md="5"
          class="table-toolbar-actions d-flex flex-nowrap justify-end gap-2"
        >
          <DataTableFilterMenu
            v-model="filterMenuOpen"
            :active="hasActiveFilters"
            :show-reset="false"
          >
            <ErpDateRange v-model:from="filterDraft.dateFrom" v-model:to="filterDraft.dateTo" :show-apply="false" />
            <ErpField
              v-model="filterDraft.consultantId"
              kind="autocomplete"
              :items="consultantDirectory"
              item-title="name"
              item-value="id"
              :label="$t('pages.contracts.visitor.visitor.consultant')"
              clearable
            />
            <ErpField v-model="filterDraft.countryId" kind="select" :items="activeCountries" :label="$t('common.fields.country')" clearable />
            <ErpField v-model="filterDraft.service" kind="select" :items="serviceOptions" :label="$t('common.messages.service')" clearable />
            <template #actions>
              <VBtn class="visitor-filter-reset" variant="flat" @click="resetFilters">
                {{ $t('common.messages.reset') }}
              </VBtn>
              <VBtn color="primary" variant="flat" class="visitor-filter-apply" @click="applyFilters">
                {{ $t('educational.apply') }}
              </VBtn>
            </template>
          </DataTableFilterMenu>
          <VBtn
            icon="tabler-printer"
            variant="tonal"
            class="visitor-toolbar-icon"
            :aria-label="$t('common.actions.print')"
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

    <ResponsiveDataTableServer
      :headers="headers"
      :items="visitors"
      :items-length="total"
      :page="page"
      :items-per-page="perPage"
      :items-per-page-options="[10, 25, 50, 100]"
      item-value="id"
      :class="['crm-responsive-table', { 'crm-responsive-table--with-email': isFollowUpList }]"
      mobile-breakpoint="sm"
      mobile-cards
      mobile-expand-all
      mobile-actions-as-menu
      :sticky-key-column="false"
      :sticky-action-column="false"
      :skeleton-rows="5"
      :show-scroll-affordance="false"
      :loading="isTableLoading"
      pagination
      mobile-title-key="name"
      mobile-action-key="actions"
      @update:options="updateTableOptions"
    >
      <template #item.contact="{ item }">
        <div dir="ltr" class="visitor-contact-value">
          <ContactActionMenu
            v-if="item.contact"
            kind="phone"
            :value="item.contact"
            contactable-type="visitor"
            :contactable-id="item.id"
            audience="visitor"
            :context="item"
          />
          <span v-else class="text-medium-emphasis">—</span>
        </div>
      </template>

      <template #item.email="{ item }">
        <div dir="ltr" class="visitor-contact-value">
          <ContactActionMenu
            v-if="item.email"
            kind="email"
            :value="item.email"
            contactable-type="visitor"
            :contactable-id="item.id"
            :context="item"
          />
          <span v-else class="text-medium-emphasis">—</span>
        </div>
      </template>

      <template #item.service="{ item }">
        {{ serviceLabel(item) }}
      </template>

      <template #item.country="{ item }">
        <span class="text-medium-emphasis">{{ item.country?.name || '—' }}</span>
      </template>

      <template #item.education="{ item }">
        <span>{{ item.education_level?.name || '—' }}<template v-if="item.grade_score !== null && item.grade_score !== ''"> - {{ item.grade_score }}%</template></span>
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

      <template #item.review="{ item }">
        <VChip
          v-if="item.consultant_review"
          size="small"
          :color="reviewColor(item.consultant_review)"
          variant="tonal"
          class="visitor-review-chip"
        >
          {{ translateBackendValue(item.consultant_review) }}
        </VChip>
        <span v-else class="text-medium-emphasis">—</span>
      </template>

      <template #item.actions="{ item }">
        <VMenu location="bottom end" :close-on-content-click="false">
          <template #activator="{ props: menuProps }">
            <VBtn
              v-bind="menuProps"
              icon="tabler-dots-vertical"
              variant="tonal"
              size="small"
              class="visitor-actions-trigger"
              :aria-label="$t('common.messages.actions')"
            />
          </template>

          <VList class="visitor-actions-menu" density="compact" min-width="220">
            <CrmRelationshipDetails
              v-if="isFollowUpList"
              type="visitor"
              :id="item.id"
              :title="item.name"
              menu-action
            />
            <VListItem
              v-if="!isFollowUpList"
              prepend-icon="tabler-address-book"
              :title="$t('common.actions.viewFullDetails')"
              @click="openVisitorDetails(item)"
            />
            <VListItem
              v-if="!isFollowUpList"
              prepend-icon="tabler-star"
              :title="$t('pages.contracts.visitor.visitor.markForFollowUp')"
              :disabled="isSaving"
              @click="setFollowUp(item, true)"
            />
            <VListItem
              v-else
              prepend-icon="tabler-star-off"
              :title="$t('pages.contracts.visitor.visitor.removeFromFollowUp')"
              :disabled="isSaving"
              @click="setFollowUp(item, false)"
            />
            <template v-if="isFollowUpList && ability.can('read', 'create_contracts') && ability.can('read', 'view_contracts')">
              <VDivider class="my-1" />
              <VMenu location="end top">
                <template #activator="{ props: contractProps }">
                  <VListItem
                    v-bind="contractProps"
                    prepend-icon="tabler-file-plus"
                    append-icon="tabler-chevron-right"
                    :title="$t('serviceContracts.create')"
                  />
                </template>
                <VList class="visitor-contract-menu" density="compact" min-width="220">
                  <VListItem prepend-icon="tabler-file-plus" :title="$t('educational.builtInServices.scholarship')" :to="{ name: 'scholarship-contracts', query: { visitor: item.id } }" />
                  <VListItem prepend-icon="tabler-file-plus" :title="$t('educational.builtInServices.testOffer')" :to="{ name: 'test-offer-contracts', query: { visitor: item.id } }" />
                  <VListItem prepend-icon="tabler-file-plus" :title="$t('educational.builtInServices.private')" :to="{ name: 'private-contracts', query: { visitor: item.id } }" />
                  <VListItem prepend-icon="tabler-file-plus" :title="$t('educational.builtInServices.academy')" :to="{ name: 'academy-contracts', query: { visitor: item.id } }" />
                </VList>
              </VMenu>
            </template>
            <VDivider class="my-1" />
            <VListItem
              v-if="canReview"
              prepend-icon="tabler-message"
              :title="$t('pages.contracts.visitor.visitor.consultantReview')"
              :disabled="isSaving"
              @click="openConsultantReview(item)"
            />
            <VListItem
              prepend-icon="tabler-edit"
              :title="$t('pages.contracts.visitor.visitor.editVisitor')"
              :disabled="isSaving"
              @click="openEditDialog(item)"
            />
            <VListItem
              prepend-icon="tabler-trash"
              class="visitor-actions-menu__delete"
              :title="$t('pages.contracts.visitor.visitor.deleteVisitor')"
              :disabled="isSaving"
              @click="openDeleteDialog(item)"
            />
          </VList>
        </VMenu>
      </template>

      <template #no-data>
        <div class="visitor-empty-state">
          <TableEmptyState
            icon="tabler-users-group"
            :min-height="0"
            :title="$t('pages.contracts.visitor.visitor.noVisitorRecords')"
            :description="$t('pages.contracts.visitor.visitor.noVisitorRecordsDescription')"
          />
          <VBtn
            v-if="!isFollowUpList"
            color="primary"
            variant="tonal"
            class="visitor-empty-state__action"
            @click="openAddDialog"
          >
            <VIcon icon="tabler-plus" class="me-1" />
            {{ $t('pages.contracts.visitor.visitor.addVisitor') }}
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
    max-width="1180"
    content-class="visitor-form-dialog"
    scrollable
  >
    <VCard class="visitor-form-card">
      <VCardTitle class="visitor-dialog-title px-5 py-5">
        <span class="text-h5 visitor-dialog-heading visitor-dialog-heading--primary">{{ $t('pages.contracts.visitor.visitor.addNewVisitor') }}</span>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="tonal"
          color="error"
          class="visitor-dialog-close"
          :aria-label="$t('common.actions.close')"
          :disabled="isSaving"
          @click="isAddNewVisitorDialogVisible = false; resetForm()"
        />
      </VCardTitle>

      <VCardText class="visitor-dialog-body pa-5 pa-sm-6">
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

      <VCardActions class="visitor-dialog-actions px-5 py-4">
        <VSpacer />
        <VBtn
          variant="flat"
          color="error"
          class="visitor-dialog-cancel"
          :disabled="isSaving"
          @click="isAddNewVisitorDialogVisible = false; resetForm()"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          class="visitor-dialog-submit"
          :loading="isSaving"
          @click="addNewVisitor"
        >
          {{ $t('common.actions.add') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <VDialog
    v-model="isVisitorDetailsDialogVisible"
    max-width="920"
    content-class="visitor-form-dialog visitor-details-dialog"
    scrollable
  >
    <VCard class="visitor-form-card">
      <VCardTitle class="visitor-dialog-title px-5 py-5">
        <div>
          <div class="visitor-details-dialog__eyebrow">{{ $t('pages.contracts.visitor.visitor.visitorDetails') }}</div>
          <div class="visitor-dialog-heading">{{ visitorDetails?.name }}</div>
        </div>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="tonal"
          color="error"
          class="visitor-dialog-close"
          :aria-label="$t('common.actions.close')"
          @click="isVisitorDetailsDialogVisible = false"
        />
      </VCardTitle>

      <VCardText class="visitor-dialog-body visitor-details-dialog__body pa-5 pa-sm-6">
        <div class="visitor-details-grid">
          <div v-for="field in visitorDetailFields" :key="field.key" class="visitor-detail-tile">
            <VIcon :icon="field.icon" size="19" class="visitor-detail-tile__icon" />
            <div class="visitor-detail-tile__content">
              <span class="visitor-detail-tile__label">{{ field.label }}</span>
              <div v-if="field.key === 'contact' && visitorDetails?.contact" dir="ltr" class="visitor-contact-value">
                <ContactActionMenu
                  kind="phone"
                  :value="visitorDetails.contact"
                  contactable-type="visitor"
                  :contactable-id="visitorDetails.id"
                  audience="visitor"
                  :context="visitorDetails"
                />
              </div>
              <div v-else-if="field.key === 'email' && visitorDetails?.email" dir="ltr" class="visitor-contact-value">
                <ContactActionMenu
                  kind="email"
                  :value="visitorDetails.email"
                  contactable-type="visitor"
                  :contactable-id="visitorDetails.id"
                  :context="visitorDetails"
                />
              </div>
              <span v-else class="visitor-detail-tile__value">{{ field.value ?? '—' }}</span>
            </div>
          </div>
          <div class="visitor-detail-tile visitor-detail-tile--notes">
            <VIcon icon="tabler-notes" size="19" class="visitor-detail-tile__icon" />
            <div class="visitor-detail-tile__content">
              <span class="visitor-detail-tile__label">{{ $t('pages.contracts.visitor.visitor.notes') }}</span>
              <span class="visitor-detail-tile__value">{{ visitorDetails?.notes || '—' }}</span>
            </div>
          </div>
        </div>
      </VCardText>
    </VCard>
  </VDialog>

  <VDialog
    v-model="reviewDialog"
    max-width="460"
    content-class="visitor-form-dialog visitor-review-dialog"
  >
    <VCard class="visitor-form-card">
      <VCardTitle class="visitor-dialog-title px-5 py-4">
        <span class="visitor-dialog-heading">{{ $t('pages.contracts.visitor.visitor.consultantReview') }}</span>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="tonal"
          color="error"
          class="visitor-dialog-close"
          :aria-label="$t('common.actions.close')"
          :disabled="isSaving"
          @click="reviewDialog = false"
        />
      </VCardTitle>
      <VCardText class="visitor-dialog-body pa-5">
        <ErpField
          v-model="consultantReview"
          kind="select"
          :items="reviewOptions"
          :label="$t('pages.contracts.visitor.visitor.consultantReview')"
          :placeholder="$t('pages.contracts.visitor.visitor.placeholders.consultantReview')"
          clearable
        />
      </VCardText>
      <VCardActions class="visitor-dialog-actions px-5 py-4">
        <VSpacer />
        <VBtn class="visitor-dialog-cancel" variant="flat" color="error" :disabled="isSaving" @click="reviewDialog = false">
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn class="visitor-dialog-submit" color="primary" variant="flat" :loading="isSaving" @click="saveConsultantReview">
          {{ $t('common.actions.save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Edit Visitor Dialog -->
  <VDialog
    v-model="isEditVisitorDialogVisible"
    max-width="1180"
    content-class="visitor-form-dialog"
    scrollable
  >
    <VCard class="visitor-form-card">
      <VCardTitle class="visitor-dialog-title px-5 py-5">
        <span class="text-h5 visitor-dialog-heading">{{ $t('pages.contracts.visitor.visitor.editVisitor') }}</span>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="tonal"
          color="error"
          class="visitor-dialog-close"
          :aria-label="$t('common.actions.close')"
          :disabled="isSaving"
          @click="isEditVisitorDialogVisible = false; resetForm()"
        />
      </VCardTitle>

      <VCardText class="visitor-dialog-body pa-5 pa-sm-6">
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

      <VCardActions class="visitor-dialog-actions px-5 py-4">
        <VSpacer />
        <VBtn
          variant="flat"
          color="error"
          class="visitor-dialog-cancel"
          :disabled="isSaving"
          @click="isEditVisitorDialogVisible = false; resetForm()"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          class="visitor-dialog-submit"
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
    max-width="480"
    class="visitor-delete-overlay"
  >
    <VCard class="visitor-delete-dialog">
      <VCardText class="visitor-delete-dialog__content">
        <div class="visitor-delete-dialog__icon" aria-hidden="true">
          <VIcon icon="tabler-trash" size="30" />
        </div>
        <h2 class="visitor-delete-dialog__title">
          {{ $t('common.confirmations.areYouSureYouWantToDeleteThisVisitor') }}
        </h2>
        <p class="visitor-delete-dialog__description">
          {{ $t('common.confirmations.thisActionCannotBeUndoneTheVisitorRecordWillBePermanentlyRemovedFromTh') }}
        </p>
      </VCardText>

      <VCardActions class="visitor-delete-dialog__actions">
        <VBtn
          class="visitor-delete-dialog__button visitor-delete-dialog__button--cancel"
          :disabled="isSaving"
          @click="isDeleteVisitorDialogVisible = false"
        >
          {{ $t('common.actions.cancel') }}
        </VBtn>
        <VBtn
          class="visitor-delete-dialog__button visitor-delete-dialog__button--delete"
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

<style lang="scss">
@media (max-width: 1279px) { .table-toolbar-actions { justify-content: flex-start; } }

.crm-responsive-table .v-table__wrapper > table {
  min-inline-size: 1180px;
}

.crm-responsive-table .v-table__wrapper > table > thead > tr > th,
.crm-responsive-table .v-table__wrapper > table > tbody > tr > td {
  white-space: nowrap;
}

.crm-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(1),
.crm-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(1) { min-inline-size: 10rem; }
.crm-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(2),
.crm-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(2) { min-inline-size: 10rem; }
.crm-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(3),
.crm-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(3) { min-inline-size: 9rem; }
.crm-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(4),
.crm-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(4) { min-inline-size: 9rem; }
.crm-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(5),
.crm-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(5) { min-inline-size: 9rem; }
.crm-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(6),
.crm-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(6) { min-inline-size: 11rem; }
.crm-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(7),
.crm-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(7) { min-inline-size: 11rem; }
.crm-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(8),
.crm-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(8) { min-inline-size: 8rem; }
.crm-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(9),
.crm-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(9) { min-inline-size: 5rem; }
.crm-responsive-table .v-table__wrapper > table > thead > tr > th:nth-child(10),
.crm-responsive-table .v-table__wrapper > table > tbody > tr > td:nth-child(10) { min-inline-size: 5rem; }

.crm-responsive-table--with-email .v-table__wrapper > table > thead > tr > th:nth-child(3),
.crm-responsive-table--with-email .v-table__wrapper > table > tbody > tr > td:nth-child(3) { min-inline-size: 13rem; }
.crm-responsive-table--with-email .v-table__wrapper > table > thead > tr > th:nth-child(4),
.crm-responsive-table--with-email .v-table__wrapper > table > tbody > tr > td:nth-child(4) { min-inline-size: 9rem; }
.crm-responsive-table--with-email .v-table__wrapper > table > thead > tr > th:nth-child(5),
.crm-responsive-table--with-email .v-table__wrapper > table > tbody > tr > td:nth-child(5) { min-inline-size: 9rem; }
.crm-responsive-table--with-email .v-table__wrapper > table > thead > tr > th:nth-child(6),
.crm-responsive-table--with-email .v-table__wrapper > table > tbody > tr > td:nth-child(6) { min-inline-size: 11rem; }
.crm-responsive-table--with-email .v-table__wrapper > table > thead > tr > th:nth-child(7),
.crm-responsive-table--with-email .v-table__wrapper > table > tbody > tr > td:nth-child(7) { min-inline-size: 11rem; }
.crm-responsive-table--with-email .v-table__wrapper > table > thead > tr > th:nth-child(8),
.crm-responsive-table--with-email .v-table__wrapper > table > tbody > tr > td:nth-child(8) { min-inline-size: 8rem; }
.crm-responsive-table--with-email .v-table__wrapper > table > thead > tr > th:nth-child(9),
.crm-responsive-table--with-email .v-table__wrapper > table > tbody > tr > td:nth-child(9) { min-inline-size: 8rem; }

.crm-responsive-table .v-table__wrapper > table > tbody > tr:hover > td {
  background-color: rgba(var(--v-theme-on-surface), 0.035) !important;
}

.crm-responsive-table .v-table__wrapper:has(.visitor-empty-state) {
  overflow-x: hidden;
}

.crm-responsive-table .v-table__wrapper > table:has(.visitor-empty-state) {
  min-inline-size: 100%;
  table-layout: fixed;
}

/* A wide row needs column minimums, but an empty table needs its headers to
   fit the card. Let only this no-data state release those row constraints. */
.crm-responsive-table .v-table__wrapper > table:has(.visitor-empty-state) > thead > tr > th,
.crm-responsive-table .v-table__wrapper > table:has(.visitor-empty-state) > tbody > tr > td {
  min-inline-size: 0 !important;
  white-space: normal;
}

.crm-responsive-table .v-table__wrapper > table > tbody > tr:has(.visitor-empty-state):hover > td {
  background-color: transparent !important;
}

.visitor-actions-trigger {
  border-radius: 999px !important;
  background-color: rgba(var(--v-theme-on-surface), 0.04) !important;
  color: rgba(var(--v-theme-on-surface), 0.72) !important;
}

.visitor-actions-trigger:hover {
  background-color: rgba(var(--v-theme-on-surface), 0.08) !important;
}

.visitor-actions-menu__delete { color: rgb(var(--v-theme-error)) !important; }

.visitor-empty-state {
  align-items: center;
  display: flex;
  flex-direction: column;
  inline-size: 100%;
  justify-content: center;
  min-block-size: 17rem;
  padding: 1.25rem 1.25rem 2rem;
}

.visitor-empty-state .table-empty-state {
  min-block-size: 0 !important;
  padding-block: 1rem;
}

.crm-responsive-table .v-table__wrapper > table > tbody > tr:has(.visitor-empty-state) > td {
  padding: 0 !important;
}

.visitor-empty-state__action {
  margin-block-start: -0.25rem;
  background-color: rgba(var(--v-theme-primary), 0.12) !important;
  color: rgb(var(--v-theme-primary)) !important;
}

.visitor-empty-state__action:hover { background-color: rgba(var(--v-theme-primary), 0.18) !important; }

@media (max-width: 959px) {
  .crm-responsive-table.responsive-data-table--mobile-cards .v-table__wrapper > table > tbody > tr:hover > td {
    background-color: transparent !important;
  }

  .crm-responsive-table.responsive-data-table--mobile-cards .responsive-data-table__field + .responsive-data-table__field {
    border-block-start-color: rgba(var(--v-theme-on-surface), 0.08);
  }

  .crm-responsive-table.responsive-data-table--mobile-cards .responsive-data-table__field-value > .d-flex {
    justify-content: flex-end;
  }
}

.visitor-filter-reset {
  background-color: rgba(var(--v-theme-primary), 0.12) !important;
  color: rgb(var(--v-theme-primary)) !important;
}

.visitor-filter-reset:hover,
.visitor-filter-reset:focus-visible,
.visitor-filter-apply,
.visitor-filter-apply:hover,
.visitor-filter-apply:focus-visible {
  background-color: rgb(var(--v-theme-primary)) !important;
  color: rgb(var(--v-theme-on-primary)) !important;
}

.visitor-contact-value {
  direction: ltr;
  unicode-bidi: plaintext;
}

.visitor-form-dialog .visitor-form-card {
  overflow: hidden;
}

.visitor-form-dialog .visitor-dialog-title,
.visitor-form-dialog .visitor-dialog-actions {
  display: flex;
  align-items: center;
  /* A near-surface tint separates these areas without the heavy dark block
     produced by Vuetify's surface-variant token. It works in both themes. */
  background-color: color-mix(in srgb, rgb(var(--v-theme-surface)) 94%, rgb(var(--v-theme-on-surface)) 6%);
  border-color: rgba(var(--v-theme-on-surface), 0.035);
  border-style: solid;
  border-width: 0;
}

.visitor-form-dialog .visitor-dialog-heading {
  font-size: 1.625rem;
  font-weight: 700;
}

.visitor-form-dialog .visitor-dialog-heading--primary {
  font-size: 2rem;
}

/* This blur is scoped to the visitor form overlay, not the global dialog
   scrim, so other popups keep their own visual treatment. */
.v-overlay:has(.visitor-form-dialog) .v-overlay__scrim {
  backdrop-filter: blur(4px);
  background-color: rgba(var(--v-theme-on-surface), 0.24);
}

.visitor-form-dialog .visitor-dialog-title {
  border-block-end-width: 1px;
}

.visitor-form-dialog .visitor-dialog-actions {
  border-block-start-width: 1px;
}

.visitor-form-dialog .visitor-dialog-body {
  background-color: rgb(var(--v-theme-surface));
}

.visitor-form-dialog .visitor-dialog-close {
  inline-size: 36px;
  block-size: 36px;
  border: 1px solid rgba(var(--v-theme-error), 0.38);
  border-radius: 50%;
  color: rgb(var(--v-theme-error));
}

.visitor-form-dialog .visitor-dialog-close:hover {
  background-color: rgba(var(--v-theme-error), 0.18) !important;
}

.visitor-form-dialog button.visitor-dialog-cancel.v-btn {
  border: 1px solid rgba(var(--v-theme-error), 0.62) !important;
  background-color: rgba(var(--v-theme-error), 0.12) !important;
  color: rgb(var(--v-theme-error)) !important;
}

.visitor-form-dialog button.visitor-dialog-cancel.v-btn:hover {
  border-color: rgb(var(--v-theme-error));
  background-color: rgb(var(--v-theme-error)) !important;
  color: rgb(var(--v-theme-on-error)) !important;
}

.visitor-form-dialog .visitor-dialog-submit {
  background-color: rgb(var(--v-theme-primary)) !important;
  color: rgb(var(--v-theme-on-primary)) !important;
}

.visitor-form-dialog .visitor-dialog-submit:hover {
  filter: brightness(0.9);
}

.visitor-details-dialog__eyebrow {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.visitor-details-grid {
  display: grid;
  gap: 0.875rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.visitor-detail-tile {
  align-items: flex-start;
  display: flex;
  gap: 0.75rem;
  min-block-size: 5.25rem;
  padding: 1rem;
  border: 1px solid rgba(var(--v-border-color), 0.42);
  border-radius: 12px;
  background-color: color-mix(in srgb, rgb(var(--v-theme-surface)) 96%, rgb(var(--v-theme-on-surface)) 4%);
}

.visitor-detail-tile__icon {
  flex: none;
  margin-block-start: 0.15rem;
  color: rgb(var(--v-theme-primary));
}

.visitor-detail-tile__content {
  display: grid;
  gap: 0.25rem;
  min-inline-size: 0;
}

.visitor-detail-tile__label {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
  font-weight: 600;
}

.visitor-detail-tile__value {
  overflow-wrap: anywhere;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.45;
}

.visitor-detail-tile--notes { grid-column: 1 / -1; }

.visitor-delete-overlay .v-overlay__scrim {
  background: rgba(var(--v-theme-on-surface), 0.3) !important;
  backdrop-filter: blur(7px);
  opacity: 1 !important;
}

.visitor-delete-dialog {
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), 0.2) !important;
  border-radius: 16px !important;
  background: rgb(var(--v-theme-surface)) !important;
  box-shadow: 0 24px 48px rgba(0, 0, 0, 0.18) !important;
  text-align: center;
}

.visitor-delete-dialog__content {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 2rem 2rem 1.25rem !important;
}

.visitor-delete-dialog__icon {
  display: grid;
  width: 4.25rem;
  height: 4.25rem;
  margin-block-end: 1.25rem;
  border-radius: 50%;
  place-items: center;
  background: rgba(var(--v-theme-error), 0.14);
  color: rgb(var(--v-theme-error));
}

.visitor-delete-dialog__title {
  margin: 0;
  color: rgb(var(--v-theme-on-surface));
  font-size: 1.2rem;
  font-weight: 700;
  line-height: 1.45;
  text-align: center;
}

.visitor-delete-dialog__description {
  max-width: 24rem;
  margin: 0.65rem 0 0;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  line-height: 1.65;
  text-align: center;
}

.visitor-delete-dialog__actions {
  display: grid !important;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
  padding: 0 2rem 2rem !important;
}

.visitor-delete-dialog__button {
  min-height: 2.85rem;
  border-radius: 10px !important;
  font-weight: 700;
  letter-spacing: 0;
  text-transform: none;

  &--cancel {
    border: 1px solid rgba(var(--v-theme-secondary), 0.52) !important;
    background: rgba(var(--v-theme-secondary), 0.13) !important;
    color: rgb(var(--v-theme-secondary)) !important;

    &:hover {
      background: rgb(var(--v-theme-secondary)) !important;
      color: rgb(var(--v-theme-on-secondary)) !important;
    }
  }

  &--delete {
    background: rgb(var(--v-theme-error)) !important;
    color: rgb(var(--v-theme-on-error)) !important;

    &:hover {
      filter: brightness(0.88);
    }
  }
}

@media (max-width: 600px) {
  .visitor-details-grid { grid-template-columns: 1fr; }

  .visitor-detail-tile--notes { grid-column: auto; }

  .visitor-delete-dialog__content { padding-inline: 1.25rem !important; }

  .visitor-delete-dialog__actions { padding-inline: 1.25rem !important; }

  .visitor-form-dialog .visitor-dialog-title {
    padding: 16px !important;
  }

  .visitor-form-dialog .visitor-dialog-body {
    padding: 16px !important;
  }

  .visitor-form-dialog .visitor-dialog-actions {
    padding: 12px 16px !important;
  }

  .visitor-form-dialog .visitor-dialog-close {
    inline-size: 34px;
    block-size: 34px;
  }

  .visitor-form-dialog .visitor-dialog-heading--primary {
    font-size: 1.75rem;
  }
}
</style>
