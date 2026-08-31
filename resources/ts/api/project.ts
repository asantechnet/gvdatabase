import api from './axios'
import type { Service } from './services'

export type ProjectStatus = 'active' | 'on_hold' | 'completed' | 'archived'

export interface Project {
  id: number
  name: string
  description: string | null
  status: ProjectStatus
  start_date: string | null
  due_date: string | null
  services?: Service[]
  boards?: Array<{ id: string; title: string }>
}

export interface ProjectPayload {
  name: string
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
