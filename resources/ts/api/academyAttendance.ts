import api from './axios'
import type { AcademyClassRecord } from './academyClasses'

export type AcademyAttendanceStatus = 'present' | 'absent' | 'leave'
export interface AcademyAttendanceCell { status: AcademyAttendanceStatus; check_in_time: string | null; check_out_time: string | null }
export interface AcademyAttendanceRow {
  enrollment_id: number; student_number: string | null; name: string | null; contact: string | null
  started_on: string | null; ends_on: string | null; status: string
  attendance: Record<string, AcademyAttendanceCell>
}
export interface AcademyAttendanceMonth { class: AcademyClassRecord; month: string; dates: string[]; rows: AcademyAttendanceRow[] }
export interface AcademyAttendanceWriteRow {
  enrollment_id: number; date: string; status: AcademyAttendanceStatus
  check_in_time?: string | null; check_out_time?: string | null
}

export const AcademyAttendanceApi = {
  async getMonth(classId: number, month: string): Promise<AcademyAttendanceMonth> {
    return (await api.get('/academy/attendance', { params: { class_id: classId, month } })).data.data
  },
  async save(rows: AcademyAttendanceWriteRow[]): Promise<number> {
    return (await api.put('/academy/attendance', { rows })).data.data.saved
  },
}
