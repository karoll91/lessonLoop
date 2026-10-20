import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/models.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../ui/widgets.dart';
import '../auth/auth_screens.dart';
import '../board/board_controller.dart';
import '../board/pair_screen.dart';

const _ruleLabels = {
  'pass_score': ("O'tish bali", 'ball'),
  'cooldown_lessons': ('Dam olish', 'dars'),
  'debtor_weight': ('Qarzdor vazni', '×'),
  'never_asked_weight': ("So'ralmagan vazni", '×'),
  'default_question_count': ('Savollar soni', 'ta'),
  'grade_edit_hours': ('Bahoni tuzatish muddati', 'soat'),
};

/// M13 — profile, appearance, random rules, server, sign out.
class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authProvider).value;
    final settings = ref.watch(settingsProvider);
    final rules = ref.watch(rulesProvider);
    final board = ref.watch(boardProvider);
    final c = context.colors;

    return Scaffold(
      appBar: AppBar(title: const Text('Sozlamalar')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 32),
        children: [
          if (user != null)
            Card(
              child: ListTile(
                contentPadding: const EdgeInsets.all(14),
                leading: Avatar(user.name, size: 48),
                title: Text(user.name, style: Theme.of(context).textTheme.titleMedium),
                subtitle: Text('${user.login} · ${user.isAdmin ? 'Admin' : "O'qituvchi"}'),
              ),
            ),
          const SizedBox(height: 20),
          _title(context, "Ko'rinish"),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: ThemeModeSelector(mode: settings.themeMode, onChanged: (mode) => ref.read(settingsProvider.notifier).setThemeMode(mode)),
            ),
          ),
          const SizedBox(height: 20),
          _title(context, 'Random qoidalari'),
          rules.when(
            loading: () => const Loading(),
            error: (error, _) => Banner2(errorText(error), tone: Tone.danger, action: 'Qayta', onAction: () => ref.invalidate(rulesProvider)),
            data: (data) => Section(
              children: [
                for (final entry in _ruleLabels.entries)
                  ListTile(
                    title: Text(entry.value.$1),
                    subtitle: data.values[entry.key] != data.defaults[entry.key] ? Text('Standart: ${data.defaults[entry.key]}') : null,
                    trailing: Text('${data.values[entry.key] ?? '—'} ${entry.value.$2}', style: const TextStyle(fontWeight: FontWeight.w800)),
                    onTap: user?.isAdmin == true ? () => _editRule(context, ref, data, entry.key) : null,
                  ),
              ],
            ),
          ),
          if (user?.isAdmin != true)
            Padding(
              padding: const EdgeInsets.only(top: 6, left: 4),
              child: Text("Qoidalarni faqat admin o'zgartiradi.", style: Theme.of(context).textTheme.bodySmall),
            ),
          const SizedBox(height: 20),
          _title(context, 'Ulanish'),
          Section(
            children: [
              ListTile(
                leading: const Icon(Icons.dns_outlined),
                title: const Text('Server manzili'),
                subtitle: Text(settings.serverUrl),
                onTap: () => showServerDialog(context, ref),
              ),
              ListTile(
                leading: const Icon(Icons.cast_outlined),
                title: const Text('Doska'),
                subtitle: Text(board == null ? 'Ulanmagan' : (board.name ?? 'Doska #${board.boardId}')),
                onTap: () => openPairing(context),
              ),
            ],
          ),
          const SizedBox(height: 20),
          _title(context, 'Hisob'),
          Section(
            children: [
              ListTile(
                leading: const Icon(Icons.lock_outline),
                title: const Text("Parolni o'zgartirish"),
                onTap: () => Navigator.push(context, MaterialPageRoute<void>(builder: (_) => const ChangePasswordScreen())),
              ),
              ListTile(
                leading: Icon(Icons.logout, color: c.danger),
                title: Text(
                  'Chiqish',
                  style: TextStyle(color: c.danger, fontWeight: FontWeight.w700),
                ),
                onTap: () async {
                  if (await confirm(
                    context,
                    title: 'Chiqasizmi?',
                    text: "Telefondagi sessiya yopiladi. Baholar serverda saqlangan.",
                    action: 'Chiqish',
                    danger: true,
                  )) {
                    await ref.read(boardProvider.notifier).release().catchError((_) {});
                    await ref.read(authProvider.notifier).logout();
                  }
                },
              ),
            ],
          ),
          const SizedBox(height: 24),
          Center(child: Text('LessonLoop 1.0.0', style: Theme.of(context).textTheme.bodySmall)),
        ],
      ),
    );
  }

  Widget _title(BuildContext context, String text) => Padding(
    padding: const EdgeInsets.only(left: 4, bottom: 8),
    child: Text(text, style: Theme.of(context).textTheme.titleMedium),
  );

  Future<void> _editRule(BuildContext context, WidgetRef ref, Rules rules, String key) async {
    final (min, max) = rules.limits[key] ?? (0, 100);
    var value = rules.values[key] ?? min;
    final result = await showModalBottomSheet<int>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setState) => SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(_ruleLabels[key]!.$1, style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    IconButton.outlined(onPressed: value > min ? () => setState(() => value--) : null, icon: const Icon(Icons.remove)),
                    SizedBox(
                      width: 96,
                      child: Text(
                        '$value',
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontSize: 36, fontWeight: FontWeight.w800),
                      ),
                    ),
                    IconButton.outlined(onPressed: value < max ? () => setState(() => value++) : null, icon: const Icon(Icons.add)),
                  ],
                ),
                const SizedBox(height: 8),
                Text('$min – $max · standart ${rules.defaults[key]}', textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodySmall),
                const SizedBox(height: 16),
                FilledButton(onPressed: () => Navigator.pop(context, value), child: const Text('Saqlash')),
              ],
            ),
          ),
        ),
      ),
    );
    if (result == null || result == rules.values[key]) {
      return;
    }
    try {
      await ref.read(apiProvider).patch('/settings', {key: result});
      ref.invalidate(rulesProvider);
      if (context.mounted) {
        showMessage(context, "Qoida o'zgartirildi. Keyingi tanlovdan boshlab amal qiladi.");
      }
    } on ApiException catch (error) {
      if (context.mounted) {
        showMessage(context, error.userMessage, error: true);
      }
    }
  }
}
