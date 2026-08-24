/*
 * Okatana - A Newfoundcodes project.
 *
 * Copyright (C) 2026 Jonathan Eldy Baldivicio
 *
 * Author: Jonathan Eldy Baldivicio
 * Contact: jonathaneldy.baldivicio@newfoundcodes.com
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

import { useEffect, useMemo, useState } from 'react';
import { get, navigate, patch, post } from '../lib/api';
import { toast } from '../lib/toast';
import RichTextEditor from '../components/RichTextEditor';
import TagInput from '../components/TagInput';
import {
  Avatar,
  BreadcrumbButton,
  Breadcrumbs,
  Button,
  EmptyState,
  Field,
  GridLayout,
  LoadingState,
  Page,
  PageHeader,
  Select,
  Table,
  Td,
  TextArea,
  TextInput,
  Th,
} from '../components/Primitives';

export default function DocumentEditorPage({ id = null }) {
  const editing = Boolean(id);
  const [options, setOptions] = useState(null);
  const [document, setDocument] = useState(null);
  const [selectedEditors, setSelectedEditors] = useState([]);
  const [editorQuery, setEditorQuery] = useState('');
  const [candidates, setCandidates] = useState([]);
  const [tagSuggestions, setTagSuggestions] = useState([]);
  const [mentionUsers, setMentionUsers] = useState([]);
  const [saving, setSaving] = useState(false);
  const [failed, setFailed] = useState(false);
  const [form, setForm] = useState({
    title: '',
    organization_id: '',
    project_id: '',
    caption: '',
    content_html: '',
    tag_names: [],
    editor_ids: [],
  });

  useEffect(() => {
    Promise.all([
      get('/app-api/documents/editor-options'),
      editing ? get(`/app-api/documents/${id}`) : Promise.resolve(null),
    ])
      .then(([opts, response]) => {
        setOptions(opts);
        if (response) {
          const row = response.document;
          if (!row.can_edit) {
            throw new Error('Document editor permission is required.');
          }

          setDocument(row);
          setSelectedEditors(row.editors || []);
          setForm({
            title: row.title || '',
            organization_id: row.organization_id,
            project_id: row.project_id || '',
            caption: row.caption || '',
            content_html: row.content_html || '',
            tag_names: (row.tags || []).map((tag) => tag.name),
            editor_ids: (row.editors || []).map((editor) => editor.id),
          });
        } else {
          const organizationId = opts.organizations?.[0]?.id || '';
          setForm((current) => ({ ...current, organization_id: organizationId }));
        }

        setFailed(false);
      })
      .catch((error) => {
        setFailed(true);
        toast.error(error.message);
      });
  }, [id]);

  const availableProjects = useMemo(
    () =>
      (options?.projects || []).filter(
        (project) => project.organization_id === form.organization_id,
      ),
    [options, form.organization_id],
  );
  const isAuthor = !editing || document?.is_author;

  useEffect(() => {
    if (!form.organization_id) {
      setTagSuggestions([]);
      return undefined;
    }

    let cancelled = false;
    get(`/app-api/organizations/${form.organization_id}/document-tags`)
      .then((data) => {
        if (!cancelled) {
          setTagSuggestions(data.tags || []);
        }
      })
      .catch((error) => toast.error(error.message));

    return () => {
      cancelled = true;
    };
  }, [form.organization_id]);

  useEffect(() => {
    if (!form.organization_id) {
      setMentionUsers([]);
      return undefined;
    }

    let cancelled = false;
    const params = new URLSearchParams({ organization_id: form.organization_id });
    if (form.project_id) {
      params.set('project_id', form.project_id);
    }

    get(`/app-api/documents/mention-candidates?${params}`)
      .then((data) => {
        if (!cancelled) {
          setMentionUsers(data.users || []);
        }
      })
      .catch((error) => toast.error(error.message));

    return () => {
      cancelled = true;
    };
  }, [form.organization_id, form.project_id]);

  useEffect(() => {
    if (!isAuthor || !form.organization_id) {
      setCandidates([]);
      return undefined;
    }

    const timer = window.setTimeout(() => {
      const params = new URLSearchParams({ organization_id: form.organization_id });
      if (form.project_id) {
        params.set('project_id', form.project_id);
      }

      if (editorQuery.trim()) {
        params.set('q', editorQuery.trim());
      }

      get(`/app-api/documents/editor-candidates?${params}`)
        .then((data) => setCandidates(data.users || []))
        .catch((error) => toast.error(error.message));
    }, 180);

    return () => window.clearTimeout(timer);
  }, [editorQuery, form.organization_id, form.project_id, isAuthor]);

  const updateScope = (key, value) => {
    if (key === 'organization_id') {
      setForm({
        ...form,
        organization_id: value,
        project_id: '',
        tag_names: [],
        editor_ids: [],
      });

      setSelectedEditors([]);
      setEditorQuery('');
    } else if (key === 'project_id') {
      setForm({ ...form, project_id: value, editor_ids: [] });
      setSelectedEditors([]);
      setEditorQuery('');
    }
  };

  const addEditor = (user) => {
    if (selectedEditors.some((editor) => editor.id === user.id)) {
      return;
    }

    const next = [...selectedEditors, user];
    setSelectedEditors(next);
    setForm({ ...form, editor_ids: next.map((editor) => editor.id) });
    setEditorQuery('');
  };

  const removeEditor = (userId) => {
    const next = selectedEditors.filter((editor) => editor.id !== userId);

    setSelectedEditors(next);
    setForm({ ...form, editor_ids: next.map((editor) => editor.id) });
  };

  const save = async (status) => {
    if (!form.title.trim()) {
      toast.error('Title is required.');
      return;
    }

    if (!form.organization_id) {
      toast.error('Organization is required.');
      return;
    }

    setSaving(true);

    const payload = { ...form, project_id: form.project_id || null, status };
    if (!isAuthor) {
      delete payload.organization_id;
      delete payload.project_id;
      delete payload.editor_ids;
    }

    try {
      const response = editing
        ? await patch(`/app-api/documents/${id}`, payload)
        : await post('/app-api/documents', payload);

      toast.success(status === 'published' ? 'Article published.' : 'Draft saved.');
      navigate(`/app/documents/${response.document.id}`);
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  };

  if (failed) {
    return (
      <Page>
        <EmptyState
          title="Document editor unavailable"
          description="Return to Documents and try opening the editor again."
          action={<Button onClick={() => navigate('/app/documents')}>Back to documents</Button>}
        />
      </Page>
    );
  }

  if (!options || (editing && !document)) {
    return (
      <Page>
        <LoadingState label="Loading document editor…" />
      </Page>
    );
  }

  if (!editing && (!options.organizations?.length || !options.projects?.length)) {
    return (
      <Page>
        <EmptyState
          title="Organization and project required"
          description="Create an organization and at least one project before creating an article."
          action={<Button onClick={() => navigate('/app')}>Go to organizations</Button>}
        />
      </Page>
    );
  }

  return (
    <Page>
      <Breadcrumbs>
        <BreadcrumbButton onClick={() => navigate('/app/documents')}>Documents</BreadcrumbButton>
        <span>/</span>
        <span>{editing ? document.title : 'New document'}</span>
      </Breadcrumbs>
      <PageHeader
        eyebrow={editing ? 'Document editor' : 'New document'}
        title={editing ? 'Edit document' : 'Create document'}
        description={
          editing && !isAuthor
            ? 'You are a selected editor. The author controls organization, project, and the editor list.'
            : 'Compose an organization or project article, select collaborators, then save it as a draft or publish it.'
        }
        actions={
          <>
            <Button disabled={saving} onClick={() => save('draft')}>
              {saving ? 'Saving…' : 'Save draft'}
            </Button>
            <Button variant="primary" disabled={saving} onClick={() => save('published')}>
              {saving ? 'Saving…' : 'Publish'}
            </Button>
          </>
        }
      />

      <form className="grid gap-8" onSubmit={(event) => event.preventDefault()}>
        <Field label="Title">
          <TextInput
            required
            value={form.title}
            onChange={(event) => setForm({ ...form, title: event.target.value })}
          />
        </Field>
        <GridLayout>
          <Field label="Organization" className="col-span-4 md:col-span-4 xl:col-span-6">
            <Select
              disabled={editing || !isAuthor}
              value={form.organization_id}
              onChange={(event) => updateScope('organization_id', event.target.value)}
            >
              <option value="">Select organization</option>
              {options.organizations.map((organization) => (
                <option key={organization.id} value={organization.id}>
                  {organization.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field
            label="Project"
            hint="Optional. A project document follows that project's access list."
            className="col-span-4 md:col-span-4 xl:col-span-6"
          >
            <Select
              disabled={!isAuthor || !form.organization_id}
              value={form.project_id}
              onChange={(event) => updateScope('project_id', event.target.value)}
            >
              <option value="">Organization-wide document</option>
              {availableProjects.map((project) => (
                <option key={project.id} value={project.id}>
                  {project.name} · {project.key}
                </option>
              ))}
            </Select>
          </Field>
        </GridLayout>
        <Field label="Caption" hint="Short description used in document lists.">
          <TextArea
            value={form.caption}
            maxLength={2000}
            onChange={(event) => setForm({ ...form, caption: event.target.value })}
          />
        </Field>
        <TagInput
          value={form.tag_names}
          onChange={(value) => setForm({ ...form, tag_names: value })}
          suggestions={tagSuggestions}
          label="Tags"
          ariaLabel="Add article tag"
        />
        <Field label="Contents">
          <RichTextEditor
            value={form.content_html}
            onChange={(value) => setForm({ ...form, content_html: value })}
            size="large"
            mentionUsers={mentionUsers}
          />
        </Field>

        <section className="border-t-2 border-ink pt-6">
          <div className="mb-6">
            <h2 className="text-lg font-semibold text-ink">Editors</h2>
            <p className="mt-2 max-w-3xl text-sm text-ink-muted">
              Selected users can view drafts and edit the article. Search by full name or email
              address.
            </p>
          </div>
          {isAuthor ? (
            <>
              <GridLayout className="mb-6">
                <Field label="Find editor" className="col-span-4 md:col-span-6 xl:col-span-6">
                  <TextInput
                    type="search"
                    value={editorQuery}
                    placeholder="Name or email"
                    onChange={(event) => setEditorQuery(event.target.value)}
                  />
                </Field>
              </GridLayout>
              {editorQuery.trim() && (
                <div className="mb-8 border-t border-line">
                  <Table>
                    <thead>
                      <tr>
                        <Th className="pr-6">User</Th>
                        <Th>Email</Th>
                      </tr>
                    </thead>
                    <tbody>
                      {candidates
                        .filter(
                          (candidate) =>
                            !selectedEditors.some((editor) => editor.id === candidate.id),
                        )
                        .map((candidate) => (
                          <tr
                            key={candidate.id}
                            className="cursor-pointer"
                            onClick={() => addEditor(candidate)}
                          >
                            <Td className="pr-6">
                              <div className="flex items-center gap-2">
                                <Avatar user={candidate} size="sm" />
                                <span className="font-medium">{candidate.name}</span>
                              </div>
                            </Td>
                            <Td className="text-ink-muted">{candidate.email}</Td>
                          </tr>
                        ))}
                    </tbody>
                  </Table>
                </div>
              )}
            </>
          ) : null}

          {selectedEditors.length ? (
            <div className="border-t border-line">
              <Table>
                <thead>
                  <tr>
                    <Th className="pr-6">Selected editor</Th>
                    <Th className="pr-6">Email</Th>
                    {isAuthor && <Th>Actions</Th>}
                  </tr>
                </thead>
                <tbody>
                  {selectedEditors.map((editor) => (
                    <tr key={editor.id}>
                      <Td className="pr-6">
                        <div className="flex items-center gap-2">
                          <Avatar user={editor} size="sm" />
                          <span className="font-medium">{editor.name}</span>
                        </div>
                      </Td>
                      <Td className="pr-6 text-ink-muted">{editor.email}</Td>
                      {isAuthor && (
                        <Td>
                          <Button compact variant="danger" onClick={() => removeEditor(editor.id)}>
                            Remove
                          </Button>
                        </Td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </Table>
            </div>
          ) : (
            <p className="border-t border-line py-6 text-sm text-ink-muted">
              No additional editors selected.
            </p>
          )}
        </section>

        <div className="flex flex-wrap gap-2 border-t border-line pt-6">
          <Button onClick={() => navigate(editing ? `/app/documents/${id}` : '/app/documents')}>
            Cancel
          </Button>
          <Button disabled={saving} onClick={() => save('draft')}>
            Save draft
          </Button>
          <Button variant="primary" disabled={saving} onClick={() => save('published')}>
            Publish
          </Button>
        </div>
      </form>
    </Page>
  );
}
