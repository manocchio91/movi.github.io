# Workflow di sviluppo con Codex

## Scopo

Questo documento definisce il processo operativo permanente per completare milestone con il minimo contesto necessario. I principi guida sono **una milestone = una implementazione** e **ogni token deve produrre valore reale**.

## Protocollo di ottimizzazione token

1. All'inizio della sessione leggere una sola volta le regole ufficiali pertinenti e verificare stato, branch e remoti Git.
2. Non rileggere documenti invariati durante la stessa sessione.
3. Analizzare soltanto file, dipendenze dirette e test coinvolti dalla milestone.
4. Evitare esplorazioni, brainstorming e riepiloghi intermedi non necessari.
5. Riutilizzare convenzioni, componenti e comandi già presenti nel repository.
6. Eseguire insieme le operazioni indipendenti quando non riduce la verificabilità.
7. Produrre aggiornamenti brevi, orientati a decisioni, risultati o blocchi reali.

## Ruoli

### Product Owner

- Definisce obiettivo, requisiti, fuori scope e criteri di accettazione.
- Decide sulle ambiguità che cambiano prodotto, dati, sicurezza o perimetro.
- Esegue il Browser QA quando Codex non dispone di un browser integrato.
- Approva eventuali debiti o variazioni di scope.

### Codex

- Verifica una volta il contesto iniziale e segnala conflitti o prerequisiti mancanti.
- Implementa l'intera milestone nel perimetro autorizzato.
- Aggiorna codice, test e documentazione interessati nello stesso flusso.
- Esegue le verifiche automatiche pertinenti e corregge gli errori introdotti.
- Mantiene la working tree sotto controllo e non altera modifiche estranee.
- Consegna commit e push quando richiesti, senza merge o tag salvo istruzione esplicita.

## Flusso standard di sviluppo

1. Ricevere una milestone completa usando [MILESTONE_TEMPLATE.md](MILESTONE_TEMPLATE.md).
2. Verificare regole ufficiali, Git e file direttamente coinvolti.
3. Confermare implicitamente il perimetro; interrompersi solo davanti a una decisione bloccante non deducibile.
4. Implementare backend, frontend, mobile, test e documentazione soltanto dove richiesto.
5. Eseguire le verifiche proporzionate alle aree modificate.
6. Eseguire Browser QA oppure consegnare al Product Owner la checklist esatta.
7. Controllare diff, collegamenti, file non tracciati e assenza di modifiche estranee.
8. Creare il commit richiesto e fare push sul branch corrente, se autorizzato.
9. Inviare il report finale sintetico.

Una milestone deve produrre una sola implementazione coerente e verificabile. Correzioni necessarie alla Definition of Done fanno parte della stessa milestone; miglioramenti indipendenti diventano milestone successive.

## Browser QA

- Codex esegue il Browser QA quando è disponibile un browser integrato e l'ambiente può essere avviato.
- Se il browser integrato non è disponibile, Codex completa tutte le altre attività e si interrompe una sola volta fornendo passi, dati e risultati attesi.
- Il Product Owner restituisce esito e anomalie; Codex corregge le anomalie in scope e ripete le verifiche pertinenti.
- Le prove riutilizzabili sono definite in [CHECKLIST_QA.md](CHECKLIST_QA.md); la milestone seleziona solo quelle applicabili.

## Gestione test

- Eseguire soltanto test, lint, analisi statica e build pertinenti ai file modificati.
- Aggiungere o aggiornare test positivi, negativi, autorizzativi e di regressione richiesti dal rischio della modifica.
- Non sostituire test automatici con Browser QA né Browser QA con test automatici quando entrambi sono necessari.
- Registrare nel report comando ed esito; distinguere fallimenti introdotti, preesistenti e verifiche non eseguibili.
- Non ampliare la suite o correggere problemi estranei senza autorizzazione.

## Gestione documentazione

- Aggiornare nello stesso flusso solo i documenti resi inesatti dalla milestone.
- Documentare contratti, decisioni operative, setup e QA al livello necessario per il riuso.
- Evitare duplicazioni: preferire collegamenti alla fonte canonica.
- Aggiornare lo stato di progetto soltanto quando cambia realmente lo stato implementativo.

## Regole Git

- Verificare branch, working tree e remoti prima delle modifiche.
- Preservare modifiche preesistenti e non includere file estranei.
- Controllare almeno `git diff`, `git diff --check` e lo stato finale.
- Usare il messaggio di commit indicato dalla milestone; in assenza, adottare Conventional Commits.
- Fare push esclusivamente sul branch corrente e soltanto quando richiesto.
- Non eseguire merge, rebase distruttivi, reset, force push o creazione di tag senza autorizzazione esplicita.

## Formato del report finale

Il report deve essere breve e includere:

- file modificati;
- verifiche eseguite e relativo esito;
- Browser QA eseguito o demandato;
- hash e messaggio del commit, se creato;
- esito del push, se richiesto;
- stato della working tree;
- debiti residui o `nessuno`.

Non ripetere il contenuto della milestone e non descrivere passaggi privi di impatto sul risultato.
