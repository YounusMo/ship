import 'package:flutter/material.dart';
import 'package:shipflow_client/l10n/app_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../models/client_device.dart';
import '../state/auth_provider.dart';
import '../state/biometric_provider.dart';
import '../state/devices_provider.dart';
import '../state/locale_provider.dart';
import '../state/notification_prefs_provider.dart';

class SettingsScreen extends ConsumerStatefulWidget {
  const SettingsScreen({super.key});

  @override
  ConsumerState<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends ConsumerState<SettingsScreen> {
  bool? _bioEnabled;
  bool? _bioAvailable;

  @override
  void initState() {
    super.initState();
    // Probe device capability + saved opt-in on first build so the switch
    // doesn't flash from "off" → "on" once the future resolves.
    final controller = ref.read(biometricControllerProvider);
    Future.wait(<Future<bool>>[controller.isAvailable(), controller.isEnabled()])
        .then((r) {
      if (!mounted) return;
      setState(() {
        _bioAvailable = r[0];
        _bioEnabled   = r[1];
      });
    });
  }

  @override
  Widget build(BuildContext context) {
    final l         = AppLocalizations.of(context)!;
    final client    = ref.watch(authProvider).valueOrNull;
    final localeAsy = ref.watch(localeProvider);
    final devices   = ref.watch(devicesProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l.settingsTitle)),
      body: ListView(
        children: <Widget>[
          // Account section ------------------------------------------------
          _SectionHeader(text: l.settingsAccount),
          if (client != null) ...<Widget>[
            ListTile(title: Text(client.name), subtitle: Text(client.code)),
            if (client.email != null) ListTile(
              leading: const Icon(Icons.email_outlined),
              title: Text(client.email!),
            ),
          ],

          // Preferences ----------------------------------------------------
          _SectionHeader(text: l.settingsPreferences),
          localeAsy.when(
            loading: () => const ListTile(title: LinearProgressIndicator()),
            error: (_, __) => const SizedBox.shrink(),
            data: (locale) => ListTile(
              leading: const Icon(Icons.language_outlined),
              title: Text(l.settingsLanguage),
              subtitle: Text(_languageLabel(locale)),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => _pickLanguage(context, locale),
            ),
          ),

          // Notifications --------------------------------------------------
          _SectionHeader(text: l.settingsNotifications),
          _NotificationPrefsSection(),

          // Security -------------------------------------------------------
          _SectionHeader(text: l.settingsSecurity),
          SwitchListTile(
            secondary: const Icon(Icons.fingerprint),
            title: Text(l.settingsBiometricUnlock),
            subtitle: Text(
              (_bioAvailable ?? false)
                  ? l.settingsBiometricSubtitle
                  : l.settingsBiometricUnavailable,
            ),
            value: (_bioEnabled ?? false) && (_bioAvailable ?? false),
            onChanged: (_bioAvailable ?? false)
                ? (v) async {
                    final controller = ref.read(biometricControllerProvider);
                    await controller.setEnabled(v);
                    if (v) {
                      // Refresh the cached prompt string in case the user
                      // changed language since their last login — the next
                      // cold start biometric gate reads from this cache.
                      await controller.cacheLocalizedReason(l.biometricUnlockReason);
                    }
                    setState(() => _bioEnabled = v);
                  }
                : null,
          ),

          // Devices --------------------------------------------------------
          _SectionHeader(text: l.settingsDevices),
          devices.when(
            loading: () => const Padding(
              padding: EdgeInsets.all(16),
              child: Center(child: CircularProgressIndicator()),
            ),
            error: (e, _) => Padding(
              padding: const EdgeInsets.all(16),
              child: Center(child: Text('$e')),
            ),
            data: (list) => list.isEmpty
                ? Padding(
                    padding: const EdgeInsets.all(16),
                    child: Center(child: Text(l.settingsNoDevices)),
                  )
                : Column(
                    children: <Widget>[
                      for (final d in list) _DeviceTile(device: d, onRevoke: () => _revoke(context, d)),
                    ],
                  ),
          ),

          const Divider(),

          // Sign out -------------------------------------------------------
          ListTile(
            leading: const Icon(Icons.logout, color: Colors.red),
            title: Text(l.signOut, style: const TextStyle(color: Colors.red)),
            onTap: () async {
              await ref.read(authProvider.notifier).logout();
            },
          ),
          const SizedBox(height: 24),
        ],
      ),
    );
  }

  String _languageLabel(Locale? locale) {
    if (locale == null) return AppLocalizations.of(context)!.settingsLanguageSystem;
    return switch (locale.languageCode) {
      'en' => 'English',
      'ar' => 'العربية',
      _    => locale.languageCode,
    };
  }

  Future<void> _pickLanguage(BuildContext context, Locale? current) async {
    final l = AppLocalizations.of(context)!;
    final picked = await showModalBottomSheet<Object?>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            ListTile(
              title: Text(l.settingsLanguageSystem),
              trailing: current == null ? const Icon(Icons.check) : null,
              onTap: () => Navigator.of(ctx).pop('system'),
            ),
            ListTile(
              title: const Text('English'),
              trailing: current?.languageCode == 'en' ? const Icon(Icons.check) : null,
              onTap: () => Navigator.of(ctx).pop('en'),
            ),
            ListTile(
              title: const Text('العربية'),
              trailing: current?.languageCode == 'ar' ? const Icon(Icons.check) : null,
              onTap: () => Navigator.of(ctx).pop('ar'),
            ),
            const SizedBox(height: 12),
          ],
        ),
      ),
    );
    if (picked == null) return;
    final newLocale = picked == 'system' ? null : Locale(picked as String);
    await ref.read(localeProvider.notifier).setLocale(newLocale);
  }

  Future<void> _revoke(BuildContext context, ClientDevice device) async {
    final l = AppLocalizations.of(context)!;
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(l.settingsRevokeTitle),
        content: Text(l.settingsRevokeConfirm(device.displayName)),
        actions: <Widget>[
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: Text(l.cancel)),
          FilledButton(onPressed: () => Navigator.of(ctx).pop(true), child: Text(l.settingsRevoke)),
        ],
      ),
    );
    if (confirm == true) {
      await ref.read(devicesProvider.notifier).revoke(device.id);
    }
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({required this.text});
  final String text;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 24, 16, 8),
    child: Text(
      text.toUpperCase(),
      style: Theme.of(context).textTheme.labelSmall?.copyWith(
        color: Colors.black54,
        fontWeight: FontWeight.w700,
        letterSpacing: 1.2,
      ),
    ),
  );
}

