import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_service.dart';
import '../models/notification_item.dart';
import '../models/paginated.dart';

class NotificationsNotifier extends AsyncNotifier<NotificationsState> {
  @override
  Future<NotificationsState> build() async => _loadFirst();

  Future<NotificationsState> _loadFirst() async {
    final result = await apiService.notifications(page: 1);
    return NotificationsState(
      items       : result.page.data,
      page        : result.page,
      unreadCount : result.unreadCount,
    );
  }

  Future<void> loadMore() async {
    final current = state.valueOrNull;
    if (current == null || !current.page.hasMore) return;
    final next = await apiService.notifications(page: current.page.currentPage + 1);
    state = AsyncValue.data(current.copyWith(
      items: <NotificationItem>[...current.items, ...next.page.data],
      page : next.page,
    ));
  }

  Future<void> refresh() async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(_loadFirst);
  }

  /// Optimistic mark-read: flip the local row immediately, then call the
  /// server. If the server call fails the next refresh will reconcile.
  Future<void> markRead(String id) async {
    final current = state.valueOrNull;
    if (current == null) return;
    final now = DateTime.now();
    final updated = current.items.map((n) =>
      n.id == id && n.readAt == null
        ? NotificationItem(id: n.id, type: n.type, data: n.data, createdAt: n.createdAt, readAt: now)
        : n
    ).toList();
    state = AsyncValue.data(current.copyWith(
      items       : updated,
      unreadCount : (current.unreadCount - 1).clamp(0, 1 << 30),
    ));
    try { await apiService.markNotificationRead(id); } catch (_) {}
  }

  Future<void> markAllRead() async {
    final current = state.valueOrNull;
    if (current == null) return;
    final now = DateTime.now();
    state = AsyncValue.data(current.copyWith(
      items: current.items.map((n) =>
        n.readAt == null
          ? NotificationItem(id: n.id, type: n.type, data: n.data, createdAt: n.createdAt, readAt: now)
          : n
      ).toList(),
      unreadCount: 0,
    ));
    try { await apiService.markAllNotificationsRead(); } catch (_) {}
  }
}

class NotificationsState {
  final List<NotificationItem>      items;
  final Paginated<NotificationItem> page;
  final int                          unreadCount;
  const NotificationsState({required this.items, required this.page, required this.unreadCount});

  NotificationsState copyWith({
    List<NotificationItem>?      items,
    Paginated<NotificationItem>? page,
    int?                          unreadCount,
  }) => NotificationsState(
    items       : items       ?? this.items,
    page        : page        ?? this.page,
    unreadCount : unreadCount ?? this.unreadCount,
  );
}

final notificationsProvider =
    AsyncNotifierProvider<NotificationsNotifier, NotificationsState>(NotificationsNotifier.new);
