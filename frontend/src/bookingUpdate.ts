export type BookingUpdateItem = { ticket_type_ulid: string; quantity: number };

export type BookingUpdatePayload = {
  phone: string;
  save_phone_to_profile: boolean;
  notes: string | null;
  adults_count: number;
  children_count: number;
  items: BookingUpdateItem[];
};

export const buildBookingUpdatePayload = (input: BookingUpdatePayload): BookingUpdatePayload => ({
  ...input,
  phone: input.phone.trim(),
  notes: input.notes?.trim() || null,
});

type RunBookingUpdateOptions<T> = {
  isLoading: () => boolean;
  setLoading: (loading: boolean) => void;
  request: () => Promise<T>;
  onSuccess: (response: T) => void | Promise<void>;
  onError: (error: unknown) => void;
};

export const runBookingUpdate = async <T>(options: RunBookingUpdateOptions<T>): Promise<boolean> => {
  if (options.isLoading()) return false;
  options.setLoading(true);
  try {
    const response = await options.request();
    await options.onSuccess(response);
    return true;
  } catch (error) {
    options.onError(error);
    return false;
  } finally {
    options.setLoading(false);
  }
};