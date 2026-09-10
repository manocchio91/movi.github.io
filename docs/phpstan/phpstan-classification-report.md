# PHPStan Classification Report

## Stato iniziale

- Errori totali: **278**
- File analizzati: **119**
- File con errori: **36**
- Livello PHPStan: **5**
- PHPStan: **2.2.5**
- Larastan: **3.10.0**
- Laravel Framework: **11.55.0**
- Commit corrente: **4f3af08b222d7120c83f34c1f5a6c26c28d518ff**
- Tag di ripristino: **v0.1-backend-stable**
- Configurazione: l'estensione `vendor/larastan/larastan/extension.neon` viene caricata correttamente; non sono presenti baseline o `ignoreErrors`.

L'output completo dell'analisi è conservato in `docs/phpstan/phpstan-errors-current.txt`.

## Distribuzione errori

Le categorie primarie seguenti sono mutuamente esclusive e totalizzano 278 errori.

| Categoria | Numero errori | File coinvolti | Priorità | Rischio |
|---|---:|---:|---|---|
| Proprietà Eloquent non riconosciute | 135 | 24 | P1 | Medio |
| Enum, cast e condizioni impossibili | 61 | 22 | P0 | Basso-Medio |
| Proprietà nullable / nullsafe incoerente | 41 | 12 | P2 | Medio |
| Relazioni Eloquent senza tipi/generics | 18 | 9 | P1 | Basso |
| Collection con tipo mancante o errato | 14 | 6 | P2 | Medio |
| Parametri incompatibili | 4 | 4 | P0 | Medio-Alto |
| Array senza shape/tipo adeguato | 3 | 1 | P1 | Alto |
| Return type incompatibile | 1 | 1 | P0 | Alto |
| Query builder/modello non specializzato | 1 | 1 | P1 | Medio |
| Model factory | 0 | 0 | P3 | Basso |
| Resource/API response come causa primaria | 0 | 0 | P3 | Medio |
| Configurazione Larastan/PHPStan come errore di bootstrap | 0 | 0 | P0 | Basso |
| Test | 0 | 0 | P3 | Basso |

### Dettaglio per categoria

#### Proprietà Eloquent non riconosciute — 135 errori

- File: 24, soprattutto `AdminPlatformOverviewController.php`, `CustomerSuccessfulEventController.php`, `OrganizerOpportunityController.php`, `OrganizerReviewController.php`, servizi di ranking/opportunity e action di eventi/notifiche.
- Esempio: `CancelEvent.php:23`, accesso a `Model::$owner_user_id`.
- Causa probabile: relazioni restituite come `Model` perché prive di return type/generics; attributi dinamici prodotti da `withCount`, alias SQL o relazioni non dichiarati nel tipo del Model.
- Rischio: medio; aggiungere tipi alle relazioni è sicuro, mentre gli alias dinamici vanno verificati query per query.
- Priorità: P1, dopo il riconoscimento corretto dei cast.

Proprietà più frequenti: `name` (34), `organizerProfile` (24), `success_score` (7), `average_rating` (7), `ticketTypes` (4), `fill_rate` (4), `province` (4), `owner_user_id` (4).

#### Relazioni Eloquent senza tipi/generics — 18 errori

- File: 9; `NotifyInterestedCustomers.php`, controller admin/event/analytics/review/subscription, `SendEventCancellationEmails.php`, `OrganizerOpportunityEngine.php`, `OrganizerRankingService.php`.
- Esempio: relazione `notificationPreference` non riconosciuta su `User`.
- Causa probabile: metodi di relazione senza return type (`User`, `Review`, `Notification`, `SubscriptionPlan`, `OrganizerProfile`) e relazioni tipizzate solo con la classe raw, senza generics Larastan.
- Rischio: basso, se il tipo riflette esattamente la relazione Eloquent già restituita.
- Priorità: P1.

Relazioni più ripetute: `event`, `organizerProfile`, `user`, `notificationPreference` (2 ciascuna), poi `features`, `images`, `deliveries`, `reviewStatistic`, `bookings`, `preferredCategories`, `followedOrganizers`, `plan`, `statistics`, `ticketTypes`.

#### Collection con tipo mancante o errato — 14 errori

