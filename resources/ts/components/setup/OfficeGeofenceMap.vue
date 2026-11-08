<script setup lang="ts">
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import type { Circle, Map as LeafletMap, Marker as LeafletMarker, LeafletMouseEvent } from 'leaflet'

const props = defineProps<{
  readonly?: boolean
  latitude: number | null
  longitude: number | null
  radiusMeters: number
  currentLatitude?: number | null
  currentLongitude?: number | null
}>()

const emit = defineEmits<{
  'update:latitude': [value: number]
  'update:longitude': [value: number]
  'update:radiusMeters': [value: number]
}>()

const { t } = useI18n()

const container = ref<HTMLElement | null>(null)
let map: LeafletMap | null = null
let officeMarker: LeafletMarker | null = null
let radiusMarker: LeafletMarker | null = null
let radiusCircle: Circle | null = null
let currentMarker: LeafletMarker | null = null
let resizeObserver: ResizeObserver | null = null
let resizeFrame = 0
let dragging = false
let hasLocation = props.latitude != null && props.longitude != null

const fallback = { latitude: 34.5553, longitude: 69.2075 }
const MIN_RADIUS = 10
const MAX_RADIUS = 5000

function numeric(value: number | null | undefined, fallbackValue: number) {
  if (value === null || value === undefined || (value as unknown) === '')
    return fallbackValue

  const parsed = Number(value)

  return Number.isFinite(parsed) ? parsed : fallbackValue
}

function latitudeValue() {
  return Math.min(90, Math.max(-90, numeric(props.latitude, fallback.latitude)))
}

function longitudeValue() {
  return Math.min(180, Math.max(-180, numeric(props.longitude, fallback.longitude)))
}

function clampRadius(value: number) {
  return Math.min(MAX_RADIUS, Math.max(MIN_RADIUS, Math.round(numeric(value, 100))))
}

function round(value: number) {
  return Number(value.toFixed(7))
}

// The Settings tab finishes its card/layout transition just after this
// component mounts. Leaflet otherwise keeps the intermediate container size,
// which can leave the map blank or clipped.
function invalidateSize() {
  cancelAnimationFrame(resizeFrame)
  resizeFrame = requestAnimationFrame(() => {
    map?.invalidateSize({ animate: false })
    resizeFrame = requestAnimationFrame(() => map?.invalidateSize({ animate: false }))
  })
}

function destination(latitude: number, longitude: number, distanceMeters: number, bearingDegrees: number) {
  const earthRadius = 6371008.8
  const angular = distanceMeters / earthRadius
  const bearing = bearingDegrees * Math.PI / 180
  const latitude1 = latitude * Math.PI / 180
  const longitude1 = longitude * Math.PI / 180

  const latitude2 = Math.asin(Math.sin(latitude1) * Math.cos(angular)
    + Math.cos(latitude1) * Math.sin(angular) * Math.cos(bearing))

  const longitude2 = longitude1 + Math.atan2(
    Math.sin(bearing) * Math.sin(angular) * Math.cos(latitude1),
    Math.cos(angular) - Math.sin(latitude1) * Math.sin(latitude2),
  )

  return L.latLng(latitude2 * 180 / Math.PI, longitude2 * 180 / Math.PI)
}

function radiusEdge(radius: number) {
  return destination(latitudeValue(), longitudeValue(), radius, 90)
}

function pinIcon(className: string, size: number) {
  return L.divIcon({
    className: `office-geofence-pin ${className}`,
    html: '',
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
  })
}

function syncCurrentLocation() {
  if (!map)
    return

  if (props.currentLatitude == null || props.currentLongitude == null) {
    currentMarker?.remove()
    currentMarker = null

    return
  }

  const point = L.latLng(
    Math.min(90, Math.max(-90, numeric(props.currentLatitude, fallback.latitude))),
    Math.min(180, Math.max(-180, numeric(props.currentLongitude, fallback.longitude))),
  )

  if (!currentMarker) {
    currentMarker = L.marker(point, {
      icon: pinIcon('office-geofence-pin--current', 16),
      keyboard: false,
      zIndexOffset: 200,
    })
      .addTo(map)
      .bindPopup(t('pages.settings.geofence.currentLocation'))
  }
  else {
    currentMarker.setLatLng(point)
  }
}

function syncMap(pan = false) {
  if (!map)
    return

  const center = L.latLng(latitudeValue(), longitudeValue())
  const radius = clampRadius(props.radiusMeters)

  officeMarker?.setLatLng(center)
  radiusMarker?.setLatLng(radiusEdge(radius))
  radiusCircle?.setLatLng(center).setRadius(radius)
  syncCurrentLocation()

  // Re-center only when the office coordinates move, never while a marker is
  // being dragged or when only the radius changes.
  if (pan && !dragging && map.distance(map.getCenter(), center) > 1)
    map.panTo(center)
}

