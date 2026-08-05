import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../shared/ui/real_components.dart';
import 'booking_controller.dart';
import 'booking_models.dart';

enum BookingGroup { upcoming, completed, cancelled }

BookingGroup classifyBooking(CustomerBooking booking) {
  if (booking.isCancelled) return BookingGroup.cancelled;
  if (booking.isCompleted) return BookingGroup.completed;
  return BookingGroup.upcoming;
}

class CustomerBookingsScreen extends ConsumerWidget {
  const CustomerBookingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final result = ref.watch(customerBookingsProvider);
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Prenotazioni'),
          actions: [
            IconButton(
              tooltip: 'Aggiorna',
              onPressed: () => ref.invalidate(customerBookingsProvider),
              icon: const Icon(Icons.refresh_rounded),
            ),
          ],
          bottom: const TabBar(tabs: [
            Tab(text: 'Prossime'),
            Tab(text: 'Concluse'),
            Tab(text: 'Annullate'),
          ]),
        ),
        body: result.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) => _BookingError(
            message: '$error',
            onRetry: () => ref.invalidate(customerBookingsProvider),
          ),
          data: (bookings) => TabBarView(children: [
            _BookingList(
                bookings: _forGroup(bookings, BookingGroup.upcoming),
                onRefresh: () async =>
                    ref.invalidate(customerBookingsProvider)),
            _BookingList(
                bookings: _forGroup(bookings, BookingGroup.completed),
                onRefresh: () async =>
                    ref.invalidate(customerBookingsProvider)),
            _BookingList(
                bookings: _forGroup(bookings, BookingGroup.cancelled),
                onRefresh: () async =>
                    ref.invalidate(customerBookingsProvider)),
          ]),
        ),
      ),
    );
  }
}

List<CustomerBooking> _forGroup(
        List<CustomerBooking> bookings, BookingGroup group) =>
    bookings.where((booking) => classifyBooking(booking) == group).toList();

class _BookingList extends ConsumerWidget {
  const _BookingList({required this.bookings, required this.onRefresh});
  final List<CustomerBooking> bookings;
  final Future<void> Function() onRefresh;
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (bookings.isEmpty) {
      return const Center(
          child: Padding(
        padding: EdgeInsets.all(24),
        child: Text('Nessuna prenotazione in questa sezione.'),
      ));
    }
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: bookings.length,
        itemBuilder: (context, index) {
          final booking = bookings[index];
          return Card(
            key: Key('booking-${booking.ulid}'),
            child: ListTile(
              isThreeLine: booking.canCancel,
              onTap: () => context.push('/customer/bookings/${booking.ulid}'),
              leading: const Icon(Icons.confirmation_number_rounded),
              title: Text(booking.eventTitle.isEmpty
                  ? 'Evento non disponibile'
                  : booking.eventTitle),
              subtitle: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                      '${booking.bookingCode} · ${booking.totalPeople} partecipanti · ${_money(booking.totalPriceCents)}'),
                  if (booking.canCancel)
                    TextButton(
                      key: Key('cancel-list-${booking.ulid}'),
                      style: TextButton.styleFrom(
                          padding: EdgeInsets.zero,
                          minimumSize: const Size(0, 36)),
                      onPressed: ref
                              .watch(bookingCancellationProvider(booking.ulid))
                              .isLoading
                          ? null
                          : () => confirmAndCancelBooking(
                              context, ref, booking.ulid),
                      child: const Text('Annulla'),
                    ),
                ],
              ),
              trailing: Text(booking.status),
            ),
          );
        },
      ),
    );
  }
}

class CustomerBookingDetailScreen extends ConsumerWidget {
  const CustomerBookingDetailScreen({super.key, required this.bookingUlid});
  final String bookingUlid;
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final booking = ref.watch(customerBookingDetailProvider(bookingUlid));
    final qr = ref.watch(customerBookingQrProvider(bookingUlid));
    final cancellation = ref.watch(bookingCancellationProvider(bookingUlid));
    return ScreenScaffold(title: 'Dettaglio prenotazione', children: [
      booking.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => Text('$error'),
        data: (value) => Column(children: [
          ListTile(
              title: Text(value.eventTitle), subtitle: Text(value.bookingCode)),
          ListTile(title: const Text('Stato'), trailing: Text(value.status)),
          ListTile(
              title: const Text('Totale'),
              trailing: Text(_money(value.totalPriceCents))),
          if (value.canCancel)
            FilledButton.tonalIcon(
              key: const Key('cancel-booking'),
              onPressed: cancellation.isLoading
                  ? null
                  : () => confirmAndCancelBooking(context, ref, bookingUlid),
              icon: cancellation.isLoading
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2))
                  : const Icon(Icons.event_busy_rounded),
              label: const Text('Annulla prenotazione'),
            ),
        ]),
      ),
      const SectionTitle('QR prenotazione'),
      qr.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => Text('$error'),
        data: (value) => Center(
            child: QrImageView(
          data: value.qrToken,
          size: 240,
          semanticsLabel: 'QR della prenotazione',
        )),
      ),
    ]);
  }
}

class _BookingError extends StatelessWidget {
  const _BookingError({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) => Center(
          child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          FilledButton(onPressed: onRetry, child: const Text('Riprova')),
        ]),
      ));
}

String _money(int cents) =>
    cents == 0 ? 'Gratis' : '€${(cents / 100).toStringAsFixed(2)}';

Future<void> confirmAndCancelBooking(
    BuildContext context, WidgetRef ref, String bookingUlid) async {
  final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Annullare la prenotazione?'),
          content: const Text(
              'I posti verranno liberati. Questa operazione non può essere annullata.'),
          actions: [
            TextButton(
                onPressed: () => Navigator.pop(dialogContext, false),
                child: const Text('Indietro')),
            FilledButton(
                key: const Key('confirm-cancel-booking'),
                onPressed: () => Navigator.pop(dialogContext, true),
                child: const Text('Conferma annullamento')),
          ],
        ),
      ) ??
      false;
  if (!confirmed || !context.mounted) return;
  final succeeded = await ref
      .read(bookingCancellationProvider(bookingUlid).notifier)
      .cancel();
  if (!context.mounted) return;
  if (succeeded) {
    ScaffoldMessenger.of(context)
        .showSnackBar(const SnackBar(content: Text('Prenotazione annullata.')));
  } else {
    final error = ref.read(bookingCancellationProvider(bookingUlid)).error;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(error?.toString() ??
            'Impossibile annullare la prenotazione. Riprova.')));
  }
}
