<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useCriteria } from '@/composables/useCriteria'
import { useCriteriaChangeFormat } from '@/composables/useCriteriaChangeFormat'
import { CHANGE_TAGS, tagsOf } from '@/utils/criteriaChangeView'

// One changed item: what it is, each changed field as «before → after», and what it does to scores.
const props = defineProps({
  change: { type: Object, required: true },
})

const { t } = useI18n()
const { localized } = useCriteria()
const { fieldRows } = useCriteriaChangeFormat()

const TAG_VARIANTS = {
  [CHANGE_TAGS.ADDED]: 'info',
  [CHANGE_TAGS.REMOVED]: 'danger',
  [CHANGE_TAGS.NEEDS_NEW_SCORE]: 'warning',
  [CHANGE_TAGS.KEEPS_SCORE]: 'success',
  [CHANGE_TAGS.TEXT_ONLY]: 'muted',
}

const isWhole = computed(() => ['added', 'removed'].includes(props.change.change))
const rows = computed(() => (isWhole.value ? [] : fieldRows(props.change)))
const tags = computed(() => tagsOf(props.change))
</script>

<template>
  <li class="criteria-change">
    <div class="criteria-change__node">
      <bdi class="criteria-code">{{ change.code }}</bdi>
      <span class="criteria-change__name">{{ localized(change.label) || change.code }}</span>
      <span class="criteria-change__kind">
        {{ t(`criteria.publish.preview.nodeTypes.${change.nodeType}`) }}
      </span>
    </div>
    <p v-if="isWhole" class="criteria-change__whole">
      {{ t(`criteria.review.whole.${change.change}`) }}
    </p>
    <dl v-else class="criteria-change__fields">
      <div v-for="row in rows" :key="row.field" class="criteria-change__field">
        <dt>{{ row.label }}</dt>
        <dd>
          <del class="criteria-change__before"
            ><bdi :dir="row.dir">{{ row.before }}</bdi></del
          >
          <AppIcon name="arrow-right" class="criteria-change__arrow" />
          <span class="criteria-change__sr">{{ t('criteria.review.becomes') }}</span>
          <ins class="criteria-change__after"
            ><bdi :dir="row.dir">{{ row.after }}</bdi></ins
          >
        </dd>
      </div>
    </dl>
    <div class="criteria-change__tags">
      <Badge v-for="tag in tags" :key="tag" :variant="TAG_VARIANTS[tag]">
        {{ t(`criteria.review.tags.${tag}`) }}
      </Badge>
    </div>
  </li>
</template>
