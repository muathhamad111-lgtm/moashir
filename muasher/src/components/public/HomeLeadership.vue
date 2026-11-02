<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import IllustrationSlot from '@/components/public/IllustrationSlot.vue'

const props = defineProps({
  section: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const { t } = useI18n()

const comingSoon = computed(() => Boolean(props.section.meta?.comingSoon))
const leadershipImage = computed(() => props.section.image || null)
const leadershipAlt = computed(
  () => props.localized(props.section.title) || t('illustrations.leadership'),
)
</script>

<template>
  <div class="home-section home-leadership">
    <div class="home-section__container">
      <article class="home-leadership__card">
        <IllustrationSlot
          v-if="leadershipImage"
          class="home-leadership__media"
          :src="leadershipImage"
          :alt="leadershipAlt"
          variant="compact"
        />
        <span class="home-leadership__icon" aria-hidden="true">
          <i :class="section.icon || 'fa-solid fa-medal'"></i>
        </span>
        <div class="home-leadership__body">
          <div class="home-leadership__head">
            <h2 class="home-leadership__title">{{ localized(section.title) }}</h2>
            <Badge v-if="comingSoon" variant="info">
              {{ $t('public.home.comingSoon') }}
            </Badge>
          </div>
          <p v-if="section.description" class="home-leadership__desc">
            {{ localized(section.description) }}
          </p>
        </div>
      </article>
    </div>
  </div>
</template>
