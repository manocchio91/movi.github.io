import 'package:flutter/material.dart';

class ScreenScaffold extends StatelessWidget {
  const ScreenScaffold(
      {super.key,
      required this.title,
      required this.children,
      this.actions,
      this.bottom,
      this.padding = const EdgeInsets.fromLTRB(20, 8, 20, 28)});
  final String title;
  final List<Widget> children;
  final List<Widget>? actions;
  final PreferredSizeWidget? bottom;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(title), actions: actions, bottom: bottom),
        body: SafeArea(
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 760),
              child: ListView(
                padding: padding,
                keyboardDismissBehavior:
                    ScrollViewKeyboardDismissBehavior.onDrag,
                children: children,
              ),
            ),
          ),
        ),
      );
}

class SectionTitle extends StatelessWidget {
  const SectionTitle(this.title, {super.key, this.trailing});
  final String title;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 24, bottom: 12),
        child: Row(children: [
          Expanded(
              child:
                  Text(title, style: Theme.of(context).textTheme.titleLarge)),
          if (trailing != null) trailing!,
        ]),
      );
}

class InfoPill extends StatelessWidget {
  const InfoPill({super.key, required this.icon, required this.text});
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 8),
        decoration: BoxDecoration(
          color: Theme.of(context)
              .colorScheme
              .primaryContainer
              .withValues(alpha: .65),
          borderRadius: BorderRadius.circular(999),
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(icon, size: 17, color: Theme.of(context).colorScheme.primary),
          const SizedBox(width: 6),
          Flexible(child: Text(text, overflow: TextOverflow.ellipsis)),
        ]),
      );
}

class MetricCard extends StatelessWidget {
  const MetricCard(
      {super.key,
      required this.label,
      required this.value,
      required this.icon});
  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child:
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Icon(icon, color: Theme.of(context).colorScheme.primary),
            const SizedBox(height: 12),
            Text(value, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 3),
            Text(label, maxLines: 2, overflow: TextOverflow.ellipsis),
          ]),
        ),
      );
}

class EmptyState extends StatelessWidget {
  const EmptyState(
      {super.key,
      required this.title,
      required this.message,
      required this.icon});
  final String title;
  final String message;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Semantics(
        liveRegion: true,
        child: Card(
          child: Padding(
            padding: const EdgeInsets.all(28),
            child: Column(children: [
              Icon(icon,
                  size: 44, color: Theme.of(context).colorScheme.primary),
              const SizedBox(height: 14),
              Text(title, style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 7),
              Text(message, textAlign: TextAlign.center),
            ]),
          ),
        ),
      );
}

class LoadingSkeleton extends StatelessWidget {
  const LoadingSkeleton({super.key});

  @override
  Widget build(BuildContext context) => Semantics(
        label: 'Caricamento in corso',
        child: Column(
          children: List.generate(
            3,
            (_) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Container(
                height: 92,
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.surfaceContainerHighest,
                  borderRadius: BorderRadius.circular(18),
                ),
              ),
            ),
          ),
        ),
      );
}

class StatusBadge extends StatelessWidget {
  const StatusBadge({super.key, required this.status});
  final String status;

  @override
  Widget build(BuildContext context) {
    final normalized = status.toLowerCase();
    final scheme = Theme.of(context).colorScheme;
    final (background, foreground) = switch (normalized) {
      'published' ||
      'verified' ||
      'active' ||
      'confirmed' ||
      'checked_in' ||
      'completed' =>
        (const Color(0xFFDDF5EA), const Color(0xFF166445)),
      'pending' || 'pending_review' || 'changes_requested' || 'draft' => (
          const Color(0xFFFFEBC2),
          const Color(0xFF7A4E00)
        ),
      'rejected' || 'suspended' || 'cancelled' || 'inactive' => (
          scheme.errorContainer,
          scheme.onErrorContainer
        ),
      _ => (scheme.surfaceContainerHighest, scheme.onSurfaceVariant),
    };
    final label = normalized
        .replaceAll('_', ' ')
        .split(' ')
        .map((word) =>
            word.isEmpty ? '' : '${word[0].toUpperCase()}${word.substring(1)}')
        .join(' ');
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
          color: background, borderRadius: BorderRadius.circular(999)),
      child: Text(label.isEmpty ? 'Non definito' : label,
          style: TextStyle(
              color: foreground, fontSize: 11, fontWeight: FontWeight.w800)),
    );
  }
}
