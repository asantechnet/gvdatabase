import type { InternalAxiosRequestConfig } from 'axios'
import { translate } from '@/utils/localization'

type Rule =
  | 'required' | 'nullable' | 'present' | 'string' | 'email' | 'numeric' | 'integer' | 'boolean' | 'accepted' | 'array' | 'date'
  | `min:${number}` | `max:${number}` | `gt:${number}` | `lte:${number}` | `size:${number}` | `between:${number},${number}` | `digits:${number}` | `multiple_of:${number}`
  | `in:${string}` | `after_or_equal:${string}` | 'phone' | 'month' | 'url' | 'file' | 'image'

type Schema = Record<string, Rule[]>

const contracts: Record<string, Schema> = {
  'POST /auth/register': { name: ['required', 'string', 'max:255'], email: ['required', 'email', 'max:255'], password: ['required', 'string', 'min:8'], password_confirmation: ['required', 'string'], phone: ['required', 'string', 'max:20'], address: ['required', 'string'] },
  'POST /auth/login': { email: ['required', 'email'], password: ['required', 'string'], remember: ['boolean'], two_factor_code: ['digits:6'] },
  'POST /auth/forgot-password': { email: ['required', 'email'] },
  'POST /auth/reset-password': { token: ['required', 'string'], email: ['required', 'email'], password: ['required', 'string', 'min:8'], password_confirmation: ['required', 'string'] },
  'POST /auth/complete-invitation': { token: ['required', 'string'], email: ['required', 'email'], password: ['required', 'string', 'min:8'], password_confirmation: ['required', 'string'] },
  'POST /auth/refresh-token': { refresh_token: ['required', 'string'], remember: ['boolean'] },
  'POST /auth/email/verification-notification': { email: ['required', 'email'] },
  'PUT /auth/account': { name: ['string', 'max:255'], email: ['email', 'max:255'], phone: ['nullable', 'string', 'max:20'], address: ['nullable', 'string', 'max:2000'], organization: ['nullable', 'string', 'max:255'], state: ['nullable', 'string', 'max:255'], zip: ['nullable', 'string', 'max:30'], country: ['nullable', 'string', 'max:100'], language: ['nullable', 'string', 'max:100'], timezone: ['nullable', 'string', 'max:100'], currency: ['nullable', 'string', 'size:3'] },
  'POST /auth/account/avatar': { avatar: ['required', 'file', 'image', 'max:800'] },
  'PUT /auth/account/password': { current_password: ['required', 'string'], password: ['required', 'string', 'min:8'], password_confirmation: ['required', 'string'] },
  'PUT /auth/account/notifications': { 'notifications': ['required', 'array'], 'notifications.*.in_app': ['required', 'boolean'], 'notifications.*.email': ['required', 'boolean'] },
  'POST /auth/account/deactivation-requests': { current_password: ['required', 'string', 'max:1024'], reason: ['nullable', 'string', 'max:2000'] },
  'PATCH /account-deactivation-requests/:id/review': { decision: ['required', 'in:approve,reject'], decision_notes: ['nullable', 'string', 'max:2000'] },
  'POST /users/:id/reactivate': { decision_notes: ['nullable', 'string', 'max:2000'] },
  'POST /auth/account/two-factor/confirm': { code: ['required', 'digits:6'] },
  'DELETE /auth/account/two-factor': { password: ['required', 'string'] },
  'POST /auth/account/logout-other-devices': { password: ['required', 'string'] },
  'POST /employees': { 'name': ['required', 'string', 'min:2', 'max:255'], 'father_name': ['required', 'string', 'min:2', 'max:255'], 'contact': ['required', 'string', 'phone'], 'id_number': ['required', 'string', 'max:100'], 'bank_account_number': ['nullable', 'string', 'max:255'], 'guarantor_id_number': ['nullable', 'string', 'max:100'], 'guarantor_phone': ['nullable', 'string', 'max:30'], 'guarantor_address': ['nullable', 'string', 'max:2000'], 'guarantor_licence': ['nullable', 'string', 'max:255'], 'create_user': ['boolean'], 'user_id': ['nullable', 'integer'], 'email': ['nullable', 'email', 'max:255'], 'education_lvl': ['required', 'string', 'max:255'], 'position_id': ['required'], 'office_id': ['nullable', 'integer'], 'employment_type': ['required', 'in:salaried,commission'], 'salary': ['nullable', 'numeric', 'min:0'], 'commissions': ['nullable', 'array'], 'commissions.*.service_id': ['required', 'integer'], 'commissions.*.percentage': ['required', 'numeric', 'gt:0', 'lte:100'], 'addr': ['required', 'string', 'min:5'], 'join_date': ['required', 'date'] },
  'PUT /employees/:id': { 'name': ['string', 'min:2', 'max:255'], 'father_name': ['string', 'min:2', 'max:255'], 'contact': ['string', 'phone'], 'id_number': ['string', 'max:100'], 'bank_account_number': ['nullable', 'string', 'max:255'], 'guarantor_id_number': ['nullable', 'string', 'max:100'], 'guarantor_phone': ['nullable', 'string', 'max:30'], 'guarantor_address': ['nullable', 'string', 'max:2000'], 'guarantor_licence': ['nullable', 'string', 'max:255'], 'create_user': ['boolean'], 'user_id': ['nullable', 'integer'], 'email': ['nullable', 'email', 'max:255'], 'education_lvl': ['string', 'max:255'], 'position_id': [], 'office_id': ['nullable', 'integer'], 'employment_type': ['in:salaried,commission'], 'salary': ['nullable', 'numeric', 'min:0'], 'commissions': ['nullable', 'array'], 'commissions.*.service_id': ['required', 'integer'], 'commissions.*.percentage': ['required', 'numeric', 'gt:0', 'lte:100'], 'addr': ['string', 'min:5'], 'join_date': ['date'], 'contract_duration': ['string', 'max:255'] },
  'POST /positions': { title: ['required', 'string', 'max:255'], salary: ['required', 'numeric', 'min:0'] },
  'PUT /positions/:id': { title: ['string', 'max:255'], salary: ['numeric', 'min:0'] },
  'POST /marketers': { user_id: ['nullable', 'integer'], name: ['required', 'string', 'max:255'], email: ['nullable', 'email', 'max:255'], phone: ['nullable', 'string', 'max:50'] },
  'PUT /marketers/:id': { user_id: ['nullable', 'integer'], name: ['required', 'string', 'max:255'], email: ['nullable', 'email', 'max:255'], phone: ['nullable', 'string', 'max:50'] },
  'POST /offices': { name: ['required', 'string', 'max:255'], code: ['required', 'string', 'max:30'], address: ['nullable', 'string', 'max:2000'], latitude: ['nullable', 'numeric', 'between:-90,90'], longitude: ['nullable', 'numeric', 'between:-180,180'], geofence_radius_meters: ['required', 'integer', 'between:10,5000'], geofence_enabled: ['required', 'boolean'] },
  'PUT /offices/:id': { name: ['required', 'string', 'max:255'], code: ['required', 'string', 'max:30'], address: ['nullable', 'string', 'max:2000'], latitude: ['nullable', 'numeric', 'between:-90,90'], longitude: ['nullable', 'numeric', 'between:-180,180'], geofence_radius_meters: ['required', 'integer', 'between:10,5000'], geofence_enabled: ['required', 'boolean'] },
  'POST /users': { name: ['required', 'string', 'max:255'], email: ['required', 'email', 'max:255'], phone: ['required', 'string', 'max:20'], address: ['required', 'string', 'max:2000'], roles: ['required', 'array', 'min:1'], acc_status: ['required', 'in:active,inactive'] },
  'PUT /users/:id': { name: ['string', 'max:255'], email: ['email', 'max:255'], phone: ['string', 'max:20'], address: ['string', 'max:2000'], roles: ['array', 'min:1'], acc_status: ['in:active,inactive,deactivated'] },
  'PUT /users/:id/status': { acc_status: ['required', 'in:active,inactive'] },
  'POST /roles': { name: ['required', 'string', 'max:255'] },
  'PUT /roles/:id': { name: ['string', 'max:255'] },
  'PUT /roles/:id/permissions': { 'permissions': ['required', 'array'], 'permissions.*': ['string'] },

  'POST /expenses': { applicant_id: ['nullable', 'integer'], type: ['required', 'string', 'max:255'], description: ['required', 'string'], amount: ['required', 'numeric', 'min:0'], quantity: ['required', 'integer', 'min:1'], currency_id: ['required'], rate: ['required', 'numeric', 'gt:0'], date: ['required', 'date'] },
  'PUT /expenses/:id': { applicant_id: ['nullable', 'integer'], type: ['string', 'max:255'], description: ['string'], amount: ['numeric', 'min:0'], quantity: ['integer', 'min:1'], currency_id: [], rate: ['numeric', 'gt:0'], date: ['date'] },
  'POST /incomes': { applicant_id: ['nullable', 'integer'], invoice_id: ['nullable', 'integer'], type: ['required', 'in:Service,Product,Investment,Other'], description: ['nullable', 'string', 'max:65535'], amount: ['required', 'numeric', 'gt:0'], quantity: ['required', 'integer', 'min:1'], currency_id: ['required'], rate: ['required', 'numeric', 'gt:0'], date: ['required', 'date'] },
  'PUT /incomes/:id': { applicant_id: ['nullable', 'integer'], invoice_id: ['nullable', 'integer'], type: ['in:Service,Product,Investment,Other'], description: ['nullable', 'string', 'max:65535'], amount: ['numeric', 'gt:0'], quantity: ['integer', 'min:1'], currency_id: [], rate: ['numeric', 'gt:0'], date: ['date'] },
  'POST /currencies': { name: ['required', 'string', 'max:255'], code: ['required', 'string', 'max:5'], symbol: ['nullable', 'string', 'max:5'], rate: ['required', 'numeric', 'gt:0'], decimal_places: ['required', 'integer', 'between:0,6'], is_active: ['boolean'], is_base: ['boolean'] },
  'PUT /currencies/:id': { name: ['string', 'max:255'], code: ['string', 'max:5'], symbol: ['nullable', 'string', 'max:5'], rate: ['numeric', 'gt:0'], decimal_places: ['integer', 'between:0,6'], is_active: ['boolean'], is_base: ['boolean'] },
  'POST /services': { type: ['required', 'string', 'max:255'], category: ['string', 'in:general,scholarship,test_offer'], description: ['nullable', 'string'], fee: ['required', 'numeric', 'min:0'], status: ['required', 'string'], currency_id: ['required'] },
  'PUT /services/:id': { type: ['string', 'max:255'], category: ['string', 'in:general,scholarship,test_offer'], description: ['nullable', 'string'], fee: ['numeric', 'min:0'], status: ['string'], currency_id: [] },
  'POST /visitors': { name: ['required', 'string', 'max:255'], company: ['required', 'string', 'max:255'], contact: ['required', 'string', 'max:255'], service: ['required', 'string', 'max:255'], marketer: ['required', 'string', 'max:255'], date: ['required', 'date'] },
  'PUT /visitors/:id': { name: ['string', 'max:255'], company: ['string', 'max:255'], contact: ['string', 'max:255'], service: ['string', 'max:255'], marketer: ['string', 'max:255'], date: ['date'] },

  'POST /customers': { name: ['required', 'string', 'max:255'], company: ['required', 'string', 'max:255'], contact: ['required', 'string', 'max:255'], marketer: ['nullable', 'string', 'max:255'], date: ['required', 'date'] },
  'PUT /customers/:id': { name: ['string', 'max:255'], company: ['string', 'max:255'], contact: ['string', 'max:255'], marketer: ['string', 'max:255'], date: ['date'] },
  'PATCH /crm/customer/:id/notes': { notes: ['nullable', 'string', 'max:10000'] },
  'PATCH /crm/visitor/:id/notes': { notes: ['nullable', 'string', 'max:10000'] },
  'PATCH /crm/lead/:id/notes': { notes: ['nullable', 'string', 'max:10000'] },
  'POST /payment-details': { method: ['required', 'string', 'max:255'], instruction: ['required', 'string', 'max:5000'] },
  'PUT /payment-details/:id': { method: ['required', 'string', 'max:255'], instruction: ['required', 'string', 'max:5000'] },
  'POST /invoices': { 'applicant_id': ['required', 'integer'], 'currency_id': ['required', 'integer'], 'services': ['required', 'array', 'min:1'], 'services.*.contract_service_id': ['required', 'integer'], 'services.*.quantity': ['integer', 'min:1'], 'tax_percent': ['required', 'numeric', 'between:0,100'], 'discount': ['numeric', 'min:0'], 'notes': ['nullable', 'string'], 'payment_detail_id': ['nullable', 'integer'], 'payment_method': ['nullable', 'string', 'max:255'], 'payment_instructions': ['nullable', 'array'] },
  'POST /commission-settlements': { employee_id: ['required', 'integer'], base_currency_id: ['required', 'integer'], period_start: ['required', 'date'], period_end: ['required', 'date', 'after_or_equal:period_start'], accrual_ids: ['nullable', 'array'], 'accrual_ids.*': ['integer'], notes: ['nullable', 'string', 'max:2000'] },
  'POST /commissions/adjustments': { employee_id: ['required', 'integer'], base_currency_id: ['required', 'integer'], effective_date: ['required', 'date'], amount: ['required', 'numeric'], reason: ['required', 'string', 'min:5', 'max:2000'] },
  'POST /commission-settlements/:id/pay': { payment_date: ['required', 'date'] },
  'PUT /invoices/:id/void': { reason: ['required', 'string', 'max:2000'] },

  // Contract parties are now selected through entity IDs. The API derives the
  // legacy counterparty snapshot from the selected employee/customer/company.
  'POST /contracts': {
    template_id: ['nullable', 'integer'],
    contract_type_id: ['nullable', 'integer'],
    employee_id: ['nullable', 'integer'],
    customer_id: ['nullable', 'integer'],
    company_id: ['nullable', 'integer'],
    is_custom: ['boolean'],
    cover_text: ['nullable', 'string'],
    content: ['string'],
    status: ['required', 'in:draft,active,completed,terminated'],
    start_date: ['required', 'date'],
    company_signatory_name: ['nullable', 'string', 'max:255'],
  },
  'PUT /contracts/:id': {
    template_id: ['nullable', 'integer'],
    contract_type_id: ['nullable', 'integer'],
    employee_id: ['nullable', 'integer'],
    customer_id: ['nullable', 'integer'],
    company_id: ['nullable', 'integer'],
    is_custom: ['boolean'],
    cover_text: ['nullable', 'string'],
    content: ['string'],
    status: ['in:draft,active,completed,terminated'],
    start_date: ['date'],
    company_signatory_name: ['nullable', 'string', 'max:255'],
  },
  'POST /contract-templates': { 'name': ['required', 'string', 'max:255'], 'contract_type_id': ['required', 'integer'], 'service_id': ['nullable', 'integer'], 'position_id': ['nullable', 'integer'], 'policy_ids': ['nullable', 'array'], 'policy_ids.*': ['integer'], 'cover_text': ['nullable', 'string'], 'content': ['required', 'string'] },
  'PUT /contract-templates/:id': { 'name': ['string', 'max:255'], 'contract_type_id': ['integer'], 'service_id': ['nullable', 'integer'], 'position_id': ['nullable', 'integer'], 'policy_ids': ['nullable', 'array'], 'policy_ids.*': ['integer'], 'cover_text': ['nullable', 'string'], 'content': ['string'] },

  'GET /attendances': { start_date: ['date'], end_date: ['date', 'after_or_equal:start_date'], employee_id: ['integer'], office_id: ['integer'], status: ['in:present,absent,paid-leave,unpaid-leave,incomplete'] },
  'GET /attendances/unmarked-staff': { date: ['required', 'date'] },
  'POST /attendances': { staff_id: ['required', 'integer'], date: ['required', 'date'], status: ['required', 'in:present,absent'], check_in_time: ['nullable', 'string'], check_out_time: ['nullable', 'string'], break_minutes: ['nullable', 'integer', 'between:0,720'] },
  'POST /workday/end': { summary: ['required', 'string', 'max:5000'], achievements: ['nullable', 'string', 'max:5000'], challenges: ['nullable', 'string', 'max:5000'], nextDayPlan: ['nullable', 'string', 'max:5000'], supportNeeded: ['nullable', 'string', 'max:5000'] },
  'POST /workday/geofence': { latitude: ['required', 'numeric', 'between:-90,90'], longitude: ['required', 'numeric', 'between:-180,180'], accuracy_meters: ['required', 'numeric', 'between:0,5000'] },
  'POST /worthy-employees': { employeeId: ['required'], achievementDetails: ['required', 'string'], amount: ['required', 'numeric', 'min:0'], selectedType: ['required', 'in:Performance,Customer Appreciation,Task Completion,Work Anniversary,Manager Recognition'], bonusMonth: ['month'] },
  'PATCH /worthy-employees/:id': { amount: ['numeric', 'min:0'], selectedAmount: ['numeric', 'min:0'], selectedType: ['in:Performance,Customer Appreciation,Task Completion,Work Anniversary,Manager Recognition'], isGranted: ['boolean'] },
  'POST /salaries': { 'employee_id': ['required', 'integer'], 'currency_id': ['required', 'integer'], 'salaryMonth': ['required', 'month'], 'amountPaid': ['required', 'numeric', 'gt:0'], 'workedDays': ['required', 'numeric', 'between:0,31', 'multiple_of:0.5'], 'totalDays': ['required', 'integer', 'between:1,31'], 'remarks': ['nullable', 'string', 'max:255'], 'date': ['required', 'date'], 'deductions': ['present', 'array'], 'deductions.*.description': ['required', 'string', 'max:255'], 'deductions.*.amount': ['required', 'numeric', 'gt:0'] },
  'PUT /salaries/:id': { 'employee_id': ['required', 'integer'], 'currency_id': ['required', 'integer'], 'salaryMonth': ['required', 'month'], 'amountPaid': ['required', 'numeric', 'gt:0'], 'workedDays': ['required', 'numeric', 'between:0,31', 'multiple_of:0.5'], 'totalDays': ['required', 'integer', 'between:1,31'], 'remarks': ['nullable', 'string', 'max:255'], 'date': ['required', 'date'], 'deductions': ['present', 'array'], 'deductions.*.description': ['required', 'string', 'max:255'], 'deductions.*.amount': ['required', 'numeric', 'gt:0'] },
  'POST /salaries/:id/deductions': { description: ['required', 'string', 'max:255'], amount: ['required', 'numeric', 'gt:0'], sortOrder: ['integer', 'min:0'] },
  'PATCH /salaries/:id/deductions/:id': { description: ['required', 'string', 'max:255'], amount: ['required', 'numeric', 'gt:0'], sortOrder: ['integer', 'min:0'] },

  'POST /boards': { title: ['required', 'string', 'max:255'], order: ['integer'] },
  'PUT /boards/:id': { title: ['required', 'string', 'max:255'], order: ['integer'] },
  'PUT /boards/reorder': { board_ids: ['required', 'array'] },
  'PUT /boards/:id/members': { 'user_ids': ['required', 'array'], 'user_ids.*': ['integer'] },
  'POST /tasks': { board_id: ['required'], title: ['required', 'string', 'max:255'], description: ['nullable', 'string'], results: ['nullable', 'string'], priority: ['in:low,medium,high,urgent'], start_date: ['nullable', 'date'], due_date: ['nullable', 'date'] },
  'PUT /tasks/:id': { title: ['string', 'max:255'], description: ['nullable', 'string'], results: ['nullable', 'string'], priority: ['in:low,medium,high,urgent'], progress: ['integer', 'between:0,100'], start_date: ['nullable', 'date'], due_date: ['nullable', 'date'], dependencies: ['array'] },
  'POST /tasks/:id/attachments': { file: ['required', 'file', 'max:10240'], section: ['in:input,results'] },
  'POST /applicant-portal/attachments': { file: ['required', 'file', 'max:10240'], label: ['nullable', 'string', 'max:120'] },
  'PUT /tasks/:id/move': { board_id: ['required'], order: ['required', 'integer', 'min:0'] },
  'PATCH /tasks/:id/bonus-review': { quality_score: ['required', 'numeric', 'between:1,5'], rejected: ['boolean'], contributions: ['array'] },
  'POST /tasks/:id/comments': { content: ['required', 'string', 'max:2000'] },
  'PUT /tasks/:id/comments/:id': { content: ['required', 'string', 'max:2000'] },
  'POST /tasks/:id/subtasks': { title: ['required', 'string', 'max:255'], category: ['nullable', 'string', 'max:255'], is_completed: ['boolean'] },
  'PUT /tasks/:id/subtasks/:id': { title: ['string', 'max:255'], category: ['nullable', 'string', 'max:255'], is_completed: ['boolean'] },
  'POST /tags': { name: ['required', 'string', 'max:255'], color: ['required', 'string', 'max:255'] },
  'PUT /tags/:id': { name: ['string', 'max:255'], color: ['string', 'max:255'] },

  'POST /settings': { primary_color: ['nullable', 'string', 'max:20'], secondary_color: ['nullable', 'string', 'max:20'], success_color: ['nullable', 'string', 'max:20'], info_color: ['nullable', 'string', 'max:20'], warning_color: ['nullable', 'string', 'max:20'], error_color: ['nullable', 'string', 'max:20'], light_background_color: ['nullable', 'string', 'max:20'], light_surface_color: ['nullable', 'string', 'max:20'], dark_background_color: ['nullable', 'string', 'max:20'], dark_surface_color: ['nullable', 'string', 'max:20'], paid_leave_days_per_year: ['required', 'numeric', 'between:0,366'], website_title: ['required', 'string', 'max:255'], website_address: ['required', 'string', 'max:255'], phone: ['required', 'string', 'max:50'], physical_address: ['required', 'string', 'max:1000'], short_company_name: ['required', 'string', 'max:255'], original_company_name: ['required', 'string', 'max:255'], business_licence: ['nullable', 'string', 'max:255'], company_signature: [], office_open_time: ['nullable', 'string'], office_close_time: ['nullable', 'string'], default_break_minutes: ['required', 'integer', 'between:0,720'] },
  'PATCH /settings/:id': { primary_color: ['nullable', 'string', 'max:20'], secondary_color: ['nullable', 'string', 'max:20'], success_color: ['nullable', 'string', 'max:20'], info_color: ['nullable', 'string', 'max:20'], warning_color: ['nullable', 'string', 'max:20'], error_color: ['nullable', 'string', 'max:20'], light_background_color: ['nullable', 'string', 'max:20'], light_surface_color: ['nullable', 'string', 'max:20'], dark_background_color: ['nullable', 'string', 'max:20'], dark_surface_color: ['nullable', 'string', 'max:20'], paid_leave_days_per_year: ['numeric', 'between:0,366'], website_title: ['string', 'max:255'], website_address: ['string', 'max:255'], phone: ['string', 'max:50'], physical_address: ['string', 'max:1000'], short_company_name: ['string', 'max:255'], original_company_name: ['string', 'max:255'], business_licence: ['nullable', 'string', 'max:255'], company_signature: [], office_open_time: ['nullable', 'string'], office_close_time: ['nullable', 'string'], default_break_minutes: ['integer', 'between:0,720'] },
  'POST /employees/:id/paid-time-offs': { date: ['required', 'date'], reason: ['required', 'string', 'max:1000'] },
  'PUT /employees/:id/paid-time-offs/:id': { date: ['required', 'date'], reason: ['required', 'string', 'max:1000'] },
  'GET /paid-time-offs': { year: ['nullable', 'integer', 'between:2000,3000'] },
  'GET /my-paid-time-offs': { year: ['nullable', 'integer', 'between:2000,3000'] },
  'POST /my-paid-time-offs': { start_date: ['required', 'date'], end_date: ['required', 'date', 'after_or_equal:start_date'], reason: ['required', 'string', 'max:1000'] },
  'PATCH /paid-time-offs/grant': { 'ids': ['required', 'array', 'min:1'], 'ids.*': ['integer'] },
  'POST /permissions': { name: ['required', 'string', 'max:255'], category: ['required', 'string', 'max:255'] },
  'PUT /permissions/:id': { name: ['string', 'max:255'], category: ['string', 'max:255'] },
  'POST /notifications': { type: ['required', 'string', 'max:50'], notification_key: ['required', 'string', 'max:100'], data: ['nullable', 'array'], date_time: ['nullable', 'date'], employee_id: ['nullable', 'integer'], user_ids: ['array', 'min:1'], go_to_url: ['nullable', 'string', 'max:2048'] },
  'PUT /notifications/:id': { is_read: ['boolean'], type: ['string', 'max:50'], notification_key: ['string', 'max:100'], data: ['nullable', 'array'], date_time: ['nullable', 'date'], employee_id: ['nullable', 'integer'], user_ids: ['array', 'min:1'], go_to_url: ['nullable', 'string', 'max:2048'] },
  'POST /assets': { serial_number: ['required', 'string', 'max:255'], item: ['required', 'string', 'max:255'], quantity: ['required', 'integer', 'min:1'], price: ['required', 'numeric', 'min:0'], model: ['required', 'string', 'max:255'], condition: ['required', 'in:new,good,fair,poor,damaged'], office_id: ['required', 'integer'] },
  'PUT /assets/:id': { serial_number: ['string', 'max:255'], item: ['string', 'max:255'], quantity: ['integer', 'min:1'], price: ['numeric', 'min:0'], model: ['string', 'max:255'], condition: ['in:new,good,fair,poor,damaged'], office_id: ['integer'] },
  'POST /files/upload': { file: ['required'], folder: ['string', 'max:100'] },
  'DELETE /files/delete': { file_path: ['required', 'string'] },
  'GET /reports/invoices': { start_date: ['date'], end_date: ['date', 'after_or_equal:start_date'] },
  'PUT /automation/bonus-rules': { minimum_completed_tasks: ['required', 'integer', 'min:1'], amount_per_completed_task: ['required', 'numeric', 'min:0'], minimum_cases_won: ['required', 'integer', 'min:1'], amount_per_case_won: ['required', 'numeric', 'min:0'], task_quality_weighting_enabled: ['required', 'boolean'], maximum_monthly_bonus: ['required', 'numeric', 'min:0'], excluded_employee_ids: ['present', 'array'], excluded_office_ids: ['present', 'array'] },
  'GET /automation/runs': { job: ['nullable', 'string', 'max:80'], status: ['nullable', 'in:running,completed,failed'] },
  'GET /automation/analytics': { from: ['nullable', 'date'], to: ['nullable', 'date', 'after_or_equal:from'], year: ['nullable', 'integer', 'between:2000,3000'] },
  'GET /reports/expences': { start_date: ['date'], end_date: ['date', 'after_or_equal:start_date'] },
  'GET /reports/expenses': { start_date: ['date'], end_date: ['date', 'after_or_equal:start_date'] },
  'GET /reports/salaries': { start_date: ['date'], end_date: ['date', 'after_or_equal:start_date'] },
  'GET /reports/invoices/export': { start_date: ['date'], end_date: ['date', 'after_or_equal:start_date'] },
}

