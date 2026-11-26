# components/modal

Shared dialog family (`references/06-architecture.md`, Task-76). Styling lives in
`src/styles/components/_modal.scss`. No component here has its own styles.

| Component                       | Use                                                                                                                                                                                                                                                                                                                                |
| ------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `BaseModal`                     | Shell: focus trap, Esc and × close, backdrop, RTL/LTR. Props `title`, `subtitle`, `size`, `role` (`dialog` / `alertdialog`), `icon` + `tone` (centred header), `describedBy`, `closeOnBackdrop`, `dismissible` (false: no ×, Esc and the backdrop do nothing — the staff agreement). Emits `interact` on pointer or keyboard use.  |
| `DecisionDialog`                | Concise confirmation: centred icon, one question, at most one line, action verb + Cancel. Props `title`, `message`, `confirmLabel`, `cancelLabel`, `variant` (`confirm` / `warning` / `danger`), `icon`, `busy`. Default slot for a required field only. Initial focus: the action for `confirm`, Cancel for `warning` / `danger`. |
| `ConfirmModal` + `useConfirm()` | Global DecisionDialog mounted once in `App.vue`. `await confirm({ title, message, confirmLabel, cancelLabel, variant, icon })` resolves `true` / `false`.                                                                                                                                                                          |
| `FormModal`                     | Form dialog: title + subtitle (the object), fields, action first then Cancel. Prop `submitVariant` (`primary` / `danger`).                                                                                                                                                                                                         |
| `ReasonDialog`                  | FormModal with one reason field. Props `title` (the action), `subtitle` (the object), `submitLabel`, `destructive`, `optional`, `minLength`, `reasonLabel`, `reasonHint`, `action`, `actionName`.                                                                                                                                  |
| `SuccessModal` + `useSuccess()` | Global success dialog in `App.vue`. `success.show({ title, message })` after major state changes only; closes on OK, Esc, ×, or by itself after `SUCCESS_AUTO_CLOSE_MS` unless the user points at it or uses the keyboard. Toasts stay for minor saves.                                                                            |

**Layouts (`BaseModal` `layout`).** `dialog` (centred, default); `drawer` (a side panel at the inline end:
the filter panel from md up); `drawer-start` (a side panel at the inline start: the phone navigation
drawer, `layout/PhoneNavDrawer.vue`); `sheet` (a bottom sheet for phones: full width, rounded top, a drag
handle that closes it when pulled down past `SHEET_DISMISS_DISTANCE` (`composables/useSheetDrag.js`), the
body scrolling between a fixed header and footer, room for the home indicator). Every layout keeps the
focus trap, Esc, the × button and the backdrop.
