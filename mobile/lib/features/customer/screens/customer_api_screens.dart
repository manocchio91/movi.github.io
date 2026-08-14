import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/auth/auth_controller.dart';
import '../../shared/ui/real_components.dart';
import '../data/customer_api_models.dart';
import '../data/customer_providers.dart';

class CustomerApiHomeScreen extends ConsumerStatefulWidget {
  const CustomerApiHomeScreen({super.key});

  @override
  ConsumerState<CustomerApiHomeScreen> createState() =>
      _CustomerApiHomeScreenState();
}

class _CustomerApiHomeScreenState extends ConsumerState<CustomerApiHomeScreen> {
  final _search = TextEditingController();

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _refresh() async {
    ref.invalidate(categoriesProvider);
    ref.invalidate(recommendedEventsProvider);
    ref.invalidate(successfulEventsProvider);
    await Future.wait([
      ref.read(categoriesProvider.future),
      ref.read(recommendedEventsProvider.future),
      ref.read(successfulEventsProvider.future),
    ]);
  }

  void _openSearch([String? value]) {
    ref.read(eventSearchQueryProvider.notifier).state =
        EventSearchQuery(text: value ?? _search.text);
    context.go('/customer/all-events');
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authControllerProvider).user;
    final categories = ref.watch(categoriesProvider);
    final recommended = ref.watch(recommendedEventsProvider);
    final successful = ref.watch(successfulEventsProvider);

    return Scaffold(
      appBar: AppBar(
        title: Row(children: [
          const MoviMark(),
          const SizedBox(width: 10),
          Expanded(
              child: Text(user == null ? 'MòVi' : 'Ciao ${user.name}',
                  overflow: TextOverflow.ellipsis)),
        ]),
        actions: const [
          IconButton(
              onPressed: null,
              icon: Icon(Icons.notifications_none_rounded),
              tooltip: 'Notifiche')
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
          children: [
            const PremiumHero(
              eyebrow: 'Scopri il Molise',
              title: 'Il prossimo ricordo inizia qui.',
              message:
                  'Esperienze selezionate, luoghi autentici e storie da vivere.',
              icon: Icons.explore_rounded,
            ),
            const SizedBox(height: 18),
            TextField(
              controller: _search,
              decoration: const InputDecoration(
                  hintText: 'Cerca eventi',
                  prefixIcon: Icon(Icons.search_rounded)),
              textInputAction: TextInputAction.search,
              onSubmitted: _openSearch,
            ),
            const SectionTitle('Macroaree'),
            categories.when(
              loading: () => const LoadingSkeleton(),
              error: (_, __) => _RetryState(
                  message: 'Impossibile caricare le categorie.',
                  onRetry: () => ref.invalidate(categoriesProvider)),
              data: (items) => items.isEmpty
                  ? const EmptyState(
                      title: 'Nessuna categoria',
                      message: 'Non ci sono categorie disponibili.',
                      icon: Icons.category_outlined)
                  : SizedBox(
                      height: 44,
                      child: ListView.separated(
                        scrollDirection: Axis.horizontal,
                        itemCount: items.length,
                        separatorBuilder: (_, __) => const SizedBox(width: 8),
                        itemBuilder: (_, index) => FilterChip(
                          selected: false,
                          label: Text(items[index].name),
                          avatar: const Icon(Icons.local_activity_rounded,
                              size: 18),
                          onSelected: (_) {
                            ref.read(eventSearchQueryProvider.notifier).state =
                                EventSearchQuery(categoryId: items[index].id);
                            context.go('/customer/all-events');
                          },
                        ),
                      ),
                    ),
            ),
            SectionTitle('Eventi consigliati',
                trailing: TextButton(
                    onPressed: _openSearch, child: const Text('Vedi tutti'))),
            recommended.when(
              loading: () => const LoadingSkeleton(),
              error: (error, _) => _RetryState(
                  message: _message(error),
                  onRetry: () => ref.invalidate(recommendedEventsProvider)),
              data: (page) => page.events.isEmpty
                  ? const EmptyState(
                      title: 'Nessun evento disponibile',
                      message: 'Riprova più tardi o aggiorna la pagina.',
                      icon: Icons.event_busy_rounded)
                  : Column(
                      children: page.events
                          .take(6)
                          .map((event) => Padding(
                              padding: const EdgeInsets.only(bottom: 12),
                              child: ApiEventCard(
                                  event: event,
                                  onTap: () => _openEvent(context, event))))
                          .toList()),
            ),
            SectionTitle('Eventi di Successo',
                trailing: TextButton(
                    onPressed: () => context.go('/customer/success'),
                    child: const Text('Vedi tutti'))),
            successful.when(
              loading: () => const LoadingSkeleton(),
              error: (error, _) => _RetryState(
                  message: _message(error),
                  onRetry: () => ref.invalidate(successfulEventsProvider)),
              data: (items) => items.isEmpty
                  ? const EmptyState(
                      title: 'Nessun evento di successo',
                      message:
                          'Non ci sono ancora esperienze concluse in evidenza.',
                      icon: Icons.workspace_premium_outlined)
                  : Column(
                      children: items
                          .take(2)
                          .map((item) => Padding(
                              padding: const EdgeInsets.only(bottom: 12),
                              child: ApiEventCard(
                                  event: item.event,
                                  badge: 'Evento di successo')))
                          .toList()),
            ),
          ],
        ),
      ),
    );
  }
}