- File: 6; controller admin overview, customer dashboard/successful events, organizer opportunity/planning e `OrganizerOpportunityEngine`.
- Esempio: callback di `Collection<int, Category>::map()` con tipo non risolvibile.
- Causa probabile: il tipo dell'elemento si perde a monte in una relazione/query non specializzata; callback e collection non hanno PHPDoc/generics coerenti.
- Rischio: medio, perché una tipizzazione errata può mascherare payload eterogenei.
- Priorità: P2, dopo relazioni e Model.

#### Array senza shape/tipo adeguato — 3 errori

- File: `OrganizerOpportunityController.php`.
- Esempio: offset `suggestions` letto su un valore inferito come stringa.
- Causa probabile: payload JSON/cast non riconosciuto oppure array shape non documentata prima dell'accesso annidato.
- Rischio: alto: può indicare dati persistiti con forma inattesa e un errore runtime reale.
- Priorità: P1.

#### Return type mancante o incompatibile — 1 errore

- File: `Actions/Events/CreateEvent.php`.
- Esempio: closure dichiarata `Event` ma inferita come `Model`.
- Causa probabile: relazione `events()` non specializzata con generic `Event`.
- Rischio: alto finché non è confermato che il builder crei sempre `Event`; basso dopo la specializzazione corretta.
- Priorità: P0.

#### Parametri con tipo mancante o incompatibile — 4 errori

- File: `CheckInBooking.php`, `CreateEvent.php`, `CreateReview.php`, `OrganizerOpportunityController.php`.
- Esempio: `abort_unless()` riceve `Booking|null` invece di `bool`.
- Causa probabile: uso del valore nullable come condizione e perdita del tipo concreto restituito da una relazione.
- Rischio: medio-alto; almeno due casi possono rappresentare logica valida a runtime ma espressa in modo ambiguo.
- Priorità: P0.

#### Enum e cast — 61 errori

- File: 22, distribuiti tra action, controller, middleware, policy, Model e service.
- Esempio: `EventStatus::Published` confrontato con una proprietà inferita come `string`; `isFuture()` chiamato su `string`; `$role->value` letto su `string`.
- Causa probabile principale: Larastan 3.10 imposta `parseModelCastsMethod: false`, mentre il progetto usa esclusivamente il metodo Laravel 11 `protected function casts(): array` per `EventStatus`, `UserRole` e datetime.
- Rischio: basso nel primo intervento di configurazione, perché abilita la comprensione di codice già esistente; medio per le segnalazioni residue, che possono essere condizioni realmente impossibili.
- Priorità: P0.

Identificatori inclusi: `method.nonObject` (18), `identical.alwaysFalse` (16), `function.impossibleType` (9), `booleanAnd.alwaysFalse` (8), `property.nonObject` (7), `match.alwaysFalse` (2), `function.alreadyNarrowedType` (1).

#### Proprietà nullable — 41 errori

- File: 12; soprattutto controller admin/customer/organizer, action notifiche/review/eventi e service opportunity/summary.
- Esempio: accesso nullsafe a `email` sul lato sinistro di `??` giudicato non necessario.
- Causa probabile: relazioni `BelongsTo` inferite non-null per default, oppure cast/relazioni non correttamente tipizzati a monte.
- Rischio: medio: rimuovere `?->` prematuramente può introdurre eccezioni se il vincolo DB consente null.
- Priorità: P2; riesaminare solo dopo cast e generics.

#### Query builder — 1 errore primario

- File: `Actions/Events/CreateEvent.php`.
- Esempio: `categories()` non riconosciuto su `Illuminate\Database\Eloquent\Model`.
- Causa probabile: builder ottenuto dalla relazione `OrganizerProfile::events()` priva di generic concreto.
- Rischio: medio.
- Priorità: P1.

#### Model factory — 0 errori

- Nessuna segnalazione PHPStan nei factory.
- La factory resta comunque un'area da verificare tramite test, non una causa degli attuali 278 errori.
- Rischio/priorità: basso/P3.

#### Resource e API response — 0 errori primari

- Non sono presenti classi Resource negli errori.
- Diversi controller costruiscono array di risposta dinamici, ma gli errori correnti ricadono in proprietà Eloquent, Collection e array shape.
- Rischio/priorità: medio/P2 dopo la stabilizzazione dei tipi a monte.

#### Configurazione Larastan/PHPStan

