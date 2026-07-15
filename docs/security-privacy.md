# Sicurezza e privacy

## Implementato o previsto nella prima base
- Validazione server-side con Form Request.
- Autenticazione Sanctum.
- Policy per modifica e moderazione eventi.
- Rate limiting sugli endpoint auth.
- Request ID su ogni risposta API.
- Password hashing tramite Laravel.
- Campi sensibili esclusi dai log e dai payload pubblici.

## Privacy by design
- Non si raccolgono nomi o date di nascita dei bambini nel primo MVP.
- Le esigenze alimentari e di accessibilità saranno salvate solo sulla prenotazione e visibili solo all'organizzatore interessato.
- Preferenze promozionali separate dalle notifiche operative.
- Consensi versionati da introdurre con profilo partecipante e prenotazioni.

## Da completare prima della produzione
- Verifica email e reset password.
- Revoca sessioni completa.
- Upload sicuro su storage S3 compatibile.
- Audit log amministrativo completo.
- Esportazione dati personali ed eliminazione account.
- Retention policy per dati delicati delle prenotazioni.
- Backup e ripristino testato.
