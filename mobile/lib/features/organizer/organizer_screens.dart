import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/auth/auth_controller.dart';
import '../role_data/role_api_repository.dart';
import '../shared/state/event_data_revision.dart';
import '../shared/ui/real_components.dart';

class OrganizerDashboardScreen extends ConsumerWidget {
  const OrganizerDashboardScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final eventRevision =
        ref.watch(eventDomainStoreProvider.select((state) => state.revision));
    return _RoleFutureScreen(
      key: ValueKey(eventRevision),
      title: 'Dashboard',
      future: ref.read(roleApiRepositoryProvider).organizerDashboard(),
      builder: (data) {
        final bi = _map(data['bi']);
        return [
          const _HeroPanel(
            eyebrow: 'AREA ORGANIZER',
            title: 'La tua attività, sempre sotto controllo',
            message:
                'Eventi, partecipanti e risultati in un’unica vista operativa.',
            icon: Icons.insights_rounded,
          ),
          const SectionTitle('Panoramica'),
          _MetricGrid(items: [
            (
              'Eventi futuri',
              _value(data, bi, 'future_experiences', 'future_events'),
              Icons.event_available_rounded
            ),
            (
              'Prenotazioni',
              _value(data, bi, 'bookings_total', 'bookings_received'),
              Icons.confirmation_number_rounded
            ),
            (
              'Partecipanti',
              _value(data, bi, 'participants_total', 'participants_total'),
              Icons.groups_rounded
            ),
            (
              'Posti disponibili',
              _value(data, bi, 'available_places', 'available_places'),
              Icons.event_seat_rounded
            ),
            (
              'Follower',
              _value(data, bi, 'followers', 'followers_total'),
              Icons.favorite_rounded
            ),
            ('Recensioni', '${data['reviews'] ?? 0}', Icons.star_rounded),
            (
              'Impression',
              '${bi['impressions'] ?? 0}',
              Icons.visibility_outlined
            ),
            (
              'Aperture',
              '${bi['detail_views'] ?? 0}',
              Icons.touch_app_outlined
            ),
            ('Condivisioni', '${bi['shares'] ?? 0}', Icons.share_outlined),
            (
              'Cancellazioni',
              '${bi['cancellations'] ?? 0}',
              Icons.event_busy_outlined
            ),
            (
              'Check-in',
              '${bi['check_in_people'] ?? 0}',
              Icons.how_to_reg_outlined
            ),
            (
              'No-show',
              '${bi['no_show_people'] ?? 0}',
              Icons.person_off_outlined
            ),
            (
              'Conversione',
              '${bi['booking_conversion_rate'] ?? 0}%',
              Icons.trending_up_rounded
            ),
            (
              'Partecipazione',
              '${bi['attendance_rate'] ?? 0}%',
              Icons.groups_2_outlined
            ),
          ]),
          const SectionTitle('Azioni rapide'),
          _ActionCard(
              icon: Icons.restaurant_menu,
              title: 'MòVi Manager',
              subtitle: 'Gestisci attività, servizi e prenotazioni',
              onTap: () => context.push('/organizer/activities')),
          const SizedBox(height: 12),
          _ActionCard(
              icon: Icons.add_circle_rounded,
              title: 'Crea evento',
              subtitle: 'Inserisci i dati e salva una nuova bozza',
              onTap: () => context.push('/organizer/events/create')),
          const SizedBox(height: 12),
          _ActionCard(
              icon: Icons.event_note_rounded,
              title: 'Gestisci eventi',
              subtitle: 'Controlla stato, pubblicazione e partecipanti',
              onTap: () => context.go('/organizer/events')),
          const SizedBox(height: 12),
          _ActionCard(
              icon: Icons.qr_code_scanner_rounded,
              title: 'Check-in',
              subtitle: 'Apri una prenotazione e registra l’ingresso',
              onTap: () => context.go('/organizer/bookings')),
        ];
      },
    );
  }
}

class OrganizerEventsScreen extends ConsumerStatefulWidget {
  const OrganizerEventsScreen({super.key});
  @override
  ConsumerState<OrganizerEventsScreen> createState() =>
      _OrganizerEventsScreenState();
}

