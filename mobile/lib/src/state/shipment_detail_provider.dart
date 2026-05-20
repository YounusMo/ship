import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_service.dart';
import '../models/shipment_detail.dart';

/// Family-keyed by (mode, id) so each detail screen has its own cache
/// entry. Riverpod's auto-dispose drops the entry when the screen pops.
final shipmentDetailProvider = AsyncNotifierProvider.autoDispose
    .family<ShipmentDetailNotifier, ShipmentDetail, ShipmentDetailKey>(
  ShipmentDetailNotifier.new,
);

class ShipmentDetailKey {
  final String mode; // 'sea' | 'sky'
  final int    id;
  const ShipmentDetailKey(this.mode, this.id);

  @override
  bool operator ==(Object other) =>
      other is ShipmentDetailKey && other.mode == mode && other.id == id;
  @override
  int get hashCode => Object.hash(mode, id);
}

class ShipmentDetailNotifier
    extends AutoDisposeFamilyAsyncNotifier<ShipmentDetail, ShipmentDetailKey> {
  @override
  Future<ShipmentDetail> build(ShipmentDetailKey arg) =>
      apiService.shipmentDetail(mode: arg.mode, id: arg.id);

  Future<void> refresh() async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() => apiService.shipmentDetail(mode: arg.mode, id: arg.id));
  }
}
