import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../models/transaction.dart';

class TransactionRow extends StatelessWidget {
  const TransactionRow({super.key, required this.tx});
  final Transaction tx;

  @override
  Widget build(BuildContext context) {
    final isCredit = tx.isCredit;
    final isDebit  = tx.isDebit;
    final color = isDebit ? Colors.red.shade700 : Colors.green.shade700;
    final sign  = isDebit ? '−' : (isCredit ? '+' : '');
    final amount = NumberFormat.decimalPattern().format(tx.value);

    return ListTile(
      leading: CircleAvatar(
        backgroundColor: color.withValues(alpha: 0.1),
        child: Icon(_iconFor(tx.type), color: color),
      ),
      title: Text(_titleFor(tx.type), style: const TextStyle(fontWeight: FontWeight.w600)),
      subtitle: Text(
        '${tx.createdDate} · ${tx.transactionNumber ?? tx.purpose ?? ''}'.trim(),
      ),
      trailing: Text(
        '$sign$amount ${tx.currency.toUpperCase()}',
        style: TextStyle(
          color: color,
          fontWeight: FontWeight.w700,
          fontFeatures: const <FontFeature>[FontFeature.tabularFigures()],
        ),
      ),
    );
  }

  IconData _iconFor(String type) => switch (type) {
        'deposit'    => Icons.arrow_downward,
        'withdraw'   => Icons.arrow_upward,
        'commission' => Icons.percent,
        'transfer'   => Icons.swap_horiz,
        _            => Icons.swap_vert,
      };

  String _titleFor(String type) => switch (type) {
        'deposit'    => 'Deposit',
        'withdraw'   => 'Withdrawal',
        'commission' => 'Commission',
        'transfer'   => 'Transfer',
        _            => type,
      };
}
