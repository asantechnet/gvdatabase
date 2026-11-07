<script setup lang="ts">
import { printDocument } from '@/utils/printDocument'
import { defineExpose, defineProps, onMounted, ref } from 'vue'

const props = defineProps({
  logo: {
    type: String,
    required: false,
    default: '',
  },
  coverDesignUrl: {
    type: String,
    required: false,
    default: '',
  },
  companyWebsite: {
    type: String,
    required: false,
    default: 'www.yourcompany.com',
  },
  contractTitle: {
    type: String,
    required: false,
    default: 'SIMPLE CONTRACT',
  },
  coverDescription: {
    type: String,
    required: false,
    default: '',
  },
  contractNumber: {
    type: String,
    required: true,
  },
  contracteeName: {
    type: String,
    required: true,
  },
  companyAddress: {
    type: String,
    required: false,
    default: '123 Company St, City, State, ZIP',
  },
  companyEmail: {
    type: String,
    required: false,
    default: 'contact@yourcompany.com',
  },
  companyNumber: {
    type: String,
    required: false,
    default: '+1 (555) 123-4567',
  },
})

const contractCoverRef = ref<HTMLElement | null>(null)
const logoImage = ref<HTMLImageElement | null>(null)
const logoLoaded = ref<boolean>(false)
// A logo URL whose file is gone must not print as a broken image on the cover.
const logoFailed = ref(false)

const waitForLogoLoad = () => {
  return new Promise<void>(resolve => {
    if (!props.logo || logoLoaded.value) {
      resolve()

      return
    }

    const checkLoad = () => {
      if (logoImage.value && logoImage.value.complete) {
        logoLoaded.value = true
        resolve()
      }
      else if (logoImage.value) {
        logoImage.value.onload = () => {
          logoLoaded.value = true
          resolve()
        }
        logoImage.value.onerror = err => {
          console.error('Failed to load logo, proceeding without it.', err)
          resolve()
        }
      }
      else {
        resolve()
      }
    }

    checkLoad()
  })
}

const printContractCover = async () => {
  if (!contractCoverRef.value) return
  await printDocument({ bodyClass: 'printing-contract-document', pageStyle: 'size: A4; margin: 0;', element: contractCoverRef.value, printPageSelector: '.contract-cover' })
}

defineExpose({
  print: printContractCover,
})

onMounted(() => {
  if (props.logo && logoImage.value)
    waitForLogoLoad()
  else
    logoLoaded.value = true
})
</script>

