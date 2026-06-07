import 'package:flutter_test/flutter_test.dart';
import 'package:shipflow_employee/src/models/employee.dart';
import 'package:shipflow_employee/src/models/scan_submit.dart';
import 'package:shipflow_employee/src/models/sticker_resolve.dart';

void main() {
  group('Employee.fromMe', () {
    test('parses user + branches + abilities', () {
      final me = {
        'user': {'id': 7, 'name': 'Ali', 'email': 'a@b.com'},
        'branches': [
          {
            'branch': {'id': 12, 'code': 'TRI', 'name': 'Tripoli', 'role': 'HUB', 'city': 'Tripoli'},
            'role': 'RECEIVER',
            'is_active': true,
          },
          {
            'branch': {'id': 13, 'code': 'SRT', 'name': 'Sirte', 'role': 'SPOKE', 'city': 'Sirte'},
            'role': 'COURIER',
            'is_active': false,
          },
        ],
      };
      final emp = Employee.fromMe(me, ['employee', 'branch:12', 'branch:13']);
      expect(emp.id, 7);
      expect(emp.name, 'Ali');
      expect(emp.assignments.length, 2);
      expect(emp.assignments.first.branch.role, 'HUB');
      expect(emp.canActAt(12), isTrue);
      expect(emp.canActAt(99), isFalse);
    });

    test('survives missing branches/user keys', () {
      final emp = Employee.fromMe(const {}, const []);
      expect(emp.id, 0);
      expect(emp.assignments, isEmpty);
    });
  });

  group('StickerResolveResult.fromJson', () {
    test('assigned shape', () {
      final r = StickerResolveResult.fromJson({
        'type': 'assigned',
        'sticker': {'id': '01HZZ${'X' * 21}'},
        'piece':   {'id': 5, 'source_table': 'store_out_sea', 'source_id': 100},
        'current_event_type': 'RECEIVED_AT_HUB',
        'allowed_event_types': ['IN_TRANSIT_INTERNAL', 'RETURNED_TO_HUB'],
      });
      expect(r, isA<StickerResolveAssigned>());
      final a = r as StickerResolveAssigned;
      expect(a.pieceId, 5);
      expect(a.sourceTable, 'store_out_sea');
      expect(a.allowedEventTypes, contains('IN_TRANSIT_INTERNAL'));
    });

    test('unassigned shape', () {
      final r = StickerResolveResult.fromJson({
        'type': 'unassigned',
        'sticker': {'id': '01HZZ${'X' * 21}'},
        'allowed_event_types': ['RECEIVED_AT_HUB'],
      });
      expect(r, isA<StickerResolveUnassigned>());
      expect((r as StickerResolveUnassigned).allowedEventTypes, ['RECEIVED_AT_HUB']);
    });

    test('revoked and not-found shapes', () {
      expect(
        StickerResolveResult.fromJson({'type': 'revoked_sticker', 'sticker': {'id': 'x' * 26}}),
        isA<StickerResolveRevoked>(),
      );
      expect(
        StickerResolveResult.fromJson({'type': 'unknown_sticker', 'sticker_id': 'x' * 26}),
        isA<StickerResolveNotFound>(),
      );
    });
  });

  group('ScanSubmitPayload', () {
    test('round-trips through JSON, drops null branches sensibly', () {
      final p = ScanSubmitPayload(
        stickerId: 'X' * 26,
        eventType: 'RECEIVED_AT_HUB',
        branchId: 1,
        shipmentPieceId: 42,
        toBranchId: null,
        notes: 'arrived dusty',
        clientEventId: 'ULID-X-${'A' * 19}',
      );
      final json = p.toJson();
      expect(json.containsKey('to_branch_id'), isFalse);
      expect(json['shipment_piece_id'], 42);
      expect(json['notes'], 'arrived dusty');
      final back = ScanSubmitPayload.fromJson(json);
      expect(back.stickerId, p.stickerId);
      expect(back.clientEventId, p.clientEventId);
    });

    test('omits empty notes', () {
      final p = ScanSubmitPayload(
        stickerId: 'X' * 26, eventType: 'X', branchId: 1,
        shipmentPieceId: null, toBranchId: null, notes: '', clientEventId: 'k',
      );
      expect(p.toJson().containsKey('notes'), isFalse);
    });
  });
}
