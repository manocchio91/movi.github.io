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

int _number(dynamic value, int fallback) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? fallback;
