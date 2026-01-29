# Parameter Relations Documentation

## Overview

This document defines how parameters interact in the Analytics module for subset drilling and chart display. Understanding these relationships is crucial for correct data visualization.

---

## Core Concepts

### Sections vs param_groups

- **Section**: UI grouping shown in the filter sidebar (e.g., `kontaktart`, `person`, `thema`)
- **param_group**: Analytical subgroup that determines ADD/SUBTRACT behavior

### Operations

| Operation | Meaning | When Applied |
|-----------|---------|--------------|
| **ADD horizontally** | Values shown as parallel lines/bars, totals sum | Same param_group |
| **SUBTRACT vertically** | Values filter down (AND logic), intersection | Different param_group |

### General Rules

1. **First selected param_group** → Becomes the BASE
2. **Same param_group** → ADD (parallel, sum totals)
3. **Different param_group** → SUBTRACT (filter, intersection)
4. **Binary parameters** in the same group cannot subtract from each other (they add)

---

## Database Structure

### Current param_group Mapping

| Section | param_group | Values |
|---------|-------------|--------|
| kontaktart | `contact` | Besuch, Telefon, Mail, Passant/in |
| person | `gender` | Mann, Frau |
| person | `age` | unter 55, über 55, über 80 |
| person | `background` | Migrationshintergrund |
| person | `affected` | selbst betroffen, Angehörige Nachbarn und andere, Institution |
| thema | `topic` | Bildung, Arbeit, Finanzen, etc. |
| zeitfenster | `time_slot` | 11:00-11:30, 11:30-12:00, etc. |
| tageszeit | `day_time` | Vormittag, Nachmittag |
| dauer | `duration` | länger als 20 Minuten |
| referenz | `referral` | Flyer/Plakat/Presse, Internet, etc. |

---

## Parameter Behavior Matrix

| param_group | Binary | ADD horiz | SUBTRACT vert | Special Behavior |
|-------------|--------|-----------|---------------|------------------|
| `contact` | Yes | Yes | Yes | Standard |
| `gender` | Yes | Yes | Yes | Standard |
| `age` | Yes | Yes | Yes | Standard |
| `background` | No | No | Yes | **Always subtracts** (even within Person section) |
| `affected` | Yes | Yes | Yes | Standard |
| `duration` | No | No | Yes | **Always subtracts** (single value, no ADD possible) |
| `topic` | Yes | Yes | Yes | Standard |
| `time_slot` | Yes | Yes | Yes | Standard |
| `day_time` | Yes | Yes | Yes | Standard |
| `referral` | Yes | Yes | Yes | Standard |

### Special Cases

#### Migrationshintergrund (`background`)
- **Can be a base** if selected first (shows subset of total count)
- **Always subtracts** when combined with other Person subgroups (gender, age, affected)
- Never adds horizontally with other parameters
- Example: `Mann` + `Migrationshintergrund` = "Men with migration background" (intersection)

#### Dauer (`duration`)
- **Can be a base** if selected first
- **Always subtracts** when combined with other parameters
- Single value in group, so ADD within group is not applicable
- Example: `Besuch` + `länger als 20 Minuten` = "Visits longer than 20 min" (intersection)

---

## Chart-Specific Behavior

### Line Graph

Two display modes (toggle below chart):
1. **All parameters**: Displays base(s) and all selected subsets on separate color lines
2. **Result only**: Displays only the final filtered result as one line

### Stacked Bar Graph

- Displays **subsets only** on stacked bars
- **Does NOT include the base** in the chart
- Base is shown as heading above the legend
- Stacked bars represent the breakdown of the base

### Bar Graph / Pie Chart

- Standard aggregate display
- Shows selected values with their counts

---

## Examples

### Example 1: Same param_group (ADD)
**Selection**: Mann + Frau (both `gender`)
- Result: Two parallel lines
- Total: Mann count + Frau count
- Behavior: ADD horizontally

### Example 2: Different param_group (SUBTRACT)
**Selection**: Mann + unter 55
- Mann is `gender`, unter 55 is `age`
- Result: Mann (base) + "Mann ∩ unter 55" (subset)
- Behavior: SUBTRACT vertically

### Example 3: Migrationshintergrund special case
**Selection**: Mann + Migrationshintergrund
- Both are in section `person`
- But Migrationshintergrund (`background`) always subtracts
- Result: Mann (base) + "Mann ∩ Migrationshintergrund" (subset)
- NOT: Mann + Migrationshintergrund (parallel/add)

### Example 4: Cross-section selection
**Selection**: Besuch + Frau + unter 55
- Besuch is `contact`, Frau is `gender`, unter 55 is `age`
- Result: Besuch (base) → Besuch ∩ Frau → Besuch ∩ Frau ∩ unter 55
- Line graph: 3 lines (or just result line in "result only" mode)
- Stacked: Shows Frau and unter 55 as subsets of Besuch

### Example 5: Multiple values in last group (parallel leaves)
**Selection**: Mann + unter 55 + über 55
- Mann is `gender` (base)
- unter 55 and über 55 are both `age` (leaves)
- Result: Mann (base) + zwei parallel subsets
- Stacked: Shows unter 55 and über 55 stacked (their sum ≈ Mann filtered by age data)

---

## Implementation Considerations

### Option A: Database Column

Add a `behavior` column to `option_definitions`:
```sql
ALTER TABLE option_definitions
ADD COLUMN behavior ENUM('standard', 'subtract_only') DEFAULT 'standard';

UPDATE option_definitions SET behavior = 'subtract_only'
WHERE param_group IN ('background', 'duration');
```

**Pros**: Flexible, maintainable, data-driven
**Cons**: Requires schema change, migration, API update

### Option B: Hardcoded Logic

Define special groups in frontend code:
```javascript
const SUBTRACT_ONLY_GROUPS = ['background', 'duration']
```

**Pros**: Quick to implement, no database changes
**Cons**: Less flexible, logic split between DB and code

---

## Current Implementation Status

| Feature | Status | Notes |
|---------|--------|-------|
| First group = base | ✅ Implemented | Works correctly |
| Same group = ADD | ✅ Implemented | Works correctly |
| Different group = SUBTRACT | ✅ Implemented | Works correctly |
| Cross-section stacked chart | ✅ Implemented | Fixed in recent commit |
| Stacked shows only leaves | ✅ Implemented | Base shown as heading |
| `background` always subtracts | ✅ Implemented | Uses `behavior` column in DB |
| `duration` always subtracts | ✅ Implemented | Uses `behavior` column in DB |
| Line graph display toggle | ❌ Not implemented | Needs UI toggle |

---

## Next Steps

1. Decide on implementation approach (Option A or B)
2. Implement special handling for `background` and `duration` param_groups
3. Add line graph display mode toggle
4. Test all parameter combinations