class CustomerApiAllEventsScreen extends ConsumerStatefulWidget {
  const CustomerApiAllEventsScreen({super.key});

  @override
  ConsumerState<CustomerApiAllEventsScreen> createState() =>
      _CustomerApiAllEventsScreenState();
}

class _CustomerApiAllEventsScreenState
    extends ConsumerState<CustomerApiAllEventsScreen> {
  late final TextEditingController _search;
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _search =
        TextEditingController(text: ref.read(eventSearchQueryProvider).text);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  void _changed(String text) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () {
      final current = ref.read(eventSearchQueryProvider);
      ref.read(eventSearchQueryProvider.notifier).state =
          EventSearchQuery(text: text, categoryId: current.categoryId);
    });
  }

  @override
  Widget build(BuildContext context) {
    final result = ref.watch(eventListProvider);
    final categories =
        ref.watch(categoriesProvider).valueOrNull ?? const <EventCategory>[];
    final selected = ref.watch(eventSearchQueryProvider).categoryId;
    return ScreenScaffold(
      title: 'Tutti gli Eventi',
      children: [
        TextField(
            controller: _search,
            onChanged: _changed,
            decoration: const InputDecoration(
                hintText: 'Cerca in tutto il Molise',
                prefixIcon: Icon(Icons.search_rounded))),
        const SizedBox(height: 14),
        if (categories.isNotEmpty)
          SizedBox(
            height: 44,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: categories.length + 1,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (_, index) {
                final category = index == 0 ? null : categories[index - 1];
                return FilterChip(
                  selected: category?.id == selected ||
                      (category == null && selected == null),
                  label: Text(category?.name ?? 'Tutte'),
                  onSelected: (_) {
                    final current = ref.read(eventSearchQueryProvider);
                    ref.read(eventSearchQueryProvider.notifier).state =
                        EventSearchQuery(
                            text: current.text, categoryId: category?.id);
                  },
                );
              },
            ),
          ),
        const SectionTitle('Lista eventi'),
        result.when(
          loading: () => const LoadingSkeleton(),
          error: (error, _) => _RetryState(
              message: _message(error),
              onRetry: () => ref.invalidate(eventListProvider)),
          data: (page) => page.events.isEmpty
              ? const EmptyState(
                  title: 'Nessun evento trovato',
                  message: 'Modifica la ricerca o i filtri e riprova.',
                  icon: Icons.search_off_rounded)
              : Column(children: [
                  ...page.events.map((event) => Padding(
                      padding: const EdgeInsets.only(bottom: 14),
                      child: ApiEventCard(
                          event: event,
                          onTap: () => _openEvent(context, event)))),
                  if (page.hasMore)
                    OutlinedButton.icon(
                        onPressed: () =>
                            ref.read(eventListProvider.notifier).loadMore(),
                        icon: const Icon(Icons.expand_more_rounded),
                        label: const Text('Carica altri eventi')),
                ]),
        ),
      ],
    );
  }
}

