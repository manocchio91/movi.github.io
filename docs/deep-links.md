# Event sharing and deep links

- Canonical public event URL: `{FRONTEND_URL}/customer/events/{event_ulid}`.
- The web SPA accepts that route directly and updates its canonical link after loading the event.
- Flutter is prepared for direct event routes and the custom URI `movi:///customer/events/{event_ulid}` on Android and iOS.

HTTPS App Links/Universal Links are not active until a production domain is chosen. At deploy time, add the HTTPS host to the Android intent filter and iOS Associated Domains, then publish `assetlinks.json` and `apple-app-site-association` on that domain. Until those external files are verified, shared HTTPS links correctly open the web detail only.
