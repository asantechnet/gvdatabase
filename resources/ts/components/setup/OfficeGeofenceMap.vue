<script setup lang="ts">
import mapboxgl, { type GeoJSONSource, type Map as MapboxMap, type Marker } from 'mapbox-gl'
import 'mapbox-gl/dist/mapbox-gl.css'

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
let map: MapboxMap | null = null
let officeMarker: Marker | null = null
let radiusMarker: Marker | null = null
let currentMarker: Marker | null = null
let syncing = false

const fallback = { latitude: 34.5553, longitude: 69.2075 }

const center = () => ({
  latitude: props.latitude ?? props.currentLatitude ?? fallback.latitude,
  longitude: props.longitude ?? props.currentLongitude ?? fallback.longitude,
})

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

  return [longitude2 * 180 / Math.PI, latitude2 * 180 / Math.PI] as [number, number]
}

function circleFeature(latitude: number, longitude: number, radiusMeters: number) {
  const coordinates: [number, number][] = []
  for (let bearing = 0; bearing <= 360; bearing += 5)
    coordinates.push(destination(latitude, longitude, radiusMeters, bearing))

  return { type: 'Feature', properties: {}, geometry: { type: 'Polygon', coordinates: [coordinates] } }
}

function distanceMeters(latitudeA: number, longitudeA: number, latitudeB: number, longitudeB: number) {
  const earthRadius = 6371008.8
  const latitudeDelta = (latitudeB - latitudeA) * Math.PI / 180
  const longitudeDelta = (longitudeB - longitudeA) * Math.PI / 180

  const raw = Math.sin(latitudeDelta / 2) ** 2
    + Math.cos(latitudeA * Math.PI / 180) * Math.cos(latitudeB * Math.PI / 180) * Math.sin(longitudeDelta / 2) ** 2

  const a = Math.min(1, Math.max(0, raw))
  return Math.round(earthRadius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)))
}

function syncMap(fly = false) {
  if (!map || !officeMarker || !radiusMarker || syncing)
    return
  syncing = true

  const location = center()
  const radius = Math.min(5000, Math.max(10, Number(props.radiusMeters) || 100))

  officeMarker.setLngLat([location.longitude, location.latitude])
  radiusMarker.setLngLat(destination(location.latitude, location.longitude, radius, 90))

  const source = map.getSource('office-radius') as GeoJSONSource | undefined

  source?.setData(circleFeature(location.latitude, location.longitude, radius) as any)
  if (fly)
    map.easeTo({ center: [location.longitude, location.latitude], duration: 350 })

  if (props.currentLatitude != null && props.currentLongitude != null) {
    if (!currentMarker) {
      currentMarker = new mapboxgl.Marker({ color: '#00a76f', scale: 0.75 })
        .setPopup(new mapboxgl.Popup({ offset: 20 }).setText(t('pages.settings.geofence.currentLocation')))
        .addTo(map)
    }
    currentMarker.setLngLat([props.currentLongitude, props.currentLatitude])
  }
  syncing = false
}

onMounted(() => {
  if (!container.value)
    return
  const location = center()

  map = new mapboxgl.Map({
    container: container.value,
    center: [location.longitude, location.latitude],
    zoom: 15,
    attributionControl: true,
    style: {
      version: 8,
      sources: {
        osm: {
          type: 'raster',
          tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
          tileSize: 256,
          attribution: '© OpenStreetMap contributors',
          maxzoom: 19,
        },
      },
      layers: [{ id: 'osm', type: 'raster', source: 'osm' }],
    },
  })
  map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-right')

  officeMarker = new mapboxgl.Marker({ color: '#7367f0', draggable: !props.readonly }).setLngLat([location.longitude, location.latitude]).addTo(map)
  radiusMarker = new mapboxgl.Marker({ color: '#ff9f43', draggable: !props.readonly, scale: 0.7 }).setLngLat([location.longitude, location.latitude]).addTo(map)

  officeMarker.on('drag', () => {
    if (props.readonly) return
    const point = officeMarker!.getLngLat().wrap()

    emit('update:latitude', Number(point.lat.toFixed(7)))
    emit('update:longitude', Number(point.lng.toFixed(7)))
  })
  radiusMarker.on('drag', () => {
    if (props.readonly) return
    const point = radiusMarker!.getLngLat().wrap()
    const location = center()

    emit('update:radiusMeters', Math.min(5000, Math.max(10, distanceMeters(location.latitude, location.longitude, point.lat, point.lng))))
  })
  map.on('click', event => {
    if (props.readonly) return
    emit('update:latitude', Number(event.lngLat.lat.toFixed(7)))
    emit('update:longitude', Number(event.lngLat.wrap().lng.toFixed(7)))
  })
  map.on('load', () => {
    map!.addSource('office-radius', { type: 'geojson', data: circleFeature(location.latitude, location.longitude, props.radiusMeters) as any })
    map!.addLayer({ id: 'office-radius-fill', type: 'fill', source: 'office-radius', paint: { 'fill-color': '#7367f0', 'fill-opacity': 0.16 } })
    map!.addLayer({ id: 'office-radius-line', type: 'line', source: 'office-radius', paint: { 'line-color': '#7367f0', 'line-width': 2 } })
    syncMap()
  })
})

watch(() => props.readonly, value => {
  officeMarker?.setDraggable(!value)
  radiusMarker?.setDraggable(!value)
})
watch(() => [props.latitude, props.longitude, props.radiusMeters, props.currentLatitude, props.currentLongitude], () => syncMap(true))
onBeforeUnmount(() => {
  currentMarker?.remove()
  officeMarker?.remove()
  radiusMarker?.remove()
  map?.remove()
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
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  block-size: 360px;
  inline-size: 100%;
}
</style>
