import 'package:flutter/material.dart';

import '../../core/config.dart';
import '../../core/strings/strings.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/app_text_styles.dart';
import '../../core/widgets/app_card.dart';
import '../../core/widgets/app_scaffold.dart';
import '../../core/widgets/notice_banner.dart';
import '../../services/native_bridge.dart';

class HistoryScreen extends StatefulWidget {
  const HistoryScreen({super.key, required this.bridge});

  final NativeBridge bridge;

  @override
  State<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends State<HistoryScreen> {
  List<SentPayment>? _items;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final List<SentPayment> items = await widget.bridge.sentHistory();
      if (!mounted) {
        return;
      }
      setState(() {
        _items = items;
        _error = null;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }
      setState(() {
        _items = const [];
        _error = Strings.genericError;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final List<SentPayment>? items = _items;

    return AppScaffold(
      title: Strings.historyTitle,
      child: items == null
          ? const Center(child: CircularProgressIndicator())
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (_error != null) ...[
                  NoticeBanner.error(_error!),
                  const SizedBox(height: AppSpacing.md),
                ],
                if (items.isEmpty)
                  const AppCard(
                    child: Text(
                      Strings.historyEmpty,
                      style: AppTextStyles.muted,
                    ),
                  )
                else
                  ...items.map(
                    (SentPayment item) => Padding(
                      padding:
                          const EdgeInsets.only(bottom: AppSpacing.sm),
                      child: AppCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              AppConfig.formatDateTime(
                                item.postedAt.toLocal(),
                              ),
                              style: AppTextStyles.subtitle,
                            ),
                            const SizedBox(height: AppSpacing.xs),
                            Text(
                              item.text.isEmpty ? '—' : item.text,
                              style: AppTextStyles.muted,
                              maxLines: 3,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
              ],
            ),
    );
  }
}
