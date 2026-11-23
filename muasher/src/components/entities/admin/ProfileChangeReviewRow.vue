<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'
import ProfileChangeValue from '@/components/entities/changes/ProfileChangeValue.vue'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  row: { type: Object, required: true },
  editable: { type: Boolean, default: false },
  approved: { type: Boolean, default: false },
})

const emit = defineEmits(['update:approved'])
const { t, locale } = useI18n()
const label = computed(() => localizedText(props.row.label, locale.value))
</script>

<template>
  <li class="change-review__row" :class="{ 'change-review__row--stale': row.isStale }">
    <div class="change-review__head">
      <span class="change-review__label">{{ label }}</span>
      <span v-if="row.decision" class="change-review__decision" :data-decision="row.decision">
        {{ t(`entities.profileChanges.review.decision.${row.decision}`) }}
      </span>
    </div>
    <dl class="change-review__values">
      <div class="change-review__side">
        <dt>{{ t('entities.profileChanges.review.approved') }}</dt>
        <dd><ProfileChangeValue :change="row" side="old" /></dd>
      </div>
      <div class="change-review__side change-review__side--new">
        <dt>{{ t('entities.profileChanges.review.proposed') }}</dt>
        <dd><ProfileChangeValue :change="row" side="new" /></dd>
      </div>
    </dl>
    <p v-if="row.isStale" class="change-review__stale">
      {{ t('entities.profileChanges.review.stale') }}
    </p>
    <BaseCheckbox
      v-if="editable"
      :model-value="approved"
      :name="`approve-${row.item}`"
      :label="`${t('entities.profileChanges.review.approve')} — ${label}`"
      :disabled="row.isStale"
      @update:model-value="emit('update:approved', $event)"
    />
  </li>
</template>
