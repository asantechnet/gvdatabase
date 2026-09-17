<script setup lang="ts">
import type { UserRole, UserStatus } from '@/api/user'

defineProps<{ form: { name: string; email: string; phone: string | null; address: string | null; roles: UserRole[]; acc_status: UserStatus }; roles: Array<{ title: string; value: UserRole }>; statusItems: Array<{ title: string; value: UserStatus }>; statusDisabled: boolean; statusHint?: string; editing: boolean; loading: boolean }>()

const emit = defineEmits<{ submit: []; cancel: [] }>()
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
        {{ $t(editing ? 'pages.settings.users.saveUser' : 'pages.settings.users.sendInvitation') }}
      </VBtn>
    </div>
  </VForm>
</template>
