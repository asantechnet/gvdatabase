import api from './axios'

export interface GeofenceOffice {
  id: number
  name: string
  latitude: number
  longitude: number
  radius_meters: number
  enabled: boolean
}

export interface GeofenceConfiguration {
  eligible: boolean
  reason: 'no_employee' | 'no_office' | 'geofence_not_configured' | null
  timezone: string
  office: GeofenceOffice | null
}

export interface GeofenceObservation {
  action: 'checked_in' | 'inside' | 'outside' | 'boundary' | 'inaccurate' | 'exit_pending' | 'checked_out' | 'already_completed' | 'blocked'
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
    })
    return response.data.data
  },
}

export default AttendanceGeofenceApi
