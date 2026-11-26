<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { PASSWORD_LENGTH } from '@/constants/entityEnums'

const props = defineProps({
  id: { type: String, required: true },
  withHistory: { type: Boolean, default: false },
  password: { type: String, default: null },
  confirmation: { type: String, default: null },
})

const { t } = useI18n()

const tracksInput = computed(() => props.password !== null)

const rules = computed(() => {
  const password = props.password ?? ''
  const list = [
    {
      key: 'length',
      text: t('auth.passwordPolicy.length', PASSWORD_LENGTH),
      met: password.length >= PASSWORD_LENGTH.MIN && password.length <= PASSWORD_LENGTH.MAX,
    },
  ]
  if (props.confirmation !== null) {
    list.push({
      key: 'match',
      text: t('auth.passwordPolicy.match'),
      met: password.length > 0 && password === props.confirmation,
    })
  }
  list.push({ key: 'breached', text: t('auth.passwordPolicy.breached') })
  if (props.withHistory) list.push({ key: 'history', text: t('auth.passwordPolicy.history') })
  list.push({ key: 'unique', text: t('auth.passwordPolicy.unique') })
  return list
})

function stateOf(rule) {
  if (!tracksInput.value || rule.met === undefined) return 'info'
  return rule.met ? 'met' : 'pending'
}
</script>

<template>
  <div :id="id" class="password-policy" :class="{ 'password-policy--live': tracksInput }">
    <p class="password-policy__title">{{ t('auth.passwordPolicy.title') }}</p>
    <ul class="password-policy__list">
      <li
        v-for="rule in rules"
        :key="rule.key"
        class="password-policy__rule"
        :data-rule="rule.key"
        :data-state="stateOf(rule)"
      >
        <template v-if="stateOf(rule) !== 'info'">
          <AppIcon v-if="rule.met" name="check-circle" class="password-policy__mark" />
          <span v-else class="password-policy__mark password-policy__mark--open"></span>
          <span class="password-policy__state">
            {{ rule.met ? t('auth.passwordPolicy.met') : t('auth.passwordPolicy.pending') }}
          </span>
        </template>
        {{ rule.text }}
      </li>
    </ul>
  </div>
</template>
