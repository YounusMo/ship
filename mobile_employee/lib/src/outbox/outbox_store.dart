import 'dart:convert';

import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:sqflite/sqflite.dart';

import '../models/scan_submit.dart';

/// SQLite-backed persistent queue for scan submits that couldn't be sent
/// (offline, transient 5xx). Drain order is FIFO; each row carries the
/// already-built ScanSubmitPayload (JSON) plus retry bookkeeping.
///
/// Idempotency: every queued row has the same client_event_id the server
/// would dedup on, so re-sending after a partial failure is safe.
class OutboxStore {
  OutboxStore({Database? db}) : _injected = db;

  final Database? _injected;
  Database? _db;

  Future<Database> _open() async {
    if (_db != null) return _db!;
    if (_injected != null) {
      _db = _injected;
      await _ensureSchema(_db!);
      return _db!;
    }
    final dir = await getApplicationSupportDirectory();
    final path = p.join(dir.path, 'shipflow_employee_outbox.db');
    _db = await openDatabase(path, version: 1, onCreate: (db, _) async {
      await _ensureSchema(db);
    });
    return _db!;
  }

  Future<void> _ensureSchema(Database db) async {
    await db.execute('''
      CREATE TABLE IF NOT EXISTS outbox (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        client_event_id TEXT    NOT NULL UNIQUE,
        payload_json    TEXT    NOT NULL,
        created_at      TEXT    NOT NULL,
        last_attempt_at TEXT,
        attempt_count   INTEGER NOT NULL DEFAULT 0,
        last_error      TEXT
      )
    ''');
  }

  /// Enqueue a scan. Returns the local row id. Re-enqueueing the same
  /// client_event_id is a no-op and returns the existing row id.
  Future<int> enqueue(ScanSubmitPayload payload) async {
    final db = await _open();
    final existing = await db.query(
      'outbox',
      columns: ['id'],
      where  : 'client_event_id = ?',
      whereArgs: [payload.clientEventId],
      limit: 1,
    );
    if (existing.isNotEmpty) return existing.first['id'] as int;

    return db.insert('outbox', <String, dynamic>{
      'client_event_id': payload.clientEventId,
      'payload_json'   : jsonEncode(payload.toJson()),
      'created_at'     : DateTime.now().toUtc().toIso8601String(),
      'attempt_count'  : 0,
    });
  }

  Future<int> pendingCount() async {
    final db = await _open();
    final rows = await db.rawQuery('SELECT COUNT(*) AS c FROM outbox');
    return (rows.first['c'] as int?) ?? 0;
  }

  /// FIFO read of pending entries.
  Future<List<OutboxEntry>> peek({int limit = 25}) async {
    final db = await _open();
    final rows = await db.query('outbox', orderBy: 'id ASC', limit: limit);
    return rows.map(OutboxEntry.fromRow).toList();
  }

  Future<void> markSent(int id) async {
    final db = await _open();
    await db.delete('outbox', where: 'id = ?', whereArgs: [id]);
  }

  Future<void> markFailed(int id, String error) async {
    final db = await _open();
    await db.update(
      'outbox',
      <String, dynamic>{
        'attempt_count'   : await _bumpAttempt(db, id),
        'last_attempt_at' : DateTime.now().toUtc().toIso8601String(),
        'last_error'      : error,
      },
      where    : 'id = ?',
      whereArgs: [id],
    );
  }

  Future<int> _bumpAttempt(Database db, int id) async {
    final row = await db.query('outbox', columns: ['attempt_count'], where: 'id = ?', whereArgs: [id], limit: 1);
    return ((row.firstOrNull?['attempt_count'] as int?) ?? 0) + 1;
  }

  /// Permanently drop a row that's failed for a non-retryable reason
  /// (4xx that won't change on retry). Caller decides what counts as
  /// non-retryable based on the typed exception.
  Future<void> drop(int id) async {
    final db = await _open();
    await db.delete('outbox', where: 'id = ?', whereArgs: [id]);
  }

  Future<void> clear() async {
    final db = await _open();
    await db.delete('outbox');
  }
}

class OutboxEntry {
  final int           id;
  final ScanSubmitPayload payload;
  final DateTime      createdAt;
  final DateTime?     lastAttemptAt;
  final int           attemptCount;
  final String?       lastError;

  const OutboxEntry({
    required this.id,
    required this.payload,
    required this.createdAt,
    required this.lastAttemptAt,
    required this.attemptCount,
    required this.lastError,
  });

  factory OutboxEntry.fromRow(Map<String, Object?> r) => OutboxEntry(
    id            : r['id'] as int,
    payload       : ScanSubmitPayload.fromJson(jsonDecode(r['payload_json'] as String) as Map<String, dynamic>),
    createdAt     : DateTime.parse(r['created_at'] as String),
    lastAttemptAt : r['last_attempt_at'] is String ? DateTime.parse(r['last_attempt_at'] as String) : null,
    attemptCount  : (r['attempt_count'] as int?) ?? 0,
    lastError     : r['last_error'] as String?,
  );
}
