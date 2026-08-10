import api from './axios'

// File Management API endpoints
const FileApi = {
  // Upload file
  upload: (file: File, data?: any) => {
    const formData = new FormData()
    formData.append('file', file)
    if (data) {
      Object.keys(data).forEach(key => {
        formData.append(key, data[key])
      })
    }
    return api.post('/files/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  // Download file
  download: (filePath: string) =>
    api.get(`/files/download/${filePath}`, { responseType: 'blob' }),

  // Delete file
  delete: (filePath: string) => api.delete('/files/delete', { data: { filePath } }),

  // List files
  listFiles: (params?: { path?: string }) => api.get('/files/list', { params }),

  // View file
  viewFile: (filePath: string) => api.get(`/files/view/${filePath}`),
}

export default FileApi