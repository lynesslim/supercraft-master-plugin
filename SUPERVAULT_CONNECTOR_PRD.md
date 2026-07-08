# Product Requirements Document (PRD)
# SuperVault Connector
Version: 1.0 (MVP)
Author: Supercraft
Status: Planning

---

# 1. Overview

## Purpose

SuperVault Connector is a WordPress plugin that integrates the central SuperVault component library directly into the Elementor editor.

Instead of switching between browsers and manually opening SuperVault, designers can browse, preview and copy components without leaving Elementor.

The connector acts as the communication layer between a project website and the central SuperVault server.

It does **not** store components locally.

All components are served from the SuperVault API.

---

# 2. Product Vision

To make reusable website components as accessible as Elementor widgets.

The long-term vision is for every Supercraft project to have access to a centralized ecosystem of:

- Components
- Templates
- Animations
- Assets
- Icons
- AI-generated layouts

through a single interface inside Elementor.

---

# 3. Problem Statement

Current workflow

```
Open Elementor

↓

Need Hero Section

↓

Open Browser

↓

Login SuperVault

↓

Search

↓

Preview

↓

Copy JSON

↓

Return to Elementor

↓

Paste
```

Problems

- Constant context switching
- Slow workflow
- Multiple browser tabs
- Poor UX
- Breaks creative flow

---

# 4. Objectives

Primary Objectives

- Integrate SuperVault inside Elementor
- Reduce component search time
- Eliminate browser switching
- Keep latest component versions available
- Require zero manual syncing

Secondary Objectives

- Enable future direct insertion
- Enable AI search
- Enable publishing back to SuperVault

---

# 5. Scope

Included

- Elementor integration
- Browse components
- Search
- Filter
- Preview
- Copy JSON
- Authentication
- API communication
- Cache management

Excluded

- Direct insertion
- Upload component
- Component editing
- Local storage
- Offline support
- AI search

---

# 6. System Architecture

```
┌────────────────────────────┐
│ Elementor Editor           │
└─────────────┬──────────────┘
              │
              │
┌─────────────▼──────────────┐
│ SuperVault Connector       │
│                            │
│ Modal                      │
│ Search                     │
│ Preview                    │
│ Copy JSON                  │
└─────────────┬──────────────┘
              │ REST API
              │
┌─────────────▼──────────────┐
│ SuperVault                 │
│                            │
│ Component CPT              │
│ Elementor Templates        │
│ Preview Renderer           │
│ API                        │
└────────────────────────────┘
```

---

# 7. Authentication

Authentication is handled by Supercraft Core.

Connector never asks users for project code.

Workflow

```
Connector loads

↓

Check Supercraft Core

↓

Core validates project

↓

Receive Access Token

↓

Connector stores nothing

↓

Every API request includes token
```

Authorization Header

```
Authorization: Bearer <token>
```

---

# 8. Elementor Integration

The connector injects a new button into the Elementor editor.

Preferred Location

Left sidebar

Example

```
Navigator

History

Finder

-------------------

SuperVault
```

Clicking opens the SuperVault modal.

---

# 9. SuperVault Modal

Full-screen overlay.

Layout

```
------------------------------------------------

SuperVault

Search _______________________

------------------------------------------------

Category Tabs

Hero

CTA

FAQ

Pricing

Contact

Gallery

Features

------------------------------------------------

Component Grid

□□□□□□□□□□□□□□□□□□

□□□□□□□□□□□□□□□□□□

□□□□□□□□□□□□□□□□□□

------------------------------------------------

Preview

Copy JSON

------------------------------------------------
```

---

# 10. Component Card

Each component displays

- Thumbnail
- Component Name
- Category
- Tags
- Motion indicator
- Dark / Light indicator

Buttons

Preview

Copy

---

# 11. Search

Instant search.

Searchable fields

- Title
- Description
- Tags
- Category
- Keywords

Example

```
hero

pricing

animated

dark

coffee

medical
```

---

# 12. Filters

Category

Industry

Style

Motion

Theme

