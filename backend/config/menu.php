<?php

return ['max_upload_kb' => (int) env('MENU_IMPORT_MAX_KB', 5120), 'max_archive_entries' => 500, 'max_uncompressed_bytes' => 20_000_000, 'preorder_edit_deadline_minutes' => (int) env('PREORDER_EDIT_DEADLINE_MINUTES', 120)];
