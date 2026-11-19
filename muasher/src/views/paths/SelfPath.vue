<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import { storeToRefs } from 'pinia'
import { useEntityPathsStore } from '@/stores/entityPaths'
import { usePathSummaries } from '@/composables/usePathSummaries'
import { usePathRequests } from '@/composables/usePathRequests'
import { PATH_KEYS } from '@/constants/entityPaths'
import { SELF_STATES, isFreePath } from '@/utils/pathStates'
import ErrorState from '@/components/feedback/ErrorState.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import PathHeader from '@/components/paths/PathHeader.vue'
import PathPrice from '@/components/paths/PathPrice.vue'
import PathPrimaryAction from '@/components/paths/PathPrimaryAction.vue'
import PathRequestsList from '@/components/paths/PathRequestsList.vue'
import { usePathNames } from '@/composables/usePathNames'

const { t } = useI18n()
const { pathName } = usePathNames()
const store = useEntityPathsStore()
const { paths, status } = storeToRefs(store)
const summaries = usePathSummaries(paths)
const summary = computed(() => summaries.value?.[PATH_KEYS.SELF] ?? null)
const self = computed(() => paths.value?.self ?? null)
const requests = usePathRequests(PATH_KEYS.SELF)
const isFree = computed(() => isFreePath(self.value))

useHead({ title: () => pathName('self') })

function reload() {
  store.load().catch(() => null)
}

onMounted(reload)
</script>

<template>
  <section class="path-page" data-testid="path-page-self">
    <PathHeader :path-key="PATH_KEYS.SELF" variant="compact">
      <template #chips>
        <div class="path-header__chips">
          <p class="path-header__chip">{{ t('paths.tile.tags.self') }}</p>
          <p
            v-if="isFree"
            class="path-header__chip path-header__chip--free"
            data-testid="path-free-chip"
          >
            <AppIcon name="check-circle" />
            {{ t('paths.price.free') }}
          </p>
        </div>
      </template>
      <Skeleton v-if="status === 'loading' && !paths" class="path-header__skeleton" />
      <template v-else-if="summary && summary.state !== SELF_STATES.HIDDEN">
        <PathPrice v-if="!isFree" :pricing="self?.pricing ?? null" />
        <div class="path-header__status" data-testid="path-status-card" :data-state="summary.state">
          <p class="path-header__status-text">{{ summary.brief }}</p>
          <PathPrimaryAction :action="summary.action" />
        </div>
      </template>
      <p v-else-if="summary" class="path-header__status-text" data-testid="path-unavailable-card">
        {{ t('paths.summary.unavailable') }}
      </p>
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
