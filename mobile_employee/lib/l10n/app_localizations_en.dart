// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appTitle => 'ShipFlow Employee';

  @override
  String get loginTitle => 'Sign in';

  @override
  String get loginEmail => 'Email';

  @override
  String get loginPassword => 'Password';

  @override
  String get loginButton => 'Sign in';

  @override
  String get loginFailedTitle => 'Sign in failed';

  @override
  String get loginFailedNoBranch =>
      'Your account isn\'t assigned to any branch. Contact ops.';

  @override
  String get loginFailedBadCreds => 'Wrong email or password.';

  @override
  String loginFailedRate(int seconds) {
    return 'Too many attempts. Try again in ${seconds}s.';
  }

  @override
  String loginFailedGeneric(String detail) {
    return 'Couldn\'t sign in: $detail';
  }

  @override
  String homeWelcome(String name) {
    return 'Hi $name';
  }

  @override
  String get homeActiveBranch => 'Active branch';

  @override
  String get homePickBranch => 'Pick a branch';

  @override
  String homeBranchRoleLabel(String role, String branch) {
    return '$role at $branch';
  }

  @override
  String get homeStartScan => 'Scan QR sticker';

  @override
  String get homeViewQueue => 'Branch queue';

  @override
  String get homeViewActivity => 'My activity';

  @override
  String get homeLogout => 'Sign out';

  @override
  String get scannerHint => 'Point the camera at a sticker.';

  @override
  String get scannerTorch => 'Torch';

  @override
  String get scannerManualEntry => 'Enter sticker id manually';

  @override
  String get scannerInvalidSticker =>
      'That doesn\'t look like a ShipFlow sticker.';

  @override
  String get scannerLookingUp => 'Looking up sticker...';

  @override
  String get scanResolveTitleAssigned => 'Sticker assigned';

  @override
  String get scanResolveTitleUnassigned => 'Fresh sticker — first scan';

  @override
  String get scanResolveTitleRevoked => 'Sticker revoked';

  @override
  String get scanResolveTitleNotFound => 'Sticker not found';

  @override
  String get scanResolveCurrentEvent => 'Current event';

  @override
  String get scanResolveNoneYet => 'no events yet';

  @override
  String get scanResolveChooseAction => 'Choose action';

  @override
  String get scanResolveNoActionsAllowed =>
      'No actions are allowed from the current state.';

  @override
  String get scanResolveNotesLabel => 'Notes (optional)';

  @override
  String get scanResolveToBranchLabel => 'Destination branch (for transit)';

  @override
  String get scanResolvePieceIdLabel =>
      'Shipment piece id (required for first scan)';

  @override
  String get scanResolveSubmit => 'Submit scan';

  @override
  String get scanResolveQueuedOffline =>
      'Offline — queued. Will sync when back online.';

  @override
  String scanResolveSubmitted(String event) {
    return 'Recorded: $event';
  }

  @override
  String get scanResolveBranchScopeError =>
      'You\'re not signed in for this branch.';

  @override
  String get scanResolveInvalidTransition =>
      'That action isn\'t allowed from the current state.';

  @override
  String get queueTitle => 'Branch queue';

  @override
  String get queueEmpty => 'Nothing currently held at this branch.';

  @override
  String get queueRefresh => 'Refresh';

  @override
  String get activityTitle => 'My activity';

  @override
  String get activityEmpty => 'No scans yet.';

  @override
  String get activityRefresh => 'Refresh';

  @override
  String outboxPendingCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n scans',
      one: '1 scan',
    );
    return '$_temp0 waiting to sync';
  }

  @override
  String get outboxFlushNow => 'Sync now';

  @override
  String outboxFlushFailed(String detail) {
    return 'Sync failed: $detail';
  }

  @override
  String get settingsTitle => 'Settings';

  @override
  String get settingsBaseUrl => 'API base URL';

  @override
  String get settingsBaseUrlSubtitle =>
      'Where the app talks to. Change only when ops asks.';

  @override
  String get settingsBaseUrlSaved => 'Saved.';

  @override
  String get eventReceivedAtHub => 'Received at hub';

  @override
  String get eventInTransitInternal => 'Dispatch (in transit)';

  @override
  String get eventReceivedAtBranch => 'Received at branch';

  @override
  String get eventReadyForPickup => 'Ready for pickup';

  @override
  String get eventDeliveredToCustomer => 'Delivered to customer';

  @override
  String get eventReturnedToHub => 'Return to hub';

  @override
  String get eventLost => 'Mark lost';

  @override
  String get eventDamaged => 'Mark damaged';
}
