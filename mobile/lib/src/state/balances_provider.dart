import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_service.dart';
import '../models/balance.dart';

/// Balance state for the dashboard. Re-fetched on pull-to-refresh and
/// after every push notification of category=transaction (see
/// push_service.dart).
class BalanceNotifier extends AsyncNotifier<Balance> {
  @override
  Future<Balance> build() async => apiService.balances();

  Future<void> refresh() async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() => apiService.balances());
  }
}

final balanceProvider = AsyncNotifierProvider<BalanceNotifier, Balance>(BalanceNotifier.new);
