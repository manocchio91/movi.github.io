import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/auth/auth_controller.dart';
import '../../demo/data/demo_repository.dart';
import '../../demo/domain/demo_models.dart';
import '../../shared/ui/app_components.dart';

class CustomerHomeScreen extends ConsumerWidget {
  const CustomerHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final repo = ref.watch(demoRepositoryProvider);
    final user = ref.watch(authControllerProvider).user!;
    final events = repo.events;

    return ScreenScaffold(
      title: 'Ciao ${user.name}',
      actions: [
        IconButton(
          onPressed: () {},
          icon: Badge.count(
              count: 3, child: const Icon(Icons.notifications_none_rounded)),
          tooltip: 'Notifiche',
        ),
      ],
      children: [
        const SizedBox(height: 16),
        TextField(
          decoration: const InputDecoration(
            hintText: 'Cerca eventi, comuni o categorie',
            prefixIcon: Icon(Icons.search_rounded),
          ),
          onSubmitted: (_) => context.go('/customer/all-events'),
        ),
        const SectionTitle('Macroaree'),
        SizedBox(
          height: 44,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemBuilder: (_, index) => FilterChip(
              selected: index == 0,
              label: Text(repo.macroAreas[index]),
              avatar: const Icon(Icons.local_activity_rounded, size: 18),
              onSelected: (_) {},
            ),
            separatorBuilder: (_, __) => const SizedBox(width: 8),
            itemCount: repo.macroAreas.length,
          ),
        ),
        SectionTitle('Eventi consigliati',
            trailing: TextButton(
                onPressed: () => context.go('/customer/all-events'),
                child: const Text('Vedi tutti'))),
        EventCard(
            event: events.first, onTap: () => openEvent(context, events.first)),
        const SectionTitle('Vicini a te'),
        SizedBox(
          height: 430,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemBuilder: (_, index) => SizedBox(
              width: 280,
              child: EventCard(
                  event: events[index + 1],
                  compact: true,
                  onTap: () => openEvent(context, events[index + 1])),
            ),
            separatorBuilder: (_, __) => const SizedBox(width: 14),
            itemCount: events.length - 1,
          ),
        ),
        const SectionTitle('Questo weekend'),
        ...events.take(2).map((event) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: EventCard(
                  event: event,
                  compact: true,
                  onTap: () => openEvent(context, event)),
            )),
        const SectionTitle('Gli eventi più amati'),
        ...events.where((event) => event.rating >= 4.7).map((event) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: EventCard(
                  event: event,
                  compact: true,
                  onTap: () => openEvent(context, event)),
            )),
        const SectionTitle('Organizzatori seguiti'),
        ...repo.organizers.map((organizer) => ListTile(
              contentPadding: EdgeInsets.zero,
              leading:
                  CircleAvatar(child: Text(organizer.name.characters.first)),
              title: Text(organizer.name),
              subtitle: Text(
                  '${organizer.town} · ${organizer.followers} follower · ${organizer.rating}★'),
              trailing: const Icon(Icons.chevron_right_rounded),
            )),
        const SizedBox(height: 12),
        FilledButton.icon(
          onPressed: () => context.push('/customer/preferences'),
          icon: const Icon(Icons.tune_rounded),
          label: const Text('Imposta preferenze'),
        ),
      ],
    );
  }
}