class CustomerApiSuccessEventsScreen extends ConsumerWidget {
  const CustomerApiSuccessEventsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final result = ref.watch(successfulEventsProvider);
    return ScreenScaffold(title: 'Eventi di Successo', children: [
      result.when(
        loading: () => const LoadingSkeleton(),
        error: (error, _) => _RetryState(
            message: _message(error),
            onRetry: () => ref.invalidate(successfulEventsProvider)),
        data: (items) => items.isEmpty
            ? const EmptyState(
                title: 'Nessun evento di successo',
                message: 'Non ci sono eventi conclusi in evidenza.',
                icon: Icons.workspace_premium_outlined)
            : Column(
                children: items
                    .map((item) => Padding(
                          padding: const EdgeInsets.only(bottom: 14),
                          child: Column(children: [
                            ApiEventCard(
                                event: item.event, badge: 'Evento di successo'),
                            const SizedBox(height: 8),
                            Row(children: [
                              Expanded(
                                  child: MetricCard(
                                      label: 'Riempimento',
                                      value:
                                          '${item.fillRate.toStringAsFixed(0)}%',
                                      icon: Icons.groups_rounded)),
                              const SizedBox(width: 10),
                              Expanded(
                                  child: MetricCard(
                                      label: 'Lo consiglierebbe',
                                      value:
                                          '${item.recommendationRate.toStringAsFixed(0)}%',
                                      icon: Icons.thumb_up_rounded)),
                            ]),
                            if (item.nextEdition != null)
                              OutlinedButton(
                                  onPressed: () =>
                                      _openEvent(context, item.nextEdition!),
                                  child: const Text('Vedi nuova edizione')),
                          ]),
                        ))
                    .toList()),
      ),
    ]);
  }
}

class CustomerApiEventDetailScreen extends ConsumerWidget {
  const CustomerApiEventDetailScreen({super.key, required this.id});
  final String id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final result = ref.watch(eventDetailProvider(id));
    return Scaffold(
      appBar: AppBar(title: const Text('Dettaglio evento')),
      bottomNavigationBar: SafeArea(
          minimum: const EdgeInsets.all(16),
          child: FilledButton.icon(
            onPressed: result.valueOrNull?.availableSeats == 0
                ? null
                : () => context.push('/customer/events/$id/book'),
            icon: const Icon(Icons.confirmation_number_rounded),
            label: Text(result.valueOrNull?.availableSeats == 0
                ? 'Evento esaurito'
                : 'Prenota'),
          )),
      body: SafeArea(
          child: result.when(
        loading: () => const Padding(
            padding: EdgeInsets.all(20), child: LoadingSkeleton()),
        error: (error, _) => Padding(
            padding: const EdgeInsets.all(20),
            child: _RetryState(
                message: _message(error),
                onRetry: () => ref.invalidate(eventDetailProvider(id)))),
        data: (event) => ListView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
            children: [
              const NeutralEventImage(height: 240),
              const SizedBox(height: 18),
              Text(event.title,
                  style: Theme.of(context)
                      .textTheme
                      .headlineSmall
                      ?.copyWith(fontWeight: FontWeight.w900)),
              const SizedBox(height: 10),
              Wrap(spacing: 8, runSpacing: 8, children: [
                if (event.organizer.isNotEmpty)
                  InfoPill(
                      icon: Icons.storefront_rounded, text: event.organizer),
                if (event.category.isNotEmpty)
                  InfoPill(
                      icon: Icons.local_activity_rounded, text: event.category),
                InfoPill(
                    icon: Icons.calendar_month_rounded,
                    text: '${event.dateLabel} · ${event.timeLabel}'),
                if (event.place.isNotEmpty)
                  InfoPill(icon: Icons.place_rounded, text: event.place),
              ]),
              const SectionTitle('Descrizione'),
              if (event.summary.isNotEmpty) Text(event.summary),
              if (event.description.isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(event.description)
              ],
              if (event.program.isNotEmpty) ...[
                const SectionTitle('Programma'),
                ...event.program.map((item) => ListTile(
                    leading: const Icon(Icons.check_circle_outline_rounded),
                    title: Text(item)))
              ],
              if (event.included.isNotEmpty) ...[
                const SectionTitle('Incluso'),
                Wrap(
                    spacing: 8,
                    children: event.included
                        .map((item) => Chip(label: Text(item)))
                        .toList())
              ],
              const SectionTitle('Disponibilità'),
              Text(event.availableSeats > 0
                  ? '${event.availableSeats} posti disponibili su ${event.capacity}'
                  : 'Posti non disponibili'),
              if (event.importantInformation.isNotEmpty) ...[
                const SectionTitle('Informazioni utili'),
                Text(event.importantInformation)
              ],
              if (event.cancellationPolicy.isNotEmpty) ...[
                const SectionTitle('Politica di cancellazione'),
                Text(event.cancellationPolicy)
              ],
            ]),
      )),
    );
  }
}

