import 'dart:math';

import '../../../core/auth/authentication_repository.dart';
import '../../../core/network/api_client.dart';
import 'booking_models.dart';

class BookingRepository {
  BookingRepository(this._client, this._auth);
  final ApiClient _client;
  final AuthenticationRepository _auth;

  String newIdempotencyKey() {
    final random = Random.secure();
    return List<int>.generate(32, (_) => random.nextInt(256))
        .map((byte) => byte.toRadixString(16).padLeft(2, '0'))
        .join();
  }

  Future<CustomerBooking> createBooking(
    String eventUlid,
    CreateBookingRequest request,
    String idempotencyKey,
  ) async {
    final token = await _requiredToken();
    final response = await _client.post(
      '/events/$eventUlid/bookings',
      token: token,
      headers: {'Idempotency-Key': idempotencyKey},
      body: request.toJson(),
    );
    _ensureSuccess(response, expected: 201);
    return CustomerBooking.fromJson(_data(response));
  }

  Future<CustomerBooking> getBooking(String bookingUlid) async {
    final response = await _client.get(
      '/me/bookings/$bookingUlid',
      token: await _requiredToken(),
    );
    _ensureSuccess(response);
    return CustomerBooking.fromJson(_data(response));
  }

  Future<List<CustomerBooking>> getBookings() async {
    final response = await _client.get(
      '/me/bookings',
      token: await _requiredToken(),
    );
    _ensureSuccess(response);
    final data = response.body['data'];
    if (data is! List) {
      throw const BookingApiException('Lista prenotazioni non valida.', 500);
    }
    return data
        .whereType<Map<String, dynamic>>()
        .map(CustomerBooking.fromJson)
        .toList(growable: false);
  }

  Future<BookingQr> getBookingQr(String bookingUlid) async {
    final response = await _client.get(
      '/me/bookings/$bookingUlid/qr',
      token: await _requiredToken(),
    );
    _ensureSuccess(response);
    return BookingQr.fromJson(_data(response));
  }

  Future<CustomerBooking> cancelBooking(String bookingUlid) async {
    final response = await _client.post(
      '/me/bookings/$bookingUlid/cancel',
      token: await _requiredToken(),
    );
    _ensureSuccess(response);
    return CustomerBooking.fromJson(_data(response));
  }

  Future<String> _requiredToken() async {
    final token = await _auth.accessToken();
    if (token == null) {
      throw const BookingApiException(
          'Sessione scaduta. Accedi di nuovo.', 401);
    }
    return token;
  }

  Map<String, dynamic> _data(ApiResponse response) {
    final data = response.body['data'];
    if (data is! Map<String, dynamic>) {
      throw const BookingApiException('Risposta prenotazione non valida.', 500);
    }
    return data;
  }

  void _ensureSuccess(ApiResponse response, {int? expected}) {
    final success = expected == null
        ? response.statusCode >= 200 && response.statusCode < 300
        : response.statusCode == expected;
    if (success) return;
    final rawErrors = response.body['errors'];
    final errors = <String, List<String>>{};
    if (rawErrors is Map) {
      for (final entry in rawErrors.entries) {
        final value = entry.value;
        errors['${entry.key}'] =
            value is List ? value.map((item) => '$item').toList() : ['$value'];
      }
    }
    throw BookingApiException(
      response.body['message'] as String? ?? _fallback(response.statusCode),
      response.statusCode,
      errors,
    );
  }

  String _fallback(int status) => switch (status) {
        401 => 'Sessione scaduta. Accedi di nuovo.',
        403 => 'Non puoi accedere a questa prenotazione.',
        409 => 'Il QR non è disponibile per questa prenotazione.',
        422 => 'Controlla i dati e la disponibilità dei biglietti.',
        _ => 'Impossibile completare la prenotazione.',
      };
}
