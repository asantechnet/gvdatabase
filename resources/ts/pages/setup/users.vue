<script setup lang="ts">
import { computed, ref } from 'vue'

interface User {
  id: number
  name: string
  email: string
  role: string
  status: string
}

const users = ref<User[]>([
  { id: 1, name: 'John Doe', email: 'john@example.com', role: 'Admin', status: 'Active' },
  { id: 2, name: 'Jane Smith', email: 'jane@example.com', role: 'Editor', status: 'Inactive' },
])

const search = ref('')
const filterRole = ref<string | null>(null)
const filterStatus = ref<string | null>(null)

const roles = ['Admin', 'Editor', 'Client', 'Support']
const statuses = ['Active', 'Inactive']

const filtered = computed(() => {
  return users.value.filter(u => {
    const matchesSearch = u.name.toLowerCase().includes(search.value.toLowerCase()) ||
      u.email.toLowerCase().includes(search.value.toLowerCase())
    const matchesRole = !filterRole.value || u.role === filterRole.value
    const matchesStatus = !filterStatus.value || u.status === filterStatus.value
    return matchesSearch && matchesRole && matchesStatus
  })
})

const headers = [
  { title: 'Name', key: 'name', sortable: true },
  { title: 'Email', key: 'email', sortable: true },
  { title: 'Role', key: 'role', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
]

const printTable = () => {
  window.print()
}

const addNew = () => {
  alert('Add new user')
}
</script>

<template>
  <VCard>
    <VCardText>
      <VRow align="center">
        <VCol cols="12" md="4">
          <VTextField v-model="search" prepend-inner-icon="tabler-search" placeholder="Search..." density="compact"
            clearable hide-details />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect v-model="filterRole" :items="roles" label="Filter by Role" density="compact" clearable
            hide-details />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect v-model="filterStatus" :items="statuses" label="Filter by Status" density="compact" clearable
            hide-details />
        </VCol>
        <VCol cols="12" md="2" class="text-end d-flex gap-2 justify-end">
          <VBtn icon="tabler-printer" variant="text" @click="printTable" />
          <VBtn color="primary" @click="addNew">
            <VIcon icon="tabler-plus" class="me-1" /> Add User
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>
    <VDataTable :headers="headers" :items="filtered" item-value="id" class="text-no-wrap" mobile-breakpoint="sm"
      style="overflow-x: auto;" />
  </VCard>
</template>
