<script setup>
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { bagRequestsService } from '@/services/bagRequestsService'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'

// Names the Kit request whose plan the page builds: the entity, the accredited result it rests on
// and its status, with the way back to the assessor's Kit requests.
const props = defineProps({
  requestId: { type: String, required: true },
})

const { t, n, locale } = useI18n()
const request = ref(null)

async function load() {
  request.value = null
  try {
    request.value = (await bagRequestsService.get(props.requestId)).data
  } catch {
    request.value = null
  }
}

onMounted(load)
watch(() => props.requestId, load)
</script>

<template>
  <header class="bag-manage__header">
    <div class="bag-manage__heading">
      <router-link class="bag-manage__back" :to="{ name: ROUTE_NAMES.BAG_EVALUATOR_REQUESTS }">
        {{ t('bag.manage.back') }}
      </router-link>
      <p class="bag-manage__eyebrow">{{ t('bag.manage.eyebrow') }}</p>
      <h1 class="bag-manage__title">{{ t('bag.manage.title') }}</h1>
      <p v-if="request" class="bag-manage__request" data-testid="bag-manage-request">
        <strong>{{ localizedText(request.entityName, locale) }}</strong>
        <span>{{
          t('bagRequests.referenceValue', {
            score: n(request.referenceSnapshot.scorePercent, 'default'),
          })
        }}</span>
        <StatusBadge :status="request.status" />
      </p>
    </div>
  </header>
</template>
