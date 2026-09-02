<script setup lang="ts">
import MarketerApi from '@/api/marketer'
import OfficeApi from '@/api/office'
import OfficeGeofenceMap from '@/components/setup/OfficeGeofenceMap.vue'
import { useToast } from 'vue-toastification'
import { translateBackendValue } from '@/utils/localization'
import { useAutomaticAttendance } from '@/composables/useAutomaticAttendance'

type DirectoryRecord = {
  id: number
  name: string
  code?: string
  email: string | null
  phone: string | null
  status: 'active' | 'inactive'
  notes?: string | null
  address?: string | null
  customer_count?: number
  visitor_count?: number
  asset_count?: number
  latitude?: number | null
  longitude?: number | null
  geofence_radius_meters?: number
  geofence_enabled?: boolean
}
type DirectoryForm = {
  name: string
  code: string
  email: string
  phone: string
  status: 'active' | 'inactive'
  notes: string
  address: string
  latitude: number | null
  longitude: number | null
  geofenceRadiusMeters: number
  geofenceEnabled: boolean
}

const props = defineProps<{ kind: 'marketers' | 'offices' }>()
const { t: $t } = useI18n()
const toast = useToast()
const records = ref<DirectoryRecord[]>([])
const search = ref('')
const statusFilter = ref<'active' | 'inactive' | null>(null)
const loading = ref(false)
const dialogOpen = ref(false)
const deleteDialogOpen = ref(false)
const editing = ref<DirectoryRecord | null>(null)
const deleting = ref<DirectoryRecord | null>(null)
const form = ref<DirectoryForm>({ name: '', code: '', email: '', phone: '', status: 'active', notes: '', address: '', latitude: null, longitude: null, geofenceRadiusMeters: 100, geofenceEnabled: false })
const locating = ref(false)
const currentLocation = reactive<{ latitude: number | null; longitude: number | null }>({ latitude: null, longitude: null })
const { enabled: automaticAttendanceEnabled, load: loadAutomaticAttendance } = useAutomaticAttendance()

const isMarketer = computed(() => props.kind === 'marketers')
const singular = computed(() => translate(isMarketer.value ? 'common.fields.marketer' : 'common.fields.office'))
const title = computed(() => translate(isMarketer.value ? 'pages.settings.directory.marketers' : 'pages.settings.directory.offices'))
const subtitle = computed(() => isMarketer.value
  ? translate('pages.settings.directory.marketersDescription')
  : translate('pages.settings.directory.officesDescription'))
const icon = computed(() => isMarketer.value ? 'tabler-speakerphone' : 'tabler-building-community')
const activeCount = computed(() => records.value.filter(record => record.status === 'active').length)
const assignmentCount = computed(() => records.value.reduce((sum, record) => sum
  + (record.customer_count ?? 0) + (record.visitor_count ?? 0) + (record.asset_count ?? 0), 0))
const filteredRecords = computed(() => {
  const query = search.value.trim().toLowerCase()
  return records.value.filter(record => {
    const text = `${record.name} ${record.code ?? ''} ${record.email ?? ''} ${record.phone ?? ''}`.toLowerCase()
    return (!query || text.includes(query)) && (!statusFilter.value || record.status === statusFilter.value)
  })
})

const headers = [
  { title: translate('common.fields.name'), key: 'name' },
  { title: translate('common.messages.contact'), key: 'contact', sortable: false },
  { title: translate('common.messages.details'), key: 'details', sortable: false },
  { title: translate('common.fields.status'), key: 'status' },
  { title: translate('pages.settings.directorycrudpage.assignedRecords'), key: 'assigned', sortable: false },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
]

