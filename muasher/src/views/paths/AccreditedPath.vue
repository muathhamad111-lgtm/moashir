<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import { useAccreditedPathPage } from '@/composables/useAccreditedPathPage'
import { usePathRequests } from '@/composables/usePathRequests'
import { PATH_KEYS, PERIOD_STATES } from '@/constants/entityPaths'
import { ACCREDITED_STATES } from '@/utils/pathStates'
import { localizedText } from '@/utils/localizedText'
import ErrorState from '@/components/feedback/ErrorState.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import PathHeader from '@/components/paths/PathHeader.vue'
import PathHeaderCard from '@/components/paths/PathHeaderCard.vue'
import PathPrice from '@/components/paths/PathPrice.vue'
import PathPrimaryAction from '@/components/paths/PathPrimaryAction.vue'
import AccreditedPeriodCard from '@/components/paths/AccreditedPeriodCard.vue'
import PathRequestsList from '@/components/paths/PathRequestsList.vue'

const { t, locale } = useI18n()
const page = useAccreditedPathPage()
const requests = usePathRequests(PATH_KEYS.ACCREDITED)
const { status, paths, accredited, summary, offersNextPeriod, starting } = page
const visible = computed(
  () => summary.value && summary.value.state !== ACCREDITED_STATES.HIDDEN && accredited.value,
)
const prePeriodic = computed(
  () => accredited.value?.openRequest?.periodState === PERIOD_STATES.PRE_PERIODIC,
)

useHead({ title: () => t('paths.names.accredited') })
</script>

<template>
  <section class="path-page" data-testid="path-page-accredited">
    <PathHeader :path-key="PATH_KEYS.ACCREDITED" :lead="t('paths.pages.accredited.lead')">
      <template v-if="prePeriodic" #chips>
        <p class="path-header__chip" data-testid="path-pre-periodic">
          {{ localizedText(accredited.openRequest.periodLabel, locale) }}
        </p>
      </template>
      <Skeleton v-if="status === 'loading' && !paths" variant="block" :count="3" />
      <template v-else-if="visible">
        <AccreditedPeriodCard :accredited="accredited" />
        <PathHeaderCard :eyebrow="t('paths.pages.status')" test-id="path-status-card">
          <p class="path-header-card__text">{{ summary.status }}</p>
          <p v-if="summary.kit" class="path-header-card__note">
            <AppIcon name="layers" />
            {{ summary.kit }}
          </p>
          <template #actions>
            <PathPrimaryAction :action="summary.action" :busy="starting" @run="page.startFree" />
          </template>
        </PathHeaderCard>
        <PathHeaderCard :eyebrow="t('paths.pages.price')">
          <PathPrice :pricing="accredited.pricing" detailed />
          <template v-if="offersNextPeriod" #actions>
            <button
              type="button"
              class="btn btn--on-band"
              data-testid="path-apply-next"
              @click="page.applyForNextPeriod"
            >
              {{ t('paths.actions.applyNextPeriod') }}
              <AppIcon name="arrow-right" />
            </button>
          </template>
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
        @retry="page.reload"
      />
      <PathRequestsList
        :path="PATH_KEYS.ACCREDITED"
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
