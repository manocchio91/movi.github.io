import '../../core/auth/authentication_repository.dart';
import '../../core/network/api_client.dart';

class ActivityServiceModel {
  const ActivityServiceModel(this.id, this.name);
  final String id;
  final String name;
  factory ActivityServiceModel.fromJson(Map<String, dynamic> json) =>
      ActivityServiceModel('${json['ulid']}', '${json['name']}');
}

class ActivityModel {
  const ActivityModel(
      this.id, this.name, this.description, this.following, this.services);
  final String id;
  final String name;
  final String description;
  final bool following;
  final List<ActivityServiceModel> services;
  factory ActivityModel.fromJson(Map<String, dynamic> json) => ActivityModel(
      '${json['ulid']}',
      '${json['name']}',
      '${json['description'] ?? ''}',
      json['is_following'] == true,
      _list(json['services']).map(ActivityServiceModel.fromJson).toList());
}

class ReservationModel {
  const ReservationModel(this.id, this.activityId, this.activity, this.service,
      this.startsAt, this.partySize, this.status);
  final String id;
  final String activityId;
  final String activity;
  final String service;
  final String startsAt;
  final int partySize;
  final String status;
  factory ReservationModel.fromJson(Map<String, dynamic> json) =>
      ReservationModel(
          '${json['ulid']}',
          '${_map(json['activity'])['ulid'] ?? ''}',
          '${_map(json['activity'])['name'] ?? ''}',
          '${_map(json['service'])['name'] ?? ''}',
          '${json['starts_at']}',
          (json['party_size'] as num).toInt(),
          '${json['status']}');
}

class MenuItemModel {
  const MenuItemModel(this.id, this.name, this.description, this.priceCents,
      this.available, this.allergens);
  final String id;
  final String name;
  final String description;
  final int? priceCents;
  final bool available;
  final List<String> allergens;
  factory MenuItemModel.fromJson(Map<String, dynamic> json) => MenuItemModel(
      '${json['ulid']}',
      '${json['name']}',
      '${json['description'] ?? ''}',
      (json['price_cents'] as num?)?.toInt(),
      json['available'] == true,
      (json['allergens'] as List? ?? const [])
          .map((value) => '$value')
          .toList());
}

class MenuSectionModel {
  const MenuSectionModel(this.name, this.items);
  final String name;
  final List<MenuItemModel> items;
  factory MenuSectionModel.fromJson(Map<String, dynamic> json) =>
      MenuSectionModel('${json['name']}',
          _list(json['items']).map(MenuItemModel.fromJson).toList());
}

class MenuModel {
  const MenuModel(this.name, this.sections);
  final String name;
  final List<MenuSectionModel> sections;
  factory MenuModel.fromJson(Map<String, dynamic> json) => MenuModel(
      '${json['name']}',
      _list(json['sections']).map(MenuSectionModel.fromJson).toList());
}

class ActivityRepository {
  ActivityRepository(this._client, this._auth);
  final ApiClient _client;
  final AuthenticationRepository _auth;
  Future<String> _token() async =>
      await _auth.accessToken() ?? (throw Exception('Sessione scaduta.'));
  void _ok(ApiResponse response) {
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw Exception(response.body['message'] ?? 'Operazione non riuscita.');
    }
  }

  Future<List<ActivityModel>> activities() async {
    final response = await _client.get('/activities');
    _ok(response);
    return _list(response.body['data']).map(ActivityModel.fromJson).toList();
  }

  Future<ActivityModel> activity(String id) async {
    final response =
        await _client.get('/activities/$id', token: await _token());
    _ok(response);
    return ActivityModel.fromJson(_map(response.body['data']));
  }

  Future<MenuModel?> menu(String id) async {
    final response = await _client.get('/activities/$id/menu',
        token: await _auth.accessToken());
    if (response.statusCode == 404) return null;
    _ok(response);
    return MenuModel.fromJson(_map(response.body['data']));
  }

  Future<List<Map<String, dynamic>>> availability(
      String activity, String service, String date) async {
    final response = await _client
        .get('/activities/$activity/services/$service/availability?date=$date');
    _ok(response);
    return _list(response.body['data']);
  }

  Future<ReservationModel> reserve(String activity, String service,
      String startsAt, int party, String phone) async {
    final response = await _client.post('/activities/$activity/reservations',
        token: await _token(),
        headers: {
          'Idempotency-Key': '${DateTime.now().microsecondsSinceEpoch}-$service'
        },
        body: {
          'service_ulid': service,
          'starts_at': startsAt,
          'party_size': party,
          'contact_phone': phone
        });
    _ok(response);
    return ReservationModel.fromJson(_map(response.body['data']));
  }

  Future<List<ReservationModel>> reservations() async {
    final response =
        await _client.get('/me/reservations', token: await _token());
    _ok(response);
    return _list(response.body['data']).map(ReservationModel.fromJson).toList();
  }

  Future<void> cancel(String id) async {
    final response = await _client.post('/me/reservations/$id/cancel',
        token: await _token());
    _ok(response);
  }

  Future<void> preorder(
      String reservationId, String menuItemId, int quantity) async {
    final response = await _client.put(
        '/me/reservations/$reservationId/preorder',
        token: await _token(),
        body: {
          'items': [
            {'menu_item_ulid': menuItemId, 'quantity': quantity}
          ]
        });
    _ok(response);
  }

  Future<void> follow(String id, bool value) async {
    final token = await _token();
    final response = value
        ? await _client.post('/customer/activities/$id/follow', token: token)
        : await _client.delete('/customer/activities/$id/follow', token: token);
    _ok(response);
  }

  Future<void> review(String id, int rating, String comment) async {
    final response = await _client.post('/activities/$id/reviews',
        token: await _token(), body: {'rating': rating, 'comment': comment});
    _ok(response);
  }
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : {};
List<Map<String, dynamic>> _list(Object? value) => value is List
    ? value
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList()
    : [];
