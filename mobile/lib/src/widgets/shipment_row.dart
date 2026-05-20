import 'package:flutter/material.dart';

import '../models/shipment.dart';

class ShipmentRow extends StatelessWidget {
  const ShipmentRow({super.key, required this.shipment});
  final Shipment shipment;

  @override
  Widget build(BuildContext context) {
    final s = shipment;
    final modeIcon = s.isSea ? Icons.directions_boat : Icons.flight;
    final modeLabel = s.isSea ? 'Sea' : 'Air';
    final bucketLabel = s.bucket == 'received' ? 'Received' : 'In transit';
    final bucketColor = s.bucket == 'received' ? Colors.blue : Colors.green;

    final size = <String>[
      if ((s.number ?? 0) > 0) '${s.number} pcs',
      if ((s.kg ?? 0) > 0)     '${s.kg!.toStringAsFixed(2)} kg',
      if ((s.cbm ?? 0) > 0)    '${s.cbm!.toStringAsFixed(3)} cbm',
    ].join(' · ');

    return ListTile(
      leading: CircleAvatar(
        backgroundColor: bucketColor.withValues(alpha: 0.12),
        child: Icon(modeIcon, color: bucketColor),
      ),
      title: Text(
        s.transactionNumber ?? 'Shipment #${s.id}',
        style: const TextStyle(fontWeight: FontWeight.w600),
      ),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text('$modeLabel · $bucketLabel · ${s.createdDate ?? ''}'),
          if (size.isNotEmpty) Text(size, style: const TextStyle(fontSize: 12)),
        ],
      ),
      trailing: s.paymentPending
        ? Chip(
            label: const Text('Payment due', style: TextStyle(fontSize: 10)),
            backgroundColor: Colors.orange.shade100,
            visualDensity: VisualDensity.compact,
          )
        : null,
    );
  }
}
