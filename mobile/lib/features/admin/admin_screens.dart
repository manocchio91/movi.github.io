import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/auth/auth_controller.dart';
import '../role_data/role_api_repository.dart';
import '../shared/ui/real_components.dart';

class AdminDashboardScreen extends ConsumerWidget {
  const AdminDashboardScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) =>
      _AdminFuture<Map<String, dynamic>>(
        title: 'Dashboard Admin',
        future: ref.read(roleApiRepositoryProvider).adminDashboard(),
        builder: (data) => [
          const _AdminHero(
              title: 'Controllo piattaforma',
              message: 'Moderazione, utenti e performance operative.',
              icon: Icons.admin_panel_settings_rounded),
          const SectionTitle('KPI principali'),
          _AdminMetrics(data: data),
          const SectionTitle('Priorità'),
          _AdminLink(
              icon: Icons.storefront_rounded,
              title: 'Organizzatori',
              subtitle: 'Approva e verifica le attività',
              onTap: () => context.go('/admin/management')),
          const SizedBox(height: 12),
          _AdminLink(
              icon: Icons.manage_search_rounded,
              title: 'Ricerca globale',
              subtitle: 'Trova persone, organizer ed eventi',
              onTap: () => context.go('/admin/search')),
        ],
      );
}

class AdminSearchScreen extends ConsumerStatefulWidget {
  const AdminSearchScreen({super.key});
  @override
  ConsumerState<AdminSearchScreen> createState() => _AdminSearchScreenState();
}

class _AdminSearchScreenState extends ConsumerState<AdminSearchScreen> {
  final query = TextEditingController();
  Future<Map<String, dynamic>>? result;
  @override
  void dispose() {
    query.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) =>
      ScreenScaffold(title: 'Ricerca', children: [
        TextField(
            controller: query,
            textInputAction: TextInputAction.search,
            onSubmitted: (_) => _search(),
            decoration: InputDecoration(
                hintText: 'Nome, email o evento',
                prefixIcon: const Icon(Icons.search_rounded),
                suffixIcon: IconButton(
                    onPressed: _search,
                    icon: const Icon(Icons.arrow_forward_rounded)))),
        const SizedBox(height: 20),
        if (result == null)
          const EmptyState(
              title: 'Ricerca globale',
              message:
                  'Inserisci almeno due caratteri per cercare in tutta la piattaforma.',
              icon: Icons.manage_search_rounded)
        else
          FutureBuilder<Map<String, dynamic>>(
              future: result,
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done) {
                  return const LoadingSkeleton();
                }
                if (snapshot.hasError) {
                  return _error('${snapshot.error}', _search);
                }
                final data = snapshot.data ?? {};
                return Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      _SearchGroup(
                          title: 'Organizzatori',
                          items: _list(data['organizers']),
                          icon: Icons.storefront_rounded,
                          onTap: (id) => context.push('/admin/organizers/$id')),
                      _SearchGroup(
                          title: 'Clienti',
                          items: _list(data['customers']),
                          icon: Icons.people_rounded,
                          onTap: (id) => context.push('/admin/customers/$id')),
                      _SearchGroup(
                          title: 'Eventi',
                          items: _list(data['events']),
                          icon: Icons.local_activity_rounded,
                          onTap: (id) => context.push('/admin/events/$id')),
                    ]);
              }),
      ]);
  void _search() {
    if (query.text.trim().length < 2) return;
    setState(() =>
        result = ref.read(roleApiRepositoryProvider).adminSearch(query.text));
  }
}

class AdminManagementScreen extends ConsumerStatefulWidget {
  const AdminManagementScreen({super.key});
  @override
  ConsumerState<AdminManagementScreen> createState() =>
      _AdminManagementScreenState();
}

class _AdminManagementScreenState extends ConsumerState<AdminManagementScreen>
    with SingleTickerProviderStateMixin {
  late final tabs = TabController(length: 3, vsync: this);
  @override
  void dispose() {
    tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(
          title: const Text('Gestione'),
          bottom: TabBar(controller: tabs, tabs: const [
            Tab(text: 'Organizzatori'),
            Tab(text: 'Clienti'),
            Tab(text: 'Eventi')
          ])),
      body: TabBarView(controller: tabs, children: const [
        _AdminOrganizerList(),
        _AdminCustomerList(),
        _AdminEventList()
      ]));
}

