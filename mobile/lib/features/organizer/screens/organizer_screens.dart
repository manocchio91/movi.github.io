import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/auth/auth_controller.dart';
import '../../demo/data/demo_repository.dart';
import '../../demo/domain/demo_models.dart';
import '../../shared/ui/app_components.dart';

class OrganizerDashboardScreen extends ConsumerWidget {
  const OrganizerDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final repo = ref.watch(demoRepositoryProvider);
    return ScreenScaffold(
      title: 'Dashboard',
      actions: [
        IconButton(
            onPressed: () => context.push('/organizer/reviews'),
            icon: const Icon(Icons.reviews_rounded),
            tooltip: 'Recensioni'),
      ],
      children: [
        GridView.count(
          crossAxisCount: 2,
          childAspectRatio: 1.22,
          physics: const NeverScrollableScrollPhysics(),
          shrinkWrap: true,
          crossAxisSpacing: 10,
          mainAxisSpacing: 10,
          children: const [
            MetricCard(
                label: 'Eventi attivi',
                value: '7',
                icon: Icons.event_available_rounded),
            MetricCard(
                label: 'Prenotazioni',
                value: '186',
                icon: Icons.confirmation_number_rounded),
            MetricCard(
                label: 'Posti liberi', value: '74', icon: Icons.chair_rounded),
            MetricCard(
                label: 'Follower',
                value: '1.240',
                icon: Icons.favorite_rounded),
            MetricCard(
                label: 'Valutazione', value: '4.8', icon: Icons.star_rounded),
            MetricCard(
                label: 'Ritorno', value: '32%', icon: Icons.repeat_rounded),
          ],
        ),
        const SectionTitle('Prenotazioni ultimi giorni'),
        const _MiniChart(),
        const SectionTitle('Eventi prossimi'),
        ...repo.events.take(3).map((event) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: EventCard(event: event, compact: true),
            )),
        const SectionTitle('Recensioni recenti'),
        ...repo.reviews.map((review) => ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.star_rounded),
              title: Text('${review.eventTitle} · ${review.rating}'),
              subtitle: Text(review.comment),
            )),
        const SectionTitle('Categorie e campagne migliori'),
        const Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            Chip(label: Text('Cene 92%')),
            Chip(label: Text('Bambini 88%')),
            Chip(label: Text('Newsletter locale +24%')),
          ],
        ),
        const SectionTitle('Suggerimenti'),
        ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.lightbulb_rounded),
          title: const Text('Hai due sabati liberi in agosto'),
          subtitle: const Text(
              'Crea un aperitivo estivo o duplica una cena riuscita.'),
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.go('/organizer/opportunities'),
        ),
        FilledButton.icon(
          onPressed: () => context.push('/organizer/bookings'),
          icon: const Icon(Icons.qr_code_scanner_rounded),
          label: const Text('Prenotazioni e check-in'),
        ),
      ],
    );
  }
}

