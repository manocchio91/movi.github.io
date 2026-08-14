import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/auth/auth_controller.dart';
import '../../core/auth/authentication_repository.dart';
import '../../core/network/api_client.dart';
import '../shared/state/event_data_revision.dart';

class RoleApiException implements Exception {
  const RoleApiException(this.message);
  final String message;
  @override
  String toString() => message;
}

class RoleApiRepository {
  RoleApiRepository(this._client, this._auth, this._events);
  final ApiClient _client;
  final AuthenticationRepository _auth;
  final EventDomainStore _events;

  Future<Map<String, dynamic>> get(String path) => _request('GET', path);
  Future<Map<String, dynamic>> post(String path,
          [Map<String, dynamic>? body]) =>
      _request('POST', path, body);
  Future<Map<String, dynamic>> patch(String path, Map<String, dynamic> body) =>
      _request('PATCH', path, body);

  Future<Map<String, dynamic>> _request(String method, String path,
      [Map<String, dynamic>? body]) async {
    final token = await _auth.accessToken();
    if (token == null) {
      throw const RoleApiException('Sessione scaduta. Accedi di nuovo.');
    }
    final response = switch (method) {
      'POST' => await _client.post(path, token: token, body: body),
      'PATCH' => await _client.patch(path, token: token, body: body),
      _ => await _client.get(path, token: token),
    };
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw RoleApiException(response.body['message'] as String? ??
          'Operazione non riuscita. Riprova.');
    }
    return response.body;
  }

  Future<Map<String, dynamic>> organizerDashboard() async =>
      _map((await get('/organizer/dashboard'))['data']);
  Future<List<Map<String, dynamic>>> organizerEvents(
          {String filter = 'all'}) async =>
      _list((await get('/organizer/events?filter=$filter&per_page=50'))['data'])
          .map((row) => _events.ingest(_map(row['event']).isEmpty
              ? row
              : {..._map(row['event']), 'summary': row['summary']}))
          .toList();
  Future<Map<String, dynamic>> organizerEvent(String id) async =>
      _events.ingest(_map((await get('/organizer/events/$id'))['data']));
  Future<List<Map<String, dynamic>>> organizerBookings(
          {String query = '', String filter = 'confirmed'}) async =>
      _list((await get(
              '/organizer/bookings?per_page=50&filter=$filter${query.trim().isEmpty ? '' : '&search=${Uri.encodeQueryComponent(query.trim())}'}'))[
          'data']);
  Future<Map<String, dynamic>> organizerBooking(String id) async =>
      _map((await get('/organizer/bookings/$id'))['data']);
  Future<Map<String, dynamic>> organizerProfile() async =>
      _map((await get('/organizer/profile'))['data']);
  Future<Map<String, dynamic>> participants(String eventId,
          {String query = ''}) async =>
      await get(
          '/organizer/events/$eventId/participants?per_page=50${query.trim().isEmpty ? '' : '&search=${Uri.encodeQueryComponent(query.trim())}'}');
  Future<void> checkIn(String eventId, String code) async => post(
      '/organizer/events/$eventId/check-ins', {'booking_code': code.trim()});

  Future<Map<String, dynamic>> adminDashboard() async =>
      _map((await get('/admin/dashboard'))['data']);
  Future<Map<String, dynamic>> adminSearch(String query) async => _map(
      (await get('/admin/search?q=${Uri.encodeQueryComponent(query.trim())}'))[
          'data']);
  Future<List<Map<String, dynamic>>> adminOrganizers(
      {String query = ''}) async {
    final body = await get(
        '/admin/organizers?per_page=50${query.trim().isEmpty ? '' : '&q=${Uri.encodeQueryComponent(query.trim())}'}');
    return _paginated(body['data']);
  }

  Future<Map<String, dynamic>> adminOrganizer(String id) async =>
      _map((await get('/admin/organizers/$id'))['data']);
  Future<List<Map<String, dynamic>>> adminCustomers({String query = ''}) async {
    final body = await get(
        '/admin/customers?per_page=50${query.trim().isEmpty ? '' : '&q=${Uri.encodeQueryComponent(query.trim())}'}');
    return _paginated(body['data']);
  }

  Future<Map<String, dynamic>> adminCustomer(String id) async =>
      _map((await get('/admin/customers/$id'))['data']);
  Future<List<Map<String, dynamic>>> adminEvents({String query = ''}) async {
    final body = await get(
        '/admin/events?per_page=50${query.trim().isEmpty ? '' : '&q=${Uri.encodeQueryComponent(query.trim())}'}');
    return _paginated(body['data']);
  }

  Future<Map<String, dynamic>> adminEvent(String id) async =>
      _map((await get('/admin/events/$id'))['data']);
  Future<List<Map<String, dynamic>>> adminPlans() async =>
      _list((await get('/admin/plans'))['data']);
  Future<void> organizerAction(String id, String action,
          {String? reason}) async =>
      post('/admin/organizers/$id/$action',
          reason == null ? null : {'reason': reason});
  Future<void> eventAction(String id, String action, {String? reason}) async =>
      post('/admin/events/$id/$action',
          reason == null ? null : {'reason': reason});
}

Map<String, dynamic> _map(Object? value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};
List<Map<String, dynamic>> _list(Object? value) => value is List
    ? value
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList()
    : <Map<String, dynamic>>[];
List<Map<String, dynamic>> _paginated(Object? value) {
  final map = _map(value);
  return map.containsKey('data') ? _list(map['data']) : _list(value);
}

final roleApiRepositoryProvider = Provider<RoleApiRepository>((ref) =>
    RoleApiRepository(
        ref.watch(apiClientProvider),
        ref.watch(authenticationRepositoryProvider),
        ref.read(eventDomainStoreProvider.notifier)));
