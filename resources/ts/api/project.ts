import api from './axios'
import type { Service } from './services'

export type ProjectStatus = 'active' | 'on_hold' | 'completed' | 'archived'

export interface Project {
  id: number
  applicant_id: number
  applicant?: { id: number; name: string }
  name: string
  description: string | null
  status: ProjectStatus
  start_date: string | null
  due_date: string | null

  /** Explicit catalogue services covered by this delivery project. */
  services?: Service[]
  service_ids?: number[]
  boards?: Array<{ id: string; title: string; tasks?: unknown[] }>
}

export interface ProjectPayload {
  name: string
  applicant_id: number
  description?: string | null
  status?: ProjectStatus
  start_date?: string | null
  due_date?: string | null
  service_ids?: number[]
}

const ProjectApi = {
  async getAll(): Promise<Project[]> {
    return (await api.get<{ data: Project[] }>('/projects')).data.data
  },
  async getById(id: number): Promise<Project> {
    return (await api.get<{ data: Project }>(`/projects/${id}`)).data.data
  },
  async create(payload: ProjectPayload): Promise<Project> {
    return (await api.post<{ data: Project }>('/projects', payload)).data.data
  },
  async update(id: number, payload: Partial<ProjectPayload>): Promise<Project> {
    return (await api.put<{ data: Project }>(`/projects/${id}`, payload)).data.data
  },
  async delete(id: number): Promise<void> {
    await api.delete(`/projects/${id}`)
  },
}

export default ProjectApi
