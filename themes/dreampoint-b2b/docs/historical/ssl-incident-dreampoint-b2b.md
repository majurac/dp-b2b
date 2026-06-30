# SSL Incident — dreampoint.b2b (Jun 2026)

Archival incident record. Investigation completed Jun 8, 2026. Read-only reference.

---

## Observed Symptom

```
https://dreampoint.b2b → ERR_CERT_COMMON_NAME_INVALID
```

Browser received `CN = jekaastore.com` instead of a `dreampoint.b2b` certificate.

---

## Proven Root Cause

`dreampoint.b2b` was never added to the OpenLiteSpeed SSL listener map in
`/usr/local/lsws/conf/httpd_config.conf`.

When an SNI request for `dreampoint.b2b` arrived on port 443, OLS could not match
it to any vhost in the `listener SSL` block. OLS fell back to the listener's default
certificate — which belonged to `jekaastore.com`.

The `vhssl` block in `vhosts/dreampoint.b2b/vhost.conf` exists and points to correct
certificate paths, but OLS never reaches it because the vhost is not selected at the
listener level.

---

## Historical Classification

**Never Completed Configuration** — HTTPS was never fully functional for this domain.

| Evidence | Detail |
|---|---|
| RCS revisions inspected | 25 (Apr 1 – Jun 8, 2026) |
| dreampoint.b2b in SSL listener | Never — in zero revisions |
| dreampoint.b2b in Default listener | Yes — added Apr 30, rev 1.23 |
| Let's Encrypt archive dir | Missing |
| Let's Encrypt renewal conf | Missing |
| Certbot log entries | None |
| Certificate type | Self-signed (O=Dreampoint, C=HR) |

The provisioning on Apr 30, 2026 (rev 1.23, 07:34) added only the HTTP side
(Default listener + virtualHost declaration). The SSL listener mapping (+2 lines,
the standard second phase for every other domain on this server) was never committed.

A self-signed certificate was manually placed at
`/etc/letsencrypt/live/dreampoint.b2b/` 2.5 hours later (09:58), but the OLS
listener configuration step was skipped.

---

## Secondary Finding

The certificate at `/etc/letsencrypt/live/dreampoint.b2b/` is self-signed:

```
CN:     dreampoint.b2b
Issuer: CN=dreampoint.b2b, O=Dreampoint, C=HR
SANs:   DNS:dreampoint.b2b, DNS:www.dreampoint.b2b
Valid:  Apr 30 2026 → Apr 27 2036
```

After adding the SSL listener mapping, browsers would still report
`NET::ERR_CERT_AUTHORITY_INVALID` until a trusted certificate is in place.

Since `dreampoint.b2b` has no public DNS record, Let's Encrypt HTTP-01 challenge
cannot complete. Options: self-signed cert added to browser trust store (staging
acceptable), or DNS-01 challenge if DNS provider supports API access.

---

## Listener State at Time of Investigation

**listener Default (port 80):** `dreampoint.b2b` mapped — HTTP works.

**listener SSL (port 443):**
```
# present in SSL listener:
shop.cotra.hr, b2b.cotra.hr, mail.cotra.hr, cotra.hr,
mail.jekaastore.com, ax42.uncledev.com, leorigine.local, jekaastore.com

# missing:
dreampoint.b2b
```

Default cert on `listener SSL`: `/etc/letsencrypt/live/jekaastore.com/fullchain.pem`

---

## Minimal Recovery (proposed — not executed)

1. Add `map dreampoint.b2b dreampoint.b2b` to both `listener SSL` and `listener SSL IPv6` in `httpd_config.conf` via CyberPanel SSL issuance or manual edit.
2. Obtain a trusted certificate — either add self-signed to browser trust store, or use DNS-01 challenge for Let's Encrypt.
3. After config change: graceful OLS restart (no killall lsphp needed for listener-only changes).
4. Verify with SNI test: `openssl s_client -connect 127.0.0.1:443 -servername dreampoint.b2b`

---

## Lessons Learned

CyberPanel SSL issuance for staging domains with no public DNS will silently fail
the cert issuance step (HTTP-01 challenge fails) while still writing partial OLS
config. The result is a vhost provisioned for HTTP only, with no indication of
failure in the OLS config or standard deploy verification.

Do not assume CyberPanel completed SSL configuration. Always verify with an SNI
test after provisioning any new domain.
