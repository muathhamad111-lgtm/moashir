<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import ReviewEntityCard from '@/components/review/ReviewEntityCard.vue'
import { ENTITY_VISIBILITY } from '@/constants/committee'
import { committeeEntityLabel } from '@/utils/award/committeeEntity'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  entity: { type: Object, required: true },
})

const { t, locale } = useI18n()
const isFull = computed(() => props.entity.visibility === ENTITY_VISIBILITY.FULL)
</script>

<template>
  <ReviewEntityCard v-if="isFull" :entity="entity" />
  <Card v-else class="review-entity committee-entity">
    <template #header
      ><h2 class="card__title-text">{{ t('review.entityTitle') }}</h2></template
    >
    <dl class="review-entity__facts">
      <div class="review-entity__fact">
        <dt>{{ t(`award.entity.${entity.visibility}`) }}</dt>
        <dd>
          <bdi :class="{ 'u-ltr': entity.code }">{{ committeeEntityLabel(entity, locale) }}</bdi>
        </dd>
      </div>
      <div v-if="entity.region" class="review-entity__fact">
        <dt>{{ t('review.entity.region') }}</dt>
        <dd>{{ localizedText(entity.region.name, locale) }}</dd>
      </div>
    </dl>
    <p class="committee-entity__note">{{ t(`award.entity.notes.${entity.visibility}`) }}</p>
  </Card>
</template>
