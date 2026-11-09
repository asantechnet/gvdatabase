import api from './axios'
import type { AcademyClassRecord } from './academyClasses'

export interface AcademyOverview {
  active_students: number
  active_enrollments: number
  classes: number
  seats_filled: number
  capacity: number
  ending_soon: number
  outstanding: number
  completed_this_month: number
  today_classes: AcademyClassRecord[]
}

export const AcademyOverviewApi = {
  async get(): Promise<AcademyOverview> {
    return (await api.get('/academy/overview')).data.data
  },
}
