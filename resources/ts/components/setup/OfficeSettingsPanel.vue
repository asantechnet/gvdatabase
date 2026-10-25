<script setup lang="ts">
import { translate } from '@/utils/localization'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import OfficeApi, { type Office, type OfficePayload } from '@/api/office'
import OfficeGeofenceMap from '@/components/setup/OfficeGeofenceMap.vue'
import { canAccess } from '@/utils/authorization'
import { useSettingsValidation } from '@/composables/useSettingsValidation'

const props = defineProps<{ automaticAttendanceEnabled: boolean }>()
const { t } = useI18n()
const toast = useToast()
const { inputProps, clear: clearErrors, capture: captureError } = useSettingsValidation()
const office = ref<Office | null>(null)
const loading = ref(false)
const saving = ref(false)
const locating = ref(false)
const location = reactive({ latitude: null as number | null, longitude: null as number | null })
const canManage = computed(() => canAccess('manage_offices'))
const form = reactive({
  name: '', code: '', email: '', phone: '', address: '', status: 'active' as 'active' | 'inactive',
  latitude: null as number | null, longitude: null as number | null, geofence_radius_meters: 100, geofence_enabled: false,
  paid_leave_days_per_year: null as number | null,
})
const geofenceReady = computed(() => form.latitude !== null && form.longitude !== null)
const profileComplete = computed(() => Boolean(form.name.trim() && form.code.trim() && form.address.trim()))
watch(() => ({ ...form }), (values, previous) => {
  for (const field of Object.keys(values) as (keyof typeof form)[]) {
    if (values[field] !== previous[field]) clearErrors(field)
  }
})

function apply(record: Office | null) {
  office.value = record
  Object.assign(form, record ? {
    name: record.name, code: record.code, email: record.email ?? '', phone: record.phone ?? '', address: record.address ?? '', status: record.status,
    latitude: record.latitude, longitude: record.longitude, geofence_radius_meters: record.geofence_radius_meters || 100, geofence_enabled: record.geofence_enabled,
    paid_leave_days_per_year: record.paid_leave_days_per_year ?? null,
  } : { name: '', code: '', email: '', phone: '', address: '', status: 'active', latitude: null, longitude: null, geofence_radius_meters: 100, geofence_enabled: false, paid_leave_days_per_year: null })
}
async function load() {
  loading.value = true
  try { apply((await OfficeApi.getAll())[0] ?? null) }
  catch { toast.error(translate('common.ui.unableToLoadTheOfficeProfile')) }
  finally { loading.value = false }
}
function locate() {
  if (!navigator.geolocation) { toast.error(t('pages.settings.geofence.notSupported')); return }
  if (locating.value || !canManage.value) return
  locating.value = true
  navigator.geolocation.getCurrentPosition(position => {
    if (position.coords.accuracy > 100) {
      locating.value = false
      toast.warning(t('components.account.geofence.inaccurate', { accuracy: Math.ceil(position.coords.accuracy) }))
      return
    }
    location.latitude = Number(position.coords.latitude.toFixed(7)); location.longitude = Number(position.coords.longitude.toFixed(7))
    form.latitude = location.latitude; form.longitude = location.longitude; locating.value = false
  }, () => { locating.value = false; toast.error(t('pages.settings.geofence.locationFailed')) }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 })
}
async function save() {
  if (saving.value || !canManage.value) return
  clearErrors()
  const errors: Record<string, string[]> = {}
  for (const field of ['name', 'code'] as const) {
    if (!form[field]?.trim()) errors[field] = ['validation.required']
  }
  if (form.geofence_enabled) {
    for (const field of ['latitude', 'longitude'] as const) {
      if (form[field] == null || String(form[field]).trim() === '') errors[field] = ['validation.required']
    }
  }
  if (Object.keys(errors).length) {
    await captureError({ errors }, translate('settingsValidation.saveFailed'))
    return
  }
  saving.value = true
  const payload: OfficePayload = { ...form, name: form.name.trim(), code: form.code.trim().toUpperCase(), email: form.email.trim() || null, phone: form.phone.trim() || null, address: form.address.trim() || null }
  try {
    apply(office.value ? await OfficeApi.update(office.value.id, payload, { quiet: true }) : await OfficeApi.create(payload, { quiet: true }))
    toast.success(translate('common.ui.officeProfileSaved'))
  } catch (error) { await captureError(error, translate('settingsValidation.saveFailed')) }
  finally { saving.value = false }
}
onMounted(load)
</script>

