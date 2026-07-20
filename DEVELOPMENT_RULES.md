# Regole di sviluppo APP EVENTI

> Queste regole e `PROJECT_STATE.md` devono essere aggiornati quando cambia il processo o lo stato reale del repository.

## Strategia branch

- Non lavorare direttamente su `main`.
- Creare o usare un branch dedicato a una sola attività coerente.
- Verificare sempre il branch prima di modificare file.
- Non eseguire merge su `main` senza collaudo e autorizzazione esplicita.

## Flusso di sviluppo

1. Definire un'attività circoscritta.
2. Verificare branch, HEAD e working tree.
3. Analizzare codice, dati, API e test esistenti.
4. Riprodurre il problema o confermare lo stato reale.
5. Modificare il minimo indispensabile.
6. Aggiornare test e documentazione insieme al codice.
7. Eseguire le verifiche automatiche.
8. Eseguire il collaudo browser quando il flusso ha una UI.
9. Committare e pubblicare solo uno stato stabile e autorizzato.

Quando un'integrazione frontend dipende da un contratto non verificato, validare prima backend e API.

## Criteri di completamento

Una funzione è conclusa quando:

- il comportamento richiesto è implementato end-to-end;
- validazioni e autorizzazioni sono presenti;
- casi positivi e negativi rilevanti sono coperti;
- i test automatici sono verdi;
- il collaudo browser è completato, se applicabile;
- OpenAPI e documentazione sono aggiornati quando cambia il contratto;
- non restano dati demo nel flusso dichiarato reale;
- `PROJECT_STATE.md` riflette lo stato finale.

## Regole per i test

- Backend: eseguire test Laravel, test mirati del dominio modificato, Pint e PHPStan.
- Frontend web: eseguire lint e build quando viene modificato.
- Flutter: eseguire `flutter analyze`, `flutter test` e la build richiesta dal task.
- Non alterare test isolati per nascondere una regressione.
- Aggiungere test per il bug prima o insieme alla correzione quando possibile.
- Mantenere separati database di test e dati QA locali.

## Browser QA

- Eseguire prima le verifiche automatiche.
- Usare dati QA identificabili e riproducibili.
- Acquisire payload API reali quando disponibilità, stati o autorizzazioni influenzano la UI.
- Verificare il percorso corretto, il ruolo autenticato e lo stato aggiornato dei provider.
- Non dichiarare superato un collaudo che non è stato eseguito.
- Non correggere la UI per compensare dati QA incoerenti o un errore backend.

## Commit

- Un commit deve rappresentare un'attività stabile e coerente.
- Controllare `git diff` e `git diff --check` prima del commit.
- Non includere modifiche estranee.
- Usare messaggi chiari che descrivano il risultato.
- Non creare commit senza autorizzazione quando il task lo vieta.

## Tag

- Creare tag soltanto per milestone validate.
- Il tag deve puntare al commit effettivamente verificato.
- Non spostare o ricreare tag pubblicati senza una decisione esplicita.

## Push

- Pubblicare soltanto il branch previsto.
- Verificare remote e autenticazione prima del push.
- Non eseguire force push salvo autorizzazione esplicita e motivata.
- Non pubblicare tag non validati.

## Dati QA

- Non cancellare o alterare dati applicativi non QA.
- Rendere idempotenti script e query di riallineamento quando possibile.
- Conservare booking e relazioni esistenti salvo requisito contrario esplicito.
- Per eventi con una sola tipologia attiva, evitare disponibilità evento positiva con contingente ticket esaurito, salvo scenario QA intenzionale e documentato.
- Verificare sempre valori prima e dopo una modifica dati.
- Non introdurre un seeder permanente per una correzione locale occasionale se non necessario.

## Diagnosi e gestione degli errori

- Non modificare dati o codice senza aver compreso la causa.
- Distinguere tra bug applicativo, dato QA incoerente, cache/provider non aggiornato e uso della schermata sbagliata.
- Non mascherare errori backend con fallback o dati simulati nel frontend.
- Non simulare nel frontend funzionalità non realmente supportate dal backend.
- Conservare il formato uniforme degli errori API: `message`, `errors`, `request_id`.

## Inizio sessione

1. Aprire la root del repository.
2. Leggere `PROJECT_STATE.md` e il task corrente.
3. Eseguire:

   ```bash
   git status
   git branch --show-current
   git log --oneline -5
   git diff --check
   ```

4. Controllare file non tracciati e modifiche già presenti.
5. Leggere le istruzioni `AGENTS.md`.
6. Definire il perimetro e analizzare prima di modificare.

La verifica iniziale dello stato Git deve inoltre rispettare queste regole:

- non richiedere la corrispondenza esatta tra HEAD e un hash salvato in `PROJECT_STATE.md`;
- verificare invece branch, working tree, cronologia recente e coerenza funzionale;
- un HEAD successivo dovuto a commit documentali non è un'anomalia;
- fermarsi soltanto in presenza di modifiche inattese, branch errato, divergenze remote o regressioni reali.

## Fine sessione

1. Eseguire test e controlli proporzionati alle modifiche.
2. Eseguire `git diff --check` e rivedere il diff.
3. Aggiornare `PROJECT_STATE.md` con versione, task, problemi e prossimo passo.
4. Indicare file modificati, verifiche eseguite e limiti del collaudo.
5. Committare, taggare o pubblicare soltanto se richiesto e autorizzato.

## Recupero dopo un'interruzione

1. Non assumere che il working tree sia pulito.
2. Eseguire i comandi di inizio sessione.
3. Leggere `PROJECT_STATE.md` e confrontarlo con Git; Git e codice reale prevalgono se la documentazione è obsoleta.
4. Ispezionare il diff senza scartare modifiche esistenti.
5. Identificare quali verifiche erano già concluse e quali devono essere ripetute.
6. Riprendere dal più piccolo passo sicuro, senza ricominciare o sovrascrivere lavoro valido.
