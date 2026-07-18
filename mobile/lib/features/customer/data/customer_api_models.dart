class EventCategory {
  const EventCategory({required this.id, required this.name});

  factory EventCategory.fromJson(Map<String, dynamic> json) => EventCategory(
        id: _int(json['id']),
        name: _string(json['name']),
      );

  final int id;
  final String name;
}

class ApiEvent {
  const ApiEvent({
    required this.id,
    required this.title,
    required this.summary,
    required this.description,
    required this.organizer,
    required this.categories,
    required this.municipality,
    required this.province,
    required this.startsAt,
    required this.capacity,
    required this.availableSeats,
    required this.isFree,
    required this.minimumPriceCents,
    required this.program,
    required this.included,
    required this.notIncluded,
    required this.importantInformation,
    required this.cancellationPolicy,
    this.rating,
  });

  factory ApiEvent.fromJson(Map<String, dynamic> json) {
    final categories = _maps(json['categories'])
        .map(EventCategory.fromJson)
        .toList(growable: false);
    final tickets = _maps(json['ticket_types']);
    final prices = tickets
        .where((item) => item['is_active'] != false)
        .map((item) => _int(item['price_cents']))
        .toList();
    final municipality = _map(json['municipality']);
    final province = _map(municipality?['province']);
    final organizer = _map(json['organizer_profile']);

    return ApiEvent(
      id: _string(json['ulid']),
      title: _string(json['title']),
      summary: _string(json['summary']),
      description: _string(json['description']),
      organizer: _string(organizer?['name']),
      categories: categories,
      municipality: _string(municipality?['name']),
      province: _string(province?['name']),
      startsAt: DateTime.tryParse(_string(json['starts_at']))?.toLocal(),
      capacity: _int(json['capacity_total']),
      availableSeats: _int(json['available_total']),
      isFree: json['is_free'] == true,
      minimumPriceCents: prices.isEmpty
          ? null
          : prices
              .reduce((value, element) => value < element ? value : element),
      program: _strings(json['program']),
      included: _strings(json['included']),
      notIncluded: _strings(json['not_included']),
      importantInformation: _string(json['important_information']),
      cancellationPolicy: _string(json['cancellation_policy']),
      rating: _doubleOrNull(json['average_rating']),
    );
  }

  final String id;
  final String title;
  final String summary;
  final String description;
  final String organizer;
  final List<EventCategory> categories;
  final String municipality;
  final String province;
  final DateTime? startsAt;
  final int capacity;
  final int availableSeats;
  final bool isFree;
  final int? minimumPriceCents;
  final List<String> program;
  final List<String> included;
  final List<String> notIncluded;
  final String importantInformation;
  final String cancellationPolicy;
  final double? rating;

  String get category => categories.isEmpty ? '' : categories.first.name;
  String get place =>
      [municipality, province].where((v) => v.isNotEmpty).join(' (') +
      (municipality.isNotEmpty && province.isNotEmpty ? ')' : '');
  String get dateLabel => startsAt == null
      ? 'Data non disponibile'
      : '${startsAt!.day.toString().padLeft(2, '0')}/${startsAt!.month.toString().padLeft(2, '0')}/${startsAt!.year}';
  String get timeLabel => startsAt == null
      ? ''
      : '${startsAt!.hour.toString().padLeft(2, '0')}:${startsAt!.minute.toString().padLeft(2, '0')}';
  String get priceLabel {
    if (isFree) return 'Gratis';
    if (minimumPriceCents == null) return 'Prezzo non disponibile';
    return 'Da €${(minimumPriceCents! / 100).toStringAsFixed(2)}';
  }
}

class EventPage {
  const EventPage(
      {required this.events,
      required this.currentPage,
      required this.lastPage,
      required this.total});
  final List<ApiEvent> events;
  final int currentPage;
  final int lastPage;
  final int total;
  bool get hasMore => currentPage < lastPage;
}

class SuccessfulEvent {
  const SuccessfulEvent(
      {required this.event,
      required this.fillRate,
      required this.recommendationRate,
      required this.hasNextEdition,
      this.nextEdition});

  factory SuccessfulEvent.fromJson(Map<String, dynamic> json) {
    final next = _map(json['next_edition']);
    final eventJson = <String, dynamic>{
      ...json,
      'starts_at': json['held_at'],
      'summary': json['summary'],
      'categories': json['category'] == null
          ? <dynamic>[]
          : [
              {'id': 0, 'name': json['category']}
            ],
      'municipality': {
        'name': json['municipality'],
        'province': {'name': json['province']}
      },
      'organizer_profile': json['organizer'],
      'available_total': 0,
      'capacity_total': 0,
      'is_free': false,
    };
    return SuccessfulEvent(
      event: ApiEvent.fromJson(eventJson),
      fillRate: _doubleOrNull(json['fill_rate']) ?? 0,
      recommendationRate: _doubleOrNull(json['recommendation_rate']) ?? 0,
      hasNextEdition: json['has_next_edition'] == true,
      nextEdition: next == null
          ? null
          : ApiEvent.fromJson({...next, 'summary': '', 'description': ''}),
    );
  }

  final ApiEvent event;
  final double fillRate;
  final double recommendationRate;
  final bool hasNextEdition;
  final ApiEvent? nextEdition;
}

Map<String, dynamic>? _map(dynamic value) =>
    value is Map<String, dynamic> ? value : null;
List<Map<String, dynamic>> _maps(dynamic value) => value is List
    ? value.whereType<Map<String, dynamic>>().toList(growable: false)
    : const [];
String _string(dynamic value) => value is String ? value : '';
int _int(dynamic value) => value is int ? value : int.tryParse('$value') ?? 0;
double? _doubleOrNull(dynamic value) =>
    value is num ? value.toDouble() : double.tryParse('$value');
List<String> _strings(dynamic value) {
  if (value is List) return value.whereType<String>().toList(growable: false);
  if (value is String && value.trim().isNotEmpty) return [value];
  return const [];
}
