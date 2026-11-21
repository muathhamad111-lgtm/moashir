<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  record: { type: Object, required: true },
})

const { t, locale } = useI18n()
const period = computed(() => localizedText(props.record.periodLabel, locale.value))
</script>

<template>
  <section class="kit-offer" aria-labelledby="kit-offer-title" data-testid="kit-offer">
    <span class="kit-offer__icon"><AppIcon name="layers" /></span>
    <div class="kit-offer__copy">
      <h2 id="kit-offer-title" class="kit-offer__title">{{ t('paths.kit.offer.title') }}</h2>
      <p class="kit-offer__text">
        <i18n-t keypath="paths.kit.offer.text" scope="global">
          <template #number>
            <bdi dir="ltr">{{ record.number }}</bdi>
          </template>
          <template #period>{{ period }}</template>
        </i18n-t>
      </p>
    </div>
    <router-link
      class="btn btn--primary kit-offer__action"
      :to="{ name: ROUTE_NAMES.BAG_PAY }"
      data-testid="kit-offer-action"
    >
      {{ t('paths.kit.offer.action') }}
      <AppIcon name="arrow-right" />
    </router-link>
  </section>
</template>