class _AdminOrganizerList extends ConsumerWidget {
  const _AdminOrganizerList();
  @override
  Widget build(BuildContext context, WidgetRef ref) => _ListFuture(
      future: ref.read(roleApiRepositoryProvider).adminOrganizers(),
      empty: 'Nessun organizer',
      builder: (item) => _AdminEntity(
          icon: Icons.storefront_rounded,
          title: '${item['name'] ?? 'Organizzatore'}',
          subtitle: '${_map(item['owner'])['email'] ?? item['email'] ?? ''}',
          status: '${item['verification_status'] ?? ''}',
          onTap: () => context.push('/admin/organizers/${item['ulid']}')));
}

class _AdminCustomerList extends ConsumerWidget {
  const _AdminCustomerList();
  @override
  Widget build(BuildContext context, WidgetRef ref) => _ListFuture(
      future: ref.read(roleApiRepositoryProvider).adminCustomers(),
      empty: 'Nessun customer',
      builder: (item) => _AdminEntity(
          icon: Icons.person_rounded,
          title: '${item['name'] ?? 'Cliente'}',
          subtitle: '${item['email'] ?? ''}',
          status: '${item['status'] ?? ''}',
          onTap: () => context.push('/admin/customers/${item['ulid']}')));
}

class _AdminEventList extends ConsumerWidget {
  const _AdminEventList();
  @override
  Widget build(BuildContext context, WidgetRef ref) => _ListFuture(
      future: ref.read(roleApiRepositoryProvider).adminEvents(),
      empty: 'Nessun evento',
      builder: (item) => _AdminEntity(
          icon: Icons.local_activity_rounded,
          title: '${item['title'] ?? 'Evento'}',
          subtitle: '${_map(item['organizer_profile'])['name'] ?? ''}',
          status: '${item['status'] ?? ''}',
          onTap: () => context.push('/admin/events/${item['ulid']}')));
}

class AdminOrganizerDetailScreen extends ConsumerStatefulWidget {
  const AdminOrganizerDetailScreen({super.key, required this.id});
  final String id;
  @override
  ConsumerState<AdminOrganizerDetailScreen> createState() =>
      _AdminOrganizerDetailScreenState();
}

class _AdminOrganizerDetailScreenState
    extends ConsumerState<AdminOrganizerDetailScreen> {
  int refresh = 0;
  @override
  Widget build(BuildContext context) => _AdminFuture<Map<String, dynamic>>(
      key: ValueKey(refresh),
      title: 'Dettaglio Organizzatore',
      future: ref.read(roleApiRepositoryProvider).adminOrganizer(widget.id),
      builder: (item) => [
            _AdminHero(
                title: '${item['name'] ?? 'Organizzatore'}',
                message:
                    '${_map(item['owner'])['email'] ?? item['email'] ?? ''}',
                icon: Icons.storefront_rounded),
            const SizedBox(height: 16),
            StatusBadge(status: '${item['verification_status'] ?? ''}'),
            const SectionTitle('Informazioni'),
            _AdminInfo(
                label: 'Partita IVA',
                value: '${item['vat_number'] ?? 'Non disponibile'}'),
            _AdminInfo(
                label: 'Sede',
                value: '${item['address'] ?? ''} ${item['province'] ?? ''}'),
            _AdminInfo(
                label: 'Piano',
                value:
                    '${_map(_map(item['subscription'])['plan'])['name'] ?? 'Non assegnato'}'),
            const SectionTitle('Moderazione'),
            Wrap(spacing: 8, runSpacing: 8, children: _actions(item)),
          ]);
  List<Widget> _actions(Map<String, dynamic> item) {
    final status = '${item['verification_status']}';
    return [
      if (status == 'pending' ||
          status == 'changes_requested' ||
          status == 'rejected')
        FilledButton.icon(
            onPressed: () => _act('approve'),
            icon: const Icon(Icons.verified_rounded),
            label: const Text('Approva')),
      if (status == 'pending')
        OutlinedButton.icon(
            onPressed: () => _reason('request-changes'),
            icon: const Icon(Icons.edit_note_rounded),
            label: const Text('Richiedi integrazione')),
      if (status == 'pending')
        OutlinedButton.icon(
            onPressed: () => _reason('reject'),
            icon: const Icon(Icons.block_rounded),
            label: const Text('Rifiuta')),
      if (status == 'verified')
        OutlinedButton.icon(
            onPressed: () => _reason('suspend'),
            icon: const Icon(Icons.pause_circle_rounded),
            label: const Text('Sospendi')),
      if (status == 'suspended')
        FilledButton.icon(
            onPressed: () => _act('reactivate'),
            icon: const Icon(Icons.restart_alt_rounded),
            label: const Text('Riattiva')),
    ];
  }

  Future<void> _act(String action, {String? reason}) async {
    try {
      await ref
          .read(roleApiRepositoryProvider)
          .organizerAction(widget.id, action, reason: reason);
      if (mounted) {
        _snack(context, 'Operazione completata.');
        setState(() => refresh++);
      }
    } catch (e) {
      if (mounted) _snack(context, '$e', error: true);
    }
  }

  Future<void> _reason(String action) async {
    final value = await _reasonDialog(context);
    if (value != null && value.trim().isNotEmpty) {
      await _act(action, reason: value);
    }
  }
}

