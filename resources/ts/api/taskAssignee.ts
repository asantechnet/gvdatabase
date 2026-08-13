import api from './axios'

export interface TaskAssigneeOption {
  id: string
  name: string
  email: string
  avatar: string | null
}

const TaskAssigneeApi = {
  getAll: async (): Promise<TaskAssigneeOption[]> => (await api.get('/task-assignees')).data.data,
}

export default TaskAssigneeApi