Sorting

Newest

Popular

Recently Used

---

# 13. Preview

Click Preview

Modal expands

Loads iframe

```
https://vault.supercraft.my/?supervault_preview=1&template_id=123
```

The preview is rendered live.

Features

Desktop width

Scrolling

Videos autoplay

GSAP animations

Responsive

---

# 14. Copy JSON

Workflow

```
Click Copy

↓

Connector requests API

↓

SuperVault loads Elementor template

↓

Reads _elementor_data

↓

Returns JSON

↓

Copies to clipboard

↓

Success animation
```

User never downloads JSON manually.

---

# 15. API Endpoints

## Browse

GET

```
/api/components
```

Returns

```
id

title

thumbnail

category

tags

preview_url
```

---

## Search

GET

```
/api/components?search=hero
```

---

## Categories

GET

```
/api/categories
```

---

## Component Details

GET

```
/api/component/{id}
```

---

## Component JSON

GET

```
/api/component/{id}/json
```

Returns

Latest Elementor JSON

---

# 16. Caching

Component List

15 minutes

Categories

1 hour

Search

No cache

JSON

Never cache

Always fetch latest.

---

# 17. Error Handling

Connection Failed

```
Unable to connect to SuperVault.
```

Unauthorized

```
This project is not authorised.
```

No Components

```
No components found.
```

Server Error

```
Please try again later.
```

---

# 18. Security

Connector never stores

- GitHub token
- Project secret
- Elementor templates

Connector only stores

- Temporary access token
- Cache

All communication

HTTPS

Bearer Authentication

Rate limited

---

# 19. Performance

Lazy load thumbnails

Pagination

Virtual scrolling

Cached component metadata

Never cache JSON

---

# 20. Settings

WordPress

Settings

SuperVault

Displays

Connection Status

Plugin Version

API Endpoint

Cache Status

Clear Cache

Debug Mode

No authentication settings required.

Authentication comes from Supercraft Core.

---

# 21. UX Guidelines

Must feel like a native Elementor feature.

Design principles

Minimal

Fast

Zero learning curve

No page reloads

Keyboard friendly

Dark mode compatible

---

# 22. Future Roadmap

## Version 2

Direct Insert

```
Browse

↓

Insert

↓

Component appears immediately
```

---

## Version 3

Publish to SuperVault

```
Designer

↓

Select Section

↓

Publish

↓

Available in Vault
```

---

## Version 4

AI Search

```
"Show me SaaS hero sections with blue gradient."

↓

Results
```

---

## Version 5

Team Libraries

Company libraries

Private libraries

Shared components

Version history

---

## Version 6

Asset Library

Icons

Illustrations

Lottie

Videos

Images

---

## Version 7

Animation Library

GSAP presets

Scroll effects

Hover effects

Mouse interactions

---

## Version 8

AI Builder

Prompt

↓

Generate website

↓

Uses SuperVault components

↓

Assemble complete page

---

# 23. Success Metrics

Average component search

Target

<15 seconds

Average insertion workflow

Target

<30 seconds

Reduction in browser switching

Target

90%

User satisfaction

Target

4.8/5

---

# 24. Technical Stack

Frontend

- JavaScript
- Elementor Editor API
- REST API
- Fetch API

Backend

- WordPress
- PHP
- Custom REST Endpoints

Authentication

- Supercraft Core
- Bearer Token

Server

- SuperVault WordPress

---

# 25. Long-term Vision

SuperVault Connector is not intended to remain a standalone plugin.

It will eventually become one module inside the broader Supercraft ecosystem.

```
Supercraft Core

│

├── SuperVault
├── SuperAnimation
├── SuperAssets
├── SuperIcons
├── SuperTemplates
├── SuperAI
├── SuperSEO
├── SuperPerformance
└── Future Services
```

The connector establishes the foundation for a cloud-based design ecosystem where every Supercraft project can access reusable resources from a single interface inside Elementor, eliminating repetitive work while ensuring consistency, scalability, and continuous improvement across all client websites.