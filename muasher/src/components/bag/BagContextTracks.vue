<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import { formatDate } from '@/utils/datetime'

const props = defineProps({
  tracks: { type: Object, required: true },
})

const { t, n, locale } = useI18n()

const reference = computed(() => props.tracks?.reference ?? null)
const contextTracks = computed(() => props.tracks?.context ?? [])

function completedLabel(value) {
  if (!value) return '—'
  return formatDate(value, locale.value, { dateStyle: 'medium' })
}
</script>

<template>
  <section class="bag-context" aria-labelledby="bag-context-title">
    <h2 id="bag-context-title" class="bag-manage__section-title">
      {{ t('bag.manage.context.title') }}
    </h2>
    <p class="bag-context__note">{{ t('bag.manage.context.note') }}</p>

    <div class="bag-context__grid">
      <Card v-if="reference" class="bag-context__card bag-context__card--reference">
        <div class="bag-context__card-head">
          <span class="bag-context__track">{{
            t(`bag.manage.context.track.${reference.kind}`)
          }}</span>
          <Badge variant="success">{{ t('bag.manage.context.reference') }}</Badge>
        </div>
        <p class="bag-context__score">
          {{ t('bag.manage.context.score', { value: n(reference.scorePercent, 'default') }) }}
        </p>
        <p class="bag-context__meta">
          {{ t('bag.manage.context.completed', { date: completedLabel(reference.completedAt) }) }}
        </p>
      </Card>

      <Card
        v-for="track in contextTracks"
        :key="track.kind"
        class="bag-context__card bag-context__card--context"
      >
        <div class="bag-context__card-head">
          <span class="bag-context__track">{{ t(`bag.manage.context.track.${track.kind}`) }}</span>
          <Badge variant="muted">{{ t('bag.manage.context.contextOnly') }}</Badge>
        </div>
        <p class="bag-context__score">
          {{ t('bag.manage.context.score', { value: n(track.scorePercent, 'default') }) }}
        </p>
        <p class="bag-context__meta">
          {{ t('bag.manage.context.completed', { date: completedLabel(track.completedAt) }) }}
        </p>
      </Card>
    </div>
  </section>
</template>