onMounted(() => {
  if (!container.value)
    return

  const center = L.latLng(latitudeValue(), longitudeValue())
  const radius = clampRadius(props.radiusMeters)

  map = L.map(container.value, {
    center,
    zoom: props.latitude != null && props.longitude != null ? 16 : 13,
    minZoom: 3,
    maxZoom: 19,
    zoomControl: false,
    worldCopyJump: true,
    scrollWheelZoom: false,
  })
  L.control.zoom({ position: 'topright' }).addTo(map)
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  }).addTo(map)

  radiusCircle = L.circle(center, {
    radius,
    color: '#7367f0',
    weight: 2,
    fillColor: '#7367f0',
    fillOpacity: 0.16,
    interactive: false,
  }).addTo(map)

  officeMarker = L.marker(center, {
    icon: pinIcon('office-geofence-pin--office', 22),
    draggable: !props.readonly,
    autoPan: true,
    keyboard: false,
    zIndexOffset: 300,
  }).addTo(map)

  radiusMarker = L.marker(radiusEdge(radius), {
    icon: pinIcon('office-geofence-pin--radius', 18),
    draggable: !props.readonly,
    autoPan: true,
    keyboard: false,
    zIndexOffset: 250,
  }).addTo(map)

  officeMarker.getElement()?.setAttribute('aria-label', t('pages.settings.geofence.officeMarker'))
  radiusMarker.getElement()?.setAttribute('aria-label', t('pages.settings.geofence.radiusMarker'))

  officeMarker.on('dragstart', () => {
    dragging = true
  })
  radiusMarker.on('dragstart', () => {
    dragging = true
  })
  officeMarker.on('dragend', () => {
    dragging = false
  })
  radiusMarker.on('dragend', () => {
    dragging = false
  })
  officeMarker.on('drag', () => {
    if (props.readonly || !officeMarker)
      return

    const point = officeMarker.getLatLng()

    emit('update:latitude', round(point.lat))
    emit('update:longitude', round(point.lng))
  })
  radiusMarker.on('drag', () => {
    if (props.readonly || !radiusMarker || !map)
      return

    const origin = L.latLng(latitudeValue(), longitudeValue())

    emit('update:radiusMeters', clampRadius(map.distance(origin, radiusMarker.getLatLng())))
  })
  map.on('click', (event: LeafletMouseEvent) => {
    if (props.readonly || !map)
      return

    emit('update:latitude', round(event.latlng.lat))
    emit('update:longitude', round(event.latlng.lng))

    // Picking a location from a wider view leaves the radius handle hidden
    // under the office pin, so close in enough to edit the boundary.
    if (map.getZoom() < 15)
      map.setView(event.latlng, 16)
  })

  resizeObserver = new ResizeObserver(invalidateSize)
  resizeObserver.observe(container.value)
  invalidateSize()
  window.setTimeout(invalidateSize, 200)
  syncCurrentLocation()
})

watch(() => props.readonly, value => {
  if (officeMarker)
    officeMarker.dragging?.[value ? 'disable' : 'enable']()
  if (radiusMarker)
    radiusMarker.dragging?.[value ? 'disable' : 'enable']()
})
watch(() => [props.latitude, props.longitude, props.radiusMeters, props.currentLatitude, props.currentLongitude], () => {
  const nextHasLocation = props.latitude != null && props.longitude != null
  const pickedFirstLocation = !hasLocation && nextHasLocation

  hasLocation = nextHasLocation
  syncMap(true)

  if (pickedFirstLocation && map && map.getZoom() < 16)
    map.setView([latitudeValue(), longitudeValue()], 16)
})

onBeforeUnmount(() => {
  cancelAnimationFrame(resizeFrame)
  resizeObserver?.disconnect()
  resizeObserver = null
  map?.remove()
  map = null
  currentMarker = null
  officeMarker = null
  radiusMarker = null
  radiusCircle = null
})
</script>

<template>
  <div
    ref="container"
    class="office-geofence-map"
    :aria-label="t('pages.settings.geofence.mapLabel')"
  />
</template>

<style scoped>
.office-geofence-map {
  position: relative;
  background: rgb(var(--v-theme-surface));
  block-size: 360px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  inline-size: 100%;
  min-block-size: 280px;
  overflow: hidden;
}

.office-geofence-map :deep(.office-geofence-pin) {
  border: 2px solid #fff;
  border-radius: 50%;
  box-shadow: 0 1px 4px rgb(0 0 0 / 35%);
  box-sizing: border-box;
}

.office-geofence-map :deep(.office-geofence-pin--office) {
  background: #7367f0;
}

.office-geofence-map :deep(.office-geofence-pin--radius) {
  background: #ff9f43;
}

.office-geofence-map :deep(.office-geofence-pin--current) {
  background: #00a76f;
}

.office-geofence-map :deep(.leaflet-container) {
  font: inherit;
}
</style>
