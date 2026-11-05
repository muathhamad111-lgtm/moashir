<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'
import { smsSegmentEstimate } from '@/utils/integrations/smsSegments'
import { matchesApprovedTemplate } from '@/utils/integrations/smsTemplates'
import { INTEGRATION_LIMITS } from '@/constants/integrations'

const props = defineProps({
  text: { type: String, default: '' },
  singleSegment: { type: Boolean, default: false },
  approvedBodies: { type: Array, default: null },
})

const { t, n } = useI18n()
const estimate = computed(() => smsSegmentEstimate(props.text))
const summary = computed(() =>
  t('integrations.sms.counter', {
    characters: formatNumber(n, estimate.value.characters, 'integer'),
    segments: formatNumber(n, estimate.value.segments, 'integer'),
  }),
)
const tooLong = computed(() => props.singleSegment && estimate.value.segments > 1)
const checked = computed(() => Array.isArray(props.approvedBodies) && props.text.trim() !== '')
const approved = computed(
  () => checked.value && matchesApprovedTemplate(props.text, props.approvedBodies),
)
</script>

<template>
  <div class="sms-counter">
    <span class="sms-counter__summary">{{ summary }}</span>
    <span class="sms-counter__encoding">{{
      t(`integrations.sms.encodings.${estimate.encoding}`)
    }}</span>
    <span v-if="tooLong" class="sms-counter__warning">
      <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
      {{
        t('integrations.sms.singleSegmentOnly', {
          max: INTEGRATION_LIMITS.SMS_SINGLE_SEGMENT_CHARS,
        })
      }}
    </span>
    <span
      v-if="checked"
      class="sms-counter__match"
      :class="approved ? 'is-approved' : 'is-unapproved'"
    >
      <i
        :class="approved ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-exclamation'"
        aria-hidden="true"
      ></i>
      {{
        t(approved ? 'integrations.sms.templateApproved' : 'integrations.sms.templateNotApproved')
      }}
    </span>
  </div>
</template>
