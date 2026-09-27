<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import OfficeApi, { type Office, type OfficePayload } from '@/api/office'
import OfficeGeofenceMap from '@/components/setup/OfficeGeofenceMap.vue'
import { canAccess } from '@/utils/authorization'

const props = defineProps<{ automaticAttendanceEnabled: boolean }>()
const { t } = useI18n()
const toast = useToast()
const office = ref<Office | null>(null)
const loading = ref(false)
const saving = ref(false)
const locating = ref(false)
const location = reactive({ latitude: null as number | null, longitude: null as number | null })
const canManage = computed(() => canAccess('manage_offices'))
const form = reactive({
  name: '', code: '', email: '', phone: '', address: '', status: 'active' as 'active' | 'inactive',
  latitude: null as number | null, longitude: null as number | null, geofence_radius_meters: 100, geofence_enabled: false,
})
const geofenceReady = computed(() => form.latitude !== null && form.longitude !== null)
const profileComplete = computed(() => Boolean(form.name.trim() && form.code.trim() && form.address.trim()))

function apply(record: Office | null) {
  office.value = record
  Object.assign(form, record ? {
    name: record.name, code: record.code, email: record.email ?? '', phone: record.phone ?? '', address: record.address ?? '', status: record.status,
    latitude: record.latitude, longitude: record.longitude, geofence_radius_meters: record.geofence_radius_meters || 100, geofence_enabled: record.geofence_enabled,
  } : { name: '', code: '', email: '', phone: '', address: '', status: 'active', latitude: null, longitude: null, geofence_radius_meters: 100, geofence_enabled: false })
}
async function load() {
  loading.value = true
  try { apply((await OfficeApi.getAll())[0] ?? null) }
  catch { toast.error('Unable to load the office profile.') }
  finally { loading.value = false }
}
function locate() {
  if (!navigator.geolocation) { toast.error(t('pages.settings.geofence.notSupported')); return }
  locating.value = true
  navigator.geolocation.getCurrentPosition(position => {
    location.latitude = Number(position.coords.latitude.toFixed(7)); location.longitude = Number(position.coords.longitude.toFixed(7))
    form.latitude = location.latitude; form.longitude = location.longitude; locating.value = false
  }, () => { locating.value = false; toast.error(t('pages.settings.geofence.locationFailed')) }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 })
}
async function save() {
  if (!form.name.trim() || !form.code.trim()) { toast.error('Office name and code are required.'); return }
  if (form.geofence_enabled && !geofenceReady.value) { toast.error(t('pages.settings.geofence.coordinatesRequired')); return }
  saving.value = true
  const payload: OfficePayload = { ...form, name: form.name.trim(), code: form.code.trim().toUpperCase(), email: form.email.trim() || null, phone: form.phone.trim() || null, address: form.address.trim() || null }
  try {
    apply(office.value ? await OfficeApi.update(office.value.id, payload) : await OfficeApi.create(payload))
    toast.success('Office profile saved.')
  } catch (error: any) { toast.error(error.response?.data?.message || 'Unable to save the office profile.') }
  finally { saving.value = false }
}
watch(() => props.automaticAttendanceEnabled, enabled => { if (!enabled) form.geofence_enabled = false })
onMounted(load)
</script>

<template>
  <div class="office-settings">
    <VAlert v-if="!automaticAttendanceEnabled" type="info" variant="tonal" icon="tabler-clock" class="mb-5">
      Turn on automatic attendance in Operations to use the office geofence. You can still keep the location ready below.
    </VAlert>
    <VRow>
      <VCol cols="12" md="4">
        <VCard variant="tonal" color="primary" class="office-summary h-100">
          <VCardText class="pa-6">
            <VAvatar size="56" color="primary" variant="flat" class="mb-5"><VIcon icon="tabler-building-community" size="30" /></VAvatar>
            <div class="text-overline">Single office workspace</div>
            <h2 class="text-h5 font-weight-bold mb-2">{{ form.name || 'Your office' }}</h2>
            <p class="text-body-2 text-medium-emphasis mb-5">This profile is shared by staff, assets, attendance, reports, and documents.</p>
            <VList bg-color="transparent" density="compact" class="pa-0">
              <VListItem prepend-icon="tabler-id" :title="form.code || 'Office code pending'" />
              <VListItem prepend-icon="tabler-map-pin" :title="form.address || 'Address pending'" />
              <VListItem :prepend-icon="form.geofence_enabled ? 'tabler-shield-check' : 'tabler-shield-off'" :title="form.geofence_enabled ? 'Geofence enabled' : 'Geofence disabled'" />
            </VList>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="8">
        <VCard variant="outlined" :loading="loading">
          <VCardItem title="Office profile" subtitle="Keep the company’s physical location and contact details accurate." />
          <VCardText>
            <VRow>
              <VCol cols="12" sm="8"><VTextField v-model="form.name" :disabled="!canManage" :label="$t('common.fields.name')" required /></VCol>
              <VCol cols="12" sm="4"><VTextField v-model="form.code" :disabled="!canManage" label="Office code" required /></VCol>
              <VCol cols="12" sm="6"><VTextField v-model="form.email" :disabled="!canManage" :label="$t('common.fields.email')" type="email" /></VCol>
              <VCol cols="12" sm="6"><VTextField v-model="form.phone" :disabled="!canManage" :label="$t('common.fields.phone')" /></VCol>
              <VCol cols="12"><VTextarea v-model="form.address" :disabled="!canManage" :label="$t('common.fields.address')" rows="2" auto-grow /></VCol>
              <VCol cols="12" sm="6"><VSelect v-model="form.status" :disabled="!canManage" :items="[{ title: 'Active', value: 'active' }, { title: 'Inactive', value: 'inactive' }]" :label="$t('common.fields.status')" /></VCol>
              <VCol cols="12" sm="6" class="d-flex align-center"><VChip :color="profileComplete ? 'success' : 'warning'" variant="tonal" prepend-icon="tabler-list-check">{{ profileComplete ? 'Profile complete' : 'Complete the profile' }}</VChip></VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12">
        <VCard variant="outlined">
          <VCardItem title="Attendance geofence" subtitle="Set one trusted location for location-aware clock-ins." />
          <VCardText>
            <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4"><VSwitch v-model="form.geofence_enabled" :disabled="!canManage || !automaticAttendanceEnabled" color="primary" label="Require staff to clock in from this office" hide-details /><VBtn variant="tonal" prepend-icon="tabler-current-location" :disabled="!canManage" :loading="locating" @click="locate">Use my current location</VBtn></div>
            <OfficeGeofenceMap v-model:latitude="form.latitude" v-model:longitude="form.longitude" v-model:radius-meters="form.geofence_radius_meters" :current-latitude="location.latitude" :current-longitude="location.longitude" />
            <VRow class="mt-2"><VCol cols="12" md="4"><VTextField v-model.number="form.latitude" :disabled="!canManage" type="number" step="0.0000001" :label="$t('pages.settings.geofence.latitude')" /></VCol><VCol cols="12" md="4"><VTextField v-model.number="form.longitude" :disabled="!canManage" type="number" step="0.0000001" :label="$t('pages.settings.geofence.longitude')" /></VCol><VCol cols="12" md="4"><VTextField v-model.number="form.geofence_radius_meters" :disabled="!canManage" type="number" min="10" max="5000" suffix="m" :label="$t('pages.settings.geofence.radiusMeters')" /></VCol></VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
    <div class="d-flex justify-end mt-5"><VBtn color="primary" prepend-icon="tabler-device-floppy" :disabled="!canManage" :loading="saving" @click="save">Save office profile</VBtn></div>
  </div>
</template>
