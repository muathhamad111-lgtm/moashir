# scripts/

Operator utilities that are NOT part of the app bundle.

## generate-images.mjs — branded illustrations

Generates the Muasher (مؤشر) illustration set with the Gemini image model and
writes stable PNG files the CMS can point to.

### 1. Provide the API key

The script reads the key from the environment or from `.env.local` at the repo
root. Never commit the key.

```
# .env.local  (already gitignored)
GEMINI_API_KEY=your_key_here
GEMINI_IMAGE_MODEL=gemini-2.5-flash-image   # optional, this is the default
```

If `GEMINI_API_KEY` is missing the script prints a clear message and exits with
a non-zero code without calling the API. The key value is never printed.

### 2. Run it (operator only)

```
node scripts/generate-images.mjs
# or
npm run generate:images
```

It performs live API calls, so it is not run in CI or during the standard
lint/test/build checks.

### 3. Output

PNG files are written to `public/generated/` with stable names:

`hero.png`, `about.png`, `evaluation-paths.png`, `excellence-model.png`,
`fast-excellence-kit.png`, `leadership.png`, `digital-transformation.png`,
`quality.png`, `collaboration.png`, `login.png`, `register.png`,
`empty-state.png`, `error.png`.

### 4. Wire them into the UI

In the admin homepage manager, set the relevant section/item `image` field to
`/generated/<file>` (for example `/generated/hero.png`). The public
`IllustrationSlot` component then renders the real image; until an image URL is
present it shows a tasteful branded placeholder, so the app stays green with no
generated assets.