- Nessun errore di bootstrap: Larastan viene caricato e tutti i 119 file vengono analizzati.
- È però disabilitato il parsing del metodo `casts()`, valore predefinito di Larastan 3.10 non adatto alla convenzione usata dal progetto.
- Questa è una causa radice, non un errore da ignorare o una riduzione di severità.
- Rischio/priorità: basso/P0.

### Distribuzione per livello applicativo

Questa vista si sovrappone alle categorie primarie e serve a ordinare il lavoro.

| Livello | Errori | File | Nota |
|---|---:|---:|---|
| Controller | 181 | 15 | Prevalentemente effetti dei tipi Eloquent persi a monte |
| Service | 47 | 6 | Cast, proprietà correlate e Collection |
| Action | 26 | 9 | Include condizioni potenzialmente reali |
| Notification | 12 | 1 | `NotifyInterestedCustomers` dipende da relazioni User non tipizzate |
| Policy | 6 | 1 | Relazioni e cast enum |
| Middleware | 2 | 2 | `UserRole` e organizer profile |
| Job | 2 | 1 | Relazione `deliveries` |
| Model | 2 | 1 | Confronti `UserRole` non riconosciuto come enum |
| Test | 0 | 0 | I test non sono nel path PHPStan corrente (`paths: [app]`) |

File con maggiore concentrazione: `AdminPlatformOverviewController.php` (46), `CustomerSuccessfulEventController.php` (41), `OrganizerOpportunityController.php` (25), `OrganizerReviewController.php` (20), `OrganizerEventSummaryService.php` (18), `OrganizerPlanningController.php` (17).

## Top 10 cause radice

### 1. Parsing del metodo `casts()` disabilitato

- File principali: `phpstan.neon`, `Event.php`, `User.php` e tutti i Model con datetime/enum/JSON cast.
- Errori collegati stimati: **60-85** diretti e secondari.
- Correzione proposta: valutare `parseModelCastsMethod: true`, mantenendo livello 5 e senza ignore/baseline.
- Rischio: basso.
- Test: suite Laravel, Pint, PHPStan completo; aggiungere controlli mirati su enum/datetime solo se emergono differenze runtime.

### 2. Relazioni Eloquent prive di return type

- File principali: `User.php`, `Review.php`, `Notification.php`, `SubscriptionPlan.php`, `OrganizerProfile.php`, `Event.php` per le relazioni ancora raw.
- Errori collegati stimati: **45-70**.
- Correzione proposta: return type Eloquent concreto (`HasOne`, `HasMany`, `BelongsTo`, `BelongsToMany`) e import corrispondenti.
- Rischio: basso.
- Test: test completi, Pint, PHPStan; test delle relazioni interessate.

### 3. Generics mancanti nelle relazioni già tipizzate

- File principali: `Event.php`, `OrganizerProfile.php`, `Municipality.php`, `Province.php`.
- Errori collegati stimati: **35-60**.
- Correzione proposta: PHPDoc generici compatibili con Larastan, indicando RelatedModel e DeclaringModel.
- Rischio: basso-medio.
- Test: suite completa e query/policy che attraversano le relazioni.

### 4. Attributi aggregati dinamici non modellati

- File principali: controller admin overview/customer successful events e service ranking/summary.
- Errori collegati stimati: **25-35**.
- Correzione proposta: DTO/array shape per projection, oppure PHPDoc locali sulle collection ottenute da `withCount`/select alias; evitare proprietà globali inesistenti sui Model quando l'attributo esiste solo in una query.
- Rischio: medio.
- Test: endpoint dashboard/ranking e test JSON structure.

### 5. Tipi delle Collection persi a monte

- File principali: sei controller/service con `map`, `filter`, `groupBy`.
- Errori collegati stimati: **14-25**.
- Correzione proposta: risolvere prima il tipo delle relazioni/query, poi aggiungere generics o callback type solo dove ancora necessario.
- Rischio: medio.
- Test: endpoint di aggregazione e ordinamento.

### 6. Array JSON e suggestion payload senza shape

- File principali: `OrganizerOpportunityController.php`, `AiEventSuggestion.php`.
- Errori collegati stimati: **3-10**.
- Correzione proposta: documentare la shape del cast JSON e validare gli offset prima dell'uso.
- Rischio: alto, perché la forma dei dati è un contratto runtime.
- Test: generazione/uso suggestion, indici mancanti e payload corrotti.

