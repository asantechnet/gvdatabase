<script setup lang="ts">
import {
  getDeactivationRequests,
  reactivateAccount,
  reviewAccountDeactivation,
  type AccountDeactivationRequest,
  type DeactivationStatus,
} from '@/api/accountDeactivation'
import { canAccess } from '@/utils/authorization'
import { translateBackendValue } from '@/utils/localization'
import { useToast } from 'vue-toastification'

const toast = useToast()
const requests = ref<AccountDeactivationRequest[]>([])
const loading = ref(false)
const statusFilter = ref<DeactivationStatus | null>('pending')
const decisionDialogOpen = ref(false)
const selectedRequest = ref<AccountDeactivationRequest | null>(null)
const decision = ref<'approve' | 'reject'>('approve')
const decisionNotes = ref('')
const canReactivate = computed(() => canAccess('reactivate_user_accounts'))
const canReview = computed(() => canAccess('review_account_deactivation_requests'))

const statuses: DeactivationStatus[] = ['pending', 'approved', 'rejected', 'cancelled']
const headers = [
  { title: translate('common.fields.employee'), key: 'requester' },
  { title: translate('components.account.accountdeactivationrequests.requested'), key: 'requested_at' },
  { title: translate('common.fields.reason'), key: 'reason', sortable: false },
  { title: translate('common.fields.status'), key: 'status' },
  { title: translate('components.account.accountdeactivationrequests.reviewer'), key: 'reviewer', sortable: false },
  { title: translate('common.messages.actions'), key: 'actions', sortable: false, align: 'end' as const },
]

const displayedRequests = computed(() => requests.value.filter(item => !statusFilter.value || item.status === statusFilter.value))
const statusColor = (status: DeactivationStatus) => ({ pending: 'warning', approved: 'error', rejected: 'secondary', cancelled: 'info' }[status])
const formatDate = (date: string | null) => date
  ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(date))
  : '—'

async function loadRequests() {
  loading.value = true
  try { requests.value = await getDeactivationRequests() }
  catch (error: any) { toast.error(error.message || 'Unable to load deactivation requests.') }
  finally { loading.value = false }
}

function openDecision(item: AccountDeactivationRequest, nextDecision: 'approve' | 'reject') {
  selectedRequest.value = item
  decision.value = nextDecision
  decisionNotes.value = ''
  decisionDialogOpen.value = true
}

function closeDecision() {
  decisionDialogOpen.value = false
  selectedRequest.value = null
  decisionNotes.value = ''
}

async function submitDecision() {
  if (!selectedRequest.value || (decision.value === 'reject' && !decisionNotes.value.trim())) return
  loading.value = true
  try {
    const updated = await reviewAccountDeactivation(selectedRequest.value.id, decision.value, decisionNotes.value)
    const index = requests.value.findIndex(item => item.id === updated.id)
    if (index >= 0) requests.value[index] = updated
    toast.success(translate(decision.value === 'approve'
      ? 'components.account.accountdeactivationrequests.approved'
      : 'components.account.accountdeactivationrequests.rejected'))
    closeDecision()
  }
  catch (error: any) { toast.error(error.message || 'Unable to review this request.') }
  finally { loading.value = false }
}

async function reactivate(item: AccountDeactivationRequest) {
  if (!item.requester) return
  loading.value = true
  try {
    await reactivateAccount(item.requester.id, 'Reactivated from the deactivation review history.')
    item.requester.acc_status = 'active'
    toast.success(translate('components.account.accountdeactivationrequests.accountReactivatedTheUserCanSignInWithANewSession'))
  }
  catch (error: any) { toast.error(error.message || 'Unable to reactivate this account.') }
  finally { loading.value = false }
}

onMounted(loadRequests)
</script>

