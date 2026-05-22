import 'branch.dart';

/// Authenticated employee + the branches they can act at.
class Employee {
  final int           id;
  final String        name;
  final String        email;
  final List<BranchAssignment> assignments;
  final List<String>  abilities;    // ['employee', 'branch:1', ...]

  const Employee({
    required this.id,
    required this.name,
    required this.email,
    required this.assignments,
    required this.abilities,
  });

  /// Builds an Employee from the `/me` response shape.
  ///
  /// `/me` returns `{ user, branches: [{branch, role, is_active}, ...] }`,
  /// which is what we cache after login. `abilities` are NOT in /me — they
  /// come from the login response and are passed alongside.
  factory Employee.fromMe(Map<String, dynamic> me, List<String> abilities) {
    final user = (me['user'] as Map<String, dynamic>?) ?? const {};
    final raw  = (me['branches'] as List?) ?? const [];
    return Employee(
      id          : (user['id'] as num?)?.toInt() ?? 0,
      name        : (user['name']  as String?) ?? '',
      email       : (user['email'] as String?) ?? '',
      assignments : raw.whereType<Map<String, dynamic>>()
                       .map(BranchAssignment.fromJson)
                       .toList(),
      abilities   : abilities,
    );
  }

  bool canActAt(int branchId) => abilities.contains('branch:$branchId');
}

class BranchAssignment {
  final Branch  branch;
  final String  role;       // MANAGER / RECEIVER / COURIER / AUDITOR
  final bool    isActive;

  const BranchAssignment({
    required this.branch,
    required this.role,
    required this.isActive,
  });

  factory BranchAssignment.fromJson(Map<String, dynamic> json) => BranchAssignment(
    branch   : Branch.fromJson((json['branch'] as Map<String, dynamic>?) ?? const {}),
    role     : (json['role'] as String?) ?? 'RECEIVER',
    isActive : (json['is_active'] as bool?) ?? true,
  );
}
