import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../shared/ui/real_components.dart';
import '../data/customer_api_models.dart';
import '../data/customer_providers.dart';
import '../screens/customer_api_screens.dart';
import 'booking_controller.dart';

class BookingFlowScreen extends ConsumerWidget {
  const BookingFlowScreen({super.key, required this.id});
  final String id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final event = ref.watch(eventDetailProvider(id));
    return event.when(
      loading: () => Scaffold(
          appBar: AppBar(title: const Text('Prenota')),
          body: const Center(child: CircularProgressIndicator())),
      error: (error, _) => Scaffold(
        appBar: AppBar(title: const Text('Prenota')),
        body: Center(
            child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Text('$error', textAlign: TextAlign.center),
            const SizedBox(height: 12),
            FilledButton(
                onPressed: () => ref.invalidate(eventDetailProvider(id)),
                child: const Text('Riprova')),
          ]),
        )),
      ),
      data: (value) => _BookingForm(event: value),
    );
  }
}

class _BookingForm extends ConsumerWidget {
  const _BookingForm({required this.event});
  final ApiEvent event;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(bookingFlowProvider(event.id));
    final controller = ref.read(bookingFlowProvider(event.id).notifier);
    if (state.booking != null) {
      return _Confirmation(
          event: event, state: state, onRecoverQr: controller.recoverQr);
    }
    final activeTickets =
        event.ticketTypes.where((ticket) => ticket.isActive).toList();
    final estimate = state.estimateTotal(event.ticketTypes);
    final soldOut = event.availableSeats <= 0;

    return ScreenScaffold(title: 'Prenota', children: [
      const NeutralEventImage(height: 150),
      const SizedBox(height: 12),
      Text(event.title,
          style: Theme.of(context)
              .textTheme
              .titleLarge
              ?.copyWith(fontWeight: FontWeight.w800)),
      const SectionTitle('Biglietti disponibili'),
      if (activeTickets.isEmpty)
        const Text('Nessuna tipologia di biglietto disponibile.'),
      ...activeTickets.map((ticket) => _TicketSelector(
            ticket: ticket,
            value: state.quantities[ticket.id] ?? 0,
            onChanged: (value) => controller.setQuantity(ticket, value,
                eventAvailable: event.availableSeats),
          )),
      const SectionTitle('Partecipanti'),
      _Counter(
          label: 'Adulti',
          value: state.adults,
          onChanged: controller.setAdults),
      _Counter(
          label: 'Bambini',
          value: state.children,
          onChanged: controller.setChildren),
      for (var index = 0; index < state.children; index++) ...[
        const SizedBox(height: 8),
        TextFormField(
          key: Key('child-age-$index'),
          decoration:
              InputDecoration(labelText: 'Fascia età bambino ${index + 1}'),
          onChanged: (value) => controller.setChildAge(index, value),
        ),
      ],
      const SectionTitle('Esigenze e note'),
      TextFormField(
          maxLines: 2,
          decoration: const InputDecoration(labelText: 'Esigenze alimentari'),
          onChanged: controller.setDietaryNeeds),
      const SizedBox(height: 10),
      TextFormField(
          maxLines: 2,
          decoration:
              const InputDecoration(labelText: 'Esigenze di accessibilità'),
          onChanged: controller.setAccessibilityNeeds),
      const SizedBox(height: 10),
      TextFormField(
          maxLines: 2,
          decoration: const InputDecoration(labelText: 'Note'),
          onChanged: controller.setNotes),
      if (state.error != null) ...[
        const SizedBox(height: 14),
        Text(state.error!,
            key: const Key('booking-error'),
            style: TextStyle(color: Theme.of(context).colorScheme.error)),
      ],
      if (state.fieldErrors.isNotEmpty) ...[
        const SizedBox(height: 8),
        ...state.fieldErrors.values.expand((messages) => messages).map(
            (message) => Text(message,
                style: TextStyle(color: Theme.of(context).colorScheme.error))),
      ],
      const SectionTitle('Riepilogo'),
      ListTile(
        contentPadding: EdgeInsets.zero,
        title: Text(
            '${state.selectedTickets} biglietti · ${state.adults} adulti · ${state.children} bambini'),
        subtitle: const Text(
            'Totale stimato; il totale definitivo è calcolato dal server.'),
        trailing: Text(_money(estimate),
            style: Theme.of(context)
                .textTheme
                .titleLarge
                ?.copyWith(fontWeight: FontWeight.w900)),
      ),
      FilledButton(
        key: const Key('confirm-booking'),
        onPressed: soldOut || activeTickets.isEmpty || state.isSubmitting
            ? null
            : () => controller.submit(event.ticketTypes),
        child: state.isSubmitting
            ? const SizedBox.square(
                dimension: 22, child: CircularProgressIndicator(strokeWidth: 2))
            : Text(soldOut ? 'Evento esaurito' : 'Conferma prenotazione'),
      ),
    ]);
  }
}

