import assert from 'node:assert/strict';
import test from 'node:test';
import { buildBookingUpdatePayload, runBookingUpdate } from './bookingUpdate.ts';

const payload = buildBookingUpdatePayload({
  phone: ' +39 320 1234567 ',
  save_phone_to_profile: true,
  notes: ' Tavolo accessibile ',
  adults_count: 2,
  children_count: 1,
  items: [{ ticket_type_ulid: '01TESTTICKETULID', quantity: 3 }],
});

test('confirmation builds the complete booking update payload', () => {
  assert.deepEqual(payload, {
    phone: '+39 320 1234567',
    save_phone_to_profile: true,
    notes: 'Tavolo accessibile',
    adults_count: 2,
    children_count: 1,
    items: [{ ticket_type_ulid: '01TESTTICKETULID', quantity: 3 }],
  });
});

test('confirmation calls the API once and closes the modal on success', async () => {
  let loading = false;
  let calls = 0;
  let modalOpen = true;
  const result = await runBookingUpdate({
    isLoading: () => loading,
    setLoading: (value) => { loading = value; },
    request: async () => { calls += 1; return { data: { ulid: '01BOOKING' } }; },
    onSuccess: () => { modalOpen = false; },
    onError: () => assert.fail('unexpected error'),
  });
  assert.equal(result, true);
  assert.equal(calls, 1);
  assert.equal(modalOpen, false);
  assert.equal(loading, false);
});

test('an API error keeps modal and draft data', async () => {
  let loading = false;
  const draft = { phone: payload.phone, notes: payload.notes, modalOpen: true };
  let captured: unknown;
  const result = await runBookingUpdate({
    isLoading: () => loading,
    setLoading: (value) => { loading = value; },
    request: async () => { throw new Error('422'); },
    onSuccess: () => assert.fail('unexpected success'),
    onError: (error) => { captured = error; },
  });
  assert.equal(result, false);
  assert.equal(draft.modalOpen, true);
  assert.equal(draft.phone, payload.phone);
  assert.equal(draft.notes, payload.notes);
  assert.match(String(captured), /422/);
});

test('a double click is blocked while the first request is pending', async () => {
  let loading = false;
  let calls = 0;
  let resolveRequest!: () => void;
  const pending = new Promise<void>((resolve) => { resolveRequest = resolve; });
  const options = {
    isLoading: () => loading,
    setLoading: (value: boolean) => { loading = value; },
    request: async () => { calls += 1; await pending; return { data: true }; },
    onSuccess: () => undefined,
    onError: () => assert.fail('unexpected error'),
  };
  const first = runBookingUpdate(options);
  const second = await runBookingUpdate(options);
  assert.equal(second, false);
  assert.equal(calls, 1);
  resolveRequest();
  assert.equal(await first, true);
});