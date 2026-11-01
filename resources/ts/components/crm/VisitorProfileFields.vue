<script setup lang="ts">
import ErpField from '@/components/erp/ErpField.vue'
import type { MasterDataRecord } from '@/api/educationalMasterData'
import type { Employee } from '@/api/employee'
import type { VisitorFormData } from '@/api/visitor'
import { BUILT_IN_SERVICES } from '@/api/applicants'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const model = defineModel<VisitorFormData>({ required: true })
const { t } = useI18n()
// Visitors share the case vocabulary, so the service is a fixed choice rather
// than free text that would fragment reporting.
const serviceOptions = computed(() => BUILT_IN_SERVICES.map(service => ({
  title: t(`educational.builtInServices.${service === 'test_offer' ? 'testOffer' : service}`),
  value: service,
})))
const reviewOptions = computed(() => [
  { title: t('common.statuses.good'), value: 'good' },
  { title: t('common.statuses.bad'), value: 'bad' },
  { title: t('common.statuses.great'), value: 'great' },
])
const props = defineProps<{
  catalogs: Partial<Record<'education-levels' | 'provinces' | 'english-levels' | 'acquisition-routes', MasterDataRecord[]>>
  consultants: Employee[]
  consultantLocked?: boolean
  canReview?: boolean
  errors?: Partial<Record<'name' | 'contact' | 'service', string>>
}>()

const options = (catalog: 'education-levels' | 'provinces' | 'english-levels' | 'acquisition-routes') =>
  (props.catalogs[catalog] ?? [])
    .filter(row => row.is_active)
    .map(row => ({ title: row.name, value: row.id }))
</script>

<template>
  <VRow>
    <VCol cols="12" md="4">
      <ErpField
        v-model="model.name"
        :label="$t('common.messages.visitorName')"
        :placeholder="$t('common.messages.enterVisitorName')"
        :error-messages="errors?.name"
        required
      />
    </VCol>
    <VCol cols="12" md="4">
      <ErpField
        v-model="model.contact"
        :label="$t('common.messages.contactPhone')"
        :placeholder="$t('common.messages.enterPhoneNumber')"
        :error-messages="errors?.contact"
        required
      />
    </VCol>
    <VCol cols="12" md="4">
      <ErpField
        v-model="model.email"
        :label="$t('common.fields.email')"
        type="email"
        prepend-inner-icon="tabler-mail"
      />
    </VCol>
    <VCol cols="12" md="4">
      <ErpField
        v-model="model.service"
        kind="select"
        :items="serviceOptions"
        :label="$t('common.messages.service')"
        :error-messages="errors?.service"
        required
      />
    </VCol>
    <VCol cols="12" sm="6" md="4">
      <ErpField
        v-model="model.education_level_id"
        kind="select"
        :items="options('education-levels')"
        :label="$t('educational.educationLevel')"
        clearable
      />
    </VCol>
    <VCol cols="6" md="2">
      <ErpField
        v-model="model.age"
        type="number"
        min="1"
        max="120"
        :label="$t('pages.contracts.visitor.visitor.age')"
        :hint="$t('pages.contracts.visitor.visitor.ageHint')"
        persistent-hint
      />
    </VCol>
    <VCol cols="6" md="2">
      <ErpField
        v-model="model.grade_score"
        type="number"
        min="0"
        max="100"
        step="0.01"
        suffix="%"
        :label="$t('pages.contracts.visitor.visitor.gradeScoreLabel')"
        :hint="$t('pages.contracts.visitor.visitor.gradeScoreHint')"
        persistent-hint
        clearable
      />
    </VCol>
    <VCol cols="12" sm="6" md="4">
      <ErpField
        v-model="model.province_id"
        kind="select"
        :items="options('provinces')"
        :label="$t('pages.contracts.visitor.visitor.province')"
        clearable
      />
    </VCol>
    <VCol cols="12" sm="6" md="4">
      <ErpField
        v-model="model.english_level_id"
        kind="select"
        :items="options('english-levels')"
        :label="$t('pages.contracts.visitor.visitor.englishLevel')"
        clearable
      />
    </VCol>
    <VCol cols="12" sm="6" md="4">
      <ErpField
        v-model="model.acquisition_route_id"
        kind="select"
        :items="options('acquisition-routes')"
        :label="$t('pages.contracts.visitor.visitor.acquisitionRoute')"
        :hint="$t('pages.contracts.visitor.visitor.acquisitionRouteHint')"
        persistent-hint
        clearable
      />
    </VCol>
    <VCol cols="12" sm="6" md="4">
      <VAutocomplete
        v-model="model.consultant_employee_id"
        :items="consultants"
        item-title="name"
        item-value="id"
        :label="$t('pages.contracts.visitor.visitor.consultant')"
        :placeholder="$t('pages.contracts.visitor.visitor.consultant')"
        :disabled="consultantLocked"
        :clearable="!consultantLocked"
        :hint="consultantLocked ? $t('agencyStaff.counselorLockedToYou') : $t('agencyStaff.optionalCounselor')"
        persistent-hint
      />
    </VCol>
    <VCol v-if="canReview" cols="12" sm="6" md="4">
      <ErpField
        v-model="model.consultant_review"
        kind="select"
        :items="reviewOptions"
        :label="$t('pages.contracts.visitor.visitor.consultantReview')"
        :hint="$t('pages.contracts.visitor.visitor.consultantReviewHint')"
        persistent-hint
        clearable
      />
    </VCol>
    <VCol cols="12">
      <ErpField
        v-model="model.notes"
        kind="textarea"
        :label="$t('common.crmUi.visitorNotes')"
        rows="2"
        :hint="$t('common.crmUi.visitorNotesHint')"
        persistent-hint
      />
    </VCol>
  </VRow>
</template>
