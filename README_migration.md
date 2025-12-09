Migration instructions for `historico_cliente`

Purpose
- The application detected mismatched schema in the `historico_cliente` table. To avoid making schema changes automatically at runtime, we provide a migration SQL file for you to run manually.

Files
- `migrations/001_add_historico_columns.sql` — SQL to add the required columns.
- `tests/test_historico_endpoint.php` — PHP test that posts a sample historico to the endpoint.

How to apply the migration
1. Prefer using phpMyAdmin or MySQL CLI. For MySQL 8+:

   - Open a terminal and run:

     mysql -u root -p awm < migrations/001_add_historico_columns.sql

   - Or open `migrations/001_add_historico_columns.sql` in phpMyAdmin and execute the SQL.

2. If your MySQL version does NOT support `ADD COLUMN IF NOT EXISTS`, either:
   - Run the individual `ALTER TABLE ... ADD COLUMN` lines from the fallback section of the SQL file.
   - Or use the phpMyAdmin UI to add the columns manually with the types shown.

Testing the endpoint after migration
- Using the included PHP test (requires PHP CLI and the cURL extension):

  php tests/test_historico_endpoint.php

- Or use the browser UI: open the app, select a client and click "Salvar Relatório".

Notes
- After migration, `salvar_historico.php` will run normally and persist records.
- If you prefer I apply the migration for you, tell me and I will either run the SQL (if you confirm) or provide exact commands to run locally.
