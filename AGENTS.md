Anton Lens — Internal Website Inspection System
Project Purpose
Anton Lens is an internal agency tool for visual website feedback.
 It supports secure website proxy viewing, pin-based threaded comments, screenshot capture, project collaboration, and client share links.
This is NOT a SaaS platform.
 No billing.
 No public registration.
 No multi-tenant monetization features.

Tech Stack Rules (Strict)
PHP 8+

MySQL / MariaDB

PDO only (prepared statements required)

Vanilla JavaScript

HTML + CSS

No frontend frameworks

No Node.js

No Docker

No build pipeline tools

Deployment target: Hostinger shared hosting inside public_html.

Architecture Rules
PHP monolith

Front controller (index.php)

Custom router

Middleware for auth

DB-backed job queue

Strict folder structure

Do not refactor architecture without explicit instruction.

Security Rules
Authentication
password_hash() required

httpOnly cookies

CSRF protection on all POST requests

Session ID regeneration on login

Proxy
Only allow http/https

Validate hostname against project allowed_hosts_json

Block private IP ranges

Re-check on redirects

Limit response size

Enforce timeouts

Prevent open proxy behavior

Tokens
Share link tokens must be stored hashed

Never store raw tokens

Validate expiration

File Storage
Store screenshots in storage/screenshots

Prevent direct public access via .htaccess

Serve files via controlled endpoint

Design System Rules (Strict)
Typography
Font: Poppins only
 Weights: 400, 500, 600, 700
Colors
Background: #FFFFFF
 Secondary: #F8F9FB
 Primary Accent: #F5C242
 Hover: #E9B731
 Active: #D9A824
 Text on Yellow: #000000
 Primary Text: #1A1A1A
 Secondary Text: #6B7280
 Borders: #E5E7EB
No additional accent colors.
Spacing
Use only 8px spacing system (8, 16, 24, 32, 48).
Restrictions
No dark theme.
 No gradients.
 No heavy shadows.
 No extra design experimentation.
Anton Lens must remain white, structured, and professional.

Coding Standards
Clear function naming

No inline SQL string concatenation

Use prepared statements only

Separate business logic from presentation

Keep functions concise

Follow defined file tree

Final Directive
All future generated code must comply with AGENTS.md.
 If ambiguity occurs, choose the simplest safe implementation.
