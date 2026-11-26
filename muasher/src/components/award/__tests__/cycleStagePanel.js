import { defineComponent, h } from 'vue'
import CycleStageButtons from '../CycleStageButtons.vue'
import CycleStageActions from '../CycleStageActions.vue'

// The cycle header's stage buttons and the notices under them, together as the workspace shows them.
export const CycleStagePanel = defineComponent({
  props: {
    cycle: { type: Object, required: true },
    busy: { type: String, default: '' },
    openBlock: { type: Object, default: null },
    blocked: { type: Object, default: null },
    canReopen: { type: Boolean, default: false },
  },
  emits: ['run', 'reopen'],
  setup(props, { emit }) {
    return () =>
      h('div', { class: 'cycle-stage-panel' }, [
        h(CycleStageButtons, {
          cycle: props.cycle,
          busy: props.busy,
          openBlock: props.openBlock,
          canReopen: props.canReopen,
          onRun: (action) => emit('run', action),
          onReopen: () => emit('reopen'),
        }),
        h(CycleStageActions, {
          cycle: props.cycle,
          openBlock: props.openBlock,
          blocked: props.blocked,
        }),
      ])
  },
})
