# API Service Layer Documentation

This directory contains the API service modules for connecting the Vue 3 frontend with the Laravel backend.

## Structure

```
api/
├── axios.ts          # Global Axios configuration with interceptors
├── index.ts          # Exports all API modules
├── auth.ts           # Authentication API
├── user.ts           # User management API
├── employee.ts       # Employee management API
├── position.ts       # Position management API
├── employeeSalary.ts # Employee salary API
├── invoice.ts        # Invoice management API
├── expence.ts        # Expense management API
├── currency.ts       # Currency management API
├── setting.ts        # Setting management API
├── policy.ts         # Policy management API
├── contractTemplate.ts # Contract template API
├── contract.ts       # Contract management API
├── notification.ts   # Notification API
├── dashboard.ts      # Dashboard API
├── report.ts         # Report generation API
└── file.ts           # File management API
```

## Setup

### 1. Environment Configuration

Add to your `.env` file:
```env
VITE_API_BASE_URL=http://your-backend-url/api
```

### 2. Using API Modules

#### Option A: Using the Composable (Recommended)

```typescript
// In your Vue component
import { useAppApi } from '@/composables/useAppApi'

export default {
  setup() {
    const { auth, employees, invoices } = useAppApi()
    
    // Example: Login
    const login = async () => {
      try {
        const response = await auth.login({
          email: 'user@example.com',
          password: 'password123'
        })
        console.log('Login successful:', response.data)
      } catch (error) {
        console.error('Login failed:', error)
      }
    }
    
    // Example: Get all employees
    const fetchEmployees = async () => {
      try {
        const response = await employees.getAll({ page: 1, perPage: 10 })
        console.log('Employees:', response.data)
      } catch (error) {
        console.error('Failed to fetch employees:', error)
      }
    }
    
    return { login, fetchEmployees }
  }
}
```

#### Option B: Using Global $api Property

```typescript
// In your Vue component
export default {
  methods: {
    async login() {
      try {
        const response = await this.$api.AuthApi.login({
          email: 'user@example.com',
          password: 'password123'
        })
        console.log('Login successful:', response.data)
      } catch (error) {
        console.error('Login failed:', error)
      }
    },
    
    async fetchEmployees() {
      try {
        const response = await this.$api.EmployeeApi.getAll({ page: 1, perPage: 10 })
        console.log('Employees:', response.data)
      } catch (error) {
        console.error('Failed to fetch employees:', error)
      }
    }
  }
}
```

#### Option C: Direct Import

```typescript
// In your Vue component or service
import { EmployeeApi, AuthApi } from '@/api'

export default {
  setup() {
    const fetchEmployees = async () => {
      try {
        const response = await EmployeeApi.getAll()
        console.log('Employees:', response.data)
      } catch (error) {
        console.error('Failed to fetch employees:', error)
      }
    }
    
    return { fetchEmployees }
  }
}
```

## API Modules Reference

### AuthApi
- `register(data)` - Register a new user
- `login(credentials)` - Login
- `logout()` - Logout
- `refreshToken(refreshToken)` - Refresh access token
- `me()` - Get current user

### UserApi
- `getAll(params?)` - Get all users
- `getById(id)` - Get single user
- `create(data)` - Create user
- `update(id, data)` - Update user
- `updateStatus(id, status)` - Update user status
- `delete(id)` - Delete user

### EmployeeApi
- `getAll(params?)` - Get all employees
- `getById(id)` - Get single employee
- `create(data)` - Create employee
- `update(id, data)` - Update employee
- `delete(id)` - Delete employee

### PositionApi
- `getAll(params?)` - Get all positions
- `getById(id)` - Get single position
- `create(data)` - Create position
- `update(id, data)` - Update position
- `delete(id)` - Delete position

### EmployeeSalaryApi
- `getAll(params?)` - Get all employee salaries
- `getById(id)` - Get single employee salary
- `create(data)` - Create employee salary
- `update(id, data)` - Update employee salary
- `delete(id)` - Delete employee salary

