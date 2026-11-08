import api from './axios'
import { backgroundReadConfig } from './backgroundRead'
import AttendanceGeofenceApi from './attendanceGeofence'
import { getAccessToken } from '@/utils/authStorage'

export type WorkdayState = 'disabled' | 'weekend' | 'unavailable' | 'not_started' | 'active' | 'awaiting_report' | 'completed' | 'blocked'

export interface DailyReport {
  id: number
  attendanceId: number
  reportDate: string
  summary: string
  achievements: string | null
  challenges: string | null
  nextDayPlan: string | null
  supportNeeded: string | null
  submittedAt: string
}

export interface WorkdayStatus {
  eligible: boolean
  needsCheckoutCorrection: boolean
  state: WorkdayState
  timezone: string
  attendance: null | {
    id: number
    date: string
    checkedInAt: string | null
    checkedOutAt: string | null
    checkInTime: string | null
    checkOutTime: string | null
    workedMinutes: number | null
    requiresCheckoutLocation: boolean
  }
  report: DailyReport | null
}

export interface EndWorkdayData {
  attendanceId?: number
  summary: string
  achievements?: string | null
  challenges?: string | null
  nextDayPlan?: string | null
  supportNeeded?: string | null
  latitude?: number
  longitude?: number
  accuracy_meters?: number
  observed_at?: number
}

export class WorkdayLocationError extends Error {
  constructor(public readonly reason: 'unsupported' | 'unavailable' | 'session_changed') {
    super(reason)
    this.name = 'WorkdayLocationError'
  }
}

/** A fresh high-accuracy fix, requested only after an explicit user action. */
function currentPosition(): Promise<GeolocationPosition> {
  if (!globalThis.isSecureContext || !navigator.geolocation)
    return Promise.reject(new WorkdayLocationError('unsupported'))

  return new Promise<GeolocationPosition>((resolve, reject) => {
    navigator.geolocation.getCurrentPosition(resolve,
      () => reject(new WorkdayLocationError('unavailable')),
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 })
  })
}

const WorkdayApi = {
  async status(quiet = false): Promise<WorkdayStatus> {
    const response = await api.get<{ data: WorkdayStatus }>('/workday/status', backgroundReadConfig({ background: quiet }))

    return response.data.data
  },
  async start(): Promise<WorkdayStatus> {
    // Establish the persistent browser cookie before a mutation, and obtain
    // location only after the employee explicitly requests a check-in.
    const accountToken = getAccessToken()
    const status = await this.status()
    const assertSameAccount = () => {
      if (getAccessToken() !== accountToken) throw new WorkdayLocationError('session_changed')
    }
    assertSameAccount()
    if (status.state === 'active') {
      const response = await api.post<{ data: WorkdayStatus }>('/workday/start')
      return response.data.data
    }
    const configuration = await AttendanceGeofenceApi.configuration()
    assertSameAccount()
    let location: Record<string, number> = {}
    if (configuration.office?.enabled) {
      const position = await currentPosition()
      location = {
        latitude: position.coords.latitude, longitude: position.coords.longitude,
        accuracy_meters: position.coords.accuracy, observed_at: position.timestamp,
      }
    }
    assertSameAccount()
    const response = await api.post<{ data: WorkdayStatus }>('/workday/start', location)

    return response.data.data
  },
  async end(data: EndWorkdayData, requiresLocation = false): Promise<WorkdayStatus> {
    // Checkout mirrors check-in: the fence that admitted the shift verifies
    // the release. Location is requested only after the explicit end click.
    let location: Record<string, number> = {}
    if (requiresLocation) {
      const accountToken = getAccessToken()
      const position = await currentPosition()
      if (getAccessToken() !== accountToken) throw new WorkdayLocationError('session_changed')
      location = {
        latitude: position.coords.latitude, longitude: position.coords.longitude,
        accuracy_meters: position.coords.accuracy, observed_at: position.timestamp,
      }
    }
    const response = await api.post<{ data: WorkdayStatus }>('/workday/end', { ...data, ...location })

    return response.data.data
  },
}

export default WorkdayApi
