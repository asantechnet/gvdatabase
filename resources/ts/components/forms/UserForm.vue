<script setup lang="ts">
defineProps<{ form: { name: string; email: string; phone: string; address: string; roles: string[]; acc_status: string }; roles: string[]; statusItems: string[]; statusDisabled: boolean; statusHint?: string; editing: boolean; loading: boolean }>()
const emit = defineEmits<{ submit: []; cancel: [] }>()
</script>
<template>
  <VForm class="app-form" @submit.prevent="emit('submit')">
    <VRow>
      <VCol cols="12"><p class="app-form__section">Account details</p><p class="app-form__section-help">Use contact details that help teammates identify and reach this user.</p></VCol>
      <VCol cols="12" md="6"><VTextField v-model="form.name" name="name" :label="$t('common.fields.name')" autocomplete="name" /></VCol>
      <VCol cols="12" md="6"><VTextField v-model="form.email" name="email" :label="$t('common.fields.email')" type="email" autocomplete="email" /></VCol>
      <VCol cols="12" md="6"><VTextField v-model="form.phone" name="phone" :label="$t('common.fields.phone')" autocomplete="tel" /></VCol>
      <VCol cols="12" md="6"><VSelect v-model="form.acc_status" name="acc_status" :items="statusItems" :label="$t('common.fields.status')" :disabled="statusDisabled" :hint="statusHint" persistent-hint /></VCol>
      <VCol cols="12"><VTextarea v-model="form.address" name="address" :label="$t('common.fields.address')" rows="2" /></VCol>
      <VCol cols="12"><VSelect v-model="form.roles" name="roles" :items="roles" :label="$t('pages.settings.users.roles')" multiple chips /></VCol>
    </VRow>
    <div class="app-form__actions"><VBtn variant="tonal" @click="emit('cancel')">{{ $t('common.actions.cancel') }}</VBtn><VBtn type="submit" :loading="loading">{{ editing ? 'Save' : 'Send invitation' }}</VBtn></div>
  </VForm>
</template>
