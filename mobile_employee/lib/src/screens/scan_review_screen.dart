import 'dart:math';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../l10n/app_localizations.dart';
import '../api/api_exceptions.dart';
import '../models/scan_submit.dart';
import '../models/sticker_resolve.dart';
import '../state/auth_state.dart';
import '../state/providers.dart';

/// After a successful QR scan, the operator lands here. We resolve the
/// sticker against the backend (or fall back to "submit blind into the
/// outbox" if offline), then show one of:
///   - Assigned: pick from `allowed_event_types`, fill notes, optionally
///     pick a to_branch for transit.
///   - Unassigned: only RECEIVED_AT_HUB is allowed; require shipment piece id.
///   - Revoked / Not found: render the error state, no actions.
class ScanReviewScreen extends ConsumerStatefulWidget {
  const ScanReviewScreen({super.key, required this.stickerId});
  final String stickerId;

  @override
  ConsumerState<ScanReviewScreen> createState() => _ScanReviewScreenState();
}

class _ScanReviewScreenState extends ConsumerState<ScanReviewScreen> {
  late Future<StickerResolveResult> _resolveFuture;
  final _notes = TextEditingController();
  final _pieceIdCtrl = TextEditingController();
  int? _toBranchId;
  String? _selectedEventType;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _resolveFuture = _resolve();
  }

  Future<StickerResolveResult> _resolve() {
    return ref.read(apiServiceProvider).scanResolve(stickerId: widget.stickerId);
  }

  @override
  void dispose() {
    _notes.dispose();
    _pieceIdCtrl.dispose();
    super.dispose();
  }

  /// Generate a client_event_id (ULID-ish — Crockford alphabet, 26 chars)
  /// so the outbox row and the server insert share a dedup key.
  String _newClientEventId() {
    const alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    final rand = Random.secure();
    final ts = DateTime.now().toUtc().millisecondsSinceEpoch.toRadixString(32).toUpperCase().padLeft(10, '0');
    final rnd = List<String>.generate(16, (_) => alphabet[rand.nextInt(alphabet.length)]).join();
    return (ts + rnd).substring(0, 26);
  }

  Future<void> _submit({
    required String eventType,
    required int branchId,
    int? pieceId,
  }) async {
    setState(() => _submitting = true);
    final l = AppLocalizations.of(context)!;
    final payload = ScanSubmitPayload(
      stickerId       : widget.stickerId,
      eventType       : eventType,
      branchId        : branchId,
      shipmentPieceId : pieceId,
      toBranchId      : _toBranchId,
      notes           : _notes.text.trim().isEmpty ? null : _notes.text.trim(),
      clientEventId   : _newClientEventId(),
    );

    try {
      await ref.read(apiServiceProvider).scanSubmit(payload);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l.scanResolveSubmitted(_localizedEvent(l, eventType)))),
      );
      context.pop();
    } on ApiValidationException catch (e) {
      final msg = e.wireType == 'invalid_transition'
          ? l.scanResolveInvalidTransition
          : e.message;
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
    } on ApiForbiddenException catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l.scanResolveBranchScopeError)),
      );
    } on ApiNetworkException catch (_) {
      await ref.read(outboxStoreProvider).enqueue(payload);
      ref.invalidate(outboxPendingCountProvider);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l.scanResolveQueuedOffline)),
      );
      context.pop();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  String _localizedEvent(AppLocalizations l, String code) => switch (code) {
    'RECEIVED_AT_HUB'        => l.eventReceivedAtHub,
    'IN_TRANSIT_INTERNAL'    => l.eventInTransitInternal,
    'RECEIVED_AT_BRANCH'     => l.eventReceivedAtBranch,
    'READY_FOR_PICKUP'       => l.eventReadyForPickup,
    'DELIVERED_TO_CUSTOMER'  => l.eventDeliveredToCustomer,
    'RETURNED_TO_HUB'        => l.eventReturnedToHub,
    'LOST'                   => l.eventLost,
    'DAMAGED'                => l.eventDamaged,
    _                        => code,
  };

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final auth = ref.watch(authControllerProvider).value;
    final activeBranchId = auth is AuthSignedIn ? auth.activeBranchId : null;
    final emp = auth is AuthSignedIn ? auth.employee : null;

    return Scaffold(
      appBar: AppBar(title: Text(widget.stickerId.substring(widget.stickerId.length - 8))),
      body: FutureBuilder<StickerResolveResult>(
        future: _resolveFuture,
        builder: (ctx, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return Center(child: Column(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                const CircularProgressIndicator(),
                const SizedBox(height: 12),
                Text(l.scannerLookingUp),
              ],
            ));
          }
          if (snap.hasError) {
            // Offline / 5xx — let the operator still submit blind to outbox
            // if they have an active branch and trust the eventType pick.
            return _OfflineFallback(
              stickerId   : widget.stickerId,
              activeBranch: activeBranchId,
              notesController: _notes,
              pieceIdController: _pieceIdCtrl,
              submitting: _submitting,
              onSubmit: (eventType, pieceId) {
                if (activeBranchId == null) return;
                _submit(eventType: eventType, branchId: activeBranchId, pieceId: pieceId);
              },
            );
          }
          final result = snap.data!;
          return switch (result) {
            StickerResolveAssigned()    => _AssignedView(
              result: result,
              activeBranchId: activeBranchId,
              employeeBranchIds: emp?.assignments.map((a) => a.branch.id).toList() ?? const [],
              notesController: _notes,
              toBranchId: _toBranchId,
              onToBranchChanged: (v) => setState(() => _toBranchId = v),
              selectedEventType: _selectedEventType,
              onEventTypeChanged: (v) => setState(() => _selectedEventType = v),
              submitting: _submitting,
              onSubmit: (eventType) => activeBranchId == null
                  ? null
                  : _submit(eventType: eventType, branchId: activeBranchId, pieceId: result.pieceId),
            ),
            StickerResolveUnassigned()  => _UnassignedView(
              result: result,
              activeBranchId: activeBranchId,
              notesController: _notes,
              pieceIdController: _pieceIdCtrl,
              submitting: _submitting,
              onSubmit: (pieceId) => activeBranchId == null
                  ? null
                  : _submit(eventType: 'RECEIVED_AT_HUB', branchId: activeBranchId, pieceId: pieceId),
            ),
            StickerResolveRevoked()     => _ErrorState(title: l.scanResolveTitleRevoked, icon: Icons.block),
            StickerResolveNotFound()    => _ErrorState(title: l.scanResolveTitleNotFound, icon: Icons.help_outline),
          };
        },
      ),
    );
  }
}