class AdminCustomerDetailScreen extends ConsumerWidget {
  const AdminCustomerDetailScreen({super.key, required this.id});
  final String id;
  @override
  Widget build(BuildContext context, WidgetRef ref) =>
      _AdminFuture<Map<String, dynamic>>(
          title: 'Dettaglio Cliente',
          future: ref.read(roleApiRepositoryProvider).adminCustomer(id),
          builder: (item) => [
                _AdminHero(
                    title: '${item['name'] ?? 'Cliente'}',
                    message: '${item['email'] ?? ''}',
                    icon: Icons.person_rounded),
                const SizedBox(height: 16),
                StatusBadge(status: '${item['status'] ?? ''}'),
                const SectionTitle('Attività'),
                _CountGrid(values: [
                  (
                    'Prenotazioni',
                    '${item['bookings_count'] ?? 0}',
                    Icons.confirmation_number_rounded
                  ),
                  (
                    'Preferiti',
                    '${item['favorites_count'] ?? 0}',
                    Icons.favorite_rounded
                  ),
                  (
                    'Recensioni',
                    '${item['reviews_count'] ?? 0}',
                    Icons.star_rounded
                  ),
                  (
                    'Organizzatori seguiti',
                    '${item['followed_organizers_count'] ?? 0}',
                    Icons.storefront_rounded
                  )
                ]),
                const SectionTitle('Prenotazioni recenti'),
                if (_list(item['recent_bookings']).isEmpty)
                  const EmptyState(
                      title: 'Nessuna prenotazione',
                      message: 'Il Cliente non ha prenotazioni recenti.',
                      icon: Icons.event_busy_rounded),
                for (final booking in _list(item['recent_bookings']))
                  _AdminInfo(
                      label: '${booking['booking_code'] ?? ''}',
                      value:
                          '${_map(booking['event'])['title'] ?? booking['status'] ?? ''}'),
              ]);
}

class AdminEventDetailScreen extends ConsumerStatefulWidget {
  const AdminEventDetailScreen({super.key, required this.id});
  final String id;
  @override
  ConsumerState<AdminEventDetailScreen> createState() =>
      _AdminEventDetailScreenState();
}

