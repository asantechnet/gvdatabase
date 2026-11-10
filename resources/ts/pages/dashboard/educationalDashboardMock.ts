import type { EducationalDashboard } from '@/api/educationalDashboard'

/**
 * Fictional, development-only data for visual review of the educational
 * dashboard. It deliberately mirrors the dashboard endpoint's response and
 * is loaded only behind `import.meta.env.DEV` in the page component.
 */
export const createEducationalDashboardMock = (): EducationalDashboard => ({
  operations: {
    active_cases: 86,
    unassigned_cases: 7,
    outstanding: [
      { currency: 'AFN', records: 34, amount: 1121000 },
    ],
    outstanding_future: [
      { currency: 'AFN', records: 9, amount: 245000 },
    ],
    admissions: { pending: 12, applied: 31, admitted: 24, rejected: 6, withdrawn: 3 },
    service_stages: [
      { service: 'scholarship', key: 'profile_review', name: 'Profile Review', is_terminal: false, count: 18 },
      { service: 'scholarship', key: 'documents_required', name: 'Documents Required', is_terminal: false, count: 14 },
      { service: 'private', key: 'university_shortlist', name: 'University Shortlist', is_terminal: false, count: 12 },
      { service: 'private', key: 'visa_preparation', name: 'Visa Preparation', is_terminal: false, count: 9 },
      { service: 'test_offer', key: 'test_booking', name: 'Test Booking', is_terminal: false, count: 7 },
      { service: 'academy', key: 'course_enrolment', name: 'Course Enrolment', is_terminal: false, count: 6 },
    ],
  },
  pipeline: {
    stages: [
      { id: 101, service: 'scholarship', key: 'initial_consultation', name: 'Initial Consultation', is_terminal: false, count: 17 },
      { id: 102, service: 'scholarship', key: 'documents_required', name: 'Documents Required Before Application', is_terminal: false, count: 13 },
      { id: 103, service: 'scholarship', key: 'university_shortlist', name: 'University and Program Shortlist Review', is_terminal: false, count: 11 },
      { id: 104, service: 'scholarship', key: 'application_preparation', name: 'Application Preparation', is_terminal: false, count: 15 },
      { id: 105, service: 'private', key: 'application_submitted', name: 'Application Submitted', is_terminal: false, count: 12 },
      { id: 106, service: 'private', key: 'offer_pending', name: 'Waiting for University Decision', is_terminal: false, count: 10 },
      { id: 107, service: 'private', key: 'visa_preparation', name: 'Visa Preparation and Interview Support', is_terminal: false, count: 8 },
      { id: 108, service: 'test_offer', key: 'test_booking', name: 'Language Test Booking', is_terminal: false, count: 6 },
      { id: 109, service: 'academy', key: 'course_enrolment', name: 'Academy Course Enrolment', is_terminal: false, count: 5 },
      { id: 110, service: 'scholarship', key: 'completed', name: 'Completed', is_terminal: true, count: 29 },
    ],
    totals: { active: 86, closed: 29, all: 115 },
  },
  financial: {
    income: [
      { currency: 'AFN', records: 50, amount: 1806000 },
    ],
    expenses: [
      { currency: 'AFN', records: 25, amount: 438500 },
    ],
    salaries: [
      { currency: 'AFN', records: 12, amount: 512000 },
    ],
    salaries_by_status: [
      { status: 'open', currency: 'AFN', records: 3, amount: 135000 },
      { status: 'partially_paid', currency: 'AFN', records: 2, amount: 65000 },
      { status: 'paid', currency: 'AFN', records: 7, amount: 312000 },
    ],
    salaries_payable: [
      { currency: 'AFN', records: 5, amount: 200000 },
    ],
    salaries_paid: [
      { currency: 'AFN', records: 7, amount: 312000 },
    ],
    pending_by_status: { draft: 9 },
  },
  sales: {
    contracts: { sold: 37, by_currency: [{ currency: 'AFN', sold: 37, revenue: 1620000 }] },
    scholarships: { sold: 16, by_currency: [{ currency: 'AFN', sold: 16, revenue: 945000 }] },
    test_offers: { sold: 8, by_currency: [{ currency: 'AFN', sold: 8, revenue: 236000 }] },
    academy: { sold: 7, by_currency: [{ currency: 'AFN', sold: 7, revenue: 91000 }] },
    private: { sold: 6, by_currency: [{ currency: 'AFN', sold: 6, revenue: 348000 }] },
  },
  staff: [
    { id: 201, name: 'Farzana Ahmadi', active_applicants: 22, sales_recorded: 11, contracts_sold: 9, bonuses_generated: 580 },
    { id: 202, name: 'Ahmad Wali Safi', active_applicants: 18, sales_recorded: 9, contracts_sold: 7, bonuses_generated: 440 },
    { id: 203, name: 'Mariam Rahimi', active_applicants: 15, sales_recorded: 8, contracts_sold: 6, bonuses_generated: 360 },
    { id: 204, name: 'Samiullah Noori', active_applicants: 12, sales_recorded: 6, contracts_sold: 5, bonuses_generated: 290 },
    { id: 205, name: 'Laila Hamdard', active_applicants: 9, sales_recorded: 5, contracts_sold: 4, bonuses_generated: 210 },
    { id: 206, name: 'Omid Karimi', active_applicants: 7, sales_recorded: 4, contracts_sold: 3, bonuses_generated: 160 },
    { id: 207, name: 'Zahra Mohammadi', active_applicants: 5, sales_recorded: 3, contracts_sold: 2, bonuses_generated: 120 },
    { id: 208, name: 'Haroon Sadiqi', active_applicants: 3, sales_recorded: 2, contracts_sold: 1, bonuses_generated: 70 },
  ],
  attention: {
    counts: { overdue_tasks: 8, stuck_applicants: 6, missing_documents: 11, contracts_requiring_attention: 5 },
    overdue_tasks: [{ id: 301, title: 'Follow up on conditional offer', subtitle: 'Fictional case · overdue', route: { name: 'applicant-case', params: { id: 901 } } }],
    stuck_applicants: [{ id: 302, title: 'Sahar Akbari', subtitle: 'Fictional case · idle 18 days', route: { name: 'applicant-case', params: { id: 902 } } }],
    missing_documents: [{ id: 303, title: 'Zubair Rahmani', subtitle: 'Fictional case · documents missing', route: { name: 'applicant-case', params: { id: 903 } } }],
    contracts_requiring_attention: [{ id: 304, title: 'EDU-2026-0048', subtitle: 'Fictional contract · ending soon', route: { name: 'applicant-case', params: { id: 904 } } }],
    pending_approvals: 9,
  },
  work: {
    tasks: { open: 43, due_today: 7, overdue: 8, unassigned: 5, completed: 39 },
    contracts: { total: 91, draft: 5, active: 68, completed: 14, terminated: 2, cancelled: 2, new_in_period: 17, service_lines: 74 },
  },
  countries: [
    { id: 401, name: 'Germany', active: 21, total: 28 },
    { id: 402, name: 'Türkiye', active: 17, total: 23 },
    { id: 403, name: 'Canada', active: 14, total: 19 },
    { id: 404, name: 'United Kingdom', active: 11, total: 15 },
    { id: 405, name: 'Australia', active: 9, total: 13 },
    { id: 406, name: 'Malaysia', active: 7, total: 10 },
    { id: 407, name: 'United Arab Emirates', active: 5, total: 8 },
    { id: 408, name: 'Hungary', active: 3, total: 6 },
  ],
  universities: [
    { id: 501, name: 'Technical University of Munich', applications: 18, in_progress: 8, admitted: 7 },
    { id: 502, name: 'Istanbul Aydın University', applications: 15, in_progress: 6, admitted: 6 },
    { id: 503, name: 'University of Windsor', applications: 13, in_progress: 5, admitted: 4 },
    { id: 504, name: 'University of Hertfordshire', applications: 11, in_progress: 4, admitted: 3 },
    { id: 505, name: 'Monash University Malaysia', applications: 9, in_progress: 3, admitted: 3 },
    { id: 506, name: 'University of Debrecen', applications: 8, in_progress: 4, admitted: 2 },
    { id: 507, name: 'Heriot-Watt University Dubai', applications: 6, in_progress: 2, admitted: 2 },
    { id: 508, name: 'University of Applied Sciences Europe', applications: 5, in_progress: 2, admitted: 1 },
  ],
})
