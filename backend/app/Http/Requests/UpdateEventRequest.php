<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends StoreEventRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event && $this->user()?->can('update', $event) === true;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $event = $this->route('event');
        $ticketUlidExists = Rule::exists('ticket_types', 'ulid');
        if ($event instanceof Event) {
            $ticketUlidExists->where('event_id', $event->id);
        }

        $rules['ticket_types.*.ulid'] = [
            'nullable',
            'string',
            'distinct',
            $ticketUlidExists,
        ];

        return $rules;
    }
}
