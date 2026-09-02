import api from './axios'
import type { CustomerService } from './customer'

export type ProjectStatus = 'active' | 'on_hold' | 'completed' | 'archived'

export interface Project {
  id: number
  customer_id: number
  customer?: { id: number; name: string; company: string }
  name: string
  description: string | null
  status: ProjectStatus
  start_date: string | null
  due_date: string | null
  services?: CustomerService[]
  boards?: Array<{ id: string; title: string; tasks?: unknown[] }>
}

export interface ProjectPayload {
  name: string
  customer_id: number
  description?: string | null
  status?: ProjectStatus
  start_date?: string | null
  due_date?: string | null
}

const ProjectApi = {
  async getAll(): Promise<Project[]> {
    return (await api.get<{ data: Project[] }>('/projects')).data.data
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