class AllEventsScreen extends ConsumerWidget {
  const AllEventsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final repo = ref.watch(demoRepositoryProvider);
    return ScreenScaffold(
      title: 'Tutti gli Eventi',
      actions: [
        IconButton(
            onPressed: () {},
            icon: const Icon(Icons.map_outlined),
            tooltip: 'Mappa'),
      ],
      children: [
        const TextField(
            decoration: InputDecoration(
                hintText: 'Cerca in tutto il Molise',
                prefixIcon: Icon(Icons.search_rounded))),
        const SizedBox(height: 14),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            ActionChip(
                label: const Text('Filtri'),
                avatar: const Icon(Icons.tune_rounded),
                onPressed: () {}),
            const FilterChip(
                selected: true, label: Text('Bambini'), onSelected: null),
            const FilterChip(
                selected: false,
                label: Text('Questo weekend'),
                onSelected: null),
            const FilterChip(
                selected: false, label: Text('Campobasso'), onSelected: null),
            const FilterChip(
                selected: false, label: Text('Gratis'), onSelected: null),
            const FilterChip(
                selected: false, label: Text('Prezzo'), onSelected: null),
            const FilterChip(
                selected: false, label: Text('Ordina'), onSelected: null),
          ],
        ),
        const SectionTitle('Lista eventi'),
        ...repo.events.map((event) => Padding(
              padding: const EdgeInsets.only(bottom: 14),
              child: EventCard(
                  event: event, onTap: () => openEvent(context, event)),
            )),
        const EmptyState(
          title: 'Vista mappa predisposta',
          message:
              'Qui saranno mostrati marker e cluster quando collegheremo geocoding e map provider.',
          icon: Icons.map_rounded,
        ),
      ],
    );
  }
}

class EventDetailScreen extends ConsumerWidget {
  const EventDetailScreen({super.key, required this.id});

  final String id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final repo = ref.watch(demoRepositoryProvider);
    final event = repo.events
        .firstWhere((item) => item.id == id, orElse: () => repo.events.first);
    return Scaffold(
      appBar: AppBar(title: const Text('Dettaglio evento')),
      bottomNavigationBar: SafeArea(
        minimum: const EdgeInsets.all(16),
        child: FilledButton.icon(
          onPressed: () => context.push('/customer/events/${event.id}/book'),
          icon: const Icon(Icons.confirmation_number_rounded),
          label: Text(
              'Prenota · ${event.price == 0 ? 'Gratis' : '€${event.price.toStringAsFixed(0)}'}'),
        ),
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
          children: [
            LocalImage(tone: event.imageTone, height: 240),
            const SizedBox(height: 18),
            Text(event.title,
                style: Theme.of(context)
                    .textTheme
                    .headlineSmall
                    ?.copyWith(fontWeight: FontWeight.w900)),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                InfoPill(icon: Icons.storefront_rounded, text: event.organizer),
                InfoPill(
                    icon: Icons.local_activity_rounded, text: event.category),
                InfoPill(
                    icon: Icons.calendar_month_rounded,
                    text: '${event.dateLabel} · ${event.timeLabel}'),
                InfoPill(
                    icon: Icons.place_rounded,
                    text: '${event.town} (${event.province})'),
              ],
            ),
            const SectionTitle('Luogo'),
            const EmptyState(
                title: 'Mappa sintetica',
                message: 'Anteprima posizione pronta per integrazione mappe.',
                icon: Icons.location_on_rounded),
            const SectionTitle('Descrizione'),
            Text(event.shortDescription),
            const SizedBox(height: 8),
            Text(event.fullDescription),
            const SectionTitle('Programma'),
            ...event.program.map((item) => ListTile(
                leading: const Icon(Icons.check_circle_outline_rounded),
                title: Text(item))),
            const SectionTitle('Incluso'),
            Wrap(
                spacing: 8,
                runSpacing: 8,
                children: event.included
                    .map((item) => Chip(label: Text(item)))
                    .toList()),
            const SectionTitle('Non incluso'),
            Wrap(
                spacing: 8,
                runSpacing: 8,
                children: event.notIncluded
                    .map((item) => Chip(label: Text(item)))
                    .toList()),
            const SectionTitle('Informazioni utili'),
            Text(
                '${event.ageRange}\n${event.importantInfo}\n${event.cancellationPolicy}'),
            const SectionTitle('Recensioni'),
            ...repo.reviews.take(2).map((review) => ListTile(
                  leading: const Icon(Icons.star_rounded),
                  title: Text('${review.author} · ${review.rating}'),
                  subtitle: Text(review.comment),
                )),
            const SectionTitle('Eventi simili'),
            ...repo.events
                .where((item) => item.id != event.id)
                .take(2)
                .map((item) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: EventCard(
                          event: item,
                          compact: true,
                          onTap: () => openEvent(context, item)),
                    )),
          ],
        ),
      ),
    );
  }
}

