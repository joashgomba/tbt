class AuthUser {
  const AuthUser({
    required this.id,
    required this.name,
    required this.email,
    this.phoneNumber,
    this.organizationName,
    this.country,
    this.preferredLanguage,
    this.permissions = const [],
  });

  final int id;
  final String name;
  final String email;
  final String? phoneNumber;
  final String? organizationName;
  final String? country;
  final String? preferredLanguage;
  final List<String> permissions;

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    return AuthUser(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      phoneNumber: json['phone_number'] as String?,
      organizationName: json['organization_name'] as String?,
      country: json['country'] as String?,
      preferredLanguage: json['preferred_language'] as String?,
      permissions: (json['permissions'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          const [],
    );
  }
}

class AuthSession {
  const AuthSession({required this.token, required this.user});

  final String token;
  final AuthUser user;
}
