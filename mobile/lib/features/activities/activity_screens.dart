import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/auth/auth_controller.dart';
import '../role_data/role_api_repository.dart';
import 'activity_repository.dart';

final activityRepositoryProvider = Provider((ref) => ActivityRepository(
    ref.watch(apiClientProvider), ref.watch(authenticationRepositoryProvider)));
final activitiesProvider =
    FutureProvider((ref) => ref.watch(activityRepositoryProvider).activities());
final reservationsProvider = FutureProvider(
    (ref) => ref.watch(activityRepositoryProvider).reservations());

class ActivityListScreen extends ConsumerWidget {
  const ActivityListScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
      appBar: AppBar(title: const Text('Attività prenotabili')),
      body: ref.watch(activitiesProvider).when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => Center(child: Text('$e')),
          data: (items) => ListView(
              children: items
                  .map((a) => ListTile(
                      leading: const Icon(Icons.restaurant),
                      title: Text(a.name),
                      subtitle: Text(a.description, maxLines: 2),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () =>
                          context.push('/customer/activities/${a.id}')))
                  .toList())));
}

class ActivityDetailScreen extends ConsumerStatefulWidget {
  const ActivityDetailScreen({super.key, required this.id});
  final String id;
  @override
  ConsumerState<ActivityDetailScreen> createState() => _ActivityDetailState();
}

class _ActivityDetailState extends ConsumerState<ActivityDetailScreen> {
  final phone = TextEditingController();
  int party = 2;
  String? service;
  List<Map<String, dynamic>> slots = [];
  String get day {
    final date = DateTime.now().add(const Duration(days: 1));
    return '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  }

  @override
  void dispose() {
    phone.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => FutureBuilder(
      future: ref.read(activityRepositoryProvider).activity(widget.id),
      builder: (context, snapshot) {
        if (!snapshot.hasData) {
          return Scaffold(
              appBar: AppBar(),
              body: Center(
                  child: snapshot.hasError
                      ? Text('${snapshot.error}')
                      : const CircularProgressIndicator()));
        }
        final activity = snapshot.data!;
        service ??=
            activity.services.isEmpty ? null : activity.services.first.id;
        return Scaffold(
            appBar: AppBar(title: Text(activity.name), actions: [
              IconButton(
                  icon: Icon(activity.following
                      ? Icons.favorite
                      : Icons.favorite_border),
                  onPressed: () async {
                    await ref
                        .read(activityRepositoryProvider)
                        .follow(activity.id, !activity.following);
                    if (mounted) setState(() {});
                  })
            ]),
            body: ListView(padding: const EdgeInsets.all(16), children: [
              Text(activity.description),
              FutureBuilder<MenuModel?>(
                  future:
                      ref.read(activityRepositoryProvider).menu(activity.id),
                  builder: (context, menuSnapshot) {
                    final menu = menuSnapshot.data;
                    if (menu == null) return const SizedBox.shrink();
                    return Card(
                        child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text('MENU · ${menu.name}',
                                      style: Theme.of(context)
                                          .textTheme
                                          .titleLarge),
                                  ...menu.sections.expand((section) => [
                                        Text(section.name,
                                            style: Theme.of(context)
                                                .textTheme
                                                .titleMedium),
                                        ...section.items.map((item) => ListTile(
                                            title: Text(item.name),
                                            subtitle: Text(
                                                '${item.description}${item.allergens.isEmpty ? '' : ' · Allergeni dichiarati: ${item.allergens.join(', ')}'}'),
                                            trailing: Text(item.priceCents ==
                                                    null
                                                ? '—'
                                                : '€ ${(item.priceCents! / 100).toStringAsFixed(2)}${item.available ? '' : ' · Non disponibile'}'))),
                                      ]),
                                  const Text(
                                      'Le informazioni sugli allergeni provengono dal locale. Comunica sempre allergie al ristoratore.'),
                                ])));
                  }),
              DropdownButtonFormField(
                  initialValue: service,
                  decoration: const InputDecoration(labelText: 'Servizio'),
                  items: activity.services
                      .map((item) => DropdownMenuItem(
                          value: item.id, child: Text(item.name)))
                      .toList(),
                  onChanged: (value) => service = value),
              TextFormField(
                  initialValue: '2',
                  decoration: const InputDecoration(labelText: 'Persone'),
                  keyboardType: TextInputType.number,
                  onChanged: (value) => party = int.tryParse(value) ?? 2),
              TextField(
                  controller: phone,
                  decoration: const InputDecoration(labelText: 'Telefono'),
                  keyboardType: TextInputType.phone),
              const SizedBox(height: 12),
              FilledButton(
                  onPressed: service == null
                      ? null
                      : () async {
                          slots = await ref
                              .read(activityRepositoryProvider)
                              .availability(activity.id, service!, day);
                          if (mounted) setState(() {});
                        },
                  child: Text('Disponibilità $day')),
              ...slots.map((slot) {
                final remaining =
                    (slot['remaining_capacity'] as num?)?.toInt() ?? 0;
                final start = '${slot['starts_at']}';
                return ListTile(
                    title: Text(
                        start.length >= 16 ? start.substring(11, 16) : start),
                    subtitle: Text(remaining == 0
                        ? 'Esaurito'
                        : remaining <= 3
                            ? 'Pochi posti'
                            : 'Disponibile'),
                    trailing: FilledButton(
                        onPressed:
                            remaining >= party && phone.text.trim().isNotEmpty
                                ? () async {
                                    final reservation = await ref
                                        .read(activityRepositoryProvider)
                                        .reserve(activity.id, service!, start,
                                            party, phone.text.trim());
                                    ref.invalidate(reservationsProvider);
                                    if (context.mounted) {
                                      ScaffoldMessenger.of(context)
                                          .showSnackBar(SnackBar(
                                              content: Text(
                                                  'Prenotazione ${reservation.status}')));
                                    }
                                  }
                                : null,
                        child: const Text('Prenota')));
              }),
              OutlinedButton(
                  onPressed: () async {
                    await ref
                        .read(activityRepositoryProvider)
                        .review(activity.id, 5, 'Esperienza consigliata');
                    if (context.mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Recensione inviata')));
                    }
                  },
                  child: const Text('Recensione verificata'))
            ]));
      });
}

