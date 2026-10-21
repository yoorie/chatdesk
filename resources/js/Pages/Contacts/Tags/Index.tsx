import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import Button from '@/Components/UI/Button';
import { Card, CardContent } from '@/Components/UI/Card';
import { IconButton, PageHeader } from '@/Components/UI/Elements';
import { EmptyState } from '@/Components/UI/EmptyState';
import { useConfirm } from '@/hooks/useConfirm';
import { useToast } from '@/hooks/useToast';
import AppShell from '@/Layouts/AppShell';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Tag, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface TagRow {
  id: number;
  name: string;
  color: string;
  description: string | null;
  contacts_count: number;
  created_at: string;
}

type TagFormData = { name: string; color: string; description: string };

function TagFields({
  data,
  setData,
  errors,
  autoFocus,
}: {
  data: TagFormData;
  setData: (key: keyof TagFormData, value: string) => void;
  errors: Partial<Record<keyof TagFormData, string>>;
  autoFocus?: boolean;
}) {
  return (
    <div className="flex flex-1 flex-wrap items-start gap-2">
      <div className="flex min-w-0 flex-1 items-center gap-2 sm:max-w-xs">
        <input
          type="color"
          value={data.color}
          onChange={(e) => setData('color', e.target.value)}
          className="h-9 w-10 shrink-0 cursor-pointer rounded-btn border border-gray-200 bg-white p-1 dark:border-ui-dark-border dark:bg-ui-dark-surface"
          aria-label="Colour"
          title="Colour"
        />
        <div className="min-w-0 flex-1">
          <TextInput
            value={data.name}
            onChange={(e) => setData('name', e.target.value)}
            className="h-9 w-full"
            placeholder="Tag name, e.g. VIP"
            required
            autoFocus={autoFocus}
          />
          <InputError message={errors.name} className="mt-1" />
        </div>
      </div>
      <TextInput
        value={data.description}
        onChange={(e) => setData('description', e.target.value)}
        className="h-9 min-w-[160px] flex-1"
        placeholder="Note (optional)"
      />
    </div>
  );
}

function EditTagRow({ tag, onDone }: { tag: TagRow; onDone: () => void }) {
  const { toast } = useToast();
  const { data, setData, put, processing, errors } = useForm<TagFormData>({
    name: tag.name,
    color: tag.color,
    description: tag.description || '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(route('app.contacts.tags.update', { tag: tag.id }), {
      preserveScroll: true,
      onSuccess: () => {
        onDone();
        toast.success('Tag updated');
      },
      onError: () => toast.error('Could not update the tag'),
    });
  };

  return (
    <form onSubmit={handleSubmit} className="flex w-full flex-col gap-2 sm:flex-row sm:items-start">
      <TagFields data={data} setData={setData} errors={errors} autoFocus />
      <div className="flex gap-2">
        <Button type="submit" size="sm" className="h-9" disabled={processing}>
          Save
        </Button>
        <Button type="button" size="sm" variant="secondary" className="h-9" onClick={onDone}>
          Cancel
        </Button>
      </div>
    </form>
  );
}

