<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatActiveDate } from '@/utils/localization'
import type { AcademyCertificateRecord } from '@/api/academyCertificates'

const props = defineProps<{
  certificate: AcademyCertificateRecord
  companyName: string
  logoUrl: string | null
  signatureUrl: string | null
}>()

const { t } = useI18n()
const snapshot = computed(() => props.certificate.snapshot)
const award = computed(() => snapshot.value.award)
const revoked = computed(() => props.certificate.status === 'revoked')
</script>

<template>
  <div class="academy-certificate" :class="{ 'is-revoked': revoked }" :dir="$i18n.locale === 'en' ? 'ltr' : 'rtl'">
    <div class="certificate-inner">
      <div class="certificate-ornament" aria-hidden="true" />
      <header class="certificate-header">
        <img v-if="logoUrl" :src="logoUrl" :alt="companyName">
        <div class="company-name">{{ companyName }}</div>
      </header>

      <main class="certificate-body">
        <p class="eyebrow">{{ t('documents.academyCertificate.eyebrow') }}</p>
        <h1>{{ snapshot.template.title }}</h1>
        <p class="student-name">{{ award.student_name }}</p>
        <p class="father-line">
          {{ t('documents.academyCertificate.father', { name: award.father_name || '—' }) }}
          <span>·</span>
          {{ snapshot.certificate_number }}
        </p>
        <p v-if="snapshot.rendered_body?.trim()" class="copy">{{ snapshot.rendered_body }}</p>

        <div class="certificate-facts">
          <div><span>{{ t('documents.academyCertificate.program') }}</span><strong>{{ award.program || '—' }}</strong></div>
          <div><span>{{ t('documents.academyCertificate.class') }}</span><strong>{{ award.class || '—' }}</strong></div>
          <div><span>{{ t('documents.academyCertificate.term') }}</span><strong>{{ formatActiveDate(award.start_date) }} — {{ formatActiveDate(award.end_date) }}</strong></div>
          <div><span>{{ t('documents.academyCertificate.completed') }}</span><strong>{{ formatActiveDate(award.completion_date) }}</strong></div>
        </div>
      </main>

      <footer class="certificate-footer">
        <div class="signature">
          <img v-if="signatureUrl" :src="signatureUrl" :alt="snapshot.signatory.name || companyName">
          <div class="signature-line" />
          <strong>{{ snapshot.signatory.name || companyName }}</strong>
          <span>{{ snapshot.signatory.title || t('documents.academyCertificate.signatory') }}</span>
        </div>
        <div class="issue-date">
          <span>{{ t('documents.academyCertificate.issued') }}</span>
          <strong>{{ formatActiveDate(snapshot.issue_date) }}</strong>
        </div>
      </footer>

      <div v-if="revoked" class="revoked-watermark">{{ t('documents.academyCertificate.revoked') }}</div>
    </div>
  </div>
</template>

<style scoped>
.academy-certificate {
  --certificate-ink: #20304a;
  --certificate-accent: #8a6a2f;
  --certificate-line: #c6a86a;
  inline-size: 297mm;
  block-size: 210mm;
  box-sizing: border-box;
  padding: 7mm;
  color: var(--certificate-ink);
  background: #fffdf7;
  font-family: Georgia, 'Times New Roman', serif;
  position: relative;
  overflow: hidden;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}
.certificate-inner { block-size: 100%; border: 1.2mm solid var(--certificate-accent); padding: 8mm 11mm; position: relative; box-sizing: border-box; display: flex; flex-direction: column; }
.certificate-inner::before { content: ''; position: absolute; inset: 2.5mm; border: .35mm solid var(--certificate-line); pointer-events: none; }
.certificate-ornament { position: absolute; inset: 6mm; border: .2mm solid var(--certificate-line); opacity: .5; }
.certificate-header { display: flex; flex-direction: column; align-items: center; gap: 2mm; z-index: 1; }
.certificate-header img { max-inline-size: 34mm; max-block-size: 15mm; object-fit: contain; }
.company-name { font-size: 10pt; letter-spacing: .16em; text-transform: uppercase; color: #5d4a24; }
.certificate-body { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: 3mm; z-index: 1; }
.eyebrow { margin: 0; font: 700 8pt/1.2 -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; letter-spacing: .22em; text-transform: uppercase; color: var(--certificate-accent); }
h1 { margin: 0; font-size: 29pt; line-height: 1.05; font-weight: 400; letter-spacing: .03em; }
.student-name { margin: 1mm 0 0; font-size: 32pt; line-height: 1.1; font-style: italic; color: #162033; overflow-wrap: anywhere; max-inline-size: 235mm; }
.father-line { margin: 0; font: 9pt/1.4 -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #5f6b7b; }
.copy { margin: 2mm 0 0; max-inline-size: 205mm; white-space: pre-line; font-size: 10pt; line-height: 1.55; color: #3d4a5c; }
.certificate-facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); inline-size: 100%; margin-block-start: 2mm; gap: 3mm; }
.certificate-facts div { display: flex; flex-direction: column; gap: 1mm; padding-block-start: 2mm; border-block-start: .25mm solid var(--certificate-line); }
.certificate-facts span, .issue-date span { font: 7pt/1.2 -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; letter-spacing: .08em; text-transform: uppercase; color: #6c7786; }
.certificate-facts strong { font-size: 9pt; font-weight: 600; overflow-wrap: anywhere; }
.certificate-footer { display: flex; align-items: flex-end; justify-content: space-between; gap: 20mm; z-index: 1; }
.signature { display: flex; flex-direction: column; gap: 1mm; min-inline-size: 75mm; }
.signature img { max-block-size: 16mm; max-inline-size: 60mm; object-fit: contain; align-self: flex-start; }
.signature-line { block-size: .25mm; background: var(--certificate-ink); margin-block-start: -2mm; }
.signature strong { font-size: 9pt; }
.signature span { font: 8pt/1.3 -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #5f6b7b; }
.issue-date { display: flex; flex-direction: column; gap: 1mm; text-align: end; }
.issue-date strong { font-size: 10pt; }
.revoked-watermark { position: absolute; inset: 0; display: grid; place-items: center; pointer-events: none; z-index: 2; font: 800 58pt/1 -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: rgb(170 30 30 / .18); letter-spacing: .12em; transform: rotate(-18deg); text-transform: uppercase; }
[dir='rtl'] .signature img { align-self: flex-end; }
</style>
