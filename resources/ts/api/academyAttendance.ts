import api from './axios'
import type { AcademyClassRecord } from './academyClasses'

export type AcademyAttendanceStatus = 'present' | 'absent' | 'late' | 'excused'

export interface AcademyAttendanceCell {
  status: AcademyAttendanceStatus
  minutes: number | null
  note: string | null
}

export interface AcademyAttendanceRow {
  enrollment_id: number
  student_number: string | null
  name: string | null
  contact: string | null
  started_on: string | null
  ends_on: string | null
  status: string
  balance: number
  currency: { code: string; symbol: string | null; decimal_places: number } | null
  attendance: Record<string, AcademyAttendanceCell>
}

export interface AcademyAttendanceMonth {
  class: AcademyClassRecord
  month: string
  dates: string[]
  rows: AcademyAttendanceRow[]
}

export interface AcademyAttendanceWriteRow {
  enrollment_id: number
  date: string
  status: AcademyAttendanceStatus
  minutes?: number | null
  note?: string | null
  override?: boolean
}

export interface AcademyTeacherTimesheetRow {
  id: number
  teacher_id: number
  class_id: number
  class_name: string | null
  date: string
  status: 'present' | 'absent'
  starts_at: string | null
  ends_at: string | null
  minutes: number | null
  note: string | null
}

export interface AcademyTeacherMonth {
  teacher: { id: number; name: string }
  month: string
  dates: string[]
  rows: AcademyTeacherTimesheetRow[]
}

export interface AcademyTeacherWriteRow {
  teacher_id: number
  class_id: number
  date: string
  status: 'present' | 'absent'
  starts_at?: string | null
  ends_at?: string | null
  minutes?: number | null
  note?: string | null
}

export const AcademyAttendanceApi = {
  async getMonth(classId: number, month: string): Promise<AcademyAttendanceMonth> {
    return (await api.get('/academy/attendance', { params: { class_id: classId, month } })).data.data
  },

  async save(rows: AcademyAttendanceWriteRow[]): Promise<number> {
    return (await api.put('/academy/attendance', { rows })).data.data.saved
  },

  async getTeacherMonth(teacherId: number, month: string): Promise<AcademyTeacherMonth> {
    return (await api.get('/academy/teacher-timesheets', { params: { teacher_id: teacherId, month } })).data.data
  },

  async saveTeacher(rows: AcademyTeacherWriteRow[]): Promise<number> {
    return (await api.put('/academy/teacher-timesheets', { teacher_rows: rows })).data.data.saved
  },
}
