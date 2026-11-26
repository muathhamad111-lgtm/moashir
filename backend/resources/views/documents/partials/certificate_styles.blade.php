@page { size: 297mm 210mm; margin: 0; }
.certificate { position: relative; width: 297mm; height: 210mm; box-sizing: border-box; padding: 8mm; overflow: hidden; }
.certificate__frame { height: 100%; box-sizing: border-box; border: 2.6mm solid var(--color-dark); padding: 1.8mm; }
.certificate__inner { position: relative; height: 100%; box-sizing: border-box; border: 0.45mm solid var(--color-gold); padding: 8mm 16mm 6mm; display: flex; flex-direction: column; background: radial-gradient(circle at 50% 0%, var(--color-surface) 0%, var(--color-white) 55%); }
.certificate__head { display: flex; align-items: flex-start; justify-content: space-between; min-height: 30mm; }
.certificate__logo { height: 24mm; }
.certificate__seal { position: relative; width: 40mm; margin-top: -2mm; }
.certificate__seal img { display: block; width: 100%; }
.certificate__seal-year { position: absolute; left: 0; width: 98.9%; top: 71.9%; transform: translateY(-50%); text-align: center; font-family: var(--font-latin); font-weight: 700; font-size: 3.3mm; letter-spacing: 0.25mm; line-height: 1; }
.certificate__star { width: 18mm; opacity: 0.9; }
.certificate--bronze .certificate__seal-year { color: var(--color-bronze); }
.certificate--silver .certificate__seal-year { color: var(--color-silver); }
.certificate--gold .certificate__seal-year { color: var(--color-gold); }
.certificate--diamond .certificate__seal-year { color: var(--color-diamond); }
.certificate__body { flex: 1; display: flex; flex-direction: column; justify-content: center; text-align: center; margin-top: -16mm; padding-bottom: 4mm; }
.certificate__title { font-weight: 900; font-size: 32pt; line-height: 1.25; color: var(--color-dark); }
.certificate__rule { width: 46mm; height: 0.5mm; margin: 0 auto 3.5mm; background: var(--color-gold); }
.certificate__title-en { margin: 0.5mm 0 3mm; font-family: var(--font-latin); font-weight: 600; font-size: 12pt; letter-spacing: 1.2pt; text-transform: uppercase; color: var(--color-teal-deep); }
.certificate__lead { margin: 0; font-size: 13pt; }
.certificate__entity { margin: 2mm 0 0; font-family: var(--font-heading); font-weight: 700; font-size: 25pt; line-height: 1.3; }
.certificate__entity-en { margin: 0 0 2mm; font-family: var(--font-latin); font-size: 11.5pt; color: var(--color-teal-deep); }
.certificate__statement { margin: 2mm auto 0; max-width: 215mm; font-size: 13.5pt; }
.certificate__statement strong { font-weight: 700; }
.certificate__percent { font-family: var(--font-latin); font-weight: 700; font-size: 14pt; color: var(--color-accent-text); }
.certificate__statement-en { margin: 2mm auto 0; max-width: 220mm; font-family: var(--font-latin); font-size: 10pt; color: var(--color-muted); }
.certificate__foot { display: flex; align-items: flex-end; justify-content: space-between; gap: 8mm; border-top: 0.3mm solid var(--color-border); padding-top: 3mm; }
.certificate__facts { border-collapse: collapse; font-size: 8.6pt; }
.certificate__facts th { font-weight: 400; color: var(--color-muted); white-space: nowrap; }
.certificate__facts td { padding: 0.4mm 4mm; text-align: center; font-weight: 600; white-space: nowrap; }
.certificate__facts-ar { text-align: right; }
.certificate__facts-en { text-align: left; font-family: var(--font-latin); font-size: 7.4pt; }
.certificate__note { flex: 1; margin: 0; text-align: center; font-size: 8.4pt; color: var(--color-muted); }
.certificate__note span { display: block; font-family: var(--font-latin); font-size: 7.4pt; }
.certificate__unverifiable { display: block; margin-top: 1mm; color: var(--color-bronze-text); font-weight: 700; }
.certificate__qr { margin: 0; width: 31mm; text-align: center; }
.certificate__qr img { width: 24mm; height: 24mm; }
.certificate__qr figcaption { font-size: 7pt; line-height: 1.35; color: var(--color-muted); }
.certificate__qr figcaption span { display: block; font-family: var(--font-latin); }
