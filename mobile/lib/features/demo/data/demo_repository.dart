import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../domain/demo_models.dart';

abstract class DemoRepository {
  List<String> get macroAreas;
  List<LocalEvent> get events;
  List<LocalEvent> get successEvents;
  List<OrganizerProfile> get organizers;
  List<Booking> get bookings;
  List<Review> get reviews;
  List<OpportunityIdea> get ideas;
}

final demoRepositoryProvider = Provider<DemoRepository>((ref) {
  return InMemoryDemoRepository();
});

class InMemoryDemoRepository implements DemoRepository {
  @override
  List<String> get macroAreas => const [
        'Bambini',
        'Cene',
        'Teatro',
        'Degustazioni',
        'Musica',
        'Natura',
        'Sport',
        'Cultura',
        'Laboratori',
        'Mercatini',
      ];

  @override
  late final List<LocalEvent> events = [
    const LocalEvent(
      id: 'evt-001',
      title: 'Cena in vigna sotto le stelle',
      organizer: 'Masseria Collevento',
      category: 'Cene',
      town: 'Termoli',
      province: 'CB',
      dateLabel: 'Ven 26 lug',
      timeLabel: '20:30',
      price: 38,
      capacity: 42,
      availableSeats: 8,
      rating: 4.8,
      imageTone: 0,
      shortDescription: 'Una serata tra filari, piatti locali e vini molisani.',
      fullDescription:
          'Degustazione guidata con menu di stagione, tavoli condivisi e racconto dei produttori del Basso Molise.',
      program: [
        'Accoglienza in cantina',
        'Cena a quattro portate',
        'Degustazione guidata',
        'Dolce al tramonto'
      ],
      included: [
        'Menu completo',
        'Tre calici',
        'Acqua',
        'Visita breve in cantina'
      ],
      notIncluded: ['Trasporto', 'Extra carta vini'],
      ageRange: 'Adulti e ragazzi da 12 anni',
      importantInfo: 'Segnalare allergie in fase di prenotazione.',
      cancellationPolicy: 'Rimborso completo fino a 48 ore prima.',
      isKidsFriendly: false,
      status: EventStatus.published,
      fillRate: 81,
      recommendRate: 96,
      hasNewEdition: true,
    ),
    const LocalEvent(
      id: 'evt-002',
      title: 'Laboratorio pane e fiabe',
      organizer: 'Forno di Nonna Ada',
      category: 'Bambini',
      town: 'Campobasso',
      province: 'CB',
      dateLabel: 'Sab 27 lug',
      timeLabel: '10:00',
      price: 14,
      capacity: 24,
      availableSeats: 5,
      rating: 4.9,
      imageTone: 1,
      shortDescription: 'Mani in pasta, racconti popolari e merenda finale.',
      fullDescription:
          'I bambini preparano piccoli pani, ascoltano una fiaba del territorio e portano a casa il loro impasto cotto.',
      program: ['Benvenuto', 'Impasto guidato', 'Fiaba molisana', 'Merenda'],
      included: ['Materiali', 'Merenda', 'Grembiule monouso'],
      notIncluded: ['Accompagnatore extra'],
      ageRange: '4-10 anni',
      importantInfo: 'Presenza obbligatoria di un adulto accompagnatore.',
      cancellationPolicy: 'Cambio data possibile fino a 24 ore prima.',
      isKidsFriendly: true,
      status: EventStatus.published,
      fillRate: 92,
      recommendRate: 98,
      hasNewEdition: true,
    ),
    const LocalEvent(
      id: 'evt-003',
      title: 'Passeggiata al tramonto sul Matese',
      organizer: 'Sentieri Aperti Molise',
      category: 'Natura',
      town: 'Roccamandolfi',
      province: 'IS',
      dateLabel: 'Dom 28 lug',
      timeLabel: '17:30',
      price: 18,
      capacity: 30,
      availableSeats: 12,
      rating: 4.7,
      imageTone: 2,
      shortDescription: 'Escursione facile con guida e picnic panoramico.',
      fullDescription:
          'Percorso adatto a famiglie allenate, con racconto naturalistico e sosta al belvedere.',
      program: ['Ritrovo', 'Cammino guidato', 'Picnic', 'Rientro al tramonto'],
      included: ['Guida AIGAE', 'Snack locale', 'Assicurazione'],
      notIncluded: ['Scarpe trekking', 'Trasporto'],
      ageRange: 'Da 8 anni',
      importantInfo: 'Scarpe chiuse e acqua obbligatorie.',
      cancellationPolicy: 'In caso di maltempo nuova data o rimborso.',
      isKidsFriendly: true,
      status: EventStatus.published,
      fillRate: 60,
      recommendRate: 91,
    ),
    const LocalEvent(
      id: 'evt-004',
      title: 'Teatro in cortile: racconti di paese',
      organizer: 'Compagnia del Borgo',
      category: 'Teatro',
      town: 'Isernia',
      province: 'IS',
      dateLabel: 'Sab 3 ago',
      timeLabel: '21:00',
      price: 12,
      capacity: 80,
      availableSeats: 28,
      rating: 4.6,
      imageTone: 3,
      shortDescription: 'Spettacolo all’aperto tra memoria, musica e dialetto.',
      fullDescription:
          'Una produzione originale pensata per cortili e piazze storiche, con posti seduti non numerati.',
      program: [
        'Ingresso',
        'Primo atto',
        'Intermezzo musicale',
        'Secondo atto'
      ],
      included: ['Posto seduto', 'Programma digitale'],
      notIncluded: ['Consumazioni'],
      ageRange: 'Per tutti',
      importantInfo: 'Arrivare 15 minuti prima.',
      cancellationPolicy: 'Biglietto convertibile in credito se annullato.',
      isKidsFriendly: false,
      status: EventStatus.published,
      fillRate: 75,
      recommendRate: 89,
    ),
    const LocalEvent(
      id: 'evt-005',
      title: 'Mercatino dei sapori e musica folk',
      organizer: 'Pro Loco Larino',
      category: 'Mercatini',
      town: 'Larino',
      province: 'CB',
      dateLabel: 'Dom 4 ago',
      timeLabel: '18:00',
      price: 0,
      capacity: 200,
      availableSeats: 64,
      rating: 4.5,
      imageTone: 4,
      shortDescription: 'Stand locali, artigianato e concerto in piazza.',
      fullDescription:
          'Percorso libero con produttori agricoli, area bimbi e musica tradizionale nella piazza principale.',
      program: [
        'Apertura stand',
        'Area giochi',
        'Degustazioni',
        'Concerto folk'
      ],
      included: ['Ingresso', 'Animazione bambini'],
      notIncluded: ['Consumazioni agli stand'],
      ageRange: 'Per tutti',
      importantInfo: 'Evento gratuito con prenotazione consigliata.',
      cancellationPolicy: 'Nessuna penale.',
      isKidsFriendly: true,
      status: EventStatus.published,
      fillRate: 68,
      recommendRate: 94,
    ),
  ];

