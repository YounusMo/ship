import 'package:flutter_test/flutter_test.dart';
import 'package:shipflow_client/src/models/shipment_detail.dart';
import 'package:shipflow_client/src/models/tracking_timeline.dart';

void main() {
  group('TrackingTimeline.fromJson', () {
    test('parses status, counts, and ordered events', () {
      final json = <String, dynamic>{
        'status'   : 'IN_TRANSIT_INTERNAL',
        'counts'   : {'international': 2, 'internal': 1},
        'timeline' : [
          {
            'id'          : 5,
            'kind'        : 'INTERNATIONAL',
            'event_type'  : 'LOADED',
            'occurred_at' : '2026-05-18T08:00:00Z',
            'city'        : 'Shanghai',
            'country'     : 'CN',
            'message'     : 'Loaded onto vessel in Shanghai',
            'branch_id'   : null,
          },
          {
            'id'          : 9,
            'kind'        : 'INTERNATIONAL',
            'event_type'  : 'ARRIVED',
            'occurred_at' : '2026-05-20T10:30:00Z',
            'city'        : 'Misrata',
            'country'     : 'LY',
            'message'     : 'Arrived at Misrata port',
            'branch_id'   : null,
          },
          {
            'id'          : 11,
            'kind'        : 'INTERNAL',
            'event_type'  : 'RECEIVED_AT_HUB',
            'occurred_at' : '2026-05-20T14:00:00Z',
            'city'        : null,
            'country'     : 'LY',
            'message'     : 'Received at Misrata hub',
            'branch_id'   : 3,
          },
        ],
      };
      final t = TrackingTimeline.fromJson(json);
      expect(t.status, 'IN_TRANSIT_INTERNAL');
      expect(t.internationalCount, 2);
      expect(t.internalCount, 1);
      expect(t.events.length, 3);
      expect(t.events[0].isInternational, isTrue);
      expect(t.events[2].isInternal, isTrue);
      expect(t.events[2].branchId, 3);
      expect(t.events[0].locationLabel, 'Shanghai, CN');
      expect(t.events[2].locationLabel, 'LY');
    });

    test('survives a missing/empty payload without throwing', () {
      final t = TrackingTimeline.fromJson(const <String, dynamic>{});
      expect(t.status, 'AT_ORIGIN');
      expect(t.events, isEmpty);
      expect(t.internationalCount, 0);
      expect(t.internalCount, 0);
      expect(t.isEmpty, isTrue);
    });

    test('handles malformed occurred_at by yielding null', () {
      final t = TrackingTimeline.fromJson({
        'status'   : 'AT_HUB',
        'timeline' : [
          {'id': 1, 'kind': 'INTERNAL', 'event_type': 'RECEIVED_AT_HUB', 'occurred_at': 'not-a-date'},
        ],
      });
      expect(t.events.first.occurredAt, isNull);
    });
  });

  group('ShipmentDetail.fromJson', () {
    test('null tracking is preserved for the received bucket', () {
      final d = ShipmentDetail.fromJson({
        'mode'     : 'sea',
        'bucket'   : 'received',
        'row'      : {'id': 1},
        'pieces'   : [],
        'tracking' : null,
      });
      expect(d.tracking, isNull);
      expect(d.bucket, 'received');
    });

    test('tracking is populated for the shipped bucket', () {
      final d = ShipmentDetail.fromJson({
        'mode'     : 'sea',
        'bucket'   : 'shipped',
        'row'      : {'id': 1, 'container_id': 42},
        'pieces'   : [],
        'tracking' : {
          'status'  : 'DELIVERED',
          'counts'  : {'international': 1, 'internal': 4},
          'timeline': [],
        },
      });
      expect(d.tracking, isNotNull);
      expect(d.tracking!.status, 'DELIVERED');
      expect(d.tracking!.internalCount, 4);
    });
  });
}
