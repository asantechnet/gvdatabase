import api from './axios'

// Dashboard API endpoints
const DashboardApi = {
  // Get dashboard stats
  getStats: () => api.get('/dashboard/stats'),

  // Get chart data
  getChartData: () => api.get('/dashboard/chart-data'),
}

export default DashboardApi