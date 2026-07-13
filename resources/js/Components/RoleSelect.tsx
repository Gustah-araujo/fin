import { Label } from '@/components/ui/label';

interface RoleSelectProps {
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
}

const roles = [
    { value: 'admin', label: 'Administrador' },
    { value: 'editor', label: 'Editor' },
    { value: 'viewer', label: 'Visualizador' },
];

export default function RoleSelect({ value, onChange, disabled }: RoleSelectProps) {
    return (
        <div className="space-y-2">
            <Label>Papel</Label>
            <select
                value={value}
                onChange={(e) => onChange(e.target.value)}
                disabled={disabled}
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {roles.map((role) => (
                    <option key={role.value} value={role.value}>
                        {role.label}
                    </option>
                ))}
            </select>
        </div>
    );
}
