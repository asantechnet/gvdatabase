<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import ApplicantApi, { type Applicant } from '@/api/applicants'

const route = useRoute()
const applicant = ref<Applicant | null>(null)
const stages = ref<Array<{ id: number; name: string; key: string }>>([])
const selected = ref<number | null>(null)
const note = ref('')

async function load() {
  applicant.value = await ApplicantApi.get(Number(route.params.id))
  stages.value = await ApplicantApi.stages()
  selected.value = applicant.value.current_stage_id ?? null
}

async function move() {
  if (selected.value)
    applicant.value = await ApplicantApi.changeStage(Number(route.params.id), selected.value, note.value)
}

onMounted(load)
</script>
<template><VCard v-if="applicant"><VCardTitle>{{ applicant.customer?.name }} — Visa case</VCardTitle><VCardText><VChip color="primary">{{ applicant.current_stage?.name }}</VChip><VSelect v-model="selected" :items="stages.map(s=>({title:s.name,value:s.id}))" label="Move case to stage" class="mt-4"/><VTextarea v-model="note" label="Stage note"/><VBtn color="primary" @click="move">Record stage change</VBtn><VDivider class="my-6"/><h3>Timeline</h3><VList><VListItem v-for="item in applicant.stage_history" :key="item.id" :title="`${item.old_stage?.name ?? 'Started'} → ${item.new_stage?.name}`" :subtitle="item.note || item.changed_at"/></VList><h3>Tasks</h3><VList><VListItem v-for="task in applicant.tasks" :key="task.id" :title="task.title" :subtitle="`${task.progress}% complete`"/></VList><h3>Contracts</h3><VList><VListItem v-for="contract in applicant.contracts" :key="contract.id" :title="contract.contract_number" :subtitle="contract.status"/></VList></VCardText></VCard></template>
