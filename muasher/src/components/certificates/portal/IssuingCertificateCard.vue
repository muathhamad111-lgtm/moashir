<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ISSUING_STAGES } from '@/utils/certificates/issuingRequests'
import { ACCREDITED_STATUSES, ENTITY_EDITABLE_STATUSES } from '@/constants/accredited'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { riyadhYear } from '@/utils/riyadhYear'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  request: { type: Object, required: true },
})

const { t } = useI18n()
const isEditable = computed(() => ENTITY_EDITABLE_STATUSES.includes(props.request.status))
const target = computed(() =>
  isEditable.value
    ? { name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_FORM, params: { id: props.request.id } }
    : props.request.status === ACCREDITED_STATUSES.COMPLETED
      ? { name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_RESULT, params: { id: props.request.id } }
      : { name: ROUTE_NAMES.PATHS_ACCREDITED_REQUEST, params: { id: String(props.request.id) } },
)
const stageKey = computed(() =>
  props.request.stage === ISSUING_STAGES.ISSUING ? 'issuing' : 'review',
)
</script>

<template>
  <article class="issuing-certificate" :aria-labelledby="`issuing-${request.id}`">
    <span class="issuing-certificate__icon" aria-hidden="true">
      <AppIcon name="clock" />
    </span>
    <div class="issuing-certificate__main">
      <h3 :id="`issuing-${request.id}`" class="issuing-certificate__title">
        {{ t('portal.certificates.issuing.title') }}
        <bdi dir="ltr">{{ riyadhYear(request.since) }}</bdi>
      </h3>
      <i18n-t
        :keypath="`portal.certificates.issuing.${stageKey}`"
        tag="p"
        class="issuing-certificate__text"
        scope="global"
      >
        <template #reference>
          <bdi dir="ltr">{{ request.number }}</bdi>
        </template>
      </i18n-t>
    </div>
    <router-link class="issuing-certificate__link" :to="target">
      {{
        t(isEditable ? 'portal.certificates.issuing.continue' : 'portal.certificates.issuing.open')
      }}
      <AppIcon name="chevron-right" />
    </router-link>
  </article>
</template>
