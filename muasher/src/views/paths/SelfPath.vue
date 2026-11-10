<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import { storeToRefs } from 'pinia'
import { useEntityPathsStore } from '@/stores/entityPaths'
import { usePathSummaries } from '@/composables/usePathSummaries'
import { usePathRequests } from '@/composables/usePathRequests'
import { PATH_KEYS } from '@/constants/entityPaths'
import { SELF_STATES } from '@/utils/pathStates'
import ErrorState from '@/components/feedback/ErrorState.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import PathHeader from '@/components/paths/PathHeader.vue'
import PathHeaderCard from '@/components/paths/PathHeaderCard.vue'
import PathPrice from '@/components/paths/PathPrice.vue'
import PathPrimaryAction from '@/components/paths/PathPrimaryAction.vue'
import PathRequestsList from '@/components/paths/PathRequestsList.vue'

const { t } = useI18n()
const store = useEntityPathsStore()
const { paths, status } = storeToRefs(store)
const summaries = usePathSummaries(paths)
const summary = computed(() => summaries.value?.[PATH_KEYS.SELF] ?? null)
const self = computed(() => paths.value?.self ?? null)
const requests = usePathRequests(PATH_KEYS.SELF)

useHead({ title: () => t('paths.names.self') })

function reload() {
  store.load().catch(() => null)
}

onMounted(reload)
</script>

<template>
  <section class="path-page" data-testid="path-page-self">
    <PathHeader :path-key="PATH_KEYS.SELF" :lead="t('paths.pages.self.lead')">
      <template #chips>
        <p class="path-header__chip">{{ t('paths.tile.tags.self') }}</p>
      </template>
      <Skeleton v-if="status === 'loading' && !paths" variant="block" :count="2" />
      <template v-else-if="summary && summary.state !== SELF_STATES.HIDDEN">
        <PathHeaderCard
          :eyebrow="t('paths.pages.status')"
          test-id="path-status-card"
          :data-state="summary.state"
        >
          <p class="path-header-card__text">{{ summary.status }}</p>
          <template #actions>
            <PathPrimaryAction :action="summary.action" />
          </template>
        </PathHeaderCard>
        <PathHeaderCard :eyebrow="t('paths.pages.price')">
          <PathPrice :pricing="self?.pricing ?? null" detailed />
        </PathHeaderCard>
      </template>
      <PathHeaderCard
        v-else-if="summary"
        :eyebrow="t('paths.pages.status')"
        test-id="path-unavailable-card"
      >
        <p class="path-header-card__text">{{ t('paths.summary.unavailable') }}</p>
      </PathHeaderCard>
    </PathHeader>
    <div class="path-page__body">
      <ErrorState
        v-if="status === 'error' && !paths"
        :message="t('paths.pages.loadError')"
        @retry="reload"
      />
      <PathRequestsList
        :path="PATH_KEYS.SELF"
        :rows="requests.rows.value"
        :status="requests.status.value"
        :page="requests.page.value"
        :last-page="requests.lastPage.value"
        @page="requests.load"
        @retry="requests.load()"
      />
    </div>
  </section>
</template>
