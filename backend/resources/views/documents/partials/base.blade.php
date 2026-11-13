html, body { margin: 0; padding: 0; }
body { font-family: var(--font-body); color: var(--color-dark); font-size: 10.5pt; line-height: 1.55; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
h1, h2, h3 { font-family: var(--font-heading); margin: 0; }
.ltr { direction: ltr; unicode-bidi: isolate; }
.latin { font-family: var(--font-latin); }
.muted { color: var(--color-muted); }
.watermark { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; z-index: 10; }
.watermark span { transform: rotate(-24deg); font-family: var(--font-heading); font-weight: 900; font-size: 84pt; letter-spacing: 4pt; color: var(--color-watermark); white-space: nowrap; }