class BookingFlowScreen extends StatefulWidget {
  const BookingFlowScreen({super.key, required this.id});

  final String id;

  @override
  State<BookingFlowScreen> createState() => _BookingFlowScreenState();
}

class _BookingFlowScreenState extends State<BookingFlowScreen> {
  int adults = 2;
  int kids = 0;
  bool accepted = false;
  bool confirmed = false;

  @override
  Widget build(BuildContext context) {
    return Consumer(builder: (context, ref, _) {
      final repo = ref.watch(demoRepositoryProvider);
      final event = repo.events.firstWhere((item) => item.id == widget.id,
          orElse: () => repo.events.first);
      final total = (adults * event.price) + (kids * event.price * .55);
      if (confirmed) {
        return ScreenScaffold(
          title: 'Prenotazione confermata',
          children: [
            const EmptyState(
                title: 'QR demo',
                message: 'Codice MOL-7K2P pronto per il check-in dimostrativo.',
                icon: Icons.qr_code_2_rounded),
            ListTile(
                title: Text(event.title),
                subtitle: Text(
                    '${adults + kids} partecipanti · €${total.toStringAsFixed(0)}')),
            const ListTile(title: Text('Stato'), subtitle: Text('Confermata')),
            FilledButton.icon(
                onPressed: () {},
                icon: const Icon(Icons.event_available_rounded),
                label: const Text('Aggiungi al calendario')),
          ],
        );
      }
      return ScreenScaffold(
        title: 'Prenota',
        children: [
          EventCard(event: event, compact: true),
          const SectionTitle('Biglietti'),
          DropdownButtonFormField<String>(
            initialValue: 'standard',
            items: const [
              DropdownMenuItem(value: 'standard', child: Text('Standard'))
            ],
            onChanged: (_) {},
            decoration: const InputDecoration(labelText: 'Tipologia biglietto'),
          ),
          _CounterTile(
              label: 'Adulti',
              value: adults,
              onChanged: (value) =>
                  setState(() => adults = value.clamp(1, 12).toInt())),
          _CounterTile(
              label: 'Bambini',
              value: kids,
              onChanged: (value) =>
                  setState(() => kids = value.clamp(0, 12).toInt())),
          const TextField(
              maxLines: 2, decoration: InputDecoration(labelText: 'Note')),
          const SizedBox(height: 12),
          const TextField(
              maxLines: 2,
              decoration: InputDecoration(labelText: 'Allergie o esigenze')),
          const SizedBox(height: 12),
          CheckboxListTile(
            value: accepted,
            onChanged: (value) => setState(() => accepted = value ?? false),
            title: const Text('Accetto condizioni e politica di cancellazione'),
            contentPadding: EdgeInsets.zero,
          ),
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Totale'),
            trailing: Text('€${total.toStringAsFixed(0)}',
                style: Theme.of(context)
                    .textTheme
                    .titleLarge
                    ?.copyWith(fontWeight: FontWeight.w900)),
          ),
          FilledButton(
              onPressed:
                  accepted ? () => setState(() => confirmed = true) : null,
              child: const Text('Conferma prenotazione')),
        ],
      );
    });
  }
}

