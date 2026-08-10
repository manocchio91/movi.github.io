enum AppRole { customer, organizer, admin }

extension AppRoleLabel on AppRole {
  static AppRole? fromApi(String? value) => switch (value) {
        'customer' || 'participant' => AppRole.customer,
        'organizer' => AppRole.organizer,
        'admin' => AppRole.admin,
        _ => null,
      };

  String get label => switch (this) {
        AppRole.customer => 'Customer',
        AppRole.organizer => 'Organizer',
        AppRole.admin => 'Admin',
      };

  String get homePath => switch (this) {
        AppRole.customer => '/customer',
        AppRole.organizer => '/organizer',
        AppRole.admin => '/admin',
      };
}
