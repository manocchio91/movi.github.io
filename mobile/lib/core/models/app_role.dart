enum AppRole {
  customer,
  organizer,
}

extension AppRoleLabel on AppRole {
  String get label => switch (this) {
        AppRole.customer => 'Customer',
        AppRole.organizer => 'Organizer',
      };
}