class SuccessEventsScreen extends ConsumerWidget {
  const SuccessEventsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final repo = ref.watch(demoRepositoryProvider);
    return ScreenScaffold(
      title: 'Eventi di Successo',
      children: [
        ...repo.successEvents.map((event) => Padding(
              padding: const EdgeInsets.only(bottom: 14),
              child: Column(
                children: [
                  EventCard(event: event, badge: 'Evento di successo'),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                          child: MetricCard(
                              label: 'Riempimento',
                              value: '${event.fillRate}%',
                              icon: Icons.groups_rounded)),
                      const SizedBox(width: 10),
                      Expanded(
                          child: MetricCard(
                              label: 'Lo consiglierebbe',
                              value: '${event.recommendRate}%',
                              icon: Icons.thumb_up_rounded)),
                    ],
                  ),
                  Row(
                    children: [
                      Expanded(
                          child: OutlinedButton(
                              onPressed: event.hasNewEdition
                                  ? () => openEvent(
                                      context,
                                      ref
                                          .read(demoRepositoryProvider)
                                          .events
                                          .first)
                                  : null,
                              child: const Text('Vedi nuova edizione'))),
                      const SizedBox(width: 10),
                      Expanded(
                          child: FilledButton(
                              onPressed: () {}, child: const Text('Avvisami'))),
                    ],
                  ),
                ],
              ),
            )),
      ],
    );
  }
}

class BookingsScreen extends ConsumerWidget {
  const BookingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final bookings = ref.watch(demoRepositoryProvider).bookings;
    return DefaultTabController(
      length: 3,
      child: ScreenScaffold(
        title: 'Prenotazioni',
        bottom: const TabBar(tabs: [
          Tab(text: 'Prossime'),
          Tab(text: 'Concluse'),
          Tab(text: 'Annullate')
        ]),
        children: [
          SizedBox(
            height: MediaQuery.sizeOf(context).height * .72,
            child: TabBarView(
              children: [
                _BookingList(
                    bookings: bookings
                        .where((item) => item.status == 'Confermata')
                        .toList()),
                _BookingList(
                    bookings: bookings
                        .where((item) => item.status == 'Conclusa')
                        .toList()),
                const EmptyState(
                    title: 'Nessuna prenotazione annullata',
                    message: 'Qui appariranno cancellazioni e rimborsi.',
                    icon: Icons.event_busy_rounded),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class PreferencesScreen extends ConsumerWidget {
  const PreferencesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final repo = ref.watch(demoRepositoryProvider);
    return ScreenScaffold(
      title: 'Preferenze e notifiche',
      children: [
        const SectionTitle('Macroaree'),
        Wrap(
            spacing: 8,
            runSpacing: 8,
            children: repo.macroAreas
                .map((item) => FilterChip(
                    selected: item == 'Bambini' || item == 'Cene',
                    label: Text(item),
                    onSelected: (_) {}))
                .toList()),
        const SectionTitle('Territorio'),
        const TextField(
            decoration: InputDecoration(
                labelText: 'Comuni',
                prefixIcon: Icon(Icons.location_city_rounded))),
        const SizedBox(height: 12),
        const TextField(
            decoration: InputDecoration(
                labelText: 'Province', prefixIcon: Icon(Icons.map_rounded))),
        const SizedBox(height: 12),
        const ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text('Raggio'),
            subtitle: Text('25 km'),
            trailing: Icon(Icons.radar_rounded)),
        const SectionTitle('Notifiche'),
        SwitchListTile(
            value: true,
            onChanged: (_) {},
            title: const Text('Notifiche interne')),
        SwitchListTile(
            value: true, onChanged: (_) {}, title: const Text('Email')),
        SwitchListTile(
            value: false, onChanged: (_) {}, title: const Text('Push')),
        DropdownButtonFormField<String>(
          initialValue: 'giornaliera',
          decoration: const InputDecoration(labelText: 'Frequenza'),
          items: const [
            DropdownMenuItem(value: 'immediata', child: Text('Immediata')),
            DropdownMenuItem(value: 'giornaliera', child: Text('Giornaliera')),
            DropdownMenuItem(value: 'settimanale', child: Text('Settimanale')),
          ],
          onChanged: (_) {},
        ),
      ],
    );
  }
}

class ReviewScreen extends StatelessWidget {
  const ReviewScreen({super.key, required this.id});

  final String id;

  @override
  Widget build(BuildContext context) {
    return ScreenScaffold(
      title: 'Recensione',
      children: [
        for (final item in [
          'Voto generale',
          'Qualità esperienza',
          'Organizzazione',
          'Location',
          'Rapporto qualità-prezzo',
          'Rispetto orari',
          'Staff',
          'Adatto ai bambini'
        ])
          ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(item),
              trailing: const Text('★★★★★')),
        SwitchListTile(
            value: true,
            onChanged: (_) {},
            title: const Text('Lo consiglieresti?')),
        const TextField(
            maxLines: 4, decoration: InputDecoration(labelText: 'Commento')),
        const SizedBox(height: 12),
        OutlinedButton.icon(
            onPressed: () {},
            icon: const Icon(Icons.add_photo_alternate_rounded),
            label: const Text('Carica foto')),
        FilledButton(
            onPressed: () => context.pop(),
            child: const Text('Invia recensione')),
      ],
    );
  }
}

class CustomerProfileScreen extends ConsumerWidget {
  const CustomerProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider).user!;
    return ScreenScaffold(
      title: 'Profilo',
      children: [
        ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const CircleAvatar(child: Icon(Icons.person_rounded)),
          title: Text(auth.name),
          subtitle: Text(auth.email),
        ),
        for (final item in [
          'Preferenze',
          'Preferiti',
          'Organizzatori seguiti',
          'Recensioni lasciate',
          'Storico',
          'Notifiche',
          'Privacy'
        ])
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.chevron_right_rounded),
            title: Text(item),
            onTap: item == 'Preferenze'
                ? () => context.push('/customer/preferences')
                : null,
          ),
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

class _CounterTile extends StatelessWidget {
  const _CounterTile(
      {required this.label, required this.value, required this.onChanged});

  final String label;
  final int value;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(label),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
              onPressed: () => onChanged(value - 1),
              icon: const Icon(Icons.remove_circle_outline_rounded)),
          Text('$value'),
          IconButton(
              onPressed: () => onChanged(value + 1),
              icon: const Icon(Icons.add_circle_outline_rounded)),
        ],
      ),
    );
  }
}