class ApiEventCard extends StatelessWidget {
  const ApiEventCard({super.key, required this.event, this.onTap, this.badge});
  final ApiEvent event;
  final VoidCallback? onTap;
  final String? badge;

  @override
  Widget build(BuildContext context) => Card(
          child: InkWell(
        borderRadius: BorderRadius.circular(22),
        onTap: onTap,
        child: Padding(
            padding: const EdgeInsets.all(12),
            child:
                Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Stack(children: [
                const NeutralEventImage(),
                if (badge != null)
                  Positioned(
                      left: 10,
                      top: 10,
                      child: Chip(
                          label: Text(badge!),
                          avatar: const Icon(Icons.workspace_premium_rounded,
                              size: 16)))
              ]),
              const SizedBox(height: 12),
              Text(event.title.isEmpty ? 'Evento senza titolo' : event.title,
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium
                      ?.copyWith(fontWeight: FontWeight.w800)),
              if (event.organizer.isNotEmpty) ...[
                const SizedBox(height: 4),
                Text(event.organizer)
              ],
              const SizedBox(height: 10),
              Wrap(spacing: 8, runSpacing: 8, children: [
                InfoPill(
                    icon: Icons.calendar_month_rounded,
                    text: '${event.dateLabel} · ${event.timeLabel}'),
                if (event.place.isNotEmpty)
                  InfoPill(icon: Icons.place_rounded, text: event.place),
                if (event.category.isNotEmpty)
                  InfoPill(
                      icon: Icons.local_activity_rounded, text: event.category),
                if (event.rating != null)
                  InfoPill(
                      icon: Icons.star_rounded,
                      text: event.rating!.toStringAsFixed(1)),
              ]),
              const SizedBox(height: 10),
              Row(children: [
                Text(event.priceLabel,
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium
                        ?.copyWith(fontWeight: FontWeight.w900)),
                const Spacer(),
                if (event.availableSeats > 0)
                  Text('${event.availableSeats} posti')
              ]),
            ])),
      ));
}

class NeutralEventImage extends StatelessWidget {
  const NeutralEventImage({super.key, this.height = 168});
  final double height;
  @override
  Widget build(BuildContext context) => ClipRRect(
      borderRadius: BorderRadius.circular(22),
      child: Container(
          height: height,
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [Color(0xFF312E81), Color(0xFF7C3AED), Color(0xFFBE185D)],
            ),
          ),
          alignment: Alignment.center,
          child: Stack(alignment: Alignment.center, children: [
            Positioned(
                right: -18,
                top: -28,
                child: Icon(Icons.local_activity_rounded,
                    size: 140, color: Colors.white.withValues(alpha: .08))),
            const Icon(Icons.auto_awesome_rounded,
                size: 48, color: Colors.white),
          ])));
}

class _RetryState extends StatelessWidget {
  const _RetryState({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) => Column(children: [
        EmptyState(
            title: 'Caricamento non riuscito',
            message: message,
            icon: Icons.cloud_off_rounded),
        TextButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Riprova'))
      ]);
}

String _message(Object error) =>
    error.toString().replaceFirst('CustomerApiException: ', '');
void _openEvent(BuildContext context, ApiEvent event) =>
    context.push('/customer/events/${event.id}');
