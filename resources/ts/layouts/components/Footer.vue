<script setup lang="ts">
import { useBranding } from '@/composables/useBranding'

const { logoUrl, originalCompanyName, loadBranding } = useBranding()

onMounted(loadBranding)
</script>

<template>
  <div class="h-100 d-flex align-center justify-center">
    <span class="d-flex align-center text-medium-emphasis">
      <img :src="logoUrl" :alt="originalCompanyName" class="footer-logo me-2">
      {{ $t('components.navigation.footer.copyright', { year: new Date().getFullYear(), company: originalCompanyName }) }}
    </span>
  </div>
</template>

<style scoped>
.footer-logo {
  block-size: 1.5rem;
  inline-size: 1.5rem;
  object-fit: contain;
}
</style>
