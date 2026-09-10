<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAppApi } from '@/composables/useAppApi'

const { auth, employees, dashboard, files } = useAppApi()
const router = useRouter()

// State
const users = ref([])
const stats = ref(null)
const loading = ref(false)
const error = ref<string | null>(null)

// Methods
const fetchData = async () => {
  try {
    loading.value = true
    error.value = null

    // Fetch dashboard stats
    const statsResponse = await dashboard.getStats()

    stats.value = statsResponse.data.data

    // Fetch employees
    const employeesResponse = await employees.getAll({ page: 1, perPage: 5 })

    users.value = employeesResponse.data.data.data
  }
  catch (err) {
    console.error('Error fetching data:', err)
    error.value = 'Failed to fetch data. Please try again.'
  }
  finally {
    loading.value = false
  }
}

const handleLogin = async () => {
  try {
    const response = await auth.login({
      email: 'admin@example.com',
      password: 'password123',
    })

    // Store tokens (handled automatically by interceptors)
    localStorage.setItem('access_token', response.data.data.access_token)
    localStorage.setItem('refresh_token', response.data.data.refresh_token)

    // Redirect or update state
    await router.push('/dashboard')
    await fetchData()
  }
  catch (err) {
    console.error('Login failed:', err)
    error.value = 'Login failed. Please check your credentials.'
  }
}

const handleLogout = async () => {
  try {
    await auth.logout()
    localStorage.clear()
    await router.push('/login')
  }
  catch (err) {
    console.error('Logout failed:', err)
  }
}

const handleFileUpload = async (event: Event) => {
  const input = event.target as HTMLInputElement
  if (input.files && input.files[0]) {
    try {
      const response = await files.upload(input.files[0], {
        description: 'Uploaded via API example',
      })

      console.log('File uploaded:', response.data)
      alert('File uploaded successfully!')
    }
    catch (err) {
      console.error('Upload failed:', err)
      error.value = 'File upload failed.'
    }
  }
}

// Lifecycle
onMounted(() => {
  fetchData()
})
</script>

<template>
  <div class="api-example">
    <h1>API Usage Example</h1>

    <div
      v-if="loading"
      class="loading"
    >
      Loading...
    </div>

    <div
      v-if="error"
      class="error"
    >
      {{ error }}
    </div>

    <div
      v-if="stats"
      class="stats"
    >
      <h2>Dashboard Stats</h2>
      <pre>{{ stats }}</pre>
    </div>

    <div
      v-if="users.length"
      class="users"
    >
      <h2>Employees (First 5)</h2>
      <ul>
        <li
          v-for="user in users"
          :key="user.id"
        >
          {{ user.name }} ({{ user.email }})
        </li>
      </ul>
    </div>

    <div class="actions">
      <h2>Actions</h2>
      <button @click="handleLogin">
        Test Login
      </button>
      <button @click="handleLogout">
        Logout
      </button>
      <input
        type="file"
        @change="handleFileUpload"
      >
    </div>
  </div>
</template>

<style scoped>
.api-example {
  padding: 20px;
  max-width: 800px;
  margin: 0 auto;
}

.loading, .error {
  padding: 10px;
  margin: 10px 0;
  border-radius: 4px;
}

.loading {
  background: #e3f2fd;
  color: #1976d2;
}

.error {
  background: #ffebee;
  color: #d32f2f;
}

.stats, .users {
  margin: 20px 0;
  padding: 15px;
  border: 1px solid #eee;
  border-radius: 4px;
}

.actions {
  margin: 20px 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

button {
  padding: 8px 16px;
  background: #1976d2;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

button:hover {
  background: #1565c0;
}

input[type="file"] {
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
}
</style>
