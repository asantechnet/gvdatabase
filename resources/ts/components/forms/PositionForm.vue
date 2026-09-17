<script setup lang="ts">
defineProps<{ form: { title: string; description?: string | null; salary: number; status: string }; loading: boolean }>()

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
          {{ $t('pages.settings.positions.positionDetails') }}
        </p><p class="app-form__section-help">
          {{ $t('pages.settings.positions.positionDetailsHint') }}
        </p>
      </VCol>
      <VCol
        cols="12"
        md="7"
      >
        <VTextField
          v-model="form.title"
          :label="$t('pages.settings.positions.positionTitle')"
          autofocus
        />
      </VCol>
      <VCol
        cols="12"
        md="5"
      >
        <VSelect
          v-model="form.status"
          :label="$t('common.fields.status')"
          :items="['active', 'inactive']"
          :item-title="translateBackendValue"
        />
      </VCol>
      <VCol cols="12">
        <VTextarea
          v-model="form.description"
          :label="$t('common.fields.description')"
          rows="3"
        />
      </VCol>
      <VCol cols="12">
        <VTextField
          v-model.number="form.salary"
          :label="$t('pages.settings.positions.defaultSalary2')"
          type="number"
          min="0"
          :hint="$t('pages.settings.positions.thisIsTheSuggestedSalaryForThePositionEachStaffRecordCanStillHaveItsOw')"
          persistent-hint
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
        {{ $t('common.actions.save') }}
      </VBtn>
    </div>
  </VForm>
</template>
