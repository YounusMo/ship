import 'package:flutter_test/flutter_test.dart';
import 'package:shipflow_employee/src/models/scan_submit.dart';
import 'package:shipflow_employee/src/outbox/outbox_store.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';

ScanSubmitPayload _payload(String key, {String type = 'RECEIVED_AT_HUB'}) {
  return ScanSubmitPayload(
    stickerId: 'X' * 26,
    eventType: type,
    branchId: 1,
    shipmentPieceId: 42,
    toBranchId: null,
    notes: null,
    clientEventId: key,
  );
}

void main() {
  setUpAll(() {
    sqfliteFfiInit();
    databaseFactory = databaseFactoryFfi;
  });

  late OutboxStore store;

  setUp(() async {
    final db = await databaseFactoryFfi.openDatabase(inMemoryDatabasePath);
    store = OutboxStore(db: db);
    // sqflite's in-memory DB is shared across opens within the same
    // process when using ':memory:', so wipe between tests.
    await store.clear();
  });

  test('enqueue + pendingCount + peek FIFO', () async {
    await store.enqueue(_payload('a'));
    await store.enqueue(_payload('b'));
    await store.enqueue(_payload('c'));

    expect(await store.pendingCount(), 3);
    final list = await store.peek();
    expect(list.map((e) => e.payload.clientEventId).toList(), ['a', 'b', 'c']);
  });

  test('enqueue is idempotent on client_event_id', () async {
    final firstId  = await store.enqueue(_payload('dup'));
    final secondId = await store.enqueue(_payload('dup'));
    expect(firstId, secondId);
    expect(await store.pendingCount(), 1);
  });

  test('markSent removes from queue', () async {
    await store.enqueue(_payload('x'));
    final entry = (await store.peek()).single;
    await store.markSent(entry.id);
    expect(await store.pendingCount(), 0);
  });

  test('markFailed bumps attempt_count and records last_error', () async {
    await store.enqueue(_payload('y'));
    final entry = (await store.peek()).single;
    await store.markFailed(entry.id, 'net timeout');
    final after = (await store.peek()).single;
    expect(after.attemptCount, 1);
    expect(after.lastError, 'net timeout');
    expect(after.lastAttemptAt, isNotNull);
  });

  test('drop removes the row permanently', () async {
    await store.enqueue(_payload('z'));
    final entry = (await store.peek()).single;
    await store.drop(entry.id);
    expect(await store.pendingCount(), 0);
  });
}