<template>
  <div class="office-settings">
    <VAlert v-if="!automaticAttendanceEnabled" type="info" variant="tonal" icon="tabler-clock" class="mb-5">
      {{ $t('common.ui.turnOnAutomaticAttendanceInOperationsToUseTheOfficeGeofenceYouCanStillKeepTheLocationReadyBelow') }}
    </VAlert>
    <VRow>
      <VCol cols="12" md="4">
        <VCard variant="tonal" color="primary" class="office-summary h-100">
          <VCardText class="pa-6">
            <VAvatar size="56" color="primary" variant="flat" class="mb-5"><VIcon icon="tabler-building-community" size="30" /></VAvatar>
            <div class="text-overline">{{ $t('common.ui.singleOfficeWorkspace') }}</div>
            <h2 class="text-h5 font-weight-bold mb-2">{{ form.name || $t('common.ui.yourOffice') }}</h2>
            <p class="text-body-2 text-medium-emphasis mb-5">{{ $t('common.ui.thisProfileIsSharedByStaffAssetsAttendanceReportsAndDocuments') }}</p>
            <VList bg-color="transparent" density="compact" class="pa-0">
              <VListItem prepend-icon="tabler-id" :title="form.code || $t('common.ui.officeCodePending')" />
              <VListItem prepend-icon="tabler-map-pin" :title="form.address || $t('common.ui.addressPending')" />
              <VListItem :prepend-icon="form.geofence_enabled ? 'tabler-shield-check' : 'tabler-shield-off'" :title="form.geofence_enabled ? $t('common.ui.geofenceEnabled') : $t('common.ui.geofenceDisabled')" />
            </VList>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="8">
        <VCard variant="outlined" :loading="loading">
          <VCardItem :title="$t('common.ui.officeProfile')" :subtitle="$t('common.ui.keepTheCompanySPhysicalLocationAndContactDetailsAccurate')" />
          <VCardText>
            <VRow>
              <VCol cols="12" sm="6"><VTextField v-model="form.name" v-bind="inputProps('name')" :disabled="!canManage" :label="$t('common.fields.name')" required /></VCol>
              <VCol cols="12" sm="6"><VTextField v-model="form.code" v-bind="inputProps('code')" :disabled="!canManage" :label="$t('pages.settings.directorycrudpage.officeCode')" required /></VCol>
              <VCol cols="12" sm="6"><VTextField v-model="form.email" v-bind="inputProps('email')" :disabled="!canManage" :label="$t('common.fields.email')" type="email" /></VCol>
              <VCol cols="12" sm="6"><VTextField v-model="form.phone" v-bind="inputProps('phone')" :disabled="!canManage" :label="$t('common.fields.phone')" /></VCol>
              <VCol cols="12"><VTextarea v-model="form.address" v-bind="inputProps('address')" :disabled="!canManage" :label="$t('common.fields.address')" rows="2" auto-grow /></VCol>
              <VCol cols="12" sm="6"><VSelect v-model="form.status" v-bind="inputProps('status')" :disabled="!canManage" :items="[{ title: $t('serviceContracts.active'), value: 'active' }, { title: $t('common.statuses.inactive'), value: 'inactive' }]" :label="$t('common.fields.status')" /></VCol>
              <VCol cols="12" sm="6"><VTextField v-model.number="form.paid_leave_days_per_year" v-bind="inputProps('paid_leave_days_per_year')" :disabled="!canManage" type="number" min="0" max="366" :label="$t('common.ui.paidLeaveDaysPerYear')" :hint="$t('common.ui.paidLeaveDaysPerYearHint')" persistent-hint /></VCol>
              <VCol cols="12" class="d-flex align-center"><VChip :color="profileComplete ? 'success' : 'warning'" variant="tonal" prepend-icon="tabler-list-check">{{ profileComplete ? $t('common.ui.profileComplete') : $t('common.ui.completeTheProfile') }}</VChip></VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12">
        <VCard variant="outlined">
          <VCardItem :title="$t('common.ui.attendanceGeofence')" :subtitle="$t('common.ui.setOneTrustedLocationForLocationAwareClockIns')" />
          <VCardText>
            <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4"><VSwitch v-model="form.geofence_enabled" v-bind="inputProps('geofence_enabled')" :disabled="!canManage || !automaticAttendanceEnabled" color="primary" :label="$t('common.ui.requireStaffToClockInFromThisOffice')" hide-details="auto" /><VBtn variant="tonal" prepend-icon="tabler-current-location" :disabled="!canManage" :loading="locating" @click="locate">{{ $t('common.ui.useMyCurrentLocation') }}</VBtn></div>
            <OfficeGeofenceMap :readonly="!canManage" v-model:latitude="form.latitude" v-model:longitude="form.longitude" v-model:radius-meters="form.geofence_radius_meters" :current-latitude="location.latitude" :current-longitude="location.longitude" />
            <VRow class="mt-2"><VCol cols="12" md="4"><VTextField v-model.number="form.latitude" v-bind="inputProps('latitude')" :disabled="!canManage" type="number" step="0.0000001" :label="$t('pages.settings.geofence.latitude')" /></VCol><VCol cols="12" md="4"><VTextField v-model.number="form.longitude" v-bind="inputProps('longitude')" :disabled="!canManage" type="number" step="0.0000001" :label="$t('pages.settings.geofence.longitude')" /></VCol><VCol cols="12" md="4"><VTextField v-model.number="form.geofence_radius_meters" v-bind="inputProps('geofence_radius_meters')" :disabled="!canManage" type="number" min="10" max="5000" suffix="m" :label="$t('pages.settings.geofence.radiusMeters')" /></VCol></VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
    <div class="d-flex justify-end mt-5"><VBtn color="primary" prepend-icon="tabler-device-floppy" :disabled="!canManage" :loading="saving" @click="save">{{ $t('common.ui.saveOfficeProfile') }}</VBtn></div>
  </div>
</template>
