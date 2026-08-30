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

import { useEffect, useState } from 'react';
import { del, get, navigate, post } from '../lib/api';
import { toast } from '../lib/toast';
import ConfirmDialog from '../components/ConfirmDialog';
import RichTextEditor from '../components/RichTextEditor';
import {
  ActionLink,
  Avatar,
  BreadcrumbButton,
  Breadcrumbs,
  Button,
  EmptyState,
  GridLayout,
  LoadingState,
  Page,
  PageHeader,
  SectionHeading,
} from '../components/Primitives';
import MetaItem from '../components/Documents/MetaItem';
import DocumentComment from '../components/Documents/DocumentComment';

export default function DocumentViewPage({ id }) {
  const [document, setDocument] = useState(null);
  const [failed, setFailed] = useState(false);
  const [comment, setComment] = useState('');
  const [posting, setPosting] = useState(false);
  const [deleteArticleOpen, setDeleteArticleOpen] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const load = () =>
    get(`/app-api/documents/${id}`)
      .then((data) => {
        setDocument(data.document);
        setFailed(false);
      })
      .catch((error) => {
        setFailed(true);
        toast.error(error.message);
      });

  const toggleFavorite = async () => {
    try {
      const saved = !document.is_favorite;
      await post(`/app-api/documents/${id}/favorite`, { saved });

      setDocument({ ...document, is_favorite: saved });
      toast.success(saved ? 'Article saved to favorites.' : 'Article removed from favorites.');
    } catch (error) {
      toast.error(error.message);
    }
  };

  const toggleArchive = async () => {
    try {
      const archived = !document.archived_at;
      const response = await post(`/app-api/documents/${id}/archive`, { archived });

      setDocument(response.document);
      toast.success(archived ? 'Article archived.' : 'Article unarchived.');
    } catch (error) {
      toast.error(error.message);
    }
  };

  const deleteArticle = async () => {
    setDeleting(true);

    try {
      await del(`/app-api/documents/${id}`);

      toast.success('Article deleted.');
      navigate('/app/documents');
    } catch (error) {
      toast.error(error.message);
      setDeleting(false);
    }
  };

  const postComment = async () => {
    if (!comment || !comment.replace(/<[^>]+>/g, '').trim()) {
      toast.error('Comment content is required.');
      return;
    }

    setPosting(true);

    try {
      const response = await post(`/app-api/documents/${id}/comments`, {
        body_html: comment,
      });

      setDocument({
        ...document,
        comments: [...(document.comments || []), response.comment],
      });
      setComment('');

      toast.success('Comment posted.');
    } catch (error) {
      toast.error(error.message);
    } finally {
      setPosting(false);
    }
  };

  useEffect(() => {
    load();
  }, [id]);

  if (failed) {
    return (
      <Page>
        <EmptyState
          title="Article unavailable"
          description="You may not have access to this document, or it may have been removed."
          action={<Button onClick={() => navigate('/app/documents')}>Back to documents</Button>}
        />
      </Page>
    );
  }

  if (!document) {
    return (
      <Page>
        <LoadingState label="Loading article…" />
      </Page>
    );
  }

  return (
    <Page>
      <Breadcrumbs>
        <BreadcrumbButton onClick={() => navigate('/app/documents')}>Documents</BreadcrumbButton>
        <span>/</span>
        <span>{document.title}</span>
      </Breadcrumbs>
      <PageHeader
        eyebrow={
          document.archived_at
            ? 'Archived article'
            : document.status === 'draft'
              ? 'Draft article'
              : 'Published article'
        }
        title={document.title}
        description={document.caption || undefined}
        actions={
          <>
            <Button onClick={toggleFavorite}>{document.is_favorite ? 'Unsave' : 'Save'}</Button>
            {document.can_edit && (
              <Button onClick={toggleArchive}>
                {document.archived_at ? 'Unarchive' : 'Archive'}
              </Button>
            )}
            {document.can_edit && (
              <Button onClick={() => navigate(`/app/documents/${id}/edit`)}>Edit</Button>
            )}
            <ActionLink href={`/app-api/documents/${id}/pdf`}>Print / PDF</ActionLink>
            {document.can_delete && (
              <Button variant="danger" onClick={() => setDeleteArticleOpen(true)}>
                Delete
              </Button>
            )}
          </>
        }
      />

      <GridLayout className="mb-12">
        <MetaItem label="Author">
          <div className="flex items-center gap-2">
            <Avatar user={document.api_credential || document.author || { name: 'Unknown author' }} size="sm" />
            <span>{document.api_credential?.name || document.author?.name || 'Unknown author'}</span>
          </div>
        </MetaItem>
        <MetaItem label="Organization">{document.organization?.name || 'Organization'}</MetaItem>
        <MetaItem label="Project">{document.project?.name || 'Organization-wide'}</MetaItem>
        <MetaItem label="Published">
          {document.published_at ? new Date(document.published_at).toLocaleString() : 'Draft'}
        </MetaItem>
        <MetaItem label="Last updated">{new Date(document.updated_at).toLocaleString()}</MetaItem>
        <div className="col-span-4 border-t border-line pt-4 md:col-span-8 xl:col-span-12">
          <div className="text-sm text-ink-muted">Tags</div>
          <div className="mt-2 flex flex-wrap gap-x-4 gap-y-2">
            {document.tags?.length ? (
              document.tags.map((tag) => (
                <span key={tag.id} className="text-sm font-medium text-ink">
                  #{tag.name}
                </span>
              ))
            ) : (
              <span className="text-sm text-ink-muted">No tags</span>
            )}
          </div>
        </div>
      </GridLayout>

      <article className="border-t-2 border-ink pt-8">
        <div
          className="wysiwyg-content mx-auto max-w-4xl"
          dangerouslySetInnerHTML={{
            __html: document.content_html || '<p>No content.</p>',
          }}
        />
      </article>

      {document.editors?.length ? (
        <section className="mt-12 border-t border-line pt-6">
          <SectionHeading
            title="Editors"
            description="Users who can edit this article and view it while it is a draft."
          />
          <div className="flex flex-wrap gap-4">
            {document.editors.map((editor) => (
              <div key={editor.id} className="flex items-center gap-2">
                <Avatar user={editor} size="sm" />
                <span className="text-sm">{editor.name}</span>
              </div>
            ))}
          </div>
        </section>
      ) : null}

      <section className="mt-12 border-t-2 border-ink pt-6">
        <SectionHeading
          title="Comments"
          description={`${document.comments?.length || 0} comment${document.comments?.length === 1 ? '' : 's'} on this article.`}
        />
        <div>
          {document.comments?.length ? (
            document.comments.map((value) => (
              <DocumentComment
                key={value.id}
                value={value}
                document={document}
                setDocument={setDocument}
              />
            ))
          ) : (
            <p className="border-b border-line py-6 text-sm text-ink-muted">
              No comments yet — post the first comment.
            </p>
          )}
        </div>
        <div className="mt-8 grid gap-4">
          <RichTextEditor
            value={comment}
            onChange={setComment}
            size="compact"
            placeholder="Write a comment…"
            mentionUsers={document.mention_users || []}
          />
          <div>
            <Button variant="primary" disabled={posting} onClick={postComment}>
              {posting ? 'Posting…' : 'Post comment'}
            </Button>
          </div>
        </div>
      </section>

      <ConfirmDialog
        open={deleteArticleOpen}
        title="Delete article"
        description={`Delete “${document.title}”? The article is soft-deleted and its audit history remains immutable.`}
        confirmLabel="Delete article"
        onCancel={() => setDeleteArticleOpen(false)}
        onConfirm={deleteArticle}
        busy={deleting}
      />
    </Page>
  );
}
