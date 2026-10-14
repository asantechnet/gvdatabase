<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import ApplicantApi, { type ApplicantStage, type BuiltInService } from '@/api/applicants'
import MasterDataApi, { type MasterDataRecord } from '@/api/educationalMasterData'

const { t } = useI18n()
const toast = useToast()
// Each built-in service owns its own stage list; the selector scopes the page.
const service = ref<BuiltInService>('scholarship')
const serviceOptions = [
  { title: t('educational.builtInServices.scholarship'), value: 'scholarship' },
  { title: t('educational.builtInServices.testOffer'), value: 'test_offer' },
  { title: t('educational.builtInServices.academy'), value: 'academy' },
  { title: t('educational.builtInServices.private'), value: 'private' },
]
const stages = ref<ApplicantStage[]>([])
const countries = ref<MasterDataRecord[]>([])
const universities = ref<MasterDataRecord[]>([])
const dialog = ref(false)
const editing = ref<ApplicantStage | null>(null)
const form = ref<Partial<ApplicantStage>>({ key: '', name: '', service: service.value, sort_order: 0, is_active: true, is_terminal: false, allowed_next_keys: [] })
const load = async () => { [stages.value, countries.value, universities.value] = await Promise.all([ApplicantApi.stageDefinitions(service.value), MasterDataApi.list('countries'), MasterDataApi.list('universities')]) }
const open = (stage?: ApplicantStage) => { editing.value = stage ?? null; form.value = stage ? { ...stage, allowed_next_keys: [...(stage.allowed_next_keys ?? [])] } : { key: '', name: '', service: service.value, sort_order: 0, is_active: true, is_terminal: false, allowed_next_keys: [] }; dialog.value = true }
const save = async () => { try { if (editing.value) await ApplicantApi.updateStageDefinition(editing.value.id, form.value); else await ApplicantApi.createStage({ ...form.value, service: service.value }); dialog.value = false; await load(); toast.success(t('educational.masterData.saved')) } catch { toast.error(t('educational.masterData.saveFailed')) } }
const remove = async (stage: ApplicantStage) => { if (!confirm(t('educational.masterData.removeConfirm', { name: stage.name }))) return; try { await ApplicantApi.removeStageDefinition(stage.id); await load() } catch { toast.error(t('educational.masterData.inUse')) } }
watch(service, load)
onMounted(load)
</script>
<template>
  <div class="education-workspace"><VCard class="education-panel"><VCardTitle class="d-flex align-center"><span>{{ $t('educational.stage') }}</span><VSpacer/><VSelect v-model="service" :items="serviceOptions" class="me-3" style="max-width: 220px" density="compact" hide-details/><VBtn color="primary" prepend-icon="tabler-plus" @click="open()">{{ $t('common.actions.add') }}</VBtn></VCardTitle><VCardText><VTable><thead><tr><th>{{ $t('common.fields.name') }}</th><th>{{ $t('educational.country') }}</th><th>{{ $t('educational.university') }}</th><th>{{ $t('educational.status') }}</th><th/></tr></thead><tbody><tr v-for="stage in stages" :key="stage.id"><td>{{ stage.name }}</td><td>{{ countries.find(c => c.id === stage.country_id)?.name ?? '—' }}</td><td>{{ universities.find(u => u.id === stage.university_id)?.name ?? '—' }}</td><td><VChip size="small" :color="stage.is_active ? 'success' : 'secondary'">{{ stage.is_active ? $t('common.statuses.active') : $t('common.statuses.inactive') }}</VChip></td><td><VBtn icon="tabler-pencil" size="small" variant="text" @click="open(stage)"/><VBtn icon="tabler-trash" size="small" variant="text" color="error" @click="remove(stage)"/></td></tr></tbody></VTable></VCardText></VCard>
  <VDialog v-model="dialog" max-width="600"><VCard><VCardTitle>{{ $t('educational.stage') }}</VCardTitle><VCardText><VTextField v-model="form.key" :label="$t('common.fields.code')" class="mb-3"/><VTextField v-model="form.name" :label="$t('common.fields.name')" class="mb-3"/><VSelect v-model="form.service" :items="serviceOptions" :label="$t('common.navigation.contract')" class="mb-3"/><VSelect v-model="form.country_id" :items="countries.map(c=>({title:c.name,value:c.id}))" :label="$t('educational.country')" clearable class="mb-3"/><VSelect v-model="form.university_id" :items="universities.filter(u=>!form.country_id||u.country_id===form.country_id).map(u=>({title:u.name,value:u.id}))" :label="$t('educational.university')" clearable class="mb-3"/><VTextField v-model.number="form.sort_order" type="number" :label="$t('educational.masterData.displayOrder')" class="mb-3"/><VCombobox v-model="form.allowed_next_keys" multiple chips :label="$t('educational.nextStages', { stages: '' })"/><VSwitch v-model="form.is_terminal" :label="$t('educational.closed')"/><VSwitch v-model="form.is_active" :label="$t('common.statuses.active')"/></VCardText><VCardActions><VSpacer/><VBtn @click="dialog=false">{{ $t('common.actions.cancel') }}</VBtn><VBtn color="primary" @click="save">{{ $t('common.actions.save') }}</VBtn></VCardActions></VCard></VDialog></div>
</template>
