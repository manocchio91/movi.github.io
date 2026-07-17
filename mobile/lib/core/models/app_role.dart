enum AppRole {
  customer,
  organizer,
}

extension AppRoleLabel on AppRole {
  static AppRole? fromApi(String? value) => switch (value) {
        'customer' => AppRole.customer,
        'organizer' => AppRole.organizer,
        _ => null,
      };

  String get label => switch (this) {
        AppRole.customer => 'Customer',
        AppRole.organizer => 'Organizer',
      };
}
