import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_service.dart';
import '../models/paginated.dart';
import '../models/transaction.dart';

/// Paginated transactions. We accumulate pages in [data] for the
/// infinite-scroll list. Pull-to-refresh resets to page 1.
class TransactionsNotifier extends AsyncNotifier<TransactionsState> {
  String? _currencyFilter;

  @override
  Future<TransactionsState> build() async => _loadFirst();

  Future<TransactionsState> _loadFirst() async {
    final p = await apiService.transactions(page: 1, currency: _currencyFilter);
    return TransactionsState(items: p.data, page: p, currency: _currencyFilter);
  }

  /// Fetch the next page and append. Returns silently when we've hit the end.
  Future<void> loadMore() async {
    final current = state.valueOrNull;
    if (current == null || !current.page.hasMore) return;
    final next = await apiService.transactions(
      page: current.page.currentPage + 1,
      currency: _currencyFilter,
    );
    state = AsyncValue.data(current.copyWith(
      items: <Transaction>[...current.items, ...next.data],
      page : next,
    ));
  }

  Future<void> refresh() async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(_loadFirst);
  }

  Future<void> setCurrency(String? currency) async {
    _currencyFilter = currency;
    await refresh();
  }
}

class TransactionsState {
  final List<Transaction>   items;
  final Paginated<Transaction> page;
  final String?             currency;
  const TransactionsState({required this.items, required this.page, this.currency});

  TransactionsState copyWith({List<Transaction>? items, Paginated<Transaction>? page, String? currency}) =>
      TransactionsState(items: items ?? this.items, page: page ?? this.page, currency: currency ?? this.currency);
}

final transactionsProvider =
    AsyncNotifierProvider<TransactionsNotifier, TransactionsState>(TransactionsNotifier.new);
