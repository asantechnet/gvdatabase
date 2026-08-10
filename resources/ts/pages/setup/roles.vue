<template>
  <div>
    <VWindow v-model="activeTab" class="mb-5">
      <!-- 👉 Role Cards Tab -->
      <VWindowItem value="role">
        <RoleCards />
      </VWindowItem>

      <!-- 👉 User List Tab -->
      <VWindowItem value="user">
        <UserList />
      </VWindowItem>
    </VWindow>
  </div>
</template>

<script setup lang="ts">
import RoleCards from '@/views/apps/roles/RoleCards.vue'
import UserList from '@/views/apps/roles/UserList.vue'

const activeTab = ref('role')
</script>