export default function TagsIndex({ tags }: { account: any; tags: TagRow[] }) {
  const { toast } = useToast();
  const confirm = useConfirm();
  const [editingId, setEditingId] = useState<number | null>(null);
  const [showCreate, setShowCreate] = useState(false);

  const {
    data: createData,
    setData: setCreateData,
    post: postCreate,
    processing: creating,
    errors: createErrors,
    reset: resetCreate,
  } = useForm<TagFormData>({
    name: '',
    color: '#3B82F6',
    description: '',
  });

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    postCreate(route('app.contacts.tags.store'), {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Tag created');
        setShowCreate(false);
        resetCreate();
      },
      onError: () => toast.error('Could not create the tag'),
    });
  };

  const handleDelete = async (tag: TagRow) => {
    const confirmed = await confirm({
      title: 'Delete tag',
      message: `Delete "${tag.name}"? It is removed from all contacts. The contacts stay.`,
      confirmText: 'Delete tag',
      variant: 'danger',
    });
    if (!confirmed) return;
    router.delete(route('app.contacts.tags.destroy', { tag: tag.id }), {
      preserveScroll: true,
      onSuccess: () => toast.success('Tag deleted'),
      onError: () => toast.error('Could not delete the tag'),
    });
  };

  return (
    <AppShell>
      <Head title="Tags" />
      <div className="module-page max-w-4xl">
        <PageHeader
          title="Tags"
          description={
            <>
              Label contacts, like VIP or New lead.{' '}
              <Link
                href={route('app.contacts.index')}
                className="font-medium text-ui-green-dark hover:underline dark:text-emerald-300"
              >
                Back to contacts
              </Link>
            </>
          }
          actions={
            !showCreate && (
              <Button onClick={() => setShowCreate(true)}>
                <Plus className="h-4 w-4" />
                New tag
              </Button>
            )
          }
        />

        {showCreate && (
          <Card className="border-transparent dark:border-slate-700/80">
            <CardContent className="p-4">
              <form
                onSubmit={handleCreate}
                className="flex flex-col gap-2 sm:flex-row sm:items-start"
              >
                <TagFields
                  data={createData}
                  setData={setCreateData}
                  errors={createErrors}
                  autoFocus
                />
                <div className="flex gap-2">
                  <Button type="submit" className="h-9" disabled={creating}>
                    Create
                  </Button>
                  <Button
                    type="button"
                    variant="secondary"
                    className="h-9"
                    onClick={() => {
                      setShowCreate(false);
                      resetCreate();
                    }}
                  >
                    Cancel
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        )}

        {tags.length === 0 ? (
          !showCreate && (
            <Card className="border-transparent dark:border-slate-700/80">
              <CardContent className="p-0">
                <EmptyState
                  icon={Tag}
                  title="No tags yet"
                  description="Tags help you find and group contacts fast."
                  action={
                    <Button onClick={() => setShowCreate(true)}>
                      <Plus className="h-4 w-4" />
                      New tag
                    </Button>
                  }
                />
              </CardContent>
            </Card>
          )
        ) : (
          <Card className="overflow-hidden border-transparent dark:border-slate-700/80">
            <ul className="divide-y divide-gray-100 dark:divide-ui-dark-border">
              {tags.map((tag) => (
                <li key={tag.id} className="px-4 py-3">
                  {editingId === tag.id ? (
                    <EditTagRow tag={tag} onDone={() => setEditingId(null)} />
                  ) : (
                    <div className="flex items-center gap-3">
                      <span
                        className="h-3 w-3 shrink-0 rounded-full"
                        style={{ backgroundColor: tag.color }}
                      />
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium text-ui-text dark:text-ui-dark-text">
                          {tag.name}
                        </p>
                        {tag.description && (
                          <p className="truncate text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                            {tag.description}
                          </p>
                        )}
                      </div>
                      <Link
                        href={route('app.contacts.index', { tags: [tag.id] })}
                        className="shrink-0 text-xs font-medium text-ui-green-dark hover:underline dark:text-emerald-300"
                      >
                        {tag.contacts_count} contact{tag.contacts_count !== 1 ? 's' : ''}
                      </Link>
                      <IconButton
                        size="sm"
                        variant="ghost"
                        onClick={() => setEditingId(tag.id)}
                        aria-label={`Edit ${tag.name}`}
                        title="Edit"
                      >
                        <Pencil className="h-4 w-4" />
                      </IconButton>
                      <IconButton
                        size="sm"
                        variant="ghost"
                        onClick={() => handleDelete(tag)}
                        aria-label={`Delete ${tag.name}`}
                        title="Delete"
                        className="hover:text-red-600"
                      >
                        <Trash2 className="h-4 w-4" />
                      </IconButton>
                    </div>
                  )}
                </li>
              ))}
            </ul>
          </Card>
        )}
      </div>
    </AppShell>
  );
}
