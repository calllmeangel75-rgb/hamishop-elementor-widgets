# 1.11.268 — staging pre-release

Adds an admin ticket attachment preview dialog with keyboard Escape, close control and fallback to the existing image link when JavaScript or native dialog support is unavailable. The script and styling are limited to ticket administration. Tested in a browser fixture, not on the actual staging site.

Disposable WordPress tests observed that trash retains ticket and attachment; permanent ticket deletion removes its post and reply metadata but does not remove the media attachment, its files, or related account notifications. Explicit WordPress attachment deletion removed the test original and thumbnail. No automatic attachment deletion or changes to retention are introduced: historical attachments may be shared or reused and need an ownership-aware, recoverable cleanup design. Backups, email copies, exports and caches are not covered by ticket deletion. Attachments may still be public. Not production-ready.
