import 'package:flutter/material.dart';

import '../theme/app_spacing.dart';

class AppScaffold extends StatelessWidget {
  const AppScaffold({
    super.key,
    required this.child,
    this.title,
    this.actions,
    this.centered = false,
    this.onRefresh,
  });

  final Widget child;
  final String? title;
  final List<Widget>? actions;
  final bool centered;
  final Future<void> Function()? onRefresh;

  @override
  Widget build(BuildContext context) {
    Widget body;
    if (centered) {
      body = LayoutBuilder(
        builder: (BuildContext context, BoxConstraints constraints) {
          return SingleChildScrollView(
            child: ConstrainedBox(
              constraints: BoxConstraints(minHeight: constraints.maxHeight),
              child: Center(
                child: Padding(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  child: child,
                ),
              ),
            ),
          );
        },
      );
    } else {
      body = SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: child,
      );
    }

    if (onRefresh != null) {
      body = RefreshIndicator(onRefresh: onRefresh!, child: body);
    }

    return Scaffold(
      appBar: title == null
          ? null
          : AppBar(title: Text(title!), actions: actions),
      body: SafeArea(child: body),
    );
  }
}