class ActivityReservationsScreen extends ConsumerWidget {
  const ActivityReservationsScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
      appBar: AppBar(title: const Text('Prenotazioni locali')),
      body: ref.watch(reservationsProvider).when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => Center(child: Text('$e')),
          data: (rows) => ListView(
              children: rows
                  .map((reservation) => ListTile(
                      title: Text(
                          '${reservation.activity} · ${reservation.service}'),
                      subtitle: Text(
                          '${reservation.startsAt} · ${reservation.partySize} persone · ${reservation.status}'),
                      trailing: reservation.status == 'confirmed'
                          ? PopupMenuButton<String>(
                              onSelected: (action) async {
                                final repository =
                                    ref.read(activityRepositoryProvider);
                                if (action == 'cancel') {
                                  await repository.cancel(reservation.id);
                                  ref.invalidate(reservationsProvider);
                                  return;
                                }
                                final menu = await repository
                                    .menu(reservation.activityId);
                                final items = menu?.sections
                                        .expand((section) => section.items)
                                        .where((item) => item.available)
                                        .toList() ??
                                    const [];
                                if (items.isEmpty || !context.mounted) return;
                                final item = items.first;
                                await repository.preorder(
                                    reservation.id, item.id, 1);
                                if (context.mounted) {
                                  ScaffoldMessenger.of(context).showSnackBar(
                                      const SnackBar(
                                          content: Text(
                                              'Pre-order confermato. Pagherai direttamente al locale.')));
                                }
                              },
                              itemBuilder: (_) => const [
                                    PopupMenuItem(
                                        value: 'preorder',
                                        child: Text('SCEGLI DAL MENU')),
                                    PopupMenuItem(
                                        value: 'cancel',
                                        child: Text('Cancella'))
                                  ])
                          : null))
                  .toList())));
}

class OrganizerActivityManagerScreen extends ConsumerWidget {
  const OrganizerActivityManagerScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
        appBar: AppBar(title: const Text('MòVi Manager')),
        body: FutureBuilder(
          future: Future.wait([
            ref.read(roleApiRepositoryProvider).get('/organizer/activities'),
            ref
                .read(roleApiRepositoryProvider)
                .get('/organizer/reservations/dashboard'),
            ref
                .read(roleApiRepositoryProvider)
                .get('/organizer/reservations/analytics'),
          ]),
          builder: (context, snapshot) {
            if (!snapshot.hasData) {
              return Center(
                  child: snapshot.hasError
                      ? Text('${snapshot.error}')
                      : const CircularProgressIndicator());
            }
            final responses = snapshot.data!;
            final activities = _rows(responses[0]['data']);
            final dashboard =
                Map<String, dynamic>.from(responses[1]['data'] as Map);
            final today = Map<String, dynamic>.from(dashboard['today'] as Map);
            final analytics =
                Map<String, dynamic>.from(responses[2]['data'] as Map);
            return ListView(padding: const EdgeInsets.all(16), children: [
              Wrap(spacing: 8, runSpacing: 8, children: [
                Chip(label: Text('${today['reservations']} prenotazioni oggi')),
                Chip(label: Text('${today['covers']} coperti')),
                Chip(label: Text('${today['cancellations']} cancellazioni')),
                Chip(label: Text('${analytics['no_show_rate']}% no-show')),
              ]),
              const SizedBox(height: 12),
              FilledButton.icon(
                  onPressed: () async {
                    await ref
                        .read(roleApiRepositoryProvider)
                        .post('/organizer/activities', {
                      'name': 'Nuovo ristorante',
                      'type': 'restaurant',
                      'description': '',
                      'status': 'draft',
                      'booking_enabled': false
                    });
                    if (context.mounted) Navigator.pop(context);
                  },
                  icon: const Icon(Icons.add),
                  label: const Text('Crea ristorante in bozza')),
              ...activities.map((activity) => Card(
                  child: ListTile(
                      title: Text('${activity['name']}'),
                      subtitle: Text(
                          'Stato: ${activity['status']} · prenotazioni: ${activity['booking_enabled'] == true ? 'attive' : 'disattive'}')))),
            ]);
          },
        ),
      );
}

List<Map<String, dynamic>> _rows(Object? value) => value is List
    ? value
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList()
    : [];
