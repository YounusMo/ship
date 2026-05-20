import 'package:flutter/material.dart';
import 'package:flutter_gen/gen_l10n/app_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../state/shipments_provider.dart';
import '../widgets/shipment_row.dart';

class ShipmentsScreen extends ConsumerStatefulWidget {
  const ShipmentsScreen({super.key});

  @override
  ConsumerState<ShipmentsScreen> createState() => _ShipmentsScreenState();
}

class _ShipmentsScreenState extends ConsumerState<ShipmentsScreen> {
  final _scroll = ScrollController();
  String _mode = 'all';

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.position.pixels >= _scroll.position.maxScrollExtent - 200) {
        ref.read(shipmentsProvider.notifier).loadMore();
      }
    });
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context)!;
    final state = ref.watch(shipmentsProvider);

    return Column(
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          child: SegmentedButton<String>(
            segments: <ButtonSegment<String>>[
              ButtonSegment(value: 'all', label: Text(l.filterAll)),
              ButtonSegment(value: 'sea', label: Text(l.filterSea)),
              ButtonSegment(value: 'sky', label: Text(l.filterAir)),
            ],
            selected: <String>{_mode},
            onSelectionChanged: (s) {
              setState(() => _mode = s.first);
              ref.read(shipmentsProvider.notifier).setMode(_mode);
            },
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () => ref.read(shipmentsProvider.notifier).refresh(),
            child: state.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => Center(child: Text('$e')),
              data: (s) {
                if (s.items.isEmpty) {
                  return Center(child: Text(l.noShipmentsYet));
                }
                return ListView.separated(
                  controller: _scroll,
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  itemCount: s.items.length + (s.page.hasMore ? 1 : 0),
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (_, i) {
                    if (i >= s.items.length) {
                      return const Padding(
                        padding: EdgeInsets.all(16),
                        child: Center(child: CircularProgressIndicator()),
                      );
                    }
                    return ShipmentRow(shipment: s.items[i]);
                  },
                );
              },
            ),
          ),
        ),
      ],
    );
  }
}
