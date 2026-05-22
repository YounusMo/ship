import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_ar.dart';
import 'app_localizations_en.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations? of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations);
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('ar'),
    Locale('en'),
  ];

  /// No description provided for @appTitle.
  ///
  /// In en, this message translates to:
  /// **'ShipFlow Employee'**
  String get appTitle;

  /// No description provided for @loginTitle.
  ///
  /// In en, this message translates to:
  /// **'Sign in'**
  String get loginTitle;

  /// No description provided for @loginEmail.
  ///
  /// In en, this message translates to:
  /// **'Email'**
  String get loginEmail;

  /// No description provided for @loginPassword.
  ///
  /// In en, this message translates to:
  /// **'Password'**
  String get loginPassword;

  /// No description provided for @loginButton.
  ///
  /// In en, this message translates to:
  /// **'Sign in'**
  String get loginButton;

  /// No description provided for @loginFailedTitle.
  ///
  /// In en, this message translates to:
  /// **'Sign in failed'**
  String get loginFailedTitle;

  /// No description provided for @loginFailedNoBranch.
  ///
  /// In en, this message translates to:
  /// **'Your account isn\'t assigned to any branch. Contact ops.'**
  String get loginFailedNoBranch;

  /// No description provided for @loginFailedBadCreds.
  ///
  /// In en, this message translates to:
  /// **'Wrong email or password.'**
  String get loginFailedBadCreds;

  /// No description provided for @loginFailedRate.
  ///
  /// In en, this message translates to:
  /// **'Too many attempts. Try again in {seconds}s.'**
  String loginFailedRate(int seconds);

  /// No description provided for @loginFailedGeneric.
  ///
  /// In en, this message translates to:
  /// **'Couldn\'t sign in: {detail}'**
  String loginFailedGeneric(String detail);

  /// No description provided for @homeWelcome.
  ///
  /// In en, this message translates to:
  /// **'Hi {name}'**
  String homeWelcome(String name);

  /// No description provided for @homeActiveBranch.
  ///
  /// In en, this message translates to:
  /// **'Active branch'**
  String get homeActiveBranch;

  /// No description provided for @homePickBranch.
  ///
  /// In en, this message translates to:
  /// **'Pick a branch'**
  String get homePickBranch;

  /// No description provided for @homeBranchRoleLabel.
  ///
  /// In en, this message translates to:
  /// **'{role} at {branch}'**
  String homeBranchRoleLabel(String role, String branch);

  /// No description provided for @homeStartScan.
  ///
  /// In en, this message translates to:
  /// **'Scan QR sticker'**
  String get homeStartScan;

  /// No description provided for @homeViewQueue.
  ///
  /// In en, this message translates to:
  /// **'Branch queue'**
  String get homeViewQueue;

  /// No description provided for @homeViewActivity.
  ///
  /// In en, this message translates to:
  /// **'My activity'**
  String get homeViewActivity;

  /// No description provided for @homeLogout.
  ///
  /// In en, this message translates to:
  /// **'Sign out'**
  String get homeLogout;

  /// No description provided for @scannerHint.
  ///
  /// In en, this message translates to:
  /// **'Point the camera at a sticker.'**
  String get scannerHint;

  /// No description provided for @scannerTorch.
  ///
  /// In en, this message translates to:
  /// **'Torch'**
  String get scannerTorch;

  /// No description provided for @scannerManualEntry.
  ///
  /// In en, this message translates to:
  /// **'Enter sticker id manually'**
  String get scannerManualEntry;

  /// No description provided for @scannerInvalidSticker.
  ///
  /// In en, this message translates to:
  /// **'That doesn\'t look like a ShipFlow sticker.'**
  String get scannerInvalidSticker;

  /// No description provided for @scannerLookingUp.
  ///
  /// In en, this message translates to:
  /// **'Looking up sticker...'**
  String get scannerLookingUp;

  /// No description provided for @scanResolveTitleAssigned.
  ///
  /// In en, this message translates to:
  /// **'Sticker assigned'**
  String get scanResolveTitleAssigned;

  /// No description provided for @scanResolveTitleUnassigned.
  ///
  /// In en, this message translates to:
  /// **'Fresh sticker — first scan'**
  String get scanResolveTitleUnassigned;

  /// No description provided for @scanResolveTitleRevoked.
  ///
  /// In en, this message translates to:
  /// **'Sticker revoked'**
  String get scanResolveTitleRevoked;

  /// No description provided for @scanResolveTitleNotFound.
  ///
  /// In en, this message translates to:
  /// **'Sticker not found'**
  String get scanResolveTitleNotFound;

  /// No description provided for @scanResolveCurrentEvent.
  ///
  /// In en, this message translates to:
  /// **'Current event'**
  String get scanResolveCurrentEvent;

  /// No description provided for @scanResolveNoneYet.
  ///
  /// In en, this message translates to:
  /// **'no events yet'**
  String get scanResolveNoneYet;

  /// No description provided for @scanResolveChooseAction.
  ///
  /// In en, this message translates to:
  /// **'Choose action'**
  String get scanResolveChooseAction;

  /// No description provided for @scanResolveNoActionsAllowed.
  ///
  /// In en, this message translates to:
  /// **'No actions are allowed from the current state.'**
  String get scanResolveNoActionsAllowed;

  /// No description provided for @scanResolveNotesLabel.
  ///
  /// In en, this message translates to:
  /// **'Notes (optional)'**
  String get scanResolveNotesLabel;

  /// No description provided for @scanResolveToBranchLabel.
  ///
  /// In en, this message translates to:
  /// **'Destination branch (for transit)'**
  String get scanResolveToBranchLabel;

  /// No description provided for @scanResolvePieceIdLabel.
  ///
  /// In en, this message translates to:
  /// **'Shipment piece id (required for first scan)'**
  String get scanResolvePieceIdLabel;

  /// No description provided for @scanResolveSubmit.
  ///
  /// In en, this message translates to:
  /// **'Submit scan'**
  String get scanResolveSubmit;

  /// No description provided for @scanResolveQueuedOffline.
  ///
  /// In en, this message translates to:
  /// **'Offline — queued. Will sync when back online.'**
  String get scanResolveQueuedOffline;

  /// No description provided for @scanResolveSubmitted.
  ///
  /// In en, this message translates to:
  /// **'Recorded: {event}'**
  String scanResolveSubmitted(String event);

  /// No description provided for @scanResolveBranchScopeError.
  ///
  /// In en, this message translates to:
  /// **'You\'re not signed in for this branch.'**
  String get scanResolveBranchScopeError;

  /// No description provided for @scanResolveInvalidTransition.
  ///
  /// In en, this message translates to:
  /// **'That action isn\'t allowed from the current state.'**
  String get scanResolveInvalidTransition;

  /// No description provided for @queueTitle.
  ///
  /// In en, this message translates to:
  /// **'Branch queue'**
  String get queueTitle;

  /// No description provided for @queueEmpty.
  ///
  /// In en, this message translates to:
  /// **'Nothing currently held at this branch.'**
  String get queueEmpty;

  /// No description provided for @queueRefresh.
  ///
  /// In en, this message translates to:
  /// **'Refresh'**
  String get queueRefresh;

  /// No description provided for @activityTitle.
  ///
  /// In en, this message translates to:
  /// **'My activity'**
  String get activityTitle;

  /// No description provided for @activityEmpty.
  ///
  /// In en, this message translates to:
  /// **'No scans yet.'**
  String get activityEmpty;

  /// No description provided for @activityRefresh.
  ///
  /// In en, this message translates to:
  /// **'Refresh'**
  String get activityRefresh;

  /// No description provided for @outboxPendingCount.
  ///
  /// In en, this message translates to:
  /// **'{n,plural, =1{1 scan} other{{n} scans}} waiting to sync'**
  String outboxPendingCount(int n);

  /// No description provided for @outboxFlushNow.
  ///
  /// In en, this message translates to:
  /// **'Sync now'**
  String get outboxFlushNow;

  /// No description provided for @outboxFlushFailed.
  ///
  /// In en, this message translates to:
  /// **'Sync failed: {detail}'**
  String outboxFlushFailed(String detail);

  /// No description provided for @settingsTitle.
  ///
  /// In en, this message translates to:
  /// **'Settings'**
  String get settingsTitle;

  /// No description provided for @settingsBaseUrl.
  ///
  /// In en, this message translates to:
  /// **'API base URL'**
  String get settingsBaseUrl;

  /// No description provided for @settingsBaseUrlSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Where the app talks to. Change only when ops asks.'**
  String get settingsBaseUrlSubtitle;

  /// No description provided for @settingsBaseUrlSaved.
  ///
  /// In en, this message translates to:
  /// **'Saved.'**
  String get settingsBaseUrlSaved;

  /// No description provided for @eventReceivedAtHub.
  ///
  /// In en, this message translates to:
  /// **'Received at hub'**
  String get eventReceivedAtHub;

  /// No description provided for @eventInTransitInternal.
  ///
  /// In en, this message translates to:
  /// **'Dispatch (in transit)'**
  String get eventInTransitInternal;

  /// No description provided for @eventReceivedAtBranch.
  ///
  /// In en, this message translates to:
  /// **'Received at branch'**
  String get eventReceivedAtBranch;

  /// No description provided for @eventReadyForPickup.
  ///
  /// In en, this message translates to:
  /// **'Ready for pickup'**
  String get eventReadyForPickup;

  /// No description provided for @eventDeliveredToCustomer.
  ///
  /// In en, this message translates to:
  /// **'Delivered to customer'**
  String get eventDeliveredToCustomer;

  /// No description provided for @eventReturnedToHub.
  ///
  /// In en, this message translates to:
  /// **'Return to hub'**
  String get eventReturnedToHub;

  /// No description provided for @eventLost.
  ///
  /// In en, this message translates to:
  /// **'Mark lost'**
  String get eventLost;

  /// No description provided for @eventDamaged.
  ///
  /// In en, this message translates to:
  /// **'Mark damaged'**
  String get eventDamaged;
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['ar', 'en'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'ar':
      return AppLocalizationsAr();
    case 'en':
      return AppLocalizationsEn();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