class _OrganizerEventsScreenState extends ConsumerState<OrganizerEventsScreen> {
  String filter = 'all';
  int refresh = 0;
  @override
  Widget build(BuildContext context) {
    final eventRevision =
        ref.watch(eventDomainStoreProvider.select((state) => state.revision));
    return _RoleFutureScreen(
      key: ValueKey('$filter-$refresh-$eventRevision'),
      title: 'Eventi',
      future:
          ref.read(roleApiRepositoryProvider).organizerEvents(filter: filter),
      header: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        FilledButton.icon(
          onPressed: () async {
            final created =
                await context.push<bool>('/organizer/events/create');
            if (created == true && mounted) {
              setState(() => refresh++);
            }
          },
          icon: const Icon(Icons.add_rounded),
          label: const Text('Crea evento'),
        ),
        const SizedBox(height: 14),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(children: [
            for (final item in const [
              ('all', 'Tutti'),
              ('future', 'Futuri'),
              ('unpublished', 'Bozze'),
              ('completed', 'Conclusi')
            ])
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  label: Text(item.$2),
                  selected: filter == item.$1,
                  onSelected: (_) => setState(() => filter = item.$1),
                ),
              ),
          ]),
        ),
      ]),
      builder: (items) => items.isEmpty
          ? const [
              EmptyState(
                  title: 'Nessun evento',
                  message: 'Non ci sono eventi per questo filtro.',
                  icon: Icons.event_busy_rounded)
            ]
          : [
              for (final event in items)
                Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: _EntityCard(
                      icon: Icons.local_activity_rounded,
                      title: '${event['title'] ?? 'Evento'}',
                      subtitle: _date(event['starts_at']),
                      status: '${event['status'] ?? 'draft'}',
                      details:
                          '${_map(event['summary'])['booked_people'] ?? 0} / ${_map(event['summary'])['capacity_total'] ?? event['capacity_total'] ?? 0} prenotati · ${_map(event['summary'])['available_total'] ?? event['available_total'] ?? 0} disponibili',
                      onTap: () async {
                        await context
                            .push('/organizer/events/${event['ulid']}');
                        if (mounted) setState(() => refresh++);
                      },
                    ))
            ],
    );
  }
}

class OrganizerEventDetailScreen extends ConsumerStatefulWidget {
  const OrganizerEventDetailScreen({super.key, required this.id});
  final String id;
  @override
  ConsumerState<OrganizerEventDetailScreen> createState() =>
      _OrganizerEventDetailScreenState();
}

