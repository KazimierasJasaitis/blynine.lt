# blyninė · Dar vieną blyną.

Lithuanian adaptation of Pancake Flipper. See [UPSTREAM.md](UPSTREAM.md) for attribution.

```sh
npm ci
npm run dev
npm run build
```

Production output is written to `../zaidimas/` and served at `/zaidimas/`.
The root website may embed this same-origin page without changing the root URL.

Touch/click or Space/Up creates a pancake; hold to tilt the pan and release to
return it. Every 180° flip scores one point. Settings stay in this browser when
local storage is available. Music starts only after a user action.
