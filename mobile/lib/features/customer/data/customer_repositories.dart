import '../../../core/auth/authentication_repository.dart';
import '../../../core/network/api_client.dart';
import '../../shared/state/event_data_revision.dart';
import 'customer_api_models.dart';

class CustomerApiException implements Exception {
  const CustomerApiException(this.message);
  final String message;

  @override
  String toString() => message;
}

class EventRepository {
  EventRepository(this._client, this._auth, this._events);
  final ApiClient _client;
  final AuthenticationRepository _auth;
  final EventDomainStore _events;

  Future<EventPage> events(
      {String? query, int? categoryId, int page = 1}) async {
    final params = <String, String>{'page': '$page'};
    if (query != null && query.trim().isNotEmpty) params['q'] = query.trim();
    if (categoryId != null) params['category_id'] = '$categoryId';
    final response = await _client
        .get(Uri(path: '/events', queryParameters: params).toString());
    _ensureSuccess(response);
    final data = response.body['data'];
    return EventPage(
      events: data is List
          ? data.whereType<Map<String, dynamic>>().map((event) {
              return ApiEvent.fromJson(_events.ingest(event));
            }).toList()
          : const [],
      currentPage: _number(response.body['current_page'], 1),
      lastPage: _number(response.body['last_page'], 1),
      total: _number(response.body['total'], 0),
    );
  }

  Future<ApiEvent> event(String id) async {
    final response = await _client.get('/events/$id');
    _ensureSuccess(response);
    final data = response.body['data'];
    if (data is! Map<String, dynamic>) {
      throw const CustomerApiException('Dettaglio evento non valido.');
    }
    return ApiEvent.fromJson(_events.ingest(data));
  }

  Future<Map<String, dynamic>?> waitlistStatus(String eventId) async {
    final response =
        await _client.get('/events/$eventId/waitlist', token: await _token());
    _ensureSuccess(response);
    return response.body['data'] is Map
        ? Map<String, dynamic>.from(response.body['data'] as Map)
        : null;
  }

  Future<Map<String, dynamic>> joinWaitlist(ApiEvent event) async {
    if (event.ticketTypes.isEmpty) {
      throw const CustomerApiException('Nessun biglietto disponibile.');
    }
    final response = await _client
        .post('/events/${event.id}/waitlist', token: await _token(), body: {
      'adults_count': 1,
      'children_count': 0,
      'items': [
        {'ticket_type_ulid': event.ticketTypes.first.id, 'quantity': 1}
      ],
    });
    _ensureSuccess(response);
    return Map<String, dynamic>.from(response.body['data'] as Map);
  }

  Future<Map<String, dynamic>?> leaveWaitlist(String eventId) async {
    final response = await _client.delete('/events/$eventId/waitlist',
        token: await _token());
    _ensureSuccess(response);
    return response.body['data'] is Map
        ? Map<String, dynamic>.from(response.body['data'] as Map)
        : null;
  }

  Future<Map<String, dynamic>> acceptWaitlist(
      String eventId, String phone) async {
    final response = await _client.post('/events/$eventId/waitlist/accept',
        token: await _token(), body: {'phone': phone});
    _ensureSuccess(response);
    return Map<String, dynamic>.from(response.body['data'] as Map);
  }

  Future<List<SuccessfulEvent>> successfulEvents() async {
    final token = await _auth.accessToken();
    if (token == null) {
      throw const CustomerApiException('Sessione scaduta. Accedi di nuovo.');
    }
    final response =
        await _client.get('/customer/successful-events', token: token);
    _ensureSuccess(response);
    final data = response.body['data'];
    return data is List
        ? data
            .whereType<Map<String, dynamic>>()
            .map(SuccessfulEvent.fromJson)
            .toList()
        : const [];
  }

  Future<String> _token() async {
    final token = await _auth.accessToken();
    if (token == null) {
      throw const CustomerApiException('Sessione scaduta. Accedi di nuovo.');
    }
    return token;
  }

  void _ensureSuccess(ApiResponse response) {
    if (response.statusCode >= 200 && response.statusCode < 300) return;
    throw CustomerApiException(
        response.body['message'] as String? ?? 'Impossibile caricare i dati.');
  }
}

class CategoryRepository {
  CategoryRepository(this._client);
  final ApiClient _client;

  Future<List<EventCategory>> categories() async {
    final response = await _client.get('/categories');
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw CustomerApiException(response.body['message'] as String? ??
          'Impossibile caricare le categorie.');
    }
    final data = response.body['data'];
    return data is List
        ? data
            .whereType<Map<String, dynamic>>()
            .map(EventCategory.fromJson)
            .toList()
        : const [];
  }
}

class FollowRepository {
  FollowRepository(this._client, this._auth);
  final ApiClient _client;
  final AuthenticationRepository _auth;

  Future<Map<String, dynamic>> organizer(String id) async {
    final token = await _token();
    final response = await _client.get('/organizers/$id', token: token);
    _ensure(response);
    return Map<String, dynamic>.from(response.body['data'] as Map);
  }

  Future<List<Map<String, dynamic>>> following() async {
    final response =
        await _client.get('/customer/following', token: await _token());
    _ensure(response);
    final data = response.body['data'];
    return data is List
        ? data
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList()
        : const [];
  }

  Future<bool> setFollowing(String id, bool value) async {
    final token = await _token();
    final response = value
        ? await _client.post('/customer/organizers/$id/follow', token: token)
        : await _client.delete('/customer/organizers/$id/follow', token: token);
    _ensure(response);
    return response.body['data'] is Map
        ? response.body['data']['is_following'] == true
        : value;
  }

  Future<String> _token() async {
    final token = await _auth.accessToken();
    if (token == null) {
      throw const CustomerApiException('Sessione scaduta. Accedi di nuovo.');
    }
    return token;
  }

  void _ensure(ApiResponse response) {
    if (response.statusCode >= 200 && response.statusCode < 300) return;
    throw CustomerApiException(
        response.body['message'] as String? ?? 'Operazione non riuscita.');
  }
}

int _number(dynamic value, int fallback) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? fallback;
