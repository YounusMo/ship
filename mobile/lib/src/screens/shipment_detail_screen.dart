import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/shipment_detail.dart';
import '../models/shipment_piece.dart';
import '../state/shipment_detail_provider.dart';

class ShipmentDetailScreen extends ConsumerWidget {
  const ShipmentDetailScreen({super.key, required this.mode, required this.id});
  final String mode;
  final int    id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final key   = ShipmentDetailKey(mode, id);
    final state = ref.watch(shipmentDetailProvider(key));

    return Scaffold(
      appBar: AppBar(
        title: const Text('Shipment'),
        actions: <Widget>[
          IconButton(
            tooltip: 'Refresh',
            icon: const Icon(Icons.refresh),
            onPressed: () => ref.read(shipmentDetailProvider(key).notifier).refresh(),
          ),
        ],
      ),
      body: state.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Padding(padding: const EdgeInsets.all(24), child: Text('$e'))),
        data: (d) => _Body(detail: d),
      ),
    );
  }
}

class _Body extends StatelessWidget {
  const _Body({required this.detail});
  final ShipmentDetail detail;

  @override
  Widget build(BuildContext context) {
    final modeLabel   = detail.mode == 'sea' ? 'Sea' : 'Air';
    final bucketLabel = detail.bucket == 'received' ? 'Received at warehouse' : 'In transit';

    return ListView(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      children: <Widget>[
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    Icon(detail.mode == 'sea' ? Icons.directions_boat : Icons.flight),
                    const SizedBox(width: 8),
                    Text('$modeLabel  ·  $bucketLabel',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                _kv('Transaction', detail.transactionNumber ?? '—'),
                _kv('Pieces',      detail.pieces_?.toString() ?? '—'),
                _kv('KG',          detail.kg?.toStringAsFixed(2) ?? '—'),
                _kv('CBM',         detail.cbm?.toStringAsFixed(3) ?? '—'),
                if (detail.category != null) _kv('Category', detail.category!),
                if (detail.shipFrom != null) _kv('Origin',   detail.shipFrom!),
                if (detail.createdDate != null) _kv('Date',  detail.createdDate!),
                if (detail.paymentPending)
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Chip(
                      label: const Text('Payment pending'),
                      backgroundColor: Colors.orange.shade100,
                    ),
                  ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
        if (detail.pieces.isNotEmpty) ...<Widget>[
          Text(
            'Tracking codes (${detail.pieces.length})',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 8),
          for (final p in detail.pieces) _PieceTile(piece: p),
        ] else
          const _EmptyPieces(),
      ],
    );
  }

  Widget _kv(String k, String v) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        SizedBox(
          width: 96,
          child: Text(k, style: const TextStyle(color: Colors.black54)),
        ),
        Expanded(child: Text(v, style: const TextStyle(fontWeight: FontWeight.w500))),
      ],
    ),
  );
}

class _PieceTile extends StatelessWidget {
  const _PieceTile({required this.piece});
  final ShipmentPiece piece;

  @override
  Widget build(BuildContext context) {
    final isCanceled = (piece.status ?? '').toLowerCase() == 'canceled';
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: isCanceled ? Colors.red.shade50 : Colors.blue.shade50,
          child: Text('${piece.pieceIndex}', style: TextStyle(color: isCanceled ? Colors.red : Colors.blue)),
        ),
        title: Text(
          piece.trackingCode,
          style: TextStyle(
            fontFamily: 'monospace',
            decoration: isCanceled ? TextDecoration.lineThrough : null,
            fontWeight: FontWeight.w600,
          ),
        ),
        subtitle: Text('Piece ${piece.pieceIndex} of ${piece.pieceTotal}${piece.status != null ? " · ${piece.status}" : ""}'),
        trailing: IconButton(
          tooltip: 'Copy',
          icon: const Icon(Icons.copy_outlined),
          onPressed: () async {
            await Clipboard.setData(ClipboardData(text: piece.trackingCode));
            if (context.mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text('Copied ${piece.trackingCode}')),
              );
            }
          },
        ),
      ),
    );
  }
}

class _EmptyPieces extends StatelessWidget {
  const _EmptyPieces();
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 32),
    child: Column(
      children: const <Widget>[
        Icon(Icons.qr_code_2, size: 48, color: Colors.black26),
        SizedBox(height: 8),
        Text('No per-piece tracking codes yet for this shipment.'),
      ],
    ),
  );
}