class _OrganizerEventDetailScreenState
    extends ConsumerState<OrganizerEventDetailScreen> {
  int refresh = 0;
  Map<String, dynamic>? updatedEvent;
  @override
  Widget build(BuildContext context) => _RoleFutureScreen(
        key: ValueKey(refresh),
        title: 'Dettaglio evento',
        future: updatedEvent == null
            ? ref.read(roleApiRepositoryProvider).organizerEvent(widget.id)
            : Future.value(updatedEvent!),
        builder: (event) => [
          _HeroPanel(
              eyebrow: _statusLabel('${event['status'] ?? ''}'),
              title: '${event['title'] ?? 'Evento'}',
              message: _date(event['starts_at']),
              icon: Icons.local_activity_rounded),
          const SectionTitle('Informazioni'),
          _DetailTile(
              icon: Icons.place_rounded,
              label: 'Luogo',
              value:
                  '${_map(event['municipality'])['name'] ?? event['location_name'] ?? 'Da definire'}'),
          _DetailTile(
              icon: Icons.groups_rounded,
              label: 'Capienza',
              value:
                  '${_map(event['summary_metrics'])['capacity_total'] ?? event['capacity_total'] ?? 0} totali · ${_map(event['summary_metrics'])['booked_people'] ?? 0} prenotati · ${_map(event['summary_metrics'])['available_total'] ?? event['available_total'] ?? 0} disponibili'),
          ..._list(event['ticket_types']).map((ticket) {
            final maximum = _integer(ticket['max_quantity']);
            final sold = _integer(ticket['booked_quantity']);
            return _DetailTile(
              icon: Icons.confirmation_number_rounded,
              label: '${ticket['name'] ?? 'Ticket'}',
              value: 'Quantità massima ticket: $maximum · '
                  'Biglietti venduti: $sold · '
                  'Biglietti disponibili: ${(maximum - sold).clamp(0, maximum)}',
            );
          }),
          _DetailTile(
              icon: Icons.schedule_rounded,
              label: 'Chiusura prenotazioni',
              value: _date(event['booking_closes_at'])),
          const SectionTitle('Operatività'),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: () async {
                final changed = await context.push<Map<String, dynamic>>(
                    '/organizer/events/${widget.id}/edit');
                if (changed != null && mounted) {
                  setState(() {
                    updatedEvent = changed;
                    refresh++;
                  });
                }
              },
              icon: const Icon(Icons.edit_rounded),
              label: const Text('Modifica evento'),
            ),
          ),
          const SizedBox(height: 12),
          _ActionCard(
              icon: Icons.groups_rounded,
              title: 'Partecipanti e check-in',
              subtitle: 'Elenco, ricerca, telefono e ingresso manuale',
              onTap: () =>
                  context.push('/organizer/events/${widget.id}/participants')),
          if ('${event['status']}' == 'draft' ||
              '${event['status']}' == 'rejected') ...[
            const SizedBox(height: 12),
            FilledButton.icon(
                onPressed: () => _eventAction(context, 'publish'),
                icon: const Icon(Icons.publish_rounded),
                label: const Text('Invia in pubblicazione')),
          ],
          if (!const ['cancelled', 'completed', 'archived']
              .contains('${event['status']}')) ...[
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: () => _cancelEvent(context),
              icon: const Icon(Icons.event_busy_rounded),
              label: const Text('Annulla evento'),
              style: OutlinedButton.styleFrom(
                foregroundColor: Theme.of(context).colorScheme.error,
              ),
            ),
          ],
        ],
      );

  Future<void> _cancelEvent(BuildContext context) async {
    final reason = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Annullare questo evento?'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Le prenotazioni saranno annullate e tutti i partecipanti riceveranno una notifica diretta con il motivo.',
            ),
            const SizedBox(height: 16),
            TextField(
              controller: reason,
              autofocus: true,
              minLines: 2,
              maxLines: 4,
              maxLength: 1000,
              decoration: const InputDecoration(
                labelText: 'Motivo dell’annullamento',
                hintText: 'Spiega ai partecipanti perché l’evento è annullato',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Torna indietro'),
          ),
          FilledButton(
            onPressed: () {
              if (reason.text.trim().isEmpty) return;
              Navigator.pop(dialogContext, true);
            },
            child: const Text('Conferma annullamento'),
          ),
        ],
      ),
    );
    final message = reason.text.trim();
    reason.dispose();
    if (confirmed != true || message.isEmpty || !mounted) return;

    try {
      await ref.read(roleApiRepositoryProvider).post(
        '/organizer/events/${widget.id}/cancel',
        {'reason_code': 'ORGANIZER_CANCELLATION', 'reason': message},
      );
      if (!context.mounted) return;
      setState(() => refresh++);
      _snack(context, 'Evento annullato. I partecipanti sono stati avvisati.');
    } catch (error) {
      if (context.mounted) _snack(context, '$error', error: true);
    }
  }

  Future<void> _eventAction(BuildContext context, String action) async {
    try {
      await ref
          .read(roleApiRepositoryProvider)
          .post('/organizer/events/${widget.id}/$action');
      if (mounted) setState(() => refresh++);
    } catch (error) {
      if (context.mounted) _snack(context, '$error', error: true);
    }
  }
}

class OrganizerBookingsScreen extends ConsumerStatefulWidget {
  const OrganizerBookingsScreen({super.key});
  @override
  ConsumerState<OrganizerBookingsScreen> createState() =>
      _OrganizerBookingsScreenState();
}

class _OrganizerBookingsScreenState
    extends ConsumerState<OrganizerBookingsScreen> {
  final search = TextEditingController();
  int refresh = 0;
  String filter = 'confirmed';
  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => _RoleFutureScreen(
        key: ValueKey(refresh),
        title: 'Prenotazioni',
        future: ref
            .read(roleApiRepositoryProvider)
            .organizerBookings(query: search.text, filter: filter),
        header: Column(children: [
          SegmentedButton<String>(
            segments: const [
              ButtonSegment(value: 'confirmed', label: Text('Confermate')),
              ButtonSegment(value: 'cancelled', label: Text('Cancellate')),
              ButtonSegment(value: 'all', label: Text('Tutte')),
            ],
            selected: {filter},
            onSelectionChanged: (value) => setState(() {
              filter = value.first;
              refresh++;
            }),
          ),
          const SizedBox(height: 12),
          TextField(
              controller: search,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => setState(() => refresh++),
              decoration: const InputDecoration(
                  hintText: 'Cerca cliente o codice',
                  prefixIcon: Icon(Icons.search_rounded))),
        ]),
        builder: (items) => items.isEmpty
            ? const [
                EmptyState(
                    title: 'Nessuna prenotazione',
                    message: 'Le prenotazioni ricevute appariranno qui.',
                    icon: Icons.confirmation_number_outlined)
              ]
            : [
                for (final item in items)
                  Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _EntityCard(
                        icon: Icons.confirmation_number_rounded,
                        title: '${_map(item['customer'])['name'] ?? 'Cliente'}',
                        subtitle:
                            '${_map(item['event'])['title'] ?? ''}\n${item['booking_code'] ?? ''}',
                        status: '${item['status'] ?? ''}',
                        details: '${item['people'] ?? 0} persone',
                        onTap: () =>
                            context.push('/organizer/bookings/${item['ulid']}'),
                      )),
              ],
      );
}

