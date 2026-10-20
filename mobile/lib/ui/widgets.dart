import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/theme.dart';

enum Tone { neutral, accent, pass, warn, danger, info }

extension ToneColors on Tone {
  (Color, Color) colors(AppColors c) => switch (this) {
    Tone.neutral => (c.surface2, c.ink2),
    Tone.accent => (c.accentSoft, c.accent),
    Tone.pass => (c.passSoft, c.pass),
    Tone.warn => (c.warnSoft, c.warn),
    Tone.danger => (c.dangerSoft, c.danger),
    Tone.info => (c.infoSoft, c.info),
  };
}

class Chip2 extends StatelessWidget {
  const Chip2(this.text, {super.key, this.tone = Tone.neutral});

  final String text;
  final Tone tone;

  @override
  Widget build(BuildContext context) {
    final (bg, fg) = tone.colors(context.colors);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(999)),
      child: Text(
        text,
        style: TextStyle(color: fg, fontSize: 12, fontWeight: FontWeight.w700),
      ),
    );
  }
}

/// A one-line notice above content (W/M «Banner» component).
class Banner2 extends StatelessWidget {
  const Banner2(this.text, {super.key, this.tone = Tone.info, this.icon, this.action, this.onAction});

  final String text;
  final Tone tone;
  final IconData? icon;
  final String? action;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final (bg, fg) = tone.colors(context.colors);
    return Semantics(
      liveRegion: true,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(12)),
        child: Row(
          children: [
            Icon(icon ?? (tone == Tone.danger || tone == Tone.warn ? Icons.error_outline : Icons.info_outline), color: fg, size: 20),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                text,
                style: TextStyle(color: fg, fontWeight: FontWeight.w600, fontSize: 14),
              ),
            ),
            if (action != null)
              TextButton(
                onPressed: onAction,
                style: TextButton.styleFrom(foregroundColor: fg),
                child: Text(action!),
              ),
          ],
        ),
      ),
    );
  }
}

class EmptyState extends StatelessWidget {
  const EmptyState({super.key, required this.icon, required this.title, this.text, this.action, this.onAction});

  final IconData icon;
  final String title;
  final String? text;
  final String? action;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 64,
            height: 64,
            decoration: BoxDecoration(color: c.accentSoft, borderRadius: BorderRadius.circular(20)),
            child: Icon(icon, color: c.accent, size: 30),
          ),
          const SizedBox(height: 16),
          Text(title, textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleMedium),
          if (text != null) ...[
            const SizedBox(height: 6),
            Text(
              text!,
              textAlign: TextAlign.center,
              style: TextStyle(color: c.ink2),
            ),
          ],
          if (action != null) ...[const SizedBox(height: 16), FilledButton(onPressed: onAction, child: Text(action!))],
        ],
      ),
    );
  }
}

/// Error from a failed load, with retry.
class ErrorView extends StatelessWidget {
  const ErrorView({super.key, required this.error, required this.onRetry});

  final Object error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final offline = error is ApiException && (error as ApiException).isOffline;
    return Center(
      child: EmptyState(
        icon: offline ? Icons.wifi_off : Icons.error_outline,
        title: offline ? "Serverga ulanib bo'lmadi" : "Ma'lumot yuklanmadi",
        text: errorText(error),
        action: 'Qayta urinish',
        onAction: onRetry,
      ),
    );
  }
}

class Loading extends StatelessWidget {
  const Loading({super.key});

  @override
  Widget build(BuildContext context) => const Center(
    child: Padding(padding: EdgeInsets.all(32), child: CircularProgressIndicator()),
  );
}

class ScoreText extends StatelessWidget {
  const ScoreText(this.score, {super.key, this.size = 15, this.passScore = 60});

  final int? score;
  final double size;
  final int passScore;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Text(
      score?.toString() ?? '—',
      style: TextStyle(
        color: c.score(score, passScore: passScore),
        fontWeight: FontWeight.w800,
        fontSize: size,
      ),
    );
  }
}

class Avatar extends StatelessWidget {
  const Avatar(this.name, {super.key, this.size = 40, this.tone = Tone.accent});

  final String name;
  final double size;
  final Tone tone;

  @override
  Widget build(BuildContext context) {
    final (bg, fg) = tone.colors(context.colors);
    final initials = name.trim().split(RegExp(r'\s+')).where((part) => part.isNotEmpty).take(2).map((part) => part[0].toUpperCase()).join();
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(color: bg, shape: BoxShape.circle),
      child: Text(
        initials,
        style: TextStyle(color: fg, fontWeight: FontWeight.w800, fontSize: size * 0.36),
      ),
    );
  }
}

/// A rounded group of list rows divided by lines.
class Section extends StatelessWidget {
  const Section({super.key, required this.children, this.padding = EdgeInsets.zero});

  final List<Widget> children;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: padding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            for (var index = 0; index < children.length; index++) ...[if (index > 0) const Divider(), children[index]],
          ],
        ),
      ),
    );
  }
}

class Kpi extends StatelessWidget {
  const Kpi({super.key, required this.label, required this.value, this.color});

  final String label;
  final String value;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: 4),
            Text(
              value,
              style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800, color: color ?? context.colors.ink),
            ),
          ],
        ),
      ),
    );
  }
}

void showMessage(BuildContext context, String text, {bool error = false}) {
  final c = context.colors;
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(text), backgroundColor: error ? c.danger : null));
}

Future<bool> confirm(BuildContext context, {required String title, required String text, required String action, bool danger = false}) async {
  final result = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      title: Text(title),
      content: Text(text),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Bekor')),
        FilledButton(
          style: danger ? FilledButton.styleFrom(backgroundColor: context.colors.danger) : null,
          onPressed: () => Navigator.pop(context, true),
          child: Text(action),
        ),
      ],
    ),
  );
  return result ?? false;
}

const _months = ['yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul', 'avgust', 'sentabr', 'oktabr', 'noyabr', 'dekabr'];
const _weekdays = ['dushanba', 'seshanba', 'chorshanba', 'payshanba', 'juma', 'shanba', 'yakshanba'];

String _pad(int value) => value.toString().padLeft(2, '0');

String formatTime(DateTime? date) => date == null ? '—' : '${_pad(date.hour)}:${_pad(date.minute)}';

String formatDate(DateTime? date) => date == null ? '—' : '${_pad(date.day)}.${_pad(date.month)}.${date.year}';

String formatDateTime(DateTime? date) => date == null ? '—' : '${_pad(date.day)}.${_pad(date.month)} ${formatTime(date)}';

/// «Dushanba, 19-oktabr»
String formatLongDate(DateTime date) {
  final weekday = _weekdays[date.weekday - 1];
  return '${weekday[0].toUpperCase()}${weekday.substring(1)}, ${date.day}-${_months[date.month - 1]}';
}
