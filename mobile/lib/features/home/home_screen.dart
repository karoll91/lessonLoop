import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/models.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../ui/widgets.dart';
import '../board/board_controller.dart';
import '../board/pair_screen.dart';
import '../lesson/lesson_run_screen.dart';
import '../lesson/lesson_setup_screen.dart';

/// M3 — today's lessons, the lesson in progress and quick figures.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authProvider).value;
    final dashboard = ref.watch(dashboardProvider);
    final now = DateTime.now();
    final greeting = now.hour < 12 ? 'Xayrli tong!' : (now.hour < 18 ? 'Xayrli kun!' : 'Xayrli kech!');

    return Scaffold(
      appBar: AppBar(
        toolbarHeight: 72,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(formatLongDate(now), style: Theme.of(context).textTheme.bodySmall),
            Text(greeting, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
          ],
        ),
        actions: [if (user != null) Padding(padding: const EdgeInsets.only(right: 16), child: Avatar(user.name, size: 44))],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => Navigator.push(context, MaterialPageRoute<void>(builder: (_) => const LessonSetupScreen())),
        icon: const Icon(Icons.play_arrow_rounded),
        label: const Text('Yangi dars'),
      ),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(dashboardProvider.future),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 96),
          children: [
            const BoardStatusCard(),
            const SizedBox(height: 12),
            ...dashboard.when(
              loading: () => [const _HomeSkeleton()],
              error: (error, _) => [
                if (error is ApiException && error.isOffline)
                  Banner2(
                    "Internet yo'q yoki server ishlamayapti",
                    tone: Tone.warn,
                    icon: Icons.wifi_off,
                    action: 'Qayta',
                    onAction: () => ref.invalidate(dashboardProvider),
                  )
                else
                  ErrorView(error: error, onRetry: () => ref.invalidate(dashboardProvider)),
              ],
              data: (data) => _content(context, ref, data),
            ),
          ],
        ),
      ),
    );
  }

  List<Widget> _content(BuildContext context, WidgetRef ref, Dashboard data) {
    final c = context.colors;
    final running = data.runningLesson;

    return [
      if (running != null) ...[
        Card(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
            side: BorderSide(color: c.accent, width: 1.5),
          ),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    const Chip2('Davom etmoqda', tone: Tone.accent),
                    const Spacer(),
                    Text('${formatTime(running.startedAt)} · ${running.askedCount} / ${running.questionCount}', style: Theme.of(context).textTheme.bodySmall),
                  ],
                ),
                const SizedBox(height: 10),
                Text('${running.group} · ${running.subject}', style: Theme.of(context).textTheme.titleMedium),
                Text('${running.sequence}-dars', style: Theme.of(context).textTheme.bodySmall),
                const SizedBox(height: 10),
                ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: LinearProgressIndicator(
                    value: running.questionCount == 0 ? 0 : running.askedCount / running.questionCount,
                    minHeight: 8,
                    backgroundColor: c.line,
                  ),
                ),
                const SizedBox(height: 12),
                FilledButton(onPressed: () => openLesson(context, ref, running.id), child: const Text('Davom ettirish')),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
      ],
      Row(
        children: [
          Expanded(
            child: Kpi(label: "So'ralmagan", value: '${data.neverAsked}'),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Kpi(label: 'Qarzdorlar', value: '${data.debtors}', color: c.warn),
          ),
        ],
      ),
      const SizedBox(height: 20),
      Text('Bugungi darslar', style: Theme.of(context).textTheme.titleMedium),
      const SizedBox(height: 10),
      if (data.todayLessons.isEmpty)
        Card(
          child: EmptyState(
            icon: Icons.event_available_outlined,
            title: "Bugun hali dars bo'lmadi",
            text: data.groups == 0 ? "Sizga hali guruh biriktirilmagan. Admin bilan bog'laning." : "Istalgan guruh bilan darsni boshlashingiz mumkin.",
          ),
        )
      else
        Section(
          children: [
            for (final lesson in data.todayLessons)
              ListTile(
                onTap: () => openLesson(context, ref, lesson.id),
                leading: Text(formatTime(lesson.startedAt), style: const TextStyle(fontWeight: FontWeight.w800)),
                title: Text('${lesson.group} · ${lesson.subject}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                subtitle: Text(
                  "${lesson.askedCount} / ${lesson.questionCount} savol${lesson.averageScore == null ? '' : ' · o\'rtacha ${lesson.averageScore}'}",
                ),
                trailing: lesson.status == 'finished' ? const Chip2('Tugadi', tone: Tone.pass) : const Chip2('Faol', tone: Tone.accent),
              ),
          ],
        ),
    ];
  }
}

/// Open a lesson: the run screen while it is going, the summary when finished.
void openLesson(BuildContext context, WidgetRef ref, int lessonId) {
  Navigator.push(context, MaterialPageRoute<void>(builder: (_) => LessonRunScreen(lessonId: lessonId))).then((_) => ref.invalidate(dashboardProvider));
}

/// The board connection on the home screen: pair, or which board is in use.
class BoardStatusCard extends ConsumerWidget {
  const BoardStatusCard({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final c = context.colors;
    final board = ref.watch(boardProvider);

    return Card(
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
        leading: Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(color: board == null ? c.surface2 : c.accentSoft, borderRadius: BorderRadius.circular(12)),
          child: Icon(Icons.cast_connected_outlined, color: board == null ? c.ink2 : c.accent),
        ),
        title: Text(board == null ? 'Doska ulanmagan' : 'Doska ulandi', style: const TextStyle(fontWeight: FontWeight.w700)),
        subtitle: Text(board == null ? 'Savollar va baholarni katta ekranda ko\'rsatish uchun ulang' : (board.name ?? 'Doska #${board.boardId}')),
        trailing: board == null
            ? FilledButton.tonal(onPressed: () => openPairing(context), child: const Text('Ulash'))
            : TextButton(
                onPressed: () async {
                  if (await confirm(context, title: 'Doskani uzasizmi?', text: "Doska yana QR kod ekraniga qaytadi.", action: 'Uzish')) {
                    await ref.read(boardProvider.notifier).release();
                  }
                },
                child: const Text('Uzish'),
              ),
      ),
    );
  }
}

class _HomeSkeleton extends StatelessWidget {
  const _HomeSkeleton();

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    Widget block(double height) => Container(
      height: height,
      decoration: BoxDecoration(color: c.surface2, borderRadius: BorderRadius.circular(16)),
    );
    return Column(
      children: [
        block(150),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(child: block(76)),
            const SizedBox(width: 10),
            Expanded(child: block(76)),
          ],
        ),
        const SizedBox(height: 20),
        block(140),
      ],
    );
  }
}