class _BookingList extends StatelessWidget {
  const _BookingList({required this.bookings});

  final List<Booking> bookings;

  @override
  Widget build(BuildContext context) {
    if (bookings.isEmpty) {
      return const EmptyState(
          title: 'Nessun elemento',
          message: 'Non ci sono prenotazioni in questa sezione.',
          icon: Icons.inbox_rounded);
    }
    return ListView.separated(
      padding: EdgeInsets.zero,
      itemBuilder: (_, index) {
        final booking = bookings[index];
        return Card(
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                InfoPill(icon: Icons.verified_rounded, text: booking.status),
                const SizedBox(height: 10),
                Text(booking.event.title,
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium
                        ?.copyWith(fontWeight: FontWeight.w800)),
                Text(
                    '${booking.event.dateLabel} · ${booking.people} partecipanti · €${booking.total.toStringAsFixed(0)}'),
                const SizedBox(height: 10),
                Row(
                  children: [
                    OutlinedButton.icon(
                        onPressed: () {},
                        icon: const Icon(Icons.qr_code_rounded),
                        label: const Text('QR')),
                    const Spacer(),
                    if (booking.canCancel)
                      TextButton(
                          onPressed: () {}, child: const Text('Annulla')),
                    if (booking.canReview)
                      TextButton(
                          onPressed: () =>
                              context.push('/customer/review/${booking.code}'),
                          child: const Text('Recensisci')),
                  ],
                ),
              ],
            ),
          ),
        );
      },
      separatorBuilder: (_, __) => const SizedBox(height: 12),
      itemCount: bookings.length,
    );
  }
}