class MyEventsScreen extends ConsumerWidget {
  const MyEventsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final events = ref.watch(demoRepositoryProvider).events;
    return DefaultTabController(
      length: 4,
      child: ScreenScaffold(
        title: 'I miei Eventi',
        bottom: const TabBar(isScrollable: true, tabs: [
          Tab(text: 'Aperti'),
          Tab(text: 'Chiusi'),
          Tab(text: 'Annullati'),
          Tab(text: 'Bozze')
        ]),
        children: [
          SizedBox(
            height: MediaQuery.sizeOf(context).height * .72,
            child: TabBarView(
              children: [
                ListView.separated(
                  padding: EdgeInsets.zero,
                  itemBuilder: (_, index) =>
                      _OrganizerEventCard(event: events[index], closed: false),
                  separatorBuilder: (_, __) => const SizedBox(height: 12),
                  itemCount: events.length,
                ),
                ListView.separated(
                  padding: EdgeInsets.zero,
                  itemBuilder: (_, index) =>
                      _OrganizerEventCard(event: events[index], closed: true),
                  separatorBuilder: (_, __) => const SizedBox(height: 12),
                  itemCount: 3,
                ),
                const EmptyState(
                    title: 'Nessun evento annullato',
                    message: 'Gli eventi annullati saranno mostrati qui.',
                    icon: Icons.cancel_rounded),
                const EmptyState(
                    title: 'Bozze vuote',
                    message:
                        'Le bozze create dal wizard appariranno in questa sezione.',
                    icon: Icons.drafts_rounded),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class CreateEventWizardScreen extends StatefulWidget {
  const CreateEventWizardScreen({super.key});

  @override
  State<CreateEventWizardScreen> createState() =>
      _CreateEventWizardScreenState();
}

class _CreateEventWizardScreenState extends State<CreateEventWizardScreen> {
  int step = 0;
  final steps = const [
    'Informazioni',
    'Data e luogo',
    'Prezzi',
    'Descrizione',
    'Anteprima',
    'Pubblicazione'
  ];

  @override
  Widget build(BuildContext context) {
    return ScreenScaffold(
      title: 'Crea Evento',
      actions: [
        IconButton(
            onPressed: () => context.push('/organizer/calendar'),
            icon: const Icon(Icons.calendar_month_rounded),
            tooltip: 'Calendario')
      ],
      children: [
        Stepper(
          currentStep: step,
          physics: const NeverScrollableScrollPhysics(),
          onStepTapped: (value) => setState(() => step = value),
          onStepContinue: () =>
              setState(() => step = (step + 1).clamp(0, steps.length - 1)),
          onStepCancel: () =>
              setState(() => step = (step - 1).clamp(0, steps.length - 1)),
          controlsBuilder: (context, details) => Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Row(
              children: [
                FilledButton(
                    onPressed: details.onStepContinue,
                    child: Text(step == steps.length - 1
                        ? 'Invia in revisione'
                        : 'Avanti')),
                const SizedBox(width: 10),
                TextButton(
                    onPressed: details.onStepCancel,
                    child: const Text('Indietro')),
              ],
            ),
          ),
          steps: [
            Step(
                title: const Text('Informazioni'),
                isActive: step >= 0,
                content: const _WizardFields(labels: [
                  'Titolo',
                  'Descrizione breve',
                  'Categoria',
                  'Immagini'
                ])),
            Step(
                title: const Text('Data e luogo'),
                isActive: step >= 1,
                content: Column(children: [
                  TextField(
                      readOnly: true,
                      onTap: () => context.push('/organizer/calendar'),
                      decoration: const InputDecoration(
                          labelText: 'Data',
                          suffixIcon: Icon(Icons.calendar_month_rounded))),
                  const SizedBox(height: 10),
                  const _WizardFields(labels: ['Comune', 'Luogo', 'Orario']),
                ])),
            Step(
                title: const Text('Prezzi e capienza'),
                isActive: step >= 2,
                content: const _WizardFields(labels: [
                  'Prezzo',
                  'Prezzo adulto',
                  'Prezzo bambino',
                  'Capienza massima',
                  'Termine prenotazioni'
                ])),
            Step(
                title: const Text('Descrizione completa'),
                isActive: step >= 3,
                content: const _WizardFields(labels: [
                  'Descrizione completa',
                  'Programma',
                  'Cosa è incluso',
                  'Informazioni importanti',
                  'Condizioni'
                ])),
            Step(
                title: const Text('Anteprima'),
                isActive: step >= 4,
                content: const EmptyState(
                    title: 'Anteprima evento',
                    message:
                        'Card e dettaglio saranno renderizzati con i dati inseriti.',
                    icon: Icons.preview_rounded)),
            Step(
                title: const Text('Pubblicazione'),
                isActive: step >= 5,
                content: const Text(
                    'Invio in moderazione con stato pending_review.')),
          ],
        ),
      ],
    );
  }
}

class SmartCalendarScreen extends StatelessWidget {
  const SmartCalendarScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final days = List.generate(31, (index) => index + 1);
    return ScreenScaffold(
      title: 'Calendario intelligente',
      children: [
        const Wrap(
          spacing: 8,
          children: [
            InfoPill(icon: Icons.circle, text: 'Verde libero'),
            InfoPill(icon: Icons.circle, text: 'Giallo 1 evento'),
            InfoPill(icon: Icons.circle, text: 'Rosso 2+ eventi'),
          ],
        ),
        const SizedBox(height: 16),
        GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 7, mainAxisSpacing: 8, crossAxisSpacing: 8),
          itemCount: days.length,
          itemBuilder: (context, index) {
            final count = index % 5;
            final color = count == 0
                ? Colors.green
                : count < 3
                    ? Colors.amber
                    : Colors.redAccent;
            return InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: () => showModalBottomSheet<void>(
                context: context,
                showDragHandle: true,
                builder: (_) => const Padding(
                  padding: EdgeInsets.all(20),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      ListTile(
                          title: Text('Teatro in cortile'),
                          subtitle: Text(
                              'Teatro · 21:00 · Isernia · 18 km · Compagnia del Borgo')),
                      ListTile(
                          title: Text('Aperitivo estivo'),
                          subtitle: Text(
                              'Cene · 19:30 · Termoli · 4 km · dati pubblici aggregati')),
                    ],
                  ),
                ),
              ),
              child: DecoratedBox(
                decoration: BoxDecoration(
                    color: color.withValues(alpha: .22),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: color)),
                child: Center(child: Text('${days[index]}')),
              ),
            );
          },
        ),
      ],
    );
  }
}

