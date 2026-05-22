import '../models/employee.dart';

/// Top-level auth state for the app. Watched by the router to drive
/// login → home redirects.
sealed class AuthState {
  const AuthState();
}

class AuthBooting extends AuthState {
  const AuthBooting();
}

class AuthSignedOut extends AuthState {
  const AuthSignedOut();
}

class AuthSignedIn extends AuthState {
  final String   token;
  final Employee employee;
  final int?     activeBranchId;

  const AuthSignedIn({
    required this.token,
    required this.employee,
    required this.activeBranchId,
  });

  AuthSignedIn withActiveBranch(int? branchId) => AuthSignedIn(
    token: token, employee: employee, activeBranchId: branchId,
  );
}