async function load() {
  loading.value = true
  try {
    records.value = isMarketer.value
      ? await MarketerApi.getAll() as DirectoryRecord[]
      : await OfficeApi.getAll() as DirectoryRecord[]
  } catch (error: any) {
    toast.error(error.message || `Could not load ${props.kind}.`)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { name: '', code: '', email: '', phone: '', status: 'active', notes: '', address: '', latitude: null, longitude: null, geofenceRadiusMeters: 100, geofenceEnabled: false }
  dialogOpen.value = true
  if (!isMarketer.value) locateCurrentLocation(false)
}

function openEdit(record: DirectoryRecord) {
  editing.value = record
  form.value = {
    name: record.name,
    code: record.code ?? '',
    email: record.email ?? '',
    phone: record.phone ?? '',
    status: record.status,
    notes: record.notes ?? '',
    address: record.address ?? '',
    latitude: record.latitude ?? null,
    longitude: record.longitude ?? null,
    geofenceRadiusMeters: record.geofence_radius_meters ?? 100,
    geofenceEnabled: record.geofence_enabled ?? false,
  }
  dialogOpen.value = true
}

async function save() {
  if (!form.value.name.trim()) return toast.error(`${singular.value} name is required.`)
  if (!isMarketer.value && !form.value.code.trim()) return toast.error(translate('pages.settings.directorycrudpage.officeCodeIsRequired'))
  if (!isMarketer.value && form.value.geofenceEnabled && (form.value.latitude == null || form.value.longitude == null))
    return toast.error(translate('pages.settings.geofence.coordinatesRequired'))

  loading.value = true
  try {
    const common = {
      name: form.value.name.trim(),
      email: form.value.email.trim() || null,
      phone: form.value.phone.trim() || null,
      status: form.value.status,
    }
    const saved = isMarketer.value
      ? await (editing.value
          ? MarketerApi.update(editing.value.id, { ...common, notes: form.value.notes.trim() || null })
          : MarketerApi.create({ ...common, notes: form.value.notes.trim() || null }))
      : await (editing.value
          ? OfficeApi.update(editing.value.id, { ...common, code: form.value.code.trim().toUpperCase(), address: form.value.address.trim() || null, latitude: form.value.latitude, longitude: form.value.longitude, geofence_radius_meters: form.value.geofenceRadiusMeters, geofence_enabled: form.value.geofenceEnabled })
          : OfficeApi.create({ ...common, code: form.value.code.trim().toUpperCase(), address: form.value.address.trim() || null, latitude: form.value.latitude, longitude: form.value.longitude, geofence_radius_meters: form.value.geofenceRadiusMeters, geofence_enabled: form.value.geofenceEnabled }))

    const index = records.value.findIndex(record => record.id === saved.id)
    if (index === -1) records.value.unshift(saved as DirectoryRecord)
    else records.value[index] = saved as DirectoryRecord
    dialogOpen.value = false
    toast.success(`${singular.value[0].toUpperCase()}${singular.value.slice(1)} saved.`)
  } catch (error: any) {
    toast.error(error.errors?.name?.[0] || error.errors?.code?.[0] || error.message || `Could not save ${singular.value}.`)
  } finally {
    loading.value = false
  }
}

function locateCurrentLocation(overwriteCoordinates: boolean) {
  if (!navigator.geolocation) {
    toast.error(translate('pages.settings.geofence.notSupported'))
    return
  }
  locating.value = true
  navigator.geolocation.getCurrentPosition(position => {
    const latitude = Number(position.coords.latitude.toFixed(7))
    const longitude = Number(position.coords.longitude.toFixed(7))
    currentLocation.latitude = latitude
    currentLocation.longitude = longitude
    if (overwriteCoordinates || (form.value.latitude == null && form.value.longitude == null)) {
      form.value.latitude = latitude
      form.value.longitude = longitude
    }
    locating.value = false
  }, () => {
    locating.value = false
    toast.error(translate('pages.settings.geofence.locationFailed'))
  }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 })
}

function useCurrentLocation() {
  locateCurrentLocation(true)
}

async function confirmDelete() {
  if (!deleting.value) return
  loading.value = true
  try {
    if (isMarketer.value) await MarketerApi.delete(deleting.value.id)
    else await OfficeApi.delete(deleting.value.id)
    records.value = records.value.filter(record => record.id !== deleting.value?.id)
    deleteDialogOpen.value = false
    toast.success(`${singular.value[0].toUpperCase()}${singular.value.slice(1)} deleted.`)
  } catch (error: any) {
    toast.error(error.message || `Could not delete ${singular.value}.`)
  } finally {
    loading.value = false
  }
}

