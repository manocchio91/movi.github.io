## 🎉 MòVi

### *Vivi quello che succede.*

**Piattaforma full-stack web e mobile per scoprire, organizzare e gestire eventi ed esperienze locali.**

MòVi integra tre esperienze dedicate — **Customer, Organizer e Admin** — attraverso applicazioni web e mobile collegate a API e dati reali.

**Stato:** 🚀 Release Candidate `v2.0-rc3` — QA pre-produzione

### Funzionalità principali

* 🔎 Scoperta e ricerca degli eventi
* 🎟️ Prenotazioni e gestione disponibilità
* 📱 Esperienza Customer web e mobile
* 🎯 Dashboard Organizer
* 🛡️ Dashboard e moderazione Admin
* 👥 Gestione partecipanti
* 📷 QR Code e check-in
* 🎫 Gestione ticket e capienza
* 👤 Profili utente
* 📊 Dashboard e gestione operativa

### Stack

`Laravel` `PHP` `PostgreSQL` `Redis` `Docker`
`React` `TypeScript` `Vite`
`Flutter` `Dart` `REST API`

### Engineering

`PHPUnit` · `PHPStan` · `Laravel Pint` · `ESLint` · `Flutter Analyze/Test` · `OpenAPI` · `GitHub Actions`

> **Release Candidate:** sviluppo applicativo avanzato e quality gate completati. Deploy production ancora da effettuare.

### Presentazione e demo online

* **Presentazione:** `index.html` nella radice del repository, pubblicata con GitHub Pages.
* **Demo navigabile:** cartella `demo/`, generata con `cd frontend && npm run build:demo`. È la web app Vue con un'API simulata (`frontend/src/demo/mockApi.ts`) e dati di esempio sul Molise: si entra come cliente, organizzatore o admin senza backend e nulla viene salvato. La build normale (`npm run build`) non include l'API simulata.
