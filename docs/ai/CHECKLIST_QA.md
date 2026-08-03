# Checklist Browser QA

Usare soltanto le sezioni pertinenti alla milestone. Annotare ambiente, account/ruolo, browser, viewport ed esito; non inserire credenziali o dati sensibili nel report.

## Preparazione

- [ ] Ambiente avviato senza errori bloccanti.
- [ ] Migrazioni e seed richiesti disponibili.
- [ ] Dati di prova isolati e ripetibili.
- [ ] Console del browser e richieste di rete aperte.
- [ ] Ruoli e viewport richiesti dalla milestone identificati.

## Navigazione e rendering

- [ ] La rotta si apre direttamente e tramite navigazione interna.
- [ ] Titolo, testi, controlli e stato iniziale sono corretti.
- [ ] Loading, stato vuoto, errore e contenuto vengono mostrati correttamente.
- [ ] Nessun errore inatteso compare in console o nella rete.
- [ ] Refresh, back e forward preservano un comportamento coerente.

## Form e validazione

- [ ] Campi obbligatori, formati e limiti sono segnalati chiaramente.
- [ ] Il submit valido produce un solo effetto e un feedback visibile.
- [ ] Submit ripetuti o doppi clic non duplicano l'operazione.
- [ ] Gli errori API sono comprensibili e i dati correggibili restano disponibili.
- [ ] I campi non modificabili o non autorizzati non vengono accettati.

## Autenticazione e autorizzazione

- [ ] L'utente anonimo viene gestito come previsto.
- [ ] Ogni ruolo vede soltanto azioni e dati consentiti.
- [ ] URL diretti e identificativi di risorse altrui non aggirano le policy.
- [ ] Logout e sessione scaduta rimuovono l'accesso ai contenuti protetti.
- [ ] Dati sensibili non compaiono in URL, console o messaggi non autorizzati.

## Responsive e accessibilità essenziale

- [ ] Flusso verificato almeno nei viewport desktop e mobile applicabili.
- [ ] Nessun contenuto o controllo essenziale è tagliato o sovrapposto.
- [ ] Navigazione da tastiera, focus visibile e ordine dei controlli sono coerenti.
- [ ] Etichette, messaggi di errore e nomi accessibili sono presenti.
- [ ] Contrasto, zoom e ridimensionamento non impediscono il flusso principale.

## Regressione e chiusura

- [ ] Happy path completo verificato con dati nuovi.
- [ ] Almeno un caso negativo e un limite pertinente verificati.
- [ ] Funzioni adiacenti direttamente coinvolte non mostrano regressioni.
- [ ] Dati creati durante il QA ripuliti quando necessario.
- [ ] Esito, anomalie, evidenze e passi di riproduzione registrati sinteticamente.