class _AdminEventDetailScreenState
    extends ConsumerState<AdminEventDetailScreen> {
  int refresh = 0;
  @override
  Widget build(BuildContext context) => _AdminFuture<Map<String, dynamic>>(
      key: ValueKey(refresh),
      title: 'Dettaglio Evento',
      future: ref.read(roleApiRepositoryProvider).adminEvent(widget.id),
      builder: (item) => [
            _AdminHero(
                title: '${item['title'] ?? 'Evento'}',
                message: '${_map(item['organizer_profile'])['name'] ?? ''}',
                icon: Icons.local_activity_rounded),
            const SizedBox(height: 16),
            StatusBadge(status: '${item['status'] ?? ''}'),
            const SectionTitle('Dati'),
            _AdminInfo(label: 'Data', value: _date(item['starts_at'])),
            _AdminInfo(
                label: 'Prenotazioni',
                value:
                    '${(item['bookings'] as List?)?.length ?? item['bookings_count'] ?? 0}'),
            _AdminInfo(
                label: 'Recensioni',
                value:
                    '${(item['reviews'] as List?)?.length ?? item['reviews_count'] ?? 0}'),
            const SectionTitle('Moderazione'),
            if ('${item['status']}' == 'published' ||
                '${item['status']}' == 'sold_out')
              OutlinedButton.icon(
                  onPressed: () => _reason('suspend'),
                  icon: const Icon(Icons.pause_circle_rounded),
                  label: const Text('Sospendi evento')),
            if ('${item['status']}' == 'suspended')
              FilledButton.icon(
                  onPressed: () => _act('reactivate'),
                  icon: const Icon(Icons.restart_alt_rounded),
                  label: const Text('Riattiva evento')),
          ]);
  Future<void> _act(String action, {String? reason}) async {
    try {
      await ref
          .read(roleApiRepositoryProvider)
          .eventAction(widget.id, action, reason: reason);
      if (mounted) {
        _snack(context, 'Evento aggiornato.');
        setState(() => refresh++);
      }
    } catch (e) {
      if (mounted) _snack(context, '$e', error: true);
    }
  }

  Future<void> _reason(String action) async {
    final value = await _reasonDialog(context);
    if (value != null) await _act(action, reason: value);
  }
}

class AdminPlansScreen extends ConsumerWidget {
  const AdminPlansScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) =>
      _AdminFuture<List<Map<String, dynamic>>>(
          title: 'Piani',
          future: ref.read(roleApiRepositoryProvider).adminPlans(),
          builder: (items) => [
                const _AdminHero(
                    title: 'Piani commerciali',
                    message:
                        'Offerta e condizioni principali per gli Organizzatori.',
                    icon: Icons.payments_rounded),
                const SectionTitle('Piani attivi'),
                if (items.isEmpty)
                  const EmptyState(
                      title: 'Nessun piano',
                      message: 'Non sono presenti piani commerciali.',
                      icon: Icons.payments_outlined),
                for (final item in items)
                  Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Card(
                          child: Padding(
                              padding: const EdgeInsets.all(18),
                              child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(children: [
                                      Expanded(
                                          child: Text(
                                              '${item['name'] ?? 'Piano'}',
                                              style: Theme.of(context)
                                                  .textTheme
                                                  .titleLarge)),
                                      StatusBadge(
                                          status: item['is_active'] == false
                                              ? 'inactive'
                                              : 'active')
                                    ]),
                                    const SizedBox(height: 8),
                                    Text(
                                        '${item['description'] ?? 'Piano commerciale'}'),
                                    const SizedBox(height: 12),
                                    Text(_planPrice(item),
                                        style: Theme.of(context)
                                            .textTheme
                                            .headlineSmall)
                                  ]))))
              ]);
}

class AdminProfileScreen extends ConsumerWidget {
  const AdminProfileScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider).user!;
    return ScreenScaffold(title: 'Profilo Admin', children: [
      _AdminHero(
          title: user.name,
          message: user.email,
          icon: Icons.admin_panel_settings_rounded),
      const SizedBox(height: 24),
      OutlinedButton.icon(
          onPressed: () => ref.read(authControllerProvider.notifier).logout(),
          icon: const Icon(Icons.logout_rounded),
          label: const Text('Esci dall’account'))
    ]);
  }
}

class _AdminFuture<T> extends StatefulWidget {
  const _AdminFuture(
      {super.key,
      required this.title,
      required this.future,
      required this.builder});
  final String title;
  final Future<T> future;
  final List<Widget> Function(T) builder;
  @override
  State<_AdminFuture<T>> createState() => _AdminFutureState<T>();
}

