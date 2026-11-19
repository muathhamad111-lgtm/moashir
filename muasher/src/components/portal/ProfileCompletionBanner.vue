<script setup>
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useActionCenterStore } from '@/stores/actionCenter'
import { ACTION_ITEM_KEYS } from '@/constants/actionCenter'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { wholePercentText } from '@/utils/percentText'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t, n } = useI18n()
const route = useRoute()
const { items } = storeToRefs(useActionCenterStore())
const titleId = useId()

// The server sends this item only while the profile is incomplete, so the banner leaves on its own.
const profileItem = computed(
  () => items.value.find((item) => item.key === ACTION_ITEM_KEYS.COMPLETE_PROFILE) ?? null,
)
const isShown = computed(
  () => Boolean(profileItem.value) && route?.name !== ROUTE_NAMES.ENTITY_PROFILE,
)
const percent = computed(() => profileItem.value?.context?.percent ?? null)
const profileRoute = { name: ROUTE_NAMES.ENTITY_PROFILE }
</script>

<template>
  <section
    v-if="isShown"
    class="profile-banner"
    :aria-labelledby="titleId"
    data-testid="profile-completion-banner"
  >
    <div class="profile-banner__inner">
      <AppIcon name="warning-triangle" class="profile-banner__icon" />
      <p class="profile-banner__text">
        <span :id="titleId" class="profile-banner__title">{{
          t('portal.profileBanner.title')
        }}</span>
        <span v-if="percent !== null" class="profile-banner__progress">
          <i18n-t keypath="portal.profileBanner.progress" scope="global" tag="span">
            <template #percent>
              <bdi dir="ltr">{{ wholePercentText(n, t, percent) }}</bdi>
            </template>
          </i18n-t>
        </span>
      </p>
      <router-link
        class="btn btn--primary btn--sm profile-banner__action"
        :to="profileRoute"
        data-testid="profile-completion-action"
      >
        {{ t('portal.profileBanner.action') }}
        <AppIcon name="chevron-right" class="profile-banner__chevron" />
      </router-link>
    </div>
  </section>
</template>