const fieldKeys: Record<string, string> = {
  addr: 'address',
  education_lvl: 'educationLevel',
  father_name: 'fatherName',
  join_date: 'joinDate',
  contract_duration: 'contractDuration',
  currency_id: 'currency',
  rate: 'exchangeRate',
  amountPaid: 'amountPaid',
  salaryMonth: 'salaryMonth',
  workedDays: 'workedDays',
  totalDays: 'totalDays',
  employee_id: 'employee',
  employeeId: 'employee',
  performanceScore: 'performanceScore',
  selectedType: 'bonusType',
  selectedAmount: 'bonusAmount',
  customer_id: 'customer',
  template_id: 'contractTemplate',
  contract_type_id: 'contractType',
  start_date: 'startDate',
  end_date: 'endDate',
  payment_instructions: 'paymentInstructions',
  two_factor_code: 'authenticatorCode',
  current_password: 'currentPassword',
  password_confirmation: 'passwordConfirmation',
  serial_number: 'serialNumber',
  file_path: 'filePath',
  go_to_url: 'link',
  staff_id: 'staffMember',
  board_id: 'board',
  assignee_ids: 'assignees',
  tag_ids: 'tags',
}

export const fieldLabel = (field: string) => {
  const leaf = field.split('.').filter(part => !/^\d+$/.test(part)).at(-1) ?? field
  const key = `common.fields.${fieldKeys[field] ?? fieldKeys[leaf] ?? leaf.replace(/_([a-z])/g, (_, character) => character.toUpperCase())}`
  const localized = translate(key)

  return localized.startsWith('[missing:') ? leaf.replace(/_/g, ' ').replace(/\b\w/g, character => character.toUpperCase()) : localized
}

