<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import SearchApi, { type SearchGroup, type SearchHit, type SearchResponse } from '@/api/search'
import ErpPageHeader from '@/components/erp/ErpPageHeader.vue'

const toast = useToast()
const router = useRouter()
const route = useRoute()
const { t } = useI18n()

const q = ref((route.query.q as string) ?? '')
const loading = ref(false)
const result = ref<SearchResponse | null>(null)
let timer: ReturnType<typeof setTimeout> | null = null

const groups = computed(() => result.value?.groups ?? {})
const groupOrder: SearchGroup[] = ['applicants', 'customers', 'contracts']
const groupMeta = computed<Record<SearchGroup, { title: string; icon: string; empty: string }>>(() => ({
  applicants: { title: t('educational.search.groups.applicants'), icon: 'tabler-school', empty: t('educational.search.empty.applicants') },
  customers: { title: t('educational.search.groups.customers'), icon: 'tabler-users', empty: t('educational.search.empty.customers') },
  contracts: { title: t('educational.search.groups.contracts'), icon: 'tabler-file-text', empty: t('educational.search.empty.contracts') },
}))
const totalHits = computed(() => Object.values(groups.value).reduce((sum, hits) => sum + (hits?.length ?? 0), 0))
const searched = computed(() => result.value !== null)

async function run() {
  const query = q.value.trim()

  // Mirrors the server: numeric input may be a record ID, other text needs
  // at least 2 characters to avoid matching almost every row.
  if (query === '' || (!/^\d+$/.test(query) && query.length < 2)) {
    result.value = null

    return
  }

  loading.value = true
  try {
    result.value = await SearchApi.query(query)
    router.replace({ name: 'search', query: { q: query } })
  }
  catch {
    toast.error(t('educational.search.unavailable'))
  }
  finally {
    loading.value = false
  }
}

function debounced() {
  if (timer)
    clearTimeout(timer)

  timer = setTimeout(run, 350)
}

function open(hit: SearchHit) {
  router.push(hit.route as never)
}

watch(q, debounced)
</script>

<template>
  <div class="erp-workspace">
  <ErpPageHeader :title="$t('educational.search.title')" section="applications" />
  <VCard>
    <VCardTitle>{{ $t('educational.search.title') }}</VCardTitle>
    <VCardText>
      <VTextField v-model="q" :label="$t('educational.search.label')" prepend-inner-icon="tabler-search" clearable autofocus :loading="loading" :hint="$t('educational.search.hint')" persistent-hint @keyup.enter="run" />
      <p v-if="!searched && !loading" class="text-caption text-medium-emphasis mt-4">{{ $t('educational.search.help') }}</p>
      <p v-if="searched && !loading" class="text-caption text-medium-emphasis mt-4">{{ $t('educational.search.resultSummary', { count: totalHits, query: result?.query }) }}</p>
    </VCardText>
    <VProgressLinear v-if="loading" indeterminate />
    <VCardText v-if="searched && !loading">
      <div v-for="key in groupOrder" :key="key">
        <template v-if="key in groups">
          <div class="d-flex align-center mt-2">
            <VIcon :icon="groupMeta[key].icon" class="me-2" />
            <h3>{{ groupMeta[key].title }}</h3>
            <VSpacer />
            <VChip size="small">{{ groups[key]?.length ?? 0 }}</VChip>
          </div>
          <VList v-if="groups[key]?.length">
            <VListItem v-for="hit in groups[key]" :key="`${hit.type}-${hit.id}`" :title="hit.title" :subtitle="hit.subtitle" @click="open(hit)">
              <template #append><VIcon icon="tabler-chevron-right" /></template>
            </VListItem>
          </VList>
          <p v-else class="text-caption text-medium-emphasis">{{ groupMeta[key].empty }}</p>
        </template>
      </div>
      <p v-if="!totalHits" class="text-center text-medium-emphasis py-6">{{ $t('educational.search.nothingFound') }}</p>
    </VCardText>
  </VCard>
  </div>
</template>
