<script setup lang="ts">
import { computed, ref } from 'vue'
import type { UserPayload, UserRole, UserStatus } from '@/api/user'
import ErpField from '@/components/erp/ErpField.vue'

type UserFormModel = UserPayload & { password?: string | null; password_confirmation?: string | null }

const props = withDefaults(defineProps<{
  form: UserFormModel
  roles: Array<{ title: string; value: UserRole }>
  statusItems: Array<{ title: string; value: UserStatus }>
  statusDisabled: boolean
  statusHint?: string
  editing?: boolean
  errors?: string[]
}>(), { editing: false, errors: () => [] })

const { t } = useI18n()
const passwordMode = defineModel<boolean>('passwordMode', { default: false })
const showPassword = ref(false)
const showPasswordConfirmation = ref(false)
const minimumPasswordLength = 8
const passwordType = computed(() => showPassword.value ? 'text' : 'password')
const confirmationType = computed(() => showPasswordConfirmation.value ? 'text' : 'password')
</script>

<template>
  <VRow>
    <VCol cols="12" md="6"><ErpField v-model="form.name" required autocomplete="name" :label="t('common.fields.name')" :placeholder="t('common.fields.name')" /></VCol>
    <VCol cols="12" md="6"><ErpField v-model="form.email" required type="email" autocomplete="email" :label="t('common.fields.email')" :placeholder="t('common.fields.email')" /></VCol>
    <VCol cols="12" md="6"><ErpField v-model="form.phone" autocomplete="tel" :label="t('common.fields.phone')" :placeholder="t('common.fields.phone')" /></VCol>
    <VCol cols="12" md="6"><ErpField v-model="form.acc_status" kind="select" :items="statusItems" :disabled="statusDisabled" :hint="statusHint" persistent-hint :label="t('common.fields.status')" :placeholder="t('common.fields.status')" /></VCol>
    <VCol cols="12" md="6"><ErpField v-model="form.address" :label="t('common.fields.address')" :placeholder="t('common.fields.address')" /></VCol>
    <VCol cols="12" md="6"><ErpField v-model="form.roles" kind="select" :items="roles" multiple chips :label="t('pages.settings.users.roles')" :placeholder="t('pages.settings.users.roles')" /></VCol>

    <VCol v-if="!editing" cols="12"><VSwitch v-model="passwordMode" color="primary" inset hide-details :label="t('pages.settings.users.setPasswordManually')" /></VCol>
    <VCol v-if="passwordMode" cols="12">
      <section class="user-password-section">
        <div class="user-password-section__heading"><h3>{{ t('pages.settings.users.passwordSection') }}</h3><p>{{ t('pages.settings.users.passwordSectionHint', { count: minimumPasswordLength }) }}</p></div>
        <VRow>
          <VCol cols="12" md="6"><ErpField v-model="form.password" :type="passwordType" autocomplete="new-password" :label="t('pages.settings.users.password')" :placeholder="t('pages.settings.users.password')" :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'" @click:append-inner="showPassword = !showPassword" /></VCol>
          <VCol cols="12" md="6"><ErpField v-model="form.password_confirmation" :type="confirmationType" autocomplete="new-password" :label="t('pages.settings.users.confirmPassword')" :placeholder="t('pages.settings.users.confirmPassword')" :append-inner-icon="showPasswordConfirmation ? 'tabler-eye-off' : 'tabler-eye'" @click:append-inner="showPasswordConfirmation = !showPasswordConfirmation" /></VCol>
        </VRow>
      </section>
    </VCol>
    <VCol v-if="errors.length" cols="12"><VAlert type="error" variant="tonal" density="compact"><ul class="user-form-errors"><li v-for="error in errors" :key="error">{{ error }}</li></ul></VAlert></VCol>
  </VRow>
</template>

<style scoped>
.user-password-section { padding: 1rem; border: 1px solid rgba(var(--v-theme-primary), .2); border-radius: .75rem; background: rgba(var(--v-theme-primary), .06); }.user-password-section__heading { margin-block-end: .75rem; }.user-password-section__heading h3 { margin: 0; color: rgb(var(--v-theme-on-surface)); font-size: 1rem; }.user-password-section__heading p { margin: .25rem 0 0; color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .82rem; }.user-form-errors { margin: 0; padding-inline-start: 1.25rem; }
</style>
