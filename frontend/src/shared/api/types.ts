export type Role = 'hospital_admin' | 'supervisor' | 'staff';

export interface Session {
    user: {
        id: string;
        name: string;
        email: string;
    };
    membership: {
        id: string;
        role: Role;
        tenant: {
            id: string;
            name: string;
            timezone: string;
        };
    };
}

export interface Employee {
    id: string;
    employee_code: string;
    full_name: string;
    status: 'active' | 'inactive';
    inactive_from: string | null;
}

export interface Ward {
    id: string;
    code: string;
    name: string;
    status: string;
}

export interface ShiftDefinition {
    id: string;
    code: string;
    name: string;
    start_time: string;
    end_time: string;
    ends_next_day: boolean;
    break_minutes: number;
    status: string;
}

export interface RosterSummary {
    id: string;
    status: string;
    lock_version: number;
    period_start: string;
    period_end: string;
    ward: Pick<Ward, 'id' | 'code' | 'name'>;
}

export interface Assignment {
    id: string;
    employee_id: string;
    shift_definition_id: string;
    duty_date: string;
    starts_at: string;
    ends_at: string;
    ends_next_day: boolean;
    shift_code: string;
    shift_name: string;
    start_time: string;
    end_time: string;
}

export interface RosterGridData {
    id: string;
    status: string;
    lock_version: number;
    period_start: string;
    period_end: string;
    timezone: string;
    ward: Pick<Ward, 'id' | 'code' | 'name'>;
    days: string[];
    staff_truncated: boolean;
    staff: Employee[];
    shift_definitions: ShiftDefinition[];
    assignments: Assignment[];
}

export interface Page<T> {
    data: T[];
    meta: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
    };
}

export interface DataResponse<T> {
    data: T;
}