class OrganizerBookingsScreen extends ConsumerWidget {
  const OrganizerBookingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final bookings = ref.watch(demoRepositoryProvider).bookings;
    return ScreenScaffold(
      title: 'Prenotazioni',
      children: [
        const TextField(
            decoration: InputDecoration(
                hintText: 'Cerca cliente o codice',
                prefixIcon: Icon(Icons.search_rounded))),
        const SizedBox(height: 12),
        const Wrap(spacing: 8, children: [
          Chip(label: Text('Evento')),
          Chip(label: Text('Stato')),
          Chip(label: Text('Check-in'))
        ]),
        const SectionTitle('Raggruppate per evento'),
        ...bookings.map((booking) => Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(booking.event.title,
                          style: Theme.of(context)
                              .textTheme
                              .titleMedium
                              ?.copyWith(fontWeight: FontWeight.w800)),
                      const SizedBox(height: 8),
                      Text(
                          'Cliente demo · ${booking.people} persone · adulti ${booking.people - 1} · bambini 1'),
                      Text(
                          'Stato: ${booking.status} · Note/esigenze visibili con accesso limitato'),
                      const SizedBox(height: 8),
                      FilledButton.icon(
                          onPressed: () {},
                          icon: const Icon(Icons.qr_code_scanner_rounded),
                          label: const Text('Check-in QR demo')),
                    ]),
              ),
            )),
      ],
    );
  }
}

class OrganizerReviewsScreen extends ConsumerWidget {
  const OrganizerReviewsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final repo = ref.watch(demoRepositoryProvider);
    return ScreenScaffold(
      title: 'Recensioni',
      children: [
        GridView.count(
          crossAxisCount: 2,
          childAspectRatio: 1.35,
          physics: const NeverScrollableScrollPhysics(),
          shrinkWrap: true,
          crossAxisSpacing: 10,
          mainAxisSpacing: 10,
          children: const [
            MetricCard(
                label: 'Media generale',
                value: '4.8',
                icon: Icons.star_rounded),
            MetricCard(
                label: 'Consiglierebbe',
                value: '96%',
                icon: Icons.thumb_up_rounded),
          ],
        ),
        const SectionTitle('Eventi recensiti'),
        ...repo.events.take(4).map((event) => ExpansionTile(
              tilePadding: EdgeInsets.zero,
              title: Text(event.title),
              subtitle: Text(
                  '${event.rating} media · ${event.recommendRate}% consiglierebbe'),
              children: [
                const ListTile(
                    title: Text('Distribuzione stelle'),
                    subtitle: Text('5★ 72% · 4★ 21% · 3★ 7%')),
                ...repo.reviews.map((review) => ListTile(
                    title: Text('${review.author} · ${review.rating}'),
                    subtitle: Text(review.comment))),
              ],
            )),
      ],
    );
  }
}

