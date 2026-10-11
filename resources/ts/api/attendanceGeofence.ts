import api from './axios'

export interface GeofenceOffice {
  id: number
  name: string
  latitude: number | null
  longitude: number | null
  radius_meters: number
  enabled: boolean
}

export interface GeofenceConfiguration {
  eligible: boolean
  reason: 'no_employee' | 'no_office' | 'geofence_not_configured' | 'inactive_employee' | 'automatic_attendance_disabled' | null
  timezone: string
  office: GeofenceOffice | null
}

export interface GeofenceObservation {
  action: 'inside' | 'outside' | 'boundary' | 'inaccurate'
  inside: boolean
  distance_meters: number
  accuracy_meters: number
  exit_confirmation_seconds_remaining: number
  office: GeofenceOffice
  attendance: { id: number; date: string; checked_in_at: string | null; checked_out_at: string | null } | null
}

const AttendanceGeofenceApi = {
  async configuration(): Promise<GeofenceConfiguration> {
    const response = await api.get<{ data: GeofenceConfiguration }>('/workday/geofence')

    return response.data.data
  },
  async observe(position: GeolocationPosition): Promise<GeofenceObservation> {
    const response = await api.post<{ data: GeofenceObservation }>('/workday/geofence', {
      latitude: position.coords.latitude,
      longitude: position.coords.longitude,
      accuracy_meters: position.coords.accuracy,
    }, { headers: { 'X-Suppress-Error-Notification': 'true' } })

    return response.data.data
  },
}

export default AttendanceGeofenceApi
