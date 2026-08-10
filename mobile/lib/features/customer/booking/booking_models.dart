class BookingItemRequest {
  const BookingItemRequest(
      {required this.ticketTypeId, required this.quantity});
  final int ticketTypeId;
  final int quantity;
  Map<String, dynamic> toJson() => {
        'ticket_type_id': ticketTypeId,
        'quantity': quantity,
      };
}

class CreateBookingRequest {
  const CreateBookingRequest({
    required this.adultsCount,
    required this.childrenCount,
    required this.items,
    this.childrenAgeRanges = const [],
    this.dietaryNeeds,
    this.accessibilityNeeds,
    this.notes,
  });
  final int adultsCount;
  final int childrenCount;
  final List<BookingItemRequest> items;
  final List<String> childrenAgeRanges;
  final String? dietaryNeeds;
  final String? accessibilityNeeds;
  final String? notes;

  Map<String, dynamic> toJson() => {
        'adults_count': adultsCount,
        'children_count': childrenCount,
        if (childrenAgeRanges.isNotEmpty)
          'children_age_ranges': childrenAgeRanges,
        if (_present(dietaryNeeds)) 'dietary_needs': dietaryNeeds!.trim(),
        if (_present(accessibilityNeeds))
          'accessibility_needs': accessibilityNeeds!.trim(),
        if (_present(notes)) 'notes': notes!.trim(),
        'items': items.map((item) => item.toJson()).toList(),
      };
}

class BookingItem {
  const BookingItem({
    required this.quantity,
    required this.unitPriceCents,
    required this.totalPriceCents,
    required this.ticketName,
    this.ticketTypeUlid = '',
  });
  factory BookingItem.fromJson(Map<String, dynamic> json) {
    final ticket = _map(json['ticket_type']);
    return BookingItem(
      quantity: _int(json['quantity']),
      unitPriceCents: _int(json['unit_price_cents']),
      totalPriceCents: _int(json['total_price_cents']),
      ticketName: _string(ticket?['name']),
      ticketTypeUlid: _string(ticket?['ulid']),
    );
  }
  final int quantity;
  final int unitPriceCents;
  final int totalPriceCents;
  final String ticketName;
  final String ticketTypeUlid;
}

class CustomerBooking {
  const CustomerBooking({
    required this.ulid,
    required this.bookingCode,
    required this.status,
    required this.totalPeople,
    required this.totalPriceCents,
    required this.items,
    required this.eventTitle,
    this.eventStartsAt,
    this.eventEndsAt,
    this.qrToken,
    this.contactPhone = '',
    this.notes = '',
    this.adultsCount = 0,
    this.childrenCount = 0,
    this.canEditFromApi = false,
  });
  factory CustomerBooking.fromJson(Map<String, dynamic> json) {
    final event = _map(json['event']);
    return CustomerBooking(
      ulid: _string(json['ulid']),
      bookingCode: _string(json['booking_code']),
      status: _string(json['status']),
      totalPeople: _int(json['total_people']),
      totalPriceCents: _int(json['total_price_cents']),
      items: _maps(json['items']).map(BookingItem.fromJson).toList(),
      eventTitle: _string(event?['title']),
      eventStartsAt: DateTime.tryParse(_string(event?['starts_at']))?.toLocal(),
      eventEndsAt: DateTime.tryParse(_string(event?['ends_at']))?.toLocal(),
      qrToken: _nullableString(json['qr_token']),
      contactPhone: _string(json['contact_phone']),
      notes: _string(json['notes']),
      adultsCount: _int(json['adults_count']),
      childrenCount: _int(json['children_count']),
      canEditFromApi: json['can_edit'] == true,
    );
  }
  final String ulid;
  final String bookingCode;
  final String status;
  final int totalPeople;
  final int totalPriceCents;
  final List<BookingItem> items;
  final String eventTitle;
  final DateTime? eventStartsAt;
  final DateTime? eventEndsAt;
  final String? qrToken;
  final String contactPhone;
  final String notes;
  final int adultsCount;
  final int childrenCount;
  final bool canEditFromApi;

  bool get isCancelled => status.startsWith('cancelled');
  bool get isCompleted =>
      status == 'completed' ||
      status == 'checked_in' ||
      ((eventEndsAt ?? eventStartsAt)?.isBefore(DateTime.now()) ?? false);
  bool get canCancel => status == 'confirmed' && !isCompleted;
  bool get canEdit => canEditFromApi || canCancel;
}

class BookingQr {
  const BookingQr({required this.bookingUlid, required this.qrToken});
  factory BookingQr.fromJson(Map<String, dynamic> json) => BookingQr(
        bookingUlid: _string(json['booking_ulid']),
        qrToken: _string(json['qr_token']),
      );
  final String bookingUlid;
  final String qrToken;
}

class BookingApiException implements Exception {
  const BookingApiException(this.message, this.statusCode,
      [this.errors = const {}]);
  final String message;
  final int statusCode;
  final Map<String, List<String>> errors;
  @override
  String toString() => message;
}

bool _present(String? value) => value != null && value.trim().isNotEmpty;
Map<String, dynamic>? _map(dynamic value) =>
    value is Map<String, dynamic> ? value : null;
List<Map<String, dynamic>> _maps(dynamic value) => value is List
    ? value.whereType<Map<String, dynamic>>().toList(growable: false)
    : const [];
String _string(dynamic value) => value is String ? value : '';
String? _nullableString(dynamic value) =>
    value is String && value.isNotEmpty ? value : null;
int _int(dynamic value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