class OpportunitiesScreen extends ConsumerWidget {
  const OpportunitiesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final repo = ref.watch(demoRepositoryProvider);
    return ScreenScaffold(
      title: 'Opportunità',
      children: [
        const SectionTitle('Idee per nuovi eventi'),
        ...repo.ideas.map((idea) => Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(idea.title,
                          style: Theme.of(context)
                              .textTheme
                              .titleMedium
                              ?.copyWith(fontWeight: FontWeight.w800)),
                      const SizedBox(height: 8),
                      Wrap(spacing: 8, runSpacing: 8, children: [
                        InfoPill(
                            icon: Icons.calendar_today_rounded,
                            text: '${idea.period} · ${idea.suggestedDate}'),
                        InfoPill(icon: Icons.groups_rounded, text: idea.target),
                        InfoPill(
                            icon: Icons.euro_rounded, text: idea.priceRange),
                      ]),
                      const SizedBox(height: 8),
                      Text(idea.reason),
                      const SizedBox(height: 10),
                      FilledButton(
                          onPressed: () => context.go('/organizer/create'),
                          child: const Text('Usa questa idea')),
                    ]),
              ),
            )),
        const SectionTitle('Eventi più riusciti'),
        for (final event in repo.successEvents.take(3))
          Card(
            child: ListTile(
              title: Text('${event.category} · ${event.town}'),
              subtitle: Text(
                  'Riempimento ${event.fillRate}% · recensione ${event.rating} · successo: format semplice, target chiaro, data stagionale.'),
              trailing: TextButton(
                  onPressed: () => context.go('/organizer/create'),
                  child: const Text('Prendi ispirazione')),
            ),
          ),
      ],
    );
  }
}

class OrganizerProfileScreen extends ConsumerWidget {
  const OrganizerProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider).user!;
    return ScreenScaffold(
      title: 'Profilo attività',
      children: [
        ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const CircleAvatar(child: Icon(Icons.storefront_rounded)),
          title: Text(user.name),
          subtitle:
              const Text('Termoli · contatti · indirizzo · logo e immagini'),
        ),
        const LocalImage(tone: 0, height: 160),
        const SectionTitle('Gestione'),
        for (final item in [
          'Follower',
          'Statistiche',
          'Recensioni',
          'Abbonamento',
          'Impostazioni'
        ])
          ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.chevron_right_rounded),
              title: Text(item),
              onTap: item == 'Recensioni'
                  ? () => context.push('/organizer/reviews')
                  : null),
        const SizedBox(height: 16),
        OutlinedButton.icon(
          onPressed: () {
            ref.read(authControllerProvider.notifier).logout();
            context.go('/login');
          },
          icon: const Icon(Icons.logout_rounded),
          label: const Text('Logout'),
        ),
      ],
    );
  }
}

class _OrganizerEventCard extends StatelessWidget {
  const _OrganizerEventCard({required this.event, required this.closed});

  final LocalEvent event;
  final bool closed;

  @override
  Widget build(BuildContext context) {
    final booked = event.capacity - event.availableSeats;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(event.title,
                style: Theme.of(context)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 6),
            Text(closed
                ? 'Prenotati $booked · partecipanti effettivi ${booked - 2} · no-show 2 · recensione ${event.rating}'
                : 'Prenotati $booked/${event.capacity} · disponibili ${event.availableSeats} · riempimento ${event.fillRate}% · published'),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                OutlinedButton(
                    onPressed: () {}, child: const Text('Dettaglio')),
                OutlinedButton(onPressed: () {}, child: const Text('Modifica')),
                OutlinedButton(
                    onPressed: () {}, child: const Text('Prenotazioni')),
                OutlinedButton(
                    onPressed: () {}, child: const Text('Partecipanti')),
                OutlinedButton(
                    onPressed: () {}, child: const Text('Recensioni')),
                OutlinedButton(onPressed: () {}, child: const Text('Duplica')),
                TextButton(onPressed: () {}, child: const Text('Annulla')),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _WizardFields extends StatelessWidget {
  const _WizardFields({required this.labels});

  final List<String> labels;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        for (final label in labels) ...[
          TextField(decoration: InputDecoration(labelText: label)),
          const SizedBox(height: 10),
        ],
      ],
    );
  }
}

class _MiniChart extends StatelessWidget {
  const _MiniChart();

  @override
  Widget build(BuildContext context) {
    final values = [22, 34, 28, 49, 42, 58, 64];
    final scheme = Theme.of(context).colorScheme;
    return Card(
      child: SizedBox(
        height: 150,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: values
                .map((value) => Expanded(
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 4),
                        child: Container(
                          height: value * 1.7,
                          decoration: BoxDecoration(
                            color: scheme.primary,
                            borderRadius: BorderRadius.circular(10),
                          ),
                        ),
                      ),
                    ))
                .toList(),
          ),
        ),
      ),
    );
  }
}