class _AdminFutureState<T> extends State<_AdminFuture<T>> {
  late Future<T> future = widget.future;
  @override
  Widget build(BuildContext context) =>
      ScreenScaffold(title: widget.title, children: [
        FutureBuilder<T>(
            future: future,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const LoadingSkeleton();
              }
              if (snapshot.hasError) {
                return _error('${snapshot.error}',
                    () => setState(() => future = widget.future));
              }
              return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: widget.builder(snapshot.data as T));
            })
      ]);
}

class _ListFuture extends StatelessWidget {
  const _ListFuture(
      {required this.future, required this.empty, required this.builder});
  final Future<List<Map<String, dynamic>>> future;
  final String empty;
  final Widget Function(Map<String, dynamic>) builder;
  @override
  Widget build(BuildContext context) =>
      FutureBuilder<List<Map<String, dynamic>>>(
          future: future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(
                  child: Padding(
                      padding: EdgeInsets.all(24),
                      child: CircularProgressIndicator()));
            }
            if (snapshot.hasError) {
              return Center(
                  child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Text('${snapshot.error}')));
            }
            final items = snapshot.data ?? [];
            if (items.isEmpty) {
              return Center(
                  child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: EmptyState(
                          title: empty,
                          message: 'Non ci sono elementi da mostrare.',
                          icon: Icons.inbox_rounded)));
            }
            return ListView.separated(
                padding: const EdgeInsets.all(16),
                itemCount: items.length,
                separatorBuilder: (_, __) => const SizedBox(height: 12),
                itemBuilder: (_, i) => builder(items[i]));
          });
}

class _AdminHero extends StatelessWidget {
  const _AdminHero(
      {required this.title, required this.message, required this.icon});
  final String title, message;
  final IconData icon;
  @override
  Widget build(BuildContext context) {
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
            borderRadius: BorderRadius.circular(24),
            boxShadow: [
              BoxShadow(
                  color: const Color(0xFF7C3AED).withValues(alpha: .22),
                  blurRadius: 24,
                  offset: const Offset(0, 10))
            ]),
        child: Row(children: [
          Expanded(
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                Text(title,
                    style: Theme.of(context)
                        .textTheme
                        .headlineSmall
                        ?.copyWith(color: Colors.white)),
                const SizedBox(height: 8),
                Text(message, style: const TextStyle(color: Color(0xFFEDE9FE)))
              ])),
          Icon(icon, size: 48, color: Colors.white)
        ]));
  }
}

class _AdminMetrics extends StatelessWidget {
  const _AdminMetrics({required this.data});
  final Map<String, dynamic> data;
  @override
  Widget build(BuildContext context) {
    final flattened = {
      ...data,
      ..._map(data['platform']),
      ..._map(data['organizers']),
      ..._map(data['events'])
    };
    return _CountGrid(values: [
      (
        'Utenti',
        '${flattened['users_total'] ?? flattened['customers_total'] ?? 0}',
        Icons.people_rounded
      ),
      (
        'Organizzatori',
        '${flattened['organizers_total'] ?? flattened['organizers_registered'] ?? 0}',
        Icons.storefront_rounded
      ),
      (
        'Eventi',
        '${flattened['events_total'] ?? 0}',
        Icons.local_activity_rounded
      ),
      (
        'Prenotazioni',
        '${flattened['bookings_total'] ?? 0}',
        Icons.confirmation_number_rounded
      ),
      (
        'Da approvare',
        '${flattened['pending_organizers'] ?? flattened['organizers_pending'] ?? 0}',
        Icons.pending_actions_rounded
      ),
      (
        'Sospesi',
        '${flattened['suspended_events'] ?? 0}',
        Icons.pause_circle_rounded
      )
    ]);
  }
}

class _CountGrid extends StatelessWidget {
  const _CountGrid({required this.values});
  final List<(String, String, IconData)> values;
  @override
  Widget build(BuildContext context) => LayoutBuilder(
      builder: (_, c) => GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: c.maxWidth > 540 ? 3 : 2,
              crossAxisSpacing: 12,
              mainAxisSpacing: 12,
              childAspectRatio: 1.35),
          itemCount: values.length,
          itemBuilder: (_, i) => MetricCard(
              label: values[i].$1, value: values[i].$2, icon: values[i].$3)));
}