### 7. Valori nullable usati direttamente come booleani

- File principali: `CheckInBooking.php`, `CreateReview.php`.
- Errori collegati stimati: **2-4**.
- Correzione proposta: confronto esplicito con `null` e narrowing immediato.
- Rischio: medio.
- Test: QR inesistente/riutilizzato, review senza prenotazione.

### 8. Builder di `OrganizerProfile::events()` non specializzato

- File principali: `OrganizerProfile.php`, `CreateEvent.php`.
- Errori collegati stimati: **3** diretti, più errori secondari nei consumer.
- Correzione proposta: tipizzare la relazione `HasMany<Event, OrganizerProfile>`.
- Rischio: basso.
- Test: creazione evento e ownership organizer.

### 9. Nullability delle relazioni non allineata allo schema

- File principali: controller admin/customer/organizer e service opportunity/summary.
- Errori collegati stimati: fino a **41**, da rivalutare dopo i gruppi 1-3.
- Correzione proposta: confrontare foreign key nullable, `withDefault()` e vincoli DB; solo dopo scegliere `->` o `?->`.
- Rischio: medio-alto se corretta meccanicamente.
- Test: risorse con relazioni mancanti e casi negativi.

### 10. Condizioni impossibili residue

- File principali: action booking/event, policy, middleware e service summary/ranking.
- Errori collegati stimati: **5-20** dopo il parsing cast.
- Correzione proposta: rieseguire PHPStan dopo il gruppo cast; correggere solo le condizioni ancora segnalate e dimostrate incompatibili.
- Rischio: alto, perché cambiano autorizzazioni o transizioni di stato.
- Test: matrice stati evento, ruoli, cancellazione, pubblicazione e disponibilità.

## Errori probabilmente reali

- `CheckInBooking.php:24` e `CreateReview.php:29`: oggetti nullable passati a `abort_unless()` invece di una condizione booleana esplicita.
- `OrganizerOpportunityController.php:65` e offset contigui: accessi annidati a un valore che può non avere la shape attesa.
- `CreateEvent.php:33-36`: builder e return type non garantiscono staticamente un `Event` finché la relazione non è specializzata.
- Attributi come `events_count`, `bookings_count`, `participants_count` e `average_rating`: alcuni sono alias query validi, altri potrebbero mancare in specifici rami di query.
- `method.notFound` su `categories()`: è probabilmente secondario alla relazione non tipizzata, ma sarebbe un errore reale se il builder restituisse davvero un Model differente.
- Condizioni `always false/true`: molte dipendono dai cast non letti; quelle residue dopo il gruppo 1 vanno trattate come bug reali di stato/ruolo.
- Accessi a relazioni potenzialmente assenti (`organizerProfile`, `municipality`, `province`): verificare nullability e vincoli DB prima di rimuovere accessi nullsafe.

## Falsi positivi probabili

- Confronti `EventStatus`/`UserRole` contro proprietà inferite `string`: altamente plausibili, perché i cast enum sono dichiarati in `casts()` ma Larastan non lo analizza con la configurazione corrente.
- Chiamate Carbon (`isFuture`, `isPast`, `format`, `toDateString`, `toIso8601String`, `copy`) su valori inferiti `string`: altamente plausibili per la stessa causa sui cast datetime.
- Proprietà di Model correlati inferiti come `Illuminate\Database\Eloquent\Model`: plausibili quando il metodo di relazione restituisce correttamente una relazione concreta ma manca il generic.
- Proprietà aggregate (`*_count`, score, rating) dopo query che le selezionano esplicitamente: plausibili solo nei rami in cui l'alias è dimostrabilmente presente; non vanno silenziate globalmente.
- `nullsafe.neverNull`: plausibile come falso positivo quando la relazione è nullable nello schema ma il generic raw la rende apparentemente non-null; richiede verifica individuale.

## Piano di correzione

Ogni gruppo deve modificare una sola categoria omogenea e fermarsi se test o analisi peggiorano.

### Gruppo 1 — Comprensione dei cast Laravel 11

- File: `phpstan.neon`; nessun codice applicativo.
- Intervento: abilitare il parsing del metodo `casts()` di Larastan e verificare la compatibilità.
- Errori stimati eliminati: **60-85**.
- Rischio: basso.
- Verifica:
  - `php artisan test`
  - `./vendor/bin/pint --test`
  - `./vendor/bin/phpstan analyse --memory-limit=1G`

