import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../demo/domain/demo_models.dart';

class ScreenScaffold extends StatelessWidget {
  const ScreenScaffold({
    super.key,
    required this.title,
    required this.children,
    this.actions,
    this.bottom,
    this.padding = const EdgeInsets.fromLTRB(20, 8, 20, 28),
  });

  final String title;
  final List<Widget> children;
  final List<Widget>? actions;
  final PreferredSizeWidget? bottom;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(title), actions: actions, bottom: bottom),
      body: SafeArea(
        child: ListView(
          padding: padding,
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          children: children,
        ),
      ),
    );
  }
}

class SectionTitle extends StatelessWidget {
  const SectionTitle(this.title, {super.key, this.trailing});

  final String title;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 22, bottom: 12),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title,
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
            ),
          ),
          if (trailing != null) trailing!,
        ],
      ),
    );
  }
}

class LocalImage extends StatelessWidget {
  const LocalImage({super.key, required this.tone, this.height = 170});

  final int tone;
  final double height;

  @override
  Widget build(BuildContext context) {
    final colors = [
      [const Color(0xFF8E3D2C), const Color(0xFFE7B55F)],
      [const Color(0xFF537A5A), const Color(0xFFF1C6A1)],
      [const Color(0xFF2F6B7C), const Color(0xFFB7D7A8)],
      [const Color(0xFF6A4C93), const Color(0xFFF2C078)],
      [const Color(0xFFBF5B45), const Color(0xFF95B46A)],
    ][tone % 5];
    return ClipRRect(
      borderRadius: BorderRadius.circular(22),
      child: Container(
        height: height,
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: colors,
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: Stack(
          children: [
            Positioned(
              right: -22,
              top: -28,
              child: Icon(Icons.wb_sunny_rounded,
                  size: 130, color: Colors.white.withValues(alpha: .18)),
            ),
            Positioned(
              left: 18,
              bottom: 16,
              child: Icon(Icons.landscape_rounded,
                  size: 42, color: Colors.white.withValues(alpha: .86)),
            ),
          ],
        ),
      ),
    );
  }
}

class EventCard extends StatelessWidget {
  const EventCard({
    super.key,
    required this.event,
    this.compact = false,
    this.onTap,
    this.badge,
  });

  final LocalEvent event;
  final bool compact;
  final VoidCallback? onTap;
  final String? badge;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(22),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Stack(
                children: [
                  LocalImage(
                      tone: event.imageTone, height: compact ? 112 : 168),
                  Positioned(
                    top: 10,
                    right: 10,
                    child: CircleAvatar(
                      backgroundColor:
                          theme.colorScheme.surface.withValues(alpha: .9),
                      child: Icon(Icons.favorite_border_rounded,
                          color: theme.colorScheme.primary),
                    ),
                  ),
                  if (badge != null)
                    Positioned(
                      left: 10,
                      top: 10,
                      child: Chip(
                        visualDensity: VisualDensity.compact,
                        label: Text(badge!),
                        avatar: const Icon(Icons.workspace_premium_rounded,
                            size: 16),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 12),
              Text(event.title,
                  style: theme.textTheme.titleMedium
                      ?.copyWith(fontWeight: FontWeight.w800)),
              const SizedBox(height: 4),
              Text(event.organizer, style: theme.textTheme.bodyMedium),
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  InfoPill(
                      icon: Icons.calendar_month_rounded,
                      text: '${event.dateLabel} · ${event.timeLabel}'),
                  InfoPill(
                      icon: Icons.place_rounded,
                      text: '${event.town} (${event.province})'),
                  InfoPill(
                      icon: Icons.local_activity_rounded, text: event.category),
                  InfoPill(
                      icon: Icons.star_rounded,
                      text: event.rating.toStringAsFixed(1)),
                ],
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Text(
                      event.price == 0
                          ? 'Gratis'
                          : '€${event.price.toStringAsFixed(0)}',
                      style: theme.textTheme.titleMedium
                          ?.copyWith(fontWeight: FontWeight.w900)),
                  const Spacer(),
                  Text('${event.availableSeats} posti',
                      style: theme.textTheme.labelLarge),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class InfoPill extends StatelessWidget {
  const InfoPill({super.key, required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: scheme.surfaceContainerHighest.withValues(alpha: .75),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: scheme.primary),
          const SizedBox(width: 5),
          Flexible(child: Text(text, overflow: TextOverflow.ellipsis)),
        ],
      ),
    );
  }
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
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: scheme.primary),
            const SizedBox(height: 12),
            Text(value,
                style: Theme.of(context)
                    .textTheme
                    .titleLarge
                    ?.copyWith(fontWeight: FontWeight.w900)),
            const SizedBox(height: 2),
            Text(label, maxLines: 2, overflow: TextOverflow.ellipsis),
          ],
        ),
      ),
    );
  }
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
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          children: [
            Icon(icon, size: 42, color: Theme.of(context).colorScheme.primary),
            const SizedBox(height: 12),
            Text(title,
                style: Theme.of(context)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 6),
            Text(message, textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }
}

class LoadingSkeleton extends StatelessWidget {
  const LoadingSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: List.generate(
        3,
        (index) => Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: Container(
            height: 92,
            decoration: BoxDecoration(
              color: Theme.of(context)
                  .colorScheme
                  .surfaceContainerHighest
                  .withValues(alpha: .65),
              borderRadius: BorderRadius.circular(20),
            ),
          ),
        ),
      ),
    );
  }
}

void openEvent(BuildContext context, LocalEvent event) {
  context.push('/customer/events/${event.id}');
}
