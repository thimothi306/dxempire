import { useRef, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, Trash2, ImageOff } from 'lucide-react';
import toast from 'react-hot-toast';
import { catalogImageService } from '../../services';
import { Card, Button, PageHeader, Spinner, Modal, Input, Select } from '../../components/ui';

const CATEGORIES = [
  { value: 'phone', label: 'Phone' },
  { value: 'laptop', label: 'Laptop' },
];

interface CatalogImage {
  id: number;
  brand: string;
  model: string;
  category: string;
  image_url: string;
}

const EMPTY_FORM = { brand: '', model: '', category: 'phone' };
type FormState = typeof EMPTY_FORM;

// Defined OUTSIDE the page component so React doesn't remount the inputs
// (and drop focus) on every keystroke — see EmployeesPage/HierarchyPage for
// the same fix applied earlier.
function UploadForm({
  form, setForm, file, setFile, onSubmit, onCancel, loading,
}: {
  form: FormState;
  setForm: (f: FormState) => void;
  file: File | null;
  setFile: (f: File | null) => void;
  onSubmit: () => void;
  onCancel: () => void;
  loading: boolean;
}) {
  const fileInputRef = useRef<HTMLInputElement>(null);
  const preview = file ? URL.createObjectURL(file) : null;

  return (
    <div className="space-y-4">
      <p className="text-xs text-gray-500">
        One photo per Brand + Model + Category. Uploading again for the same combination replaces the existing photo.
      </p>
      <Input label="Brand *" value={form.brand} onChange={(e) => setForm({ ...form, brand: e.target.value })} placeholder="e.g. Apple" />
      <Input label="Model *" value={form.model} onChange={(e) => setForm({ ...form, model: e.target.value })} placeholder="e.g. iPhone 13" />
      <Select label="Category *" value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} options={CATEGORIES} />

      <div className="flex flex-col gap-1">
        <label className="text-xs font-medium text-gray-600">Photo * (JPG, PNG or WEBP, max 4MB)</label>
        <input
          ref={fileInputRef}
          type="file"
          accept="image/jpeg,image/png,image/webp"
          onChange={(e) => setFile(e.target.files?.[0] ?? null)}
          className="text-sm border border-gray-300 rounded-lg px-3 py-2 file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-primary/10 file:text-primary file:text-xs file:font-medium"
        />
      </div>

      {preview && (
        <div className="border border-gray-200 rounded-lg p-2 w-fit">
          <img src={preview} alt="Preview" className="h-32 w-32 object-cover rounded" />
        </div>
      )}

      <div className="flex gap-3 pt-2">
        <Button onClick={onSubmit} loading={loading} className="flex-1 justify-center">Upload</Button>
        <Button variant="outline" onClick={onCancel} className="flex-1 justify-center">Cancel</Button>
      </div>
    </div>
  );
}

export default function CatalogImagesPage() {
  const qc = useQueryClient();
  const [showUpload, setShowUpload] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<CatalogImage | null>(null);
  const [form, setForm] = useState(EMPTY_FORM);
  const [file, setFile] = useState<File | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ['catalog-images'],
    queryFn: () => catalogImageService.list(),
  });

  const uploadMut = useMutation({
    mutationFn: () => {
      const fd = new FormData();
      fd.append('brand', form.brand);
      fd.append('model', form.model);
      fd.append('category', form.category);
      if (file) fd.append('image', file);
      return catalogImageService.upload(fd);
    },
    onSuccess: () => {
      toast.success('Catalog image uploaded');
      qc.invalidateQueries({ queryKey: ['catalog-images'] });
      setShowUpload(false);
      setForm(EMPTY_FORM);
      setFile(null);
    },
    onError: (err: any) => toast.error(err?.response?.data?.message || 'Upload failed'),
  });

  const deleteMut = useMutation({
    mutationFn: (id: number) => catalogImageService.remove(id),
    onSuccess: () => {
      toast.success('Image removed');
      qc.invalidateQueries({ queryKey: ['catalog-images'] });
      setDeleteTarget(null);
    },
    onError: () => toast.error('Failed to remove image'),
  });

  const images: CatalogImage[] = Array.isArray(data) ? data : [];

  return (
    <div>
      <PageHeader
        title="Catalog Images"
        subtitle={`${images.length} model photo${images.length === 1 ? '' : 's'} — shown to partners in the app catalog`}
        action={<Button onClick={() => { setForm(EMPTY_FORM); setFile(null); setShowUpload(true); }}><Plus size={15} /> Upload Image</Button>}
      />

      <Card className="p-5">
        {isLoading ? <Spinner /> : images.length === 0 ? (
          <div className="text-center py-12 text-gray-400">
            <ImageOff size={32} className="mx-auto mb-2" />
            <p className="text-sm">No catalog images uploaded yet.</p>
          </div>
        ) : (
          <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            {images.map((img) => (
              <div key={img.id} className="border border-gray-200 rounded-lg overflow-hidden group relative">
                <img src={img.image_url} alt={img.model} className="w-full aspect-square object-cover bg-gray-50" />
                <div className="p-2">
                  <div className="text-xs font-semibold truncate">{img.brand} {img.model}</div>
                  <div className="text-[11px] text-gray-400 capitalize">{img.category}</div>
                </div>
                <button
                  onClick={() => setDeleteTarget(img)}
                  className="absolute top-2 right-2 bg-white/90 hover:bg-red-50 text-red-500 rounded-full p-1.5 opacity-0 group-hover:opacity-100 transition-opacity shadow-sm"
                  title="Remove image"
                >
                  <Trash2 size={13} />
                </button>
              </div>
            ))}
          </div>
        )}
      </Card>

      <Modal open={showUpload} onClose={() => setShowUpload(false)} title="Upload Catalog Image">
        <UploadForm
          form={form} setForm={setForm} file={file} setFile={setFile}
          onSubmit={() => uploadMut.mutate()}
          onCancel={() => setShowUpload(false)}
          loading={uploadMut.isPending}
        />
      </Modal>

      <Modal open={!!deleteTarget} onClose={() => setDeleteTarget(null)} title="Remove Image">
        <div className="space-y-4">
          <p className="text-sm text-gray-600">
            Remove the photo for <span className="font-semibold">{deleteTarget?.brand} {deleteTarget?.model}</span>?
            Partners will see a placeholder until a new photo is uploaded.
          </p>
          <div className="flex gap-3">
            <Button variant="danger" onClick={() => deleteMut.mutate(deleteTarget!.id)} loading={deleteMut.isPending} className="flex-1 justify-center">Remove</Button>
            <Button variant="outline" onClick={() => setDeleteTarget(null)} className="flex-1 justify-center">Cancel</Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
