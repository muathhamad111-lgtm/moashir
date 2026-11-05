<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePublicHome } from '@/composables/useHomeContent'
import DemoDataNotice from '@/components/ui/DemoDataNotice.vue'
import { MOCK_MODULES } from '@/constants/mockModules'

const GROUPS = ['winner', 'partner', 'sponsor', 'supporter']

const { t } = useI18n()
const { sectionByKey, localized, status } = usePublicHome()

const content = computed(() => sectionByKey('advanced-intro'))
const award = computed(() => content.value?.meta?.award ?? null)
const nextCycle = computed(() => content.value?.meta?.nextCycle ?? null)
const groups = computed(() =>
  GROUPS.map((group) => ({
    group,
    items: (content.value?.items ?? [])
      .filter((item) => item.meta?.group === group)
      .sort((a, b) => a.order - b.order),
  })).filter((entry) => entry.items.length),
)

const DEMO_MODULES = [MOCK_MODULES.CMS]
</script>

<template>
  <section
    v-if="status === 'success' && content"
    class="advanced-content"
    :aria-label="t('advanced.content.label')"
  >
    <DemoDataNotice scope="section" :modules="DEMO_MODULES" />
    <p v-if="content.description" class="advanced-content__description">
      {{ localized(content.description) }}
    </p>
    <div class="advanced-content__highlights">
      <article v-if="award" class="advanced-content__card">
        <h2 class="advanced-content__title">{{ localized(award.title) }}</h2>
        <p class="advanced-content__text">{{ localized(award.body) }}</p>
      </article>
      <article v-if="nextCycle" class="advanced-content__card">
        <h2 class="advanced-content__title">{{ localized(nextCycle.title) }}</h2>
        <p class="advanced-content__text">{{ localized(nextCycle.body) }}</p>
      </article>
    </div>
    <div v-for="entry in groups" :key="entry.group" class="advanced-content__group">
      <h2 class="advanced-content__title">{{ t(`advanced.content.groups.${entry.group}`) }}</h2>
      <ul class="advanced-content__list">
        <li v-for="item in entry.items" :key="item.id" class="advanced-content__item">
          <i v-if="item.icon" :class="item.icon" aria-hidden="true"></i>
          <span class="advanced-content__name">{{ localized(item.title) }}</span>
          <span class="advanced-content__note">{{ localized(item.description) }}</span>
        </li>
      </ul>
    </div>
  </section>
</template>