class OrganizerBookingDetailScreen extends ConsumerWidget {
  const OrganizerBookingDetailScreen({super.key, required this.id});
  final String id;
  @override
  Widget build(BuildContext context, WidgetRef ref) => _RoleFutureScreen(
        title: 'Dettaglio prenotazione',
        future: ref.read(roleApiRepositoryProvider).organizerBooking(id),
        builder: (item) {
          final customer = _map(item['customer']);
          final event = _map(item['event']);
          return [
            _HeroPanel(
                eyebrow: _statusLabel('${item['status']}'),
                title: '${customer['name'] ?? 'Cliente'}',
                message: '${event['title'] ?? ''}',
                icon: Icons.confirmation_number_rounded),
            const SectionTitle('Contatti e accesso'),
            _DetailTile(
                icon: Icons.badge_rounded,
                label: 'Codice',
                value: '${item['booking_code'] ?? ''}'),
            _DetailTile(
                icon: Icons.mail_rounded,
                label: 'Email',
                value: '${customer['email'] ?? ''}'),
            _DetailTile(
                icon: Icons.phone_rounded,
                label: 'Telefono',
                value: '${customer['phone'] ?? 'Non disponibile'}'),
            _DetailTile(
                icon: Icons.groups_rounded,
                label: 'Partecipanti',
                value: '${item['people'] ?? 0}'),
            if (item['checked_in_at'] != null)
              _DetailTile(
                  icon: Icons.verified_rounded,
                  label: 'Check-in',
                  value: _date(item['checked_in_at'])),
          ];
        },
      );
}

class OrganizerParticipantsScreen extends ConsumerStatefulWidget {
  const OrganizerParticipantsScreen({super.key, required this.eventId});
  final String eventId;
  @override
  ConsumerState<OrganizerParticipantsScreen> createState() =>
      _OrganizerParticipantsScreenState();
}

class _OrganizerParticipantsScreenState
    extends ConsumerState<OrganizerParticipantsScreen> {
  final search = TextEditingController();
  int refresh = 0;
  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => _RoleFutureScreen(
        key: ValueKey(refresh),
        title: 'Partecipanti',
        future: ref
            .read(roleApiRepositoryProvider)
            .participants(widget.eventId, query: search.text),
        header: Column(children: [
          TextField(
              controller: search,
              onSubmitted: (_) => setState(() => refresh++),
              decoration: const InputDecoration(
                  hintText: 'Nome, email o codice',
                  prefixIcon: Icon(Icons.search_rounded))),
          const SizedBox(height: 12),
          SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                  onPressed: _manualCheckIn,
                  icon: const Icon(Icons.qr_code_scanner_rounded),
                  label: const Text('Check-in con codice')))
        ]),
        builder: (body) {
          final summary = _map(body['summary']);
          final items = _list(body['data']);
          return [
            _MetricGrid(items: [
              (
                'Prenotazioni',
                '${summary['bookings_total'] ?? 0}',
                Icons.confirmation_number_rounded
              ),
              (
                'Persone',
                '${summary['people_total'] ?? 0}',
                Icons.groups_rounded
              ),
              (
                'Entrati',
                '${summary['checked_in_total'] ?? 0}',
                Icons.verified_rounded
              )
            ]),
            const SectionTitle('Elenco'),
            if (items.isEmpty)
              const EmptyState(
                  title: 'Nessun partecipante',
                  message: 'Modifica la ricerca o attendi nuove prenotazioni.',
                  icon: Icons.group_off_rounded),
            for (final item in items)
              Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _EntityCard(
                      icon: item['checked_in'] == true
                          ? Icons.verified_rounded
                          : Icons.person_rounded,
                      title: '${item['name'] ?? ''}',
                      subtitle:
                          '${item['phone'] ?? 'Telefono non disponibile'}\n${item['booking_code'] ?? ''}',
                      status:
                          item['checked_in'] == true ? 'checked_in' : 'pending',
                      details: '${item['people'] ?? 0} persone')),
          ];
        },
      );
  Future<void> _manualCheckIn() async {
    final controller = TextEditingController();
    final code = await showDialog<String>(
        context: context,
        builder: (dialogContext) => AlertDialog(
                title: const Text('Check-in manuale'),
                content: TextField(
                    controller: controller,
                    autofocus: true,
                    textCapitalization: TextCapitalization.characters,
                    decoration: const InputDecoration(
                        labelText: 'Codice prenotazione',
                        prefixIcon: Icon(Icons.pin_rounded))),
                actions: [
                  TextButton(
                      onPressed: () => Navigator.pop(dialogContext),
                      child: const Text('Annulla')),
                  FilledButton(
                      onPressed: () =>
                          Navigator.pop(dialogContext, controller.text),
                      child: const Text('Conferma'))
                ]));
    controller.dispose();
    if (code == null || code.trim().isEmpty) return;
    try {
      await ref.read(roleApiRepositoryProvider).checkIn(widget.eventId, code);
      if (mounted) {
        _snack(context, 'Check-in registrato.');
        setState(() => refresh++);
      }
    } catch (error) {
      if (mounted) _snack(context, '$error', error: true);
    }
  }
}

