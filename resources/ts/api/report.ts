import api from './axios'

// Report API endpoints
const ReportApi = {
  // Generate invoice report
  generateInvoiceReport: (params?: { start_date?: string; end_date?: string }) =>
    api.get('/reports/invoices', { params }),

  // Generate expense report
  generateExpenceReport: (params?: { start_date?: string; end_date?: string }) =>
    api.get('/reports/expences', { params }),

  // Generate salary report
  generateSalaryReport: (params?: { start_date?: string; end_date?: string }) =>
    api.get('/reports/salaries', { params }),

  // Export invoice report
  exportInvoiceReport: (params?: { start_date?: string; end_date?: string }) =>
    api.get('/reports/invoices/export', { params, responseType: 'blob' }),
}

export default ReportApi