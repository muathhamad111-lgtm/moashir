<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { LEVEL_TIERS, TIER_ORDER } from '@/constants/levels'
import { riyadhDateKey } from '@/utils/datetime'
import BaseButton from '@/components/ui/BaseButton.vue'
import LevelEmblem from './LevelEmblem.vue'
import LevelValidityChip from './LevelValidityChip.vue'

const props = defineProps({
  tier: { type: String, required: true, validator: (value) => TIER_ORDER.includes(value) },
  nameId: { type: String, required: true },
  certificate: { type: Object, default: null },
  reassessment: { type: Boolean, default: false },
  action: { type: Object, required: true },
})

const { t } = useI18n()

const name = computed(() =>
  props.tier === LEVEL_TIERS.NONE ? t('level.hero.none') : t(`level.names.${props.tier}`),
)
const validity = computed(() => props.certificate?.validity ?? null)
const issuedOn = computed(() =>
  props.certificate?.issuedAt ? riyadhDateKey(props.certificate.issuedAt) : '',
)
</script>

<template>
  <div class="level-hero__identity">
    <span class="level-hero__medal">
      <LevelEmblem class="level-hero__emblem" :tier="tier" size="l" />
    </span>
    <div class="level-hero__heading">
      <h2 :id="nameId" class="level-hero__name">{{ name }}</h2>
      <p v-if="validity || reassessment" class="level-hero__chips">
        <LevelValidityChip
          v-if="validity"
          :validity="validity"
          :valid-until="certificate.validUntil"
          :days-left="certificate.daysUntilExpiry"
        />
        <span
          v-if="reassessment"
          class="level-hero__chip level-hero__chip--reassessment"
          data-testid="level-reassessment"
        >
          {{ t('level.reassessment') }}
        </span>
      </p>
    </div>
    <i18n-t
      v-if="certificate"
      keypath="level.hero.meta"
      scope="global"
      tag="p"
      class="level-hero__meta"
      data-testid="level-meta"
    >
      <template #number>
        <bdi dir="ltr">{{ certificate.number }}</bdi>
      </template>
      <template #date>
        <bdi dir="ltr">{{ issuedOn }}</bdi>
      </template>
    </i18n-t>
    <div class="level-hero__action">
      <BaseButton :to="{ name: action.routeName }" data-testid="level-hero-action">
        {{ t(action.actionKey) }}
      </BaseButton>
    </div>
  </div>
</template>
