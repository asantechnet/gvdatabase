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
  { title: t('common.statuses.bad'), value: 'bad' },
  { title: t('common.statuses.good'), value: 'good' },
  { title: t('common.statuses.great'), value: 'great' },
])
const props = defineProps<{
  catalogs: Partial<Record<'countries' | 'education-levels' | 'provinces' | 'english-levels' | 'acquisition-routes', MasterDataRecord[]>>
  consultants: Employee[]
  consultantLocked?: boolean
  canReview?: boolean
  errors?: Partial<Record<'name' | 'contact' | 'service', string>>
}>()

const options = (catalog: 'countries' | 'education-levels' | 'provinces' | 'english-levels' | 'acquisition-routes') =>
  (props.catalogs[catalog] ?? [])
    .filter(row => row.is_active)
    .map(row => ({ title: row.name, value: row.id }))
</script>

<template>
  <VRow class="visitor-profile-fields">
    <!-- The 12-column grid keeps four related fields on a laptop, two on a
         tablet and one on a phone without shrinking the controls too far. -->
    <VCol cols="12" sm="6" md="3">
      <ErpField
        v-model="model.name"
        :label="$t('common.messages.visitorName')"
        :placeholder="$t('pages.contracts.visitor.visitor.placeholders.name')"
        :error-messages="errors?.name"
        required
        :show-required-rule="false"
        validate-on="submit"
      />
    </VCol>
    <VCol cols="12" sm="6" md="3">
      <ErpField
        v-model="model.contact"
        :label="$t('common.messages.contactPhone')"
        :placeholder="$t('pages.contracts.visitor.visitor.placeholders.contact')"
        :error-messages="errors?.contact"
        required
        :show-required-rule="false"
        validate-on="submit"
      />
    </VCol>
    <VCol cols="12" sm="6" md="3">
      <ErpField
        v-model="model.email"
        :label="$t('common.fields.email')"
        type="email"
        prepend-inner-icon="tabler-mail"
        :placeholder="$t('pages.contracts.visitor.visitor.placeholders.email')"
      />
    </VCol>
    <VCol cols="12" sm="6" md="3">
      <ErpField
        v-model="model.service"
        kind="select"
        :items="serviceOptions"
        :label="$t('common.messages.service')"
        :placeholder="$t('pages.contracts.visitor.visitor.placeholders.service')"
        :error-messages="errors?.service"
        required
        :show-required-rule="false"
        validate-on="submit"
      />
    </VCol>
    <VCol cols="12" sm="6" md="3">
      <ErpField
        v-model="model.education_level_id"
        kind="select"
        :items="options('education-levels')"
        :label="$t('educational.educationLevel')"
        :placeholder="$t('pages.contracts.visitor.visitor.placeholders.educationLevel')"
        clearable
      />
    </VCol>
    <VCol cols="12" sm="6" md="3">
      <ErpField
        v-model="model.age"
        type="number"
        min="1"
        max="120"
        :label="$t('pages.contracts.visitor.visitor.age')"
        :placeholder="$t('pages.contracts.visitor.visitor.placeholders.age')"
      />
    </VCol>
    <VCol cols="12" sm="6" md="3">
      <ErpField
        v-model="model.grade_score"
        type="number"
        min="0"
        max="100"
        step="0.01"
        suffix="%"
        :label="$t('pages.contracts.visitor.visitor.gradeScoreLabel')"
        :placeholder="$t('pages.contracts.visitor.visitor.placeholders.gradeScore')"
        clearable
      />
    </VCol>
    <VCol cols="12" sm="6" md="3">
      <ErpField
        v-model="model.province_id"
        kind="select"
        :items="options('provinces')"
        :label="$t('pages.contracts.visitor.visitor.province')"
        :placeholder="$t('pages.contracts.visitor.visitor.placeholders.province')"
        clearable
      />
    </VCol>
    <VCol cols="12" sm="6" md="3">
      <ErpField
        v-model="model.english_level_id"
        kind="select"
        :items="options('english-levels')"
        :label="$t('pages.contracts.visitor.visitor.englishLevel')"
        :placeholder="$t('pages.contracts.visitor.visitor.placeholders.englishLevel')"
        clearable
      />
    </VCol>
    <VCol cols="12" sm="6" md="3">
      <ErpField
        v-model="model.acquisition_route_id"
        kind="select"
        :items="options('acquisition-routes')"
        :label="$t('pages.contracts.visitor.visitor.acquisitionRoute')"
        :placeholder="$t('pages.contracts.visitor.visitor.placeholders.acquisitionRoute')"
        clearable
      />
    </VCol>
    <VCol cols="12" sm="6" md="3">
      <ErpField
        v-model="model.consultant_employee_id"
        kind="autocomplete"
        :items="consultants"
        item-title="name"
        item-value="id"
        :label="$t('pages.contracts.visitor.visitor.consultant')"
        :placeholder="$t('pages.contracts.visitor.visitor.placeholders.consultant')"
        :disabled="consultantLocked"
        :clearable="!consultantLocked"
      />
    </VCol>
    <VCol cols="12" sm="6" md="3">
      <ErpField
        v-model="model.country_id"
        kind="select"
        :items="options('countries')"
        :label="$t('common.fields.country')"
        :placeholder="$t('pages.contracts.visitor.visitor.placeholders.country')"
        clearable
      />
    </VCol>
    <VCol v-if="canReview" cols="12" md="6">
      <ErpField
        v-model="model.consultant_review"
        kind="select"
        :items="reviewOptions"
        :label="$t('pages.contracts.visitor.visitor.consultantReview')"
        :placeholder="$t('pages.contracts.visitor.visitor.placeholders.consultantReview')"
        clearable
      />
    </VCol>
    <VCol cols="12" md="6">
      <ErpField
        v-model="model.notes"
        :label="$t('common.crmUi.visitorNotes')"
        :placeholder="$t('pages.contracts.visitor.visitor.placeholders.notes')"
      />
    </VCol>
  </VRow>
</template>

<style scoped>
.visitor-profile-fields {
  --v-gutter-x: 12px;
  --v-gutter-y: 12px;
}
</style>