<template>
  <VCard :title="$t('components.account.accountdeactivationrequests.accountDeactivationRequests')" :loading="loading">
    <VCardText>
      <VAlert type="info" variant="tonal" class="mb-5">
        {{ $t('components.account.accountdeactivationrequests.onlyRequestsBelowYourAuthorityLevelAreShownARequesterCanNeverReviewThe') }}
      </VAlert>
      <div class="d-flex flex-wrap align-center ga-3 mb-5">
        <VSelect
          v-model="statusFilter"
          :items="statuses"
          :label="$t('common.fields.status')"
          density="compact"
          hide-details
          clearable
          class="deactivation-status-filter"
        />
        <VBtn variant="tonal" prepend-icon="tabler-refresh" :loading="loading" @click="loadRequests">{{ $t('common.actions.refresh') }}</VBtn>
      </div>
      <ResponsiveDataTable :headers="headers" :items="displayedRequests" :loading="loading" item-value="id">
        <template #item.requester="{ item }">
          <div class="py-2">
            <div class="font-weight-medium">{{ item.requester?.name }}</div>
            <div class="text-caption text-medium-emphasis">{{ item.requester?.email }}</div>
            <div class="d-flex flex-wrap ga-1 mt-1">
              <VChip v-for="role in item.requester?.roles" :key="role" size="x-small" label>{{ role.replace('_', ' ') }}</VChip>
            </div>
          </div>
        </template>
        <template #item.requested_at="{ item }">{{ formatDate(item.requested_at) }}</template>
        <template #item.reason="{ item }"><span class="text-wrap">{{ item.reason || 'No reason provided' }}</span></template>
        <template #item.status="{ item }"><VChip size="small" label :color="statusColor(item.status)">{{ item.status }}</VChip></template>
        <template #item.reviewer="{ item }">
          <div>{{ item.reviewer?.name || '—' }}</div>
          <div v-if="item.reviewed_at" class="text-caption text-medium-emphasis">{{ formatDate(item.reviewed_at) }}</div>
        </template>
        <template #item.actions="{ item }">
          <div v-if="canReview && item.status === 'pending'" class="d-flex justify-end ga-2">
            <VBtn size="small" color="error" variant="tonal" @click="openDecision(item, 'approve')">{{ $t('common.actions.approve') }}</VBtn>
            <VBtn size="small" color="secondary" variant="tonal" @click="openDecision(item, 'reject')">{{ $t('common.actions.reject') }}</VBtn>
          </div>
          <VBtn
            v-else-if="canReactivate && item.status === 'approved' && item.requester?.acc_status === 'deactivated'"
            size="small"
            color="success"
            variant="tonal"
            @click="reactivate(item)"
          >{{ $t('components.account.accountdeactivationrequests.reactivate') }}</VBtn>
        </template>
      </ResponsiveDataTable>
    </VCardText>
  </VCard>

  <VDialog :model-value="decisionDialogOpen" max-width="520" @update:model-value="value => { if (!value) closeDecision() }">
    <VCard :title="decision === 'approve' ? 'Approve deactivation' : 'Reject deactivation'">
      <VCardText>
        <VAlert :type="decision === 'approve' ? 'warning' : 'info'" variant="tonal" class="mb-5">
          <template v-if="decision === 'approve'">
            {{ $t('components.account.accountdeactivationrequests.approvalWarning', { user: selectedRequest?.requester?.name }) }}
          </template>
          <template v-else>{{ $t('components.account.accountdeactivationrequests.theAccountWillRemainActive') }}</template>
        </VAlert>
        <VTextarea
          v-model="decisionNotes"
          :label="$t('components.account.accountdeactivationrequests.decisionNotes')"
          :hint="decision === 'reject' ? 'Required when rejecting a request.' : 'Optional'"
          persistent-hint
          maxlength="2000"
          counter
          rows="3"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn variant="tonal" :disabled="loading" @click="closeDecision">{{ $t('common.actions.cancel') }}</VBtn>
        <VBtn
          :color="decision === 'approve' ? 'error' : 'secondary'"
          :disabled="decision === 'reject' && !decisionNotes.trim()"
          :loading="loading"
          @click="submitDecision"
        >{{ $t('components.account.accountdeactivationrequests.confirmDecision', { decision: translateBackendValue(decision) }) }}</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.deactivation-status-filter { max-inline-size: 240px; }
</style>
