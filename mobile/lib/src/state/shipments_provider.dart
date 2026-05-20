import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_service.dart';
import '../models/paginated.dart';
import '../models/shipment.dart';

class ShipmentsNotifier extends AsyncNotifier<ShipmentsState> {
  String _mode = 'all';

  @override
  Future<ShipmentsState> build() async => _loadFirst();

  Future<ShipmentsState> _loadFirst() async {
    final p = await apiService.shipments(page: 1, mode: _mode);
    return ShipmentsState(items: p.data, page: p, mode: _mode);
  }

  Future<void> loadMore() async {
    final current = state.valueOrNull;
    if (current == null || !current.page.hasMore) return;
    final next = await apiService.shipments(
      page: current.page.currentPage + 1,
      mode: _mode,
    );
    state = AsyncValue.data(current.copyWith(
      items: <Shipment>[...current.items, ...next.data],
      page : next,
    ));
  }

  Future<void> refresh() async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(_loadFirst);
  }

  Future<void> setMode(String mode) async {
    _mode = mode;
    await refresh();
  }
}

class ShipmentsState {
  final List<Shipment>     items;
  final Paginated<Shipment> page;
  final String             mode;
  const ShipmentsState({required this.items, required this.page, required this.mode});

  ShipmentsState copyWith({List<Shipment>? items, Paginated<Shipment>? page, String? mode}) =>
      ShipmentsState(items: items ?? this.items, page: page ?? this.page, mode: mode ?? this.mode);
}

final shipmentsProvider =
    AsyncNotifierProvider<ShipmentsNotifier, ShipmentsState>(ShipmentsNotifier.new);
