import 'package:flutter/foundation.dart';

import '../api/api_exceptions.dart';
import '../api/api_service.dart';
import 'outbox_store.dart';

/// Drives the outbox: takes pending entries, calls scanSubmit for each,
/// marks success/failure/non-retryable accordingly.
class OutboxDrainer {
  OutboxDrainer({required this.api, required this.store});
  final ApiService api;
  final OutboxStore store;

  /// Drains in FIFO order, stops on the first 5xx or network error so we
  /// don't burn through retries when the network is clearly down.
  /// Returns (sent, failed, dropped).
  Future<OutboxDrainResult> drainOnce({int max = 25}) async {
    final batch = await store.peek(limit: max);
    int sent = 0, failed = 0, dropped = 0;

    for (final entry in batch) {
      try {
        await api.scanSubmit(entry.payload);
        await store.markSent(entry.id);
        sent++;
      } on ApiServerException catch (e) {
        await store.markFailed(entry.id, '5xx ${e.status}: ${e.message}');
        failed++;
        break;
      } on ApiNetworkException catch (e) {
        await store.markFailed(entry.id, 'net: ${e.message}');
        failed++;
        break;
      } on ApiAuthException catch (e) {
        // Don't drop — the user needs to re-login; the outbox stays put.
        await store.markFailed(entry.id, 'auth: ${e.message}');
        failed++;
        break;
      } on ApiValidationException catch (e) {
        // 422 with wireType=invalid_transition is non-retryable — the
        // shipment moved on. Drop, log, and let activity log show the gap.
        await store.drop(entry.id);
        debugPrint('outbox: dropped ${entry.id} (${e.wireType}: ${e.message})');
        dropped++;
      } on ApiForbiddenException catch (e) {
        // 403 branch_scope_denied means the operator's token isn't valid
        // for the branch on file. Don't drop — they may re-login with the
        // right scope later.
        await store.markFailed(entry.id, '403 ${e.wireType}: ${e.message}');
        failed++;
        break;
      } catch (e) {
        await store.markFailed(entry.id, 'unknown: $e');
        failed++;
        break;
      }
    }

    return OutboxDrainResult(sent: sent, failed: failed, dropped: dropped);
  }
}

class OutboxDrainResult {
  final int sent;
  final int failed;
  final int dropped;
  const OutboxDrainResult({required this.sent, required this.failed, required this.dropped});

  bool get isClean => failed == 0;
}