const isBlank = (value: unknown) => value === undefined || value === null || value === ''

const valuesFor = (data: any, path: string, resolved = ''): Array<{ key: string; value: unknown }> => {
  const [part, ...rest] = path.split('.')
  if (!part)
    return [{ key: resolved, value: data }]
  if (part === '*') {
    if (!Array.isArray(data))
      return [{ key: `${resolved}.*`.replace(/^\./, ''), value: undefined }]

    return data.flatMap((value, index) => valuesFor(value, rest.join('.'), `${resolved}.${index}`.replace(/^\./, '')))
  }

  return valuesFor(data?.[part], rest.join('.'), `${resolved}.${part}`.replace(/^\./, ''))
}

const errorFor = (label: string, rule: Rule): string => {
  if (rule === 'required' || rule === 'present')
    return translate('backend.validation.required')
  if (rule === 'email')
    return translate('backend.validation.email')
  if (rule === 'date')
    return translate('backend.validation.date')
  if (rule === 'numeric')
    return translate('backend.validation.numeric')
  if (rule === 'integer')
    return translate('backend.validation.integer')
  if (rule === 'boolean' || rule.startsWith('in:'))
    return translate('backend.validation.option')
  if (rule === 'accepted')
    return translate('backend.validation.accepted')
  if (rule === 'array')
    return translate('backend.validation.array')
  if (rule === 'phone')
    return translate('backend.validation.phone')
  if (rule === 'month')
    return translate('backend.validation.month')
  if (rule === 'url')
    return translate('backend.validation.url')
  if (rule === 'file')
    return translate('backend.validation.file')
  if (rule === 'image')
    return translate('backend.validation.image')
  if (rule.startsWith('min:'))
    return translate('backend.validation.min', { value: rule.slice(4) })
  if (rule.startsWith('max:'))
    return translate('backend.validation.max', { value: rule.slice(4) })
  if (rule.startsWith('gt:'))
    return translate('backend.validation.gt', { value: rule.slice(3) })
  if (rule.startsWith('lte:'))
    return translate('backend.validation.max', { value: rule.slice(4) })
  if (rule.startsWith('size:'))
    return translate('backend.validation.size', { value: rule.slice(5) })
  if (rule.startsWith('between:')) {
    const [min, max] = rule.slice(8).split(',')

    return translate('backend.validation.between', { min, max })
  }
  if (rule.startsWith('digits:'))
    return translate('backend.validation.digits', { value: rule.slice(7) })
  if (rule.startsWith('after_or_equal:'))
    return translate('backend.validation.afterOrEqual', { field: fieldLabel(rule.slice(15)) })

  return translate('backend.validation.invalid')
}

