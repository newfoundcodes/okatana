# Rich text and files

Tickets, ticket comments, document bodies, and document comments share a browser `contentEditable` WYSIWYG editor. The editor is designed for structured operational text, not arbitrary HTML: formatting is constrained in the browser and sanitized again by Laravel before storage.

## Formatting tools

The compact toolbar supports common structural formatting such as headings, emphasis, lists, block quotes, code, links, images, and tables. Controls use SVG icons with accessible labels and tooltips.

Keyboard behavior follows a toolbar pattern:

- Left/Right moves between enabled controls.
- Home/End moves to the first/last control.
- All actions remain buttons rather than implicit form submissions.
- Reduced-motion preferences disable animated transitions.

The content area scrolls horizontally for wide tables and wraps ordinary long text so a ticket or article does not expand the entire page.

## Add links

Use the link control to enter an allowed destination. Server-side sanitization permits:

- relative `/...` paths;
- same-document `#...` fragments;
- `http` and `https` URLs;
- `mailto` links.

Other schemes are removed. Links receive `rel="noopener noreferrer"`; only `_blank` is retained as a target value.

## Mention a teammate

Type `@` to open mention autocomplete. Candidate users are limited to people who can participate in the content’s scope.

1. Continue typing to narrow the list.
2. Use Up/Down to move between results.
3. Use Enter to insert or Escape to close.
4. The editor inserts a non-editable span carrying `data-mention-user-id`.

Laravel extracts the identifier, verifies its format and scope, synchronizes the relevant mention pivot, and creates notifications. A visually typed `@Name` without selecting a candidate is ordinary text and has no notification identity.

### Mention scope

| Content                    | Eligible mention recipients                                                                                |
| -------------------------- | ---------------------------------------------------------------------------------------------------------- |
| Ticket or ticket comment   | explicit project members plus organization owners/admins                                                   |
| Organization-wide document | organization members                                                                                       |
| Project document           | explicit project members plus organization owners/admins                                                   |
| Draft document             | recipients must also be able to view the draft; the author and selected editors are the effective audience |

Self-mentions are removed from notification fan-out. A person directly mentioned in a comment is excluded from the generic participant notification for the same action, preventing duplicate alerts.

## Insert an image

The image tool accepts either an HTTP(S) URL or an upload.

Uploaded editor images must be JPEG, PNG, GIF, or WebP and no larger than 5 MB. They are stored on the `public` Laravel disk under the uploading user, but rendered through the authenticated path:

```text
/app-api/editor/images/{owner-ulid}/{uuid}.{extension}
```

The delivery response is private and includes `nosniff`. Editor images do not depend on a `public/storage` symlink. Portable project/organization exports discover referenced images, embed them as base64 assets, restore them on import, and rewrite both current authenticated URLs and legacy `/storage/editor-images/...` references.

Remote image URLs are retained only when they use HTTP or HTTPS. Relative local URLs are also allowed. Data URLs and unsafe schemes are removed during sanitization.

!!! note "Image privacy follows authentication, not content scope"

    The editor-image endpoint requires an authenticated user and a valid path, but it does not perform document/project membership lookup for each image. Treat image URLs as authenticated assets and do not use the editor for secrets requiring per-document file authorization. Ticket attachments use stricter project authorization.

## Insert and edit a table

Select **Insert table**, then choose 1–20 rows and 1–20 columns. When the caret is inside a table cell, the contextual table toolbar can:

- insert a row above or below;
- remove the current row;
- insert a column left or right;
- remove the current column;
- merge with the adjacent cell horizontally or vertically;
- split a merged cell.

Mutations account for existing `rowspan` and `colspan` regions so an insertion or deletion does not create overlapping cells. Repeated merges can create larger regions. Removing the last row or column removes the table and leaves a normal paragraph so editing can continue.

On persistence, Laravel permits semantic `table`, `caption`, `thead`, `tbody`, `tfoot`, `tr`, `th`, and `td` elements. Cell spans must be canonical integers from 1–100. Header scope may be `row`, `col`, `rowgroup`, or `colgroup`; scope is removed from normal cells.

## Server sanitization contract

The allowed tag set is:

```text
p br strong b em i u s blockquote pre code ul ol li
h1 h2 h3 h4 a span img table caption thead tbody tfoot tr th td
```

The sanitizer:

- removes scripts, styles, iframes, objects, embeds, SVG, MathML, forms, form controls, meta/link/base, and their contents;
- unwraps unknown non-dangerous elements while recursively sanitizing their descendants;
- removes event handlers, CSS classes/styles, and all unapproved attributes;
- validates link and image URL schemes;
- constrains mention attributes and forces accepted mention tokens to `contenteditable="false"`;
- constrains table spans and header scope;
- removes images left without a safe source;
- discards non-text/non-element nodes.

Imports pass ticket descriptions, comments, documents, and document comments through the same trust boundary. Never treat an export file as pre-sanitized simply because it came from another Okatana installation.

## Ticket attachments

Attachments differ from inline editor images:

| Property     | Editor image                             | Ticket/comment attachment                 |
| ------------ | ---------------------------------------- | ----------------------------------------- |
| Maximum      | 5 MB                                     | 200 MB per file                           |
| Formats      | JPEG, PNG, GIF, WebP                     | any uploaded file accepted by Laravel/PHP |
| Disk         | `public`                                 | `local` private disk                      |
| Access check | authenticated asset path                 | current project membership                |
| Use          | rendered inside rich text                | explicit downloadable file                |
| Transfer     | embedded when referenced by project HTML | exported/restored as attachment assets    |

A request can upload 1–10 attachments. File names are reduced to a safe basename and capped at 255 characters; stored filenames use generated ULIDs rather than user input.

## Writing guidance

- Use headings to create navigable structure; avoid a single long paragraph.
- Use links for references and attachments for private source files.
- Use tables for exact comparisons, ownership mappings, or repeated fields—not page layout.
- Select mention candidates from autocomplete so identity is encoded.
- Keep operational decisions in comments or documents, not only in ephemeral chat.
- Use revision history to understand edits; the latest HTML is not the only retained evidence.

For notification results, see [Notifications and activity](notifications.md). For the implementation boundary, see [Security model](../developer/security.md).
