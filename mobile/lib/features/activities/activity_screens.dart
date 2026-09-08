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
            ]));
      });
}

class ActivityReviewDialog extends StatefulWidget {
  const ActivityReviewDialog({super.key, required this.activityId, required this.reservationId, required this.repository});
  final String activityId;
  final String reservationId;
  final ActivityRepository repository;
  @override State<ActivityReviewDialog> createState() => _ActivityReviewDialogState();
}
class _ActivityReviewDialogState extends State<ActivityReviewDialog> {
  final comment = TextEditingController(); int rating = 5; bool loading = false; String? error;
  @override void dispose() { comment.dispose(); super.dispose(); }
  @override Widget build(BuildContext context) => AlertDialog(title: const Text('Recensione'), content: Column(mainAxisSize: MainAxisSize.min, children: [DropdownButtonFormField<int>(initialValue: rating, decoration: const InputDecoration(labelText: 'Valutazione'), items: [1,2,3,4,5].map((value) => DropdownMenuItem(value: value, child: Text('$value stelle'))).toList(), onChanged: (value) => rating = value ?? 5), TextField(controller: comment, maxLength: 2000, decoration: const InputDecoration(labelText: 'Testo')), if (error != null) Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error))]), actions: [TextButton(onPressed: loading ? null : () => Navigator.pop(context), child: const Text('Chiudi')), FilledButton(onPressed: loading ? null : () async { if (comment.text.trim().isEmpty) { setState(() => error = 'Inserisci un testo.'); return; } setState(() { loading = true; error = null; }); try { await widget.repository.review(widget.activityId, widget.reservationId, rating, comment.text.trim()); if (!context.mounted) return; Navigator.pop(context); ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Recensione inviata'))); } catch (e) { if (mounted) setState(() { loading = false; error = '$e'; }); } }, child: Text(loading ? 'Invio…' : 'Invia'))]);
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
                      trailing: reservation.reviewEligible ? FilledButton(onPressed: () => showDialog<void>(context: context, builder: (_) => ActivityReviewDialog(activityId: reservation.activityId, reservationId: reservation.id, repository: ref.read(activityRepositoryProvider))), child: const Text('Recensisci')) : reservation.status == 'confirmed'
                          ? PopupMenuButton<String>(
                              onSelected: (action) async {
                                final repository =
                                    ref.read(activityRepositoryProvider);
                                if (action == 'cancel') {
                                  await repository.cancel(reservation.id);
                                  ref.invalidate(reservationsProvider);
                                  return;
                                }
                                if (!context.mounted) return;
                                await showDialog<void>(context: context, builder: (_) => PreorderDialog(reservation: reservation, repository: repository));
                              },
                              itemBuilder: (_) => [
                                    PopupMenuItem(
                                        enabled: reservation.preorderEnabled,
                                        value: 'preorder',
                                        child: const Text('SCEGLI DAL MENU')),
                                    const PopupMenuItem(
                                        value: 'cancel',
                                        child: Text('Cancella'))
                                  ])
                          : null))
                  .toList())));
}

class PreorderDialog extends StatefulWidget {
  const PreorderDialog({super.key, required this.reservation, required this.repository});
  final ReservationModel reservation;
  final ActivityRepository repository;
  @override
  State<PreorderDialog> createState() => _PreorderDialogState();
}

class _PreorderDialogState extends State<PreorderDialog> {
  MenuModel? menu;
  final quantities = <String, int>{};
  bool loading = true;
  String? error;
  late String deadline;

  @override
  void initState() { super.initState(); deadline = DateTime.parse(widget.reservation.startsAt).subtract(Duration(minutes: widget.reservation.preorderDeadlineMinutes)).toLocal().toString(); _load(); }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      final values = await Future.wait([widget.repository.menu(widget.reservation.activityId), widget.repository.preorderDetails(widget.reservation.id)]);
      menu = values[0] as MenuModel?;
      final existing = values[1] as Map<String, dynamic>?;
      deadline = existing?['deadline']?.toString() ?? deadline;
      final existingData = existing?['data'];
      for (final row in _rows(existingData is Map<String, dynamic> ? existingData['items'] : null)) { final id = '${row['menu_item_ulid'] ?? ''}'; if (id.isNotEmpty) quantities[id] = (row['quantity'] as num).toInt(); }
    } catch (e) { error = '$e'; }
    if (mounted) setState(() => loading = false);
  }

  int get total => menu?.sections.expand((s) => s.items).fold<int>(0, (sum, item) => sum + (item.priceCents ?? 0) * (quantities[item.id] ?? 0)) ?? 0;
  void change(MenuItemModel item, int delta) => setState(() => quantities[item.id] = ((quantities[item.id] ?? 0) + delta).clamp(0, 50).toInt());

  Future<void> _submit() async {
    if (!quantities.values.any((value) => value > 0)) { setState(() => error = 'Seleziona almeno un piatto.'); return; }
    setState(() { loading = true; error = null; });
    try { final result = await widget.repository.preorder(widget.reservation.id, quantities); if (!mounted) return; Navigator.pop(context); ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Pre-order confermato · totale backend € ${((result['total_known_amount'] as num?)?.toInt() ?? 0) / 100}. Pagherai direttamente al locale.'))); }
    catch (e) { if (mounted) setState(() { loading = false; error = '$e'; }); }
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Scegli dal menu'),
    content: SizedBox(width: 520, child: loading ? const Center(child: CircularProgressIndicator()) : error != null && menu == null ? Column(mainAxisSize: MainAxisSize.min, children: [Text(error!), TextButton(onPressed: _load, child: const Text('Riprova'))]) : SingleChildScrollView(child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
      ...?menu?.sections.expand((section) => [Text(section.name, style: Theme.of(context).textTheme.titleMedium), ...section.items.map((item) => ListTile(enabled: item.available, title: Text(item.name), subtitle: Text(item.available ? (item.description.isEmpty ? 'Disponibile' : item.description) : 'Non disponibile'), leading: IconButton(icon: const Icon(Icons.remove), onPressed: item.available && (quantities[item.id] ?? 0) > 0 ? () => change(item, -1) : null), trailing: Row(mainAxisSize: MainAxisSize.min, children: [Text('${quantities[item.id] ?? 0}'), IconButton(icon: const Icon(Icons.add), onPressed: item.available ? () => change(item, 1) : null)])))]),
      const Divider(), Text('Modificabile fino al ${DateTime.tryParse(deadline)?.toLocal() ?? deadline}'), Text('Totale indicativo € ${(total / 100).toStringAsFixed(2)}'), const Text('Il totale definitivo è calcolato dal backend. Pagherai direttamente al locale.'), if (error != null) Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error))
    ]))),
    actions: [TextButton(onPressed: loading ? null : () => Navigator.pop(context), child: const Text('Chiudi')), TextButton(onPressed: loading ? null : () async { try { await widget.repository.cancelPreorder(widget.reservation.id); if (context.mounted) Navigator.pop(context); } catch (e) { if (mounted) setState(() => error = '$e'); } }, child: const Text('Cancella pre-order')), FilledButton(onPressed: loading ? null : _submit, child: const Text('Conferma'))]);
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
