import 'package:flutter/material.dart';
import 'package:shipflow_client/l10n/app_localizations.dart';

import '../models/tracking_timeline.dart';

/// Vertical timeline of merged international + internal tracking events
/// for one shipment. Status pill at the top, then a connected vertical
/// rail with one tile per event, newest at the top.
///
/// Backend already returns the events chronologically (oldest first), so
/// we reverse here for the UI convention of "latest event on top".
class TrackingTimelineSection extends StatelessWidget {
  const TrackingTimelineSection({super.key, required this.timeline});
  final TrackingTimeline timeline;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;

    if (timeline.isEmpty) {
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Row(
            children: <Widget>[
              const Icon(Icons.timeline, color: Colors.black38),
              const SizedBox(width: 12),
              Expanded(child: Text(l.trackingNoEventsYet)),
            ],
          ),
        ),
      );
    }

    // Newest first for the UI; backend ships oldest first.
    final events = timeline.events.reversed.toList(growable: false);

    return Card(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                Icon(Icons.timeline, color: Theme.of(context).colorScheme.primary),
                const SizedBox(width: 8),
                Text(
                  l.trackingSectionTitle,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                ),
                const Spacer(),
                _StatusPill(status: timeline.status),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              l.trackingCountsLine(timeline.internationalCount, timeline.internalCount),
              style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.black54),
            ),
            const SizedBox(height: 12),
            for (int i = 0; i < events.length; i++)
              _EventRow(
                event   : events[i],
                isFirst : i == 0,
                isLast  : i == events.length - 1,
              ),
          ],
        ),
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.status});
  final String status;

  static const Map<String, Color> _palette = <String, Color>{
    'AT_ORIGIN'            : Color(0xFF607D8B),
    'IN_TRANSIT_INTL'      : Color(0xFF1976D2),
    'AT_PORT'              : Color(0xFF0288D1),
    'AT_HUB'               : Color(0xFF00897B),
    'IN_TRANSIT_INTERNAL'  : Color(0xFF00897B),
    'AT_DESTINATION'       : Color(0xFF7CB342),
    'READY_FOR_PICKUP'     : Color(0xFFFBC02D),
    'DELIVERED'            : Color(0xFF43A047),
    'EXCEPTION'            : Color(0xFFE53935),
  };

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final color = _palette[status] ?? Colors.grey;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.15),
        border: Border.all(color: color),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        _label(status, l),
        style: TextStyle(color: color, fontWeight: FontWeight.w700, fontSize: 12),
      ),
    );
  }

  static String _label(String status, AppLocalizations l) => switch (status) {
    'AT_ORIGIN'           => l.trackingStatusAtOrigin,
    'IN_TRANSIT_INTL'     => l.trackingStatusInTransitIntl,
    'AT_PORT'             => l.trackingStatusAtPort,
    'AT_HUB'              => l.trackingStatusAtHub,
    'IN_TRANSIT_INTERNAL' => l.trackingStatusInTransitInternal,
    'AT_DESTINATION'      => l.trackingStatusAtDestination,
    'READY_FOR_PICKUP'    => l.trackingStatusReadyForPickup,
    'DELIVERED'           => l.trackingStatusDelivered,
    'EXCEPTION'           => l.trackingStatusException,
    _                     => status,
  };
}

class _EventRow extends StatelessWidget {
  const _EventRow({required this.event, required this.isFirst, required this.isLast});
  final TrackingEvent event;
  final bool isFirst;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;

    final dot = Container(
      width: 14,
      height: 14,
      decoration: BoxDecoration(
        color: isFirst
            ? Theme.of(context).colorScheme.primary
            : (event.isInternational ? Colors.blue.shade300 : Colors.teal.shade300),
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white, width: 2),
        boxShadow: const <BoxShadow>[BoxShadow(color: Colors.black12, blurRadius: 2, offset: Offset(0, 1))],
      ),
    );

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          SizedBox(
            width: 28,
            child: Column(
              children: <Widget>[
                Container(width: 2, color: isFirst ? Colors.transparent : Colors.grey.shade300, height: 12),
                dot,
                Expanded(
                  child: Container(
                    width: 2,
                    color: isLast ? Colors.transparent : Colors.grey.shade300,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(bottom: 16, top: 6),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    event.message ?? event.eventType,
                    style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                  ),
                  const SizedBox(height: 2),
                  Wrap(
                    spacing: 8,
                    runSpacing: 2,
                    children: <Widget>[
                      if (event.occurredAt != null)
                        Text(
                          _formatDate(event.occurredAt!),
                          style: const TextStyle(color: Colors.black54, fontSize: 12),
                        ),
                      if (event.locationLabel.isNotEmpty)
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: <Widget>[
                            const Icon(Icons.location_on_outlined, size: 12, color: Colors.black45),
                            const SizedBox(width: 2),
                            Text(event.locationLabel, style: const TextStyle(color: Colors.black54, fontSize: 12)),
                          ],
                        ),
                      Text(
                        event.isInternational ? l.trackingKindIntl : l.trackingKindInternal,
                        style: TextStyle(
                          color: event.isInternational ? Colors.blue.shade700 : Colors.teal.shade700,
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  static String _formatDate(DateTime d) {
    String two(int n) => n.toString().padLeft(2, '0');
    return '${d.year}-${two(d.month)}-${two(d.day)} ${two(d.hour)}:${two(d.minute)}';
  }
}
