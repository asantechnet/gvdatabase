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
}

export class WorkdayLocationError extends Error {
  constructor(public readonly reason: 'unsupported' | 'unavailable' | 'session_changed') {
    super(reason)
    this.name = 'WorkdayLocationError'
  }
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
      if (!globalThis.isSecureContext || !navigator.geolocation)
        throw new WorkdayLocationError('unsupported')
      const position = await new Promise<GeolocationPosition>((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve,
          () => reject(new WorkdayLocationError('unavailable')),
          { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 })
      })
      location = {
        latitude: position.coords.latitude, longitude: position.coords.longitude,
        accuracy_meters: position.coords.accuracy, observed_at: position.timestamp,
      }
    }
    assertSameAccount()
    const response = await api.post<{ data: WorkdayStatus }>('/workday/start', location)

    return response.data.data
  },
  async end(data: EndWorkdayData): Promise<WorkdayStatus> {
    const response = await api.post<{ data: WorkdayStatus }>('/workday/end', data)

    return response.data.data
  },
}

export default WorkdayApi
