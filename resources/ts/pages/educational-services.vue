<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import Api, { type EducationalService, type EducationalServiceType } from '@/api/educationalServices'

const type = ref<EducationalServiceType>('scholarships')
const rows = ref<EducationalService[]>([])
const form = ref<Partial<EducationalService>>({
  status: 'active',
  date: new Date().toISOString().slice(0, 10),
})
const dialog = ref(false)

async function load() {
  rows.value = await Api.list(type.value)
}

async function save() {
  await Api.create(type.value, form.value)
  dialog.value = false
  await load()
}

watch(type, load)
onMounted(load)
</script>
<template><VCard><VCardTitle class="d-flex justify-space-between"><span>Educational services</span><VBtn @click="dialog=true">Record sale</VBtn></VCardTitle><VCardText><VTabs v-model="type"><VTab value="scholarships">Scholarship services</VTab><VTab value="test-offers">Test offers</VTab></VTabs><VTable><thead><tr><th>Customer</th><th>Employee</th><th>Fee</th><th>Date</th><th>Status</th></tr></thead><tbody><tr v-for="row in rows" :key="row.id"><td>#{{row.customer_id}}</td><td>#{{row.employee_id}}</td><td>{{row.fee}}</td><td>{{row.date}}</td><td>{{row.status}}</td></tr></tbody></VTable></VCardText></VCard><VDialog v-model="dialog" max-width="500"><VCard><VCardTitle>Record {{type==='scholarships'?'scholarship service':'test offer'}}</VCardTitle><VCardText><VTextField v-model.number="form.customer_id" type="number" label="Customer ID"/><VTextField v-model.number="form.applicant_id" type="number" label="Applicant ID (optional)"/><VTextField v-model.number="form.employee_id" type="number" label="Employee ID"/><VTextField v-model.number="form.currency_id" type="number" label="Currency ID"/><VTextField v-model.number="form.package_type_id" type="number" label="Package type ID (optional)"/><VTextField v-model="form.date" type="date" label="Date"/><VTextField v-model.number="form.fee" type="number" label="Fee"/></VCardText><VCardActions><VSpacer/><VBtn @click="dialog=false">Cancel</VBtn><VBtn color="primary" @click="save">Save</VBtn></VCardActions></VCard></VDialog></template>