class OrganizerProfileScreen extends ConsumerWidget {
  const OrganizerProfileScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => _RoleFutureScreen(
        title: 'Profilo',
        future: ref.read(roleApiRepositoryProvider).organizerProfile(),
        builder: (profile) => [
          _HeroPanel(
              eyebrow: _statusLabel('${profile['verification_status'] ?? ''}'),
              title: '${profile['name'] ?? 'Organizer'}',
              message: '${profile['public_email'] ?? profile['email'] ?? ''}',
              icon: Icons.storefront_rounded),
          const SectionTitle('Attività'),
          _DetailTile(
              icon: Icons.category_rounded,
              label: 'Tipologia',
              value: '${profile['type'] ?? 'Non indicata'}'),
          _DetailTile(
              icon: Icons.place_rounded,
              label: 'Sede',
              value:
                  '${profile['address'] ?? ''} ${profile['street_number'] ?? ''}, ${profile['postal_code'] ?? ''} ${profile['province'] ?? ''}'),
          _DetailTile(
              icon: Icons.verified_user_rounded,
              label: 'Stato approvazione',
              value: _statusLabel('${profile['verification_status'] ?? ''}')),
          const SizedBox(height: 24),
          OutlinedButton.icon(
              onPressed: () =>
                  ref.read(authControllerProvider.notifier).logout(),
              icon: const Icon(Icons.logout_rounded),
              label: const Text('Esci dall’account')),
        ],
      );
}

class _RoleFutureScreen<T> extends StatefulWidget {
  const _RoleFutureScreen(
      {super.key,
      required this.title,
      required this.future,
      required this.builder,
      this.header});
  final String title;
  final Future<T> future;
  final List<Widget> Function(T) builder;
  final Widget? header;
  @override
  State<_RoleFutureScreen<T>> createState() => _RoleFutureScreenState<T>();
}

class _RoleFutureScreenState<T> extends State<_RoleFutureScreen<T>> {
  late Future<T> future = widget.future;
  @override
  Widget build(BuildContext context) =>
      ScreenScaffold(title: widget.title, children: [
        if (widget.header != null) ...[
          widget.header!,
          const SizedBox(height: 18)
        ],
        FutureBuilder<T>(
            future: future,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const LoadingSkeleton();
              }
              if (snapshot.hasError) {
                return Column(children: [
                  EmptyState(
                      title: 'Qualcosa non ha funzionato',
                      message: '${snapshot.error}',
                      icon: Icons.cloud_off_rounded),
                  const SizedBox(height: 12),
                  FilledButton.icon(
                      onPressed: () => setState(() => future = widget.future),
                      icon: const Icon(Icons.refresh_rounded),
                      label: const Text('Riprova'))
                ]);
              }
              return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: widget.builder(snapshot.data as T));
            }),
      ]);
}