### Gruppo 2 — Return type delle relazioni Eloquent

- File: Model con relazioni senza return type, iniziando da `User`, `Review`, `Notification`, `SubscriptionPlan`, `OrganizerProfile`, `Event`.
- Errori stimati eliminati: **45-70**.
- Rischio: basso.
- Verifica: comandi standard più test di relazioni, notifiche e ownership.

### Gruppo 3 — Generics e tipi concreti dei Model correlati

- File: Model con `BelongsTo`, `HasOne`, `HasMany`, `BelongsToMany` raw.
- Errori stimati eliminati: **35-60**.
- Rischio: basso-medio.
- Verifica: comandi standard più policy e query annidate.

### Gruppo 4 — Projection, Collection e attributi aggregati

- File: controller admin/customer e service ranking/summary/opportunity.
- Errori stimati eliminati: **30-50**.
- Rischio: medio.
- Verifica: comandi standard più endpoint dashboard, ranking e opportunity.

### Gruppo 5 — Array shape e payload API/JSON

- File: `OrganizerOpportunityController.php`, Model suggestion e service correlati.
- Errori stimati eliminati: **3-10**.
- Rischio: alto.
- Verifica: comandi standard più test payload mancanti, corrotti e indici fuori range.

### Gruppo 6 — Nullable e narrowing

- File: action check-in/review/eventi e consumer di relazioni nullable.
- Errori stimati eliminati: **10-45**, dopo rivalutazione.
- Rischio: medio-alto.
- Verifica: comandi standard più casi negativi IDOR/assenza relazione.

### Gruppo 7 — Controller, service, job e notification residui

- File: solo quelli ancora segnalati dopo i tipi a monte.
- Errori stimati eliminati: residuo variabile.
- Rischio: medio.
- Verifica: comandi standard e test mirati per ogni singolo sottogruppo.

### Gruppo 8 — Errori reali residui

- File: condizioni impossibili, metodi/return type incompatibili e query ancora errate.
- Errori stimati eliminati: tutti i residui confermati.
- Rischio: alto.
- Verifica: test di regressione specifici prima della suite completa.

## Regole operative per ogni gruppo futuro

1. Modificare una sola categoria omogenea.
2. Eseguire `php artisan test`.
3. Eseguire `./vendor/bin/pint --test`.
4. Eseguire `./vendor/bin/phpstan analyse --memory-limit=1G`.
5. Fermarsi se i test peggiorano.
6. Non introdurre `ignoreErrors`, baseline, riduzioni di livello o direttive `@phpstan-ignore-next-line` senza approvazione esplicita.

## Gruppo 1 - Parsing casts Eloquent

- Configurazione modificata: aggiunto `parameters.parseModelCastsMethod: true` in `backend/phpstan.neon`, usando la chiave dichiarata dallo schema di Larastan 3.10.0.
- Errori prima: **278**.
- Errori dopo: **211**.
- Riduzione netta: **67 errori** (**24,1%**).
- Categorie maggiormente ridotte: sono scomparse 18 segnalazioni `method.nonObject`, 16 `identical.alwaysFalse`, 9 `function.impossibleType`, 8 `booleanAnd.alwaysFalse`, 7 `property.nonObject`, 3 `nullCoalesce.offset`, 2 `match.alwaysFalse` e 1 `function.alreadyNarrowedType`; inoltre `argument.unresolvableType` è sceso da 14 a 12 e `argument.type` da 4 a 3. La riduzione riguarda soprattutto enum, cast datetime/JSON, condizioni e consumer di Collection derivati dai cast.
- Nuove segnalazioni emerse: **3** `nullsafe.neverNull` su valori Carbon ora correttamente riconosciuti come non nullable, in `NotifyInterestedCustomers.php`, `AdminPlatformOverviewController.php` e `CreateReview.php`. Il totale della categoria resta 41 perché tre precedenti segnalazioni della stessa categoria sono state risolte dal nuovo tipo inferito. Non sono errori di configurazione e non sono stati corretti in questo gruppo.
- Test Laravel: **34 superati, 174 assertion**.
- Pint: **verde su 141 file**.
- Rischio: **basso**; la modifica agisce esclusivamente sull'inferenza statica dei cast Eloquent già esistenti e non cambia il comportamento runtime.
- Prossimo gruppo consigliato: tipizzare i return type e i generics delle relazioni Eloquent, iniziando dai Model che alimentano le 18 segnalazioni `larastan.relationExistence` e le proprietà correlate; lavorare in un gruppo separato e rieseguire tutte le verifiche standard.

