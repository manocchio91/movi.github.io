import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../role_data/role_api_repository.dart';
import '../shared/ui/real_components.dart';

class OrganizerEventFormScreen extends ConsumerStatefulWidget {
  const OrganizerEventFormScreen({super.key});

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
  bool _isFree = true;
  bool _familyFriendly = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _categories = ref
        .read(roleApiRepositoryProvider)
        .get('/categories')
        .then((body) => _list(body['data']));
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
        title: 'Crea evento',
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.primaryContainer,
              borderRadius: BorderRadius.circular(22),
            ),
            child: const Row(children: [
              Icon(Icons.add_circle_rounded, size: 42),
              SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Nuova esperienza',
                        style: TextStyle(
                            fontSize: 20, fontWeight: FontWeight.w900)),
                    SizedBox(height: 4),
                    Text(
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
                      onChanged: (value) => setState(() => _categoryId = value),
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
                    child: Text('Il Comune è obbligatorio.',
                        style:
                            TextStyle(color: Colors.redAccent, fontSize: 12)),
                  ),
                const SectionTitle('Data e disponibilità'),
                OutlinedButton.icon(
                  onPressed: _chooseDateTime,
                  icon: const Icon(Icons.calendar_month_rounded),
                  label: Text(_startsAt == null
                      ? 'Scegli data e ora'
                      : _dateTimeLabel(_startsAt!)),
                ),
                const SizedBox(height: 12),
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
                  subtitle:
                      const Text('Il prezzo del ticket sarà impostato a zero.'),
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
                          labelText: 'Prezzo €', prefixText: '€ '),
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
                      decoration: const InputDecoration(labelText: 'Quantità'),
                      validator: (value) =>
                          _positive(value, 'Quantità non valida.'),
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
    if (value != null) setState(() => _municipality = value);
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
      await ref.read(roleApiRepositoryProvider).post('/organizer/events', {
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
            'name': _ticketName.text.trim(),
            'audience': _audience,
            'price_cents': priceCents,
            'max_quantity': maxQuantity,
            'is_active': true,
          }
        ],
      });
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
String _dateTimeLabel(DateTime value) =>
    '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}/${value.year} · ${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';
Map<String, dynamic> _map(Object? value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};
List<Map<String, dynamic>> _list(Object? value) => value is List
    ? value
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList()
    : <Map<String, dynamic>>[];