class _HeroPanel extends StatelessWidget {
  const _HeroPanel(
      {required this.eyebrow,
      required this.title,
      required this.message,
      required this.icon});
  final String eyebrow, title, message;
  final IconData icon;
  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
        padding: const EdgeInsets.all(22),
        decoration: BoxDecoration(
            gradient: const LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  Color(0xFF312E81),
                  Color(0xFF7C3AED),
                  Color(0xFFEC4899)
                ]),
            borderRadius: BorderRadius.circular(24)),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Expanded(
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                Text(eyebrow,
                    style: TextStyle(
                        color: colors.onPrimary.withValues(alpha: .8),
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1.2,
                        fontSize: 11)),
                const SizedBox(height: 8),
                Text(title,
                    style: Theme.of(context)
                        .textTheme
                        .headlineSmall
                        ?.copyWith(color: colors.onPrimary)),
                const SizedBox(height: 8),
                Text(message,
                    style: TextStyle(
                        color: colors.onPrimary.withValues(alpha: .9)))
              ])),
          const SizedBox(width: 12),
          Icon(icon, size: 44, color: colors.onPrimary)
        ]));
  }
}

class _MetricGrid extends StatelessWidget {
  const _MetricGrid({required this.items});
  final List<(String, String, IconData)> items;
  @override
  Widget build(BuildContext context) => LayoutBuilder(
      builder: (context, c) => GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: c.maxWidth > 540 ? 3 : 2,
              crossAxisSpacing: 12,
              mainAxisSpacing: 12,
              childAspectRatio: 1.35),
          itemCount: items.length,
          itemBuilder: (_, i) => MetricCard(
              label: items[i].$1, value: items[i].$2, icon: items[i].$3)));
}

class _ActionCard extends StatelessWidget {
  const _ActionCard(
      {required this.icon,
      required this.title,
      required this.subtitle,
      required this.onTap});
  final IconData icon;
  final String title, subtitle;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => Card(
      child: ListTile(
          contentPadding: const EdgeInsets.all(16),
          leading: Container(
              padding: const EdgeInsets.all(11),
              decoration: BoxDecoration(
                  gradient: const LinearGradient(
                      colors: [Color(0xFF7C3AED), Color(0xFFEC4899)]),
                  borderRadius: BorderRadius.circular(14)),
              child: Icon(icon, color: Colors.white)),
          title: Text(title),
          subtitle: Text(subtitle),
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: onTap));
}

class _EntityCard extends StatelessWidget {
  const _EntityCard(
      {required this.icon,
      required this.title,
      required this.subtitle,
      required this.status,
      required this.details,
      this.onTap});
  final IconData icon;
  final String title, subtitle, status, details;
  final VoidCallback? onTap;
  @override
  Widget build(BuildContext context) => Card(
      child: InkWell(
          borderRadius: BorderRadius.circular(18),
          onTap: onTap,
          child: Padding(
              padding: const EdgeInsets.all(16),
              child:
                  Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                CircleAvatar(child: Icon(icon)),
                const SizedBox(width: 14),
                Expanded(
                    child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                      Text(title,
                          style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: 4),
                      Text(subtitle,
                          maxLines: 3, overflow: TextOverflow.ellipsis),
                      const SizedBox(height: 10),
                      Wrap(spacing: 8, runSpacing: 8, children: [
                        StatusBadge(status: status),
                        InfoPill(icon: Icons.people_rounded, text: details)
                      ])
                    ])),
                if (onTap != null) const Icon(Icons.chevron_right_rounded)
              ]))));
}

class _DetailTile extends StatelessWidget {
  const _DetailTile(
      {required this.icon, required this.label, required this.value});
  final IconData icon;
  final String label, value;
  @override
  Widget build(BuildContext context) => Card(
      child: ListTile(
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          leading: Icon(icon, color: Theme.of(context).colorScheme.primary),
          title: Text(label),
          subtitle: Text(value.isEmpty ? 'Non disponibile' : value)));
}

Map<String, dynamic> _map(Object? value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};
List<Map<String, dynamic>> _list(Object? value) => value is List
    ? value.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
    : [];
int _integer(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
String _date(Object? value) {
  if (value == null) return 'Data non disponibile';
  final date = DateTime.tryParse('$value')?.toLocal();
  return date == null
      ? '$value'
      : '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year} · ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
}

String _value(Map<String, dynamic> data, Map<String, dynamic> bi, String a,
        String b) =>
    '${data[a] ?? bi[b] ?? 0}';
String _statusLabel(String value) => value
    .replaceAll('_', ' ')
    .split(' ')
    .map((e) => e.isEmpty ? '' : '${e[0].toUpperCase()}${e.substring(1)}')
    .join(' ');
void _snack(BuildContext context, String message, {bool error = false}) =>
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(message),
        backgroundColor: error ? Theme.of(context).colorScheme.error : null));
