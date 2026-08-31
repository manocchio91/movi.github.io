import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/auth/auth_controller.dart';
import '../../shared/state/event_data_revision.dart';
import 'customer_api_models.dart';
import 'customer_repositories.dart';

final eventRepositoryProvider =
    Provider<EventRepository>((ref) => EventRepository(
          ref.watch(apiClientProvider),
          ref.watch(authenticationRepositoryProvider),
          ref.read(eventDomainStoreProvider.notifier),
        ));
final categoryRepositoryProvider = Provider<CategoryRepository>(
    (ref) => CategoryRepository(ref.watch(apiClientProvider)));
final followRepositoryProvider = Provider<FollowRepository>((ref) => FollowRepository(
    ref.watch(apiClientProvider), ref.watch(authenticationRepositoryProvider)));
final categoriesProvider = FutureProvider<List<EventCategory>>(
    (ref) => ref.watch(categoryRepositoryProvider).categories());
final recommendedEventsProvider = FutureProvider<EventPage>((ref) {
  ref.watch(eventDomainStoreProvider.select((state) => state.revision));
  return ref.watch(eventRepositoryProvider).events();
});
final successfulEventsProvider = FutureProvider<List<SuccessfulEvent>>((ref) {
  ref.watch(eventDomainStoreProvider.select((state) => state.revision));
  return ref.watch(eventRepositoryProvider).successfulEvents();
});
final eventDetailProvider = FutureProvider.family<ApiEvent, String>((ref, id) {
  ref.watch(eventDomainStoreProvider.select((state) => state.revision));
  return ref.watch(eventRepositoryProvider).event(id);
});

class EventSearchQuery {
  const EventSearchQuery({this.text = '', this.categoryId});
  final String text;
  final int? categoryId;
}

final eventSearchQueryProvider =
    StateProvider<EventSearchQuery>((ref) => const EventSearchQuery());

class EventListController extends AsyncNotifier<EventPage> {
  @override
  Future<EventPage> build() {
    ref.watch(eventDomainStoreProvider.select((state) => state.revision));
    final query = ref.watch(eventSearchQueryProvider);
    return ref
        .watch(eventRepositoryProvider)
        .events(query: query.text, categoryId: query.categoryId);
  }

  Future<void> loadMore() async {
    final current = state.valueOrNull;
    if (current == null || !current.hasMore || state.isLoading) return;
    state = const AsyncLoading<EventPage>().copyWithPrevious(state);
    state = await AsyncValue.guard(() async {
      final query = ref.read(eventSearchQueryProvider);
      final next = await ref.read(eventRepositoryProvider).events(
          query: query.text,
          categoryId: query.categoryId,
          page: current.currentPage + 1);
      return EventPage(
          events: [...current.events, ...next.events],
          currentPage: next.currentPage,
          lastPage: next.lastPage,
          total: next.total);
    });
  }
}

final eventListProvider = AsyncNotifierProvider<EventListController, EventPage>(
    EventListController.new);
