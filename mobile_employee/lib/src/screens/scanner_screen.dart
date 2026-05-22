import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../l10n/app_localizations.dart';

/// Live QR scanner. On detection, navigates to /scan/review with the
/// extracted sticker id. Includes a manual-entry fallback dialog for the
/// case where the camera can't read a damaged sticker.
class ScannerScreen extends StatefulWidget {
  const ScannerScreen({super.key});

  @override
  State<ScannerScreen> createState() => _ScannerScreenState();
}

class _ScannerScreenState extends State<ScannerScreen> {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.normal,
    formats: const [BarcodeFormat.qrCode],
  );
  bool _handled = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture cap) {
    if (_handled) return;
    for (final b in cap.barcodes) {
      final raw = b.rawValue;
      if (raw == null) continue;
      final stickerId = _extractStickerId(raw);
      if (stickerId == null) continue;
      _handled = true;
      context.go('/scan/review?sticker=$stickerId');
      return;
    }
  }

  /// Pulls the ULID out of `shipflow://qr/<ULID>` or accepts a bare ULID.
  static String? _extractStickerId(String raw) {
    const scheme = 'shipflow://qr/';
    if (raw.startsWith(scheme)) {
      final candidate = raw.substring(scheme.length);
      return _isUlid(candidate) ? candidate : null;
    }
    return _isUlid(raw) ? raw : null;
  }

  static bool _isUlid(String s) => s.length == 26 && RegExp(r'^[0-9A-HJKMNP-TV-Z]{26}$').hasMatch(s);

  Future<void> _manualEntry() async {
    final controller = TextEditingController();
    final l = AppLocalizations.of(context)!;
    final result = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(l.scannerManualEntry),
        content: TextField(
          controller: controller,
          autofocus: true,
          textCapitalization: TextCapitalization.characters,
          decoration: const InputDecoration(hintText: '26-char ULID'),
          onSubmitted: (v) => Navigator.of(ctx).pop(v.trim()),
        ),
        actions: <Widget>[
          TextButton(onPressed: () => Navigator.of(ctx).pop(null), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.of(ctx).pop(controller.text.trim()), child: const Text('OK')),
        ],
      ),
    );
    if (!mounted || result == null || result.isEmpty) return;
    if (!_isUlid(result)) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppLocalizations.of(context)!.scannerInvalidSticker)),
      );
      return;
    }
    if (!mounted) return;
    context.go('/scan/review?sticker=$result');
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    return Scaffold(
      appBar: AppBar(
        title: Text(l.homeStartScan),
        actions: <Widget>[
          IconButton(
            tooltip: l.scannerTorch,
            icon: const Icon(Icons.flash_on),
            onPressed: () => _controller.toggleTorch(),
          ),
          IconButton(
            tooltip: l.scannerManualEntry,
            icon: const Icon(Icons.keyboard),
            onPressed: _manualEntry,
          ),
        ],
      ),
      body: Stack(
        children: <Widget>[
          MobileScanner(
            controller: _controller,
            onDetect: _onDetect,
          ),
          Positioned(
            left: 0, right: 0, bottom: 24,
            child: Center(
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                decoration: BoxDecoration(
                  color: Colors.black54,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  l.scannerHint,
                  style: const TextStyle(color: Colors.white),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
