<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { avatarText } from '@core/utils/formatters'

const props = withDefaults(defineProps<{
  name: string
  src?: string | null
  size?: string | number
  color?: string
  variant?: 'flat' | 'text' | 'elevated' | 'tonal' | 'outlined' | 'plain'
}>(), {
  src: null,
  size: 40,
  color: 'primary',
  variant: 'tonal',
})

const imageFailed = ref(false)
const hasImage = computed(() => Boolean(props.src) && !imageFailed.value)

watch(() => props.src, () => {
  imageFailed.value = false
})
</script>

<template>
  <VAvatar
    :size="size"
    :color="hasImage ? undefined : color"
    :variant="hasImage ? undefined : variant"
  >
    <VImg
      v-if="hasImage"
      :src="src!"
      :alt="name"
      cover
      @error="imageFailed = true"
    />
    <span v-else class="text-caption font-weight-medium">{{ avatarText(name).slice(0, 2) || '?' }}</span>
    <slot />
  </VAvatar>
</template>
