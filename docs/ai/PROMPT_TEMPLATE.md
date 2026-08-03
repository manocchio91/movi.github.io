# Template prompt per una sessione Codex

```text
Ottimizzazione Codex

Questa chat è una sessione completa di sviluppo.

Obiettivo: massimizzare il valore prodotto e minimizzare il consumo di token.

Regole:
- esegui una sola volta la verifica iniziale prevista dal progetto: documenti ufficiali pertinenti e Git;
- non rileggere documenti invariati;
- analizza esclusivamente i file coinvolti dalla milestone;
- evita analisi esplorative e brainstorming;
- implementa tutte le aree in scope, test, documentazione e Git nello stesso flusso;
- interrompiti solo per decisioni realmente bloccanti o per Browser QA manuale se il browser integrato non è disponibile;
- non eseguire merge o creare tag;
- fornisci aggiornamenti e report finale sintetici.

Milestone

[Incollare qui una milestone compilata da docs/ai/MILESTONE_TEMPLATE.md]
```

Prima di inviare il prompt, rimuovere istruzioni non applicabili e specificare sempre verifiche, commit, push e fuori scope.
