import 'package:flutter_riverpod/flutter_riverpod.dart';

class EventDomainState {
  const EventDomainState({this.events = const {}, this.revision = 0});

  final Map<String, Map<String, dynamic>> events;
  final int revision;
}

class EventDomainStore extends Notifier<EventDomainState> {
  @override
  EventDomainState build() => const EventDomainState();

  void acceptUpdate(Map<String, dynamic> event) {
    final id = '${event['ulid'] ?? ''}';
    if (id.isEmpty) return;
    final metrics = {
      if (event['summary'] is Map) ..._map(event['summary']),
      ..._map(event['summary_metrics']),
    };
    final capacity =
        _integer(event['capacity_total'] ?? metrics['capacity_total']);
    final reportedAvailable = _integer(
      event['available_total'] ?? metrics['available_total'],
      fallback: capacity,
    );
    final booked = _integer(
      metrics['booked_people'],
      fallback: (capacity - reportedAvailable).clamp(0, capacity),
    );
    final available = (capacity - booked).clamp(0, capacity);
    final authoritative = <String, dynamic>{
      ...event,
      'capacity_total': capacity,
      'available_total': available,
      'summary_metrics': {
        ...metrics,
        'capacity_total': capacity,
        'booked_people': booked,
        'available_total': available,
      },
    };
    final previous = state.events[id];
    if (previous != null && _sameRevision(previous, authoritative)) return;
    state = EventDomainState(
      events: {...state.events, id: authoritative},
      revision: state.revision + 1,
    );
  }

  Map<String, dynamic> ingest(Map<String, dynamic> event) {
    acceptUpdate(event);
    return reconcile(event);
  }

  Map<String, dynamic> reconcile(Map<String, dynamic> event) {
    final authoritative = state.events['${event['ulid'] ?? ''}'];
    if (authoritative == null) return event;
    final metrics = _map(authoritative['summary_metrics']);
    final incomingMetrics = _map(event['summary_metrics']);
    final reconciled = <String, dynamic>{
      ...authoritative,
      ...event,
      'summary': event['summary'] is Map
          ? {...metrics, ..._map(event['summary'])}
          : event['summary'] ?? authoritative['summary'],
      'summary_metrics': {...metrics, ...incomingMetrics},
    };
    return reconciled;
  }
}

final eventDomainStoreProvider =
    NotifierProvider<EventDomainStore, EventDomainState>(EventDomainStore.new);

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

bool _sameRevision(
  Map<String, dynamic> previous,
  Map<String, dynamic> current,
) {
  final previousMetrics = _map(previous['summary_metrics']);
  final currentMetrics = _map(current['summary_metrics']);
  return previous['title'] == current['title'] &&
      previous['status'] == current['status'] &&
      previous['updated_at'] == current['updated_at'] &&
      previous['capacity_total'] == current['capacity_total'] &&
      previous['available_total'] == current['available_total'] &&
      previousMetrics['booked_people'] == currentMetrics['booked_people'];
}

int _integer(Object? value, {int fallback = 0}) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? fallback;
