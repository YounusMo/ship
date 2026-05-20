import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../models/notification_item.dart';

class NotificationRow extends StatelessWidget {
  const NotificationRow({super.key, required this.item, required this.onTap});
  final NotificationItem item;
  final VoidCallback     onTap;

  @override
  Widget build(BuildContext context) {
    final unread = item.isUnread;
    final cat    = item.category;
    final (icon, color) = switch (cat) {
      'transaction' => (Icons.account_balance_wallet_outlined, Colors.green),
      'shipment'    => (Icons.local_shipping_outlined,         Colors.blue),
      'receipt'     => (Icons.receipt_outlined,                Colors.purple),
      _             => (Icons.notifications_none,              Colors.grey),
    };

    return ListTile(
      tileColor: unread ? Theme.of(context).colorScheme.primary.withValues(alpha: 0.04) : null,
      leading: Stack(
        children: <Widget>[
          CircleAvatar(backgroundColor: color.withValues(alpha: 0.12), child: Icon(icon, color: color)),
          if (unread)
            Positioned(
              right: 0,
              top: 0,
              child: Container(
                width: 10, height: 10,
                decoration: const BoxDecoration(color: Colors.red, shape: BoxShape.circle),
              ),
            ),
        ],
      ),
      title: Text(_titleFor(item), style: TextStyle(fontWeight: unread ? FontWeight.w700 : FontWeight.w500)),
      subtitle: Text(_subtitleFor(item)),
      trailing: Text(
        DateFormat.MMMd().format(item.createdAt),
        style: const TextStyle(fontSize: 11, color: Colors.black54),
      ),
      onTap: onTap,
    );
  }

  String _titleFor(NotificationItem n) {
    switch (n.category) {
      case 'transaction':
        final kind = n.data['kind'] as String? ?? 'transaction';
        return switch (kind) {
          'deposit'      => 'Deposit posted',
          'withdraw'     => 'Withdrawal posted',
          'commission'   => 'Commission charged',
          'transfer_in'  => 'Transfer received',
          'transfer_out' => 'Transfer sent',
          'transfer'     => 'Currency transfer',
          _               => 'Transaction posted',
        };
      case 'shipment':
        final mode   = n.data['mode']   as String? ?? '';
        final status = n.data['status'] as String? ?? '';
        final label  = mode == 'sea' ? 'Sea' : 'Air';
        return switch (status) {
          'received' => '$label shipment received',
          'shipped'  => '$label shipment dispatched',
          'canceled' => '$label shipment canceled',
          _           => '$label shipment update',
        };
      case 'receipt':
        return 'Receipt ${n.data['receipt_number'] ?? ''}';
      default:
        return 'Update';
    }
  }

  String _subtitleFor(NotificationItem n) {
    switch (n.category) {
      case 'transaction':
        final amt = n.data['amount']?.toString() ?? '';
        final cur = (n.data['currency'] as String? ?? '').toUpperCase();
        final txn = n.data['transaction_number'] as String? ?? '';
        return '$amt $cur${txn.isNotEmpty ? " · $txn" : ""}';
      case 'shipment':
        final txn = n.data['transaction_number'] as String? ?? '';
        return txn.isEmpty ? '' : txn;
      case 'receipt':
        final amt = n.data['amount']?.toString() ?? '';
        final cur = (n.data['currency'] as String? ?? '').toUpperCase();
        return '$amt $cur';
      default:
        return '';
    }
  }
}