/// Three switches gating the three notification categories the backend
/// fans out (ClientTransactionPosted / ShipmentStatusChanged / ReceiptIssued).
/// Server-side via() returns [] when a category is off, so a mute kills
/// both the in-app feed row AND the FCM push.
class _NotificationPrefsSection extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l     = AppLocalizations.of(context)!;
    final prefs = ref.watch(notificationPrefsProvider);

    return prefs.when(
      loading: () => const Padding(
        padding: EdgeInsets.all(16),
        child: Center(child: CircularProgressIndicator()),
      ),
      error: (e, _) => Padding(
        padding: const EdgeInsets.all(16),
        child: Text('$e'),
      ),
      data: (p) => Column(
        children: <Widget>[
          SwitchListTile(
            secondary: const Icon(Icons.account_balance_wallet_outlined),
            title: Text(l.settingsNotifyTransactions),
            subtitle: Text(l.settingsNotifyTransactionsSub),
            value: p.transactions,
            onChanged: (v) => ref.read(notificationPrefsProvider.notifier).setTransactions(v),
          ),
          SwitchListTile(
            secondary: const Icon(Icons.local_shipping_outlined),
            title: Text(l.settingsNotifyShipments),
            subtitle: Text(l.settingsNotifyShipmentsSub),
            value: p.shipments,
            onChanged: (v) => ref.read(notificationPrefsProvider.notifier).setShipments(v),
          ),
          SwitchListTile(
            secondary: const Icon(Icons.receipt_outlined),
            title: Text(l.settingsNotifyReceipts),
            subtitle: Text(l.settingsNotifyReceiptsSub),
            value: p.receipts,
            onChanged: (v) => ref.read(notificationPrefsProvider.notifier).setReceipts(v),
          ),
        ],
      ),
    );
  }
}

class _DeviceTile extends StatelessWidget {
  const _DeviceTile({required this.device, required this.onRevoke});
  final ClientDevice device;
  final VoidCallback onRevoke;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final platformIcon = switch (device.platform) {
      'ios'     => Icons.phone_iphone,
      'android' => Icons.phone_android,
      _         => Icons.devices_other,
    };
    final seen = device.lastSeenAt != null
        ? l.settingsLastSeen(DateFormat.yMMMd().add_jm().format(device.lastSeenAt!))
        : '';
    return ListTile(
      leading: Icon(platformIcon),
      title: Text(device.displayName),
      subtitle: Text(<String>[
        if (device.osVersion != null)  device.osVersion!,
        if (device.appVersion != null) 'v${device.appVersion}',
        seen,
      ].where((s) => s.isNotEmpty).join(' · ')),
      trailing: IconButton(
        tooltip: l.settingsRevoke,
        icon: const Icon(Icons.logout, color: Colors.red),
        onPressed: onRevoke,
      ),
    );
  }
}
