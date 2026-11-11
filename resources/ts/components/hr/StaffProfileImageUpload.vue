<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import EmployeeAvatar from './EmployeeAvatar.vue'

const props = defineProps<{
  name: string
  avatar: string | null | undefined
  file: File | null
}>()

const emit = defineEmits<{
  select: [file: File]
  remove: []
}>()

const input = ref<HTMLInputElement | null>(null)
const previewUrl = ref<string | null>(null)
const imageSource = computed(() => previewUrl.value ?? props.avatar ?? null)
const { t } = useI18n()

function clearPreview() {
  if (previewUrl.value)
    URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = null
}

watch(() => props.file, file => {
  clearPreview()
  if (file)
    previewUrl.value = URL.createObjectURL(file)
}, { immediate: true })

onBeforeUnmount(clearPreview)

function chooseImage() {
  input.value?.click()
}

function onFileChange(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file)
    emit('select', file)

  // Permit selecting the same file after replacing or removing it.
  if (input.value)
    input.value.value = ''
}
</script>

<template>
  <section class="staff-profile-image-upload" :aria-label="t('components.account.accountsettingsaccount.uploadNewPhoto')">
    <input ref="input" class="staff-profile-image-upload__input" type="file" accept="image/jpeg,image/png,image/gif,image/webp" @change="onFileChange">
    <EmployeeAvatar :name="name" :src="imageSource" size="76" class="staff-profile-image-upload__preview" />
    <div class="staff-profile-image-upload__copy">
      <strong>{{ t('components.account.accountsettingsaccount.uploadNewPhoto') }}</strong>
      <span>{{ t('components.account.accountsettingsaccount.allowedJPGGIFPNGOrWebPMaximumSize800KB') }}</span>
    </div>
    <div class="staff-profile-image-upload__actions">
      <VTooltip :text="t('components.account.accountsettingsaccount.uploadNewPhoto')">
        <template #activator="{ props: tooltipProps }">
          <VBtn v-bind="tooltipProps" icon color="primary" variant="tonal" :aria-label="t('components.account.accountsettingsaccount.uploadNewPhoto')" @click="chooseImage"><VIcon :icon="imageSource ? 'tabler-refresh' : 'tabler-upload'" /></VBtn>
        </template>
      </VTooltip>
      <VTooltip v-if="imageSource" :text="t('common.actions.delete')">
        <template #activator="{ props: tooltipProps }">
          <VBtn v-bind="tooltipProps" icon color="error" variant="text" :aria-label="t('common.actions.delete')" @click="emit('remove')"><VIcon icon="tabler-trash" /></VBtn>
        </template>
      </VTooltip>
    </div>
  </section>
</template>

<style scoped>
.staff-profile-image-upload { display: flex; align-items: center; gap: 1rem; border: 1px dashed rgba(var(--v-theme-primary), .4); border-radius: .75rem; background: rgba(var(--v-theme-primary), .035); padding: 1rem; }
.staff-profile-image-upload__input { display: none; }
.staff-profile-image-upload__preview { flex: 0 0 auto; }
.staff-profile-image-upload__copy { display: grid; gap: .2rem; min-inline-size: 0; }
.staff-profile-image-upload__copy > span { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .8rem; }
.staff-profile-image-upload__actions { display: flex; gap: .25rem; margin-inline-start: auto; }
@media (max-width: 480px) { .staff-profile-image-upload { align-items: flex-start; flex-wrap: wrap; }.staff-profile-image-upload__copy { flex: 1 1 10rem; }.staff-profile-image-upload__actions { margin-inline-start: auto; } }
</style>
