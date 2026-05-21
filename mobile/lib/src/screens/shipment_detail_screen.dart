import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shipflow_client/l10n/app_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/shipment_detail.dart';
import '../models/shipment_piece.dart';
import '../state/shipment_detail_provider.dart';
import '../widgets/tracking_timeline_section.dart';

class ShipmentDetailScreen extends ConsumerWidget {
  const ShipmentDetailScreen({super.key, required this.mode, required this.id});
  final String mode;
  final int    id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l     = AppLocalizations.of(context)!;
    final key   = ShipmentDetailKey(mode, id);
    final state = ref.watch(shipmentDetailProvider(key));

    return Scaffold(
      appBar: AppBar(
        title: Text(l.shipmentTitle),
        actions: <Widget>[
          IconButton(
            tooltip: l.shipmentRefresh,
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
    final l           = AppLocalizations.of(context)!;
    final modeLabel   = detail.mode == 'sea' ? l.filterSea : l.filterAir;
    final bucketLabel = detail.bucket == 'received' ? l.shipmentReceived : l.shipmentInTransit;

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
                _kv(l.shipmentTransaction, detail.transactionNumber ?? '—'),
                _kv(l.shipmentPieces,      detail.pieces_?.toString() ?? '—'),
                _kv(l.shipmentKg,          detail.kg?.toStringAsFixed(2) ?? '—'),
                _kv(l.shipmentCbm,         detail.cbm?.toStringAsFixed(3) ?? '—'),
                if (detail.category != null) _kv(l.shipmentCategory, detail.category!),
                if (detail.shipFrom != null) _kv(l.shipmentOrigin,   detail.shipFrom!),
                if (detail.createdDate != null) _kv(l.shipmentDate,  detail.createdDate!),
                if (detail.paymentPending)
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Chip(
                      label: Text(l.shipmentPaymentPending),
                      backgroundColor: Colors.orange.shade100,
                    ),
                  ),
              ],
            ),
          ),
        ),
        if (detail.tracking != null) ...<Widget>[
          const SizedBox(height: 16),
          TrackingTimelineSection(timeline: detail.tracking!),
        ],
        const SizedBox(height: 16),
        if (detail.pieces.isNotEmpty) ...<Widget>[
          Text(
            l.shipmentTrackingCodes(detail.pieces.length),
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
    final l = AppLocalizations.of(context)!;
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
        subtitle: Text(
          '${l.shipmentPieceCounter(piece.pieceIndex, piece.pieceTotal)}'
          '${piece.status != null ? " · ${piece.status}" : ""}',
        ),
        trailing: IconButton(
          tooltip: l.shipmentCopy,
          icon: const Icon(Icons.copy_outlined),
          onPressed: () async {
            await Clipboard.setData(ClipboardData(text: piece.trackingCode));
            if (context.mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text(l.shipmentCopied(piece.trackingCode))),
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
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 32),
      child: Column(
        children: <Widget>[
          const Icon(Icons.qr_code_2, size: 48, color: Colors.black26),
          const SizedBox(height: 8),
          Text(l.shipmentNoPieces),
        ],
      ),
    );
  }
}
