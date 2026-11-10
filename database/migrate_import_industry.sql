-- Importação: associa brindes a uma indústria.
-- Aplicado pelo Installer::upgrade de forma idempotente.
ALTER TABLE items
  ADD COLUMN industry_id INT UNSIGNED NULL AFTER category_id,
  ADD KEY idx_items_industry (industry_id),
  ADD CONSTRAINT fk_items_industry FOREIGN KEY (industry_id) REFERENCES industries (id);
