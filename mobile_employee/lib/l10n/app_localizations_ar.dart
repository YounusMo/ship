// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Arabic (`ar`).
class AppLocalizationsAr extends AppLocalizations {
  AppLocalizationsAr([String locale = 'ar']) : super(locale);

  @override
  String get appTitle => 'موظف ShipFlow';

  @override
  String get loginTitle => 'تسجيل الدخول';

  @override
  String get loginEmail => 'البريد الإلكتروني';

  @override
  String get loginPassword => 'كلمة المرور';

  @override
  String get loginButton => 'دخول';

  @override
  String get loginFailedTitle => 'فشل تسجيل الدخول';

  @override
  String get loginFailedNoBranch =>
      'حسابك غير مرتبط بأي فرع. تواصل مع الإدارة.';

  @override
  String get loginFailedBadCreds => 'البريد أو كلمة المرور غير صحيحة.';

  @override
  String loginFailedRate(int seconds) {
    return 'محاولات كثيرة، حاول بعد $seconds ثانية.';
  }

  @override
  String loginFailedGeneric(String detail) {
    return 'تعذّر الدخول: $detail';
  }

  @override
  String homeWelcome(String name) {
    return 'مرحباً $name';
  }

  @override
  String get homeActiveBranch => 'الفرع الحالي';

  @override
  String get homePickBranch => 'اختر الفرع';

  @override
  String homeBranchRoleLabel(String role, String branch) {
    return '$role في $branch';
  }

  @override
  String get homeStartScan => 'مسح ملصق QR';

  @override
  String get homeViewQueue => 'قائمة الفرع';

  @override
  String get homeViewActivity => 'نشاطي';

  @override
  String get homeLogout => 'تسجيل الخروج';

  @override
  String get scannerHint => 'وجّه الكاميرا على الملصق.';

  @override
  String get scannerTorch => 'الفلاش';

  @override
  String get scannerManualEntry => 'إدخال رقم الملصق يدوياً';

  @override
  String get scannerInvalidSticker => 'هذا لا يبدو ملصق ShipFlow.';

  @override
  String get scannerLookingUp => 'جارٍ التحقق من الملصق...';

  @override
  String get scanResolveTitleAssigned => 'ملصق مرتبط بشحنة';

  @override
  String get scanResolveTitleUnassigned => 'ملصق جديد — أول مسح';

  @override
  String get scanResolveTitleRevoked => 'ملصق ملغى';

  @override
  String get scanResolveTitleNotFound => 'الملصق غير موجود';

  @override
  String get scanResolveCurrentEvent => 'آخر حدث';

  @override
  String get scanResolveNoneYet => 'لا توجد أحداث بعد';

  @override
  String get scanResolveChooseAction => 'اختر الإجراء';

  @override
  String get scanResolveNoActionsAllowed =>
      'لا يمكن تنفيذ أي إجراء من الحالة الحالية.';

  @override
  String get scanResolveNotesLabel => 'ملاحظات (اختياري)';

  @override
  String get scanResolveToBranchLabel => 'فرع الوجهة (للنقل)';

  @override
  String get scanResolvePieceIdLabel => 'رقم الطرد (إلزامي لأول مسح)';

  @override
  String get scanResolveSubmit => 'إرسال';

  @override
  String get scanResolveQueuedOffline =>
      'وضع غير متصل — تم الحفظ، سيُرسل عند عودة الاتصال.';

  @override
  String scanResolveSubmitted(String event) {
    return 'تم تسجيل: $event';
  }

  @override
  String get scanResolveBranchScopeError => 'لست مسجّلاً لهذا الفرع.';

  @override
  String get scanResolveInvalidTransition =>
      'هذا الإجراء غير مسموح من الحالة الحالية.';

  @override
  String get queueTitle => 'قائمة الفرع';

  @override
  String get queueEmpty => 'لا توجد شحنات الآن في هذا الفرع.';

  @override
  String get queueRefresh => 'تحديث';

  @override
  String get activityTitle => 'نشاطي';

  @override
  String get activityEmpty => 'لا توجد عمليات بعد.';

  @override
  String get activityRefresh => 'تحديث';

  @override
  String outboxPendingCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n عمليات',
      one: 'مسح واحد',
    );
    return '$_temp0 بانتظار المزامنة';
  }

  @override
  String get outboxFlushNow => 'مزامنة الآن';

  @override
  String outboxFlushFailed(String detail) {
    return 'فشلت المزامنة: $detail';
  }

  @override
  String get settingsTitle => 'الإعدادات';

  @override
  String get settingsBaseUrl => 'عنوان الـ API';

  @override
  String get settingsBaseUrlSubtitle => 'حدّث هذا فقط لو طلبت الإدارة.';

  @override
  String get settingsBaseUrlSaved => 'تم الحفظ.';

  @override
  String get eventReceivedAtHub => 'استلام في المركز';

  @override
  String get eventInTransitInternal => 'إرسال (نقل داخلي)';

  @override
  String get eventReceivedAtBranch => 'استلام في الفرع';

  @override
  String get eventReadyForPickup => 'جاهز للاستلام';

  @override
  String get eventDeliveredToCustomer => 'تم التسليم للعميل';

  @override
  String get eventReturnedToHub => 'إرجاع إلى المركز';

  @override
  String get eventLost => 'تحديد كمفقود';

  @override
  String get eventDamaged => 'تحديد كتالف';
}
