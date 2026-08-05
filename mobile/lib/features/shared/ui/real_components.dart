import 'package:flutter/material.dart';

class ScreenScaffold extends StatelessWidget {
  const ScreenScaffold({super.key, required this.title, required this.children, this.actions, this.bottom, this.padding = const EdgeInsets.fromLTRB(20, 8, 20, 28)});
  final String title;
  final List<Widget> children;
  final List<Widget>? actions;
  final PreferredSizeWidget? bottom;
  final EdgeInsets padding;
  @override
  Widget build(BuildContext context) => Scaffold(appBar: AppBar(title: Text(title), actions: actions, bottom: bottom), body: SafeArea(child: ListView(padding: padding, keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag, children: children)));
}

class SectionTitle extends StatelessWidget {
  const SectionTitle(this.title, {super.key, this.trailing});
  final String title;
  final Widget? trailing;
  @override
  Widget build(BuildContext context) => Padding(padding: const EdgeInsets.only(top: 22, bottom: 12), child: Row(children: [Expanded(child: Text(title, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800))), if (trailing != null) trailing!]));
}

class InfoPill extends StatelessWidget {
  const InfoPill({super.key, required this.icon, required this.text});
  final IconData icon;
  final String text;
  @override
  Widget build(BuildContext context) => Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7), decoration: BoxDecoration(color: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: .75), borderRadius: BorderRadius.circular(999)), child: Row(mainAxisSize: MainAxisSize.min, children: [Icon(icon, size: 16, color: Theme.of(context).colorScheme.primary), const SizedBox(width: 5), Flexible(child: Text(text, overflow: TextOverflow.ellipsis))]));
}

class MetricCard extends StatelessWidget {
  const MetricCard({super.key, required this.label, required this.value, required this.icon});
  final String label;
  final String value;
  final IconData icon;
  @override
  Widget build(BuildContext context) => Card(child: Padding(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon, color: Theme.of(context).colorScheme.primary), const SizedBox(height: 12), Text(value, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900)), const SizedBox(height: 2), Text(label, maxLines: 2, overflow: TextOverflow.ellipsis)])));
}

class EmptyState extends StatelessWidget {
  const EmptyState({super.key, required this.title, required this.message, required this.icon});
  final String title;
  final String message;
  final IconData icon;
  @override
  Widget build(BuildContext context) => Card(child: Padding(padding: const EdgeInsets.all(24), child: Column(children: [Icon(icon, size: 42, color: Theme.of(context).colorScheme.primary), const SizedBox(height: 12), Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)), const SizedBox(height: 6), Text(message, textAlign: TextAlign.center)])));
}

class LoadingSkeleton extends StatelessWidget {
  const LoadingSkeleton({super.key});
  @override
  Widget build(BuildContext context) => Column(children: List.generate(3, (_) => Padding(padding: const EdgeInsets.only(bottom: 12), child: Container(height: 92, decoration: BoxDecoration(color: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: .65), borderRadius: BorderRadius.circular(20))))));
}
