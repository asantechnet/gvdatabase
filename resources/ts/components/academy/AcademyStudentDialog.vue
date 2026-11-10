<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import type { AcademyClassRecord } from '@/api/academyClasses'
import { AcademyStudentsApi, type AcademyStudentPayload } from '@/api/academyStudents'
import { apiFieldErrors } from '@/utils/apiErrors'

const props = defineProps<{ modelValue: boolean; classes: AcademyClassRecord[] }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; saved: [] }>()
const { t } = useI18n()
const toast = useToast()
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const form = reactive<AcademyStudentPayload>({
  name: '',
  father_name: '',
  father_phone: '',
  contact: '',
  email: '',
  address: '',
  notes: '',
  class_id: 0,
  started_on: new Date().toISOString().slice(0, 10),
  ends_on: null,
})

const open = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })

watch(() => props.modelValue, value => {
  if (!value)
    return
  errors.value = {}
  Object.assign(form, {
    name: '', father_name: '', father_phone: '', contact: '', email: '', address: '', notes: '',
    class_id: props.classes[0]?.id ?? 0,
    started_on: new Date().toISOString().slice(0, 10),
    ends_on: props.classes[0]?.ends_on ?? null,
  })
})

function classChanged() {
  const selected = props.classes.find(row => row.id === form.class_id)
  form.ends_on = selected?.ends_on ?? null
}

async function save() {
  if (!form.name.trim() || !form.class_id || saving.value)
    return
  saving.value = true
  errors.value = {}
  try {
    await AcademyStudentsApi.create(form)
    toast.success(t('academy.students.saved'))
    open.value = false
    emit('saved')
  }
  catch (error) {
    errors.value = apiFieldErrors(error)
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <VDialog v-model="open" max-width="680">
    <VCard>
      <VCardTitle>{{ t('academy.students.addTitle') }}</VCardTitle>
      <VCardText>
        <VRow>
          <VCol cols="12" md="6"><VTextField v-model="form.name" :label="t('academy.fields.name')" :error-messages="errors.name" required /></VCol>
          <VCol cols="12" md="6"><VTextField v-model="form.father_name" :label="t('educational.fatherName')" :error-messages="errors.father_name" /></VCol>
          <VCol cols="12" md="6"><VTextField v-model="form.contact" :label="t('academy.fields.contact')" :error-messages="errors.contact" /></VCol>
          <VCol cols="12" md="6"><VTextField v-model="form.father_phone" :label="t('educational.fatherPhone')" :error-messages="errors.father_phone" /></VCol>
          <VCol cols="12" md="6"><VTextField v-model="form.email" type="email" :label="t('common.fields.email')" :error-messages="errors.email" /></VCol>
          <VCol cols="12" md="6"><VTextField v-model="form.address" :label="t('common.fields.address')" :error-messages="errors.address" /></VCol>
          <VCol cols="12" md="6">
            <VSelect v-model="form.class_id" :items="classes" item-title="name" item-value="id" :label="t('academy.fields.class')" :error-messages="errors.class_id" @update:model-value="classChanged" />
          </VCol>
          <VCol cols="12" md="3"><VTextField v-model="form.started_on" type="date" :label="t('academy.fields.startsOn')" :error-messages="errors.started_on" /></VCol>
          <VCol cols="12" md="3"><VTextField v-model="form.ends_on" type="date" :label="t('academy.fields.endsOn')" :error-messages="errors.ends_on" /></VCol>
          <VCol cols="12"><VTextarea v-model="form.notes" :label="t('academy.fields.notes')" rows="2" /></VCol>
        </VRow>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="open = false">{{ t('common.actions.cancel') }}</VBtn>
        <VBtn color="primary" :loading="saving" :disabled="!form.name.trim() || !form.class_id" @click="save">{{ t('common.actions.save') }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
