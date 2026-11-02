<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  entity: { type: Object, required: true },
  context: { type: Array, default: () => [] },
})

const { t, te, locale } = useI18n()

function enumLabel(prefix, value) {
  return value && te(`${prefix}.${value}`) ? t(`${prefix}.${value}`) : value
}

const facts = computed(() =>
  [
    { key: 'entityType', value: enumLabel('entities.enums.entityType', props.entity.entityType) },
    { key: 'developmentSector', value: enumLabel('entities.enums.developmentSector', props.entity.developmentSector) },
    { key: 'orgSize', value: enumLabel('entities.enums.orgSize', props.entity.orgSize) },
    { key: 'foundingYear', value: props.entity.foundingYear },
    { key: 'region', value: localizedText(props.entity.region?.name, locale.value) },
    { key: 'city', value: localizedText(props.entity.city?.name, locale.value) },
    { key: 'employeesCount', value: props.entity.employeesCount },
    { key: 'annualBeneficiariesCount', value: props.entity.annualBeneficiariesCount },
  ].filter((fact) => fact.value !== null && fact.value !== undefined && fact.value !== ''),
)
</script>

<template>
  <Card class="review-entity">
    <template #header><h2 class="card__title-text">{{ t('review.entityTitle') }}</h2></template>
    <dl class="review-entity__facts">
      <div v-for="fact in facts" :key="fact.key" class="review-entity__fact">
        <dt>{{ t(`review.entity.${fact.key}`) }}</dt>
        <dd>{{ fact.value }}</dd>
      </div>
    </dl>
    <div v-if="context.length" class="review-entity__context">
      <p class="review-entity__context-title">{{ t('review.contextTitle') }}</p>
      <ul class="review-entity__context-list">
        <li v-for="item in context" :key="item.id" class="review-entity__context-item">
          <span>{{ t(`requests.paths.${item.path}`) }}</span>
          <bdi dir="ltr">{{ item.number }}</bdi>
          <span v-if="item.percent" class="u-ltr">{{ item.percent }}%</span>
        </li>
      </ul>
    </div>
  </Card>
</template>