const fails = (value: unknown, rule: Rule, data: Record<string, unknown>) => {
  if (rule === 'string')
    return typeof value !== 'string'
  if (rule === 'email')
    return typeof value !== 'string' || !/^[^\s@]+@[^\s@][^\s.@]*\.[^\s@]+$/.test(value)
  if (rule === 'numeric')
    return !Number.isFinite(Number(value))
  if (rule === 'integer')
    return !Number.isInteger(Number(value))
  if (rule === 'boolean')
    return ![true, false, 0, 1, '0', '1'].includes(value as never)
  if (rule === 'accepted')
    return ![true, 1, '1', 'on', 'yes'].includes(value as never)
  if (rule === 'array')
    return !Array.isArray(value)
  if (rule === 'date')
    return typeof value !== 'string' || Number.isNaN(Date.parse(value))
  if (rule === 'phone')
    return typeof value !== 'string' || !/^[\d\s\-()+]{8,}$/.test(value)
  if (rule === 'month')
    return typeof value !== 'string' || !/^\d{4}-(0[1-9]|1[0-2])$/.test(value)
  if (rule === 'url') {
    try {
      new URL(String(value))

      return false
    }
    catch { return true }
  }
  if (rule === 'file')
    return typeof File === 'undefined' || !(value instanceof File)
  if (rule === 'image')
    return typeof File === 'undefined' || !(value instanceof File) || !value.type.startsWith('image/')
  if (rule.startsWith('min:'))
    return typeof value === 'string' || Array.isArray(value) ? value.length < Number(rule.slice(4)) : Number(value) < Number(rule.slice(4))
  if (rule.startsWith('max:'))
    return typeof value === 'string' || Array.isArray(value) ? value.length > Number(rule.slice(4)) : Number(value) > Number(rule.slice(4))
  if (rule.startsWith('gt:'))
    return Number(value) <= Number(rule.slice(3))
  if (rule.startsWith('lte:'))
    return Number(value) > Number(rule.slice(4))
  if (rule.startsWith('size:'))
    return typeof value !== 'string' || value.length !== Number(rule.slice(5))
  if (rule.startsWith('between:')) {
    const [min, max] = rule.slice(8).split(',').map(Number)

    return Number(value) < min || Number(value) > max
  }
  if (rule.startsWith('digits:'))
    return !new RegExp(`^\\d{${rule.slice(7)}}$`).test(String(value))
  if (rule.startsWith('in:'))
    return !rule.slice(3).split(',').includes(String(value))
  if (rule.startsWith('after_or_equal:'))
    return String(value) < String(data[rule.slice(15)] ?? '')

  return false
}

