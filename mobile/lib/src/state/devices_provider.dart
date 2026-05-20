import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_service.dart';
import '../models/client_device.dart';

class DevicesNotifier extends AsyncNotifier<List<ClientDevice>> {
  @override
  Future<List<ClientDevice>> build() => apiService.devices();

  Future<void> refresh() async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() => apiService.devices());
  }

  Future<void> revoke(int id) async {
    // Optimistic remove. If the API call fails, the next refresh restores.
    final current = state.valueOrNull ?? const <ClientDevice>[];
    state = AsyncValue.data(current.where((d) => d.id != id).toList());
    try {
      await apiService.revokeDeviceById(id);
    } catch (_) {
      await refresh();
    }
  }
}

final devicesProvider =
    AsyncNotifierProvider<DevicesNotifier, List<ClientDevice>>(DevicesNotifier.new);