### InvoiceApi
- `getAll(params?)` - Get all invoices
- `getById(id)` - Get single invoice
- `create(data)` - Create invoice
- `update(id, data)` - Update invoice
- `delete(id)` - Delete invoice

### ExpenceApi
- `getAll(params?)` - Get all expenses
- `getById(id)` - Get single expense
- `create(data)` - Create expense
- `update(id, data)` - Update expense
- `delete(id)` - Delete expense

### CurrencyApi
- `getAll(params?)` - Get all currencies
- `getById(id)` - Get single currency
- `create(data)` - Create currency
- `update(id, data)` - Update currency
- `delete(id)` - Delete currency

### SettingApi
- `getAll(params?)` - Get all settings
- `getById(id)` - Get single setting
- `create(data)` - Create setting
- `update(id, data)` - Update setting
- `delete(id)` - Delete setting

### PolicyApi
- `getAll(params?)` - Get all policies
- `getById(id)` - Get single policy
- `create(data)` - Create policy
- `update(id, data)` - Update policy
- `delete(id)` - Delete policy
- `attachToContractTemplate(policyId, templateId)` - Attach policy to contract template
- `detachFromContractTemplate(policyId, templateId)` - Detach policy from contract template

### ContractTemplateApi
- `getAll(params?)` - Get all contract templates
- `getById(id)` - Get single contract template
- `create(data)` - Create contract template
- `update(id, data)` - Update contract template
- `delete(id)` - Delete contract template
- `attachPolicy(templateId, policyId)` - Attach policy to template
- `detachPolicy(templateId, policyId)` - Detach policy from template

### ContractApi
- `getAll(params?)` - Get all contracts
- `getById(id)` - Get single contract
- `create(data)` - Create contract
- `update(id, data)` - Update contract
- `delete(id)` - Delete contract
- `downloadContractFile(contractId)` - Download contract file

### NotificationApi
- `getAll(params?)` - Get all notifications
- `getById(id)` - Get single notification
- `create(data)` - Create notification
- `update(id, data)` - Update notification
- `delete(id)` - Delete notification

### DashboardApi
- `getStats()` - Get dashboard statistics
- `getChartData()` - Get chart data

### ReportApi
- `generateInvoiceReport(params?)` - Generate invoice report
- `generateExpenceReport(params?)` - Generate expense report
- `generateSalaryReport(params?)` - Generate salary report
- `exportInvoiceReport(params?)` - Export invoice report

### FileApi
- `upload(file, data?)` - Upload file
- `download(filePath)` - Download file
- `delete(filePath)` - Delete file
- `listFiles(params?)` - List files
- `viewFile(filePath)` - View file

## Authentication Flow

The Axios interceptor automatically:
1. Attaches the `Authorization: Bearer <token>` header to requests
2. Handles 401 errors by attempting to refresh the token
3. Redirects to login if token refresh fails

Tokens are stored in `localStorage` with keys:
- `access_token` - Current access token
- `refresh_token` - Refresh token

## Error Handling

All API calls return Axios responses. Handle errors like this:

```typescript
try {
  const response = await apiModule.someMethod()
  // Handle success
} catch (error) {
  if (error.response) {
    // The request was made and the server responded with a status code
    console.error('Error data:', error.response.data)
    console.error('Error status:', error.response.status)
  } else if (error.request) {
    // The request was made but no response was received
    console.error('No response received:', error.request)
  } else {
    // Something happened in setting up the request
    console.error('Request setup error:', error.message)
  }
}
```

## Response Structure

The Laravel backend typically returns responses in this format:

```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

For pagination:

```json
{
  "success": true,
  "data": {
    "data": [...],
    "current_page": 1,
    "per_page": 10,
    "total": 100,
    "last_page": 10
  }
}
```