---
hide:
  - toc
---

# Run work with a clear system.

<p class="okatana-lede">Okatana connects organizations, controlled project access, configurable Kanban workflows, tickets, documents, audit history, and integrations in one Laravel and React application. This manual covers the system from first sign-in through production operation and extension.</p>

<div class="okatana-actions">
  <a href="getting-started/quickstart/" class="md-button md-button--primary">Start in five minutes</a>
  <a href="/docs/api" class="md-button">Open interactive API</a>
</div>

<div class="okatana-grid">
  <div class="okatana-stat"><strong>3</strong><span>organization roles</span></div>
  <div class="okatana-stat"><strong>6</strong><span>ticket priorities</span></div>
  <div class="okatana-stat"><strong>14</strong><span>API scopes</span></div>
  <div class="okatana-stat"><strong>46</strong><span>webhook selectors</span></div>
</div>

## Choose your path

<div class="okatana-grid">
  <div class="okatana-card">
    <h3><a href="getting-started/product-tour/">Evaluate Okatana</a></h3>
    <p>Understand the domain, principal workflows, deployment shape, and what the system deliberately does not require.</p>
  </div>
  <div class="okatana-card">
    <h3><a href="user-guide/boards-and-tickets/">Use the workspace</a></h3>
    <p>Learn daily navigation, project boards, ticket collaboration, documents, notifications, analytics, and history.</p>
  </div>
  <div class="okatana-card">
    <h3><a href="administration/roles-and-permissions/">Administer teams</a></h3>
    <p>Model access, invite members, manage integrations, preserve audit evidence, and transfer data safely.</p>
  </div>
  <div class="okatana-card">
    <h3><a href="operations/installation/">Operate a deployment</a></h3>
    <p>Install with SQLite, MySQL, or PostgreSQL; configure mail and queues; back up; monitor; and troubleshoot.</p>
  </div>
  <div class="okatana-card">
    <h3><a href="integrations/http-api/">Automate work</a></h3>
    <p>Create scoped bearer credentials, call the versioned API, consume signed webhooks, and handle failures.</p>
  </div>
  <div class="okatana-card">
    <h3><a href="ARCHITECTURE/">Extend the codebase</a></h3>
    <p>Trace requests across React and Laravel, understand the persistence and security boundaries, and run the quality suite.</p>
  </div>
</div>

## System in one minute

<div class="okatana-flow">
  <div><strong>Organization</strong><span>Membership, roles, invitations, integrations, and global documents.</span></div>
  <div><strong>Project</strong><span>Explicit access, workflow phases, labels, tags, analytics, and project documents.</span></div>
  <div><strong>Ticket</strong><span>Priority, assignment, rich text, files, comments, movement, and revisions.</span></div>
  <div><strong>Evidence</strong><span>Append-only audit events, portable exports, and signed webhook deliveries.</span></div>
</div>

Okatana is a single deployable Laravel application. React is compiled into browser assets; Laravel owns authentication, authorization, validation, persistence, queues, mail, exports, and external calls. The default SQL-backed session, cache, and queue setup means a complete small installation needs only the application and one supported SQL database.

!!! note "Two documentation surfaces"

    This MkDocs site is the complete product manual at `/docs/`. The [Scalar API reference](/docs/api) remains the executable OpenAPI console at `/docs/api`; both use the same visual language but serve different jobs.

## Documentation map

| Section                                                   | Use it when you need to…                                                |
| --------------------------------------------------------- | ----------------------------------------------------------------------- |
| [Start here](getting-started/product-tour.md)             | learn the vocabulary, evaluate the system, or complete a first workflow |
| [User guide](user-guide/account-and-access.md)            | use the browser workspace day to day                                    |
| [Administration](administration/roles-and-permissions.md) | manage people, permissions, history, transfers, or integrations         |
| [Operations](operations/installation.md)                  | install, configure, deploy, back up, monitor, or recover Okatana        |
| [Integrations](integrations/http-api.md)                  | build clients for the HTTP API or receive webhooks                      |
| [Developers](ARCHITECTURE.md)                             | understand or change the Laravel/React implementation                   |
| [Reference](reference/environment.md)                     | look up exact variables, permissions, events, scopes, routes, or terms  |
