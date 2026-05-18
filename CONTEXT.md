# Themeisle Tester

Themeisle Tester is a WordPress admin testing context for creating, inspecting, and resetting controlled product/plugin states. It exists so humans can reproduce Themeisle product scenarios without changing server infrastructure or editing product code manually.

## Language

**Dashboard**:
The admin screen where testing items are configured, inspected, and run.
_Avoid_: app, SPA, settings page

**Category**:
A top-level Dashboard grouping for related testing items.
_Avoid_: section, tab, group

**Product**:
A Themeisle plugin or shared Themeisle SDK area that owns a testing item.
_Avoid_: integration, provider, extension

**Scenario**:
A saved test condition that changes runtime behavior through controlled hooks or filters.
_Avoid_: module, feature, tweak

**Utility**:
A reusable stateless inspector or action that helps create, inspect, or reset test conditions.
_Avoid_: tool, widget, helper

**Danger Utility**:
A Utility that intentionally mutates site or Product data and keeps a backup for restoration.
_Avoid_: destructive scenario, mutation tool

**Control**:
A React field component used to edit a value inside a Scenario or Utility card.
_Avoid_: widget, field renderer, component

**Registry**:
The extension point where Products declare their Scenarios and Utilities.
_Avoid_: loader, container, manager

**Testing Item**:
A generic term for either a Scenario or a Utility when both types are being discussed together.
_Avoid_: module, card

## Relationships

- A **Dashboard** contains one or more **Categories**.
- A **Category** contains one or more **Testing Items**.
- A **Testing Item** is either a **Scenario** or a **Utility**.
- A **Danger Utility** is a specialized **Utility**.
- A **Product** may register many **Testing Items**.
- **Category** and **Product** are orthogonal: Category controls where an item appears in the Dashboard, while Product identifies who owns it.
- A **Scenario** belongs to one **Product** and stores a controlled test condition.
- A **Utility** may appear in multiple **Categories** when it provides useful quick access.
- A **Control** edits one value within a **Testing Item**.
- The **Registry** receives **Testing Items** from Products.

## Example dialogue

> **Dev:** "Should the expired license behavior be a Utility?"
> **Domain expert:** "No. If it saves a test condition that changes runtime behavior, it is a **Scenario**. A **Utility** can inspect existing license options or help reset them."

> **Dev:** "The install date editor writes directly to product options. Is that still a Scenario?"
> **Domain expert:** "No. Direct mutation makes it a **Danger Utility**, and it must keep a backup before changing data."

## Flagged ambiguities

- "module" was used to mean both a user-facing test behavior and an internal implementation unit; resolved: user-facing behavior is a **Scenario**, while implementation classes should avoid exposing "module" as domain language.
- "tool" was used for reusable Dashboard actions; resolved: the canonical term is **Utility** to avoid broad or ambiguous wording.
- "widget" was considered for reusable UI pieces; rejected because it conflicts with WordPress widget terminology.
- "card" describes a visual container only; resolved: use **Testing Item**, **Scenario**, or **Utility** when discussing domain behavior.