  @override
  List<LocalEvent> get successEvents => events
      .map((event) => LocalEvent(
            id: '${event.id}-past',
            title: event.title,
            organizer: event.organizer,
            category: event.category,
            town: event.town,
            province: event.province,
            dateLabel: 'Edizione conclusa',
            timeLabel: event.timeLabel,
            price: event.price,
            capacity: event.capacity,
            availableSeats: 0,
            rating: event.rating,
            imageTone: event.imageTone,
            shortDescription: event.shortDescription,
            fullDescription: event.fullDescription,
            program: event.program,
            included: event.included,
            notIncluded: event.notIncluded,
            ageRange: event.ageRange,
            importantInfo: event.importantInfo,
            cancellationPolicy: event.cancellationPolicy,
            isKidsFriendly: event.isKidsFriendly,
            status: EventStatus.completed,
            fillRate: event.fillRate + 4,
            recommendRate: event.recommendRate,
            hasNewEdition: event.hasNewEdition,
          ))
      .toList();

  @override
  List<OrganizerProfile> get organizers => const [
        OrganizerProfile(
          name: 'Masseria Collevento',
          town: 'Termoli',
          followers: 1240,
          rating: 4.8,
          description: 'Cucina rurale, cantina e serate in vigna.',
        ),
        OrganizerProfile(
          name: 'Forno di Nonna Ada',
          town: 'Campobasso',
          followers: 780,
          rating: 4.9,
          description: 'Laboratori per famiglie e tradizioni da impastare.',
        ),
        OrganizerProfile(
          name: 'Sentieri Aperti Molise',
          town: 'Isernia',
          followers: 960,
          rating: 4.7,
          description: 'Escursioni leggere e natura accessibile.',
        ),
      ];

  @override
  late final List<Booking> bookings = [
    Booking(
        code: 'MOL-7K2P',
        event: events[0],
        status: 'Confermata',
        people: 2,
        total: 76,
        canCancel: true,
        canReview: false),
    Booking(
        code: 'MOL-1B8F',
        event: events[1],
        status: 'Confermata',
        people: 3,
        total: 42,
        canCancel: true,
        canReview: false),
    Booking(
        code: 'MOL-9R4S',
        event: successEvents[3],
        status: 'Conclusa',
        people: 2,
        total: 24,
        canCancel: false,
        canReview: true),
  ];

  @override
  List<Review> get reviews => const [
        Review(
            author: 'Marta',
            eventTitle: 'Laboratorio pane e fiabe',
            rating: 5,
            comment: 'Bambini felicissimi e staff molto attento.',
            recommends: true),
        Review(
            author: 'Luca',
            eventTitle: 'Cena in vigna sotto le stelle',
            rating: 4.8,
            comment: 'Atmosfera bellissima, vini raccontati con passione.',
            recommends: true),
        Review(
            author: 'Elena',
            eventTitle: 'Passeggiata al tramonto sul Matese',
            rating: 4.6,
            comment: 'Percorso ben gestito e panorama incredibile.',
            recommends: true),
      ];

  @override
  List<OpportunityIdea> get ideas => const [
        OpportunityIdea(
          title: 'Degustazione vino novello',
          period: 'Autunno',
          suggestedDate: 'Sab 9 nov',
          target: 'Coppie e gruppi adulti',
          reason:
              'Le degustazioni autunnali hanno riempimento alto nel Basso Molise.',
          priceRange: '28-42 euro',
        ),
        OpportunityIdea(
          title: 'Laboratorio pasquale per bambini',
          period: 'Primavera',
          suggestedDate: 'Dom 24 mar',
          target: 'Famiglie',
          reason: 'Le domeniche senza eventi family hanno domanda scoperta.',
          priceRange: '10-18 euro',
        ),
        OpportunityIdea(
          title: 'Aperitivo estivo in terrazza',
          period: 'Estate',
          suggestedDate: 'Ven 2 ago',
          target: 'Under 40 e turisti',
          reason:
              'Le date libere del venerdi performano bene vicino alla costa.',
          priceRange: '18-30 euro',
        ),
      ];
}
