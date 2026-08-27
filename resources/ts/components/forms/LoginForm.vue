<script setup lang="ts">
defineProps<{
  form: { email: string; password: string; remember: boolean; twoFactorCode: string }
  loading: boolean
  showPassword: boolean
  twoFactorRequired: boolean
}>()

const emit = defineEmits<{ togglePassword: []; submit: [] }>()
</script>

<template>
  <VForm class="app-form" @submit.prevent="emit('submit')">
    <VRow>
      <VCol cols="12"><VTextField v-model="form.email" name="email" :label="$t('common.fields.email')" type="email" autocomplete="email" :placeholder="$t('pages.auth.login.login.enterYourEmail')" required /></VCol>
      <VCol cols="12"><VTextField v-model="form.password" name="password" :label="$t('common.fields.password')" :placeholder="$t('pages.auth.login.login.enterYourPassword')" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'" required @click:append-inner="emit('togglePassword')" /></VCol>
      <VCol v-if="twoFactorRequired" cols="12"><VTextField v-model="form.twoFactorCode" name="two_factor_code" :label="$t('pages.auth.login.login.authenticatorCode')" inputmode="numeric" autocomplete="one-time-code" maxlength="6" prepend-inner-icon="tabler-shield-lock" required /></VCol>
      <VCol cols="12"><div class="d-flex align-center justify-space-between flex-wrap gap-3"><VCheckbox v-model="form.remember" :label="$t('pages.auth.login.login.rememberMe')" hide-details /><RouterLink class="text-primary" :to="{ name: 'forgot-password', query: { email: form.email || undefined } }">{{ $t('pages.auth.login.login.forgotPassword') }}</RouterLink></div></VCol>
      <VCol cols="12"><VBtn block type="submit" :loading="loading">{{ twoFactorRequired ? 'Verify and sign in' : 'Login' }}</VBtn></VCol>
    </VRow>
  </VForm>
</template>
