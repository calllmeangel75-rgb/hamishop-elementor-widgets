# 1.11.267 — staging pre-release

Refines ticket-only administration layout and maintains WordPress status/priority save controls and separate reply/status forms. Staff cannot reply to a closed ticket from wp-admin or inbound email until explicitly reopened; customer inbound mail retains its prior reopen behavior. Disposable WordPress and browser-fixture checks do not replace testing on the actual site. The package remains staging only.

Ticket attachments may still be publicly accessible by direct URL; do not use sensitive images. No migration or deletion of existing attachments was performed. Review ticket roles, email and retention before production. Version changes can trigger existing cache/rewrite/upgrader behavior across the plugin; back up and test unrelated areas after upgrading.
