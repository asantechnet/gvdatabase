<script setup lang="ts">
import AttendanceGeofenceApi from '@/api/attendanceGeofence'
import { canAccess } from '@/utils/authorization'
import { useAutomaticAttendance } from '@/composables/useAutomaticAttendance'
import { useToast } from 'vue-toastification'

const { t } = useI18n()
const toast = useToast()
const { enabled, load: loadAutomaticAttendance } = useAutomaticAttendance()
let pollTimer: number | undefined
let requestInFlight = false
let locationErrorReported = false
let inaccurateReported = false
let geofenceUnavailableReported = false
let isInitialLocationCheck = true

function stop() {
  if (pollTimer !== undefined) window.clearInterval(pollTimer)
  pollTimer = undefined
}

async function processPosition(position: GeolocationPosition) {
  if (requestInFlight || document.visibilityState !== 'visible') return
  requestInFlight = true
  try {
    const result = await AttendanceGeofenceApi.observe(position)
    if (result.action === 'checked_in') {
      toast.success(t('components.account.geofence.checkedIn', { office: result.office.name }))
      window.dispatchEvent(new Event('workday-status-changed'))
    }
    else if (result.action === 'checked_out') {
      toast.info(t('components.account.geofence.checkedOut', { office: result.office.name }))
      window.dispatchEvent(new Event('workday-status-changed'))
    }
    else if (result.action === 'inaccurate' && !inaccurateReported) {
      inaccurateReported = true
      toast.warning(t('components.account.geofence.inaccurate', { accuracy: result.accuracy_meters }))
    }
    else if (result.action !== 'inaccurate') inaccurateReported = false
  }
  catch { /* The shared API notifier handles server failures. */ }
  finally { requestInFlight = false }
}

function locate() {
  if (requestInFlight || document.visibilityState !== 'visible') return
  navigator.geolocation.getCurrentPosition(position => {
    locationErrorReported = false
    isInitialLocationCheck = false
    processPosition(position)
  }, () => {
    // Permission guidance belongs to the initial post-login check. Repeating
    // it on every background poll is disruptive and gives the user no new
    // action to take.
    if (isInitialLocationCheck && !locationErrorReported) {
      locationErrorReported = true
      toast.warning(t('components.account.geofence.permissionRequired'))
    }
    isInitialLocationCheck = false
  }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 })
}

async function start() {
  if (!await loadAutomaticAttendance() || !enabled.value) return
  if (!canAccess('manage_own_workday') || !navigator.geolocation) return
  try {
    const configuration = await AttendanceGeofenceApi.configuration()
    if (!configuration.eligible) {
      if (configuration.reason === 'geofence_not_configured' && !geofenceUnavailableReported) {
        geofenceUnavailableReported = true
        toast.warning(t('backend.errors.office_geofence_unavailable'))
      }
      return
    }
    locate()
    pollTimer = window.setInterval(locate, 30_000)
  }
  catch { /* The shared API notifier handles server failures. */ }
}

function onVisibilityChange() {
  if (document.visibilityState === 'visible') locate()
}

onMounted(() => {
  start()
  document.addEventListener('visibilitychange', onVisibilityChange)
})
onBeforeUnmount(() => {
  stop()
  document.removeEventListener('visibilitychange', onVisibilityChange)
})
</script>

<template><span class="d-none" aria-hidden="true" /></template>
