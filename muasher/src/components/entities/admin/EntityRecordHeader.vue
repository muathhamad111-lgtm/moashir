<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import FrozenBadge from '@/components/entities/FrozenBadge.vue'
import LevelBadge from '@/components/level/LevelBadge.vue'
import EntityHeaderLogo from './EntityHeaderLogo.vue'
import EntityRecordActions from './EntityRecordActions.vue'
import { useEntityDisplay } from '@/composables/useEntityDisplay'
import { displayTierOf, reassessmentOf } from '@/utils/levelTier'
import { ROUTE_NAMES } from '@/constants/routeNames'

// The fixed top of the staff entity record: back link, logo, name, status and current level, then the actions.
const props = defineProps({
  entity: { type: Object, required: true },
  refreshLogo: { type: Function, required: true },
  // The level shows as a badge here unless the record shows it as the ribbon under the header.
  showsLevelBadge: { type: Boolean, default: true },
})

const emit = defineEmits(['action'])
const { t } = useI18n()
const { localized } = useEntityDisplay()
const hasOpenRequest = computed(() => props.entity.hasOpenAccreditedRequest === true)
const tier = computed(() => displayTierOf(props.entity, hasOpenRequest.value))
const backRoute = computed(() =>
  props.entity.isDeleted ? ROUTE_NAMES.ADMIN_ENTITIES_DELETED : ROUTE_NAMES.ADMIN_ENTITIES,
)
</script>

<template>
  <div class="entity-head">
    <router-link class="entity-record__back" :to="{ name: backRoute }">
      <AppIcon name="arrow-left" />
      {{ t(entity.isDeleted ? 'entities.record.backToDeleted' : 'entities.record.backToList') }}
    </router-link>
    <header class="entity-head__main">
      <EntityHeaderLogo
        :logo="entity.logo ?? null"
        :entity-name="localized(entity.name)"
        :refresh="refreshLogo"
      />
      <div class="entity-head__identity">
        <h1 class="entity-record__title">{{ localized(entity.name) }}</h1>
        <p class="entity-head__meta">
          <span class="entity-record__reference u-ltr">{{ entity.reference }}</span>
          <StatusBadge :status="entity.status" />
          <FrozenBadge v-if="entity.isFrozen" />
          <LevelBadge
            v-if="showsLevelBadge"
            :tier="tier"
            size="s"
            :reassessment="reassessmentOf(tier, hasOpenRequest)"
            :certificate="entity.lastLevel"
          />
        </p>
      </div>
      <EntityRecordActions
        class="entity-head__actions"
        :entity="entity"
        @action="emit('action', $event)"
      />
    </header>
  </div>
</template>
