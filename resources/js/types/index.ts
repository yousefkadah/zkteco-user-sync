export interface Device {
    id: number;
    name: string;
    ip_address: string;
    port: number;
    comm_key: number | null;
    serial_number: string | null;
    notes: string | null;
    last_connection_ok: boolean;
    last_connected_at: string | null;
}

export interface DeviceLite {
    id: number;
    name: string;
    ip_address: string;
    last_connection_ok?: boolean;
}

export interface BatchSummary {
    id: number;
    original_filename: string;
    total_rows: number;
    valid_rows: number;
    invalid_rows: number;
    status: string;
    synced_count: number;
    failed_count: number;
    device: { id: number; name?: string } | null;
    sync_started_at: string | null;
    sync_finished_at: string | null;
    created_at: string | null;
}

export interface ImportedUserRow {
    id: number;
    row_number: number;
    user_id: string;
    name: string;
    name_ascii: string;
    password: string | null;
    card_number: string | null;
    privilege: string;
    is_valid: boolean;
    validation_errors: string[];
    device_uid: number | null;
    sync_status: string;
    sync_error: string | null;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface SharedPageProps {
    app: { name: string; version: string; platform?: 'darwin' | 'windows' | 'linux' };
    flash: { success: string | null; error: string | null };
    [key: string]: unknown;
}
