import 'package:flutter/material.dart';
import 'package:shipflow_client/l10n/app_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../state/notifications_provider.dart';
import '../widgets/notification_row.dart';

class NotificationsScreen extends ConsumerStatefulWidget {
  const NotificationsScreen({super.key});

  @override
  ConsumerState<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends ConsumerState<NotificationsScreen> {
  final _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.position.pixels >= _scroll.position.maxScrollExtent - 200) {
        ref.read(notificationsProvider.notifier).loadMore();
      }
    });
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final state = ref.watch(notificationsProvider);
    final unread = state.valueOrNull?.unreadCount ?? 0;

    return Column(
      children: <Widget>[
        if (unread > 0)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: <Widget>[
                Text(l.unreadCount(unread)),
                TextButton(
                  onPressed: () => ref.read(notificationsProvider.notifier).markAllRead(),
                  child: Text(l.markAllRead),
                ),
              ],
            ),
          ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () => ref.read(notificationsProvider.notifier).refresh(),
            child: state.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => Center(child: Text('$e')),
              data: (s) {
                if (s.items.isEmpty) {
                  return Center(child: Text(l.noNotificationsYet));
                }
                return ListView.separated(
                  controller: _scroll,
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  itemCount: s.items.length + (s.page.hasMore ? 1 : 0),
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (_, i) {
                    if (i >= s.items.length) {
                      return const Padding(
                        padding: EdgeInsets.all(16),
                        child: Center(child: CircularProgressIndicator()),
                      );
                    }
                    final item = s.items[i];
                    return NotificationRow(
                      item: item,
                      onTap: () => ref.read(notificationsProvider.notifier).markRead(item.id),
                    );
                  },
                );
              },
            ),
          ),
        ),
      ],
    );
  }
}
