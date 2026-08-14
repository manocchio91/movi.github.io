import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../role_data/role_api_repository.dart';
import '../shared/ui/real_components.dart';

class OrganizerEventFormScreen extends ConsumerStatefulWidget {
  const OrganizerEventFormScreen({super.key, this.eventId});
  final String? eventId;

  @override
  ConsumerState<OrganizerEventFormScreen> createState() =>
      _OrganizerEventFormScreenState();
}

class _OrganizerEventFormScreenState
    extends ConsumerState<OrganizerEventFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _title = TextEditingController();
  final _summary = TextEditingController();
  final _description = TextEditingController();
  final _capacity = TextEditingController(text: '20');
  final _ticketName = TextEditingController(text: 'Ingresso');
  final _ticketPrice = TextEditingController(text: '0');
  final _ticketQuantity = TextEditingController(text: '20');
  late final Future<List<Map<String, dynamic>>> _categories;
  Map<String, dynamic>? _municipality;
  int? _categoryId;
  DateTime? _startsAt;
  String _audience = 'generic';
  String? _ticketUlid;
  bool _isFree = true;
  bool _familyFriendly = true;
  bool _saving = false;
  String? _error;
  Future<List<Map<String, dynamic>>>? _competition;

  @override
  void initState() {
    super.initState();
    _categories = _loadInitial();
  }

  Future<List<Map<String, dynamic>>> _loadInitial() async {
    final repository = ref.read(roleApiRepositoryProvider);
    final categories = _list((await repository.get('/categories'))['data']);
    if (widget.eventId != null) {
      final event = await repository.organizerEvent(widget.eventId!);
      final ticketTypes = _list(event['ticket_types']);
      final categoriesForEvent = _list(event['categories']);
      if (mounted) {
        setState(() {
          _title.text = '${event['title'] ?? ''}';
          _summary.text = '${event['summary'] ?? ''}';
          _description.text = '${event['description'] ?? ''}';
          _capacity.text = '${event['capacity_total'] ?? 0}';
          _municipality = _map(event['municipality']);
          _categoryId = categoriesForEvent.isEmpty
              ? null
              : categoriesForEvent.first['id'] as int?;
          _startsAt = DateTime.tryParse('${event['starts_at']}')?.toLocal();
          _isFree = event['is_free'] == true;
          _familyFriendly = event['family_friendly'] == true;
          if (ticketTypes.isNotEmpty) {
            final ticket = ticketTypes.first;
            _ticketUlid = '${ticket['ulid']}';
            _ticketName.text = '${ticket['name'] ?? ''}';
            _ticketPrice.text =
                ((_asNum(ticket['price_cents']) / 100)).toStringAsFixed(2);
            _ticketQuantity.text = '${ticket['max_quantity'] ?? 1}';
            _audience = '${ticket['audience'] ?? 'generic'}';
          }
        });
      }
    }
    return categories;
  }

  @override
  void dispose() {
    _title.dispose();
    _summary.dispose();
    _description.dispose();
    _capacity.dispose();
    _ticketName.dispose();
    _ticketPrice.dispose();
    _ticketQuantity.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => ScreenScaffold(
        title: widget.eventId == null ? 'Crea evento' : 'Modifica evento',
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.primaryContainer,
              borderRadius: BorderRadius.circular(22),
            ),
            child: Row(children: [
              const Icon(Icons.add_circle_rounded, size: 42),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                        widget.eventId == null
                            ? 'Nuova esperienza'
                            : 'Modifica esperienza',
                        style: const TextStyle(
                            fontSize: 20, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 4),
                    const Text(
                        'Salva una bozza e completala prima della pubblicazione.'),
                  ],
                ),
              ),
            ]),
          ),
          const SizedBox(height: 20),
          Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SectionTitle('Informazioni principali'),
                TextFormField(
                  controller: _title,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                      labelText: 'Titolo',
                      prefixIcon: Icon(Icons.title_rounded)),
                  validator: (value) =>
                      _required(value, 'Inserisci il titolo.'),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _summary,
                  maxLength: 280,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                      labelText: 'Breve descrizione',
                      prefixIcon: Icon(Icons.short_text_rounded)),
                  validator: (value) =>
                      _required(value, 'Inserisci una breve descrizione.'),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _description,
                  minLines: 4,
                  maxLines: 7,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                      labelText: 'Descrizione completa',
                      alignLabelWithHint: true,
                      prefixIcon: Icon(Icons.description_rounded)),
                  validator: (value) =>
                      _required(value, 'Inserisci la descrizione completa.'),
                ),
                const SectionTitle('Categoria e luogo'),
                FutureBuilder<List<Map<String, dynamic>>>(
                  future: _categories,
                  builder: (context, snapshot) {
                    if (!snapshot.hasData) return const LoadingSkeleton();
                    return DropdownButtonFormField<int>(
                      initialValue: _categoryId,
                      decoration: const InputDecoration(
                          labelText: 'Categoria',
                          prefixIcon: Icon(Icons.category_rounded)),
                      items: snapshot.data!
                          .map((item) => DropdownMenuItem<int>(
                                value: item['id'] as int,
                                child: Text('${item['name']}'),
                              ))
                          .toList(),
                      onChanged: (value) {
                        setState(() => _categoryId = value);
                        _refreshCompetition();
                      },
                      validator: (value) =>
                          value == null ? 'Seleziona una categoria.' : null,
                    );
                  },
                ),
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: _chooseMunicipality,
                  icon: const Icon(Icons.place_rounded),
                  label: Text(_municipality == null
                      ? 'Scegli Comune e provincia'
                      : '${_municipality!['name']} (${_map(_municipality!['province'])['code'] ?? _municipality!['province'] ?? ''})'),
                ),
                if (_municipality == null)
                  const Padding(
                    padding: EdgeInsets.only(top: 6, left: 12),
                    child: Text('Il Comune ÃƒÂ¨ obbligatorio.',
                        style:
                            TextStyle(color: Colors.redAccent, fontSize: 12)),
                  ),
                const SectionTitle('Data e disponibilitÃƒÂ '),
                OutlinedButton.icon(
                  onPressed: _chooseDateTime,
                  icon: const Icon(Icons.calendar_month_rounded),
                  label: Text(_startsAt == null
                      ? 'Scegli data e ora'
                      : _dateTimeLabel(_startsAt!)),
                ),
                const SizedBox(height: 12),
                if (_competition != null) ...[
                  FutureBuilder<List<Map<String, dynamic>>>(
                    future: _competition,
                    builder: (context, snapshot) {
                      if (!snapshot.hasData) {
                        return const LoadingSkeleton();
                      }
                      final busy = snapshot.data!.where(
                          (day) => (day['events_count'] as num? ?? 0) > 0);
                      return Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Wrap(spacing: 8, children: [
                              Chip(
                                  backgroundColor: Color(0xffffd54f),
                                  label: Text('Giallo: stesso Comune')),
                              Chip(
                                  backgroundColor: Color(0xffef5350),
                                  label: Text('Rosso: forte concorrenza')),
                            ]),
                            const SizedBox(height: 8),
                            SizedBox(
                                height: 58,
                                child: ListView(
                                    scrollDirection: Axis.horizontal,
                                    children: [
                                      for (final day in busy)
                                        Container(
                                            width: 54,
                                            margin:
                                                const EdgeInsets.only(right: 6),
                                            decoration: BoxDecoration(
                                                color: day['color'] == 'red'
                                                    ? const Color(0xffef5350)
                                                    : const Color(0xffffd54f),
                                                borderRadius:
                                                    BorderRadius.circular(12)),
                                            child: Center(
                                                child: Column(
                                                    mainAxisSize:
                                                        MainAxisSize.min,
                                                    children: [
                                                  Text(
                                                      DateTime.parse(
                                                              '${day['date']}')
                                                          .day
                                                          .toString(),
                                                      style: const TextStyle(
                                                          fontWeight:
                                                              FontWeight.w900)),
                                                  Text(
                                                      '${day['events_count']} ev.',
                                                      style: const TextStyle(
                                                          fontSize: 11)),
                                                ]))),
                                    ])),
                          ]);
                    },
                  ),
                  const SizedBox(height: 12),
                ],
                TextFormField(
                  controller: _capacity,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                      labelText: 'Capienza totale',
                      prefixIcon: Icon(Icons.groups_rounded)),
                  validator: (value) =>
                      _positive(value, 'Inserisci la capienza.'),
                ),
                const SectionTitle('Ticket'),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  value: _isFree,
                  title: const Text('Evento gratuito'),
                  subtitle: const Text(
                      'Il prezzo del ticket sarÃƒÂ  impostato a zero.'),
                  onChanged: (value) => setState(() => _isFree = value),
                ),
                TextFormField(
                  controller: _ticketName,
                  decoration: const InputDecoration(
                      labelText: 'Nome ticket',
                      prefixIcon: Icon(Icons.confirmation_number_rounded)),
                  validator: (value) =>
                      _required(value, 'Inserisci il nome del ticket.'),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: _audience,
                  decoration: const InputDecoration(
                      labelText: 'Destinatari',
                      prefixIcon: Icon(Icons.people_rounded)),
                  items: const [
                    DropdownMenuItem(value: 'generic', child: Text('Tutti')),
                    DropdownMenuItem(value: 'adult', child: Text('Adulti')),
                    DropdownMenuItem(value: 'child', child: Text('Bambini')),
                  ],
                  onChanged: (value) => setState(() => _audience = value!),
                ),
                const SizedBox(height: 12),
                Row(children: [
                  Expanded(
                    child: TextFormField(
                      controller: _ticketPrice,
                      enabled: !_isFree,
                      keyboardType:
                          const TextInputType.numberWithOptions(decimal: true),
                      decoration: const InputDecoration(
                          labelText: 'Prezzo Ã¢â€šÂ¬', prefixText: 'Ã¢â€šÂ¬ '),
                      validator: (value) => _isFree
                          ? null
                          : _nonNegative(value, 'Inserisci un prezzo valido.'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextFormField(
                      controller: _ticketQuantity,
                      keyboardType: TextInputType.number,
                      decoration:
                          const InputDecoration(labelText: 'QuantitÃƒÂ '),
                      validator: (value) =>
                          _positive(value, 'QuantitÃƒÂ  non valida.'),
                    ),
                  ),
                ]),
                const SizedBox(height: 8),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  value: _familyFriendly,
                  title: const Text('Adatto alle famiglie'),
                  onChanged: (value) => setState(() => _familyFriendly = value),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.errorContainer,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(_error!,
                        style: TextStyle(
                            color: Theme.of(context)
                                .colorScheme
                                .onErrorContainer)),
                  ),
                ],
                const SizedBox(height: 20),
                FilledButton.icon(
                  onPressed: _saving ? null : _save,
                  icon: _saving
                      ? const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(strokeWidth: 2))
                      : const Icon(Icons.save_rounded),
                  label: Text(_saving ? 'Salvataggio...' : 'Salva evento'),
                ),
              ],
            ),
          ),
        ],
      );

  Future<void> _chooseMunicipality() async {
    final value = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => const _MunicipalityPicker(),
    );
    if (value != null) {
      setState(() => _municipality = value);
      _refreshCompetition();
    }
  }

  Future<void> _chooseDateTime() async {
    final now = DateTime.now();
    final date = await showDatePicker(
      context: context,
      firstDate: now,
      lastDate: now.add(const Duration(days: 730)),
      initialDate: _startsAt ?? now.add(const Duration(days: 1)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime:
          TimeOfDay.fromDateTime(_startsAt ?? now.add(const Duration(days: 1))),
    );
    if (time == null) return;
    setState(() => _startsAt =
        DateTime(date.year, date.month, date.day, time.hour, time.minute));
  }

  void _refreshCompetition() {
    if (_municipality == null || _categoryId == null) return;
    final date = _startsAt ?? DateTime.now();
    final exclude =
        widget.eventId == null ? '' : '&exclude_event_ulid=${widget.eventId}';
    setState(() {
      _competition = ref
          .read(roleApiRepositoryProvider)
          .get(
              '/organizer/calendar/availability?month=${date.month}&year=${date.year}&municipality_id=${_municipality!['id']}&category_id=$_categoryId$exclude')
          .then((body) => _list(body['data']));
    });
  }

  Future<void> _save() async {
    final valid = _formKey.currentState?.validate() == true;
    if (!valid || _municipality == null || _startsAt == null) {
      setState(() => _error = 'Completa tutti i campi obbligatori.');
      return;
    }
    if (!_startsAt!.isAfter(DateTime.now())) {
      setState(() => _error = 'La data deve essere futura.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    final capacity = int.parse(_capacity.text);
    final maxQuantity = int.parse(_ticketQuantity.text);
    final priceCents = _isFree
        ? 0
        : ((double.tryParse(_ticketPrice.text.replaceAll(',', '.')) ?? 0) * 100)
            .round();
    try {
      final repository = ref.read(roleApiRepositoryProvider);
      final payload = {
        'title': _title.text.trim(),
        'summary': _summary.text.trim(),
        'description': _description.text.trim(),
        'municipality_id': _municipality!['id'],
        'category_ids': [_categoryId],
        'starts_at': _startsAt!.toUtc().toIso8601String(),
        'capacity_total': capacity,
        'is_free': _isFree,
        'family_friendly': _familyFriendly,
        'indoor_outdoor': 'mixed',
        'ticket_types': [
          {
            if (_ticketUlid != null) 'ulid': _ticketUlid,
            'name': _ticketName.text.trim(),
            'audience': _audience,
            'price_cents': priceCents,
            'max_quantity': maxQuantity,
            'is_active': true,
          }
        ],
      };
      if (widget.eventId == null) {
        await repository.post('/organizer/events', payload);
      } else {
        await repository.patch('/organizer/events/${widget.eventId}', payload);
      }
      if (mounted) context.pop(true);
    } catch (error) {
      if (mounted) {
        setState(() {
          _saving = false;
          _error = '$error';
        });
      }
    }
  }
}

class _MunicipalityPicker extends ConsumerStatefulWidget {
  const _MunicipalityPicker();
  @override
  ConsumerState<_MunicipalityPicker> createState() =>
      _MunicipalityPickerState();
}

class _MunicipalityPickerState extends ConsumerState<_MunicipalityPicker> {
  final _search = TextEditingController();
  Future<List<Map<String, dynamic>>>? _results;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
        title: const Text('Scegli Comune'),
        content: SizedBox(
          width: 420,
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            TextField(
              controller: _search,
              autofocus: true,
              textInputAction: TextInputAction.search,
              decoration: const InputDecoration(
                  hintText: 'Cerca Comune',
                  prefixIcon: Icon(Icons.search_rounded)),
              onSubmitted: (_) => _runSearch(),
              onChanged: (value) {
                if (value.trim().length >= 2) _runSearch();
              },
            ),
            const SizedBox(height: 12),
            SizedBox(
              height: 260,
              child: _results == null
                  ? const Center(child: Text('Inserisci almeno due caratteri.'))
                  : FutureBuilder<List<Map<String, dynamic>>>(
                      future: _results,
                      builder: (context, snapshot) {
                        if (!snapshot.hasData) {
                          return const Center(
                              child: CircularProgressIndicator());
                        }
                        if (snapshot.data!.isEmpty) {
                          return const Center(
                              child: Text('Nessun Comune trovato.'));
                        }
                        return ListView.builder(
                          itemCount: snapshot.data!.length,
                          itemBuilder: (_, index) {
                            final item = snapshot.data![index];
                            final province = _map(item['province']);
                            return ListTile(
                              leading: const Icon(Icons.place_rounded),
                              title: Text('${item['name']}'),
                              subtitle: Text('${province['code'] ?? ''}'),
                              onTap: () => Navigator.pop(context, item),
                            );
                          },
                        );
                      },
                    ),
            ),
          ]),
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Annulla')),
        ],
      );

  void _runSearch() {
    final query = _search.text.trim();
    if (query.length < 2) return;
    setState(() {
      _results = ref
          .read(roleApiRepositoryProvider)
          .get('/locations/search?q=${Uri.encodeQueryComponent(query)}')
          .then((body) => _list(body['data']));
    });
  }
}

String? _required(String? value, String message) =>
    value == null || value.trim().isEmpty ? message : null;
String? _positive(String? value, String message) =>
    (int.tryParse(value ?? '') ?? 0) < 1 ? message : null;
String? _nonNegative(String? value, String message) =>
    (double.tryParse((value ?? '').replaceAll(',', '.')) ?? -1) < 0
        ? message
        : null;
num _asNum(Object? value) => value is num ? value : num.tryParse('$value') ?? 0;
String _dateTimeLabel(DateTime value) =>
    '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}/${value.year} Ã‚Â· ${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';
Map<String, dynamic> _map(Object? value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};
List<Map<String, dynamic>> _list(Object? value) => value is List
    ? value
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList()
    : <Map<String, dynamic>>[];