class _AssignedView extends StatelessWidget {
  const _AssignedView({
    required this.result,
    required this.activeBranchId,
    required this.employeeBranchIds,
    required this.notesController,
    required this.toBranchId,
    required this.onToBranchChanged,
    required this.selectedEventType,
    required this.onEventTypeChanged,
    required this.submitting,
    required this.onSubmit,
  });
  final StickerResolveAssigned result;
  final int? activeBranchId;
  final List<int> employeeBranchIds;
  final TextEditingController notesController;
  final int? toBranchId;
  final ValueChanged<int?> onToBranchChanged;
  final String? selectedEventType;
  final ValueChanged<String?> onEventTypeChanged;
  final bool submitting;
  final void Function(String eventType)? onSubmit;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final needsToBranch = selectedEventType == 'IN_TRANSIT_INTERNAL';
    final allowed = result.allowedEventTypes;
    return ListView(
      padding: const EdgeInsets.all(16),
      children: <Widget>[
        Text(l.scanResolveTitleAssigned, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        Text('${l.scanResolveCurrentEvent}: ${result.currentEventType ?? l.scanResolveNoneYet}'),
        const SizedBox(height: 16),
        if (allowed.isEmpty)
          Padding(padding: const EdgeInsets.all(12), child: Text(l.scanResolveNoActionsAllowed))
        else ...<Widget>[
          Text(l.scanResolveChooseAction, style: Theme.of(context).textTheme.labelLarge),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8, runSpacing: 8,
            children: allowed.map((code) {
              return ChoiceChip(
                label: Text(_eventLabel(l, code)),
                selected: selectedEventType == code,
                onSelected: (s) => onEventTypeChanged(s ? code : null),
              );
            }).toList(),
          ),
          const SizedBox(height: 16),
          if (needsToBranch)
            DropdownButtonFormField<int?>(
              decoration: InputDecoration(labelText: l.scanResolveToBranchLabel),
              initialValue: toBranchId,
              items: employeeBranchIds.where((id) => id != activeBranchId)
                  .map((id) => DropdownMenuItem<int?>(value: id, child: Text('Branch #$id')))
                  .toList(),
              onChanged: onToBranchChanged,
            ),
          if (needsToBranch) const SizedBox(height: 12),
          TextField(
            controller: notesController,
            minLines: 2, maxLines: 4,
            decoration: InputDecoration(labelText: l.scanResolveNotesLabel),
          ),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: (selectedEventType == null || submitting || onSubmit == null)
                ? null
                : () => onSubmit!(selectedEventType!),
            child: submitting
                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                : Text(l.scanResolveSubmit),
          ),
        ],
      ],
    );
  }

  String _eventLabel(AppLocalizations l, String code) => switch (code) {
    'RECEIVED_AT_HUB'        => l.eventReceivedAtHub,
    'IN_TRANSIT_INTERNAL'    => l.eventInTransitInternal,
    'RECEIVED_AT_BRANCH'     => l.eventReceivedAtBranch,
    'READY_FOR_PICKUP'       => l.eventReadyForPickup,
    'DELIVERED_TO_CUSTOMER'  => l.eventDeliveredToCustomer,
    'RETURNED_TO_HUB'        => l.eventReturnedToHub,
    'LOST'                   => l.eventLost,
    'DAMAGED'                => l.eventDamaged,
    _                        => code,
  };
}

