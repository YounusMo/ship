import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../l10n/app_localizations.dart';
import '../state/providers.dart';

class QueueScreen extends ConsumerStatefulWidget {
  const QueueScreen({super.key, required this.branchId});
  final int branchId;

  @override
  ConsumerState<QueueScreen> createState() => _QueueScreenState();
}

class _QueueScreenState extends ConsumerState<QueueScreen> {
  late Future<List<Map<String, dynamic>>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<Map<String, dynamic>>> _load() {
    return ref.read(apiServiceProvider).branchQueue(widget.branchId);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    return Scaffold(
      appBar: AppBar(
        title: Text(l.queueTitle),
        actions: <Widget>[
          IconButton(
            tooltip: l.queueRefresh,
            icon: const Icon(Icons.refresh),
            onPressed: () => setState(() => _future = _load()),
          ),
        ],
      ),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _future,
        builder: (ctx, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            return Center(child: Padding(padding: const EdgeInsets.all(24), child: Text('${snap.error}')));
          }
          final rows = snap.data ?? const [];
          if (rows.isEmpty) {
            return Center(child: Text(l.queueEmpty));
          }
          return ListView.separated(
            padding: const EdgeInsets.all(8),
            itemCount: rows.length,
            separatorBuilder: (_, _) => const Divider(height: 1),
            itemBuilder: (_, i) {
              final r = rows[i];
              return ListTile(
                leading: const Icon(Icons.inventory_2_outlined),
                title: Text('${r['shipment_source_table'] ?? '?'} #${r['shipment_source_id'] ?? '?'}'),
                subtitle: Text('${r['event_type'] ?? ''} · ${r['occurred_at'] ?? ''}'),
                trailing: r['shipment_piece_id'] == null
                    ? null
                    : Text('piece ${r['shipment_piece_id']}', style: const TextStyle(fontFamily: 'monospace')),
              );
            },
          );
        },
      ),
    );
  }
}
