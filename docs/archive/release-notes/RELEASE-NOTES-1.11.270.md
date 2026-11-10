# 1.11.270 — staging pre-release

Removes safely identifiable owner ticket notifications after permanent ticket deletion, including the existing scheduled retention cleanup. Trash remains reversible. Older notifications with changed URLs may remain; concurrent writers can still race. Attachments and files remain to protect potentially shared media, and attachments may be public. Backups, exports and emails are not erased. Not approved for production.
