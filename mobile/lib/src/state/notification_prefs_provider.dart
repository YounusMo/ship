import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_service.dart';
import '../models/notification_prefs.dart';

/// Per-category mute toggles, backed by clients.notify_* on the server.
/// We optimistically update the local state on toggle so the switch
/// responds instantly; if the PATCH fails the next build() reconciles.
class NotificationPrefsNotifier extends AsyncNotifier<NotificationPrefs> {
  @override
  Future<NotificationPrefs> build() => apiService.notificationPrefs();

  Future<void> setTransactions(bool v) async {
    final current = state.valueOrNull;
    if (current == null) return;
    state = AsyncValue.data(current.copyWith(transactions: v));
    try {
      await apiService.updateNotificationPrefs(transactions: v);
    } catch (_) {
      state = AsyncValue.data(current); // revert
    }
  }

  Future<void> setShipments(bool v) async {
    final current = state.valueOrNull;
    if (current == null) return;
    state = AsyncValue.data(current.copyWith(shipments: v));
    try {
      await apiService.updateNotificationPrefs(shipments: v);
    } catch (_) {
      state = AsyncValue.data(current);
    }
  }

  Future<void> setReceipts(bool v) async {
    final current = state.valueOrNull;
    if (current == null) return;
    state = AsyncValue.data(current.copyWith(receipts: v));
    try {
      await apiService.updateNotificationPrefs(receipts: v);
    } catch (_) {
      state = AsyncValue.data(current);
    }
  }
}

final notificationPrefsProvider =
    AsyncNotifierProvider<NotificationPrefsNotifier, NotificationPrefs>(NotificationPrefsNotifier.new);