<template>
  <div
    ref="contractCoverRef"
    class="contract-cover print-document"
    :dir="$i18n.locale === 'en' ? 'ltr' : 'rtl'"
    :class="{ 'contract-cover--custom': coverDesignUrl }"
  >
    <img
      v-if="coverDesignUrl"
      :src="coverDesignUrl"
      alt=""
      class="cover-design-background"
      aria-hidden="true"
    >
    <!-- Blue top section with a clean straight edge -->
    <div class="top-section">
      <div class="content-container">
        <!-- Far Left: "COVER PAGE" -->
        <div class="cover-page-text">
          {{ $t('documents.contract_cover_template.contractCoverTemplate.coverPAGE') }}
        </div>

        <!-- Far Right: Logo, website, Contractee Info -->
        <div class="right-info-block">
          <div class="logo-circle">
            <img
              v-if="logo && !logoFailed"
              ref="logoImage"
              :src="logo"
              alt="Company Logo"
              class="logo-image"
              @error="logoFailed = true"
            >
            <div
              v-else
              class="logo-placeholder"
            >
              {{ $t('documents.contract_cover_template.contractCoverTemplate.yourLOGO') }}
            </div>
          </div>
          <div class="company-website">
            {{ companyWebsite }}
          </div>

          <div class="contractee-block">
            <div class="contractee-word">
              {{ $t('documents.contract_cover_template.contractCoverTemplate.contractee') }}
            </div>
            <div class="contractee-name">
              {{ contracteeName }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Light grey/white bottom section with main title and footer -->
    <div class="bottom-section">
      <div class="content-container">
        <div class="title-block">
          <h1 class="contract-title">
            {{ contractTitle }}
          </h1>
          <p
            v-if="coverDescription"
            class="cover-description"
          >
            {{ coverDescription }}
          </p>
          <p>{{ contractNumber }}</p>
        </div>
        <footer class="footer-block">
          {{ companyAddress }} | {{ companyEmail }} | {{ companyNumber }}
        </footer>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap');

*,
*::before,
*::after {
  box-sizing: border-box;
}

.contract-cover {
  font-family: 'Vazirmatn', 'Noto Sans Arabic', Tahoma, sans-serif;
  width: 100%;
  aspect-ratio: 210 / 297;
  max-width: 800px;
  margin: 20px auto;
  position: relative;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  background: #ffffff;
}

.cover-design-background {
  position: absolute;
  z-index: 0;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.contract-cover--custom .top-section,
.contract-cover--custom .bottom-section {
  background: transparent;
  clip-path: none;
}

.contract-cover--custom .top-section,
.contract-cover--custom .bottom-section {
  z-index: 1;
}

.contract-cover--custom .cover-page-text,
.contract-cover--custom .company-website,
.contract-cover--custom .contractee-word,
.contract-cover--custom .contractee-name,
.contract-cover--custom .contract-title,
.contract-cover--custom .title-block,
.contract-cover--custom .footer-block {
  text-shadow: 0 1px 3px rgb(255 255 255 / 80%);
}

.cover-description {
  max-inline-size: 34rem;
  margin-block: 0.75rem;
  white-space: pre-line;
}

.content-container {
  width: 100%;
  height: 100%;
  position: relative;
  display: flex;
  flex-direction: column;
  padding: 8% 5% 5% 5%;
}

.top-section {
  flex: 3;
  background-color: #26308e;
  position: relative;
  z-index: 10;
  overflow: hidden;
}

.top-section .content-container {
  flex-direction: row;
  justify-content: space-between;
  align-items: flex-start;
  color: white;
}

[dir='rtl'] .top-section .content-container {
  flex-direction: row-reverse;
}

.cover-page-text {
  font-size: 1.8rem;
  font-weight: bold;
  color: #aaa3e0;
  opacity: 0.8;
  margin-top: 1%;
}

.right-info-block {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  text-align: center;
  max-width: 25%;
}

.logo-circle {
  width: 100px;
  height: 100px;
  border-radius: 50%;
  background-color: white;
  display: flex;
  justify-content: center;
  align-items: center;
  overflow: hidden;
  color: rgb(100, 100, 100);
}

.logo-image {
  max-width: 90%;
  max-height: 90%;
  object-fit: contain;
}

.logo-placeholder {
  font-size: 0.9rem;
  font-weight: bold;
}

.company-website {
  font-size: 0.9rem;
  color: white;
  opacity: 0.9;
}

.contractee-block {
  margin-top: 20px;
  text-align: center;
}

.contractee-word {
  font-size: 0.8rem;
  color: white;
  opacity: 0.8;
}

.contractee-name {
  font-size: 1rem;
  font-weight: bold;
  color: white;
  margin-top: 5px;
}

.bottom-section {
  flex: 2;
  background-color: #f3f4f8;
  position: relative;
  z-index: 1;
}

.bottom-section .content-container {
  justify-content: flex-end;
  align-items: flex-start;
  color: #333;
  padding-bottom: 30px;
}

.title-block {
  width: 100%;
}

.contract-title {
  font-size: 3.5rem;
  font-weight: bold;
  color: #333;
  margin: 0 0 10% 0;
  line-height: 1.1;
  text-transform: uppercase;
  max-width: 80%;
}

.footer-block {
  width: 100%;
  text-align: center;
  font-size: 0.8rem;
  color: #555;
  opacity: 0.8;
  margin-top: auto;
}

@media print {

  :global(body.printing-contract-document) {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  :global(body.printing-contract-document .contract-cover) {
    box-shadow: none !important;
    margin: 0 auto !important;
    width: 210mm !important;
    max-width: 210mm !important;
    height: 297mm !important;
    min-height: 297mm !important;
    max-height: 297mm !important;
    position: relative !important;
    aspect-ratio: auto !important;
    display: flex !important;
    flex-direction: column !important;
    visibility: visible !important;
    overflow: hidden !important;
    page-break-after: auto !important;
    break-after: auto !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
    background: #ffffff !important;
  }

  :global(body.printing-contract-document .top-section) {
    width: 100% !important;
    flex: 3 !important;
  }

  :global(body.printing-contract-document .bottom-section) {
    width: 100% !important;
    flex: 2 !important;
  }
}
</style>
