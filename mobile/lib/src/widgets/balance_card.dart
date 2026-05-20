import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class BalanceCard extends StatelessWidget {
  const BalanceCard({super.key, required this.currency, required this.amount});

  final String currency; // 'USD', 'EUR', 'LYD', 'CNY'
  final double amount;

  @override
  Widget build(BuildContext context) {
    final positive = amount > 0;
    final negative = amount < 0;
    final color = negative
        ? Colors.red.shade700
        : positive
            ? Theme.of(context).colorScheme.primary
            : Colors.black54;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                Text(
                  currency,
                  style: Theme.of(context).textTheme.labelLarge?.copyWith(
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.6,
                        color: Colors.black54,
                      ),
                ),
              ],
            ),
            const Spacer(),
            Text(
              NumberFormat.decimalPattern().format(amount.abs()),
              style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    color: color,
                    fontWeight: FontWeight.w700,
                    fontFeatures: const <FontFeature>[FontFeature.tabularFigures()],
                  ),
            ),
            const SizedBox(height: 2),
            Text(
              negative
                  ? 'You owe'
                  : positive
                      ? 'We owe you'
                      : 'Settled',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.black54),
            ),
          ],
        ),
      ),
    );
  }
}