## Gruppo 2 - Relazioni Eloquent

- File Model modificati: `User.php`, `Review.php`, `OrganizerSubscription.php`, `Event.php`, `Campaign.php`, `SubscriptionPlan.php`, `Notification.php`, `OrganizerProfile.php`, `Booking.php` e `BookingItem.php`.
- Relazioni tipizzate: `User::preferredCategories`, `User::notificationPreference`, `User::followedOrganizers`; `Review::event`, `Review::organizerProfile`, `Review::user`, `Review::images`; `OrganizerSubscription::plan`, `OrganizerSubscription::organizerProfile`; `Event::ticketTypes`, `Event::bookings`; `Campaign::statistics`; `SubscriptionPlan::features`; `Notification::deliveries`; `OrganizerProfile::reviewStatistic`; `Booking::user`, `Booking::items`; `BookingItem::ticketType`.
- Intervento: aggiunti esclusivamente return type Eloquent concreti e PHPDoc generic nella forma supportata da Larastan 3.10.0, con il Model correlato e `$this` come secondo parametro. Corpi, chiavi, pivot, scope e comportamento delle relazioni sono invariati.
- Errori prima: **211**.
- Errori dopo: **182**.
- Errori eliminati: **29** netti; tutte le **18** segnalazioni iniziali `larastan.relationExistence` e 11 segnalazioni derivate sui tipi dei Model collegati.
- Errori residui nell'ambito: **0** `larastan.relationExistence` e **0** `missingType.generics`.
- Regressioni: nessuna nuova segnalazione rispetto all'output iniziale del gruppo. Durante la prima analisi sono emerse due relazioni latenti nella catena resa concreta da `Event::bookings`; sono state tipizzate, insieme alla relazione annidata `BookingItem::ticketType`, senza modificare la logica.
- Test Laravel: **34 superati, 174 assertion**.
- Pint: **verde su 141 file**.
- Prossimo gruppo consigliato: proprietà Eloquent non riconosciute e tipi base dei Model, da affrontare separatamente; restano 124 segnalazioni `property.notFound`, senza includere in quel gruppo nullability, Collection o controller/service.

## Gruppo 3 - Proprietà Eloquent dei Model

- Model modificati: `CheckIn`, `Category`, `Event`, `Municipality`, `OrganizerProfile` e `User`.
- Proprietà documentate: relazione dinamica `CheckIn::$booking`; alias `withCount` realmente selezionati `Category::$events_count`, `Event::$participants_count`, `Event::$customer_cancellations_count`, `Municipality::$events_count`, `OrganizerProfile::$followers_count`, `User::$reviews_count` e `User::$favorites_count`.
- Annotazioni usate: esclusivamente `@property-read` con classi Model concrete o `int`; nessun `mixed`, proprietà inventata o duplicata.
- Errori totali prima: **182**.
- Errori totali dopo: **169**.
- Riduzione netta: **13 errori**.
- `property.notFound` prima: **124**.
- `property.notFound` dopo: **114**.
- `property.notFound` eliminate: **10**. Altri 3 errori `argument.unresolvableType` sono scomparsi come conseguenza dell'inferenza più precisa, senza modificare Collection, controller o service.
- Nuove segnalazioni o categorie: **0**.
- Residui: la maggioranza usa ancora come ricevente `Illuminate\Database\Eloquent\Model` a causa di relazioni con tipo correlato generico; non è correggibile con PHPDoc veritiero sul Model concreto senza intervenire nuovamente sui generics. `User::$organizerProfile` non è stato annotato perché il tipo nullable corretto faceva emergere errori fuori ambito nei consumer; la modifica sperimentale è stata rimossa prima della verifica finale.
- Test Laravel: **34 superati, 174 assertion**.
- Pint: **verde su 141 file**.
- Prossimo gruppo consigliato: completare in un gruppo autonomo i generics delle relazioni ancora raw che producono il ricevente base `Model`; non affrontare insieme nullability, Collection o logica dei consumer.

