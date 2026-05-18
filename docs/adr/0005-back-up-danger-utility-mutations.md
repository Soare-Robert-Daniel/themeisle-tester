# Back Up Danger Utility Mutations

Date: 2026-05-18

Danger Utilities intentionally mutate Product or site data, so they must write a Tester-owned backup before the first mutation of each target. Backups are keyed by Utility ID and target identifier, kept until restore/reset, and not overwritten by later changes in the same testing session. This preserves the original pre-test value and gives reset meaningful behavior even when a Utility edits WordPress options or related transients directly.

## Considered Options

- No backup: simplest, but reset cannot reliably undo destructive testing changes.
- Overwrite backup on every mutation: easy to implement, but loses the original value the tester needs to restore.
- First-mutation backup: preserves the original state while allowing repeated test edits.
