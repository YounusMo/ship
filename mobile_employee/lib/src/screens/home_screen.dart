import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../l10n/app_localizations.dart';
import '../state/auth_state.dart';
import '../state/providers.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context)!;
    final auth = ref.watch(authControllerProvider).value;
    if (auth is! AuthSignedIn) return const Scaffold(body: SizedBox.shrink());
    final emp = auth.employee;
    final activeBranch = emp.assignments
        .where((a) => a.branch.id == auth.activeBranchId)
        .map((a) => a.branch)
        .toList()
        .firstOrNull;

    final pending = ref.watch(outboxPendingCountProvider).value ?? 0;

    return Scaffold(
      appBar: AppBar(
        title: Text(l.appTitle),
        actions: <Widget>[
          IconButton(
            tooltip: l.homeLogout,
            icon: const Icon(Icons.logout),
            onPressed: () async {
              await ref.read(authControllerProvider.notifier).signOut();
            },
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: <Widget>[
          Text(
            emp.name.isNotEmpty ? l.homeWelcome(emp.name) : l.appTitle,
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(l.homeActiveBranch, style: Theme.of(context).textTheme.labelMedium),
                  const SizedBox(height: 6),
                  if (emp.assignments.isEmpty)
                    Text(l.homePickBranch, style: const TextStyle(color: Colors.black54))
                  else
                    DropdownButton<int?>(
                      isExpanded: true,
                      value: auth.activeBranchId,
                      hint: Text(l.homePickBranch),
                      items: emp.assignments
                          .where((a) => a.isActive)
                          .map((a) => DropdownMenuItem<int?>(
                                value: a.branch.id,
                                child: Text(l.homeBranchRoleLabel(
                                    a.role, a.branch.nameEn ?? a.branch.name)),
                              ))
                          .toList(),
                      onChanged: (v) => ref
                          .read(authControllerProvider.notifier)
                          .setActiveBranch(v),
                    ),
                ],
              ),
            ),
          ),
          if (pending > 0) ...<Widget>[
            const SizedBox(height: 12),
            Card(
              color: Colors.orange.shade50,
              child: ListTile(
                leading: const Icon(Icons.cloud_upload_outlined, color: Colors.orange),
                title: Text(l.outboxPendingCount(pending)),
                trailing: TextButton(
                  child: Text(l.outboxFlushNow),
                  onPressed: () async {
                    final drainer = ref.read(outboxDrainerProvider);
                    final r = await drainer.drainOnce();
                    ref.invalidate(outboxPendingCountProvider);
                    if (!context.mounted) return;
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(content: Text(
                        r.isClean ? 'Synced ${r.sent}' : 'Synced ${r.sent}, ${r.failed} failed',
                      )),
                    );
                  },
                ),
              ),
            ),
          ],
          const SizedBox(height: 16),
          _ActionTile(
            icon  : Icons.qr_code_scanner,
            label : l.homeStartScan,
            color : Theme.of(context).colorScheme.primary,
            enabled: activeBranch != null,
            onTap : () => context.push('/scan'),
          ),
          _ActionTile(
            icon  : Icons.inventory_2_outlined,
            label : l.homeViewQueue,
            color : Colors.indigo,
            enabled: activeBranch != null,
            onTap : () => context.push('/queue/${auth.activeBranchId}'),
          ),
          _ActionTile(
            icon  : Icons.history,
            label : l.homeViewActivity,
            color : Colors.teal,
            enabled: true,
            onTap : () => context.push('/activity'),
          ),
        ],
      ),
    );
  }
}

class _ActionTile extends StatelessWidget {
  const _ActionTile({
    required this.icon,
    required this.label,
    required this.color,
    required this.enabled,
    required this.onTap,
  });
  final IconData icon;
  final String label;
  final Color color;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: enabled ? color.withValues(alpha: 0.15) : Colors.grey.shade200,
          child: Icon(icon, color: enabled ? color : Colors.grey),
        ),
        title: Text(label),
        trailing: const Icon(Icons.chevron_right),
        enabled: enabled,
        onTap: enabled ? onTap : null,
      ),
    );
  }
}