## Gruppo 4 - Generics relazioni residue

- File Model modificati: `AiEventSuggestion.php`, `Booking.php`, `Category.php`, `CheckIn.php`, `Event.php`, `EventCancellation.php`, `EventRanking.php`, `EventSuccessScore.php`, `Municipality.php`, `OrganizerProfile.php`, `OrganizerRanking.php`, `Province.php`, `Review.php`, `ReviewStatistic.php`, `SeasonalEventTemplate.php`, `TicketType.php` e `User.php`.
- Relazioni corrette: `AiEventSuggestion::organizerProfile`; `Booking::event`, `Booking::review`, `Booking::checkIns`; `Category::events`; `CheckIn::booking`; `Event::organizerProfile`, `Event::series`, `Event::municipality`, `Event::categories`, `Event::reviews`, `Event::reviewStatistic`, `Event::successScore`, `Event::cancellation`, `Event::ranking`, `Event::venue`, `Event::views`; `EventCancellation::cancelledBy`; `EventRanking::event`; `EventSuccessScore::event`; `Municipality::province`, `Municipality::events`; `OrganizerProfile::owner`, `OrganizerProfile::events`, `OrganizerProfile::reviews`, `OrganizerProfile::ranking`, `OrganizerProfile::municipality`, `OrganizerProfile::followers`, `OrganizerProfile::subscription`; `OrganizerRanking::organizerProfile`; `Province::region`; `Review::booking`, `Review::reports`; `ReviewStatistic::event`, `ReviewStatistic::organizerProfile`; `SeasonalEventTemplate::category`; `TicketType::event`; `User::organizerProfile`, `User::bookings`, `User::reviews`, `User::notifications`, `User::favorites`, `User::eventFormatInterests`.
- Numero di relazioni completate: **43**.
- Intervento: aggiunti esclusivamente return type Eloquent mancanti e generic Larastan con Model concreto e `$this`; nessun corpo relazione, chiave, pivot, scope o query è stato modificato.
- Errori totali prima: **169**.
- Errori totali dopo: **62**.
- Riduzione netta: **107 errori**.
- `property.notFound` prima: **114**.
- `property.notFound` dopo: **0**.
- Errori a cascata eliminati: tutte le **114** segnalazioni `property.notFound`; sono inoltre scomparse le categorie residue `argument.unresolvableType`, `method.notFound` e `return.type` collegate ai Model generici.
- Nuove segnalazioni: **19** `argument.type` nei consumer che passano `User::$organizerProfile` nullable a `abort_unless`. Sono emerse grazie al tipo concreto e non sono state corrette perché appartengono a nullability/controller/middleware, fuori dall'ambito del gruppo.
- Errori residui su `Model` generico: **0 `property.notFound`**.
- Test Laravel: **34 superati, 174 assertion**.
- Pint: **verde su 141 file**.
- Prossimo gruppo consigliato: affrontare separatamente i 21 `argument.type`, iniziando dai 19 consumer di `organizerProfile` nullable e verificando il contratto di `abort_unless`; non includere nello stesso gruppo i 41 `nullsafe.neverNull`.

## Gruppo 5 - argument.type e organizerProfile nullable

- File modificati: `Actions/Bookings/CheckInBooking.php`, `Actions/Reviews/CreateReview.php`, `Http/Controllers/Api/V1/OrganizerAnalyticsController.php`, `OrganizerDashboardController.php`, `OrganizerEventController.php`, `OrganizerOpportunityController.php`, `OrganizerPlanningController.php`, `OrganizerProfileController.php`, `OrganizerReviewController.php` e `Http/Middleware/EnsureOrganizerApproved.php`.
- Correzione: il primo argomento di `abort_unless` ora è un confronto booleano esplicito `!== null`; codici HTTP, messaggi e controlli di sicurezza sono invariati.
- Errori totali prima: **62**.
- Errori totali dopo: **41**.
- `argument.type` prima: **21**.
- `argument.type` dopo: **0**.
- Casi `organizerProfile` corretti: **19** — 6 in `OrganizerAnalyticsController`, 1 in `OrganizerDashboardController`, 2 in `OrganizerEventController`, 2 in `OrganizerOpportunityController`, 4 in `OrganizerPlanningController`, 1 in `OrganizerProfileController`, 2 in `OrganizerReviewController` e 1 in `EnsureOrganizerApproved`.
- Altri `argument.type` corretti: **2** — `Booking|null` in `CheckInBooking` e `CreateReview`.
- Casi ambigui: nessuno; tutti i 21 errori riguardavano un Model nullable passato direttamente a una funzione che richiede `bool`.
- `nullsafe.neverNull` prima: **41**.
- `nullsafe.neverNull` dopo: **41**; non modificati.
- Nuove segnalazioni o categorie: **0**.
- Test Laravel: **34 superati, 174 assertion**.
- Pint: **verde su 141 file**.
- Prossimo gruppo consigliato: analizzare separatamente i 41 `nullsafe.neverNull`, distinguendo relazioni realmente nullable da PHPDoc/generics troppo restrittivi prima di rimuovere qualsiasi operatore nullsafe.

