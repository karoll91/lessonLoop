import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/theme.dart';
import '../../ui/widgets.dart';
import 'board_controller.dart';

void openPairing(BuildContext context) {
  Navigator.push(context, MaterialPageRoute<void>(builder: (_) => const PairScreen()));
}

/// The camera works on phones and in the browser, not on desktop builds.
bool get cameraAvailable => kIsWeb || defaultTargetPlatform == TargetPlatform.android || defaultTargetPlatform == TargetPlatform.iOS;

/// D1 phone side — scan the board's QR or type its 6-digit code.
class PairScreen extends ConsumerStatefulWidget {
  const PairScreen({super.key});

  @override
  ConsumerState<PairScreen> createState() => _PairScreenState();
}

class _PairScreenState extends ConsumerState<PairScreen> {
  final _code = TextEditingController();
  bool _busy = false;
  bool _camera = cameraAvailable;
  String? _error;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _pair(String raw) async {
    final code = parsePairCode(raw);
    if (code == null) {
      setState(() => _error = tr('Kod 6 ta raqamdan iborat bo\'lishi kerak'));
      return;
    }
    if (_busy) {
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(boardProvider.notifier).pair(code);
      HapticFeedback.mediumImpact();
    } on ApiException catch (error) {
      setState(() => _error = error.userMessage);
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final board = ref.watch(boardProvider);
    final c = context.colors;

    if (board != null) {
      return Scaffold(
        appBar: AppBar(title: Text(tr('Doska'))),
        body: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Center(
              child: Container(
                width: 88,
                height: 88,
                decoration: BoxDecoration(color: c.passSoft, shape: BoxShape.circle),
                child: Icon(Icons.check_rounded, color: c.pass, size: 48),
              ),
            ),
            const SizedBox(height: 16),
            Text(tr('Doska ulandi'), textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineSmall),
            const SizedBox(height: 6),
            Text(
              tr('Endi doskadagi ekranni shu telefondan boshqarasiz. Dars boshlanganda savollar doskada chiqadi.'),
              textAlign: TextAlign.center,
              style: TextStyle(color: c.ink2),
            ),
            const SizedBox(height: 24),
            Section(
              children: [
                ListTile(
                  title: Text(tr('Doska')),
                  trailing: Text(board.name ?? '#${board.boardId}', style: const TextStyle(fontWeight: FontWeight.w700)),
                ),
                ListTile(
                  title: Text(tr('Holat')),
                  trailing: Chip2(tr('Sinxron'), tone: Tone.pass),
                ),
              ],
            ),
            const SizedBox(height: 24),
            FilledButton(onPressed: () => Navigator.pop(context), child: Text(tr('Tayyor'))),
            const SizedBox(height: 8),
            OutlinedButton(onPressed: () => ref.read(boardProvider.notifier).release(), child: Text(tr('Doskani uzish'))),
          ],
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: Text(tr('Doskaga ulash'))),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          if (_camera) ...[
            ClipRRect(
              borderRadius: BorderRadius.circular(20),
              child: AspectRatio(
                aspectRatio: 1,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    MobileScanner(
                      onDetect: (capture) {
                        final value = capture.barcodes.map((barcode) => barcode.rawValue).whereType<String>().firstOrNull;
                        if (value != null && !_busy) {
                          _pair(value);
                        }
                      },
                      errorBuilder: (context, error) {
                        WidgetsBinding.instance.addPostFrameCallback((_) {
                          if (mounted) {
                            setState(() => _camera = false);
                          }
                        });
                        return const SizedBox.shrink();
                      },
                    ),
                    Center(
                      child: Container(
                        width: 220,
                        height: 220,
                        decoration: BoxDecoration(
                          border: Border.all(color: Colors.white, width: 3),
                          borderRadius: BorderRadius.circular(24),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
            Text(
              tr('Doskadagi QR kodni ramkaga joylashtiring'),
              textAlign: TextAlign.center,
              style: TextStyle(color: c.ink2),
            ),
            const SizedBox(height: 24),
            Row(
              children: [
                const Expanded(child: Divider()),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  child: Text(tr('yoki'), style: TextStyle(color: c.ink3)),
                ),
                const Expanded(child: Divider()),
              ],
            ),
            const SizedBox(height: 16),
          ] else ...[
            Banner2(tr("Kamera yo'q — doskadagi 6 xonali kodni kiriting.")),
            const SizedBox(height: 16),
          ],
          TextField(
            controller: _code,
            keyboardType: TextInputType.number,
            maxLength: 6,
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w800, letterSpacing: 10),
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            onChanged: (value) {
              if (value.length == 6) {
                _pair(value);
              }
            },
            decoration: InputDecoration(labelText: tr('6 xonali kod'), counterText: '', errorText: _error),
          ),
          const SizedBox(height: 16),
          FilledButton(onPressed: _busy ? null : () => _pair(_code.text), child: Text(_busy ? tr('Ulanmoqda…') : tr('Ulash'))),
          const SizedBox(height: 16),
          Text(
            tr("Doskadagi kompyuterda admin paneldagi «Doska rejimi» ochiladi. Kod 5 daqiqa amal qiladi."),
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }
}
