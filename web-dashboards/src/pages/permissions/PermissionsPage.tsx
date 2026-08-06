import { useEffect, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { permissionService } from '../../services';
import { Card, Button, PageHeader, Spinner } from '../../components/ui';

interface RoleRow {
  id: number;
  name: string;
  permissions: string[];
}

export default function PermissionsPage() {
  const qc = useQueryClient();
  const [selectedRoleId, setSelectedRoleId] = useState<number | null>(null);
  const [draft, setDraft] = useState<string[]>([]);

  const { data: permData, isLoading: loadingPerms } = useQuery({ queryKey: ['permissions'], queryFn: permissionService.list });
  const { data: roleData, isLoading: loadingRoles } = useQuery({ queryKey: ['permission-roles'], queryFn: permissionService.roles });

  const allPermissions: string[] = Array.isArray(permData) ? permData : [];
  const roles: RoleRow[] = Array.isArray(roleData) ? roleData : [];
  const selectedRole = roles.find((r) => r.id === selectedRoleId) ?? null;

  useEffect(() => {
    if (!selectedRoleId && roles.length > 0) {
      const firstEditable = roles.find((r) => r.name !== 'super_admin') ?? roles[0];
      setSelectedRoleId(firstEditable.id);
    }
  }, [roles, selectedRoleId]);

  useEffect(() => {
    if (selectedRole) setDraft(selectedRole.permissions);
  }, [selectedRole?.id]);

  const saveMut = useMutation({
    mutationFn: () => permissionService.updateRole(selectedRole!.id, draft),
    onSuccess: () => {
      toast.success(`Permissions updated for ${selectedRole?.name}`);
      qc.invalidateQueries({ queryKey: ['permission-roles'] });
    },
    onError: (e: any) => toast.error(e?.response?.data?.message || 'Could not update permissions'),
  });

  const toggle = (perm: string) => {
    setDraft((cur) => (cur.includes(perm) ? cur.filter((p) => p !== perm) : [...cur, perm]));
  };

  const isSuperAdmin = selectedRole?.name === 'super_admin';
  const dirty = selectedRole ? JSON.stringify([...draft].sort()) !== JSON.stringify([...selectedRole.permissions].sort()) : false;

  if (loadingPerms || loadingRoles) return <Spinner />;

  return (
    <div>
      <PageHeader title="Permissions" subtitle="Control exactly what each role can access" />

      <div className="grid grid-cols-1 md:grid-cols-4 gap-5">
        <Card className="p-3 md:col-span-1">
          <div className="text-xs font-semibold text-gray-500 px-2 pb-2 uppercase tracking-wide">Roles</div>
          <div className="space-y-1">
            {roles.map((r) => (
              <button
                key={r.id}
                onClick={() => setSelectedRoleId(r.id)}
                className={`w-full text-left px-3 py-2 rounded-lg text-sm capitalize transition-colors ${
                  r.id === selectedRoleId ? 'bg-primary text-white font-medium' : 'hover:bg-gray-100 text-gray-700'
                }`}
              >
                {r.name.replace('_', ' ')}
              </button>
            ))}
          </div>
        </Card>

        <Card className="p-5 md:col-span-3">
          {!selectedRole ? (
            <div className="text-sm text-gray-400">Select a role</div>
          ) : (
            <>
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-semibold text-gray-800 capitalize">{selectedRole.name.replace('_', ' ')}</h3>
                {!isSuperAdmin && (
                  <Button size="sm" onClick={() => saveMut.mutate()} loading={saveMut.isPending} disabled={!dirty}>
                    Save Changes
                  </Button>
                )}
              </div>

              {isSuperAdmin ? (
                <p className="text-sm text-gray-500 bg-gray-50 px-3 py-2 rounded">
                  super_admin always has every permission — this cannot be edited, to prevent locking every admin out at once.
                </p>
              ) : (
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                  {allPermissions.map((perm) => (
                    <label key={perm} className="flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-100 hover:bg-gray-50 cursor-pointer text-sm">
                      <input
                        type="checkbox"
                        checked={draft.includes(perm)}
                        onChange={() => toggle(perm)}
                        className="accent-primary"
                      />
                      <code className="text-xs">{perm}</code>
                    </label>
                  ))}
                </div>
              )}
            </>
          )}
        </Card>
      </div>
    </div>
  );
}