class _TicketSelector extends StatelessWidget {
  const _TicketSelector(
      {required this.ticket, required this.value, required this.onChanged});
  final TicketType ticket;
  final int value;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final enabled = ticket.isAvailable;
    return Card(
        child: Padding(
      padding: const EdgeInsets.all(12),
      child: Row(children: [
        Expanded(
            child:
                Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(ticket.name,
              style: const TextStyle(fontWeight: FontWeight.w800)),
          if (ticket.description.isNotEmpty) Text(ticket.description),
          Text(ticket.priceCents == 0 ? 'Gratis' : _money(ticket.priceCents)),
          Text(ticket.availableQuantity == null
              ? 'Disponibilità secondo i posti evento'
              : '${ticket.availableQuantity} disponibili'),
          if (!enabled) const Text('Non disponibile'),
        ])),
        IconButton(
            key: Key('ticket-${ticket.id}-minus'),
            onPressed: enabled && value > 0 ? () => onChanged(value - 1) : null,
            icon: const Icon(Icons.remove_circle_outline)),
        Text('$value', key: Key('ticket-${ticket.id}-quantity')),
        IconButton(
            key: Key('ticket-${ticket.id}-plus'),
            onPressed: enabled &&
                    (ticket.availableQuantity == null ||
                        value < ticket.availableQuantity!)
                ? () => onChanged(value + 1)
                : null,
            icon: const Icon(Icons.add_circle_outline)),
      ]),
    ));
  }
}

class _Counter extends StatelessWidget {
  const _Counter(
      {required this.label, required this.value, required this.onChanged});
  final String label;
  final int value;
  final ValueChanged<int> onChanged;
  @override
  Widget build(BuildContext context) => ListTile(
        contentPadding: EdgeInsets.zero,
        title: Text(label),
        trailing: Row(mainAxisSize: MainAxisSize.min, children: [
          IconButton(
              onPressed: value > 0 ? () => onChanged(value - 1) : null,
              icon: const Icon(Icons.remove_circle_outline)),
          Text('$value'),
          IconButton(
              onPressed: value < 50 ? () => onChanged(value + 1) : null,
              icon: const Icon(Icons.add_circle_outline)),
        ]),
      );
}

class _Confirmation extends StatelessWidget {
  const _Confirmation(
      {required this.event, required this.state, required this.onRecoverQr});
  final ApiEvent event;
  final BookingFlowState state;
  final Future<void> Function() onRecoverQr;
  @override
  Widget build(BuildContext context) {
    final booking = state.booking!;
    return ScreenScaffold(title: 'Prenotazione confermata', children: [
      const Icon(Icons.check_circle_rounded, size: 72, color: Colors.green),
      Text(booking.bookingCode,
          textAlign: TextAlign.center,
          style: Theme.of(context)
              .textTheme
              .headlineSmall
              ?.copyWith(fontWeight: FontWeight.w900)),
      ListTile(
          title: Text(booking.eventTitle.isEmpty
              ? 'Evento non disponibile'
              : booking.eventTitle),
          subtitle: Text('${booking.totalPeople} partecipanti')),
      ListTile(
          title: const Text('Totale'),
          trailing: Text(_money(booking.totalPriceCents))),
      ListTile(title: const Text('Stato'), trailing: Text(booking.status)),
      const SectionTitle('QR prenotazione'),
      if (state.qr != null)
        Center(
            child: QrImageView(
                key: const Key('booking-qr'),
                data: state.qr!.qrToken,
                size: 240,
                semanticsLabel: 'QR della prenotazione'))
      else ...[
        const Text('QR non ancora disponibile.'),
        const SizedBox(height: 10),
        OutlinedButton(
            onPressed: onRecoverQr, child: const Text('Ricarica QR')),
      ],
      if (state.error != null)
        Text(state.error!,
            style: TextStyle(color: Theme.of(context).colorScheme.error)),
    ]);
  }
}

String _money(int cents) =>
    cents == 0 ? 'Gratis' : '€${(cents / 100).toStringAsFixed(2)}';