function assignedLabel(record: DirectoryRecord) {
  if (isMarketer.value)
    return `${record.customer_count ?? 0} customers · ${record.visitor_count ?? 0} visitors`
  return `${record.asset_count ?? 0} asset records`
}

onMounted(() => {
  load()
  loadAutomaticAttendance()
})
</script>

<template>
  <div>
    <VCard class="directory-hero mb-6" variant="tonal" color="primary">
      <VCardText class="d-flex flex-wrap align-center justify-space-between ga-5 pa-6">
        <div class="d-flex align-center ga-4">
          <VAvatar color="primary" variant="flat" size="52"><VIcon :icon="icon" size="27" /></VAvatar>
          <div><h2 class="text-h4 font-weight-bold">{{ title }}</h2><p class="mb-0 text-medium-emphasis">{{ subtitle }}</p></div>
        </div>
        <div class="d-flex ga-3">
      <VChip prepend-icon="tabler-circle-check" color="success" variant="flat">{{ $t('components.setup.directory.activeCount', { count: activeCount }) }}</VChip>
      <VChip prepend-icon="tabler-link" variant="outlined">{{ $t('components.setup.directory.assignmentCount', { count: assignmentCount }) }}</VChip>
        </div>
      </VCardText>
    </VCard>

    <VCard :loading="loading">
      <VCardText><VRow align="center">
        <VCol cols="12" md="5"><VTextField v-model="search" :label="$t('common.actions.search')" class="data-table-search" prepend-inner-icon="tabler-search" density="compact" hide-details clearable /></VCol>
      <VCol cols="12" sm="6" md="3"><VSelect v-model="statusFilter" :items="['active', 'inactive']" :item-title="translateBackendValue" :label="$t('common.fields.status')" density="compact" hide-details clearable /></VCol>
      <VCol cols="12" sm="6" md="4" class="d-flex justify-sm-end"><VBtn prepend-icon="tabler-plus" @click="openCreate">{{ $t('common.actions.addItem', { item: singular }) }}</VBtn></VCol>
      </VRow></VCardText>

      <ResponsiveDataTable :headers="headers" :items="filteredRecords" item-value="id" :loading="loading">
        <template #item.name="{ item }"><div class="d-flex align-center ga-3"><VAvatar color="primary" variant="tonal"><VIcon :icon="icon" /></VAvatar><div><div class="font-weight-bold">{{ item.name }}</div><div v-if="item.code" class="text-caption text-medium-emphasis">{{ item.code }}</div></div></div></template>
        <template #item.contact="{ item }"><div>{{ item.email || 'No email' }}</div><div class="text-caption text-medium-emphasis">{{ item.phone || 'No phone' }}</div></template>
      <template #item.details="{ item }"><span class="text-medium-emphasis">{{ isMarketer ? (item.notes || $t('common.messages.noNotes')) : (item.address || $t('common.messages.noAddress')) }}</span></template>
      <template #item.status="{ item }"><VChip size="small" :color="item.status === 'active' ? 'success' : 'secondary'" variant="tonal" class="text-capitalize">{{ translateBackendValue(item.status) }}</VChip></template>
        <template #item.assigned="{ item }">{{ assignedLabel(item) }}</template>
        <template #item.actions="{ item }"><FullDetailsButton :item="item" :title="item.name" /><IconBtn :aria-label="$t('common.actions.editItem', { item: singular })" @click="openEdit(item)"><VIcon icon="tabler-pencil" /></IconBtn><IconBtn :aria-label="$t('common.actions.deleteItem', { item: singular })" color="error" @click="deleting = item; deleteDialogOpen = true"><VIcon icon="tabler-trash" /></IconBtn></template>
        <template #no-data><div class="py-8 text-center text-medium-emphasis">{{ $t('common.messages.noMatchingRecords') }}</div></template>
      </ResponsiveDataTable>
    </VCard>

    <VDialog v-model="dialogOpen" max-width="1000">
      <VCard :title="$t(editing ? 'common.actions.editItem' : 'common.actions.addItem', { item: singular })">
        <VCardText><VForm class="app-form" @submit.prevent="save"><VRow>
          <VCol cols="12" :md="isMarketer ? 8 : 6"><VTextField v-model="form.name" :label="$t('common.fields.itemName', { item: singular })" autofocus required /></VCol>
          <VCol v-if="!isMarketer" cols="12" md="3"><VTextField v-model="form.code" :label="$t('pages.settings.directorycrudpage.officeCode')" required /></VCol>
          <VCol cols="12" :md="isMarketer ? 4 : 3"><VSelect v-model="form.status" :items="['active', 'inactive']" :item-title="translateBackendValue" :label="$t('common.fields.status')" /></VCol>
          <VCol cols="12" md="6"><VTextField v-model="form.email" :label="$t('common.fields.email')" type="email" /></VCol>
          <VCol cols="12" md="6"><VTextField v-model="form.phone" :label="$t('common.fields.phone')" /></VCol>
          <VCol cols="12"><VTextarea v-if="isMarketer" v-model="form.notes" :label="$t('common.fields.notes')" rows="3" /><VTextarea v-else v-model="form.address" :label="$t('common.fields.address')" rows="3" /></VCol>
          <template v-if="!isMarketer && automaticAttendanceEnabled">
            <VCol cols="12"><VDivider class="mb-4" /><div class="d-flex flex-wrap align-center justify-space-between ga-3"><div><h3 class="text-h6">{{ $t('pages.settings.geofence.title') }}</h3><p class="text-body-2 text-medium-emphasis mb-0">{{ $t('pages.settings.geofence.description') }}</p></div><VSwitch v-model="form.geofenceEnabled" color="primary" :label="$t('pages.settings.geofence.enableAutomaticAttendance')" hide-details /></div></VCol>
            <VCol cols="12">
              <OfficeGeofenceMap v-model:latitude="form.latitude" v-model:longitude="form.longitude" v-model:radius-meters="form.geofenceRadiusMeters" :current-latitude="currentLocation.latitude" :current-longitude="currentLocation.longitude" />
              <p class="text-caption text-medium-emphasis mt-2 mb-0">{{ $t('pages.settings.geofence.mapHint') }}</p>
            </VCol>
            <VCol cols="12" md="4"><VTextField v-model.number="form.latitude" type="number" step="0.0000001" :label="$t('pages.settings.geofence.latitude')" /></VCol>
            <VCol cols="12" md="4"><VTextField v-model.number="form.longitude" type="number" step="0.0000001" :label="$t('pages.settings.geofence.longitude')" /></VCol>
            <VCol cols="12" md="4"><VTextField v-model.number="form.geofenceRadiusMeters" type="number" min="10" max="5000" step="1" suffix="m" :label="$t('pages.settings.geofence.radiusMeters')" /></VCol>
            <VCol cols="12" md="8"><VSlider v-model="form.geofenceRadiusMeters" :min="10" :max="5000" :step="10" color="primary" thumb-label hide-details :label="$t('pages.settings.geofence.radiusMeters')" /></VCol>
            <VCol cols="12" md="4" class="d-flex align-center"><VBtn block variant="tonal" prepend-icon="tabler-current-location" :loading="locating" @click="useCurrentLocation">{{ $t('pages.settings.geofence.useCurrentLocation') }}</VBtn></VCol>
          </template>
        </VRow><div class="d-flex justify-end ga-3 mt-4"><VBtn variant="tonal" @click="dialogOpen = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn type="submit" :loading="loading">{{ $t('common.actions.save') }}</VBtn></div></VForm></VCardText>
      </VCard>
    </VDialog>

    <VDialog v-model="deleteDialogOpen" max-width="460"><VCard :title="$t('common.actions.deleteItem', { item: singular })"><VCardText>{{ $t('pages.settings.directory.deleteConfirmation', { name: deleting?.name }) }}</VCardText><VCardActions><VSpacer /><VBtn variant="tonal" @click="deleteDialogOpen = false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="error" :loading="loading" @click="confirmDelete">{{ $t('common.actions.delete') }}</VBtn></VCardActions></VCard></VDialog>
  </div>
</template>

<style scoped>
.directory-hero { border: 1px solid rgba(var(--v-theme-primary), .2); }
</style>