const normalizePath = (url = '') => `/${url.replace(/^\/?api\/?/, '').replace(/^\//, '').split('?')[0]}`.replace(/\/\d+(?=\/|$)/g, '/:id')

const formDataToObject = (data: unknown): Record<string, unknown> => {
  if (data instanceof FormData)
    return Object.fromEntries(data.entries())
  if (typeof data === 'string') {
    try { return JSON.parse(data) }
    catch { return {} }
  }

  return (data && typeof data === 'object' ? data : {}) as Record<string, unknown>
}

export const validateApiRequest = (config: InternalAxiosRequestConfig): Record<string, string> => {
  const schema = contracts[`${(config.method ?? 'get').toUpperCase()} ${normalizePath(config.url)}`]
  if (!schema)
    return {}

  const data = formDataToObject(['get', 'delete'].includes((config.method ?? '').toLowerCase()) ? config.params ?? config.data : config.data)
  const errors: Record<string, string> = {}

  for (const [path, rules] of Object.entries(schema)) {
    for (const { key, value } of valuesFor(data, path)) {
      const label = fieldLabel(key.replace(/\.\d+\./g, '.'))
      if (rules.includes('present') && value === undefined) { errors[key] = errorFor(label, 'present'); continue }
      if (rules.includes('required') && isBlank(value)) { errors[key] = errorFor(label, 'required'); continue }
      if (isBlank(value) && rules.includes('nullable'))
        continue
      if (isBlank(value))
        continue
      const failedRule = rules.find(rule => !['required', 'nullable', 'present'].includes(rule) && fails(value, rule, data))
      if (failedRule)
        errors[key] = errorFor(label, failedRule)
    }
  }

  if ('password' in data && 'password_confirmation' in data && data.password !== data.password_confirmation)
    errors.password_confirmation = translate('backend.validation.confirmed')

  return errors
}