class _AdminLink extends StatelessWidget {
  const _AdminLink(
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
          leading: CircleAvatar(child: Icon(icon)),
          title: Text(title),
          subtitle: Text(subtitle),
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: onTap));
}

class _AdminEntity extends StatelessWidget {
  const _AdminEntity(
      {required this.icon,
      required this.title,
      required this.subtitle,
      required this.status,
      required this.onTap});
  final IconData icon;
  final String title, subtitle, status;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => Card(
      child: ListTile(
          contentPadding: const EdgeInsets.all(16),
          leading: CircleAvatar(child: Icon(icon)),
          title: Text(title),
          subtitle:
              Text(subtitle, maxLines: 2, overflow: TextOverflow.ellipsis),
          trailing:
              Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            StatusBadge(status: status),
            const SizedBox(height: 4),
            const Icon(Icons.chevron_right_rounded, size: 18)
          ]),
          onTap: onTap));
}

class _AdminInfo extends StatelessWidget {
  const _AdminInfo({required this.label, required this.value});
  final String label, value;
  @override
  Widget build(BuildContext context) => Card(
      child: ListTile(
          title: Text(label),
          subtitle: Text(value.isEmpty ? 'Non disponibile' : value)));
}

class _SearchGroup extends StatelessWidget {
  const _SearchGroup(
      {required this.title,
      required this.items,
      required this.icon,
      required this.onTap});
  final String title;
  final List<Map<String, dynamic>> items;
  final IconData icon;
  final void Function(String) onTap;
  @override
  Widget build(BuildContext context) =>
      Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        SectionTitle(title),
        if (items.isEmpty)
          Text('Nessun risultato',
              style: Theme.of(context).textTheme.bodyMedium),
        for (final item in items)
          Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: _AdminEntity(
                  icon: icon,
                  title: '${item['name'] ?? item['title'] ?? 'Risultato'}',
                  subtitle: '${item['email'] ?? ''}',
                  status:
                      '${item['verification_status'] ?? item['status'] ?? ''}',
                  onTap: () => onTap('${item['ulid']}')))
      ]);
}

Widget _error(String message, VoidCallback retry) => Column(children: [
      EmptyState(
          title: 'Caricamento non riuscito',
          message: message,
          icon: Icons.cloud_off_rounded),
      const SizedBox(height: 12),
      FilledButton.icon(
          onPressed: retry,
          icon: const Icon(Icons.refresh_rounded),
          label: const Text('Riprova'))
    ]);
Map<String, dynamic> _map(Object? value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};
List<Map<String, dynamic>> _list(Object? value) => value is List
    ? value.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
    : [];
String _date(Object? value) {
  final d = DateTime.tryParse('$value')?.toLocal();
  return d == null
      ? 'Data non disponibile'
      : '${d.day.toString().padLeft(2, '0')}/${d.month.toString().padLeft(2, '0')}/${d.year} · ${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}';
}

String _planPrice(Map<String, dynamic> p) {
  if ('${p['billing_model']}' == 'unlimited') {
    return '€${((p['monthly_price_cents'] ?? 0) as num) / 100}/mese';
  }
  return '€${((p['booking_fee_cents'] ?? 0) as num) / 100} per prenotazione';
}

Future<String?> _reasonDialog(BuildContext context) async {
  final c = TextEditingController();
  final result = await showDialog<String>(
      context: context,
      builder: (d) => AlertDialog(
              title: const Text('Motivazione'),
              content: TextField(
                  controller: c,
                  maxLines: 3,
                  decoration: const InputDecoration(
                      labelText: 'Motivazione operativa')),
              actions: [
                TextButton(
                    onPressed: () => Navigator.pop(d),
                    child: const Text('Annulla')),
                FilledButton(
                    onPressed: () => Navigator.pop(d, c.text),
                    child: const Text('Conferma'))
              ]));
  c.dispose();
  return result;
}

void _snack(BuildContext context, String message, {bool error = false}) =>
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(message),
        backgroundColor: error ? Theme.of(context).colorScheme.error : null));
