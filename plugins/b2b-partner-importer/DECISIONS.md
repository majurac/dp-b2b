# B2B Partner Importer — Decisions

## Company name search in wp-admin/users.php

**Date:** 2026-06-15  
**File:** `b2b-partner-importer.php`, method `handle_company_query()`

---

### Why company search was added

WordPress admin user search (`wp-admin/users.php?s=X`) only searches user table columns: `user_login`, `user_email`, `user_nicename`, `display_name`, `user_url`. Company name is stored in `billing_company` usermeta and was invisible to search.

Operators on this B2B platform work primarily with company names, not personal names or usernames. Searching "TRGOVINA KRK" or "KRK" returned no results even though the partner existed with `billing_company = 'TRGOVINA KRK d.d.'`.

---

### Why the implementation uses ID lookup + IN(...) instead of a JOIN

The natural approach would be a LEFT JOIN against `wp_usermeta` with an OR condition added to the search WHERE block:

```php
$q->query_from .= " LEFT JOIN {$wpdb->usermeta} AS um_search ON (...)";
// inject: OR um_search.meta_value LIKE '%term%'
```

This was the original design intent. The injection uses `strrpos()` to find the closing `)` of WordPress's search AND block and inserts the OR condition before it.

The same `strrpos()` approach works for both ID IN and JOIN — the difference is only in what gets injected. The ID IN approach was chosen at the time of writing because it avoids the need to modify `query_fields` (to add DISTINCT) and avoids any risk of duplicate rows from the JOIN. It was a simpler, lower-risk change.

---

### WordPress 7.x query_where format change

The original implementation used `preg_replace_callback` with a regex designed for the pre-7.x WordPress search SQL format:

```
AND ((user_login LIKE '%term%') OR (user_email LIKE '%term%') ...)
      ^-- inner parens around each condition
```

WordPress 7.x changed this format to:

```
AND (user_login LIKE '{hash}term{hash}' OR user_email LIKE '{hash}term{hash}' ...)
     ^-- no inner parens; hash-based prepared value placeholders
```

Two breaking differences:
1. Each column condition is no longer wrapped in its own parentheses.
2. LIKE values are replaced with `{sha256hash}value{sha256hash}` placeholders that WordPress resolves at query execution time.

The regex matched neither pattern. `preg_match` returned NO MATCH → `query_where` was not modified → company search silently did nothing.

The fix replaced the regex with `strrpos($q->query_where, ')')` which finds the closing paren of the search AND block regardless of internal format, plus a direct `$wpdb->get_col()` query for company IDs. This is format-agnostic.

---

### Future optimization opportunity

**Trigger:** customer base grows significantly beyond ~5,000 users, or admin search response time degrades.

**Problem with current approach at scale:**

Searching a very common suffix like "d.o.o." (79% of current partners are d.o.o.) produces:

1. First query: `SELECT user_id FROM wp_usermeta WHERE meta_key = 'billing_company' AND meta_value LIKE '%d.o.o%'`  
   → returns N IDs (N grows linearly with user count)
2. PHP builds: `OR wp_users.ID IN (1, 2, 3, ..., N)`  
   → at 10,000 users: ~55 KB SQL fragment
3. Main `WP_User_Query` runs with embedded IN list

At 10,000 users searching "d.o.o." the IN list would contain ~7,900 integers. MySQL handles this, but it is two DB round-trips and a linearly growing SQL string.

**Replacement (single-query JOIN approach):**

```php
// Replace the get_col + strrpos/IN block with:

$q->query_from .= " LEFT JOIN {$wpdb->usermeta} AS um_search"
    . " ON ({$wpdb->users}.ID = um_search.user_id"
    . " AND um_search.meta_key = 'billing_company')";

$last_paren = strrpos($q->query_where, ')');
if ($last_paren !== false) {
    $q->query_where = substr_replace(
        $q->query_where,
        $wpdb->prepare(" OR um_search.meta_value LIKE %s)", $like),
        $last_paren,
        1
    );
}
```

Benefits: single DB query, no PHP ID array, SQL string stays constant-size regardless of result count, MySQL optimizer uses the `meta_key` index on the JOIN ON clause.

Note: `$wpdb->prepare()` in WordPress 7.x produces hash-placeholder format (`{hash}value{hash}`), which WordPress resolves at execution time. This is compatible with the `strrpos` injection approach already in use.
