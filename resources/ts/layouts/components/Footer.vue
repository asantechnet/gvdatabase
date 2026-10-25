<template>
  <div class="h-100 d-flex align-center justify-center text-medium-emphasis text-body-2">
    <I18nT keypath="components.navigation.footer.developedBy" tag="span" scope="global">
      <template #year>{{ new Date().getFullYear() }}</template>
      <template #developer><a href="https://asantech.net" target="_blank" rel="noopener noreferrer">{{ $t('components.navigation.footer.developerName') }}</a></template>
    </I18nT>
  </div>
</template>