class _UnassignedView extends StatelessWidget {
  const _UnassignedView({
    required this.result,
    required this.activeBranchId,
    required this.notesController,
    required this.pieceIdController,
    required this.submitting,
    required this.onSubmit,
  });
  final StickerResolveUnassigned result;
  final int? activeBranchId;
  final TextEditingController notesController;
  final TextEditingController pieceIdController;
  final bool submitting;
  final void Function(int pieceId)? onSubmit;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    return ListView(
      padding: const EdgeInsets.all(16),
      children: <Widget>[
        Text(l.scanResolveTitleUnassigned, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 16),
        TextField(
          controller: pieceIdController,
          keyboardType: TextInputType.number,
          decoration: InputDecoration(labelText: l.scanResolvePieceIdLabel),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: notesController,
          minLines: 2, maxLines: 4,
          decoration: InputDecoration(labelText: l.scanResolveNotesLabel),
        ),
        const SizedBox(height: 16),
        FilledButton(
          onPressed: (submitting || onSubmit == null)
              ? null
              : () {
                  final id = int.tryParse(pieceIdController.text.trim());
                  if (id == null) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(content: Text(l.scanResolvePieceIdLabel)),
                    );
                    return;
                  }
                  onSubmit!(id);
                },
          child: submitting
              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
              : Text(l.scanResolveSubmit),
        ),
      ],
    );
  }
}

class _OfflineFallback extends StatefulWidget {
  const _OfflineFallback({
    required this.stickerId,
    required this.activeBranch,
    required this.notesController,
    required this.pieceIdController,
    required this.submitting,
    required this.onSubmit,
  });
  final String stickerId;
  final int? activeBranch;
  final TextEditingController notesController;
  final TextEditingController pieceIdController;
  final bool submitting;
  final void Function(String eventType, int? pieceId) onSubmit;

  @override
  State<_OfflineFallback> createState() => _OfflineFallbackState();
}

class _OfflineFallbackState extends State<_OfflineFallback> {
  String _eventType = 'RECEIVED_AT_HUB';

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final all = const ['RECEIVED_AT_HUB', 'IN_TRANSIT_INTERNAL', 'RECEIVED_AT_BRANCH', 'READY_FOR_PICKUP', 'DELIVERED_TO_CUSTOMER'];
    return ListView(
      padding: const EdgeInsets.all(16),
      children: <Widget>[
        Text(l.scanResolveQueuedOffline, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 16),
        Wrap(
          spacing: 8, runSpacing: 8,
          children: all.map((c) => ChoiceChip(
            label: Text(c),
            selected: _eventType == c,
            onSelected: (s) => setState(() => _eventType = c),
          )).toList(),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: widget.pieceIdController,
          keyboardType: TextInputType.number,
          decoration: InputDecoration(labelText: l.scanResolvePieceIdLabel),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: widget.notesController,
          minLines: 2, maxLines: 4,
          decoration: InputDecoration(labelText: l.scanResolveNotesLabel),
        ),
        const SizedBox(height: 16),
        FilledButton(
          onPressed: widget.submitting
              ? null
              : () => widget.onSubmit(_eventType, int.tryParse(widget.pieceIdController.text.trim())),
          child: Text(l.scanResolveSubmit),
        ),
      ],
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.title, required this.icon});
  final String title;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: 56, color: Colors.red),
          const SizedBox(height: 12),
          Text(title, style: Theme.of(context).textTheme.titleLarge),
        ],
      ),
    );
  }
}
