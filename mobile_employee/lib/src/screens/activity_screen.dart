import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../l10n/app_localizations.dart';
import '../models/activity_log_row.dart';
import '../state/providers.dart';

class ActivityScreen extends ConsumerStatefulWidget {
  const ActivityScreen({super.key});
  @override
  ConsumerState<ActivityScreen> createState() => _ActivityScreenState();
}

class _ActivityScreenState extends ConsumerState<ActivityScreen> {
  late Future<List<ActivityLogRow>> _future;

  @override
  void initState() {
    super.initState();
    _future = ref.read(apiServiceProvider).activity();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    return Scaffold(
      appBar: AppBar(
        title: Text(l.activityTitle),
        actions: <Widget>[
          IconButton(
            tooltip: l.activityRefresh,
            icon: const Icon(Icons.refresh),
            onPressed: () => setState(() => _future = ref.read(apiServiceProvider).activity()),
          ),
        ],
      ),
      body: FutureBuilder<List<ActivityLogRow>>(
        future: _future,
        builder: (ctx, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            return Center(child: Padding(padding: const EdgeInsets.all(24), child: Text('${snap.error}')));
          }
          final rows = snap.data ?? const [];
          if (rows.isEmpty) return Center(child: Text(l.activityEmpty));
          final fmt = DateFormat('yyyy-MM-dd HH:mm');
          return ListView.separated(
            itemCount: rows.length,
            separatorBuilder: (_, __) => const Divider(height: 1),
            itemBuilder: (_, i) {
              final r = rows[i];
              return ListTile(
                leading: const Icon(Icons.history),
                title: Text(r.action),
                subtitle: Text(
                  '${r.entityType ?? ""} ${r.entityId ?? ""} · '
                  '${r.createdAt != null ? fmt.format(r.createdAt!) : ""}',
                ),
                trailing: r.branchId == null ? null : Text('branch ${r.branchId}'),
              );
            },
          );
        },
      ),
    );
  }
}
