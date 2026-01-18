# Helpdesk Statistics Application

## Purpose & Context
Felix is developing a web-based helpdesk statistics application for a German counseling center, migrating from their current FileMaker system to a cost-effective LAMP stack solution (MySQL, PHP, Vue.js). As product manager, he's overseeing the technical architecture and data migration strategy. The application tracks visitor statistics across multiple categories including contact methods (Besuch, Telefon, Mail), demographics, consultation topics (Bildung, Arbeit, Finanzen, Gesundheit, Migration Integration), time windows, and referral sources. The system needs to handle complex multi-select data where users can check multiple options per category simultaneously, while preserving historical data integrity from existing CSV files containing thousands of entries. Key requirements include administrative flexibility to modify dropdown options without code changes, smooth data migration from legacy systems, and analytics capabilities for backoffice users to compare trends across different time periods.

## Current State
Felix has established the core database architecture using a "loose coupling" approach with four main tables: users (authentication), option_definitions (dropdown configuration), stats_entries (main data), and stats_entry_values (junction table for multi-select relationships). The design stores actual text values rather than foreign key references to preserve historical data when options are modified. He's completed comprehensive MySQL schema design, PHP API endpoints, and CSV import scripts for migrating historical data. Current focus is on the analytics dashboard development, with working HTML prototypes for both data entry forms and visualization components. The technical stack is confirmed as MySQL on Swiss hosting (Cyon or Hostpoint) rather than more expensive alternatives like Supabase, based on cost analysis and feature requirements for the counseling center's scale.

## On the Horizon
Felix is developing sophisticated data visualization capabilities for the analytics section, exploring multiple chart types including connected dot plots, diverging bar charts, and small multiples with sparklines to serve different analytical needs. He's working on interactive filtering systems that allow backoffice users to compare visitor trends across time periods and demographic segments. The visualization approach will likely implement multiple chart types - small multiples for dashboard monitoring, connected dot plots for detailed trend analysis, and diverging bars for executive summaries. Integration of real-time-feeling updates using polling techniques and optimistic UI updates with Vue.js is planned to create smooth user experience without requiring WebSocket complexity.

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
Four main tables with "loose coupling" approach:
1. **users** - authentication
2. **option_definitions** - dropdown configuration
3. **stats_entries** - main data
4. **stats_entry_values** - junction table for multi-select relationships (stores text values, not FK references)

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
- Add, edit, reorder, deactivate dropdown options
- Create/manage user accounts and roles
- Changes reflect immediately in Frontend

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
