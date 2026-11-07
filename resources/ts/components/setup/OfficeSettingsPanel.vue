<script setup lang="ts">
import { translate } from '@/utils/localization'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import OfficeApi, { type Office, type OfficePayload } from '@/api/office'
import OfficeGeofenceMap from '@/components/setup/OfficeGeofenceMap.vue'
import ErpField from '@/components/erp/ErpField.vue'
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
})
const geofenceReady = computed(() => form.latitude !== null && form.longitude !== null)
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
  } : { name: '', code: '', email: '', phone: '', address: '', status: 'active', latitude: null, longitude: null, geofence_radius_meters: 100, geofence_enabled: false })
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
    <VRow>
      <VCol cols="12">
        <VCard variant="outlined" :loading="loading" class="office-geofence-card">
          <VCardItem :title="$t('common.ui.attendanceGeofence')" :subtitle="$t('common.ui.setOneTrustedLocationForLocationAwareClockIns')" />
          <VCardText>
            <section class="office-geofence-controls mb-4">
              <VSwitch v-model="form.geofence_enabled" v-bind="inputProps('geofence_enabled')" :disabled="!canManage || !automaticAttendanceEnabled" color="primary" :label="$t('common.ui.requireStaffToClockInFromThisOffice')" hide-details="auto" />
              <VBtn variant="tonal" color="primary" prepend-icon="tabler-current-location" :disabled="!canManage" :loading="locating" @click="locate">{{ $t('common.ui.useMyCurrentLocation') }}</VBtn>
            </section>
            <OfficeGeofenceMap :readonly="!canManage" v-model:latitude="form.latitude" v-model:longitude="form.longitude" v-model:radius-meters="form.geofence_radius_meters" :current-latitude="location.latitude" :current-longitude="location.longitude" />
            <VRow class="mt-2">
              <VCol cols="12" md="4"><ErpField v-model.number="form.latitude" v-bind="inputProps('latitude')" :disabled="!canManage" type="number" step="0.0000001" :label="$t('pages.settings.geofence.latitude')" placeholder="34.5553000" /></VCol>
              <VCol cols="12" md="4"><ErpField v-model.number="form.longitude" v-bind="inputProps('longitude')" :disabled="!canManage" type="number" step="0.0000001" :label="$t('pages.settings.geofence.longitude')" placeholder="69.2075000" /></VCol>
              <VCol cols="12" md="4"><ErpField v-model.number="form.geofence_radius_meters" v-bind="inputProps('geofence_radius_meters')" :disabled="!canManage" type="number" min="10" max="5000" suffix="m" :label="$t('pages.settings.geofence.radiusMeters')" placeholder="100" /></VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
    <div class="d-flex justify-end mt-5"><VBtn color="primary" prepend-icon="tabler-device-floppy" :disabled="!canManage" :loading="saving" @click="save">{{ $t('common.ui.saveOfficeProfile') }}</VBtn></div>
  </div>
</template>

<style scoped>
.office-geofence-card {
  overflow: hidden;
}

.office-geofence-controls {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

@media (max-width: 600px) {
  .office-geofence-controls {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
