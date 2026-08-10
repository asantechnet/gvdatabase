import api from "./axios";

export interface Attendance {
  id: number;
  staff_id: number;
  name: string;
  role: string;
  date: string;
  status: "present" | "absent";
  timestamp: string;
}

export interface UnmarkedStaff {
  id: number;
  name: string;
  position: string;
}

export interface AttendanceParams {
  start_date?: string;
  end_date?: string;
}

export interface CreateAttendanceData {
  staff_id: number;
  date: string;
  status: "present" | "absent";
}

// Attendance API endpoints
const AttendanceApi = {
  // Get all attendance records
  getAll: async (params?: AttendanceParams) => {
    try {
      const response = await api.get<{ data: Attendance[] }>("/attendances", {
        params,
      });
      return response;
    } catch (error) {
      console.error("API getAll error:", error);
      throw error;
    }
  },

  // Get unmarked staff for a specific date
  getUnmarkedStaff: async (date: string) => {
    try {
      const response = await api.get<{ data: UnmarkedStaff[] }>(
        `/attendances/unmarked-staff?date=${date}`,
      );
      return response;
    } catch (error) {
      console.error("API getUnmarkedStaff error:", error);
      throw error;
    }
  },

  // Create attendance record
  create: async (data: CreateAttendanceData) => {
    console.log("data", data);
    try {
      const response = await api.post<{ data: Attendance }>(
        "/attendances",
        data,
      );
      return response;
    } catch (error) {
      console.error("API create error:", error);
      throw error;
    }
  },

  // Delete attendance record
  delete: async (id: number) => {
    try {
      const response = await api.delete<{ success: boolean; message: string }>(
        `/attendances/${id}`,
      );
      return response;
    } catch (error) {
      console.error("API delete error:", error);
      throw error;
    }
  },
};

export default AttendanceApi;
export type {
  Attendance,
  AttendanceParams,
  CreateAttendanceData,
  UnmarkedStaff,
};
