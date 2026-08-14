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
                      '${booking.bookingCode} Â· ${booking.totalPeople} partecipanti Â· ${_money(booking.totalPriceCents)}'),
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
          if (value.canEdit) ...[
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: () => editBooking(context, ref, value),
                icon: const Icon(Icons.edit_rounded),
                label: const Text('Modifica prenotazione'),
              ),
            ),
            const SizedBox(height: 10),
          ],
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
    cents == 0 ? 'Gratis' : 'â‚¬${(cents / 100).toStringAsFixed(2)}';

Future<void> confirmAndCancelBooking(
    BuildContext context, WidgetRef ref, String bookingUlid) async {
  final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Annullare la prenotazione?'),
          content: const Text(
              'I posti verranno liberati. Questa operazione non puÃ² essere annullata.'),
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

Future<void> editBooking(
    BuildContext context, WidgetRef ref, CustomerBooking booking) async {
  final phone = TextEditingController(text: booking.contactPhone);
  final notes = TextEditingController(text: booking.notes);
  final adults = TextEditingController(text: '${booking.adultsCount}');
  final children = TextEditingController(text: '${booking.childrenCount}');
  final quantities = <String, int>{
    for (final item in booking.items) item.ticketTypeUlid: item.quantity
  };
  final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => StatefulBuilder(
          builder: (context, setDialogState) => AlertDialog(
            title: const Text('Modifica prenotazione'),
            content: SingleChildScrollView(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                for (final item in booking.items)
                  ListTile(
                    title: Text(item.ticketName),
                    subtitle: Text(_money(item.unitPriceCents)),
                    trailing: SizedBox(
                        width: 92,
                        child: TextFormField(
                          initialValue:
                              '${quantities[item.ticketTypeUlid] ?? 0}',
                          keyboardType: TextInputType.number,
                          decoration:
                              const InputDecoration(labelText: 'Quantità'),
                          onChanged: (value) => setDialogState(() =>
                              quantities[item.ticketTypeUlid] =
                                  int.tryParse(value) ?? 0),
                        )),
                  ),
                Row(children: [
                  Expanded(
                      child: TextField(
                          controller: adults,
                          keyboardType: TextInputType.number,
                          decoration:
                              const InputDecoration(labelText: 'Adulti'))),
                  const SizedBox(width: 8),
                  Expanded(
                      child: TextField(
                          controller: children,
                          keyboardType: TextInputType.number,
                          decoration:
                              const InputDecoration(labelText: 'Bambini'))),
                ]),
                const SizedBox(height: 12),
                TextField(
                    controller: phone,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(
                        labelText: 'Telefono',
                        prefixIcon: Icon(Icons.phone_rounded))),
                const SizedBox(height: 12),
                TextField(
                    controller: notes,
                    maxLines: 3,
                    decoration: const InputDecoration(
                        labelText: 'Note',
                        prefixIcon: Icon(Icons.notes_rounded))),
                const SizedBox(height: 16),
                Card(
                    child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(children: [
                          Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                const Text('Partecipanti'),
                                Text(
                                    '${quantities.values.fold<int>(0, (sum, value) => sum + value)}')
                              ]),
                          Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                const Text('Nuovo totale'),
                                Text(_money(booking.items.fold<int>(
                                    0,
                                    (sum, item) =>
                                        sum +
                                        item.unitPriceCents *
                                            (quantities[item.ticketTypeUlid] ??
                                                0))))
                              ]),
                        ]))),
              ]),
            ),
            actions: [
              TextButton(
                  onPressed: () => Navigator.pop(dialogContext, false),
                  child: const Text('Annulla')),
              FilledButton(
                  onPressed: () {
                    final people = quantities.values
                        .fold<int>(0, (sum, value) => sum + value);
                    if (people < 1 ||
                        (int.tryParse(adults.text) ?? 0) +
                                (int.tryParse(children.text) ?? 0) !=
                            people ||
                        phone.text.trim().isEmpty) {
                      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
                          content: Text(
                              'Controlla quantità, partecipanti e telefono.')));
                      return;
                    }
                    Navigator.pop(dialogContext, true);
                  },
                  child: const Text('Conferma modifica')),
            ],
          ),
        ),
      ) ??
      false;
  if (!confirmed || !context.mounted) {
    phone.dispose();
    notes.dispose();
    adults.dispose();
    children.dispose();
    return;
  }
  try {
    await ref.read(bookingRepositoryProvider).updateBooking(
          booking,
          phone.text,
          notes.text,
          quantities: quantities,
          adultsCount: int.tryParse(adults.text) ?? 0,
          childrenCount: int.tryParse(children.text) ?? 0,
        );
    ref.invalidate(customerBookingsProvider);
    ref.invalidate(customerBookingDetailProvider(booking.ulid));
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content:
              Text('Prenotazione aggiornata. Codice e QR sono invariati.')));
    }
  } on BookingApiException catch (error) {
    if (context.mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(error.message)));
    }
  } finally {
    phone.dispose();
    notes.dispose();
    adults.dispose();
    children.dispose();
  }
}
