<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import ApplicantApi, { type Applicant } from '@/api/applicants'

const router = useRouter()
const rows = ref<Applicant[]>([])
const loading = ref(false)

async function load() {
  loading.value = true

  try {
    rows.value = await ApplicantApi.list()
  }
  finally {
    loading.value = false
  }
}

onMounted(load)
</script>
<template><VCard><VCardTitle>Applicants / Visa Cases</VCardTitle><VCardText><VProgressLinear v-if="loading" indeterminate/><VTable><thead><tr><th>Customer</th><th>Stage</th><th>Status</th><th/></tr></thead><tbody><tr v-for="row in rows" :key="row.id"><td>{{ row.customer?.name }}</td><td>{{ row.current_stage?.name }}</td><td>{{ row.status }}</td><td><VBtn size="small" @click="router.push({name:'applicant-case',params:{id:row.id}})">Open case</VBtn></td></tr></tbody></VTable></VCardText></VCard></template>
