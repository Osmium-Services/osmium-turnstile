INSERT INTO {TABLE} (day, form, outcome, total) VALUES (CURDATE(), :form, :outcome, 1)
ON DUPLICATE KEY UPDATE total = total + 1
