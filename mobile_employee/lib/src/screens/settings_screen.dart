import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../l10n/app_localizations.dart';
import '../state/providers.dart';

/// Settings — currently a single field for the API base URL so ops can
/// retarget the app to a staging server without rebuilding. The value is
/// persisted via [ApiBaseUrlController] and survives app restarts.
class SettingsScreen extends ConsumerStatefulWidget {
  const SettingsScreen({super.key});

  @override
  ConsumerState<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends ConsumerState<SettingsScreen> {
  late final TextEditingController _baseUrlCtl;
  bool _initialized = false;

  @override
  void initState() {
    super.initState();
    _baseUrlCtl = TextEditingController();
  }

  @override
  void dispose() {
    _baseUrlCtl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final baseUrlAsync = ref.watch(apiBaseUrlProvider);

    // Seed the field exactly once with the hydrated value. Subsequent
    // edits stay in the TextEditingController until the user saves.
    if (!_initialized) {
      baseUrlAsync.whenData((value) {
        _baseUrlCtl.text = value;
        _initialized = true;
      });
    }

    return Scaffold(
      appBar: AppBar(title: Text(l.settingsTitle)),
      body: baseUrlAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text('$e')),
        data: (_) => ListView(
          padding: const EdgeInsets.all(16),
          children: <Widget>[
            Text(l.settingsBaseUrl,
                style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 4),
            Text(
              l.settingsBaseUrlSubtitle,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Colors.black54,
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _baseUrlCtl,
              keyboardType: TextInputType.url,
              autocorrect: false,
              enableSuggestions: false,
              decoration: const InputDecoration(
                hintText: 'https://api.example.com',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.link),
              ),
            ),
            const SizedBox(height: 16),
            FilledButton.icon(
              icon: const Icon(Icons.save),
              label: const Text('Save'),
              onPressed: () async {
                await ref
                    .read(apiBaseUrlProvider.notifier)
                    .setBaseUrl(_baseUrlCtl.text);
                if (!context.mounted) return;
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text(l.settingsBaseUrlSaved)),
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}
