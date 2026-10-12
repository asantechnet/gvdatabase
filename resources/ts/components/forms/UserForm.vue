<script setup lang="ts">
import type { UserPayload, UserRole, UserStatus } from '@/api/user'

type UserFormModel = UserPayload & { password?: string | null; password_confirmation?: string | null }

const props = withDefaults(defineProps<{ form: UserFormModel; roles: Array<{ title: string; value: UserRole }>; statusItems: Array<{ title: string; value: UserStatus }>; statusDisabled: boolean; statusHint?: string; editing: boolean; loading: boolean; passwordMode?: boolean }>(), { passwordMode: false })

const emit = defineEmits<{ submit: []; cancel: [] }>()
const { t } = useI18n()

const minimumPasswordLength = 8
const passwordRule = (value: unknown) => !value || String(value).length >= minimumPasswordLength || t('pages.settings.users.passwordTooShort', { count: minimumPasswordLength })
const confirmationRule = (value: unknown) => !props.passwordMode || !value || value === props.form.password || t('pages.settings.users.passwordsDoNotMatch')
const submitLabelKey = computed(() => {
  if (props.editing)
    return 'pages.settings.users.saveUser'

  return props.passwordMode ? 'pages.settings.users.createUser' : 'pages.settings.users.sendInvitation'
})
</script>

<template>
  <VForm
    class="app-form"
    @submit.prevent="emit('submit')"
  >
    <VRow>
      <VCol cols="12">
        <p class="app-form__section">
          {{ $t('pages.settings.users.accountDetails') }}
        </p><p class="app-form__section-help">
          {{ $t('pages.settings.users.accountDetailsHint') }}
        </p>
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <VTextField
          v-model="form.name"
          name="name"
          :label="$t('common.fields.name')"
          autocomplete="name"
        />
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <VTextField
          v-model="form.email"
          name="email"
          :label="$t('common.fields.email')"
          type="email"
          autocomplete="email"
        />
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <VTextField
          v-model="form.phone"
          name="phone"
          :label="$t('common.fields.phone')"
          autocomplete="tel"
        />
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <VSelect
          v-model="form.acc_status"
          name="acc_status"
          :items="statusItems"
          :label="$t('common.fields.status')"
          :disabled="statusDisabled"
          :hint="statusHint"
          persistent-hint
        />
      </VCol>
      <VCol cols="12">
        <VTextarea
          v-model="form.address"
          name="address"
          :label="$t('common.fields.address')"
          rows="2"
        />
      </VCol>
      <VCol cols="12">
        <VSelect
          v-model="form.roles"
          name="roles"
          :items="roles"
          :label="$t('pages.settings.users.roles')"
          multiple
          chips
        />
      </VCol>
      <template v-if="passwordMode">
        <VCol cols="12">
          <p class="app-form__section">
            {{ $t('pages.settings.users.passwordSection') }}
          </p><p class="app-form__section-help">
            {{ $t('pages.settings.users.passwordSectionHint', { count: minimumPasswordLength }) }}
          </p>
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.password"
            name="password"
            type="password"
            autocomplete="new-password"
            :label="$t('pages.settings.users.password')"
            :rules="[passwordRule]"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.password_confirmation"
            name="password_confirmation"
            type="password"
            autocomplete="new-password"
            :label="$t('pages.settings.users.confirmPassword')"
            :rules="[confirmationRule]"
          />
        </VCol>
      </template>
    </VRow>
    <div class="app-form__actions">
      <VBtn
        variant="tonal"
        @click="emit('cancel')"
      >
        {{ $t('common.actions.cancel') }}
      </VBtn><VBtn
        type="submit"
        :loading="loading"
      >
        {{ $t(submitLabelKey) }}
      </VBtn>
    </div>
  </VForm>
</template>
