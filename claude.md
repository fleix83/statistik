# Helpdesk Statistics Application

## Rules
- Always use Context7 MCP when I need library/API documentation, code generation, setup or configuration steps without me having to explicitly ask.

## Purpose & Context
Felix is developing a web-based helpdesk statistics application for a German counseling center, migrating from their current FileMaker system to a cost-effective LAMP stack solution (MySQL, PHP, Vue.js). As product manager, he's overseeing the technical architecture and data migration strategy. The application tracks visitor statistics across multiple categories including contact methods (Besuch, Telefon, Mail), demographics, consultation topics (Bildung, Arbeit, Finanzen, Gesundheit, Migration Integration), time windows, and referral sources. The system needs to handle complex multi-select data where users can check multiple options per category simultaneously, while preserving historical data integrity from existing CSV files containing thousands of entries. Key requirements include administrative flexibility to modify dropdown options without code changes, smooth data migration from legacy systems, and analytics capabilities for backoffice users to compare trends across different time periods.

## Current State
The application is now fully functional with a complete Vue.js frontend and PHP API backend. The Editor view features a sophisticated draft/publish system allowing admins to make changes in isolation before publishing to the live application. Authentication uses database-backed tokens for reliability across requests. The Data Entry form is complete with multi-select support for all categories. Current focus is on the Analytics dashboard implementation.

**Completed:**
- ✅ Vue.js frontend with PrimeVue components (DataEntry, Editor, Login views)
- ✅ PHP REST API with full CRUD for options, entries, users
- ✅ Editor with draft/publish workflow (changes only go live after "Publish")
- ✅ Inline editing and drag-drop reordering (vue-draggable-next)
- ✅ Keywords support for Thema options (visible in Editor and DataEntry)
- ✅ Database-backed token authentication (replaced unreliable PHP sessions)
- ✅ Apache .htaccess for Authorization header forwarding through Vite proxy

## On the Horizon
The Analytics dashboard is the primary remaining feature. This will include sophisticated data visualization with multiple chart types: connected dot plots for trend analysis, diverging bar charts for comparisons, and small multiples with sparklines for dashboard monitoring. Interactive filtering will allow backoffice users to compare visitor trends across time periods and demographic segments. CSV export functionality is already implemented in the API.

## Key Learnings & Principles
Felix has learned that preserving historical data integrity requires careful architectural decisions, leading to the adoption of text-based storage over foreign key relationships in junction tables. The discovery that multi-select boolean fields in CSV data necessitated a complete redesign from a simple 4-column approach to a many-to-many architecture was a critical pivot. Cost analysis revealed that simpler, established technologies (MySQL on traditional hosting) often provide better value than modern alternatives (PostgreSQL on Supabase) for specific use cases. For data visualization, he's learned that single visualization approaches have limitations - color saturation encoding creates cognitive difficulty, and different analytical needs require different chart types rather than trying to solve everything with one approach.

## Approach & Patterns
Felix follows a systematic approach of prototyping with HTML mockups before full implementation, allowing rapid iteration and stakeholder feedback. He prioritizes data preservation and administrative flexibility in design decisions, choosing architectures that accommodate future changes without requiring developer intervention. His development process includes comprehensive documentation and visual schema diagrams to communicate complex database relationships. For analytics, he's adopting a multi-visualization strategy where different chart types serve specific analytical purposes rather than attempting universal solutions. The technical approach emphasizes practical, cost-effective solutions over cutting-edge technologies when the simpler option meets requirements.

## Tools & Resources
- **Backend**: LAMP stack (MySQL, PHP)
- **Frontend**: Vue.js with PrimeVue components
- **Visualization**: Chart.js
- **Hosting**: Swiss providers (Cyon recommended for sustainability, Hostpoint for budget)
- **Data Migration**: CSV import from legacy FileMaker systems

## Database Architecture
Core tables with "loose coupling" approach:
1. **users** - user accounts and roles
2. **auth_tokens** - database-backed session tokens (24h expiry)
3. **option_definitions** - dropdown configuration (with keywords JSON column)
4. **option_definitions_draft** - pending changes before publish
5. **publish_state** - tracks draft/publish workflow state
6. **stats_entries** - main consultation records
7. **stats_entry_values** - junction table for multi-select (stores text values, not FK references)

## App Structure

```
┌─────────────────────────────────────────────────────────────────┐
│                        HELPDESK STATISTIK                       │
├─────────────────┬─────────────────────┬─────────────────────────┤
│    FRONTEND     │       EDITOR        │       ANALYTICS         │
│   (Users)       │      (Admins)       │        (Admins)         │
├─────────────────┼─────────────────────┼─────────────────────────┤
│ Data Entry      │ Manage Options      │ Visualize Data          │
│                 │ Manage Users        │ Compare Periods         │
└────────┬────────┴──────────┬──────────┴────────────┬────────────┘
         │                   │                       │
         ▼                   ▼                       ▼
┌─────────────────────────────────────────────────────────────────┐
│                         PHP API                                 │
├─────────────────────────────────────────────────────────────────┤
│                      MySQL Database                             │
│  ┌───────┐ ┌──────────────────┐ ┌─────────────┐ ┌─────────────┐│
│  │ users │ │option_definitions│ │stats_entries│ │entry_values ││
│  └───────┘ └──────────────────┘ └─────────────┘ └─────────────┘│
└─────────────────────────────────────────────────────────────────┘
```

### 1. Frontend (Users)
**Purpose:** Statistical data entry for helpdesk consultations
- No login required - user selects their name from dropdown per entry
- Multi-select form for all categories (Kontaktart, Person, Thema, Zeitfenster, Referenz)
- Submit consultation records

### 2. Editor (Admins)
**Purpose:** Manage dropdown options and user accounts
- Draft/publish workflow: changes are staged until "Publish" is clicked
- Inline editing: click label to edit, blur/Enter to save
- Drag-and-drop reordering with vue-draggable-next
- Toggle switch to activate/deactivate options
- Keywords editor for Thema section (displayed as hints in DataEntry)
- Section grouping: Person (kontaktart+person+dauer), Thema, Zeitfenster, Referenz
- Discard: revert all pending changes
- Reset: restore default options from seed file

### 3. Analytics (Admins)
**Purpose:** Analyze and visualize consultation data
- Dashboard with key metrics
- Charts: small multiples, connected dot plots, diverging bars
- Filter by date range, section, values
- Export to CSV

### Data Flow
```
User → Frontend (Entry) → stats_entries + stats_entry_values
Admin → Editor → option_definitions + users
Admin → Analytics ← Query aggregated data
```

### User Roles
| Role | Frontend | Editor | Analytics |
|------|----------|--------|-----------|
| user | ✓ | ✗ | ✗ |
| admin | ✓ | ✓ | ✓ |
