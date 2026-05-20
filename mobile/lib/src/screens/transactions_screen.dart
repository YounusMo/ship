import 'package:flutter/material.dart';
import 'package:shipflow_client/l10n/app_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../state/transactions_provider.dart';
import '../widgets/transaction_row.dart';

class TransactionsScreen extends ConsumerStatefulWidget {
  const TransactionsScreen({super.key});

  @override
  ConsumerState<TransactionsScreen> createState() => _TransactionsScreenState();
}

class _TransactionsScreenState extends ConsumerState<TransactionsScreen> {
  final _scroll = ScrollController();
  String? _filter;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.position.pixels >= _scroll.position.maxScrollExtent - 200) {
        ref.read(transactionsProvider.notifier).loadMore();
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
    final state = ref.watch(transactionsProvider);

    return Column(
      children: <Widget>[
        _CurrencyFilter(
          current: _filter,
          onChanged: (v) {
            setState(() => _filter = v);
            ref.read(transactionsProvider.notifier).setCurrency(v);
          },
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () => ref.read(transactionsProvider.notifier).refresh(),
            child: state.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => Center(child: Text('$e')),
              data: (s) {
                if (s.items.isEmpty) {
                  return Center(child: Text(AppLocalizations.of(context)!.noTransactionsYet));
                }
                return ListView.separated(
                  controller: _scroll,
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  itemCount: s.items.length + (s.page.hasMore ? 1 : 0),
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (context, i) {
                    if (i >= s.items.length) {
                      return const Padding(
                        padding: EdgeInsets.all(16),
                        child: Center(child: CircularProgressIndicator()),
                      );
                    }
                    return TransactionRow(tx: s.items[i]);
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

class _CurrencyFilter extends StatelessWidget {
  const _CurrencyFilter({required this.current, required this.onChanged});
  final String? current;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final choices = <(String?, String)>[
      (null,  l.filterAll),
      ('usd', l.currencyUsd),
      ('eur', l.currencyEur),
      ('den', l.currencyLyd),
      ('cny', l.currencyCny),
    ];
    return SizedBox(
      height: 48,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        itemCount: choices.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (_, i) {
          final (value, label) = choices[i];
          final selected = value == current;
          return ChoiceChip(
            label: Text(label),
            selected: selected,
            onSelected: (_) => onChanged(value),
          );
        },
      ),
    );
  }
}