## Gruppo 6 - nullsafe.neverNull

- File modificati: `Actions/Events/CancelEvent.php`, `Actions/Notifications/NotifyInterestedCustomers.php`, `Actions/Reviews/CreateReview.php`, `Http/Controllers/Api/V1/Admin/AdminPlatformOverviewController.php`, `CustomerDashboardController.php`, `CustomerSuccessfulEventController.php`, `OrganizerOpportunityController.php`, `OrganizerPlanningController.php`, `OrganizerReviewController.php`, `Models/Event.php`, `Models/OrganizerProfile.php`, `Models/SeasonalEventTemplate.php`, `Services/AiEventSuggestionService.php`, `OrganizerEventSummaryService.php` e `OrganizerOpportunityEngine.php`.
- Verifica nullability: confrontate migration, foreign key, cast datetime, relazioni, query e fallback applicativi per tutti i 41 casi.
- Casi realmente non nullable: rimossi i nullsafe su `Booking::user`, `Event::$starts_at`, la variabile Carbon `$eventConcludedAt`, `OrganizerSubscription::plan` e la catena `Event::municipality.province`, tutte garantite da colonne/foreign key `NOT NULL` o dal flusso.
- Collection potenzialmente vuote: quattro accessi `categories->first()?->name` sono stati riscritti con `categories->pluck('name')->first()` mantenendo il fallback, senza assumere l'esistenza di una categoria.
- Nullability dichiarata corretta: documentate come nullable `Event::$reviewStatistic`, `Event::$successScore`, `OrganizerProfile::$municipality`, `OrganizerProfile::$reviewStatistic`, `OrganizerProfile::$subscription` e `SeasonalEventTemplate::$category`, coerentemente con FK nullable o relazioni `HasOne` opzionali.
- Nullsafe mantenuti: nei valori numerici di statistiche/success score opzionali è rimasto `?->`; il fallback è stato espresso con `?: 0` per evitare il falso positivo Larastan sul lato sinistro di `??`.
- Relazioni nullable governate da FK: `AiEventSuggestionService` controlla esplicitamente `municipality_id` e `category_id` prima di dereferenziare la relazione.
- Casi ambigui: **0**.
- Errori prima: **41**.
- Errori dopo: **0**.
- `nullsafe.neverNull` eliminati: **41**.
- Nuove segnalazioni: **0**.
- Test Laravel: **34 superati, 174 assertion**.
- Pint: **verde su 141 file**; rimosso automaticamente un import `SubscriptionPlan` divenuto inutilizzato.
- Risultato finale PHPStan: **verde, nessun errore su 119 file analizzati**.

## Fase 1 - Metadata Eloquent dopo le estensioni applicative

- Baseline corrente: **619 errori** su 87 file, di cui **597 `property.notFound`** e **22 `argument.unresolvableType`**.
- Intervento: aggiunti PHPDoc di attributo a 30 Model Eloquent, verificati contro lo schema PostgreSQL runtime, la nullability e i cast dichiarati; corretto inoltre il docblock multilinea di `PreOrder` usato da `PreOrderResource`.
- Tipi PostgreSQL `numeric`: documentati come `numeric-string`, coerentemente con il valore restituito dal driver e senza introdurre cast o modifiche runtime.
- Relazioni, query, fillable, accessor e logica applicativa: invariati.
- Risultato finale PHPStan level 5: **0 errori**; **0 `property.notFound`**, **0 errori Collection**, **0 `argument.unresolvableType`**.
- Possibili bug reali emersi: **nessuno**.
