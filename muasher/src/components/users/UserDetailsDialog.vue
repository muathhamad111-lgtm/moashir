<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import NdaUserHistory from '@/components/nda/NdaUserHistory.vue'
import { useEntityDisplay } from '@/composables/useEntityDisplay'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  user: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const { dateTime } = useEntityDisplay()

const notAdded = computed(() => t('nationalIdentity.card.missing'))
const genderLabel = computed(() =>
  props.user?.gender ? t(`nationalIdentity.gender.${props.user.gender}`) : notAdded.value,
)
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    :title="t('users.details.title')"
    :subtitle="user?.name ?? ''"
    size="md"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <dl v-if="user" class="account-page__facts" data-testid="user-details">
      <div class="account-page__fact">
        <dt>{{ t('users.fields.name') }}</dt>
        <dd>{{ user.name }}</dd>
      </div>
      <div class="account-page__fact">
        <dt>{{ t('users.fields.email') }}</dt>
        <dd>
          <bdi dir="ltr">{{ user.email }}</bdi>
        </dd>
      </div>
      <div class="account-page__fact">
        <dt>{{ t('users.details.phone') }}</dt>
        <dd>
          <bdi v-if="user.phone" dir="ltr">{{ user.phone }}</bdi>
          <span v-else class="account-page__muted">{{ notAdded }}</span>
        </dd>
      </div>
      <div class="account-page__fact national-identity-card">
        <dt>{{ t('nationalIdentity.field.label') }}</dt>
        <dd
          v-if="user.nationalIdMasked"
          class="u-ltr national-identity-card__value"
          data-testid="user-national-id"
        >
          {{ user.nationalIdMasked }}
        </dd>
        <dd v-else class="account-page__muted">{{ notAdded }}</dd>
      </div>
      <div class="account-page__fact">
        <dt>{{ t('nationalIdentity.gender.label') }}</dt>
        <dd data-testid="user-gender">{{ genderLabel }}</dd>
      </div>
    </dl>
    <p
      v-if="user?.nationalIdChangedAtAcceptanceAt"
      class="national-identity-card__pending"
      role="note"
      data-testid="user-national-id-changed"
    >
      <AppIcon name="warning-triangle" />
      <span>{{
        t('users.details.changedAtAcceptance', {
          date: dateTime(user.nationalIdChangedAtAcceptanceAt),
        })
      }}</span>
    </p>
    <NdaUserHistory v-if="user" :user="user" />
  </BaseModal>
</template>
