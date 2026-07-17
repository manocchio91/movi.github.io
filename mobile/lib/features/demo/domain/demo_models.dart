enum EventStatus {
  draft,
  pendingReview,
  published,
  soldOut,
  cancelled,
  completed
}

class LocalEvent {
  const LocalEvent({
    required this.id,
    required this.title,
    required this.organizer,
    required this.category,
    required this.town,
    required this.province,
    required this.dateLabel,
    required this.timeLabel,
    required this.price,
    required this.capacity,
    required this.availableSeats,
    required this.rating,
    required this.imageTone,
    required this.shortDescription,
    required this.fullDescription,
    required this.program,
    required this.included,
    required this.notIncluded,
    required this.ageRange,
    required this.importantInfo,
    required this.cancellationPolicy,
    required this.isKidsFriendly,
    required this.status,
    this.fillRate = 0,
    this.recommendRate = 0,
    this.hasNewEdition = false,
  });

  final String id;
  final String title;
  final String organizer;
  final String category;
  final String town;
  final String province;
  final String dateLabel;
  final String timeLabel;
  final double price;
  final int capacity;
  final int availableSeats;
  final double rating;
  final int imageTone;
  final String shortDescription;
  final String fullDescription;
  final List<String> program;
  final List<String> included;
  final List<String> notIncluded;
  final String ageRange;
  final String importantInfo;
  final String cancellationPolicy;
  final bool isKidsFriendly;
  final EventStatus status;
  final int fillRate;
  final int recommendRate;
  final bool hasNewEdition;
}

class OrganizerProfile {
  const OrganizerProfile({
    required this.name,
    required this.town,
    required this.followers,
    required this.rating,
    required this.description,
  });

  final String name;
  final String town;
  final int followers;
  final double rating;
  final String description;
}

class Booking {
  const Booking({
    required this.code,
    required this.event,
    required this.status,
    required this.people,
    required this.total,
    required this.canCancel,
    required this.canReview,
  });

  final String code;
  final LocalEvent event;
  final String status;
  final int people;
  final double total;
  final bool canCancel;
  final bool canReview;
}

class Review {
  const Review({
    required this.author,
    required this.eventTitle,
    required this.rating,
    required this.comment,
    required this.recommends,
  });

  final String author;
  final String eventTitle;
  final double rating;
  final String comment;
  final bool recommends;
}

class OpportunityIdea {
  const OpportunityIdea({
    required this.title,
    required this.period,
    required this.suggestedDate,
    required this.target,
    required this.reason,
    required this.priceRange,
  });

  final String title;
  final String period;
  final String suggestedDate;
  final String target;
  final String reason;
  final String priceRange;
}
